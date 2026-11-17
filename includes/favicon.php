<?php
/**
 * Favicon / icônes pour Google, navigateurs et PWA.
 * Inclure une seule fois dans le <head> (via pwa_meta.php).
 */
if (defined('FAVICON_META_INCLUDED')) {
    return;
}
define('FAVICON_META_INCLUDED', true);

if (!function_exists('get_site_base_url')) {
    require_once __DIR__ . '/site_url.php';
}
// URL sans paramètre de version : Google exige une adresse d'icône stable dans le temps.
$favicon_base = rtrim(get_site_base_url(), '/');
?>
<link rel="icon" href="<?php echo htmlspecialchars($favicon_base . '/favicon.ico', ENT_QUOTES, 'UTF-8'); ?>" sizes="any">
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo htmlspecialchars($favicon_base . '/site-icons/site-icon-16.png', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($favicon_base . '/site-icons/site-icon-32.png', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="icon" type="image/png" sizes="48x48" href="<?php echo htmlspecialchars($favicon_base . '/site-icons/site-icon-48.png', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="icon" type="image/png" sizes="96x96" href="<?php echo htmlspecialchars($favicon_base . '/site-icons/site-icon-96.png', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="icon" type="image/png" sizes="144x144" href="<?php echo htmlspecialchars($favicon_base . '/site-icons/site-icon-144.png', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?php echo htmlspecialchars($favicon_base . '/site-icons/site-icon-192.png', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="icon" type="image/png" sizes="256x256" href="<?php echo htmlspecialchars($favicon_base . '/site-icons/site-icon-256.png', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="icon" type="image/png" sizes="512x512" href="<?php echo htmlspecialchars($favicon_base . '/site-icons/site-icon-512.png', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="shortcut icon" type="image/png" href="<?php echo htmlspecialchars($favicon_base . '/site-icons/site-icon-48.png', ENT_QUOTES, 'UTF-8'); ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo htmlspecialchars($favicon_base . '/site-icons/site-icon-180.png', ENT_QUOTES, 'UTF-8'); ?>">
<?php
require_once __DIR__ . '/skeleton_shimmer.php';
skeleton_shimmer_include_head();
?>
