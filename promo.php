<?php
/**
 * « Promotions » : produits en réduction — même design que Nouveautés.
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/includes/produit_boutique_line.php';
require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/includes/app_base.php';
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';

$limit = 40;
$total_produits = count_produits_en_promo();
$total_pages = $total_produits > 0 ? (int) ceil($total_produits / $limit) : 1;
$page = isset($_GET['page']) ? max(1, min($total_pages, (int) $_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$produits = get_produits_en_promo($offset, $limit);
if (!empty($produits) && file_exists(__DIR__ . '/models/model_produits_avis.php')) {
    require_once __DIR__ . '/models/model_produits_avis.php';
    if (function_exists('produits_avis_enrich_products')) {
        $produits = produits_avis_enrich_products($produits);
    }
}

if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

$promo_flag = static function (array $p): string {
    $prix = (float) ($p['prix'] ?? 0);
    $promo = (float) ($p['prix_promotion'] ?? 0);
    if ($prix <= 0 || $promo <= 0 || $promo >= $prix) {
        return '';
    }
    $pct = (int) round((($prix - $promo) / $prix) * 100);
    return $pct > 0 ? ('-' . $pct . ' %') : '';
};

$base = get_site_base_url();
$seo_title = 'Promotions & bonnes affaires — ' . SITE_BRAND_NAME;
$seo_description = 'Promotions et produits en réduction sur ' . SITE_BRAND_NAME . ', marketplace multi-boutiques au Sénégal. Économisez sur des milliers d’articles.';
$seo_keywords = site_brand_seo_keywords_default() . ', promotions Sénégal, soldes en ligne, bonnes affaires Dakar';
$seo_canonical = $base . '/promo.php' . ($page > 1 ? '?page=' . $page : '');

$card_partial = __DIR__ . '/includes/partials/home_mp_product_card.php';
$return_url_list = app_url('/promo.php' . ($page > 1 ? '?page=' . $page : ''));
$mp_ref = 'promo';

$lp_active = 'promo';
$lp_title = 'Promotions';
$lp_icon = 'fa-percent';
$lp_count = $total_produits . ' produit' . ($total_produits > 1 ? 's' : '');
$lp_show_tabs = false;
$page_href = function (int $n) {
    return app_url('/promo.php' . ($n > 1 ? '?page=' . $n : ''));
};
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
            <?php if (isset($_GET['error'])): ?>
                <div class="cat-page-alert cat-page-alert--err">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars((string) $_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($produits)): ?>
                <div class="lp-empty">
                    <i class="fas fa-tags" aria-hidden="true"></i>
                    <p>Aucun produit en promotion pour le moment.</p>
                    <a href="<?php echo htmlspecialchars(app_url('/produits.php'), ENT_QUOTES, 'UTF-8'); ?>">Voir le catalogue</a>
                </div>
            <?php else: ?>
                <div class="mp-grid lp-grid">
                    <?php foreach ($produits as $produit):
                        $flag = $promo_flag($produit); ?>
                        <div class="lp-item">
                            <?php if ($flag !== ''): ?>
                                <span class="lp-flag lp-flag--new"><i class="fas fa-percent" aria-hidden="true"></i> <?php echo htmlspecialchars($flag, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <?php $return_url = $return_url_list; require $card_partial; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="lp-pager" role="navigation" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo htmlspecialchars($page_href($page - 1), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Page précédente"><i class="fas fa-chevron-left" aria-hidden="true"></i></a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="is-current" aria-current="page"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($page_href($i), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo htmlspecialchars($page_href($page + 1), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Page suivante"><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
