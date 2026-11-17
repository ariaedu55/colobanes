<?php
/**
 * Cycle de relance panier abandonné (reset après commande ou panier vidé).
 */

require_once __DIR__ . '/../includes/schema_notifications.php';

function notification_panier_relance_reset(int $user_id): void
{
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !notifications_ensure_tables()) {
        return;
    }
    global $db;
    try {
        $db->prepare('DELETE FROM panier_relance WHERE user_id = :uid')->execute(['uid' => $user_id]);
    } catch (PDOException $e) {
        error_log('[notification_panier_relance_reset] ' . $e->getMessage());
    }
}

/**
 * Nouvelle activité panier après une relance → nouveau cycle (max 2 relances à nouveau).
 */
function notification_panier_relance_sync_cycle(int $user_id, string $last_add, ?string $derniere_relance_at): void
{
    $user_id = (int) $user_id;
    if ($user_id <= 0 || $last_add === '' || !notifications_ensure_tables()) {
        return;
    }
    if ($derniere_relance_at === null || $derniere_relance_at === '') {
        return;
    }
    if (strtotime($last_add) > strtotime($derniere_relance_at)) {
        notification_panier_relance_reset($user_id);
    }
}
