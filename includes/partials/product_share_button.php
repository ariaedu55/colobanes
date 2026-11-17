<?php
/**
 * Bouton partage produit — ouvre la modal unifiée (platformShareModal).
 * Variable : $produit (tableau avec id, nom, prix, prix_promotion optionnels).
 */
if (empty($produit) || !is_array($produit)) {
    return;
}
$pid = (int) ($produit['id'] ?? 0);
if ($pid <= 0) {
    return;
}
if (!function_exists('product_share_abs_url')) {
    require_once __DIR__ . '/../product_share.php';
}
$share_url = product_share_abs_url($pid);
$share_msg = product_share_text_short($produit);
$share_nom = htmlspecialchars((string) ($produit['nom'] ?? 'Produit'), ENT_QUOTES, 'UTF-8');
/* Aperçu dans la fenêtre de partage : petite photo + prix affiché */
$share_img = '';
if (!empty($produit['image_principale']) && function_exists('upload_image_url')) {
    $share_img = (string) upload_image_url((string) $produit['image_principale'], 'sm');
}
$share_prix_n = (float) ($produit['prix'] ?? 0);
$share_promo_n = (float) ($produit['prix_promotion'] ?? 0);
if ($share_promo_n > 0 && $share_promo_n < $share_prix_n) {
    $share_prix_n = $share_promo_n;
}
$share_prix = $share_prix_n > 0 ? number_format($share_prix_n, 0, ',', ' ') . ' FCFA' : '';
?>
<div class="pshare" data-pshare>
    <button type="button"
        class="pshare__toggle"
        aria-label="Partager <?php echo $share_nom; ?>"
        data-share-url="<?php echo htmlspecialchars($share_url, ENT_QUOTES, 'UTF-8'); ?>"
        data-share-text="<?php echo htmlspecialchars($share_msg, ENT_QUOTES, 'UTF-8'); ?>"
        data-share-title="<?php echo $share_nom; ?>"
        <?php if ($share_img !== ''): ?>data-share-image="<?php echo htmlspecialchars($share_img, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
        <?php if ($share_prix !== ''): ?>data-share-price="<?php echo htmlspecialchars($share_prix, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
        <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
    </button>
</div>
