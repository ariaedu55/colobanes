<?php
/**
 * File d’attente notifications par segment (rayon) — envoi par lots.
 */

require_once __DIR__ . '/../includes/schema_notifications.php';
require_once __DIR__ . '/../models/model_notification_log.php';
require_once __DIR__ . '/notification_dispatch.php';

function notification_segment_queue_ensure_table(): bool
{
    if (notifications_table_exists('notification_segment_queue')) {
        return true;
    }
    if (!notifications_ensure_tables()) {
        return false;
    }
    global $db;
    if (!$db) {
        return false;
    }
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `notification_segment_queue` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `user_id` INT(11) NOT NULL,
              `categorie_generale_id` INT(11) NOT NULL DEFAULT 0,
              `campaign` VARCHAR(32) NOT NULL,
              `produit_id` INT(11) NULL DEFAULT NULL,
              `dedupe_key` VARCHAR(191) NOT NULL,
              `payload_json` TEXT NOT NULL,
              `scheduled_send_at` DATETIME NOT NULL,
              `sent_at` DATETIME NULL DEFAULT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_segment_queue_dedupe` (`dedupe_key`),
              KEY `idx_segment_queue_due` (`sent_at`, `scheduled_send_at`),
              KEY `idx_segment_queue_segment_due` (`categorie_generale_id`, `sent_at`, `scheduled_send_at`),
              KEY `idx_segment_queue_campaign` (`campaign`, `produit_id`, `sent_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        return notifications_table_exists('notification_segment_queue');
    } catch (PDOException $e) {
        error_log('[notification_segment_queue_ensure_table] ' . $e->getMessage());
        return false;
    }
}

function notification_segment_queue_dispatch_config(): array
{
    $cfg = notifications_config();
    return [
        'batch_per_run' => max(1, min(200, (int) ($cfg['engagement_dispatch_batch_per_run'] ?? 25))),
        'spread_hours' => max(1, (int) ($cfg['engagement_dispatch_spread_hours'] ?? 168)),
        'max_per_segment_per_run' => max(1, min(50, (int) ($cfg['engagement_dispatch_max_per_segment_per_run'] ?? 5))),
        'cron_interval_minutes' => max(5, min(120, (int) ($cfg['notifications_cron_interval_minutes'] ?? 15))),
    ];
}

/**
 * Créneau d’envoi pour un nouvel élément (étalement par segment / campagne).
 */
