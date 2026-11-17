<?php
/**
 * Carte produit « marketplace » pour la page d'accueil (index.php).
 * Variables attendues : $produit (tableau), $return_url (string optionnel).
 * Optionnel : $show_nouveau_badge (bool) — affiche le badge « Nouveau ».
 */
if (empty($produit) || !is_array($produit)) {
    return;
}
if (!function_exists('upload_image_url')) {
    require_once __DIR__ . '/../image_optimizer.php';
}
$return_url = isset($return_url) ? (string) $return_url : (string) ($_SERVER['REQUEST_URI'] ?? '/index.php');
$card_prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
    ? $produit['prix_promotion']
    : $produit['prix'];
$has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
$show_nouveau_badge = !empty($show_nouveau_badge);
$pid = (int) ($produit['id'] ?? 0);
if ($pid <= 0) {
    return;
}
if (!function_exists('app_url')) {
    require_once __DIR__ . '/../app_base.php';
}
$mp_ref_card = isset($mp_ref) && (string) $mp_ref !== '' ? (string) $mp_ref : 'home_grid';
$produit_href = app_url('/produit.php?id=' . $pid . '&mp_ref=' . rawurlencode($mp_ref_card));
$panier_action = app_url('/add-to-panier.php');
$image_fallback = app_url('/image/produit1.jpg');

?>
<article class="mp-card">
    <?php require __DIR__ . '/product_share_button.php'; ?>
    <?php require __DIR__ . '/mp_favori_button.php'; ?>
    <a href="<?php echo htmlspecialchars($produit_href, ENT_QUOTES, 'UTF-8'); ?>" class="mp-card-link">
        <div class="mp-card-img">
            <?php if ($show_nouveau_badge): ?>
            <span class="mp-card-badge mp-card-badge--nouveau">Nouveau</span>
            <?php endif; ?>
            <?php $mp_gros_part = 'badge'; require __DIR__ . '/mp_card_gros.php'; ?>
            <img <?php echo upload_image_carte_attrs($produit['image_principale'] ?? ''); ?>
                alt="<?php echo htmlspecialchars($produit['nom'] ?? 'Produit'); ?>"
                loading="lazy"
                onerror="this.src='<?php echo htmlspecialchars($image_fallback, ENT_QUOTES, 'UTF-8'); ?>'">
        </div>
        <div class="mp-card-body">
            <h3 class="mp-card-title"><?php echo htmlspecialchars($produit['nom'] ?? 'Produit'); ?></h3>
            <?php if (!empty($produit['avis_count'])): ?>
                <?php
                $note = (float) ($produit['avis_moyenne'] ?? 0);
                $count = (int) ($produit['avis_count'] ?? 0);
                $size = 'sm';
                require __DIR__ . '/product_rating_stars.php';
                ?>
            <?php endif; ?>
            <div class="mp-card-price-row">
                <?php if ($has_promotion): ?>
                <span class="mp-card-price-old"><?php echo number_format((float) $produit['prix'], 0, ',', ' '); ?> FCFA</span>
                <span class="mp-card-price"><?php echo number_format((float) $card_prix_affichage, 0, ',', ' '); ?> FCFA</span>
                <?php else: ?>
                <span class="mp-card-price"><?php echo number_format((float) $card_prix_affichage, 0, ',', ' '); ?> FCFA</span>
                <?php endif; ?>
            </div>
            <?php $mp_gros_part = 'line'; require __DIR__ . '/mp_card_gros.php'; ?>
        </div>
    </a>
    <form method="POST" action="<?php echo htmlspecialchars($panier_action, ENT_QUOTES, 'UTF-8'); ?>" class="mp-card-cart">
        <?php
        if (!function_exists('site_add_to_panier_hidden_fields')) {
            require_once __DIR__ . '/../site_csrf.php';
        }
        site_add_to_panier_hidden_fields();
        ?>
        <input type="hidden" name="produit_id" value="<?php echo $pid; ?>">
        <input type="hidden" name="quantite" value="1">
        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
        <button type="submit" class="mp-card-btn">
            <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Ajouter
        </button>
    </form>
</article>
