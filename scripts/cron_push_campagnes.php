<?php
/**
 * Campagnes push (voir services/push_campagnes.php). Lancé automatiquement par les tâches auto ;
 * ce script sert à regarder ce qui partirait :
 *   php scripts/cron_push_campagnes.php --dry-run          (aperçu, rien n'est envoyé)
 *   php scripts/cron_push_campagnes.php --dry-run --force  (aperçu même hors plage horaire)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/require_cli_only.php';
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../services/push_campagnes.php';

$args = array_slice($argv, 1);
$r = push_campagnes_run(in_array('--force', $args, true), in_array('--dry-run', $args, true));
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
exit(empty($r['ok']) ? 1 : 0);
