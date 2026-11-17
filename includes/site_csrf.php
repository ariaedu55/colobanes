<?php
/**
 * Protection CSRF — espace client (panier, commande, profil, etc.)
 */

if (!function_exists('site_csrf_token')) {
    function site_csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['site_csrf'])) {
            $_SESSION['site_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['site_csrf'];
    }
}

if (!function_exists('site_csrf_valid')) {
    function site_csrf_valid($token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $expected = (string) ($_SESSION['site_csrf'] ?? '');
        if ($expected === '' || !is_string($token)) {
            return false;
        }

        return hash_equals($expected, (string) $token);
    }
}

if (!function_exists('site_csrf_verify_post')) {
    function site_csrf_verify_post(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return true;
        }
        $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

        return site_csrf_valid($token);
    }
}

if (!function_exists('site_csrf_fail_message')) {
    function site_csrf_fail_message(): string
    {
        return 'Session expirée. Rechargez la page et réessayez.';
    }
}

if (!function_exists('site_csrf_field')) {
    function site_csrf_field(): string
    {
        $token = site_csrf_token();
        if ($token === '') {
            return '';
        }

        /* + piège à robots invisible (vérifié automatiquement, voir includes/protection_site.php) */
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">'
            . (function_exists('protection_honeypot_champs') ? protection_honeypot_champs() : '');
    }
}

if (!function_exists('site_add_to_panier_hidden_fields')) {
    /**
     * Champs cachés communs pour les formulaires d'ajout au panier (CSRF inclus).
     */
    function site_add_to_panier_hidden_fields(): void
    {
        echo site_csrf_field();
    }
}

if (!function_exists('site_csrf_require_post')) {
    /**
     * @return array{ok:bool, message:string}
     */
    function site_csrf_require_post(): array
    {
        if (site_csrf_verify_post()) {
            return ['ok' => true, 'message' => ''];
        }

        return ['ok' => false, 'message' => site_csrf_fail_message()];
    }
}