function notification_segment_queue_schedule_at(
    int $segment_gid,
    string $campaign,
    ?int $produit_id = null
): string {
    $segment_gid = max(0, $segment_gid);
    $campaign = trim($campaign);
    $produit_id = $produit_id !== null && $produit_id > 0 ? (int) $produit_id : null;

    $d = notification_segment_queue_dispatch_config();
    $batch = $d['batch_per_run'];
    $spread_minutes = $d['spread_hours'] * 60;
    $cron_min = $d['cron_interval_minutes'];

    global $db;
    $pending_count = 0;
    try {
        if ($produit_id !== null && $campaign === 'produit_cible') {
            $st = $db->prepare('
                SELECT COUNT(*) FROM notification_segment_queue
                WHERE sent_at IS NULL AND campaign = :c AND produit_id = :pid
                  AND categorie_generale_id = :gid
            ');
            $st->execute(['c' => $campaign, 'pid' => $produit_id, 'gid' => $segment_gid]);
        } else {
            $st = $db->prepare('
                SELECT COUNT(*) FROM notification_segment_queue
                WHERE sent_at IS NULL AND campaign = :c AND categorie_generale_id = :gid
            ');
            $st->execute(['c' => $campaign, 'gid' => $segment_gid]);
        }
        $pending_count = (int) $st->fetchColumn();
    } catch (PDOException $e) {
        error_log('[notification_segment_queue_schedule_at] ' . $e->getMessage());
    }

    $slot = (int) floor($pending_count / $batch);
    $delay_minutes = min($spread_minutes, $slot * $cron_min);
    $ts = time() + ($delay_minutes * 60);

    return date('Y-m-d H:i:s', $ts);
}

/**
 * @param array<string, mixed> $payload
 */
function notification_segment_queue_enqueue(
    int $user_id,
    int $segment_gid,
    string $campaign,
    string $dedupe_key,
    array $payload,
    ?int $produit_id = null,
    ?string $scheduled_send_at = null
): bool {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !notification_segment_queue_ensure_table()) {
        return false;
    }
    $dedupe_key = trim($dedupe_key);
    if ($dedupe_key === '') {
        return false;
    }
    if (notification_log_exists($dedupe_key)) {
        return false;
    }

    global $db;
    try {
        $st = $db->prepare('SELECT 1 FROM notification_segment_queue WHERE dedupe_key = :k LIMIT 1');
        $st->execute(['k' => $dedupe_key]);
        if ($st->fetchColumn()) {
            return false;
        }
    } catch (PDOException $e) {
        return false;
    }

    if ($scheduled_send_at === null || $scheduled_send_at === '') {
        $scheduled_send_at = notification_segment_queue_schedule_at($segment_gid, $campaign, $produit_id);
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    $produit_id = $produit_id !== null && $produit_id > 0 ? (int) $produit_id : null;

    try {
        $st = $db->prepare('
            INSERT INTO notification_segment_queue
                (user_id, categorie_generale_id, campaign, produit_id, dedupe_key, payload_json, scheduled_send_at)
            VALUES
                (:uid, :gid, :camp, :pid, :dedupe, :payload, :sched)
        ');
        $st->execute([
            'uid' => $user_id,
            'gid' => max(0, $segment_gid),
            'camp' => $campaign,
            'pid' => $produit_id,
            'dedupe' => $dedupe_key,
            'payload' => $json,
            'sched' => $scheduled_send_at,
        ]);
        return true;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            return false;
        }
        error_log('[notification_segment_queue_enqueue] ' . $e->getMessage());
        return false;
    }
}

/**
 * @return array{sent: int, skipped: int, by_campaign: array<string, int>}
 */
function notification_segment_queue_dispatch_batch(): array
{
    $stats = ['sent' => 0, 'skipped' => 0, 'by_campaign' => []];
    if (!notification_segment_queue_ensure_table()) {
        return $stats;
    }

    $d = notification_segment_queue_dispatch_config();
    $batch_max = $d['batch_per_run'];
    $per_seg_max = $d['max_per_segment_per_run'];

    global $db;
    try {
        $st = $db->query('
            SELECT id, user_id, categorie_generale_id, campaign, produit_id, dedupe_key, payload_json
            FROM notification_segment_queue
            WHERE sent_at IS NULL AND scheduled_send_at <= NOW()
            ORDER BY categorie_generale_id ASC, scheduled_send_at ASC, id ASC
            LIMIT 2000
        ');
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[notification_segment_queue_dispatch_batch] ' . $e->getMessage());
        return $stats;
    }

    if ($rows === []) {
        return $stats;
    }

    $by_gid = [];
    foreach ($rows as $row) {
        $gid = (int) ($row['categorie_generale_id'] ?? 0);
        if (!isset($by_gid[$gid])) {
            $by_gid[$gid] = [];
        }
        $by_gid[$gid][] = $row;
    }

    $picked = [];
    $counts_per_gid = [];
    while (count($picked) < $batch_max) {
        $progress = false;
        foreach ($by_gid as $gid => $list) {
            if ($list === []) {
                continue;
            }
            $gid_key = (string) $gid;
            if (($counts_per_gid[$gid_key] ?? 0) >= $per_seg_max) {
                continue;
            }
            $picked[] = array_shift($by_gid[$gid]);
            $counts_per_gid[$gid_key] = ($counts_per_gid[$gid_key] ?? 0) + 1;
            $progress = true;
            if (count($picked) >= $batch_max) {
                break;
            }
        }
        if (!$progress) {
            break;
        }
    }

    $st_sent = $db->prepare('UPDATE notification_segment_queue SET sent_at = NOW() WHERE id = :id AND sent_at IS NULL');
    $st_eng_upd = null;

    foreach ($picked as $row) {
        $id = (int) ($row['id'] ?? 0);
        $user_id = (int) ($row['user_id'] ?? 0);
        $dedupe = (string) ($row['dedupe_key'] ?? '');
        $campaign = (string) ($row['campaign'] ?? '');
        if ($id <= 0 || $user_id <= 0 || $dedupe === '') {
            $stats['skipped']++;
            continue;
        }

        if (notification_log_exists($dedupe)) {
            $st_sent->execute(['id' => $id]);
            $stats['skipped']++;
            continue;
        }

        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            $stats['skipped']++;
            continue;
        }

        $title = (string) ($payload['title'] ?? '');
        $body = (string) ($payload['body'] ?? '');
        $email_subject = (string) ($payload['email_subject'] ?? '');
        $email_html = (string) ($payload['email_html'] ?? '');
        $link_abs = (string) ($payload['link_abs'] ?? '');
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        $log_type = (string) ($payload['log_type'] ?? $campaign);
        $email_meta = isset($payload['email_meta']) && is_array($payload['email_meta']) ? $payload['email_meta'] : [];

        $disp = notification_dispatch_to_user(
            $user_id,
            $title,
            $body,
            $data,
            '',
            $email_subject,
            $email_html,
            $link_abs,
            $email_meta
        );

        if (!$disp['push'] && !$disp['email']) {
            $stats['skipped']++;
            continue;
        }

        $log_row = [
            'type' => $log_type,
            'dedupe_key' => $dedupe,
            'user_id' => $user_id,
            'push_sent' => $disp['push'],
            'email_sent' => $disp['email'],
        ];
        if (!empty($payload['produit_id'])) {
            $log_row['produit_id'] = (int) $payload['produit_id'];
        }
        if (!empty($payload['admin_id'])) {
            $log_row['admin_id'] = (int) $payload['admin_id'];
        }

        if (!notification_log_insert($log_row)) {
            $stats['skipped']++;
            continue;
        }

        $st_sent->execute(['id' => $id]);

        if ($campaign === 'engagement_inactif') {
            if ($st_eng_upd === null) {
                $st_eng_upd = $db->prepare('
                    UPDATE engagement_relance
                    SET derniere_relance_at = NOW(), relance_count = relance_count + 1
                    WHERE user_id = :uid
                ');
            }
            $st_eng_upd->execute(['uid' => $user_id]);
        }

        $stats['sent']++;
        if (!isset($stats['by_campaign'][$campaign])) {
            $stats['by_campaign'][$campaign] = 0;
        }
        $stats['by_campaign'][$campaign]++;
    }

    return $stats;
}
