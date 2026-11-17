<?php
/**
 * Bottom sheet — invitation à télécharger l'app native (mobile web).
 * Masqué sur desktop et dans la WebView native (is-native-app).
 */
if (!function_exists('app_mobile_version_load')) {
    require_once dirname(__DIR__) . '/app_mobile_version.php';
}
if (!function_exists('asset_version_query')) {
    require_once dirname(__DIR__) . '/asset_version.php';
}
if (!function_exists('app_url')) {
    require_once dirname(__DIR__) . '/app_base.php';
}
if (!defined('SITE_BRAND_NAME')) {
    require_once dirname(__DIR__) . '/site_brand.php';
}

$__asp_cfg = app_mobile_version_load();
$__asp_android = trim((string) ($__asp_cfg['store_android'] ?? ''));
$__asp_ios = trim((string) ($__asp_cfg['store_ios'] ?? ''));
if ($__asp_android === '') {
    $__asp_android = 'https://play.google.com/store/apps/details?id=com.colobanes.app&hl=fr';
}
if ($__asp_ios === '') {
    $__asp_ios = 'https://apps.apple.com/app/id6771130832';
}

$__asp_payload = [
    'android' => $__asp_android,
    'ios' => $__asp_ios,
    'delayMs' => 1600,
];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/app-store-prompt.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
<div class="asp-prompt" id="aspPrompt" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="aspPromptTitle" aria-describedby="aspPromptText">
    <button type="button" class="asp-prompt__backdrop" id="aspPromptBackdrop" aria-label="Fermer" tabindex="-1"></button>
    <div class="asp-prompt__sheet">
        <div class="asp-prompt__handle" aria-hidden="true"></div>
        <button type="button" class="asp-prompt__close" id="aspPromptClose" aria-label="Fermer">
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="asp-prompt__app">
            <img class="asp-prompt__logo"
                src="<?php echo htmlspecialchars(app_url('/image/logo_market.webp'), ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?>"
                width="190"
                height="51"
                decoding="async">
            <h2 class="visually-hidden" id="aspPromptTitle">Application <?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="asp-prompt__meta">
                <span>Application officielle</span>
                <span class="asp-prompt__free">Gratuit</span>
            </p>
        </div>
        <p class="asp-prompt__text" id="aspPromptText">Téléchargez l'application pour acheter plus facilement depuis votre téléphone.</p>
        <ul class="asp-prompt__perks">
            <li><i class="fas fa-bolt" aria-hidden="true"></i> Navigation plus fluide et plus rapide</li>
            <li><i class="fas fa-bell" aria-hidden="true"></i> Alertes sur vos commandes et messages</li>
            <li><i class="fas fa-heart" aria-hidden="true"></i> Vos boutiques et favoris à portée de main</li>
        </ul>
        <div class="asp-prompt__actions">
            <a class="asp-prompt__store asp-prompt__store--ios js-asp-store" href="<?php echo htmlspecialchars($__asp_ios, ENT_QUOTES, 'UTF-8'); ?>" rel="noopener">
                <i class="fab fa-apple" aria-hidden="true"></i>
                <span><small>Télécharger dans</small>l'App Store</span>
            </a>
            <a class="asp-prompt__store asp-prompt__store--android js-asp-store" href="<?php echo htmlspecialchars($__asp_android, ENT_QUOTES, 'UTF-8'); ?>" rel="noopener">
                <svg class="asp-prompt__gplay" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="#00d7fe" d="M3.6 1.8 13.4 12l-9.8 10.2c-.4-.2-.6-.7-.6-1.2V3c0-.5.2-1 .6-1.2z"/>
                    <path fill="#00f076" d="m16.8 8.6-3.4 3.4-9.8-10.2c.3-.2.8-.2 1.2 0z"/>
                    <path fill="#ff3a44" d="m16.8 15.4-12 6.8c-.4.2-.9.2-1.2 0L13.4 12z"/>
                    <path fill="#ffd500" d="m20.6 10.7-3.8-2.1-3.4 3.4 3.4 3.4 3.8-2.1c.9-.6.9-2 0-2.6z"/>
                </svg>
                <span><small>Disponible sur</small>Google Play</span>
            </a>
            <button type="button" class="asp-prompt__later" id="aspPromptLater">Continuer sur le site</button>
        </div>
    </div>
</div>
<script type="application/json" id="aspPromptData"><?php
    echo json_encode($__asp_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?></script>
<script src="<?php echo htmlspecialchars(app_url('/js/app-store-prompt.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
