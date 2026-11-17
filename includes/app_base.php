<?php
/**
 * Chemin de base de l'application (ex. /fouta-main en local XAMPP, '' en production à la racine).
 */

require_once __DIR__ . '/app_timezone.php';

if (!function_exists('get_app_base_path')) {
    function get_app_base_path(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = '';

        $config_file = __DIR__ . '/../config/site.php';
        if (is_file($config_file)) {
            $config = require $config_file;
            if (isset($config['base_path'])) {
                $bp = rtrim(trim((string) $config['base_path']), '/');
                $cached = ($bp === '/' || $bp === '.') ? '' : $bp;
                return $cached;
            }
        }

        $doc_root = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $app_root = realpath(__DIR__ . '/..');
        if ($doc_root && $app_root && strncmp($app_root, $doc_root, strlen($doc_root)) === 0) {
            $rel = str_replace('\\', '/', substr($app_root, strlen($doc_root)));
            $rel = rtrim($rel, '/');
            if ($rel !== '' && $rel !== '/') {
                $cached = $rel;
            }
        }

        return $cached;
    }
}

if (!function_exists('app_url')) {
    /**
     * Préfixe un chemin absolu site (/css/…) avec le base path si nécessaire.
     */
    function app_url(string $path = ''): string
    {
        $base = get_app_base_path();
        if ($path === '' || $path === '/') {
            return $base === '' ? '/' : $base . '/';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        if ($base === '') {
            return $path;
        }

        if (strncmp($path, $base . '/', strlen($base) + 1) === 0 || $path === $base) {
            return $path;
        }

        return $base . $path;
    }
}

if (!function_exists('app_base_web_path_roots')) {
    /**
     * Segments racine autorisés pour la réécriture HTML (évite /Applications, /Users, etc.).
     *
     * @return string[]
     */
    function app_base_web_path_roots(): array
    {
        return [
            'css', 'js', 'image', 'icons', 'upload', 'admin', 'user', 'boutique', 'api', 'auth',
            'page', 'fonts', 'super_admin', 'scripts', 'colobane', 'pwa', '.well-known', 'dons',
        ];
    }
}

if (!function_exists('app_base_rewrite_html')) {
    function app_base_rewrite_html(string $html, string $base): string
    {
        if ($base === '') {
            return $html;
        }

        $base = rtrim($base, '/');
        $bq = preg_quote($base, '#');
        $roots = implode('|', array_map('preg_quote', app_base_web_path_roots()));

        // Dossiers applicatifs : /css/…, /admin/…, /upload/…
        $html = preg_replace(
            '#(?<=["\'])/(?!' . $bq . '/)((' . $roots . ')(?:/[^"\']*)?)#',
            $base . '/$1',
            $html
        );

        // Pages PHP à la racine : /index.php, /choix-connexion.php?…
        $html = preg_replace(
            '#(?<=["\'])/(?!' . $bq . '/)([a-z0-9_-]+\.php(?:\?[^"\']*)?)#i',
            $base . '/$1',
            $html
        );

        // Fichiers racine connus
        $html = preg_replace(
            '#(?<=["\'])/(?!' . $bq . '/)(manifest\.json|favicon\.ico|firebase-messaging-sw\.js|sw\.js|robots\.txt|sitemap\.xml)#',
            $base . '/$1',
            $html
        );

        // Accueil seul : href="/"
        $html = preg_replace(
            '#(?<=["\'])/(?!' . $bq . '/)(?=["\'])#',
            $base . '/',
            $html
        );

        // url(/css/…) dans CSS inline
        $html = preg_replace(
            '#url\(\s*/(?!' . $bq . '/)((' . $roots . ')/)#',
            'url(' . $base . '/$1',
            $html
        );
        $html = preg_replace(
            '#url\(\s*/(?!' . $bq . '/)([a-z0-9_-]+\.php)#i',
            'url(' . $base . '/$1',
            $html
        );

        return $html;
    }
}

if (!function_exists('app_base_start_output_buffer')) {
    function app_base_start_output_buffer(): void
    {
        static $started = false;
        if ($started) {
            return;
        }

        $base = get_app_base_path();
        if ($base === '') {
            return;
        }

        $started = true;
        ob_start(static function (string $html) use ($base): string {
            if (stripos($html, '<html') === false && stripos($html, '<!DOCTYPE') === false) {
                return $html;
            }

            return app_base_rewrite_html($html, $base);
        });
    }
}
