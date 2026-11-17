<?php
/**
 * Skeleton shimmer — chargement visuel des images et cartes (hors super_admin).
 */
function skeleton_shimmer_is_enabled(): bool
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script !== '' && strpos($script, '/super_admin/') !== false) {
        return false;
    }
    return true;
}

function skeleton_shimmer_include_head(): void
{
    if (!skeleton_shimmer_is_enabled()) {
        return;
    }
    if (!function_exists('asset_version_query')) {
        require_once __DIR__ . '/asset_version.php';
    }
    if (!function_exists('app_url')) {
        require_once __DIR__ . '/app_base.php';
    }
    $vq = asset_version_query();
    $app_base = get_app_base_path();
    echo '<script>document.documentElement.classList.add("sk-shimmer-pending");</script>' . "\n";
    echo '<script>window.__APP_BASE__=' . json_encode($app_base, JSON_UNESCAPED_SLASHES) . ';</script>' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(app_url('/css/skeleton-shimmer.css' . $vq), ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<script src="' . htmlspecialchars(app_url('/js/skeleton-shimmer.js' . $vq), ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
}
