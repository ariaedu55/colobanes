<?php
/**
 * Popup professionnelle — incitation certification vendeur.
 * Variables : $__vendeur_admin, $__vendeur_cert_promo_ctx (array)
 */
if (empty($__vendeur_admin) || !is_array($__vendeur_admin)) {
    return;
}
if (empty($__vendeur_cert_promo_ctx) || !is_array($__vendeur_cert_promo_ctx)) {
    return;
}

require_once dirname(__DIR__, 2) . '/includes/asset_version.php';
require_once dirname(__DIR__, 2) . '/includes/app_base.php';

$vcp = $__vendeur_cert_promo_ctx;
$vcp_title = (string) ($vcp['title'] ?? '');
$vcp_cta_label = (string) ($vcp['cta_label'] ?? 'En savoir plus');
$vcp_cta_href = (string) ($vcp['cta_href'] ?? app_url('/admin/parametres/certification.php'));
$vcp_benefits = isset($vcp['benefits']) && is_array($vcp['benefits']) ? $vcp['benefits'] : [];
$vcp_mode = (string) ($vcp['mode'] ?? 'first');
$vcp_next = (string) ($vcp['next_niveau'] ?? 'standard');

if ($vcp_title === '') {
    return;
}

$redirect_back = $_SERVER['REQUEST_URI'] ?? app_url('/admin/dashboard.php');
$dismiss_url = app_url('/admin/vendeur-certification-promo-dismiss.php') . '?redirect=' . rawurlencode($redirect_back);
$snooze_url = app_url('/admin/vendeur-certification-promo-snooze.php') . '?days=30&redirect=' . rawurlencode($redirect_back);
$vcp_logo_src = app_url('/image/logo_market.webp');
?>
<link rel="stylesheet" href="/css/vendeur-certification-promo-modal.css<?php echo asset_version_query(); ?>">

<div class="vcpromo-overlay" id="vcpromoOverlay" role="dialog" aria-modal="true" aria-labelledby="vcpromoTitle">
    <div class="vcpromo-modal">
        <button type="button" class="vcpromo-modal__close" id="vcpromoCloseBtn" aria-label="Fermer">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>

        <header class="vcpromo-modal__header">
            <img src="<?php echo htmlspecialchars($vcp_logo_src, ENT_QUOTES, 'UTF-8'); ?>" alt="COLObanes" class="vcpromo-modal__logo" width="132" height="40" decoding="async">
            <p class="vcpromo-modal__kicker">Programme vendeur certifié</p>
            <h2 class="vcpromo-modal__title" id="vcpromoTitle"><?php echo htmlspecialchars($vcp_title, ENT_QUOTES, 'UTF-8'); ?></h2>
        </header>

        <div class="vcpromo-modal__body">
            <div class="vcpromo-panel">
                <?php if ($vcp_mode === 'upgrade' && in_array($vcp_next, ['vip', 'premium'], true)): ?>
                <div class="vcpromo-panel__badge">
                    <?php $cert_niveau = $vcp_next; $cert_size = 'md'; require __DIR__ . '/vendeur_certification_badge.php'; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($vcp_benefits)): ?>
                <p class="vcpromo-panel__heading">Ce que vous gagnez</p>
                <ul class="vcpromo-benefits">
                    <?php
                    $vcp_benefit_icons = ['fa-award', 'fa-bullhorn', 'fa-shield-halved'];
                    foreach ($vcp_benefits as $bi => $benefit):
                        $icon = $vcp_benefit_icons[(int) $bi % count($vcp_benefit_icons)];
                        $tier = ((int) $bi % 3) + 1;
                    ?>
                    <li class="vcpromo-benefit-card vcpromo-benefit-card--tier-<?php echo $tier; ?>">
                        <span class="vcpromo-benefit-card__icon" aria-hidden="true">
                            <i class="fas <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                        </span>
                        <p class="vcpromo-benefit-card__text"><?php echo htmlspecialchars((string) $benefit, ENT_QUOTES, 'UTF-8'); ?></p>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <footer class="vcpromo-modal__footer">
            <a href="<?php echo htmlspecialchars($vcp_cta_href, ENT_QUOTES, 'UTF-8'); ?>" class="vcpromo-btn vcpromo-btn--primary">
                <?php echo htmlspecialchars($vcp_cta_label, ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a href="<?php echo htmlspecialchars($dismiss_url, ENT_QUOTES, 'UTF-8'); ?>" class="vcpromo-btn vcpromo-btn--ghost" id="vcpromoDismissLink">
                Plus tard
            </a>
            <a href="<?php echo htmlspecialchars($snooze_url, ENT_QUOTES, 'UTF-8'); ?>" class="vcpromo-link-snooze">
                Ne plus afficher ce mois-ci
            </a>
        </footer>
    </div>
</div>
<script>
(function () {
    var overlay = document.getElementById('vcpromoOverlay');
    var closeBtn = document.getElementById('vcpromoCloseBtn');
    var dismissLink = document.getElementById('vcpromoDismissLink');
    if (!overlay) return;
    var dismissUrl = dismissLink ? dismissLink.getAttribute('href') : '';
    function goDismiss() {
        if (dismissUrl) {
            window.location.href = dismissUrl;
        } else {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    if (closeBtn) closeBtn.addEventListener('click', goDismiss);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) goDismiss(); });
    document.body.style.overflow = 'hidden';
})();
</script>
