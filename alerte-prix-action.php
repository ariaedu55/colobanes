<?php
/**
 * Activer / désactiver une alerte prix (prix de référence = prix affiché au moment de l’action).
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/site_csrf.php';
require_once __DIR__ . '/includes/flash_toast.php';
require_once __DIR__ . '/includes/app_base.php';
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/models/model_alerte_prix.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_redirect_safe('/index.php');
}

$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
if ($user_id <= 0) {
    flash_toast_queue_page('error', 'Connectez-vous pour activer une alerte prix.');
    http_redirect_safe('/choix-connexion.php');
}

if (!site_csrf_verify_post()) {
    flash_toast_queue_page('error', 'Session expirée. Réessayez.');
    http_redirect_safe('/index.php');
}

$produit_id = isset($_POST['produit_id']) ? (int) $_POST['produit_id'] : 0;
$action = isset($_POST['alerte_action']) ? (string) $_POST['alerte_action'] : 'activer';
$return = isset($_POST['return_url']) ? trim((string) $_POST['return_url']) : '';
if ($return === '' || strpos($return, '//') !== false || preg_match('/[\\\\\x00-\x1F\x7F]/', (string) $return) || $return[0] !== '/') {
    $return = $produit_id > 0 ? '/produit.php?id=' . $produit_id : '/index.php';
}

if ($produit_id <= 0) {
    flash_toast_queue_page('error', 'Produit invalide.');
    http_redirect_safe($return);
}

$produit = get_produit_by_id($produit_id);
if (!$produit || !produit_est_visible_client($produit['statut'] ?? '')) {
    flash_toast_queue_page('error', 'Produit introuvable.');
    http_redirect_safe($return);
}

if ($action === 'desactiver') {
    $res = alerte_prix_desactiver($user_id, $produit_id);
    flash_toast_queue_page($res['success'] ? 'success' : 'error', $res['success']
        ? 'Alerte prix désactivée.'
        : ($res['message'] ?? 'Erreur.'));
    http_redirect_safe($return);
}

$res = alerte_prix_activer($user_id, $produit_id, $produit);
if ($res['success']) {
    $pr = number_format((float) ($res['prix_reference'] ?? 0), 0, ',', ' ');
    flash_toast_queue_page('success', 'Alerte activée : nous vous préviendrons si le prix passe sous ' . $pr . ' FCFA.');
} else {
    flash_toast_queue_page('error', $res['message'] ?? 'Impossible d’activer l’alerte.');
}
http_redirect_safe($return);
