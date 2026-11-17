<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/produit_boutique_line.php';

// Inclusion du fichier de connexion à la BDD

// Récupérez l'ID du commerçant à partir de la session
// Récupérez l'ID de l'utilisateur depuis la variable de session
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';
$base = get_site_base_url();
$seo_title = site_brand_seo_title_default();
$seo_description = site_brand_seo_description_default();
$seo_keywords = site_brand_seo_keywords_default();
$seo_canonical = $base . '/';
require_once __DIR__ . '/includes/seo_structured_data.php';
$seo_json_ld_blocks = [
    seo_structured_data_homepage_graph(),
];
?>




<!DOCTYPE html>
<html lang="fr" class="aos-not-ready">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    require_once __DIR__ . '/includes/asset_version.php';
    require_once __DIR__ . '/includes/app_base.php';
    require_once __DIR__ . '/includes/site_perf_head.php';
    ?>
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <?php site_perf_emit_resource_hints(); ?>
    <?php
    /* Bannières de l'accueil lues dès le <head> : la 1re est annoncée au navigateur tout de suite (image LCP) */
    require_once __DIR__ . '/includes/image_optimizer.php';
    $hero_affiches = [];
    if (file_exists(__DIR__ . '/models/model_marketplace_hero.php')) {
        require_once __DIR__ . '/models/model_marketplace_hero.php';
        $hero_affiches = marketplace_hero_list_actifs();
    }
    if (!is_array($hero_affiches)) {
        $hero_affiches = [];
    }
    $hero_affiches = array_values(array_filter($hero_affiches, static function ($ha) {
        $img = trim(str_replace('\\', '/', (string) ($ha['image'] ?? '')));
        if ($img === '') {
            return false;
        }
        $rel = str_contains($img, '/') ? $img : 'marketplace_hero/' . $img;

        return function_exists('upload_image_local_exists') && upload_image_local_exists($rel);
    }));
    $hero_affiches_prets = true;
    if (!empty($hero_affiches)) {
        $hero_pre_img = trim(str_replace('\\', '/', (string) ($hero_affiches[0]['image'] ?? '')));
        $hero_pre_rel = str_contains($hero_pre_img, '/') ? $hero_pre_img : 'marketplace_hero/' . $hero_pre_img;
        $hero_pre_md = upload_image_url($hero_pre_rel, 'md');
        $hero_pre_full = upload_image_url($hero_pre_rel, 'original');
        $hero_pre_attr = 'href="' . htmlspecialchars($hero_pre_md, ENT_QUOTES, 'UTF-8') . '"';
        if ($hero_pre_full !== $hero_pre_md) {
            $hero_pre_attr .= ' imagesrcset="' . htmlspecialchars($hero_pre_md . ' 800w, ' . $hero_pre_full . ' 1920w', ENT_QUOTES, 'UTF-8')
                . '" imagesizes="(max-width: 1360px) 100vw, 1320px"';
        }
        echo '    <link rel="preload" as="image" ' . $hero_pre_attr . ' fetchpriority="high">' . "\n";
    }
    ?>
    <?php
    site_perf_preload_stylesheet(app_url('/css/variables.css' . asset_version_query()));
    site_perf_preload_stylesheet(app_url('/css/style.css' . asset_version_query()));
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/variables.css' . asset_version_query()), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/style.css' . asset_version_query()), ENT_QUOTES, 'UTF-8'); ?>">
    <?php site_perf_stylesheet_deferred('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'); ?>
    <?php site_perf_stylesheet_deferred('https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css'); ?>
    <style>
        /* AOS masque [data-aos] dans sa feuille CSS avant que le JS n’ajoute .aos-animate — affichage immédiat jusqu’à init */
        html.aos-not-ready [data-aos] {
            opacity: 1 !important;
            transform: none !important;
            filter: none !important;
            pointer-events: auto !important;
        }
    </style>
    <?php
    site_perf_stylesheet_deferred(app_url('/css/owl.carousel.min.css' . asset_version_query()));
    site_perf_stylesheet_deferred(app_url('/css/animate.min.css' . asset_version_query()));
    ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/a_style.css' . asset_version_query()), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/product-cards.css' . asset_version_query()), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/boutiques-marketplace.css' . asset_version_query()), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/mp-shops.css' . asset_version_query()), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/mp-hero-slider.css' . asset_version_query()), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/accueil.css' . asset_version_query()), ENT_QUOTES, 'UTF-8'); ?>">

</head>


