<?php
/**
 * Popups vendeur à la connexion (onboarding, rappels config) — pas les push FCM.
 */
if (empty($_SESSION['admin_id'])) {
    return;
}

if (!isset($is_vendeur_menu)) {
    if (!function_exists('admin_normalize_role_for_route')) {
        require_once dirname(__DIR__, 2) . '/includes/admin_route_access.php';
    }
    $is_vendeur_menu = admin_normalize_role_for_route($_SESSION['admin_role'] ?? '') === 'vendeur';
}
if (empty($is_vendeur_menu)) {
    return;
}

require_once dirname(__DIR__, 2) . '/models/model_admin.php';

if (empty($__vendeur_admin) || !is_array($__vendeur_admin)) {
    $__vendeur_admin = get_admin_by_id((int) $_SESSION['admin_id']);
}
if (!$__vendeur_admin || ($__vendeur_admin['role'] ?? '') !== 'vendeur') {
    return;
}

require_once dirname(__DIR__, 2) . '/models/model_vendeur_onboarding.php';
if (vendeur_onboarding_should_show_popup_modal()) {
    require dirname(__DIR__, 2) . '/includes/partials/vendeur_onboarding_modal.php';
    return;
}

if (!isset($__vendeur_rappel_pending)) {
    require_once dirname(__DIR__, 2) . '/models/model_vendeur_rappels.php';
    $__vendeur_rappel_pending = vendeur_rappel_get_pending_for_dashboard((int) $__vendeur_admin['id']);
    if ($__vendeur_rappel_pending === false) {
        $__vendeur_rappel_pending = false;
    }
}

if (!empty($__vendeur_rappel_pending) && is_array($__vendeur_rappel_pending)) {
    require dirname(__DIR__, 2) . '/includes/partials/vendeur_rappel_modal.php';
    return;
}

require_once dirname(__DIR__, 2) . '/models/model_vendeur_certification.php';
$__vendeur_cert_promo_ctx = vendeur_certification_promo_resolve_context((int) $__vendeur_admin['id']);
if (
    $__vendeur_cert_promo_ctx !== null
    && vendeur_certification_promo_should_show_modal((int) $__vendeur_admin['id'])
) {
    /* Une fois affichée, la popup est mise en pause 10 jours (quel que soit le bouton cliqué) */
    vendeur_certification_promo_set_snooze_days((int) $__vendeur_admin['id'], VENDEUR_CERT_PROMO_INTERVAL_DAYS);
    require dirname(__DIR__, 2) . '/includes/partials/vendeur_certification_promo_modal.php';
}
