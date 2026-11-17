<?php
/**
 * Popup activation compte vendeur — premier produit requis.
 */
if (!function_exists('vendeur_onboarding_should_show_popup_modal')
    || !vendeur_onboarding_should_show_popup_modal()) {
    return;
}

require_once dirname(__DIR__, 2) . '/includes/asset_version.php';
require_once dirname(__DIR__, 2) . '/includes/app_base.php';
require_once dirname(__DIR__, 2) . '/models/model_vendeur_onboarding.php';

$vonb_logo_src = app_url('/image/logo_market.webp');

$von_cta_href = vendeur_onboarding_cta_href();
$boutique_nom = trim((string) ($_SESSION['admin_boutique_nom'] ?? ''));
if ($boutique_nom === '') {
    $boutique_nom = 'votre boutique';
}
$redirect_back = $_SERVER['REQUEST_URI'] ?? app_url('/admin/dashboard.php');
$dismiss_url = app_url('/admin/vendeur-onboarding-dismiss.php') . '?redirect=' . rawurlencode($redirect_back);
?>
<link rel="stylesheet" href="/css/vendeur-onboarding-modal.css<?php echo asset_version_query(); ?>">

<div class="vonb-overlay" id="vonbOverlay" role="dialog" aria-modal="true" aria-labelledby="vonbTitle">
    <div class="vonb-modal">
        <button type="button" class="vonb-modal__close" id="vonbCloseBtn" aria-label="Fermer">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
        <div class="vonb-modal__icon">
            <img src="<?php echo htmlspecialchars($vonb_logo_src, ENT_QUOTES, 'UTF-8'); ?>" alt="COLObanes" width="120" height="48" class="vonb-modal__logo" decoding="async">
        </div>
        <h2 class="vonb-modal__title" id="vonbTitle">Activez votre compte vendeur</h2>
        <p class="vonb-modal__text">
            Pour activer votre boutique
            <strong class="vonb-modal__boutique">«&nbsp;<?php echo htmlspecialchars($boutique_nom, ENT_QUOTES, 'UTF-8'); ?>&nbsp;»</strong>,
            il faut ajouter un produit.
        </p>
        <div class="vonb-modal__actions">
            <a href="<?php echo htmlspecialchars($von_cta_href, ENT_QUOTES, 'UTF-8'); ?>" class="vonb-btn vonb-btn--primary" id="vonbCtaAddProduit">
                <i class="fas fa-plus" aria-hidden="true"></i> Ajouter mon premier produit
            </a>
            <a href="<?php echo htmlspecialchars($dismiss_url, ENT_QUOTES, 'UTF-8'); ?>" class="vonb-btn vonb-btn--ghost" id="vonbDismissLink">
                Plus tard
            </a>
        </div>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('vonbOverlay');
    var closeBtn = document.getElementById('vonbCloseBtn');
    var dismissLink = document.getElementById('vonbDismissLink');
    if (!overlay) return;
    var dismissUrl = dismissLink ? dismissLink.getAttribute('href') : '';
    function goDismiss() {
        if (dismissUrl) {
            window.location.href = dismissUrl;
        } else {
            overlay.style.display = 'none';
        }
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', goDismiss);
    }
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            goDismiss();
        }
    });
    document.body.style.overflow = 'hidden';
})();
</script>
