<?php
require_once __DIR__ . '/../includes/session_admin.php';
/**
 * Point d'entrée : redirige vers le tableau de bord ou la connexion
 */
require_once __DIR__ . '/includes/paths.php';
require_once dirname(__DIR__) . '/includes/app_base.php';
require_once dirname(__DIR__) . '/includes/auth_redirect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start_persistent();
}

if (!empty($_SESSION['super_admin_id'])) {
    header('Location: ' . super_admin_href('dashboard.php'));
    exit;
}

header('Location: ' . auth_choix_connexion_url('/super_admin/dashboard.php'));
exit;
