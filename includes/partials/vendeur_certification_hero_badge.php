<?php
/**
 * Statut certification dans les bandeaux hero vendeur
 * Affiche clairement que la boutique est certifiée (niveau inclus).
 */
if (empty($__vendeur_cert_niveau_hero)) {
    return;
}
require_once __DIR__ . '/../../models/model_vendeur_certification.php';
$cert_niveau = (string) $__vendeur_cert_niveau_hero;
if (!in_array($cert_niveau, ['standard', 'vip', 'premium'], true)) {
    return;
}
$cert_meta = vendeur_certification_niveaux()[$cert_niveau];
$cert_label = (string) ($cert_meta['label'] ?? ucfirst($cert_niveau));
$cert_icon = (string) ($cert_meta['icon'] ?? 'fa-shield');
$cert_img_src = vendeur_certification_badge_image_src($cert_niveau);
$cert_aria = 'Boutique certifiée ' . $cert_label;
?>
<div class="vendeur-hero-cert vendeur-hero-cert--<?php echo htmlspecialchars($cert_niveau, ENT_QUOTES, 'UTF-8'); ?>"
    role="status"
    aria-label="<?php echo htmlspecialchars($cert_aria, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="vendeur-hero-cert__mark" aria-hidden="true">
        <?php if ($cert_img_src !== ''): ?>
            <img src="<?php echo htmlspecialchars($cert_img_src, ENT_QUOTES, 'UTF-8'); ?>"
                alt=""
                class="vendeur-hero-cert__img"
                loading="lazy"
                decoding="async"
                draggable="false">
        <?php else: ?>
            <i class="fas <?php echo htmlspecialchars($cert_icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
        <?php endif; ?>
    </div>
    <div class="vendeur-hero-cert__text">
        <span class="vendeur-hero-cert__status">Boutique certifi&eacute;e</span>
    </div>
</div>
