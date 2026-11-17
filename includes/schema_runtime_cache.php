<?php
/**
 * Cache disque des modules de schéma déjà à jour — évite information_schema à chaque requête.
 */

if (!defined('SCHEMA_RUNTIME_CACHE_VERSION')) {
    define('SCHEMA_RUNTIME_CACHE_VERSION', '2026-03-22-v1');
}

if (!function_exists('schema_runtime_cache_file')) {
    function schema_runtime_cache_file(): string
    {
        return dirname(__DIR__) . '/config/cache/schema_modules.json';
    }
}

if (!function_exists('schema_runtime_read_modules')) {
    /**
     * @return array<string, bool>
     */
    function schema_runtime_read_modules(): array
    {
        if (isset($GLOBALS['__schema_runtime_modules']) && is_array($GLOBALS['__schema_runtime_modules'])) {
            return $GLOBALS['__schema_runtime_modules'];
        }
        $modules = [];
        $file = schema_runtime_cache_file();
        if (is_readable($file)) {
            $raw = file_get_contents($file);
            $decoded = json_decode($raw !== false ? $raw : '', true);
            if (is_array($decoded) && ($decoded['v'] ?? '') === SCHEMA_RUNTIME_CACHE_VERSION) {
                $list = $decoded['modules'] ?? [];
                if (is_array($list)) {
                    $modules = $list;
                }
            }
        }
        $GLOBALS['__schema_runtime_modules'] = $modules;
        return $modules;
    }
}

if (!function_exists('schema_runtime_module_ready')) {
    function schema_runtime_module_ready(string $module): bool
    {
        $modules = schema_runtime_read_modules();
        return !empty($modules[$module]);
    }
}

if (!function_exists('schema_runtime_mark_module_ready')) {
    function schema_runtime_mark_module_ready(string $module): void
    {
        $modules = schema_runtime_read_modules();
        $modules[$module] = true;
        $GLOBALS['__schema_runtime_modules'] = $modules;
        $dir = dirname(schema_runtime_cache_file());
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $payload = json_encode(
            ['v' => SCHEMA_RUNTIME_CACHE_VERSION, 'modules' => $modules],
            JSON_UNESCAPED_UNICODE
        );
        if ($payload !== false) {
            @file_put_contents(schema_runtime_cache_file(), $payload, LOCK_EX);
        }
    }
}

if (!function_exists('schema_runtime_conn_bootstrap_ready')) {
    function schema_runtime_conn_bootstrap_ready(): bool
    {
        return schema_runtime_module_ready('client_comportement')
            && schema_runtime_module_ready('notifications');
    }
}

if (!function_exists('conn_request_path')) {
    function conn_request_path(): string
    {
        return (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    }
}

if (!function_exists('conn_normalize_request_path')) {
    function conn_normalize_request_path(?string $uri = null): string
    {
        $uri = $uri ?? conn_request_path();
        if (!function_exists('get_app_base_path')) {
            require_once __DIR__ . '/app_base.php';
        }
        $base = get_app_base_path();
        if ($base !== '' && strncmp($uri, $base . '/', strlen($base) + 1) === 0) {
            $uri = substr($uri, strlen($base)) ?: '/';
        } elseif ($base !== '' && $uri === $base) {
            $uri = '/';
        }
        if ($uri === '') {
            $uri = '/';
        }
        return $uri;
    }
}

if (!function_exists('conn_skip_schema_bootstrap')) {
    function conn_skip_schema_bootstrap(?string $uri = null): bool
    {
        $uri = conn_normalize_request_path($uri);
        if (
            strpos($uri, '/css/') !== false
            || strpos($uri, '/js/') !== false
            || strpos($uri, '/image/') !== false
            || strpos($uri, '/upload/') !== false
            || strpos($uri, '/icons/') !== false
            || strpos($uri, '/site-icons/') !== false
        ) {
            return true;
        }
        // Back-offices : migrations déclenchées par les pages / modèles concernés.
        if (preg_match('#^/(admin|super_admin)(/|$)#', $uri)) {
            return true;
        }
        if (preg_match('#^/api(/|$)#', $uri)) {
            return true;
        }
        if (schema_runtime_conn_bootstrap_ready()) {
            return true;
        }
        return false;
    }
}
