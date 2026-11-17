<?php
/**
 * Contrôleur — favoris client
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_favoris.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../includes/site_csrf.php';
require_once __DIR__ . '/../includes/flash_toast.php';

/**
 * @param string $redirect
 * @return string
 */
function favori_safe_redirect($redirect)
{
    $redirect = trim((string) $redirect);
    if ($redirect === '' || strpos($redirect, '//') !== false || preg_match('#^https?://#i', $redirect)) {
        return '/index.php';
    }
    if ($redirect[0] !== '/') {
        $redirect = '/' . $redirect;
    }

    return $redirect;
}

/**
 * Bascule favori — logique métier (sans redirection).
 *
 * @param int $user_id
 * @param int $produit_id
 * @return array{success:bool, favori?:bool, message?:string, count?:int, auth_required?:bool}
 */
function favori_execute_toggle($user_id, $produit_id)
{
    $user_id = (int) $user_id;
    $produit_id = (int) $produit_id;

    if ($user_id <= 0) {
        return [
            'success' => false,
            'message' => 'Connectez-vous pour enregistrer vos favoris.',
            'auth_required' => true,
        ];
    }

    if ($produit_id <= 0) {
        return ['success' => false, 'message' => 'Produit invalide.'];
    }

    $produit = get_produit_by_id($produit_id);
    if (!$produit || !produit_est_visible_client($produit['statut'] ?? '')) {
        return ['success' => false, 'message' => 'Ce produit n\'est pas disponible.'];
    }

    $result = toggle_favori($user_id, $produit_id);
    if (empty($result['success'])) {
        return ['success' => false, 'message' => $result['message'] ?? 'Action impossible.'];
    }

    $favori = !empty($result['favori']);
    require_once __DIR__ . '/../includes/client_tracking.php';
    client_tracking_log_event($favori ? 'favori_ajout' : 'favori_retrait', [
        'produit' => $produit,
        'produit_id' => $produit_id,
    ]);

    return [
        'success' => true,
        'favori' => $favori,
        'message' => $favori ? 'Produit ajouté à vos favoris.' : 'Produit retiré de vos favoris.',
        'count' => count_favoris_by_user($user_id),
    ];
}

/**
 * Bascule favori (ajout / retrait) — utilisateur connecté uniquement.
 *
 * @return never
 */
function process_toggle_favori()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_redirect_safe('/index.php');
    }

    $return_url = favori_safe_redirect($_POST['return_url'] ?? '/index.php');

    if (!site_csrf_verify_post()) {
        flash_toast_push('error', site_csrf_fail_message());
        http_redirect_safe($return_url);
    }

    $produit_id = isset($_POST['produit_id']) ? (int) $_POST['produit_id'] : 0;
    $user_id = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    $result = favori_execute_toggle($user_id, $produit_id);

    if (!empty($result['auth_required'])) {
        flash_toast_push('error', $result['message'] ?? 'Connectez-vous pour enregistrer vos favoris.');
        http_redirect_safe('/choix-connexion.php?redirect=' . rawurlencode($return_url));
    }

    if (empty($result['success'])) {
        flash_toast_push('error', $result['message'] ?? 'Action impossible.');
        http_redirect_safe($return_url);
    }

    flash_toast_push('success', $result['message'] ?? 'Favori mis à jour.');
    http_redirect_safe($return_url);
}
