<?php
/**
 * Reporte la popup certification (7 jours par défaut).
 */
require_once __DIR__ . '/includes/require_admin_session.php';
require_once __DIR__ . '/../models/model_vendeur_certification.php';

$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
$days = isset($_GET['days']) ? (int) $_GET['days'] : 7;
$redirect_raw = isset($_GET['redirect']) ? (string) $_GET['redirect'] : '';
$redirect = vendeur_certification_promo_sanitize_redirect($redirect_raw);

if ($admin_id > 0) {
    vendeur_certification_promo_set_snooze_days($admin_id, $days);
}

header('Location: ' . $redirect);
exit;
