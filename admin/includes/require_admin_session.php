<?php
/**
 * Session admin persistante + garde d'authentification
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../../includes/session_admin.php';
require_once __DIR__ . '/../../includes/auth_redirect.php';
require_once __DIR__ . '/../../includes/admin_csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start_persistent();
}

if (!isset($_SESSION['admin_id'])) {
    admin_redirect_to_login();
}

/* Statut / rôle / accès collaborateur relus en base (toutes les 60 s) : une désactivation coupe les sessions ouvertes */
require_once __DIR__ . '/../../includes/admin_session_revalidate.php';
admin_session_revalidate();

admin_csrf_token();
admin_csrf_enforce_post();

/* Contrôle des rôles appliqué à toutes les pages d'administration (une page ne peut plus l'oublier) */
require_once __DIR__ . '/require_access.php';
