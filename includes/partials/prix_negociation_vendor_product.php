<?php
/**
 * Partial — carte produit avec offres en attente (dashboard vendeur → messagerie)
 * Variables : $prix_neg_groupe, $prix_neg_messages_href (optionnel)
 */
if (!isset($prix_neg_groupe) || !is_array($prix_neg_groupe)) {
    return;
}

$pn_prod_id = (int) ($prix_neg_groupe['produit_id'] ?? 0);
$pn_prod_nom = (string) ($prix_neg_groupe['produit_nom'] ?? 'Produit');
$pn_prod_img = trim((string) ($prix_neg_groupe['produit_image'] ?? ''));
$pn_pending = (int) ($prix_neg_groupe['pending_count'] ?? 0);

if (!function_exists('upload_image_url')) {
    require_once dirname(__DIR__) . '/image_optimizer.php';
}
if (!function_exists('app_url')) {
    require_once dirname(__DIR__) . '/app_base.php';
}

$pn_img_url = $pn_prod_img !== '' ? upload_image_url($pn_prod_img, 'sm') : '';
$pn_href = isset($prix_neg_messages_href) && trim((string) $prix_neg_messages_href) !== ''
    ? trim((string) $prix_neg_messages_href)
    : app_url('/admin/messages.php?filtre=negociations');
?>
<a href="<?php echo htmlspecialchars($pn_href, ENT_QUOTES, 'UTF-8'); ?>" class="prix-neg-produit-card prix-neg-produit-card--link"
    aria-label="Ouvrir la messagerie pour <?php echo htmlspecialchars($pn_prod_nom, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="prix-neg-produit-card__media">
        <?php if ($pn_img_url !== ''): ?>
        <img src="<?php echo htmlspecialchars($pn_img_url, ENT_QUOTES, 'UTF-8'); ?>"
            alt="<?php echo htmlspecialchars($pn_prod_nom, ENT_QUOTES, 'UTF-8'); ?>"
            loading="lazy" width="72" height="72">
        <?php else: ?>
        <span class="prix-neg-produit-card__placeholder" aria-hidden="true"><i class="fas fa-box"></i></span>
        <?php endif; ?>
        <?php if ($pn_pending > 0): ?>
        <span class="prix-neg-produit-card__badge"><?php echo $pn_pending; ?></span>
        <?php endif; ?>
    </div>
    <div class="prix-neg-produit-card__body">
        <h4 class="prix-neg-produit-card__title"><?php echo htmlspecialchars($pn_prod_nom, ENT_QUOTES, 'UTF-8'); ?></h4>
        <p class="prix-neg-produit-card__meta">
            <?php if ($pn_pending > 0): ?>
                <?php echo $pn_pending; ?> offre<?php echo $pn_pending > 1 ? 's' : ''; ?> en attente
            <?php else: ?>
                N&eacute;gociations en cours
            <?php endif; ?>
        </p>
    </div>
    <span class="prix-neg-btn prix-neg-btn--view-offers">
        <i class="fas fa-comments" aria-hidden="true"></i>
        <span>Répondre dans la messagerie</span>
    </span>
</a>
