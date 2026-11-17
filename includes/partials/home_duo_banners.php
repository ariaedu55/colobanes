<?php
/**
 * Accueil — deux bannières côte à côte sous la 3e ligne de « Nos produits » :
 * « Offres du moment » (vers PROMO) et « Nouveautés ». Données réelles : $mp_duo (index.php).
 */
$mp_duo = $mp_duo ?? [];
$duo_promo_img = !empty($mp_duo['promo_img']) ? upload_image_url($mp_duo['promo_img'], 'md') : '';
$duo_new_img = !empty($mp_duo['new_img']) ? upload_image_url($mp_duo['new_img'], 'md') : '';
?>
<div class="mp-duo" id="mpDuo" role="region" aria-label="Offres et nouveautés">
    <a class="mp-duo__card mp-duo__card--promo" href="<?php echo htmlspecialchars(app_url('/promo.php'), ENT_QUOTES, 'UTF-8'); ?>">
        <span class="mp-duo__glow" aria-hidden="true"></span>
        <div class="mp-duo__text">
            <span class="mp-duo__eyebrow"><i class="fas fa-bolt" aria-hidden="true"></i> Offres du moment</span>
            <h3 class="mp-duo__title">Petits prix, <br>grandes affaires</h3>
            <?php if (!empty($mp_duo['promo_count'])): ?>
            <ul class="mp-duo__stats">
                <li><strong><?php echo (int) $mp_duo['promo_count']; ?></strong><span>produits</span></li>
                <?php if (!empty($mp_duo['promo_max'])): ?>
                <li><strong>-<?php echo (int) $mp_duo['promo_max']; ?>%</strong><span>jusqu’à</span></li>
                <?php endif; ?>
            </ul>
            <?php endif; ?>
            <span class="mp-duo__cta">Voir les promos <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
        </div>
        <?php if ($duo_promo_img !== ''): ?>
        <span class="mp-duo__visual" aria-hidden="true"><img src="<?php echo htmlspecialchars($duo_promo_img, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async"></span>
        <?php endif; ?>
    </a>
    <a class="mp-duo__card mp-duo__card--new" href="<?php echo htmlspecialchars(app_url('/nouveautes.php'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="mp-duo__text">
            <span class="mp-duo__eyebrow"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Nouveautés</span>
            <h3 class="mp-duo__title">Les derniers <br>arrivages</h3>
            <p class="mp-duo__sub">Soyez les premiers à les découvrir.</p>
            <span class="mp-duo__cta">Découvrir <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
        </div>
        <?php if ($duo_new_img !== ''): ?>
        <span class="mp-duo__visual" aria-hidden="true">
            <img src="<?php echo htmlspecialchars($duo_new_img, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
            <span class="mp-duo__badge">NEW</span>
        </span>
        <?php endif; ?>
    </a>
</div>
