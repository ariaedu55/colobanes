<?php
/**
 * Session super admin + garde d'accès + jeton CSRF
 */
require_once dirname(__DIR__, 2) . '/includes/session_user.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start_persistent();
}

require_once __DIR__ . '/paths.php';
require_once dirname(__DIR__, 2) . '/includes/app_base.php';
require_once dirname(__DIR__, 2) . '/includes/auth_redirect.php';
require_once dirname(__DIR__, 2) . '/includes/super_admin_csrf.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';

/** Ferme uniquement la session super admin (une éventuelle session client reste ouverte) et renvoie à la connexion. */
function super_admin_session_end_and_redirect(): void
{
    foreach (array_keys($_SESSION) as $key) {
        if (strpos((string) $key, 'super_admin') === 0) {
            unset($_SESSION[$key]);
        }
    }
    $return_to = (string) ($_SERVER['REQUEST_URI'] ?? '/super_admin/dashboard.php');
    if ($return_to === '' || strpos($return_to, '//') !== false || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        $return_to = '/super_admin/dashboard.php';
    }
    header('Location: ' . auth_choix_connexion_url($return_to));
    exit;
}

if (empty($_SESSION['super_admin_id'])) {
    super_admin_session_end_and_redirect();
}

/* Compte toujours actif (un compte désactivé perd l'accès immédiatement, même session ouverte) */
$sa_compte = get_super_admin_by_id((int) $_SESSION['super_admin_id']);
if (!$sa_compte || ($sa_compte['statut'] ?? '') !== 'actif') {
    super_admin_session_end_and_redirect();
}
unset($sa_compte);

/* Inactivité : la session super admin se ferme après 2 h sans action */
const SUPER_ADMIN_IDLE_TIMEOUT = 7200;
$sa_last = (int) ($_SESSION['super_admin_last_activity'] ?? 0);
if ($sa_last > 0 && (time() - $sa_last) > SUPER_ADMIN_IDLE_TIMEOUT) {
    super_admin_session_end_and_redirect();
}
$_SESSION['super_admin_last_activity'] = time();

super_admin_csrf_token();
super_admin_csrf_enforce_post();
