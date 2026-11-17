<?php
/**
 * Worker CLI — relances et rappels (push FCM + e-mail)
 * Planifier : cron toutes les 15 min
 *   php /chemin/vers/scripts/run_notifications_worker.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once dirname(__DIR__) . '/services/notification_worker.php';
require_once dirname(__DIR__) . '/services/send_produit_cible_notification.php';

$stats = notification_worker_run_all();
echo json_encode(['ok' => true, 'stats' => $stats], JSON_UNESCAPED_UNICODE) . PHP_EOL;
