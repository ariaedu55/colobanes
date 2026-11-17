<?php
/**
 * Carte boutique du catalogue (couverture produits, logo, actions).
 * Variable : $boutique (array admin) ou $bt_card (array préparé)
 */
if (!function_exists('marketplace_boutique_prepare_card')) {
    require_once dirname(__DIR__) . '/marketplace_boutique_card_helpers.php';
}

$card = null;
if (isset($bt_card) && is_array($bt_card) && !empty($bt_card['nom'])) {
    $card = $bt_card;
} elseif (!empty($boutique) && is_array($boutique)) {
    $card = marketplace_boutique_prepare_card($boutique);
} else {
    return;
}

$theme = $card['theme'];
$style_vars = '--bt-main:' . htmlspecialchars($theme['main'], ENT_QUOTES, 'UTF-8')
    . ';--bt-dark:' . htmlspecialchars($theme['dark'], ENT_QUOTES, 'UTF-8')
    . ';--bt-accent:' . htmlspecialchars($theme['accent'], ENT_QUOTES, 'UTF-8')
    . ';--bt-band:' . htmlspecialchars($theme['band'], ENT_QUOTES, 'UTF-8');

$nb_produits = (int) ($card['nb_produits'] ?? 0);
$produits_label = $nb_produits . ' produit' . ($nb_produits > 1 ? 's' : '');
$has_geo = !empty($card['has_geo']);
$bt_geo_label = 'Boutique — ' . $card['nom'];
$bt_initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($card['nom'], 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($card['nom'], 0, 1));
$bt_cover = isset($card['produits_vignettes']) && is_array($card['produits_vignettes'])
    ? array_slice(array_values(array_filter($card['produits_vignettes'], function ($pv) {
        return !empty($pv['image_url']);
    })), 0, 3)
    : [];
$bt_cover_n = count($bt_cover);
$bt_href = htmlspecialchars($card['vitrine_href'], ENT_QUOTES, 'UTF-8');
$bt_meta = [$produits_label];
if ($card['distance_km'] !== null && function_exists('geo_format_distance')) {
    $bt_meta[] = geo_format_distance((float) $card['distance_km']);
} elseif (!empty($card['region'])) {
    $bt_meta[] = function_exists('mb_convert_case') ? mb_convert_case($card['region'], MB_CASE_TITLE, 'UTF-8') : ucwords($card['region']);
}
?>
<article class="bq-card<?php echo !empty($theme['has_custom']) ? ' bq-card--themed' : ''; ?>"
    style="<?php echo $style_vars; ?>" role="listitem">
    <a class="bq-card__cover bq-card__cover--<?php echo $bt_cover_n >= 3 ? 'mosaic' : ($bt_cover_n > 0 ? 'single' : 'empty'); ?>"
        href="<?php echo $bt_href; ?>" tabindex="-1" aria-hidden="true">
        <?php foreach ($bt_cover as $pv): ?>
            <img src="<?php echo htmlspecialchars((string) $pv['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                alt=""
                loading="lazy"
                decoding="async"
                onerror="this.remove();">
        <?php endforeach; ?>
    </a>
    <button type="button"
        class="bq-card__share js-platform-share"
        title="Partager cette boutique"
        aria-label="Partager la boutique <?php echo htmlspecialchars($card['nom'], ENT_QUOTES, 'UTF-8'); ?>"
        aria-haspopup="dialog"
        aria-controls="platformShareModal"
        data-share-modal-title="<?php echo htmlspecialchars($card['share_modal_title'] ?? 'Partager cette boutique', ENT_QUOTES, 'UTF-8'); ?>"
        data-share-title="<?php echo htmlspecialchars($card['share_title'], ENT_QUOTES, 'UTF-8'); ?>"
        data-share-url="<?php echo htmlspecialchars($card['share_url'], ENT_QUOTES, 'UTF-8'); ?>"
        data-share-text="<?php echo htmlspecialchars($card['share_text'], ENT_QUOTES, 'UTF-8'); ?>"
        data-share-hint="<?php echo htmlspecialchars($card['share_hint'] ?? 'Le lien ouvre la boutique publique sur COLObanes.', ENT_QUOTES, 'UTF-8'); ?>">
        <i class="fas fa-share-nodes" aria-hidden="true"></i>
    </button>

    <div class="bq-card__body">
        <a class="bq-card__logo" href="<?php echo $bt_href; ?>" tabindex="-1" aria-hidden="true">
            <span class="bq-card__initial"><?php echo htmlspecialchars($bt_initial, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($card['logo_url'] !== ''): ?>
                <img src="<?php echo htmlspecialchars($card['logo_url'], ENT_QUOTES, 'UTF-8'); ?>"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    onerror="this.remove();">
            <?php endif; ?>
        </a>
        <h3 class="bq-card__title">
            <a href="<?php echo $bt_href; ?>"><?php echo htmlspecialchars($card['nom'], ENT_QUOTES, 'UTF-8'); ?></a>
        </h3>
        <p class="bq-card__meta">
            <?php foreach ($bt_meta as $mi => $m): ?>
                <?php if ($mi > 0): ?><span class="bq-card__dot" aria-hidden="true">·</span><?php endif; ?>
                <span><?php echo htmlspecialchars((string) $m, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endforeach; ?>
        </p>

        <div class="bq-card__actions">
            <a href="<?php echo $bt_href; ?>" class="bq-card__visit"><span class="bq-card__visit-long">Voir la boutique</span><span class="bq-card__visit-short">Visiter</span></a>
            <?php if ($has_geo): ?>
                <button type="button"
                    class="bq-card__geo js-geo-open-maps"
                    title="Itinéraire vers la boutique"
                    aria-label="Itinéraire vers <?php echo htmlspecialchars($card['nom'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-lat="<?php echo htmlspecialchars((string) $card['lat'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-lng="<?php echo htmlspecialchars((string) $card['lng'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-label="<?php echo htmlspecialchars($bt_geo_label, ENT_QUOTES, 'UTF-8'); ?>"
                    data-maps-url="<?php echo htmlspecialchars($card['maps_url'], ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-diamond-turn-right" aria-hidden="true"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
</article>
