<?php
/**
 * Redirections de connexion et cookie « portail » (vendeur / admin / client).
 * Programmation procédurale uniquement
 */

if (!function_exists('app_url')) {
    require_once __DIR__ . '/app_base.php';
}

if (!function_exists('auth_portal_cookie_lifetime')) {
    function auth_portal_cookie_lifetime()
    {
        if (function_exists('session_persistent_lifetime')) {
            return session_persistent_lifetime();
        }
        return 30 * 24 * 3600;
    }
}

if (!function_exists('auth_set_portal_cookie')) {
    /**
     * @param string $portal vendeur|admin|client|choix
     */
    function auth_set_portal_cookie($portal)
    {
        $portal = trim((string) $portal);
        if ($portal === '') {
            return;
        }

        $lifetime = auth_portal_cookie_lifetime();
        setcookie('colobane_auth_portal', $portal, [
            'expires' => time() + $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => function_exists('session_request_is_https') ? session_request_is_https() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('auth_clear_portal_cookie')) {
    function auth_clear_portal_cookie()
    {
        setcookie('colobane_auth_portal', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('auth_normalize_redirect_path')) {
    /**
     * Chemin interne sûr (/index.php) — refuse les URLs externes.
     */
    function auth_normalize_redirect_path($url, $fallback = '/index.php')
    {
        $url = trim((string) $url);
        $fallback = trim((string) $fallback);
        if ($fallback === '' || $fallback[0] !== '/') {
            $fallback = '/index.php';
        }

        if ($url === '' || preg_match('/[\\\\\x00-\x1F\x7F]/', $url)) {
            return $fallback;
        }

        /* URL absolue http(s) même origine → chemin seul */
        if (preg_match('#^https?://#i', $url)) {
            $parts = parse_url($url);
            $path = (string) ($parts['path'] ?? '/');
            $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
            $url = ($path === '' ? '/' : $path) . $query;
        }

        if (strpos($url, '//') !== false) {
            return $fallback;
        }
        if ($url[0] !== '/') {
            $url = '/' . $url;
        }

        return $url;
    }
}

if (!function_exists('auth_absolute_redirect_url')) {
    /**
     * URL absolue pour Location: (plus fiable que les chemins relatifs après POST).
     */
    function auth_absolute_redirect_url($url)
    {
        $path = auth_normalize_redirect_path($url);
        $path = app_url($path);

        if (!function_exists('get_site_base_url')) {
            require_once __DIR__ . '/site_url.php';
        }

        $site = rtrim((string) get_site_base_url(), '/');
        $origin = $site;
        if (preg_match('#^(https?://[^/]+)#i', $site, $m)) {
            $origin = $m[1];
        } else {
            $https = function_exists('session_request_is_https')
                ? session_request_is_https()
                : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $origin = ($https ? 'https' : 'http') . '://' . $host;
        }

        return $origin . $path;
    }
}

if (!function_exists('auth_redirect_after_login')) {
    /**
     * Redirection HTTP sûre après connexion (évite écran blanc / re-soumission formulaire).
     * Toujours envoie un corps HTML de secours + JS — jamais une réponse vide.
     *
     * @param string $url URL absolue ou relative commençant par /
     */
    function auth_redirect_after_login($url)
    {
        $target = auth_absolute_redirect_url($url);
        $safe = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Location: ' . $target, true, 303);
        }

        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta http-equiv="refresh" content="0;url=' . $safe . '">'
            . '<title>Redirection…</title>'
            . '<style>html,body{margin:0;min-height:100%;background:#fff;font-family:system-ui,sans-serif}'
            . '.box{min-height:100vh;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px;color:#3564a6}'
            . '.spin{width:36px;height:36px;border:3px solid #e8eef6;border-top-color:#3564a6;border-radius:50%;animation:s .7s linear infinite}'
            . '@keyframes s{to{transform:rotate(360deg)}}a{color:#3564a6}</style>'
            . '</head><body><div class="box" role="status" aria-live="polite">'
            . '<div class="spin" aria-hidden="true"></div>'
            . '<p>Connexion réussie — redirection…</p>'
            . '<p><a href="' . $safe . '">Continuer</a></p></div>'
            . '<script>try{location.replace(' . json_encode($target, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');}catch(e){location.href=' . json_encode($target, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';}</script>'
            . '</body></html>';
        exit;
    }
}

if (!function_exists('auth_user_is_logged_in')) {
    function auth_user_is_logged_in()
    {
        return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
    }
}

if (!function_exists('auth_site_home_url')) {
    function auth_site_home_url()
    {
        return app_url('/index.php');
    }
}

if (!function_exists('auth_redirect_to_site_home')) {
    /**
     * Redirection vers l'accueil (déconnexion manuelle ou session expirée).
     */
    function auth_redirect_to_site_home()
    {
        auth_redirect_after_login('/index.php');
    }
}

if (!function_exists('auth_user_redirect_if_not_logged_in')) {
    function auth_user_redirect_if_not_logged_in()
    {
        if (!auth_user_is_logged_in()) {
            auth_redirect_to_site_home();
        }
    }
}

if (!function_exists('admin_login_redirect_url')) {
    /**
     * URL de connexion unifiée (choix-connexion.php).
     *
     * @param string|null $redirect_after
     */
    function admin_login_redirect_url($redirect_after = null)
    {
        if ($redirect_after === null || $redirect_after === '') {
            $redirect_after = $_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php';
        }

        $redirect_after = (string) $redirect_after;
        if ($redirect_after !== '' && $redirect_after[0] !== '/') {
            $redirect_after = '/' . ltrim($redirect_after, '/');
        }

        $qs = $redirect_after !== '' ? ('?redirect=' . urlencode($redirect_after)) : '';

        return app_url('/choix-connexion.php' . $qs);
    }
}

if (!function_exists('admin_redirect_to_login')) {
    function admin_redirect_to_login()
    {
        auth_redirect_to_site_home();
    }
}

if (!function_exists('auth_session_is_vendeur')) {
    /**
     * Session vendeur titulaire ou collaborateur boutique.
     */
    function auth_session_is_vendeur()
    {
        if (empty($_SESSION['admin_id']) || (int) $_SESSION['admin_id'] <= 0) {
            return false;
        }

        if (!empty($_SESSION['vendeur_collaborateur_id'])) {
            return true;
        }

        if (!function_exists('normalize_admin_role')) {
            $model_admin = __DIR__ . '/../models/model_admin.php';
            if (is_file($model_admin)) {
                require_once $model_admin;
            }
        }

        if (!function_exists('normalize_admin_role')) {
            return (string) ($_SESSION['admin_role'] ?? '') === 'vendeur';
        }

        return normalize_admin_role($_SESSION['admin_role'] ?? '') === 'vendeur';
    }
}

if (!function_exists('auth_vendeur_dashboard_url')) {
    function auth_vendeur_dashboard_url()
    {
        return app_url('/admin/dashboard.php');
    }
}

if (!function_exists('auth_vendeur_mp_visit_cookie_name')) {
    function auth_vendeur_mp_visit_cookie_name()
    {
        return 'colobane_vendeur_mp_visit';
    }
}

if (!function_exists('auth_sync_vendeur_marketplace_visit')) {
    /**
     * Réaligne la session si le cookie de visite est présent (prod / partage session).
     */
    function auth_sync_vendeur_marketplace_visit()
    {
        if (!auth_session_is_vendeur()) {
            return;
        }
        $cookie_name = auth_vendeur_mp_visit_cookie_name();
        if (!empty($_SESSION['vendeur_visite_marketplace'])) {
            return;
        }
        if (isset($_COOKIE[$cookie_name]) && (string) $_COOKIE[$cookie_name] === '1') {
            $_SESSION['vendeur_visite_marketplace'] = true;
        }
    }
}

if (!function_exists('auth_vendeur_may_browse_marketplace')) {
    /**
     * Vendeur connecté : accès libre au site public (catalogue, accueil, etc.).
     */
    function auth_vendeur_may_browse_marketplace()
    {
        return auth_session_is_vendeur();
    }
}

if (!function_exists('auth_grant_vendeur_marketplace_visit')) {
    function auth_grant_vendeur_marketplace_visit()
    {
        if (!auth_session_is_vendeur()) {
            return false;
        }
        $_SESSION['vendeur_visite_marketplace'] = true;

        $lifetime = auth_portal_cookie_lifetime();
        setcookie(auth_vendeur_mp_visit_cookie_name(), '1', [
            'expires' => time() + $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return true;
    }
}

if (!function_exists('auth_revoke_vendeur_marketplace_visit')) {
    function auth_revoke_vendeur_marketplace_visit()
    {
        unset($_SESSION['vendeur_visite_marketplace']);
        setcookie(auth_vendeur_mp_visit_cookie_name(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('auth_marketplace_visit_url')) {
    /**
     * URL marketplace publique (sans paramètre d’URL).
     */
    function auth_marketplace_visit_url($path = '')
    {
        unset($path);
        return '/index.php';
    }
}


if (!function_exists('auth_redirect_vendeur_to_dashboard')) {
    /**
     * Redirige un vendeur déjà connecté vers son tableau de bord (ex. page inscription).
     */
    function auth_redirect_vendeur_to_dashboard()
    {
        if (!auth_session_is_vendeur()) {
            return;
        }
        auth_redirect_after_login(auth_vendeur_dashboard_url());
    }
}

if (!function_exists('auth_login_redirect_url_for_admin')) {
    /**
     * URL de redirection après connexion admin (vendeur → tableau de bord).
     *
     * @param string|null $fallback
     */
    function auth_login_redirect_url_for_admin($fallback = null)
    {
        if (auth_session_is_vendeur()) {
            return auth_vendeur_dashboard_url();
        }

        $fallback = trim((string) ($fallback ?? '/admin/dashboard.php'));
        if ($fallback === '' || strpos($fallback, '//') !== false || preg_match('/[\\\\\x00-\x1F\x7F]/', (string) $fallback)) {
            $fallback = '/admin/dashboard.php';
        }

        return $fallback[0] === '/' ? $fallback : '/' . $fallback;
    }
}

if (!function_exists('auth_super_admin_dashboard_url')) {
    function auth_super_admin_dashboard_url()
    {
        return app_url('/super_admin/dashboard.php');
    }
}

if (!function_exists('auth_choix_connexion_url')) {
    /**
     * Page de connexion unifiée (optionnel : redirect après login).
     */
    function auth_choix_connexion_url($redirect_after = '')
    {
        $redirect_after = trim((string) $redirect_after);
        if ($redirect_after !== '' && $redirect_after[0] !== '/') {
            $redirect_after = '/' . ltrim($redirect_after, '/');
        }
        if ($redirect_after !== '' && strpos($redirect_after, '//') !== false || preg_match('/[\\\\\x00-\x1F\x7F]/', (string) $redirect_after)) {
            $redirect_after = '';
        }
        $qs = $redirect_after !== '' ? ('?' . http_build_query(['redirect' => $redirect_after])) : '';

        return app_url('/choix-connexion.php' . $qs);
    }
}

if (!function_exists('auth_super_admin_local_path')) {
    /**
     * Chemin /super_admin/… sûr à partir d'une redirection demandée (préfixe d'installation accepté), sinon ''.
     */
    function auth_super_admin_local_path($redirect)
    {
        $r = (string) $redirect;
        if ($r === '' || strpos($r, '//') !== false || preg_match('/[\\\\\x00-\x1F\x7F]/', $r)) {
            return '';
        }
        $base = function_exists('get_app_base_path') ? (string) get_app_base_path() : '';
        if ($base !== '' && strpos($r, $base . '/') === 0) {
            $r = substr($r, strlen($base));
        }
        return (strpos($r, '/super_admin/') === 0 && strpos($r, '..') === false) ? $r : '';
    }
}

if (!function_exists('auth_complete_super_admin_login')) {
    /**
     * Session super administrateur après authentification réussie.
     */
    function auth_complete_super_admin_login(array $row)
    {
        require_once __DIR__ . '/../models/model_super_admin.php';
        session_regenerate_persistent();
        $_SESSION['super_admin_id'] = (int) ($row['id'] ?? 0);
        $_SESSION['super_admin_nom'] = (string) ($row['nom'] ?? '');
        $_SESSION['super_admin_prenom'] = (string) ($row['prenom'] ?? '');
        $_SESSION['super_admin_email'] = (string) ($row['email'] ?? '');
        $_SESSION['super_admin_csrf'] = bin2hex(random_bytes(32));
        if ($_SESSION['super_admin_id'] > 0) {
            super_admin_update_last_login((int) $_SESSION['super_admin_id']);
        }
        auth_set_portal_cookie('super_admin');
    }
}
