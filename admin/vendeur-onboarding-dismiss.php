<?php
/**
 * Ferme la modale d’onboarding vendeur pour la session en cours.
 */
require_once __DIR__ . '/includes/require_admin_session.php';
require_once __DIR__ . '/../models/model_vendeur_onboarding.php';

$redirect_raw = isset($_GET['redirect']) ? (string) $_GET['redirect'] : '';
$redirect = vendeur_onboarding_sanitize_redirect($redirect_raw);

vendeur_onboarding_dismiss_popup_for_session();

header('Location: ' . $redirect);
exit;
