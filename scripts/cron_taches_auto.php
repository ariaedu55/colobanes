<?php
/**
 * Exécute les tâches automatiques dues (voir services/taches_auto.php).
 * Lancé tout seul pendant les visites du site ; une crontab est facultative :
 *   php scripts/cron_taches_auto.php           (tâches dont c'est l'heure)
 *   php scripts/cron_taches_auto.php --tout    (toutes les tâches, maintenant)
 *   php scripts/cron_taches_auto.php --etat    (dernier résultat de chaque tâche)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/require_cli_only.php';
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../services/taches_auto.php';

$args = array_slice($argv, 1);

if (in_array('--etat', $args, true)) {
    $etat = taches_auto_etat_lire();
    foreach (taches_auto_liste() as $id => $t) {
        $e = $etat[$id] ?? null;
        printf("%-26s %s  %s\n", $id, $e ? ($e['ok'] ? 'OK    ' : 'ÉCHEC ') . $e['date'] : 'jamais', $e['resultat'] ?? '');
    }
    exit(0);
}

foreach (taches_auto_executer(in_array('--tout', $args, true)) as $id => $res) {
    echo $id . ' : ' . $res . "\n";
}
exit(0);
