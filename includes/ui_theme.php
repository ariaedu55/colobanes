<?php
/**
 * Préférence thème clair / sombre — compte client
 */

if (!function_exists('ui_theme_is_valid')) {
    function ui_theme_is_valid($theme)
    {
        return in_array((string) $theme, ['light', 'dark'], true);
    }
}

if (!function_exists('ui_theme_normalize')) {
    function ui_theme_normalize($theme, $fallback = 'light')
    {
        $theme = trim((string) $theme);
        return ui_theme_is_valid($theme) ? $theme : $fallback;
    }
}

if (!function_exists('ui_theme_session_get')) {
    function ui_theme_session_get()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return 'light';
        }

        if (!empty($_SESSION['ui_theme']) && ui_theme_is_valid($_SESSION['ui_theme'])) {
            return (string) $_SESSION['ui_theme'];
        }

        return 'light';
    }
}

if (!function_exists('ui_theme_session_set')) {
    function ui_theme_session_set($theme)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $_SESSION['ui_theme'] = ui_theme_normalize($theme);
        return true;
    }
}

if (!function_exists('ui_theme_load_for_user')) {
    /**
     * Charge la préférence BDD → session (une fois par session).
     */
    function ui_theme_load_for_user($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0 || session_status() !== PHP_SESSION_ACTIVE) {
            return 'light';
        }

        if (!empty($_SESSION['ui_theme_loaded'])) {
            return ui_theme_session_get();
        }

        $_SESSION['ui_theme_loaded'] = true;

        if (!function_exists('users_has_column')) {
            require_once __DIR__ . '/../models/model_users.php';
        }

        if (!users_has_column('ui_theme')) {
            return ui_theme_session_get();
        }

        global $db;
        if (!isset($db) || !($db instanceof PDO)) {
            require_once __DIR__ . '/../conn/conn.php';
        }

        try {
            $stmt = $db->prepare('SELECT ui_theme FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && ui_theme_is_valid($row['ui_theme'] ?? '')) {
                ui_theme_session_set($row['ui_theme']);
            }
        } catch (PDOException $e) {
            // ignore
        }

        return ui_theme_session_get();
    }
}

if (!function_exists('ui_theme_save_for_user')) {
    function ui_theme_save_for_user($user_id, $theme)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }

        $theme = ui_theme_normalize($theme);
        ui_theme_session_set($theme);

        if (!function_exists('users_has_column')) {
            require_once __DIR__ . '/../models/model_users.php';
        }

        if (!users_has_column('ui_theme')) {
            return true;
        }

        global $db;
        if (!isset($db) || !($db instanceof PDO)) {
            require_once __DIR__ . '/../conn/conn.php';
        }

        try {
            $stmt = $db->prepare('UPDATE users SET ui_theme = ? WHERE id = ? LIMIT 1');
            return (bool) $stmt->execute([$theme, $user_id]);
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('ui_theme_resolve_for_request')) {
    function ui_theme_resolve_for_request()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return 'light';
        }

        if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
            return ui_theme_load_for_user((int) $_SESSION['user_id']);
        }

        return ui_theme_session_get();
    }
}
