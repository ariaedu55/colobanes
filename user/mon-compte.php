<?php
/**
 * Ancienne URL tableau de bord — redirection permanente vers le profil client.
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

if (!function_exists('app_url')) {
    require_once __DIR__ . '/../includes/app_base.php';
}

$query = isset($_SERVER['QUERY_STRING']) && trim((string) $_SERVER['QUERY_STRING']) !== ''
    ? '?' . (string) $_SERVER['QUERY_STRING']
    : '';

header('Location: ' . app_url('/user/profil.php') . $query, true, 301);
exit;
