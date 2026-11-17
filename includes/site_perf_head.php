<?php
/**
 * Aides performance front (hints réseau, CSS non bloquant).
 */

if (!function_exists('site_perf_emit_resource_hints')) {
    function site_perf_emit_resource_hints(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        echo '<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">' . "\n";
        echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>' . "\n";
        if (!function_exists('app_url')) {
            require_once __DIR__ . '/app_base.php';
        }
        if (!function_exists('asset_version_query')) {
            require_once __DIR__ . '/asset_version.php';
        }
    }
}

if (!function_exists('site_perf_stylesheet_deferred')) {
    /**
     * Charge une feuille CSS sans bloquer le premier rendu (carousel, animations…).
     */
    function site_perf_stylesheet_deferred(string $href): void
    {
        $href = trim($href);
        if ($href === '') {
            return;
        }
        $esc = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        echo '<link rel="preload" href="' . $esc . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
        echo '<noscript><link rel="stylesheet" href="' . $esc . '"></noscript>' . "\n";
    }
}

if (!function_exists('site_perf_preload_stylesheet')) {
    function site_perf_preload_stylesheet(string $href): void
    {
        $href = trim($href);
        if ($href === '') {
            return;
        }
        echo '<link rel="preload" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" as="style">' . "\n";
    }
}
