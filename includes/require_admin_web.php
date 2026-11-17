<?php
/**
 * Bloque l'accès si l'administrateur n'est pas connecté.
 */

require_once __DIR__ . '/session_admin.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start_persistent();
}

if (empty($_SESSION['admin_id']) || (int) $_SESSION['admin_id'] <= 0) {
    http_response_code(404);
    exit;
}