<body>

    <?php
    $section1_mp_page_title = SITE_BRAND_NAME . ' — Marketplace Sénégal';
    $nav_head_styles_loaded = true;
    include 'nav_bar.php';
    unset($section1_mp_page_title, $nav_head_styles_loaded);
    ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="commande-perso-success"
            style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: var(--error-bg); border-left: 4px solid var(--error-border); border-radius: 8px; color: var(--titres);">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['commande_perso_success'])): ?>
        <div class="commande-perso-success"
            style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: var(--success-bg); border-left: 4px solid var(--bleu); border-radius: 8px; color: var(--titres);">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_SESSION['commande_perso_success']);
            unset($_SESSION['commande_perso_success']); ?>
        </div>
    <?php endif; ?>

    <?php
    require_once __DIR__ . '/includes/image_optimizer.php';

    $return_url_mp = (string) ($_SERVER['REQUEST_URI'] ?? '/index.php');

    $slides = [];
    if (file_exists(__DIR__ . '/models/model_slider.php')) {
        require_once __DIR__ . '/models/model_slider.php';
        $slides_result = get_all_slides('actif');
        $slides = is_array($slides_result) ? $slides_result : [];
    }

    $mp_categories = [];
    if (file_exists(__DIR__ . '/models/model_categories.php')) {
        require_once __DIR__ . '/models/model_categories.php';
        $mp_categories = get_all_categories();
        if (is_array($mp_categories) && count($mp_categories) > 24) {
            $mp_categories = array_slice($mp_categories, 0, 24);
        }
    }
    if (!is_array($mp_categories)) {
        $mp_categories = [];
    }

    $logos_carousel = [];
    if (file_exists(__DIR__ . '/models/model_logos.php')) {
        require_once __DIR__ . '/models/model_logos.php';
        $logos_carousel = get_all_logos('actif', null);
    }
    if (!is_array($logos_carousel)) {
        $logos_carousel = [];
    }

    $produits_nouveaux = [];
    $produits_populaires = [];
    $produits_tous = [];
    $total_produits = 0;
    $mp_home_catalog_grid_limit = 24;
    if (file_exists(__DIR__ . '/models/model_produits.php')) {
        require_once __DIR__ . '/models/model_produits.php';
        require_once __DIR__ . '/includes/catalogue_shuffle.php';
        $seed_tous = catalogue_nouveau_seed('index_tous');
        $produits_tous = get_all_produits_paginated(0, $mp_home_catalog_grid_limit, null, $seed_tous);
        $seed_nouveaux = catalogue_nouveau_seed('index_nouveaux');
        $produits_nouveaux = catalogue_melanger_produits($produits_tous, $seed_nouveaux);
        $total_produits = count_all_produits_actifs();
    }
    if (file_exists(__DIR__ . '/models/model_visites.php')) {
        require_once __DIR__ . '/models/model_visites.php';
        $produits_populaires = get_produits_plus_visites(12);
    }
    if (!is_array($produits_nouveaux)) {
        $produits_nouveaux = [];
    }
    if (!is_array($produits_populaires)) {
        $produits_populaires = [];
    }
    if (!is_array($produits_tous)) {
        $produits_tous = [];
    }
    if (!empty($produits_populaires) && file_exists(__DIR__ . '/includes/catalogue_shuffle.php')) {
        require_once __DIR__ . '/includes/catalogue_shuffle.php';
        $produits_populaires = catalogue_melanger_produits($produits_populaires);
    }

    $card_partial = __DIR__ . '/includes/partials/home_mp_product_card.php';
    $partial_top = __DIR__ . '/includes/partials/home_mp_top_product.php';
    $partial_newc = __DIR__ . '/includes/partials/home_mp_new_compact.php';

    $mp_nav_cats = [];
    $mp_nav_is_generale = false;
    if (function_exists('categories_generales_table_exists') && categories_generales_table_exists()) {
        $mp_nav_generales = get_general_categories_ordered();
        if (is_array($mp_nav_generales) && !empty($mp_nav_generales)) {
            $mp_nav_cats = array_slice($mp_nav_generales, 0, 14);
            $mp_nav_is_generale = true;
        }
    }
    if (empty($mp_nav_cats) && !empty($mp_categories)) {
        $mp_nav_cats = array_slice($mp_categories, 0, 14);
        $mp_nav_is_generale = false;
    }
    require_once __DIR__ . '/includes/marketplace_personalization.php';
    $mp_nav_cats = marketplace_personalization_order_categories($mp_nav_cats);
    $mp_personalization = marketplace_personalization_home_context();
    $mp_home_personalized = !empty($mp_personalization['warm']);
    $mp_popular_cats = marketplace_personalization_popular_categories($mp_nav_cats, 8);

    require_once __DIR__ . '/includes/marketplace_home_helpers.php';
    if (file_exists(__DIR__ . '/models/model_recherches_catalogue.php')) {
        require_once __DIR__ . '/models/model_recherches_catalogue.php';
    }
    if (file_exists(__DIR__ . '/models/model_produits_avis.php')) {
        require_once __DIR__ . '/models/model_produits_avis.php';
    }

    /* Recherches fréquentes : produits liés aux termes les plus recherchés (ordre conservé) */
    $recherche_candidats = function_exists('get_produits_lies_aux_recherches_frequentes')
        ? get_produits_lies_aux_recherches_frequentes(40)
        : [];
    if ($mp_home_personalized && !empty($mp_personalization['produits_recommandes'])) {
        $produits_tendance = array_slice($mp_personalization['produits_recommandes'], 0, 20);
    } else {
        $produits_tendance = marketplace_produits_ordre_ou_aleatoire($recherche_candidats, 20, 'marketplace_recherches');
    }

    /* 4 articles / panneau : grille 2×2 sur mobile, 4 en ligne sur grand écran */
    $produits_top_panneau = !empty($produits_populaires)
        ? array_slice($produits_populaires, 0, 4)
        : array_slice($produits_nouveaux, 0, 4);
    $produits_new_panneau = function_exists('get_produits_nouveautes_aleatoires_panneau')
        ? get_produits_nouveautes_aleatoires_panneau(4, 8)
        : array_slice($produits_nouveaux, 0, 4);

    /* Bandeau B2B : best-sellers (quantités commandées), mélangés */
    $vendus_candidats = function_exists('get_produits_plus_vendus_marketplace')
        ? get_produits_plus_vendus_marketplace(60)
        : [];
    /* « Top des ventes » : le vrai classement (70 meilleurs produits), dans l'ordre */
    $produits_strip = function_exists('get_produits_top_ventes')
        ? get_produits_top_ventes(70)
        : marketplace_produits_aleatoires_avec_seuil($vendus_candidats, 8, 5);

    if ($mp_home_personalized && !empty($mp_personalization['produits_recommandes'])) {
        $produits_tous = marketplace_personalization_blend_catalogue(
            $produits_tous,
            $mp_personalization['produits_recommandes'],
            $mp_home_catalog_grid_limit
        );
    }
    $produits_tous = array_slice($produits_tous, 0, $mp_home_catalog_grid_limit);
    /* Bannières « Offres du moment » / « Nouveautés » sous la première ligne de « Nos produits » */
    $mp_duo = ['promo_count' => 0, 'promo_max' => 0, 'promo_img' => '', 'new_img' => '', 'new_nom' => ''];
    try {
        global $db;
        $r = $db->query("SELECT COUNT(*) AS n, MAX(ROUND((prix - prix_promotion) / prix * 100)) AS mx FROM produits
            WHERE statut = 'actif' AND prix_promotion > 0 AND prix_promotion < prix")->fetch(PDO::FETCH_ASSOC) ?: [];
        $mp_duo['promo_count'] = (int) ($r['n'] ?? 0);
        $mp_duo['promo_max'] = (int) ($r['mx'] ?? 0);
        $mp_duo['promo_img'] = (string) ($db->query("SELECT image_principale FROM produits
            WHERE statut = 'actif' AND prix_promotion > 0 AND prix_promotion < prix AND image_principale <> ''
            ORDER BY (prix - prix_promotion) / prix DESC LIMIT 1")->fetchColumn() ?: '');
        $r = $db->query("SELECT nom, image_principale FROM produits WHERE statut = 'actif' AND image_principale <> ''
            ORDER BY date_creation DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
        $mp_duo['new_img'] = (string) ($r['image_principale'] ?? '');
        $mp_duo['new_nom'] = (string) ($r['nom'] ?? '');
    } catch (Throwable $e) {
        error_log('[index mp_duo] ' . $e->getMessage());
    }

    /* Rangée de 4 tuiles sous la 8e ligne de « Nos produits » : 3 rayons tirés au hasard à chaque visite
       (parmi ceux qui ont des produits en ligne et une image) + « Vendez sur COLObanes » */
    $mp_quad_rayons = [];
    try {
        global $db;
        $mp_quad_rayons = $db->query("SELECT g.id, g.nom, g.image, COUNT(p.id) AS nb
            FROM categories_generales g
            JOIN categories c ON c.categorie_generale_id = g.id
            JOIN produits p ON p.categorie_id = c.id AND p.statut = 'actif'
            WHERE g.image IS NOT NULL AND g.image <> ''
            GROUP BY g.id, g.nom, g.image
            ORDER BY RAND() LIMIT 3")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('[index mp_quad] ' . $e->getMessage());
    }

    /* Défilement infini : produits déjà affichés, pour que la suite ne les répète pas */
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['home_infinite'] = [
            'seed' => (int) ($seed_tous ?? 1),
            'vus' => array_values(array_map(static function ($p) {
                return (int) ($p['id'] ?? 0);
            }, $produits_tous)),
        ];
    }

    /* Boutiques partenaires — aperçu accueil */
    $boutiques_accueil = [];
    if (file_exists(__DIR__ . '/models/model_boutiques_marketplace.php')) {
        require_once __DIR__ . '/models/model_boutiques_marketplace.php';
        require_once __DIR__ . '/includes/marketplace_boutique_card_helpers.php';
        require_once __DIR__ . '/includes/marketplace_country_filter.php';
        $mp_country_boutiques = marketplace_country_filter_code();
        $boutiques_accueil = marketplace_boutiques_top_ventes(4, $mp_country_boutiques);
    }

    if (empty($hero_affiches_prets)) {
    $hero_affiches = [];
    if (file_exists(__DIR__ . '/models/model_marketplace_hero.php')) {
        require_once __DIR__ . '/models/model_marketplace_hero.php';
        $hero_affiches = marketplace_hero_list_actifs();
    }
    if (!is_array($hero_affiches)) {
        $hero_affiches = [];
    }
    $hero_affiches = array_values(array_filter($hero_affiches, static function ($ha) {
        $img = trim(str_replace('\\', '/', (string) ($ha['image'] ?? '')));
        if ($img === '') {
            return false;
        }
        $rel = str_contains($img, '/') ? $img : 'marketplace_hero/' . $img;

        return function_exists('upload_image_local_exists') && upload_image_local_exists($rel);
    }));
    }

    $showcase_site_pool = marketplace_build_showcase_pool(
        10,
        $produits_tendance,
        $mp_home_personalized ? ($mp_personalization['produits_recommandes'] ?? []) : [],
        $produits_populaires,
        $produits_nouveaux,
        $produits_tous,
        $recherche_candidats
    );
    $mp_showcase_pick = marketplace_home_showcase_produits($mp_home_personalized, $mp_personalization, $showcase_site_pool, 10);
    $produits_showcase_center = $mp_showcase_pick['produits'];
    $mp_showcase_featured_id = (int) ($mp_showcase_pick['featured_produit_id'] ?? 0);

    if (function_exists('produits_avis_enrich_products')) {
        $avis_stats = marketplace_avis_stats_for_product_lists([
            $produits_tendance,
            $produits_showcase_center,
            $produits_strip,
            $produits_top_panneau,
            $produits_new_panneau,
            $produits_tous,
        ]);
        $produits_tendance = marketplace_apply_avis_stats($produits_tendance, $avis_stats);
        $produits_showcase_center = marketplace_apply_avis_stats($produits_showcase_center, $avis_stats);
        $produits_strip = marketplace_apply_avis_stats($produits_strip, $avis_stats);
        $produits_top_panneau = marketplace_apply_avis_stats($produits_top_panneau, $avis_stats);
        $produits_new_panneau = marketplace_apply_avis_stats($produits_new_panneau, $avis_stats);
        $produits_tous = marketplace_apply_avis_stats($produits_tous, $avis_stats);
    }
    ?>

    <main class="mp-main">

        <section class="mp-hero" aria-label="Affichage marketplace">
            <?php if (!empty($hero_affiches)): ?>
                <div class="mp-slider-wrap mp-hero-slider-wrap">
                    <div class="slider-area owl-carousel">
                        <?php foreach ($hero_affiches as $hero_index => $ha): ?>
                            <?php
                            $hero_img = trim(str_replace('\\', '/', (string) ($ha['image'] ?? '')));
                            $hero_rel = str_contains($hero_img, '/') ? $hero_img : 'marketplace_hero/' . $hero_img;
                            $hero_alt = htmlspecialchars((string) ($ha['alt_text'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $hero_md = upload_image_url($hero_rel, 'md');
                            $hero_full = upload_image_url($hero_rel, 'original');
                            $hero_src = htmlspecialchars($hero_md, ENT_QUOTES, 'UTF-8');
                            // Variante « md » sur mobile, original sur grand écran / Retina — largeurs réelles des fichiers
                            $hero_w = static function (string $url): int {
                                $path = parse_url($url, PHP_URL_PATH) ?: '';
                                $pos = strpos($path, '/upload/');
                                $file = $pos === false ? '' : __DIR__ . substr($path, $pos);
                                $size = $file !== '' && is_file($file) ? @getimagesize($file) : false;
                                return $size ? (int) $size[0] : 0;
                            };
                            $hero_md_w = $hero_w($hero_md);
                            // Dimensions réelles : sur téléphone le cadre prend la proportion de l'affiche
                            $hero_md_file = (static function (string $url): string {
                                $path = parse_url($url, PHP_URL_PATH) ?: '';
                                $pos = strpos($path, '/upload/');
                                return $pos === false ? '' : __DIR__ . substr($path, $pos);
                            })($hero_md);
                            $hero_md_size = $hero_md_file !== '' && is_file($hero_md_file) ? @getimagesize($hero_md_file) : false;
                            $hero_full_w = $hero_w($hero_full);
                            $hero_srcset = ($hero_full !== $hero_md && $hero_md_w > 0 && $hero_full_w > $hero_md_w)
                                ? htmlspecialchars($hero_md . ' ' . $hero_md_w . 'w, ' . $hero_full . ' ' . $hero_full_w . 'w', ENT_QUOTES, 'UTF-8')
                                : '';
                            ?>
                            <div class="slider-item"<?php echo $hero_index === 0 ? ' style="--hero-bg: url(\'' . $hero_src . '\');"' : ' data-hero-bg="' . $hero_src . '"'; ?>>
                                <?php /* 1re affiche : chargée tout de suite ; les suivantes après l'affichage de la page (ne freinent pas la 1re) */
                                $hero_pfx = $hero_index === 0 ? '' : 'data-'; ?>
                                <img <?php echo $hero_pfx; ?>src="<?php echo $hero_src; ?>" alt="<?php echo $hero_alt; ?>"
                                    <?php if ($hero_md_size): ?>width="<?php echo (int) $hero_md_size[0]; ?>" height="<?php echo (int) $hero_md_size[1]; ?>"<?php endif; ?>
                                    <?php if ($hero_srcset !== ''): ?><?php echo $hero_pfx; ?>srcset="<?php echo $hero_srcset; ?>" sizes="(max-width: 1360px) 100vw, 1320px"<?php endif; ?>
                                    decoding="async"<?php echo $hero_index === 0 ? ' fetchpriority="high"' : ' fetchpriority="low"'; ?>>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <script>
                /* Affiches 2, 3… : chargées une fois la page affichée (y compris les copies créées par le carrousel) */
                (function () {
                    function charger() {
                        document.querySelectorAll('.mp-hero img[data-src]').forEach(function (img) {
                            if (img.dataset.srcset) { img.srcset = img.dataset.srcset; img.removeAttribute('data-srcset'); }
                            img.src = img.dataset.src; img.removeAttribute('data-src');
                        });
                        document.querySelectorAll('.mp-hero [data-hero-bg]').forEach(function (el) {
                            el.style.setProperty('--hero-bg', "url('" + el.getAttribute('data-hero-bg') + "')");
                            el.removeAttribute('data-hero-bg');
                        });
                    }
                    function plusTard() { setTimeout(charger, 200); setTimeout(charger, 2000); }
                    if (document.readyState === 'complete') { plusTard(); } else { window.addEventListener('load', plusTard); }
                })();
                </script>
            <?php else: ?>
                <div class="mp-hero-placeholder" role="img" aria-label="Aucune bannière"></div>
            <?php endif; ?>
        </section>

        <?php if (!empty($mp_popular_cats)): ?>
            <section class="mp-popular-categories" aria-label="Catégories populaires">
                <div class="mp-popular-categories-inner">
                    <div class="mp-popular-categories-track" role="list">
                        <?php foreach ($mp_popular_cats as $prow): ?>
                            <?php
                            $plabel = trim((string) ($prow['nom'] ?? ''));
                            if ($plabel === '') {
                                continue;
                            }
                            $pic_class = function_exists('categorie_fa_icon_class')
                                ? categorie_fa_icon_class($prow)
                                : 'fa-solid fa-layer-group';
                            $pop_img = function_exists('categorie_image_public_path')
                                ? categorie_image_public_path($prow)
                                : null;
                            $has_pop_img = is_string($pop_img) && $pop_img !== '';
                            $pid = (int) ($prow['id'] ?? 0);
                            if ($mp_nav_is_generale) {
                                $phref = $pid > 0 && function_exists('nav_categorie_generale_href')
                                    ? nav_categorie_generale_href($pid)
                                    : ('produits.php?recherche=' . rawurlencode($plabel));
                            } else {
                                $phref = $pid > 0 && function_exists('nav_categorie_href')
                                    ? nav_categorie_href($pid)
                                    : 'produits.php';
                            }
                            ?>
                            <a class="mp-pop-cat-item" role="listitem"
                                href="<?php echo htmlspecialchars($phref, ENT_QUOTES, 'UTF-8'); ?>"
                                title="<?php echo htmlspecialchars($plabel, ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="mp-pop-cat-icon<?php echo $has_pop_img ? ' mp-pop-cat-icon--photo' : ''; ?>"
                                    aria-hidden="true">
                                    <?php if ($has_pop_img): ?>
                                        <img src="<?php echo htmlspecialchars(upload_image_url_from_src($pop_img, 'sm'), ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <i class="<?php echo htmlspecialchars($pic_class, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                    <?php endif; ?>
                                </span>
                                <?php
                                // Libellés saisis tout en majuscules : affichage en casse phrase, homogène avec les autres tuiles
                                $plabel_display = ($plabel === mb_strtoupper($plabel, 'UTF-8'))
                                    ? mb_strtoupper(mb_substr($plabel, 0, 1, 'UTF-8'), 'UTF-8') . mb_strtolower(mb_substr($plabel, 1, null, 'UTF-8'), 'UTF-8')
                                    : $plabel;
                                // 18 lettres maximum (le CSS coupe encore plus court sur mobile ; nom complet dans l'infobulle)
                                if (mb_strlen($plabel_display, 'UTF-8') > 18) {
                                    $plabel_display = rtrim(mb_substr($plabel_display, 0, 18, 'UTF-8'), " \t&,-") . '…';
                                }
                                ?>
                                <span class="mp-pop-cat-label"><?php echo htmlspecialchars($plabel_display); ?></span>
                            </a>
                        <?php endforeach; ?>
                        <button type="button" class="mp-pop-cat-item mp-pop-cat-item--all" role="listitem"
                            title="Voir toutes les catégories"
                            onclick="if(typeof window.openBoutiqueNavSidebar==='function'){window.openBoutiqueNavSidebar();}">
                            <span class="mp-pop-cat-icon" aria-hidden="true">
                                <span class="mp-pop-grid-dots">
                                    <span></span><span></span><span></span><span></span>
                                </span>
                            </span>
                            <span class="mp-pop-cat-label">Voir tout</span>
                        </button>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <div class="mp-shell">
            <section class="mp-showcase" aria-label="Tendances et sélections">
                <div class="mp-showcase-inner mp-showcase-inner--no-nav">
                    <div class="mp-showcase-center">
                        <div class="mp-showcase-center-top">
                            <div>
                                <h2><?php echo $mp_home_personalized ? 'Sélection pour vous' : 'Recherches fréquentes'; ?></h2>
                            </div>
                            <div class="mp-trend-nav">
                                <button type="button" class="mp-trend-prev" aria-label="Faire défiler vers la gauche">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button type="button" class="mp-trend-next" aria-label="Faire défiler vers la droite">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mp-trend-scroll" id="mpTrendScroll">
                            <?php if (empty($produits_showcase_center)): ?>
                                <p class="mp-empty" style="min-width:100%;margin:0;">Aucun produit à afficher.</p>
                            <?php else: ?>
                                <?php foreach ($produits_showcase_center as $produit): ?>
                                    <?php
                                    $tid = (int) ($produit['id'] ?? 0);
                                    if ($tid <= 0) {
                                        continue;
                                    }
                                    $tnom = trim((string) ($produit['nom'] ?? ''));
                                    if ($tnom === '') {
                                        $tnom = 'Produit';
                                    }
                                    $showcase_is_featured = $mp_home_personalized && $mp_showcase_featured_id > 0 && $tid === $mp_showcase_featured_id;
                                    if ($showcase_is_featured) {
                                        $showcase_label = 'Pour vous';
                                    } elseif ($mp_home_personalized) {
                                        $showcase_label = 'Suggestion';
                                    } else {
                                        $showcase_label = 'Recherché';
                                    }
                                    ?>
                                    <div class="mp-trend-card<?php echo $showcase_is_featured ? ' mp-trend-card--featured' : ''; ?>">
                                        <?php /* Étiquette seulement si elle apporte une info (personnalisation) : « Recherché » répétait le titre */ ?>
                                        <?php if ($showcase_label !== 'Recherché'): ?>
                                        <span class="mp-trend-label"><?php echo htmlspecialchars($showcase_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                        <a class="mp-trend-img-link" href="produit.php?id=<?php echo $tid; ?>&amp;mp_ref=home_trend">
                                            <img src="<?php echo htmlspecialchars(upload_image_url($produit['image_principale'] ?? '', 'sm')); ?>"
                                                alt="<?php echo htmlspecialchars($tnom); ?>" loading="lazy"
                                                onerror="this.src='/image/produit1.jpg'">
                                        </a>
                                        <span class="mp-trend-sub"><?php echo htmlspecialchars($tnom); ?></span>
                                        <?php if (!empty($produit['avis_count'])): ?>
                                            <?php
                                            $note = (float) ($produit['avis_moyenne'] ?? 0);
                                            $count = (int) ($produit['avis_count'] ?? 0);
                                            $size = 'sm';
                                            require __DIR__ . '/includes/partials/product_rating_stars.php';
                                            ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <aside class="mp-showcase-boutiques mp-shops" aria-labelledby="mp-bt-title">
                        <header class="mp-shops__head">
                            <h2 class="mp-shops__title" id="mp-bt-title">Boutiques partenaires</h2>
                            <a class="mp-shops__more" href="/boutiques.php">
                                Voir plus <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </a>
                        </header>
                        <?php if (empty($boutiques_accueil)): ?>
                            <div class="mp-shops__empty">
                                <i class="fas fa-store" aria-hidden="true"></i>
                                <span>Les boutiques vendeurs apparaîtront ici.</span>
                            </div>
                        <?php else: ?>
                            <?php
                            if (!function_exists('boutique_vitrine_entry_href')) {
                                require_once __DIR__ . '/includes/marketplace_helpers.php';
                            }
                            ?>
                            <ul class="mp-shops__list">
                                <?php
                                /* 3 boutiques, la 4e place invite à ouvrir sa boutique */
                                $shops_affichees = 0;
                                foreach ($boutiques_accueil as $shop):
                                    $shop_slug = trim((string) ($shop['boutique_slug'] ?? ''));
                                    if ($shop_slug === '' || $shops_affichees >= 3) {
                                        continue;
                                    }
                                    $shops_affichees++;
                                    $shop_nom = trim((string) ($shop['boutique_nom'] ?? '')) ?: 'Boutique';
                                    $shop_logo = trim(str_replace('\\', '/', (string) ($shop['boutique_logo'] ?? '')));
                                    $shop_certif = !empty($shop['certification_niveau']);
                                    $shop_theme = function_exists('marketplace_boutique_card_theme')
                                        ? marketplace_boutique_card_theme($shop) : ['main' => '#3564a6'];
                                    $shop_initiale = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $shop_nom) ?: 'B', 0, 1));
                                    ?>
                                    <li>
                                        <a class="mp-shop" href="<?php echo htmlspecialchars(boutique_vitrine_entry_href($shop_slug), ENT_QUOTES, 'UTF-8'); ?>"
                                            style="--shop-color: <?php echo htmlspecialchars((string) $shop_theme['main'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="mp-shop__cover" aria-hidden="true"></span>
                                            <span class="mp-shop__logo" aria-hidden="true" data-initiale="<?php echo htmlspecialchars($shop_initiale, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php if ($shop_logo !== ''): ?>
                                                    <img src="<?php echo htmlspecialchars(function_exists('upload_image_url') ? upload_image_url($shop_logo, 'sm') : app_url('/upload/' . $shop_logo), ENT_QUOTES, 'UTF-8'); ?>"
                                                        alt="" width="64" height="64" loading="lazy" decoding="async" onerror="this.remove();">
                                                <?php endif; ?>
                                            </span>
                                            <span class="mp-shop__name">
                                                <span class="mp-shop__name-text"><?php echo htmlspecialchars($shop_nom, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php if ($shop_certif): ?>
                                                    <i class="fas fa-circle-check mp-shop__certif" title="Boutique certifiée" aria-label="Boutique certifiée"></i>
                                                <?php endif; ?>
                                            </span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <li>
                                    <a class="mp-shop mp-shop--vendre" href="<?php echo htmlspecialchars(app_url('/admin/inscription-vendeur.php'), ENT_QUOTES, 'UTF-8'); ?>"
                                        title="Ouvrir ma boutique sur COLObanes" aria-label="Ouvrir ma boutique sur COLObanes (gratuit)">
                                        <i class="fas fa-shop mp-shop-cta__icon" aria-hidden="true"></i>
                                        <span class="mp-shop-cta__title">Ouvrez votre boutique</span>
                                        <span class="mp-shop-cta__btn">C’est gratuit <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                                    </a>
                                </li>
                            </ul>
                        <?php endif; ?>
                    </aside>
                </div>
            </section>
        </div>

        <div class="mp-shell">
            <section class="mp-top" aria-labelledby="mp-top-title">
                <header class="mp-top__head">
                    <h2 class="mp-top__title" id="mp-top-title">
                        <span class="mp-top__trophy" aria-hidden="true"><i class="fas fa-trophy"></i></span>
                        Top des ventes
                    </h2>
                    <div class="mp-top__actions">
                        <button type="button" class="mp-top__nav" data-top-scroll="-1" aria-label="Précédents"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                        <button type="button" class="mp-top__nav" data-top-scroll="1" aria-label="Suivants"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                        <a class="mp-top__all" href="top-ventes.php">Tout voir <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                </header>
                <div class="mp-top__track" id="mpStripScroll">
                    <?php $top_rang = 0; ?>
                    <?php foreach ($produits_strip as $sproduit): ?>
                        <?php
                        $sid = (int) ($sproduit['id'] ?? 0);
                        if ($sid <= 0) {
                            continue;
                        }
                        $top_rang++;
                        $s_promo = !empty($sproduit['prix_promotion']) && $sproduit['prix_promotion'] < $sproduit['prix'];
                        $spx = $s_promo ? (float) $sproduit['prix_promotion'] : (float) ($sproduit['prix'] ?? 0);
                        $snom = (string) ($sproduit['nom'] ?? 'Produit');
                        ?>
                        <a class="mp-top-card mp-top-card--<?php echo $top_rang; ?>" href="produit.php?id=<?php echo $sid; ?>&amp;mp_ref=home_strip">
                            <span class="mp-top-card__rank" aria-label="Numéro <?php echo $top_rang; ?>"><?php echo $top_rang; ?></span>
                            <div class="mp-top-card__img">
                                <img src="<?php echo htmlspecialchars(upload_image_url($sproduit['image_principale'] ?? '', 'sm')); ?>"
                                    alt="<?php echo htmlspecialchars($snom); ?>" loading="lazy"
                                    onerror="this.src='/image/produit1.jpg'">
                            </div>
                            <div class="mp-top-card__body">
                                <p class="mp-top-card__title"><?php echo htmlspecialchars($snom); ?></p>
                                <?php if (!empty($sproduit['avis_count'])): ?>
                                    <?php
                                    $note = (float) ($sproduit['avis_moyenne'] ?? 0);
                                    $count = (int) ($sproduit['avis_count'] ?? 0);
                                    $size = 'sm';
                                    require __DIR__ . '/includes/partials/product_rating_stars.php';
                                    ?>
                                <?php endif; ?>
                                <p class="mp-top-card__price">
                                    <strong><?php echo number_format($spx, 0, ',', ' '); ?> FCFA</strong>
                                    <?php if ($s_promo): ?><s><?php echo number_format((float) $sproduit['prix'], 0, ',', ' '); ?></s><?php endif; ?>
                                </p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <script>
                (function () {
                    var track = document.getElementById('mpStripScroll');
                    if (!track) return;
                    document.querySelectorAll('[data-top-scroll]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            track.scrollBy({ left: parseInt(btn.getAttribute('data-top-scroll'), 10) * track.clientWidth * 0.8, behavior: 'smooth' });
                        });
                    });
                })();
                </script>
            </section>
        </div>

        <?php
        /* Vente en gros (type Alibaba) : prix par pièce dégressif, quantité minimum */
        require_once __DIR__ . '/includes/produit_vente_gros.php';
        $produits_gros = vente_gros_produits_accueil(12);
        ?>
        <?php if (!empty($produits_gros)): ?>
        <div class="mp-shell">
            <section class="mp-gros" aria-labelledby="mp-gros-title">
                <header class="mp-gros__head">
                    <div>
                        <h2 class="mp-gros__title" id="mp-gros-title">
                            <span class="mp-gros__icon" aria-hidden="true"><i class="fas fa-boxes-stacked"></i></span>
                            Vente en gros
                        </h2>
                        <p class="mp-gros__sub">Achetez en quantité, payez moins cher la pièce</p>
                    </div>
                    <div class="mp-gros__actions">
                        <button type="button" class="mp-top__nav" data-gros-scroll="-1" aria-label="Précédents"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                        <button type="button" class="mp-top__nav" data-gros-scroll="1" aria-label="Suivants"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                    </div>
                </header>
                <div class="mp-gros__track" id="mpGrosScroll">
                    <?php foreach ($produits_gros as $gp):
                        $gid = (int) $gp['id'];
                        $g_paliers = vente_gros_paliers($gp);
                        $g_min_prix = vente_gros_prix_min($gp);
                        $g_detail = vente_gros_prix_detail($gp);
                        $g_moq = vente_gros_quantite_min($gp);
                        $g_pct = ($g_detail > 0 && $g_min_prix !== null) ? (int) round(($g_detail - $g_min_prix) / $g_detail * 100) : 0;
                        $g_nom = (string) ($gp['nom'] ?? 'Produit');
                    ?>
                    <a class="mp-gros-card" href="produit.php?id=<?php echo $gid; ?>&amp;mp_ref=home_gros">
                        <div class="mp-gros-card__img">
                            <img src="<?php echo htmlspecialchars(upload_image_url($gp['image_principale'] ?? '', 'sm'), ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars($g_nom, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy"
                                onerror="this.src='/image/produit1.jpg'">
                            <?php if ($g_pct > 0): ?><span class="mp-gros-card__save">Jusqu’à -<?php echo $g_pct; ?> %</span><?php endif; ?>
                        </div>
                        <div class="mp-gros-card__body">
                            <p class="mp-gros-card__title"><?php echo htmlspecialchars($g_nom, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mp-gros-card__price">
                                <small>dès</small>
                                <strong><?php echo number_format((float) $g_min_prix, 0, ',', ' '); ?> FCFA</strong>
                                <small>/ pièce</small>
                            </p>
                            <ul class="mp-gros-card__tiers">
                                <?php foreach (array_slice($g_paliers, 0, 3) as $pl): ?>
                                    <li><span><?php echo (int) $pl['q']; ?>+</span> <?php echo number_format($pl['p'], 0, ',', ' '); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="mp-gros-card__moq">
                                <i class="fas fa-box" aria-hidden="true"></i>
                                <?php echo $g_moq > 1 ? 'Min. ' . (int) $g_moq . ' pièces' : 'Dès 1 pièce'; ?>
                            </p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <script>
                (function () {
                    var track = document.getElementById('mpGrosScroll');
                    if (!track) return;
                    document.querySelectorAll('[data-gros-scroll]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            track.scrollBy({ left: parseInt(btn.getAttribute('data-gros-scroll'), 10) * track.clientWidth * 0.8, behavior: 'smooth' });
                        });
                    });
                })();
                </script>
            </section>
        </div>
        <?php endif; ?>

        <div class="mp-shell">

            <?php if (!empty($logos_carousel)): ?>
                <div class="mp-logos-wrap">
                    <section class="marques-section logos-section" data-aos="fade-up" data-aos-duration="600">
                        <div class="marques-container">
                            <button type="button" class="marques-nav marques-nav-prev logos-nav-prev"
                                aria-label="Logos précédents">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div class="marques-carousel owl-carousel logos-owl">
                                <?php foreach ($logos_carousel as $logo): ?>
                                    <?php
                                    $logo_path = '/image/produit1.jpg';
                                    if (!empty($logo['image'])) {
                                        $file_path = __DIR__ . '/upload/' . $logo['image'];
                                        if (file_exists($file_path)) {
                                            $logo_path = upload_image_url((string) $logo['image'], 'sm');
                                        }
                                    }
                                    ?>
                                    <div class="marque-item marque-item-logo">
                                        <div class="marque-logo-wrap">
                                            <img src="<?php echo $logo_path; ?>" alt="Logo partenaire" loading="lazy"
                                                decoding="async"
                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <span class="marque-fallback" style="display: none;"><i
                                                    class="fas fa-image"></i></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="marques-nav marques-nav-next logos-nav-next"
                                aria-label="Logos suivants">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

            <section class="mp-pair" aria-label="Classements et nouveautés">
                <div class="mp-pair-grid">
                    <article class="mp-panel">
                        <header class="mp-panel-head">
                            <div>
                                <h2>Les plus consultés</h2>
                            </div>
                            <a class="mp-panel-more" href="plus-consultes.php">Voir tout <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                        </header>
                        <div class="mp-panel-products">
                            <?php if (empty($produits_top_panneau)): ?>
                                <p class="mp-empty" style="grid-column:1/-1;">Aucun produit à mettre en avant pour le
                                    moment.</p>
                            <?php else: ?>
                                <?php
                                foreach ($produits_top_panneau as $produit) {
                                    $return_url = $return_url_mp;
                                    require $partial_top;
                                }
                                ?>
                            <?php endif; ?>
                        </div>
                    </article>
                    <article class="mp-panel">
                        <header class="mp-panel-head">
                            <div>
                                <h2>Nouveautés</h2>
                            </div>
                            <a class="mp-panel-more" href="nouveautes.php">Voir tout <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                        </header>
                        <div class="mp-panel-products">
                            <?php if (empty($produits_new_panneau)): ?>
                                <p class="mp-empty" style="grid-column:1/-1;">Pas de nouveautés pour le moment.</p>
                            <?php else: ?>
                                <?php
                                foreach ($produits_new_panneau as $produit) {
                                    $return_url = $return_url_mp;
                                    require $partial_newc;
                                }
                                ?>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            </section>


            <section class="mp-block" aria-labelledby="mp-all-heading">
                <header class="mp-block-head">
                    <h2 id="mp-all-heading">Nos produits</h2>
                    <?php if ($total_produits > 0): ?>
                        <a class="mp-block-more" href="produits.php">Catalogue (<?php echo (int) $total_produits; ?>)</a>
                    <?php endif; ?>
                </header>
                <div class="mp-grid" id="produits-container"
                    data-seed="<?php echo (int) ($seed_tous ?? 1); ?>"
                    data-offset="0"
                    data-endpoint="<?php echo htmlspecialchars(app_url('/api/home_produits_html.php'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (empty($produits_tous)): ?>
                        <p class="mp-empty">Aucun produit publié pour le moment.</p>
                    <?php else: ?>
                        <?php
                        foreach ($produits_tous as $mp_i => $produit) {
                            $return_url = $return_url_mp;
                            require $card_partial;
                            if ($mp_i === 3) {
                                /* Rayons après la 1re ligne ; le script recale selon le nombre de colonnes */
                                require __DIR__ . '/includes/partials/home_quad_banners.php';
                            }
                        }
                        ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($produits_tous)): ?>
                    <?php require __DIR__ . '/includes/partials/home_duo_banners.php'; ?>
                    <script>
                    (function () {
                        /* Quad (rayons) sous la 1re ligne ; Duo (offres) sous la 3e ligne */
                        var grid = document.getElementById('produits-container');
                        var duo = document.getElementById('mpDuo');
                        var quad = document.getElementById('mpQuad');
                        if (!grid) return;

                        function colsAndCards() {
                            var cols = getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length || 1;
                            var cards = grid.querySelectorAll(':scope > .mp-card');
                            return { cols: cols, cards: cards };
                        }

                        function placeQuad() {
                            if (!quad) return;
                            var info = colsAndCards();
                            if (!info.cards.length) return;
                            var after = info.cards[Math.min(info.cols, info.cards.length) - 1];
                            if (after && after.nextElementSibling !== quad) after.after(quad);
                        }

                        function placeDuo() {
                            if (!duo) return;
                            var info = colsAndCards();
                            var n = info.cols * 3;
                            if (info.cards.length < n) return;
                            var after = info.cards[n - 1];
                            if (after.nextElementSibling !== duo) after.after(duo);
                        }

                        placeQuad();
                        placeDuo();
                        new MutationObserver(function () { placeDuo(); }).observe(grid, { childList: true });
                        var t;
                        window.addEventListener('resize', function () {
                            clearTimeout(t);
                            t = setTimeout(function () { placeQuad(); placeDuo(); }, 150);
                        });
                    })();
                    </script>
                <?php endif; ?>
                <?php if (!empty($produits_tous) && $total_produits > count($produits_tous)): ?>
                    <div class="mp-infinite" id="mpInfinite" role="status" aria-label="Chargement des produits">
                        <span></span><span></span><span></span>
                    </div>
                    <noscript><p style="text-align:center"><a href="produits.php">Voir tous les produits</a></p></noscript>
                    <script>
                    (function () {
                        /* « Nos produits » : le lot suivant se charge tout seul en approchant du bas */
                        var grid = document.getElementById('produits-container');
                        var sentinel = document.getElementById('mpInfinite');
                        if (!grid || !sentinel || !('IntersectionObserver' in window)) return;
                        var offset = parseInt(grid.getAttribute('data-offset'), 10) || 0;
                        var seed = grid.getAttribute('data-seed');
                        var endpoint = grid.getAttribute('data-endpoint');
                        var busy = false, done = false, fails = 0;

                        function finish() {
                            done = true;
                            observer.disconnect();
                            sentinel.hidden = true;
                        }

                        function load() {
                            if (busy || done) return;
                            busy = true;
                            sentinel.classList.add('is-loading');
                            fetch(endpoint + '?offset=' + offset + '&seed=' + encodeURIComponent(seed), { credentials: 'same-origin' })
                                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                                .then(function (data) {
                                    fails = 0;
                                    if (data.html) grid.insertAdjacentHTML('beforeend', data.html);
                                    offset = data.next_offset;
                                    if (data.done) finish();
                                })
                                .catch(function () {
                                    if (++fails >= 3) finish();
                                })
                                .then(function () {
                                    busy = false;
                                    sentinel.classList.remove('is-loading');
                                    /* Écran encore vide en bas : on enchaîne le lot suivant */
                                    if (!done && sentinel.getBoundingClientRect().top < window.innerHeight + 600) {
                                        setTimeout(load, 150);
                                    }
                                });
                        }

                        var observer = new IntersectionObserver(function (entries) {
                            if (entries[0].isIntersecting) load();
                        }, { rootMargin: '0px 0px 600px 0px' });
                        observer.observe(sentinel);
                    })();
                    </script>
                <?php endif; ?>
            </section>

        </div>
    </main>
    <?php include('footer.php') ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js" crossorigin="anonymous"
        referrerpolicy="no-referrer" defer></script>
    <script>
        (function () {
            function finishAos() {
                document.documentElement.classList.remove('aos-not-ready');
            }

            function runAos() {
                if (typeof AOS === 'undefined') {
                    finishAos();
                    return;
                }
                AOS.init({
                    duration: 800,
                    once: true,
                    offset: 24,
                    disable: function () {
                        if (window.__COLOBANES_NATIVE_APP === true ||
                            document.documentElement.classList.contains('is-native-app')) {
                            return true;
                        }
                        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    }
                });
                requestAnimationFrame(function () {
                    requestAnimationFrame(finishAos);
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', runAos);
            } else {
                runAos();
            }
        })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('mpTrendScroll');
            var prev = document.querySelector('.mp-showcase-center .mp-trend-prev');
            var next = document.querySelector('.mp-showcase-center .mp-trend-next');
            if (!el) return;
            function showcaseScrollStep() {
                var card = el.querySelector('.mp-trend-card');
                if (!card) {
                    return 220;
                }
                var gap = 12;
                var style = window.getComputedStyle(el);
                var g = parseFloat(style.columnGap || style.gap) || gap;
                return card.getBoundingClientRect().width + g;
            }
            if (prev) {
                prev.addEventListener('click', function () {
                    el.scrollBy({
                        left: -showcaseScrollStep() * 4,
                        behavior: 'smooth'
                    });
                });
            }
            if (next) {
                next.addEventListener('click', function () {
                    el.scrollBy({
                        left: showcaseScrollStep() * 4,
                        behavior: 'smooth'
                    });
                });
            }
        });
    </script>
    <?php /* Si le carrousel ne charge pas, la première affiche reste visible (css/mp-hero-slider.css) */ ?>
    <script id="mpOwlScript" src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js" defer></script>

    <script>
        /* Carrousels (affiche d'accueil, logos). Si jQuery n'a pas pu être chargé, on attend le chargement de secours. */
        function mpInitCarousels() {
            if (typeof window.jQuery === 'undefined' || !window.jQuery.fn || !window.jQuery.fn.owlCarousel) {
                return false;
            }
            window.jQuery(function ($) {
                if ($('.mp-hero-slider-wrap .slider-area').length && $('.mp-hero-slider-wrap .slider-item')
                    .length) {
                    var $mpHeroSlider = $('.mp-hero-slider-wrap .slider-area');
                    var mpHeroSlideCount = $mpHeroSlider.children('.slider-item').length;
                    var mpHeroCarouselEnabled = mpHeroSlideCount > 1;
                    /* Téléphone : hauteur du cadre = proportion de l'affiche affichée (css/mp-hero-slider.css) */
                    var mpHeroFluid = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;

                    $mpHeroSlider.owlCarousel({
                        items: 1,
                        slideBy: 1,
                        loop: mpHeroCarouselEnabled,
                        dots: false,
                        nav: false,
                        mouseDrag: false,
                        touchDrag: false,
                        pullDrag: false,
                        freeDrag: false,
                        autoplay: mpHeroCarouselEnabled,
                        /* 7 s par affiche (le temps de lire), fondu doux d'1 s */
                        autoplayTimeout: 7000,
                        autoplayHoverPause: true,
                        autoplaySpeed: 1000,
                        smartSpeed: 1000,
                        /* Seule l'affiche sortante s'efface (la suivante est déjà affichée dessous) : jamais de blanc */
                        animateOut: 'fadeOut',
                        stagePadding: 0,
                        autoHeight: mpHeroFluid,
                        navText: ['<i class="fa-solid fa-chevron-left"></i>',
                            '<i class="fa-solid fa-chevron-right"></i>'
                        ]
                    });

                    var refreshMpHeroSlider = function () {
                        $mpHeroSlider.trigger('refresh.owl.carousel');
                    };
                    var mpHeroResizeTimer;
                    $(window).on('resize', function () {
                        clearTimeout(mpHeroResizeTimer);
                        mpHeroResizeTimer = setTimeout(refreshMpHeroSlider, 150);
                    });
                    $('.mp-hero-slider-wrap .slider-item img').each(function () {
                        if (this.complete) {
                            refreshMpHeroSlider();
                        } else {
                            $(this).on('load', refreshMpHeroSlider);
                        }
                    });

                    if (mpHeroCarouselEnabled) {
                        $mpHeroSlider.trigger('play.owl.autoplay', [7000]);
                    }

                    if (window.ColobanesSkeleton) {
                        window.ColobanesSkeleton.refresh();
                        if (typeof window.ColobanesSkeleton.checkFinishAll === 'function') {
                            window.ColobanesSkeleton.checkFinishAll();
                        }
                    }
                }

                // Carrousel des logos partenaires
                if ($('.logos-owl').length && $('.logos-owl .marque-item').length) {
                    var logosCarousel = $('.logos-owl').owlCarousel({
                        items: 4,
                        loop: true,
                        dots: false,
                        nav: false,
                        margin: 28,
                        stagePadding: 15,
                        autoplay: true,
                        autoplayTimeout: 3000,
                        autoplayHoverPause: true,
                        smartSpeed: 500,
                        responsive: {
                            0: {
                                items: 2,
                                margin: 16
                            },
                            480: {
                                items: 3,
                                margin: 20
                            },
                            768: {
                                items: 4,
                                margin: 28
                            },
                            992: {
                                items: 5,
                                margin: 28
                            }
                        }
                    });
                    $('.logos-nav-prev').on('click', function () {
                        logosCarousel.trigger('prev.owl.carousel');
                    });
                    $('.logos-nav-next').on('click', function () {
                        logosCarousel.trigger('next.owl.carousel');
                    });
                }
            });
            return true;
        }

        (function () {
            var done = false;
            function tryInit() {
                if (!done && mpInitCarousels()) {
                    done = true;
                }
            }
            document.addEventListener('DOMContentLoaded', tryInit);
            /* jQuery de secours arrivé après coup : le carrousel n'a pas pu s'installer, on le recharge puis on initialise */
            document.addEventListener('colobanes:jquery-ready', function () {
                if (window.jQuery.fn.owlCarousel) {
                    tryInit();
                    return;
                }
                var s = document.createElement('script');
                s.src = document.getElementById('mpOwlScript').src;
                s.onload = tryInit;
                document.body.appendChild(s);
            }, { once: true });
        })();
    </script>

</body>

</html>