<?php
/**
 * Suggestions de recherche catalogue (barre nav) — produits + catégories.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/api_rate_limit.php';
if (!api_rate_limit_check('search_suggestions', 90, 60)) {
    api_rate_limit_deny_json();
}
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_categories.php';
require_once __DIR__ . '/../includes/app_base.php';
require_once __DIR__ . '/../includes/image_optimizer.php';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$limit_products = isset($_GET['limit']) ? (int) $_GET['limit'] : 7;
if ($limit_products < 1 || $limit_products > 12) {
    $limit_products = 7;
}

if (mb_strlen($q, 'UTF-8') < 2) {
    echo json_encode(['success' => true, 'suggestions' => [], 'categories' => [], 'produits' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$boutique_admin_id = null;
if (!empty($_GET['boutique'])) {
    require_once __DIR__ . '/../models/model_admin.php';
    require_once __DIR__ . '/../includes/boutique_slug_redirect.php';
    $row = resolve_vendeur_by_boutique_slug(trim((string) $_GET['boutique']));
    if ($row && ($row['role'] ?? '') === 'vendeur' && ($row['statut'] ?? '') === 'actif') {
        $boutique_admin_id = (int) $row['id'];
    }
}

$categories_raw = search_categories_suggestions($q, 3);
$categories = [];
foreach ($categories_raw as $cat) {
    $type = (string) ($cat['type'] ?? '');
    $id = (int) ($cat['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }
    if ($type === 'category_generale') {
        $url = app_url('/categorie.php?generale=' . $id);
    } else {
        $url = app_url('/categorie.php?id=' . $id);
    }
    $categories[] = [
        'type' => $type,
        'id' => $id,
        'nom' => (string) ($cat['nom'] ?? ''),
        'subtitle' => (string) ($cat['subtitle'] ?? 'Catégorie'),
        'url' => $url,
    ];
}

$produits = search_produits_with_filters($q, null, null, null, 'date', 0, $limit_products, $boutique_admin_id);
$produits_out = [];
foreach ($produits as $produit) {
    $prix_base = (float) ($produit['prix'] ?? 0);
    $prix_promo = (float) ($produit['prix_promotion'] ?? 0);
    $en_promo = $prix_promo > 0 && $prix_promo < $prix_base;
    $prix = $en_promo ? $prix_promo : $prix_base;
    $pid = (int) ($produit['id'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    $produits_out[] = [
        'type' => 'produit',
        'id' => $pid,
        'nom' => (string) ($produit['nom'] ?? ''),
        'prix' => $prix,
        'prix_barre' => $en_promo ? $prix_base : 0,
        'image' => upload_image_url((string) ($produit['image_principale'] ?? ''), 'sm'),
        'url' => app_url('/produit.php?id=' . $pid . '&mp_ref=nav_search'),
    ];
}

$suggestions = array_merge($categories, $produits_out);

echo json_encode([
    'success' => true,
    'suggestions' => $suggestions,
    'categories' => $categories,
    'produits' => $produits_out,
], JSON_UNESCAPED_UNICODE);
