<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

// Inclusion des modèles
require_once __DIR__ . '/models/model_categories.php';
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/includes/produit_boutique_line.php';
require_once __DIR__ . '/includes/image_optimizer.php';

// Rayon plateforme (categories_generales) ou catégorie feuille
$generale_id = isset($_GET['generale']) ? (int) $_GET['generale'] : 0;
$categorie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$filter_genre_id = isset($_GET['genre']) ? (int) $_GET['genre'] : 0;
$filter_sous_categorie_id = 0;

unset($categorie);
$categorie = null;
$generale_row = null;
$categorie_nom = 'Catégorie';
$produits = [];
$genres_pour_filtre_rayon = [];

if ($generale_id > 0) {
    $filter_sous_categorie_id = isset($_GET['sous_categorie']) ? (int) $_GET['sous_categorie'] : 0;
    $generale_row = get_categorie_generale_by_id($generale_id);
    if (!$generale_row || empty($generale_row['nom'])) {
        header('Location: index.php');
        exit;
    }
    $categorie_nom = (string) $generale_row['nom'];

    require_once __DIR__ . '/models/model_genres.php';
    if (
        function_exists('count_genres_linked_to_categorie_generale')
        && count_genres_linked_to_categorie_generale($generale_id) > 0
        && function_exists('get_genres_linked_to_categorie_generale')
    ) {
        $genres_pour_filtre_rayon = get_genres_linked_to_categorie_generale($generale_id);
        if ($filter_genre_id > 0) {
            $genre_ok = false;
            foreach ($genres_pour_filtre_rayon as $grow) {
                if ((int) ($grow['id'] ?? 0) === $filter_genre_id) {
                    $genre_ok = true;
                    break;
                }
            }
            if (!$genre_ok) {
                $filter_genre_id = 0;
            }
        }
    } else {
        $filter_genre_id = 0;
    }

    if ($filter_sous_categorie_id > 0) {
        if (
            !function_exists('categorie_plateforme_liee_au_rayon')
            || !categorie_plateforme_liee_au_rayon($filter_sous_categorie_id, $generale_id)
        ) {
            $filter_sous_categorie_id = 0;
        }
    }

    $produits = get_produits_by_categorie_generale(
        $generale_id,
        null,
        $filter_genre_id > 0 ? $filter_genre_id : null,
        $filter_sous_categorie_id > 0 ? $filter_sous_categorie_id : null
    );
} elseif ($categorie_id > 0) {
    $filter_genre_id = 0;
    $filter_sous_categorie_id = 0;
    $categorie = get_categorie_by_id($categorie_id);
    if (!$categorie || !is_array($categorie) || empty($categorie['nom'])) {
        header('Location: index.php');
        exit;
    }
    $categorie_nom = (string) $categorie['nom'];
    $produits = get_produits_by_categorie($categorie_id);
    if ($produits === false) {
        $produits = [];
    }
} else {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/client_tracking.php';
if ($generale_id > 0) {
    client_tracking_log_event('vue_rayon', [
        'categorie_generale_id' => $generale_id,
        'source' => 'categorie',
    ]);
} elseif ($categorie_id > 0) {
    client_tracking_log_event('vue_categorie', [
        'categorie_id' => $categorie_id,
        'source' => 'categorie',
    ]);
}

if (!empty($produits) && is_array($produits)) {
    require_once __DIR__ . '/includes/catalogue_shuffle.php';
    $produits = catalogue_melanger_produits($produits);
}

if (file_exists(__DIR__ . '/models/model_produits_avis.php')) {
    require_once __DIR__ . '/models/model_produits_avis.php';
    if (!empty($produits) && function_exists('produits_avis_enrich_products')) {
        $produits = produits_avis_enrich_products($produits);
    }
}

// Inclusion du fichier de connexion à la BDD (pour les autres fonctionnalités si nécessaire)
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

require_once __DIR__ . '/includes/marketplace_helpers.php';
require_once __DIR__ . '/includes/asset_version.php';

$sous_categories_rayon = [];
if ($generale_id > 0) {
    $sous_categories_rayon = function_exists('get_subcategories_for_categorie_generale')
        ? get_subcategories_for_categorie_generale($generale_id)
        : get_subcategories_with_active_products_for_general($generale_id, null);
}

$return_url_cat = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/categorie.php';
$card_partial = __DIR__ . '/includes/partials/home_mp_product_card.php';

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';
$base = get_site_base_url();
$seo_title = $categorie_nom . ' — catalogue ' . SITE_BRAND_NAME . ' | Marketplace Sénégal';
if ($generale_row) {
    $desc_cat = !empty($generale_row['description'])
        ? strip_tags((string) $generale_row['description'])
        : 'Rayon « ' . $categorie_nom . ' » sur ' . SITE_BRAND_NAME . ' : produits de boutiques sénégalaises, achat en ligne.';
    $seo_canonical = $base . '/categorie.php?generale=' . (int) $generale_id;
    if (!empty($filter_genre_id)) {
        $seo_canonical .= '&genre=' . (int) $filter_genre_id;
    }
    if (!empty($filter_sous_categorie_id)) {
        $seo_canonical .= '&sous_categorie=' . (int) $filter_sous_categorie_id;
    }
} else {
    $desc_cat = !empty($categorie['description'])
        ? strip_tags((string) $categorie['description'])
        : 'Catégorie « ' . $categorie_nom . ' » sur ' . SITE_BRAND_NAME . ', marketplace multi-boutiques au Sénégal.';
    $seo_canonical = $base . '/categorie.php?id=' . (int) $categorie_id;
}
$seo_description = mb_substr($desc_cat, 0, 160);
$seo_keywords = site_brand_seo_keywords_default() . ', ' . $categorie_nom . ', catalogue ' . $categorie_nom;
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <?php require_once __DIR__ . '/includes/ui_font_awesome.php'; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/mp-category-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/category-v2.css<?php echo asset_version_query(); ?>">
    <style>
        @keyframes catCardFadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        #produits-container > .mp-card { animation: catCardFadeIn 0.22s ease-out both; }
        #produits-container > .mp-card:nth-child(1) { animation-delay: 0.00s; }
        #produits-container > .mp-card:nth-child(2) { animation-delay: 0.03s; }
        #produits-container > .mp-card:nth-child(3) { animation-delay: 0.06s; }
        #produits-container > .mp-card:nth-child(4) { animation-delay: 0.09s; }
        #produits-container > .mp-card:nth-child(n+5){ animation-delay: 0.00s; }
        .mp-card--pending { display: none !important; }
        .mp-card--revealing { animation: catCardFadeIn 0.22s ease-out both; }
        #cat-load-indicator { text-align:center; padding:20px; display:none; }
    </style>
</head>

<body>

    <?php include __DIR__ . '/nav_bar.php'; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="cat-page-alert cat-page-alert--err mp-shell">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo htmlspecialchars((string) $_GET['error']); ?>
        </div>
    <?php endif; ?>

    <?php
    $cp_count = is_array($produits) ? count($produits) : 0;
    $cp_has_filtre = function_exists('nav_categorie_generale_filtre_href');
    $cp_sub_nom = '';
    foreach ($sous_categories_rayon as $sc) {
        if ((int) ($sc['id'] ?? 0) === $filter_sous_categorie_id) {
            $cp_sub_nom = trim((string) ($sc['nom'] ?? ''));
        }
    }
    ?>
    <main class="mp-main cp-page">
        <div class="mp-shell">
            <?php /* Titre gardé pour l'accessibilité et le référencement, sans bandeau visible */ ?>
            <h1 class="cp-sr-title"><?php echo htmlspecialchars($cp_sub_nom !== '' ? $cp_sub_nom : $categorie_nom); ?></h1>

            <?php if ($generale_id > 0 && !empty($sous_categories_rayon)): ?>
            <div class="cp-subs" role="navigation" aria-label="Sous-catégories">
                <?php if ($cp_has_filtre): ?>
                <a class="cp-sub cp-sub--all<?php echo $filter_sous_categorie_id === 0 ? ' is-active' : ''; ?>"
                    href="<?php echo htmlspecialchars(nav_categorie_generale_filtre_href($generale_id, $filter_genre_id > 0 ? $filter_genre_id : 0, 0), ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $filter_sous_categorie_id === 0 ? 'aria-current="page"' : ''; ?>>
                    <?php
                    /* « Tout » : mosaïque des images des sous-catégories du rayon */
                    $cp_mosaic = [];
                    foreach ($sous_categories_rayon as $msc) {
                        $mimg = function_exists('categorie_image_public_path') ? categorie_image_public_path($msc) : null;
                        if (is_string($mimg) && $mimg !== '') {
                            $cp_mosaic[] = $mimg;
                        }
                        if (count($cp_mosaic) === 4) {
                            break;
                        }
                    }
                    ?>
                    <span class="cp-sub__icon<?php echo count($cp_mosaic) === 4 ? ' cp-sub__icon--mosaic' : ''; ?>">
                        <?php if (count($cp_mosaic) === 4): ?>
                            <?php foreach ($cp_mosaic as $mimg): ?>
                                <img src="<?php echo htmlspecialchars(upload_image_url_from_src($mimg, 'sm'), ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
                            <?php endforeach; ?>
                        <?php else: ?>
                            <i class="fas fa-border-all" aria-hidden="true"></i>
                        <?php endif; ?>
                    </span>
                    <span class="cp-sub__label">Tout</span>
                </a>
                <?php endif; ?>
                <?php foreach ($sous_categories_rayon as $sc):
                    $sid = (int) ($sc['id'] ?? 0);
                    $slabel = trim((string) ($sc['nom'] ?? ''));
                    if ($sid <= 0 || $slabel === '') {
                        continue;
                    }
                    $h_sub = $cp_has_filtre
                        ? nav_categorie_generale_filtre_href($generale_id, $filter_genre_id > 0 ? $filter_genre_id : 0, $sid)
                        : nav_categorie_href($sid);
                    $sc_img = function_exists('categorie_image_public_path') ? categorie_image_public_path($sc) : null;
                    $sic_class = function_exists('categorie_fa_icon_class') ? categorie_fa_icon_class($sc) : 'fa-solid fa-layer-group';
                    $is_active_sub = $filter_sous_categorie_id === $sid;
                ?>
                    <a class="cp-sub<?php echo $is_active_sub ? ' is-active' : ''; ?>"
                        href="<?php echo htmlspecialchars($h_sub, ENT_QUOTES, 'UTF-8'); ?>"
                        title="<?php echo htmlspecialchars($slabel, ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo $is_active_sub ? 'aria-current="page"' : ''; ?>>
                        <span class="cp-sub__icon">
                            <?php if (is_string($sc_img) && $sc_img !== ''): ?>
                                <img src="<?php echo htmlspecialchars(upload_image_url_from_src($sc_img, 'sm'), ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
                            <?php else: ?>
                                <i class="<?php echo htmlspecialchars($sic_class, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                            <?php endif; ?>
                        </span>
                        <?php
                        // 11 lettres maximum (nom complet dans l'infobulle)
                        $slabel_court = mb_strlen($slabel, 'UTF-8') > 11
                            ? rtrim(mb_substr($slabel, 0, 11, 'UTF-8'), " \t&,-(") . '…'
                            : $slabel;
                        ?>
                        <span class="cp-sub__label"><?php echo htmlspecialchars($slabel_court); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="cp-toolbar">
                <?php if ($generale_id > 0 && !empty($genres_pour_filtre_rayon) && $cp_has_filtre): ?>
                <div class="cp-genres" role="navigation" aria-label="Filtrer par genre">
                    <a class="cp-genre<?php echo $filter_genre_id === 0 ? ' is-active' : ''; ?>"
                        href="<?php echo htmlspecialchars(nav_categorie_generale_filtre_href($generale_id, 0, $filter_sous_categorie_id > 0 ? $filter_sous_categorie_id : 0), ENT_QUOTES, 'UTF-8'); ?>">Tous</a>
                    <?php foreach ($genres_pour_filtre_rayon as $grow):
                        $gpid = (int) ($grow['id'] ?? 0);
                        $gn = trim((string) ($grow['nom'] ?? ''));
                        if ($gpid <= 0 || $gn === '') {
                            continue;
                        }
                        // « FEMME » → « Femme » (libellés saisis en majuscules)
                        if ($gn === mb_strtoupper($gn, 'UTF-8')) {
                            $gn = mb_strtoupper(mb_substr($gn, 0, 1, 'UTF-8'), 'UTF-8') . mb_strtolower(mb_substr($gn, 1, null, 'UTF-8'), 'UTF-8');
                        }
                    ?>
                        <a class="cp-genre<?php echo $filter_genre_id === $gpid ? ' is-active' : ''; ?>"
                            href="<?php echo htmlspecialchars(nav_categorie_generale_filtre_href($generale_id, $gpid, $filter_sous_categorie_id > 0 ? $filter_sous_categorie_id : 0), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($gn); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="cp-toolbar__count"><strong><?php echo $cp_count; ?></strong> article<?php echo $cp_count > 1 ? 's' : ''; ?></p>
                <?php endif; ?>

            </div>

            <section class="cp-products" aria-label="Produits">
                <div class="mp-grid cp-grid" id="produits-container">
                    <?php if (empty($produits)): ?>
                        <div class="mp-empty cp-empty">
                            <span class="cp-empty__icon"><i class="fas fa-box-open" aria-hidden="true"></i></span>
                            <h2>Aucun produit pour le moment</h2>
                            <p>Les boutiques n’ont encore rien publié ici<?php echo ($filter_genre_id > 0 || $filter_sous_categorie_id > 0) ? ' avec ces filtres' : ''; ?>.</p>
                            <?php if (($filter_genre_id > 0 || $filter_sous_categorie_id > 0) && $cp_has_filtre): ?>
                                <a href="<?php echo htmlspecialchars(nav_categorie_generale_filtre_href($generale_id, 0, 0), ENT_QUOTES, 'UTF-8'); ?>" class="cp-btn">Voir tout le rayon</a>
                            <?php else: ?>
                                <a href="index.php" class="cp-btn">Retour à l’accueil</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php
                        foreach ($produits as $produit) {
                            $return_url = $return_url_cat;
                            require $card_partial;
                        }
                        ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        (function () {
            const container = document.getElementById('produits-container');
            if (!container) return;
            const cards = Array.from(container.querySelectorAll('.mp-card'));
            const BATCH = 20;
            if (cards.length <= BATCH) return;

            // Masquer les cartes au-delà du premier lot
            for (let i = BATCH; i < cards.length; i++) {
                cards[i].classList.add('mp-card--pending');
            }

            // Indicateur spinner
            const loader = document.createElement('div');
            loader.id = 'cat-load-indicator';
            loader.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:1.6rem;color:var(--couleur-dominante);"></i>';
            container.insertAdjacentElement('afterend', loader);

            // Compteur
            const counter = document.createElement('p');
            counter.id = 'cat-count';
            counter.style.cssText = 'text-align:center;font-size:14px;color:var(--gris-moyen);margin-top:8px;';
            counter.innerHTML = 'Affichés\u00a0: <span id="cat-count-n">' + BATCH + '</span>\u00a0/ ' + cards.length + ' produits';
            loader.insertAdjacentElement('afterend', counter);

            let shown = BATCH;
            let sentinel = null;

            function placeSentinel() {
                if (sentinel) sentinel.remove();
                sentinel = document.createElement('div');
                sentinel.style.height = '1px';
                cards[shown - 1].insertAdjacentElement('afterend', sentinel);
                obs.observe(sentinel);
            }

            const obs = new IntersectionObserver(function (entries) {
                if (!entries[0].isIntersecting) return;
                obs.disconnect();

                loader.style.display = 'block';
                setTimeout(function () {
                    const next = cards.slice(shown, shown + BATCH);
                    next.forEach(function (c) {
                        c.classList.remove('mp-card--pending');
                        c.classList.add('mp-card--revealing');
                    });
                    shown += next.length;
                    const countEl = document.getElementById('cat-count-n');
                    if (countEl) countEl.textContent = shown;
                    loader.style.display = 'none';

                    if (shown < cards.length) {
                        placeSentinel();
                    } else {
                        if (sentinel) sentinel.remove();
                        counter.style.display = 'none';
                    }
                }, 80);
            }, { rootMargin: '200px' });

            if ('IntersectionObserver' in window) {
                placeSentinel();
            } else {
                // Fallback : tout afficher d'un coup
                cards.forEach(function (c) { c.classList.remove('mp-card--pending'); });
                counter.style.display = 'none';
            }
        })();
    </script>
    <script>
        /* Sous-catégorie choisie : visible dans la rangée défilante */
        (function () {
            var active = document.querySelector('.cp-subs .cp-sub.is-active');
            var row = document.querySelector('.cp-subs');
            if (active && row) {
                row.scrollLeft = active.offsetLeft - (row.clientWidth - active.offsetWidth) / 2;
            }
        })();

    </script>

</body>

</html>