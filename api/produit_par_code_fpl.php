<?php
/**
 * API JSON : recherche par code FPL — réservée aux administrateurs connectés
 */

require_once __DIR__ . '/../includes/require_admin_web.php';

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../includes/admin_route_access.php';

// Vendeur : uniquement les produits de sa boutique (null = rôle plateforme, accès global)
$vf_api = admin_vendeur_filter_id();

$code = isset($_GET['code']) ? trim((string) $_GET['code']) : '';
if ($code === '' && isset($_GET['q'])) {
    $code = trim((string) $_GET['q']);
}

if ($code === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Paramètre code ou q requis (ex. FPL000042 ou 00151 pour les 5 derniers chiffres).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!produits_has_column('identifiant_interne')) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Identifiants produits non disponibles (migration base de données).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/site_url.php';
$base = get_site_base_url();

function api_produit_fpl_format_row(array $p, $base_url)
{
    return [
        'id' => (int) $p['id'],
        'identifiant_interne' => $p['identifiant_interne'] ?? '',
        'nom' => $p['nom'] ?? '',
        'prix' => isset($p['prix']) ? (float) $p['prix'] : 0,
        'prix_promotion' => isset($p['prix_promotion']) && $p['prix_promotion'] !== null ? (float) $p['prix_promotion'] : null,
        'stock' => isset($p['stock']) ? (int) $p['stock'] : 0,
        'statut' => $p['statut'] ?? '',
        'image_principale' => $p['image_principale'] ?? '',
        'url_fiche' => $base_url . '/produit.php?id=' . (int) $p['id'],
    ];
}

// Recherche rapide : exactement 5 chiffres = fin du numéro interne (hors préfixe FPL)
if (preg_match('/^\d{5}$/', $code)) {
    $liste = get_produits_by_identifiant_suffix_5_chiffres($code, 0, 50, false, $vf_api);
    if (empty($liste)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Aucun produit pour ces 5 chiffres.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $out = [];
    foreach ($liste as $row) {
        $out[] = api_produit_fpl_format_row($row, $base);
    }
    echo json_encode([
        'success' => true,
        'match_type' => 'suffix_5_digits',
        'suffix' => $code,
        'count' => count($out),
        'produits' => $out,
        // Rétrocompatibilité : un seul résultat = clé produit encore présente
        'produit' => count($out) === 1 ? $out[0] : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$code = strtoupper($code);
if (!produit_identifiant_interne_is_valid_format($code)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Utilisez le code complet (3 lettres + 6 chiffres), ou exactement 5 chiffres pour la recherche rapide.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$p = get_produit_by_identifiant_interne($code, false);
if ($p && $vf_api !== null && (int) ($p['admin_id'] ?? 0) !== $vf_api) {
    $p = false;
}
if (!$p) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Aucun produit pour cette référence.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$row = api_produit_fpl_format_row($p, $base);
echo json_encode([
    'success' => true,
    'match_type' => 'full_code',
    'produit' => $row,
    'produits' => [$row],
    'count' => 1,
], JSON_UNESCAPED_UNICODE);
