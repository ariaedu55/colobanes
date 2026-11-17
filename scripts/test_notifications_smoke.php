<?php
/**
 * Smoke test notifications (XAMPP / CLI)
 * Usage:
 *   php scripts/test_notifications_smoke.php
 *   php scripts/test_notifications_smoke.php --run-worker
 *   php scripts/test_notifications_smoke.php --user-id=12 --simulate-panier
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$run_worker = in_array('--run-worker', $argv, true);
$simulate_panier = in_array('--simulate-panier', $argv, true);
$user_id = 0;
foreach ($argv as $arg) {
    if (strpos($arg, '--user-id=') === 0) {
        $user_id = (int) substr($arg, 10);
    }
}

require_once dirname(__DIR__) . '/conn/conn.php';
require_once dirname(__DIR__) . '/includes/schema_notifications.php';
notifications_ensure_tables();
require_once dirname(__DIR__) . '/services/send_produit_cible_notification.php';

echo "=== COLObanes — smoke test notifications ===\n\n";

$tables = ['notification_log', 'favoris_produit_snapshot', 'panier_relance', 'favoris_relance'];
foreach ($tables as $t) {
    $ok = notifications_table_exists($t) ? 'OK' : 'MANQUANT';
    echo "Table {$t}: {$ok}\n";
}

$tokens = notification_produit_recherche_tokens('Huile de coco bio 500ml');
echo "\nTokens recherche (exemple): " . implode(', ', $tokens) . "\n";

try {
    $st = $db->query('SELECT type, COUNT(*) AS n FROM notification_log GROUP BY type ORDER BY n DESC LIMIT 10');
    echo "\nDerniers volumes notification_log (par type):\n";
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        echo '  - ' . $row['type'] . ': ' . $row['n'] . "\n";
    }
} catch (PDOException $e) {
    echo "notification_log: " . $e->getMessage() . "\n";
}

if ($simulate_panier && $user_id > 0) {
    echo "\n--- Simulation panier abandonné (user {$user_id}) ---\n";
    echo "SQL suggéré (phpMyAdmin):\n";
    echo "  UPDATE panier SET date_ajout = DATE_SUB(NOW(), INTERVAL 25 HOUR) WHERE user_id = {$user_id};\n";
    echo "  DELETE FROM notification_log WHERE dedupe_key LIKE 'panier:{$user_id}:%';\n";
    echo "  DELETE FROM panier_relance WHERE user_id = {$user_id};\n";
}

echo "\nTest automatique (sans SQL manuel) :\n";
echo "  php scripts/test_notifications_auto.php\n";
echo "\nCron production (relances + e-mails) :\n";
echo "  php scripts/cron_notifications_bundle.php\n";

if ($run_worker) {
    echo "\n--- Exécution worker ---\n";
    require_once dirname(__DIR__) . '/services/notification_worker.php';
    $stats = notification_worker_run_all();
    echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

echo "\nTerminé.\n";
