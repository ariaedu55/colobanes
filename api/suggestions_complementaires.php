<?php
/**
 * Produits complémentaires suggérés par l'IA.
 * POST JSON { mode: 'produit', produit_id }  → panneau « Ajouté au panier » (juste après un ajout)
 * POST JSON { mode: 'panier' }               → bloc « Complétez votre achat » du panier
 * Réponse : { success, html } (html vide = rien à proposer)
 *
 * Les pages n'appellent jamais l'IA elles-mêmes : c'est cette API, chargée après l'affichage, qui prépare
 * (et met en cache 30 jours) les compléments des produits que les règles du site ne connaissent pas.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/api_rate_limit.php';
require_once __DIR__ . '/../includes/panier_accessoires_engine.php';
require_once __DIR__ . '/../includes/assistant/assistant_ia_json.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

/* Appels acceptés uniquement depuis le site lui-même */
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
$origin_host = strtolower((string) parse_url($origin, PHP_URL_HOST));
$origin_port = parse_url($origin, PHP_URL_PORT);
if ($origin_port) {
    $origin_host .= ':' . $origin_port;
}
if ($origin === '' || $origin_host !== $host) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

if (!api_rate_limit_check('suggestions_complementaires', 20, 60)) {
    api_rate_limit_deny_json();
}

$input = json_decode((string) file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];
$mode = ($input['mode'] ?? '') === 'panier' ? 'panier' : 'produit';

if ($mode === 'panier') {
    require_once __DIR__ . '/../controllers/controller_panier.php';
    $items = panier_get_items_courant();
} else {
    $pid = (int) ($input['produit_id'] ?? 0);
    $produit = $pid > 0 ? get_produit_by_id($pid) : false;
    if (!is_array($produit) || !in_array($produit['statut'] ?? '', ['actif', 'rupture_stock'], true)) {
        echo json_encode(['success' => true, 'html' => '']);
        exit;
    }
    $items = [['produit_id' => $pid] + $produit];
}
/* Le panier est lu : on libère la session pour ne pas bloquer la navigation pendant l'appel à l'IA */
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if ($items === []) {
    echo json_encode(['success' => true, 'html' => '']);
    exit;
}

/* Prépare les compléments manquants (au plus 3 produits par appel), puis calcule les suggestions */
foreach (panier_accessoires_ia_a_preparer($items, 3) as $p) {
    panier_accessoires_ia_profil($p, true);
}

$panier_accessoires = panier_accessoires_resolve_for_cart($items);
if (!empty($panier_accessoires['produits']) && file_exists(__DIR__ . '/../models/model_produits_avis.php')) {
    require_once __DIR__ . '/../models/model_produits_avis.php';
    if (function_exists('produits_avis_enrich_products')) {
        $panier_accessoires['produits'] = produits_avis_enrich_products($panier_accessoires['produits']);
    }
}

ob_start();
if ($mode === 'panier') {
    require __DIR__ . '/../includes/partials/panier_accessoires_block.php';
} else {
    $produit_ajoute = $produit;
    require __DIR__ . '/../includes/partials/suggestions_apres_ajout_contenu.php';
}
$html = trim((string) ob_get_clean());

if ($html !== '' && file_exists(__DIR__ . '/../includes/client_tracking.php')) {
    require_once __DIR__ . '/../includes/client_tracking.php';
    if (function_exists('client_tracking_log_event')) {
        client_tracking_log_event('panier_accessoires_vue', ['source' => $mode === 'panier' ? 'panier_ia' : 'apres_ajout']);
    }
}

echo json_encode(['success' => true, 'html' => $html], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
