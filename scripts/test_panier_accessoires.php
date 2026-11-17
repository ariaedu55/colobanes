<?php
/**
 * Test CLI — accessoires panier (profils + candidats).
 * Usage: php scripts/test_panier_accessoires.php [produit_id ...]
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/includes/panier_accessoires_engine.php';

$ids = [];
foreach (array_slice($argv, 1) as $arg) {
    if (is_numeric($arg)) {
        $ids[] = (int) $arg;
    }
}
if ($ids === []) {
    $row = $db->query("
        SELECT id FROM produits WHERE statut = 'actif'
        AND (LOWER(nom) LIKE '%iphone%' OR LOWER(nom) LIKE '%samsung%' OR LOWER(nom) LIKE '%couche%')
        LIMIT 3
    ")->fetchAll(PDO::FETCH_COLUMN);
    $ids = array_map('intval', $row ?: []);
}
if ($ids === []) {
    echo "Aucun produit de test. Passez des IDs : php scripts/test_panier_accessoires.php 33 40\n";
    exit(1);
}

echo "=== Test accessoires panier ===\n\n";

$panier_items = [];
foreach ($ids as $pid) {
    $p = get_produit_by_id($pid);
    if (!$p) {
        echo "Produit #$pid introuvable\n";
        continue;
    }
    echo "Ligne panier : #{$pid} — " . ($p['nom'] ?? '') . "\n";
    $profiles = panier_accessoires_profiles_for_produit($p);
    foreach ($profiles as $pr) {
        echo "  profil : " . ($pr['id'] ?? '?') . " (prio " . ($pr['priority'] ?? 0) . ")\n";
    }
    $panier_items[] = array_merge($p, ['produit_id' => $pid, 'quantite' => 1, 'panier_id' => $pid]);
}

echo "\n--- Besoins agrégés ---\n";
$needs = panier_accessoires_collect_needs($panier_items);
echo "Chips : " . implode(', ', $needs['labels']) . "\n";
echo "Termes (" . count($needs['termes']) . ") : " . implode(', ', array_slice(array_keys($needs['termes']), 0, 12)) . "\n";
echo "Montage : " . ($needs['montage'] ? 'oui' : 'non') . "\n";

echo "\n--- Résolution ---\n";
$result = panier_accessoires_resolve_for_cart($panier_items);
echo "Titre : " . ($result['title'] ?? '') . "\n";
echo "Exemple bundle : " . ($result['bundle_example'] ?? '') . "\n";
echo "Produits suggérés : " . count($result['produits']) . "\n";
foreach ($result['produits'] as $pr) {
    $slot = isset($pr['panier_bundle_slot']) ? ' [' . $pr['panier_bundle_slot'] . ']' : '';
    echo "  #" . ($pr['id'] ?? '') . ' ' . ($pr['nom'] ?? '') . $slot . "\n";
}

$in_cart = array_flip($ids);
$leak = false;
foreach ($result['produits'] as $pr) {
    if (isset($in_cart[(int) ($pr['id'] ?? 0)])) {
        $leak = true;
        echo "ERREUR : produit panier reproposé #" . $pr['id'] . "\n";
    }
}
echo $leak ? "FAIL\n" : "OK — aucun doublon panier\n";
exit($leak ? 1 : 0);
