<?php
/**
 * Tests — catégories populaires personnalisées (recherche → rayon).
 *
 * Usage : php scripts/test_marketplace_personalization_categories.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/includes/schema_client_comportement.php';
require_once $root . '/models/model_client_comportement.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['catalogue_visiteur_id'] = bin2hex(random_bytes(8));
require_once $root . '/includes/catalogue_shuffle.php';
$vk = catalogue_shuffle_visiteur_cle();

require_once $root . '/includes/marketplace_personalization.php';

$pass = 0;
$fail = 0;

function tp(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo '  [OK]   ' . $label . ($detail !== '' ? ' — ' . $detail : '') . "\n";
    } else {
        $fail++;
        echo '  [FAIL] ' . $label . ($detail !== '' ? ' — ' . $detail : '') . "\n";
    }
}

echo "=== Test personnalisation catégories populaires ===\n\n";

client_comportement_ensure_tables();

$terme = 'technolog';
$rayon_ids = client_comportement_rayon_ids_from_terme($terme, 3);
tp('inférence rayon depuis terme', $rayon_ids !== [], 'ids=' . implode(',', $rayon_ids));

$target_gid = $rayon_ids[0] ?? 0;

if ($target_gid > 0) {
    client_comportement_apply_recherche_rayon_scores(null, $vk, $terme);
    $tops = client_comportement_top_rayons(null, $vk, 3);
    $found = false;
    foreach ($tops as $t) {
        if ((int) ($t['categorie_generale_id'] ?? 0) === $target_gid && (float) ($t['score'] ?? 0) > 0) {
            $found = true;
            break;
        }
    }
    tp('score rayon après recherche simulée', $found, 'gid=' . $target_gid);
} else {
    tp('score rayon après recherche simulée', false, 'aucun rayon match BDD pour « technolog »');
}

$nav_fake = [
    ['id' => 1, 'nom' => 'A'],
    ['id' => 2, 'nom' => 'B'],
    ['id' => 3, 'nom' => 'C'],
];
if ($target_gid > 0) {
    $nav_fake[] = ['id' => $target_gid, 'nom' => 'Rayon cible'];
}

$scores = marketplace_personalization_interest_scores_for_categories([
    'user_id' => null,
    'visiteur_key' => $vk,
]);
tp('scores intérêt non vides', $scores !== []);

$ordered = marketplace_personalization_order_categories($nav_fake);
$first_id = (int) ($ordered[0]['id'] ?? 0);
if ($target_gid > 0) {
    tp('rayon cible en première position', $first_id === $target_gid, 'first=' . $first_id);
} else {
    tp('rayon cible en première position', true, 'skip (pas de gid)');
}

try {
    $db->prepare('DELETE FROM client_interets_rayon WHERE visiteur_key = :vk')->execute(['vk' => $vk]);
    $db->prepare('DELETE FROM client_evenements WHERE visiteur_key = :vk')->execute(['vk' => $vk]);
    echo "\nNettoyage données test OK.\n";
} catch (Throwable $e) {
    echo "\nNettoyage incomplet : " . $e->getMessage() . "\n";
}

echo "\n=== Résultat : $pass OK / $fail FAIL ===\n";
exit($fail > 0 ? 1 : 0);
