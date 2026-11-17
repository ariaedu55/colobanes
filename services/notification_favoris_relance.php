<?php
/**
 * Relances favoris (push) — 48 h, 4 j, puis chaque semaine.
 */

require_once __DIR__ . '/../includes/schema_notifications.php';
require_once __DIR__ . '/../models/model_notification_log.php';

function notification_favoris_relance_ensure_table(): bool
{
    if (notifications_table_exists('favoris_relance')) {
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
            CREATE TABLE IF NOT EXISTS `favoris_relance` (
              `user_id` INT(11) NOT NULL,
              `cycle_started_at` DATETIME NOT NULL,
              `derniere_favori_at` DATETIME NOT NULL,
              `derniere_relance_at` DATETIME NULL DEFAULT NULL,
              `relance_count` INT(11) NOT NULL DEFAULT 0,
              PRIMARY KEY (`user_id`),
              KEY `idx_favoris_relance_cycle` (`cycle_started_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        return notifications_table_exists('favoris_relance');
    } catch (PDOException $e) {
        error_log('[notification_favoris_relance_ensure_table] ' . $e->getMessage());
        return false;
    }
}

function notification_favoris_relance_reset(int $user_id): void
{
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !notification_favoris_relance_ensure_table()) {
        return;
    }
    global $db;
    try {
        $db->prepare('DELETE FROM favoris_relance WHERE user_id = :uid')->execute(['uid' => $user_id]);
    } catch (PDOException $e) {
        error_log('[notification_favoris_relance_reset] ' . $e->getMessage());
    }
}

/**
 * Nouveau favori ou mise à jour de la date d’ajout.
 */
function notification_favoris_relance_on_favori_add(int $user_id): void
{
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !notification_favoris_relance_ensure_table()) {
        return;
    }
    global $db;
    try {
        $db->prepare('
            INSERT INTO favoris_relance (user_id, cycle_started_at, derniere_favori_at, relance_count)
            VALUES (:uid, NOW(), NOW(), 0)
            ON DUPLICATE KEY UPDATE derniere_favori_at = NOW()
        ')->execute(['uid' => $user_id]);
    } catch (PDOException $e) {
        error_log('[notification_favoris_relance_on_favori_add] ' . $e->getMessage());
    }
}

/**
 * Nouveau favori après une relance → nouveau cycle.
 */
function notification_favoris_relance_sync_cycle(int $user_id, string $last_favori_at, ?string $derniere_relance_at): void
{
    $user_id = (int) $user_id;
    if ($user_id <= 0 || $last_favori_at === '' || !notification_favoris_relance_ensure_table()) {
        return;
    }
    if ($derniere_relance_at === null || $derniere_relance_at === '') {
        return;
    }
    if (strtotime($last_favori_at) > strtotime($derniere_relance_at)) {
        notification_favoris_relance_reset($user_id);
        notification_favoris_relance_on_favori_add($user_id);
    }
}

/**
 * Heures depuis le début du cycle pour la relance n° (index 0 = 48 h, 1 = 4 j, 2+ = +1 semaine).
 */
function notification_favoris_relance_heures_pour_index(int $index): int
{
    $cfg = notifications_config();
    $h48 = max(1, (int) ($cfg['favoris_relance_premiere_heures'] ?? 48));
    $h4j = max($h48, (int) ($cfg['favoris_relance_deuxieme_heures'] ?? 96));
    $hebdo = max(24, (int) ($cfg['favoris_relance_hebdo_heures'] ?? 168));
    if ($index <= 0) {
        return $h48;
    }
    if ($index === 1) {
        return $h4j;
    }
    return $h4j + ($index - 1) * $hebdo;
}
