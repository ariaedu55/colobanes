<?php
/**
 * « Top des ventes » : les 70 meilleurs produits de la plateforme,
 * classés par ventes réelles puis par popularité.
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/includes/produit_boutique_line.php';
require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/includes/app_base.php';
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';

$produits = get_produits_top_ventes(70);
if (!empty($produits) && file_exists(__DIR__ . '/models/model_produits_avis.php')) {
    require_once __DIR__ . '/models/model_produits_avis.php';
    if (function_exists('produits_avis_enrich_products')) {
        $produits = produits_avis_enrich_products($produits);
    }
}

$rayons = [];
foreach ($produits as $p) {
    $r = trim((string) ($p['categorie_nom'] ?? ''));
    if ($r !== '') {
        $rayons[$r] = ($rayons[$r] ?? 0) + 1;
    }
}
arsort($rayons);
$rayon_label = function (string $n) {
    return mb_strtoupper(mb_substr($n, 0, 1, 'UTF-8'), 'UTF-8') . mb_strtolower(mb_substr($n, 1, null, 'UTF-8'), 'UTF-8');
};
/* Indicateur affiché sur la carte : ventes, sinon vues */
$signal = function (array $p) {
    $q = (int) ($p['qte_vendue'] ?? 0);
    if ($q > 0) {
        return ['fa-fire', $q . ' vendu' . ($q > 1 ? 's' : ''), true];
    }
    $v = (int) ($p['nb_visites'] ?? 0);
    return $v > 0 ? ['fa-eye', $v . ' vue' . ($v > 1 ? 's' : ''), false] : ['', '', false];
};

$base = get_site_base_url();
$seo_title = 'Top des ventes — ' . SITE_BRAND_NAME . ' | Les produits les plus achetés';
$seo_description = 'Le classement des produits les plus achetés sur ' . SITE_BRAND_NAME . ', mis à jour à chaque commande.';
$seo_canonical = $base . '/top-ventes.php';

$card_partial = __DIR__ . '/includes/partials/home_mp_product_card.php';
$return_url_list = app_url('/top-ventes.php');
$mp_ref = 'top_ventes';

$lp_active = 'ventes';
$lp_title = 'Top des ventes';
$lp_sub = 'Les produits les plus achetés sur la plateforme, classés par ventes puis par popularité.';
$lp_icon = 'fa-trophy';
$lp_count = count($produits) . ' produit' . (count($produits) > 1 ? 's' : '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <?php require_once __DIR__ . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/mp-category-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/mp-list-page.css<?php echo asset_version_query(); ?>">
</head>
<body class="lp-body">
    <?php include __DIR__ . '/nav_bar.php'; ?>
    <?php require __DIR__ . '/includes/partials/mp_list_header.php'; ?>

    <main class="lp-page">
        <div class="lp-shell">
            <?php if (empty($produits)): ?>
                <div class="lp-empty">
                    <i class="fas fa-trophy" aria-hidden="true"></i>
                    <p>Aucun produit disponible dans votre région pour le moment.</p>
                    <a href="<?php echo htmlspecialchars(app_url('/produits.php'), ENT_QUOTES, 'UTF-8'); ?>">Voir le catalogue</a>
                </div>
            <?php else: ?>
                <?php if (count($rayons) > 1): ?>
                <div class="lp-chips" role="toolbar" aria-label="Filtrer par rayon">
                    <button type="button" class="lp-chip is-active" data-lp-filter="">Tous</button>
                    <?php foreach (array_slice($rayons, 0, 10, true) as $rn => $nb): ?>
                        <button type="button" class="lp-chip" data-lp-filter="<?php echo htmlspecialchars($rn, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($rayon_label($rn), ENT_QUOTES, 'UTF-8'); ?> <span><?php echo (int) $nb; ?></span></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="mp-grid lp-grid" id="lpGrid">
                    <?php foreach ($produits as $i => $produit):
                        [$sicon, $stxt, $sold] = $signal($produit); ?>
                        <div class="lp-item" data-rayon="<?php echo htmlspecialchars(trim((string) ($produit['categorie_nom'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="lp-rank<?php echo $i < 3 ? ' lp-rank--top' : ''; ?>">#<?php echo $i + 1; ?></span>
                            <?php if ($stxt !== ''): ?>
                                <span class="lp-flag<?php echo $sold ? ' lp-flag--sold' : ''; ?>"><i class="fas <?php echo $sicon; ?>" aria-hidden="true"></i> <?php echo htmlspecialchars($stxt, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <?php $return_url = $return_url_list; require $card_partial; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="lp-noresult" id="lpNoResult" hidden>Aucun produit dans ce rayon.</p>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>

    <script>
    (function () {
        var chips = document.querySelectorAll('[data-lp-filter]');
        var items = document.querySelectorAll('#lpGrid .lp-item');
        var empty = document.getElementById('lpNoResult');
        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                var f = chip.getAttribute('data-lp-filter');
                var shown = 0;
                chips.forEach(function (c) { c.classList.toggle('is-active', c === chip); });
                items.forEach(function (it) {
                    var ok = f === '' || it.getAttribute('data-rayon') === f;
                    it.hidden = !ok;
                    if (ok) { shown++; }
                });
                if (empty) { empty.hidden = shown > 0; }
            });
        });
    })();
    </script>
</body>
</html>
