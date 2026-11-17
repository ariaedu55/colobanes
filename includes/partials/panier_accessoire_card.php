<?php
/**
 * Carte accessoire (panier) : image, type, nom, prix, bouton « + » d'ajout direct.
 * Variables : $produit, $return_url (optionnel)
 */
if (empty($produit) || !is_array($produit)) {
    return;
}
$pid = (int) ($produit['id'] ?? 0);
if ($pid <= 0) {
    return;
}
if (!function_exists('upload_image_url')) {
    require_once __DIR__ . '/../image_optimizer.php';
}
if (!function_exists('app_url')) {
    require_once __DIR__ . '/../app_base.php';
}
if (!function_exists('site_add_to_panier_hidden_fields')) {
    require_once __DIR__ . '/../site_csrf.php';
}
$return_url = isset($return_url) ? (string) $return_url : '/panier.php';
$nom = (string) ($produit['nom'] ?? 'Produit');
$prix_base = (float) ($produit['prix'] ?? 0);
$prix_promo = (float) ($produit['prix_promotion'] ?? 0);
$en_promo = $prix_promo > 0 && $prix_promo < $prix_base;
$prix = $en_promo ? $prix_promo : $prix_base;
$produit_href = app_url('/produit.php?id=' . $pid . '&mp_ref=panier_accessoire');
$img = upload_image_url($produit['image_principale'] ?? '', 'sm');
$fallback = app_url('/image/produit1.jpg');
$bundle_slot = trim((string) ($produit['panier_bundle_slot'] ?? ''));
?>
<article class="pacc-card" role="listitem">
    <a class="pacc-card__img" href="<?php echo htmlspecialchars($produit_href, ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1" aria-hidden="true">
        <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" width="160" height="160"
            onerror="this.src='<?php echo htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8'); ?>'">
        <?php if ($bundle_slot !== ''): ?>
            <span class="pacc-card__tag"><?php echo htmlspecialchars($bundle_slot, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php elseif ($en_promo): ?>
            <span class="pacc-card__tag pacc-card__tag--promo">Promo</span>
        <?php endif; ?>
    </a>
    <div class="pacc-card__body">
        <a class="pacc-card__name" href="<?php echo htmlspecialchars($produit_href, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'); ?></a>
        <p class="pacc-card__price">
            <strong><?php echo number_format($prix, 0, ',', ' '); ?> FCFA</strong>
            <?php if ($en_promo): ?><s><?php echo number_format($prix_base, 0, ',', ' '); ?></s><?php endif; ?>
        </p>
    </div>
    <form method="POST" action="<?php echo htmlspecialchars(app_url('/add-to-panier.php'), ENT_QUOTES, 'UTF-8'); ?>" class="pacc-card__form" data-pacc-add>
        <?php site_add_to_panier_hidden_fields(); ?>
        <input type="hidden" name="produit_id" value="<?php echo $pid; ?>">
        <input type="hidden" name="quantite" value="1">
        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="pacc-card__btn" aria-label="Ajouter <?php echo htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'); ?> au panier">
            <i class="fas fa-plus" aria-hidden="true"></i><span class="pacc-card__btn-label">Ajouter</span>
        </button>
    </form>
</article>
