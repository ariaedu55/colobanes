<?php
/**
 * Déconnexion unifiée (client ou admin/vendeur)
 * URL courte : /logout.php
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

if (!empty($_SESSION['admin_id'])) {
    require __DIR__ . '/admin/logout.php';
    exit;
}

require __DIR__ . '/user/deconnexion.php';
