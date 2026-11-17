<?php
/**
 * Protection CSRF — espace administrateur (vendeur / admin / employés)
 * Session : $_SESSION['admin_csrf']
 */

if (!function_exists('admin_csrf_token')) {
    function admin_csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['admin_csrf'])) {
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['admin_csrf'];
    }
}

if (!function_exists('admin_csrf_valid')) {
    function admin_csrf_valid($token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $expected = (string) ($_SESSION['admin_csrf'] ?? '');
        if ($expected === '' || !is_string($token)) {
            return false;
        }

        return hash_equals($expected, (string) $token);
    }
}

if (!function_exists('admin_csrf_verify_post')) {
    function admin_csrf_verify_post(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return true;
        }
        $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

        return admin_csrf_valid($token);
    }
}

if (!function_exists('admin_csrf_fail_message')) {
    function admin_csrf_fail_message(): string
    {
        return 'Session expirée ou jeton de sécurité invalide. Rechargez la page et réessayez.';
    }
}

if (!function_exists('admin_csrf_field')) {
    function admin_csrf_field(): string
    {
        $token = admin_csrf_token();
        if ($token === '') {
            return '';
        }

        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('admin_csrf_safe_back_url')) {
    /**
     * Page admin de retour après un refus : le référent s'il est sur ce site et sous /admin/,
     * sinon le tableau de bord. Toujours avec le sous-dossier du site (ex. /fouta-main).
     */
    function admin_csrf_safe_back_url(string $fallback = '/admin/dashboard.php'): string
    {
        if (!function_exists('app_url')) {
            require_once __DIR__ . '/app_base.php';
        }
        $base = function_exists('get_app_base_path') ? (string) get_app_base_path() : '';
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '') {
            $host = strtolower((string) parse_url($ref, PHP_URL_HOST));
            $own = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
            $path = (string) parse_url($ref, PHP_URL_PATH);
            $query = parse_url($ref, PHP_URL_QUERY);
            if ($host !== '' && $host === $own && strpos($path, $base . '/admin/') === 0
                && !preg_match('/[\\\\\x00-\x1F\x7F]/', $path)) {
                return $path . ($query ? '?' . $query : '');
            }
        }

        return app_url($fallback);
    }
}

if (!function_exists('admin_csrf_enforce_post')) {
    function admin_csrf_enforce_post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }
        if (admin_csrf_verify_post()) {
            return;
        }

        if (!function_exists('flash_toast_push')) {
            require_once __DIR__ . '/flash_toast.php';
        }
        flash_toast_push('error', admin_csrf_fail_message());
        header('Location: ' . admin_csrf_safe_back_url());
        exit;
    }
}

if (!function_exists('admin_csrf_emit_form_inject_script')) {
    /**
     * Ajoute csrf_token aux formulaires POST qui n'en ont pas encore (filet de sécurité UI).
     */
    function admin_csrf_emit_form_inject_script(): void
    {
        $token = admin_csrf_token();
        if ($token === '') {
            return;
        }

        echo '<script>(function(){var t='
            . json_encode($token, JSON_UNESCAPED_UNICODE)
            . ';document.querySelectorAll(\'form[method="post"],form[method="POST"]\').forEach(function(f){if(f.querySelector(\'input[name="csrf_token"]\'))return;var i=document.createElement("input");i.type="hidden";i.name="csrf_token";i.value=t;f.appendChild(i);});})();</script>';
    }
}
