<?php
/**
 * Revalidation périodique de la session admin / vendeur / collaborateur
 * Le statut et le rôle sont relus en base (au plus une fois toutes les 60 s par session)
 * afin qu'une désactivation, une révocation d'accès ou un changement de rôle
 * s'applique aussi aux sessions déjà ouvertes.
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/auth_redirect.php';

if (!defined('ADMIN_SESSION_REVALIDATE_INTERVAL')) {
    define('ADMIN_SESSION_REVALIDATE_INTERVAL', 60);
}

if (!function_exists('admin_session_revoke_and_redirect')) {
    /**
     * Détruit la session courante (même logique que admin/logout.php) puis renvoie vers la connexion.
     */
    function admin_session_revoke_and_redirect()
    {
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        auth_clear_portal_cookie();
        auth_revoke_vendeur_marketplace_visit();
        admin_redirect_to_login();
        exit;
    }
}

if (!function_exists('admin_session_revalidate')) {
    /**
     * Relit en base le compte admin de la session (et l'accès collaborateur éventuel).
     * Compte supprimé / désactivé ou accès collaborateur révoqué : session détruite.
     * Rôle modifié : $_SESSION['admin_role'] mis à jour.
     */
    function admin_session_revalidate()
    {
        $admin_id = (int) ($_SESSION['admin_id'] ?? 0);
        if ($admin_id <= 0) {
            return;
        }

        $last = (int) ($_SESSION['admin_revalidated_at'] ?? 0);
        $now = time();
        if ($last > 0 && ($now - $last) < ADMIN_SESSION_REVALIDATE_INTERVAL) {
            return;
        }

        require_once __DIR__ . '/../models/model_admin.php';
        require_once __DIR__ . '/../models/model_vendeur_onboarding.php';
        global $db;

        try {
            $stmt = $db->prepare('SELECT id, statut, role FROM admin WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $admin_id]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            $collab = null;
            $collab_id = (int) ($_SESSION['vendeur_collaborateur_id'] ?? 0);
            if ($collab_id > 0) {
                $stmt = $db->prepare('SELECT id, statut, vendeur_admin_id FROM vendeur_comptes_acces WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $collab_id]);
                $collab = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            // Erreur base passagère : on ne déconnecte pas, nouvelle tentative à la prochaine requête
            error_log('[admin_session_revalidate] ' . $e->getMessage());
            return;
        }

        if (!$admin) {
            admin_session_revoke_and_redirect();
        }

        if ($collab_id > 0) {
            // Collaborateur : accès actif, rattaché à ce propriétaire, et propriétaire autorisé (mêmes règles qu'au login)
            if (!$collab
                || ($collab['statut'] ?? '') !== 'actif'
                || (int) ($collab['vendeur_admin_id'] ?? 0) !== $admin_id
                || !vendeur_owner_statut_allows_collab_login($admin)) {
                admin_session_revoke_and_redirect();
            }
        } elseif (!admin_statut_allows_admin_login($admin)) {
            admin_session_revoke_and_redirect();
        }

        $_SESSION['admin_statut'] = $admin['statut'];
        $_SESSION['admin_role'] = normalize_admin_role($admin['role'] ?? 'admin');
        $_SESSION['admin_revalidated_at'] = $now;
    }
}
