<?php
/**
 * Marquer la notification certification comme lue — vendeur
 */
require_once __DIR__ . '/includes/require_admin_session.php';

$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
$demande_id = (int) ($_GET['id'] ?? 0);
$redirect = trim((string) ($_GET['redirect'] ?? '/admin/dashboard.php'));
if ($redirect === '' || strpos($redirect, '//') !== false || strpos($redirect, '..') !== false || preg_match('/[\\\\\x00-\x1F\x7F]/', $redirect)) {
    $redirect = '/admin/dashboard.php';
}
if ($redirect[0] !== '/') {
    $redirect = '/admin/dashboard.php';
}

if ($admin_id > 0 && $demande_id > 0 && file_exists(__DIR__ . '/../models/model_vendeur_certification.php')) {
    require_once __DIR__ . '/../models/model_vendeur_certification.php';
    vendeur_certification_marquer_notif_vendeur_lue($demande_id, $admin_id);
}

/* Chemin du site ajouté si besoin (site installé dans un sous-dossier) */
if (function_exists('app_url') && function_exists('get_app_base_path')) {
    $base_site = rtrim((string) get_app_base_path(), '/');
    if ($base_site !== '' && strpos($redirect, $base_site . '/') !== 0) {
        $redirect = app_url($redirect);
    }
}
header('Location: ' . $redirect);
exit;
