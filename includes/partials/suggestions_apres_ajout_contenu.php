<?php
/**
 * Panneau « Ajouté au panier » + produits complémentaires choisis par l'IA (js/panier-added-snackbar.js).
 * Variables : $panier_accessoires (panier_accessoires_resolve_for_cart), $produit_ajoute
 */
if (empty($panier_accessoires) || !is_array($panier_accessoires)) {
    return;
}
$produits_acc = array_slice($panier_accessoires['produits'] ?? [], 0, 8);
if ($produits_acc === []) {
    return;
}
$card_partial = __DIR__ . '/panier_accessoire_card.php';
if (!is_readable($card_partial)) {
    return;
}
if (!function_exists('assistant_config')) {
    require_once __DIR__ . '/../assistant/assistant_engine.php';
}
$bot_nom = (string) (assistant_config()['bot_name'] ?? 'Kennassy');
$nom_ajoute = trim((string) ($produit_ajoute['nom'] ?? ''));
if (!function_exists('app_url')) {
    require_once __DIR__ . '/../app_base.php';
}
/* Ajout depuis le panneau : on passe par le panier (le script vérifie que l'article y est) */
$return_url = app_url('/panier.php');
?>
<div class="sap" role="dialog" aria-labelledby="sap-titre">
    <div class="sap__head">
        <span class="sap__ok" aria-hidden="true"><i class="fas fa-check"></i></span>
        <div class="sap__head-txt">
            <p id="sap-titre" class="sap__titre">Ajouté au panier</p>
            <?php if ($nom_ajoute !== ''): ?>
                <p class="sap__nom"><?php echo htmlspecialchars($nom_ajoute, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>
        <button type="button" class="sap__close" data-sap-close aria-label="Fermer"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    </div>
    <p class="sap__intro">
        <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i>
        <?php echo !empty($panier_accessoires['ia'])
            ? htmlspecialchars($bot_nom, ENT_QUOTES, 'UTF-8') . ' vous suggère pour aller avec :'
            : 'Souvent achetés avec :'; ?>
    </p>
    <section class="pacc pacc--sheet" id="panierAccessoires" aria-label="Produits complémentaires">
        <div class="pacc__track" role="list">
            <?php foreach ($produits_acc as $produit):
                if (!is_array($produit)) {
                    continue;
                }
                require $card_partial;
            endforeach; ?>
        </div>
    </section>
    <div class="sap__actions">
        <a href="<?php echo htmlspecialchars(app_url('/panier.php'), ENT_QUOTES, 'UTF-8'); ?>" class="sap__btn sap__btn--primary"><i class="fas fa-cart-shopping" aria-hidden="true"></i> Voir mon panier</a>
        <button type="button" class="sap__btn" data-sap-close>Continuer mes achats</button>
    </div>
</div>
