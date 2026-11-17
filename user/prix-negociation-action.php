<?php
/**
 * Action POST — négociation de prix (client)
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../includes/site_csrf.php';
require_once __DIR__ . '/../includes/flash_toast.php';
require_once __DIR__ . '/../controllers/controller_prix_negociation.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !site_csrf_verify_post()) {
    flash_toast_push('error', site_csrf_fail_message());
    $redirect = isset($_POST['redirect']) ? trim((string) $_POST['redirect']) : '/index.php';
    if ($redirect === '' || strpos($redirect, '//') !== false || preg_match('/[\\\\\x00-\x1F\x7F]/', (string) $redirect) || preg_match('#^https?://#i', $redirect)) {
        $redirect = '/index.php';
    }
    if ($redirect[0] !== '/') {
        $redirect = '/' . $redirect;
    }
    http_redirect_safe($redirect);
}

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : 'propose';

if ($action === 'commander') {
    process_prix_negociation_client_commander();
}

if ($action === 'reject_counter') {
    process_prix_negociation_client_reject_counter();
}

process_prix_negociation_client_propose();
