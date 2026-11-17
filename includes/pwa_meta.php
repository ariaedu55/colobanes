<?php
/**
 * Meta tags PWA (sans enregistrement de Service Worker).
 *
 * IMPORTANT:
 * Le SW PWA (/sw.js) est volontairement désactivé globalement pour éviter
 * tout conflit avec Firebase Messaging (/firebase-messaging-sw.js).
 */
if (!function_exists('get_asset_version')) {
    require_once __DIR__ . '/asset_version.php';
}
if (!function_exists('app_url')) {
    require_once __DIR__ . '/app_base.php';
}
$asset_version = get_asset_version();
?>
<?php include __DIR__ . '/favicon.php'; ?>
<meta name="theme-color" content="#3564a6">
<?php /* Pas de Smart App Banner Apple : l'invitation unique est includes/partials/app_store_prompt.php (tous navigateurs mobiles). */ ?>
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<?php if (!defined('SITE_BRAND_NAME')) { require_once __DIR__ . '/site_brand.php'; } ?>
<meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?>">
<meta name="application-name" content="<?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="manifest" href="<?php echo htmlspecialchars(app_url('/manifest.json'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="dns-prefetch" href="https://cdn.gtranslate.net">
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php /* Mode application : détecte la WebView Flutter et active les optimisations de scroll */ ?>
<script>(function(){try{var n=(window.__COLOBANES_NATIVE_APP===true)||(navigator.userAgent.indexOf('ColobanesApp')!==-1);if(n){document.documentElement.classList.add('is-native-app');}}catch(e){}})();</script>
<link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/app-performance.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo htmlspecialchars((string) $asset_version, ENT_QUOTES, 'UTF-8'); ?>">
<script src="<?php echo htmlspecialchars(app_url('/js/app-native-perf.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo htmlspecialchars((string) $asset_version, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
