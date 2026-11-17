<?php
/**
 * Tâche planifiée unique — relances + envoi des e-mails en file d'attente.
 *
 * CLI (crontab toutes les 15 min) :
 *   php /chemin/vers/fouta-main/scripts/cron_notifications_bundle.php
 *
 * HTTP (cron hébergeur, clé dans config/notifications.php) :
 *   GET /scripts/cron_notifications_bundle.php?key=VOTRE_SECRET
 *
 * Sans crontab : activé par défaut via notifications_cron_auto_on_request
 * (lancement en arrière-plan lors des visites sur le site).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/services/notifications_cron_bundle.php';

$is_cli = (PHP_SAPI === 'cli');

if (!$is_cli) {
    if (!notifications_cron_http_key_valid()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Forbidden');
    }
    header('Content-Type: application/json; charset=utf-8');
}

// Exécution explicite (CLI, URL cron) : toujours lancer. L’intervalle ne s’applique qu’au déclenchement auto sur les pages.
$result = notifications_cron_bundle_run(true);

echo json_encode($result, JSON_UNESCAPED_UNICODE) . ($is_cli ? PHP_EOL : '');
exit(!empty($result['ok']) ? 0 : 1);
