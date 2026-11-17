<?php
/**
 * Scripts tiers en chargement différé (GTranslate) — inclus une fois via footer.php
 */
if (defined('COLOBANES_PERF_LAZY_LOADED')) {
    return;
}
define('COLOBANES_PERF_LAZY_LOADED', true);

if (!function_exists('asset_version_query')) {
    require_once __DIR__ . '/asset_version.php';
}

$lazy_gtranslate = 'https://translate.google.com/translate_a/element.js';
?>
<script>
window.__COLOBANES_LAZY = {
    gtranslate: <?php echo json_encode($lazy_gtranslate, JSON_UNESCAPED_SLASHES); ?>,
    languages: 'fr,en,es,pt,ar'
};
</script>
<style>
/* Masque la barre et les infobulles Google Translate (le sélecteur du site les remplace) */
.skiptranslate iframe, iframe.skiptranslate, .goog-te-banner-frame, #goog-gt-tt, .goog-te-balloon-frame,
.VIpgJd-ZVi9od-ORHb-OEVmcd, .VIpgJd-ZVi9od-aZ2wEe-wOHMyf, .VIpgJd-yAWNEb-L7lbkb { display: none !important; }
body { top: 0 !important; }
.VIpgJd-yAWNEb-VIpgJd-fmcmS-sn54Q { background-color: transparent !important; box-shadow: none !important; }
</style>
<script src="/js/deferred-third-party.js<?php echo asset_version_query(); ?>" defer></script>
