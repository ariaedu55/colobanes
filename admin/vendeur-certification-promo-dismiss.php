<?php
/**
 * Ferme la popup certification pour la session en cours.
 */
require_once __DIR__ . '/includes/require_admin_session.php';
require_once __DIR__ . '/../models/model_vendeur_certification.php';

$redirect_raw = isset($_GET['redirect']) ? (string) $_GET['redirect'] : '';
$redirect = vendeur_certification_promo_sanitize_redirect($redirect_raw);

vendeur_certification_promo_dismiss_for_session();

header('Location: ' . $redirect);
exit;
