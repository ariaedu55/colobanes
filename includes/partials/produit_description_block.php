<?php
/**
 * Bloc description fiche produit (extrait + « voir plus »).
 * Variables : $produit (array), $produit_desc_excerpt (array) optionnel.
 */
if (empty($produit['description'])) {
    return;
}
require_once dirname(__DIR__) . '/produit_description_excerpt.php';
if (!isset($produit_desc_excerpt) || !is_array($produit_desc_excerpt)) {
    $produit_desc_excerpt = produit_description_prepare_excerpt((string) $produit['description']);
}
$desc_raw = (string) $produit['description'];
/* Aperçu sur une seule ligne (coupée par le CSS) dès que le texte risque de dépasser une ligne */
$desc_plain = trim(produit_description_plain_text($desc_raw));
$desc_one_line = !empty($produit_desc_excerpt['truncated'])
    || mb_strlen($desc_plain, 'UTF-8') > 60
    || preg_match('/\R/u', trim($desc_raw));
if ($desc_one_line && empty($produit_desc_excerpt['truncated'])) {
    $produit_desc_excerpt['short'] = $desc_plain;
}
?>
<div class="produit-description produit-section-bg">
    <h3>Description</h3>
    <?php if (!$desc_one_line): ?>
        <div class="produit-desc-full"><?php echo produit_description_format_html($desc_raw); ?></div>
    <?php else: ?>
        <div class="produit-desc-wrap" data-produit-desc>
            <p class="produit-desc-preview">
                <span class="produit-desc-excerpt"><?php echo htmlspecialchars($produit_desc_excerpt['short'], ENT_QUOTES, 'UTF-8'); ?></span>
                <button type="button" class="produit-desc-more" aria-expanded="false">voir plus</button>
            </p>
            <div class="produit-desc-expanded" hidden>
                <?php echo produit_description_format_html($desc_raw); ?>
            </div>
        </div>
    <?php endif; ?>
</div>
