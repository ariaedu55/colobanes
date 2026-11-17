<?php
/**
 * Planification automatique du bundle notifications (sans crontab manuelle).
 */

function notifications_cron_auto_enabled(): bool
{
    if (!function_exists('notifications_config')) {
        require_once __DIR__ . '/../models/model_notification_log.php';
    }
    $cfg = notifications_config();
    return !empty($cfg['notifications_cron_auto_on_request']);
}

function notifications_cron_auto_request_eligible(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }
    $uri = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if (strpos($uri, '/admin/') === 0 || strpos($uri, '/super_admin/') === 0) {
        return false;
    }
    if (strpos($uri, '/scripts/') !== false) {
        return false;
    }
    if (strpos($uri, 'cron_notifications_bundle.php') !== false) {
        return false;
    }
    return true;
}

function notifications_cron_auto_on_shutdown(): void
{
    if (!notifications_cron_auto_enabled() || !notifications_cron_auto_request_eligible()) {
        return;
    }
    if (!function_exists('notifications_cron_spawn_bundle')) {
        require_once __DIR__ . '/../services/notifications_cron_bundle.php';
    }
    notifications_cron_spawn_bundle();
}

function notifications_cron_register_auto(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;
    register_shutdown_function('notifications_cron_auto_on_shutdown');
}
