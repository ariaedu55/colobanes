<?php
/**
 * Déconnexion super administrateur
 * Ne détruit que les clés super_admin (conserve éventuelle session client/boutique).
 */
require_once dirname(__DIR__) . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/paths.php';

foreach (array_keys($_SESSION) as $key) {
    if (strpos((string) $key, 'super_admin') === 0) {
        unset($_SESSION[$key]);
    }
}

require_once dirname(__DIR__) . '/includes/app_base.php';
require_once dirname(__DIR__) . '/includes/auth_redirect.php';

header('Location: ' . auth_choix_connexion_url());
exit;
