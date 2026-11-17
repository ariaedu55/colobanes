<?php
/**
 * Relance utilisateurs inactifs — mise en file segmentée (envoi via notification_segment_queue).
 */

require_once __DIR__ . '/../includes/schema_notifications.php';
require_once __DIR__ . '/../models/model_client_comportement.php';
require_once __DIR__ . '/../models/model_notification_log.php';
require_once __DIR__ . '/notification_engagement_segments.php';
require_once __DIR__ . '/notification_segment_queue.php';
require_once __DIR__ . '/../includes/site_url.php';
require_once __DIR__ . '/../includes/app_base.php';
require_once __DIR__ . '/../models/model_fcm.php';

function notification_engagement_inactif_ensure_table(): bool
{
    if (notifications_table_exists('engagement_relance')) {
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
            CREATE TABLE IF NOT EXISTS `engagement_relance` (
              `user_id` INT(11) NOT NULL,
              `cycle_started_at` DATETIME NOT NULL,
              `derniere_relance_at` DATETIME NULL DEFAULT NULL,
              `relance_count` INT(11) NOT NULL DEFAULT 0,
              PRIMARY KEY (`user_id`),
              KEY `idx_engagement_relance_cycle` (`cycle_started_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        return notifications_table_exists('engagement_relance');
    } catch (PDOException $e) {
        error_log('[notification_engagement_inactif_ensure_table] ' . $e->getMessage());
        return false;
    }
}

function notification_engagement_inactif_reset(int $user_id): void
{
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !notification_engagement_inactif_ensure_table()) {
        return;
    }
    global $db;
    try {
        $db->prepare('DELETE FROM engagement_relance WHERE user_id = :uid')->execute(['uid' => $user_id]);
    } catch (PDOException $e) {
        error_log('[notification_engagement_inactif_reset] ' . $e->getMessage());
    }
}

function notification_engagement_user_can_notify(int $user_id): bool
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    $tokens = get_fcm_tokens_by_user($user_id);
    if (!empty($tokens)) {
        return true;
    }
    require_once __DIR__ . '/../models/model_users.php';
    $u = get_user_by_id($user_id);
    $email = trim((string) ($u['email'] ?? ''));
    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * @return int Nombre d’utilisateurs mis en file
 */
function notification_worker_engagement_inactif(): int
{
    if (!notification_engagement_inactif_ensure_table()) {
        return 0;
    }
    if (!client_comportement_ensure_tables() || !notification_segment_queue_ensure_table()) {
        return 0;
    }

    $cfg = notifications_config();
    $inactif_jours = max(1, (int) ($cfg['engagement_inactif_jours'] ?? 7));
    $interval_jours = max(1, (int) ($cfg['engagement_inactif_interval_jours'] ?? 7));
    $sync_limit = max(50, (int) ($cfg['engagement_inactif_sync_limit_per_run'] ?? 300));

    global $db;
    $enqueued = 0;
    $base = rtrim(get_site_base_url(), '/');
    $year_week = date('o-\WW');

    try {
        $st = $db->prepare("
            SELECT u.id AS user_id, ce.last_evt
            FROM users u
            LEFT JOIN (
                SELECT user_id, MAX(date_evenement) AS last_evt
                FROM client_evenements
                WHERE user_id IS NOT NULL
                GROUP BY user_id
            ) ce ON ce.user_id = u.id
            WHERE u.statut = 'actif'
              AND (ce.last_evt IS NULL OR ce.last_evt < DATE_SUB(NOW(), INTERVAL :j DAY))
            LIMIT " . (int) $sync_limit . "
        ");
        $st->execute(['j' => $inactif_jours]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[notification_worker_engagement_inactif] ' . $e->getMessage());
        return 0;
    }

    $st_rel = $db->prepare('
        SELECT cycle_started_at, derniere_relance_at, relance_count
        FROM engagement_relance WHERE user_id = :uid
    ');
    $st_ins = $db->prepare('
        INSERT INTO engagement_relance (user_id, cycle_started_at, relance_count)
        VALUES (:uid, :cycle, 0)
        ON DUPLICATE KEY UPDATE user_id = user_id
    ');
    $st_cmd = $db->prepare('
        SELECT COUNT(*) FROM commandes
        WHERE user_id = :uid AND date_commande >= :since AND statut NOT IN (\'annulee\')
    ');
    $st_pending = $db->prepare('
        SELECT 1 FROM notification_segment_queue
        WHERE user_id = :uid AND campaign = \'engagement_inactif\' AND sent_at IS NULL
        LIMIT 1
    ');

    foreach ($rows as $row) {
        $user_id = (int) ($row['user_id'] ?? 0);
        if ($user_id <= 0 || !notification_engagement_user_can_notify($user_id)) {
            continue;
        }

        $last_evt = isset($row['last_evt']) && $row['last_evt'] !== null && $row['last_evt'] !== ''
            ? (string) $row['last_evt']
            : null;
        $cycle_start_default = $last_evt ?? date('Y-m-d H:i:s', strtotime('-' . $inactif_jours . ' days'));

        $st_rel->execute(['uid' => $user_id]);
        $rel = $st_rel->fetch(PDO::FETCH_ASSOC);
        if (!$rel || empty($rel['cycle_started_at'])) {
            $st_ins->execute(['uid' => $user_id, 'cycle' => $cycle_start_default]);
            $st_rel->execute(['uid' => $user_id]);
            $rel = $st_rel->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$rel || empty($rel['cycle_started_at'])) {
            continue;
        }

        $cycle_started_at = (string) $rel['cycle_started_at'];
        $derniere_relance = isset($rel['derniere_relance_at']) && $rel['derniere_relance_at'] !== null && $rel['derniere_relance_at'] !== ''
            ? (string) $rel['derniere_relance_at']
            : null;

        $st_cmd->execute(['uid' => $user_id, 'since' => $cycle_started_at]);
        if ((int) $st_cmd->fetchColumn() > 0) {
            notification_engagement_inactif_reset($user_id);
            continue;
        }

        if ($derniere_relance !== null) {
            $elapsed = (time() - strtotime($derniere_relance)) / 86400;
            if ($elapsed < $interval_jours) {
                continue;
            }
        }

        $dedupe = 'engagement_inactif:' . $user_id . ':' . $year_week;
        if (notification_log_exists($dedupe)) {
            continue;
        }

        $st_pending->execute(['uid' => $user_id]);
        if ($st_pending->fetchColumn()) {
            continue;
        }

        $gid = notification_engagement_primary_gid_for_user($user_id);
        $copy = notification_engagement_copy_inactif($gid);
        $link_path = notification_engagement_link_rayon($gid);
        $link_abs = strpos($link_path, 'http') === 0 ? $link_path : $base . (strpos($link_path, '/') === 0 ? $link_path : '/' . $link_path);

        $scheduled = notification_segment_queue_schedule_at($gid, 'engagement_inactif', null);
        $ok = notification_segment_queue_enqueue(
            $user_id,
            $gid,
            'engagement_inactif',
            $dedupe,
            [
                'title' => $copy['title'],
                'body' => $copy['body'],
                'email_subject' => $copy['email_subject'],
                'email_html' => $copy['email_html'],
                'link_abs' => $link_abs,
                'log_type' => 'engagement_inactif',
                'data' => [
                    'type' => 'engagement_inactif',
                    'link' => $link_path,
                    'redirect_url' => $link_abs,
                    'categorie_generale_id' => (string) $gid,
                    'tag' => 'engagement-inactif-' . $gid,
                ],
                'email_meta' => [
                    'type' => 'engagement_inactif',
                    'categorie_generale_id' => $gid,
                ],
            ],
            null,
            $scheduled
        );
        if ($ok) {
            $enqueued++;
        }
    }

    return $enqueued;
}
