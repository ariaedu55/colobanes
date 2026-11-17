<?php
/**
 * Invitation au don « Vos affaires peuvent encore servir » — site et application.
 * Affichée au plus une fois tous les 3 jours par appareil (js/dons-prompt.js),
 * jamais sur les pages dons, panier, commande ou connexion, ni si le module dons est fermé.
 */
if (defined('DONS_PROMPT_LOADED')) {
    return;
}
define('DONS_PROMPT_LOADED', true);

$__dp_path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
if (preg_match('#/(dons|admin|super_admin)/|/(panier|commande|commande-confirmee|choix-connexion|choix-inscription|sign-up|mot-de-passe-oublie)\.php$#', $__dp_path)) {
    return;
}

global $db;
if (!$db instanceof PDO) {
    return;
}
if (!function_exists('dons_module_actif')) {
    require_once dirname(__DIR__, 2) . '/models/model_dons.php';
}
try {
    if (!dons_module_actif()) {
        return;
    }
    $__dp_count = (int) $db->query("SELECT COUNT(*) FROM dons WHERE statut IN ('disponible', 'reserve', 'donne')")->fetchColumn();
} catch (Throwable $e) {
    return;
}

if (!function_exists('app_url')) {
    require_once dirname(__DIR__) . '/app_base.php';
}
if (!function_exists('asset_version_query')) {
    require_once dirname(__DIR__) . '/asset_version.php';
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/dons-prompt.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
<div class="dp-root" id="donsPrompt" hidden aria-hidden="true">
    <div class="dp-backdrop" data-dp-close></div>
    <section class="dp-card" role="dialog" aria-modal="true" aria-labelledby="dpTitle" aria-describedby="dpText">
        <button type="button" class="dp-close" data-dp-close aria-label="Fermer">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
        <div class="dp-visual" aria-hidden="true">
            <span class="dp-visual__main"><i class="fas fa-hand-holding-heart"></i></span>
            <span class="dp-visual__chip dp-visual__chip--a"><i class="fas fa-shirt"></i></span>
            <span class="dp-visual__chip dp-visual__chip--b"><i class="fas fa-shoe-prints"></i></span>
            <span class="dp-visual__chip dp-visual__chip--c"><i class="fas fa-book"></i></span>
        </div>
        <h2 class="dp-title" id="dpTitle">Vos affaires peuvent encore servir</h2>
        <p class="dp-text" id="dpText">Un habit, un pull, des chaussures… Donnez gratuitement ce que vous n’utilisez plus à quelqu’un près de chez vous.</p>
        <?php if ($__dp_count >= 5): ?>
        <p class="dp-proof"><i class="fas fa-heart" aria-hidden="true"></i> <?php echo number_format($__dp_count, 0, ',', ' '); ?> objets déjà partagés sur COLObanes</p>
        <?php endif; ?>
        <div class="dp-actions">
            <a class="dp-btn dp-btn--primary" href="<?php echo htmlspecialchars(app_url('/dons/offrir.php?mp_ref=popup_don'), ENT_QUOTES, 'UTF-8'); ?>" data-dp-cta>
                <i class="fas fa-gift" aria-hidden="true"></i> Faire un don
            </a>
            <a class="dp-btn dp-btn--ghost" href="<?php echo htmlspecialchars(app_url('/dons/?mp_ref=popup_don'), ENT_QUOTES, 'UTF-8'); ?>" data-dp-cta>Voir les dons</a>
        </div>
        <button type="button" class="dp-later" data-dp-close>Plus tard</button>
    </section>
</div>
<script src="<?php echo htmlspecialchars(app_url('/js/dons-prompt.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
