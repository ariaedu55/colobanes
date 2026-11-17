<?php
/**
 * Bloc « Complétez votre achat » — accessoires adaptés au panier (téléphone → coque, chargeur…).
 * Téléphone : carrousel horizontal ; ordinateur / tablette : grille. Ajout sans quitter la page (js/panier-accessoires.js).
 * Variables : $panier_accessoires
 */
if (empty($panier_accessoires) || !is_array($panier_accessoires)) {
    return;
}
$produits_acc = array_slice($panier_accessoires['produits'] ?? [], 0, 8);
if ($produits_acc === []) {
    return;
}
$section_title = trim((string) ($panier_accessoires['title'] ?? ''));
if ($section_title === '') {
    $section_title = 'Complétez votre achat';
}
$return_url = '/panier.php';
$card_partial = __DIR__ . '/panier_accessoire_card.php';
if (!is_readable($card_partial)) {
    return;
}
?>
<section class="pacc" id="panierAccessoires" aria-labelledby="pacc-title">
    <header class="pacc__head">
        <h2 id="pacc-title" class="pacc__title">
            <i class="fas fa-plus-circle" aria-hidden="true"></i>
            <?php echo htmlspecialchars($section_title, ENT_QUOTES, 'UTF-8'); ?>
        </h2>
        <div class="pacc__nav" aria-hidden="true">
            <button type="button" class="pacc__arrow" data-pacc-scroll="-1" tabindex="-1"><i class="fas fa-chevron-left"></i></button>
            <button type="button" class="pacc__arrow" data-pacc-scroll="1" tabindex="-1"><i class="fas fa-chevron-right"></i></button>
        </div>
    </header>
    <div class="pacc__track" role="list">
        <?php foreach ($produits_acc as $produit):
            if (!is_array($produit)) {
                continue;
            }
            require $card_partial;
        endforeach; ?>
    </div>
</section>
