<?php
/**
 * Protection CSRF — espace super administrateur
 * Session : $_SESSION['super_admin_csrf']
 */

if (!function_exists('super_admin_csrf_token')) {
    function super_admin_csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['super_admin_csrf'])) {
            $_SESSION['super_admin_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['super_admin_csrf'];
    }
}

if (!function_exists('super_admin_csrf_valid')) {
    function super_admin_csrf_valid($token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $expected = (string) ($_SESSION['super_admin_csrf'] ?? '');
        if ($expected === '' || !is_string($token)) {
            return false;
        }

        return hash_equals($expected, (string) $token);
    }
}

if (!function_exists('super_admin_csrf_verify_post')) {
    function super_admin_csrf_verify_post(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return true;
        }
        $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

        return super_admin_csrf_valid($token);
    }
}

if (!function_exists('super_admin_csrf_fail_message')) {
    function super_admin_csrf_fail_message(): string
    {
        return 'Session expirée ou jeton de sécurité invalide. Rechargez la page et réessayez.';
    }
}

if (!function_exists('super_admin_csrf_field')) {
    function super_admin_csrf_field(): string
    {
        $token = super_admin_csrf_token();
        if ($token === '') {
            return '';
        }

        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('super_admin_csrf_safe_back_url')) {
    function super_admin_csrf_safe_back_url(string $fallback = ''): string
    {
        if ($fallback === '') {
            if (!function_exists('super_admin_href')) {
                require_once __DIR__ . '/../super_admin/includes/paths.php';
            }
            $fallback = super_admin_href('index.php');
        }

        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '' && strpos($ref, '//') === false) {
            $path = parse_url($ref, PHP_URL_PATH);
            $query = parse_url($ref, PHP_URL_QUERY);
            if (is_string($path) && strpos($path, '/super_admin/') === 0) {
                return $path . ($query ? '?' . $query : '');
            }
        }

        return $fallback;
    }
}

if (!function_exists('super_admin_csrf_enforce_post')) {
    function super_admin_csrf_enforce_post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }
        if (super_admin_csrf_verify_post()) {
            return;
        }

        $_SESSION['super_admin_flash_err'] = super_admin_csrf_fail_message();
        header('Location: ' . super_admin_csrf_safe_back_url());
        exit;
    }
}

if (!function_exists('super_admin_csrf_emit_form_inject_script')) {
    function super_admin_csrf_emit_form_inject_script(): void
    {
        $token = super_admin_csrf_token();
        if ($token === '') {
            return;
        }

        echo '<script>(function(){var t='
            . json_encode($token, JSON_UNESCAPED_UNICODE)
            . ';document.querySelectorAll(\'form[method="post"],form[method="POST"]\').forEach(function(f){if(f.querySelector(\'input[name="csrf_token"]\'))return;var i=document.createElement("input");i.type="hidden";i.name="csrf_token";i.value=t;f.appendChild(i);});})();</script>';
    }
}
