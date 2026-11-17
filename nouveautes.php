<?php
/**
 * « Nouveautés » : produits ajoutés au cours des 30 dernières minutes.
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/includes/produit_boutique_line.php';
require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/includes/app_base.php';
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';

$fenetre_minutes = 30;
$limit = 40;
$total_produits = function_exists('count_produits_nouveautes_recentes')
    ? count_produits_nouveautes_recentes($fenetre_minutes)
    : 0;
$total_pages = $total_produits > 0 ? (int) ceil($total_produits / $limit) : 1;
$page = isset($_GET['page']) ? max(1, min($total_pages, (int) $_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$produits = get_produits_nouveautes_paginated($offset, $limit, null, null, $fenetre_minutes);
if (!empty($produits) && file_exists(__DIR__ . '/models/model_produits_avis.php')) {
    require_once __DIR__ . '/models/model_produits_avis.php';
    if (function_exists('produits_avis_enrich_products')) {
        $produits = produits_avis_enrich_products($produits);
    }
}

if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

/* Date d'ajout lisible : « Aujourd'hui », « Il y a 3 jours », « 12 sept. » */
$mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$ajout = function (array $p) use ($mois) {
    $ts = strtotime((string) ($p['date_creation'] ?? ''));
    if (!$ts) {
        return ['', false];
    }
    $minutes = (int) floor((time() - $ts) / 60);
    if ($minutes < 1) {
        return ['À l’instant', true];
    }
    if ($minutes < 60) {
        return ['Il y a ' . $minutes . ' min', true];
    }
    $jours = (int) floor((strtotime('today') - strtotime(date('Y-m-d', $ts))) / 86400);
    if ($jours <= 0) {
        return ['Aujourd’hui', true];
    }
    if ($jours === 1) {
        return ['Hier', true];
    }
    if ($jours < 7) {
        return ['Il y a ' . $jours . ' jours', true];
    }
    $label = (int) date('j', $ts) . ' ' . $mois[(int) date('n', $ts) - 1];
    if (date('Y', $ts) !== date('Y')) {
        $label .= ' ' . date('Y', $ts);
    }
    return [$label, $jours < 30];
};

$base = get_site_base_url();
$seo_title = 'Nouveautés — ' . SITE_BRAND_NAME . ' | Produits des boutiques Sénégal';
$seo_description = 'Derniers produits ajoutés sur ' . SITE_BRAND_NAME . ' : découvrez les nouveautés des vendeurs du marketplace, toutes catégories, achat en ligne.';
$seo_keywords = site_brand_seo_keywords_default() . ', nouveautés produits, nouveautés e-commerce Sénégal';
$seo_canonical = $base . '/nouveautes.php' . ($page > 1 ? '?page=' . $page : '');

$card_partial = __DIR__ . '/includes/partials/home_mp_product_card.php';
$return_url_list = app_url('/nouveautes.php' . ($page > 1 ? '?page=' . $page : ''));
$mp_ref = 'nouveautes';

$lp_active = 'nouveautes';
$lp_title = 'Nouveautés';
$lp_sub = 'Uniquement les produits ajoutés au cours des 30 dernières minutes.';
$lp_icon = 'fa-bolt';
$lp_count = $total_produits . ' produit' . ($total_produits > 1 ? 's' : '');
$page_href = function (int $n) {
    return app_url('/nouveautes.php' . ($n > 1 ? '?page=' . $n : ''));
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
                    <i class="fas fa-box-open" aria-hidden="true"></i>
                    <p>Aucun nouveau produit ces 30 dernières minutes.</p>
                    <a href="<?php echo htmlspecialchars(app_url('/index.php'), ENT_QUOTES, 'UTF-8'); ?>">Retour à l’accueil</a>
                </div>
            <?php else: ?>
                <div class="mp-grid lp-grid">
                    <?php foreach ($produits as $produit):
                        [$quand, $recent] = $ajout($produit); ?>
                        <div class="lp-item">
                            <?php if ($quand !== ''): ?>
                                <span class="lp-flag<?php echo $recent ? ' lp-flag--new' : ''; ?>"><i class="fas <?php echo $recent ? 'fa-bolt' : 'fa-calendar'; ?>" aria-hidden="true"></i> <?php echo htmlspecialchars($quand, ENT_QUOTES, 'UTF-8'); ?></span>
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
