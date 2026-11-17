<?php
/**
 * Accueil — lot suivant de la grille « Nos produits » (défilement infini).
 * Parcourt le catalogue dans l'ordre mélangé (seed) et saute les produits déjà affichés
 * (la première page de l'accueil peut contenir des recommandations personnalisées).
 * Les cartes sont rendues avec le même partial que index.php.
 *
 * GET offset (curseur dans l'ordre mélangé), seed (int) → JSON { html, next_offset, done }
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/api_rate_limit.php';
if (!api_rate_limit_check('home_produits_html', 90, 60)) {
    api_rate_limit_deny_json();
}

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../includes/produit_boutique_line.php';
require_once __DIR__ . '/../includes/site_csrf.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/app_base.php';
require_once __DIR__ . '/../models/model_produits.php';

$lot = 24;
$cursor = max(0, min(5000, (int) ($_GET['offset'] ?? 0)));
$seed = max(1, (int) ($_GET['seed'] ?? 1));

/* Produits déjà affichés pour cette visite (même seed que l'accueil) */
$etat = $_SESSION['home_infinite'] ?? null;
$vus = (is_array($etat) && (int) ($etat['seed'] ?? 0) === $seed) ? array_flip(array_map('intval', (array) ($etat['vus'] ?? []))) : [];

$produits = [];
$epuise = false;
while (count($produits) < $lot && !$epuise) {
    $page = get_all_produits_paginated($cursor, $lot, null, $seed);
    $cursor += count($page);
    if (count($page) < $lot) {
        $epuise = true;
    }
    foreach ($page as $p) {
        $id = (int) ($p['id'] ?? 0);
        if ($id <= 0 || isset($vus[$id])) {
            continue;
        }
        $vus[$id] = true;
        $produits[] = $p;
    }
}

if (is_array($etat) && (int) ($etat['seed'] ?? 0) === $seed) {
    $_SESSION['home_infinite']['vus'] = array_keys($vus);
}

if (!empty($produits) && file_exists(__DIR__ . '/../models/model_produits_avis.php')) {
    require_once __DIR__ . '/../models/model_produits_avis.php';
    if (function_exists('produits_avis_enrich_products')) {
        $produits = produits_avis_enrich_products($produits);
    }
}

/* Les cartes renvoient vers l'accueil après un ajout au panier */
$return_url = app_url('/index.php');
ob_start();
foreach ($produits as $produit) {
    require __DIR__ . '/../includes/partials/home_mp_product_card.php';
}
$html = ob_get_clean();

echo json_encode([
    'html' => $html,
    'next_offset' => $cursor,
    'done' => $epuise,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
