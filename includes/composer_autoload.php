<?php
/**
 * Autoload Composer à la demande (évite de charger vendor sur chaque requête PDO).
 */
if (!function_exists('app_composer_autoload')) {
    function app_composer_autoload(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
        $loaded = true;
    }
}
