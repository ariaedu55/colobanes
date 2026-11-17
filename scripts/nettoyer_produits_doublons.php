<?php
/**
 * Retire de la plateforme les produits en double (même boutique, même nom, même description, même prix).
 * Pour chaque groupe, un seul produit reste visible : le plus vendu, sinon le plus ancien.
 * Les autres passent en statut « inactif » (masqués, pas supprimés : l'historique des commandes est conservé).
 *
 * CLI :
 *   php scripts/nettoyer_produits_doublons.php              (aperçu, aucune modification)
 *   php scripts/nettoyer_produits_doublons.php --appliquer  (masque les doublons)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/require_cli_only.php';

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/includes/produit_doublons.php';

$appliquer = in_array('--appliquer', array_slice($argv, 1), true);

$groupes = produits_doublons_groupes();
if (!$groupes) {
    echo "Aucun produit en double visible sur la plateforme.\n";
    exit(0);
}

$total = 0;
$ids = [];
foreach ($groupes as $g) {
    $garde = $g['garde'];
    echo "« {$garde['nom']} » (boutique {$garde['admin_id']}) : on garde #{$garde['id']}";
    echo (int) $garde['ventes'] > 0 ? " ({$garde['ventes']} vendus)" : '';
    echo "\n";
    foreach ($g['retires'] as $r) {
        echo "    → masqué : #{$r['id']} (créé le {$r['date_creation']})\n";
        $ids[] = (int) $r['id'];
        $total++;
    }
}

if (!$appliquer) {
    echo "\nAperçu : {$total} produit(s) seraient masqués. Relancez avec --appliquer pour les retirer.\n";
    exit(0);
}

$total = produits_doublons_masquer();
echo "\n{$total} produit(s) en double retirés de la plateforme (statut « inactif »).\n";
exit(0);
