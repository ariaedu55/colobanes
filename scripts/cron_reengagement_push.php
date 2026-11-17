<?php
/**
 * Relances push des utilisateurs inactifs (voir services/reengagement_push.php).
 * Déjà lancé automatiquement par scripts/cron_notifications_bundle.php.
 *
 * CLI :
 *   php scripts/cron_reengagement_push.php            (respecte plages horaires / intervalle)
 *   php scripts/cron_reengagement_push.php --force    (ignore plages horaires / intervalle)
 *   php scripts/cron_reengagement_push.php --dry-run  (aperçu, aucun envoi)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/services/reengagement_push.php';

$args = array_slice($argv, 1);
$dry = in_array('--dry-run', $args, true);
$force = $dry || in_array('--force', $args, true);

$result = reengagement_run($force, $dry);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit(!empty($result['ok']) ? 0 : 1);
