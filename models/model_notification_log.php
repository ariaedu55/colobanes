<?php
/**
 * Journal des notifications (anti-doublon).
 */
require_once __DIR__ . '/../includes/schema_notifications.php';

function notifications_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $path = dirname(__DIR__) . '/config/notifications.php';
    $cfg = is_readable($path) ? (require $path) : [];
    if (!is_array($cfg)) {
        $cfg = [];
    }
    return $cfg;
}

function notification_log_exists(string $dedupe_key): bool
{
    if (!notifications_ensure_tables()) {
        return false;
    }
    global $db;
    try {
        $st = $db->prepare('SELECT 1 FROM notification_log WHERE dedupe_key = :k LIMIT 1');
        $st->execute(['k' => $dedupe_key]);
        return (bool) $st->fetchColumn();
    } catch (PDOException $e) {
        error_log('[notification_log_exists] ' . $e->getMessage());
        return false;
    }
}

/**
 * @param array<string, mixed> $row
 */
function notification_log_insert(array $row): bool
{
    if (!notifications_ensure_tables()) {
        return false;
    }
    global $db;
    $dedupe = trim((string) ($row['dedupe_key'] ?? ''));
    if ($dedupe === '') {
        return false;
    }
    if (notification_log_exists($dedupe)) {
        return false;
    }
    try {
        $st = $db->prepare('
            INSERT INTO notification_log
                (type, dedupe_key, user_id, admin_id, produit_id, commande_id, channel, push_sent, email_sent, date_envoi)
            VALUES
                (:type, :dedupe_key, :user_id, :admin_id, :produit_id, :commande_id, :channel, :push_sent, :email_sent, NOW())
        ');
        $st->execute([
            'type' => (string) ($row['type'] ?? 'generic'),
            'dedupe_key' => $dedupe,
            'user_id' => isset($row['user_id']) && (int) $row['user_id'] > 0 ? (int) $row['user_id'] : null,
            'admin_id' => isset($row['admin_id']) && (int) $row['admin_id'] > 0 ? (int) $row['admin_id'] : null,
            'produit_id' => isset($row['produit_id']) && (int) $row['produit_id'] > 0 ? (int) $row['produit_id'] : null,
            'commande_id' => isset($row['commande_id']) && (int) $row['commande_id'] > 0 ? (int) $row['commande_id'] : null,
            'channel' => (string) ($row['channel'] ?? 'push_email'),
            'push_sent' => !empty($row['push_sent']) ? 1 : 0,
            'email_sent' => !empty($row['email_sent']) ? 1 : 0,
        ]);
        return true;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            return false;
        }
        error_log('[notification_log_insert] ' . $e->getMessage());
        return false;
    }
}

function notification_count_today_for_user(int $user_id, string $type): int
{
    if (!notifications_ensure_tables() || $user_id <= 0) {
        return 0;
    }
    global $db;
    try {
        $st = $db->prepare("
            SELECT COUNT(*) FROM notification_log
            WHERE user_id = :uid AND type = :type
              AND DATE(date_envoi) = CURDATE()
        ");
        $st->execute(['uid' => $user_id, 'type' => $type]);
        return (int) $st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
