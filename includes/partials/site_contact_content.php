<?php
/**
 * Bloc coordonnées COLObanes (menu latéral, page contact, etc.)
 */
$site_contact_email = isset($site_contact_email) ? (string) $site_contact_email : 'contact@colobanes.com';
$site_contact_adresse = isset($site_contact_adresse)
    ? (string) $site_contact_adresse
    : 'Rond Point Colobane, Dakar, Sénégal';

if (!isset($social_config) || !is_array($social_config)) {
    $social_config = [];
    if (file_exists(__DIR__ . '/../../config/social.php')) {
        $social_config = require __DIR__ . '/../../config/social.php';
    }
}
if (!is_array($social_config)) {
    $social_config = [];
}

$site_contact_show_social = (
    trim((string) ($social_config['instagram'] ?? '')) !== ''
    || trim((string) ($social_config['facebook'] ?? '')) !== ''
    || trim((string) ($social_config['linkedin'] ?? '')) !== ''
    || trim((string) ($social_config['tiktok'] ?? '')) !== ''
);
?>
<div class="site-contact-content">
    <p class="site-contact-intro">Une question ? Une suggestion ? N&rsquo;h&eacute;sitez pas &agrave; nous &eacute;crire.</p>

    <div class="site-contact-info">
        <h3 class="site-contact-info__title"><i class="fas fa-info-circle" aria-hidden="true"></i> Nos coordonn&eacute;es</h3>
        <div class="site-contact-item">
            <span class="site-contact-item__icon" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></span>
            <div>
                <strong>Localisation</strong><br>
                <?php echo htmlspecialchars($site_contact_adresse, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
        <div class="site-contact-item">
            <span class="site-contact-item__icon" aria-hidden="true"><i class="fas fa-envelope"></i></span>
            <div>
                <strong>Courrier</strong><br>
                <a href="mailto:<?php echo htmlspecialchars($site_contact_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($site_contact_email, ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        </div>
        <?php if ($site_contact_show_social): ?>
        <div class="site-contact-social">
            <h4><i class="fas fa-share-alt" aria-hidden="true"></i> Suivez-nous</h4>
            <p>Rejoignez la communaut&eacute; sur les r&eacute;seaux sociaux.</p>
            <?php include __DIR__ . '/../social_links_block.php'; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="site-contact-map" id="livraison">
        <h3 class="site-contact-map__title"><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Notre localisation</h3>
        <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3858.0!2d-17.4486!3d14.7167!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xec112b5f6e5e6e5%3A0x6e5e5e5e5e5e5e5e!2sRond%20Point%20Colobane%2C%20Dakar!5e0!3m2!1sfr!2ssn!4v1700000000!5m2!1sfr!2ssn"
            allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
            title="Localisation COLObanes - Rond Point Colobane, Dakar">
        </iframe>
    </div>
</div>
