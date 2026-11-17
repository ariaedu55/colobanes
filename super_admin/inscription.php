<?php
/**
 * Inscription publique de super administrateur : désactivée.
 * Les comptes super admin sont créés par un super admin existant (Comptes super admin),
 * et la connexion se fait uniquement sur choix-connexion.php.
 */
require_once dirname(__DIR__) . '/includes/app_base.php';
require_once dirname(__DIR__) . '/includes/auth_redirect.php';

header('Location: ' . auth_choix_connexion_url(app_url('/super_admin/dashboard.php')), true, 302);
exit;
