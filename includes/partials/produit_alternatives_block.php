<?php
/**
 * Alternatives disponibles (produit indisponible).
 * Variables : $produits_alternatives (list), $return_url (string)
 */
if (empty($produits_alternatives) || !is_array($produits_alternatives)) {
    return;
}
$card_partial = __DIR__ . '/home_mp_product_card.php';
if (!is_readable($card_partial)) {
    return;
}
$return_url = isset($return_url) ? (string) $return_url : '/index.php';
?>
<section class="produit-alternatives" id="alternatives-disponibles" aria-labelledby="alternatives-title">
    <header class="mp-block-head">
        <div>
            <h2 id="alternatives-title">Alternatives encore disponibles</h2>
            <p class="produit-alternatives-lead">Les articles les plus proches, en stock sur la marketplace.</p>
        </div>
    </header>
    <div class="mp-grid produit-alternatives-grid">
        <?php
        $return_url_cards = $return_url;
        foreach ($produits_alternatives as $produit):
            if (!is_array($produit)) {
                continue;
            }
            $return_url = $return_url_cards;
            require $card_partial;
        endforeach; ?>
    </div>
</section>
