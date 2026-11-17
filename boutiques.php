<?php
/**
 * Catalogue des boutiques partenaires — recherche + carte de proximité.
 */

require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/conn/conn.php';
require_once __DIR__ . '/models/model_boutiques_marketplace.php';
require_once __DIR__ . '/includes/geo_location_service.php';
require_once __DIR__ . '/includes/marketplace_country_filter.php';
require_once __DIR__ . '/includes/marketplace_helpers.php';
require_once __DIR__ . '/includes/marketplace_boutique_card_helpers.php';
require_once __DIR__ . '/includes/boutique_types.php';
require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';
require_once __DIR__ . '/includes/app_base.php';

$base = get_site_base_url();
$seo_title = 'Boutiques partenaires — ' . SITE_BRAND_NAME . ' | Marketplace Sénégal';
$seo_description = 'Découvrez les boutiques vendeurs partenaires sur ' . SITE_BRAND_NAME . '. Recherchez par nom et explorez les commerces proches de vous sur la carte.';
$seo_keywords = site_brand_seo_keywords_default() . ', boutiques partenaires, vendeurs, marketplace, géolocalisation';
$seo_canonical = $base . '/boutiques.php';

$search = trim((string) ($_GET['q'] ?? ''));
$filter_type_id = (int) ($_GET['type'] ?? 0);
$has_dist_param = isset($_GET['dist']);
$filter_dist_raw = $has_dist_param ? (int) $_GET['dist'] : 0;
if ($has_dist_param) {
    $filter_dist = boutique_types_distance_is_valid($filter_dist_raw)
        ? $filter_dist_raw
        : boutique_types_distance_default_km();
} else {
    $filter_dist = 0;
}
$map_filter_dist = boutique_types_distance_is_valid($filter_dist)
    ? $filter_dist
    : boutique_types_distance_default_km();
$map_limit = boutique_types_map_limit_normalize((int) ($_GET['limit'] ?? boutique_types_map_limit_default()));
$geo_loc = geo_session_get_location();
$geo_error = !empty($_GET['geo_error']);
$country = marketplace_country_filter_code();

$types_actifs = boutique_types_list_active();
$types_filter_available = count($types_actifs) > 0;

$boutiques_map_max = 200;
$rayon_proche_default = boutique_types_distance_default_km();
$has_geo = $geo_loc !== null;
$lat_proche = $has_geo ? (float) $geo_loc['lat'] : null;
$lng_proche = $has_geo ? (float) $geo_loc['lng'] : null;

/* Ancre marketplace Colobane (Dakar) — recentre si GPS hors zone. */
$colobane_lat = 14.7167;
$colobane_lng = -17.4342;
$lat_map = $lat_proche;
$lng_map = $lng_proche;
$geo_far_from_colobane = false;
if ($has_geo && function_exists('geo_distance_km')) {
    $geo_far_from_colobane = geo_distance_km($lat_proche, $lng_proche, $colobane_lat, $colobane_lng) > 100.0;
    if ($geo_far_from_colobane) {
        $lat_map = $colobane_lat;
        $lng_map = $colobane_lng;
    }
}

$use_geo_catalog = $has_geo;
$catalog_rayon_km = $filter_dist > 0 ? boutique_types_distance_to_rayon_km($filter_dist) : 0.0;

/* Carte : jusqu'à 200 boutiques les plus proches (bassin Dakar / Colobane). */
$map_rayon_km = $has_geo ? 80.0 : 0.0;

$rayon_proche = $map_rayon_km > 0
    ? $map_rayon_km
    : (float) $rayon_proche_default;

$nb_boutiques = marketplace_count_boutiques(
    $search,
    $country,
    $use_geo_catalog ? $lat_proche : null,
    $use_geo_catalog ? $lng_proche : null,
    $use_geo_catalog ? $catalog_rayon_km : 0.0,
    false,
    $filter_type_id
);
/* Catalogue : toutes les boutiques correspondant aux filtres (pas de pagination). */
$per_page = max(1, (int) $nb_boutiques);
$page = 1;
$total_pages = 1;
$offset = 0;

$boutiques = marketplace_list_boutiques(
    $search,
    $per_page,
    $offset,
    $country,
    $use_geo_catalog ? $lat_proche : null,
    $use_geo_catalog ? $lng_proche : null,
    $use_geo_catalog ? $catalog_rayon_km : 0.0,
    false,
    $filter_type_id
);

$boutiques_vignettes = marketplace_boutiques_produits_vignettes(array_column($boutiques, 'id'), 3);
foreach ($boutiques as $bk => $boutique_row) {
    $boutiques[$bk]['_produits_vignettes'] = $boutiques_vignettes[(int) ($boutique_row['id'] ?? 0)] ?? [];
}
unset($boutiques_vignettes, $bk, $boutique_row);

$boutiques_proches = [];
$map_boutiques_all = [];
if ($has_geo) {
    $map_boutiques_all = marketplace_list_boutiques(
        '',
        $boutiques_map_max,
        0,
        $country,
        $lat_map,
        $lng_map,
        $map_rayon_km,
        true,
        $filter_type_id
    );
    $boutiques_proches = $map_boutiques_all;
}
$map_payload = marketplace_boutiques_map_payload($map_boutiques_all);
$nb_proches = count($boutiques_proches);

/* Marqueurs déco : réutilise le catalogue déjà chargé (pas de 2ᵉ grosse requête). */
$nearby_map_shops = marketplace_boutiques_map_deco_from_list(
    !empty($map_boutiques_all) ? $map_boutiques_all : $boutiques,
    4
);

$redirect_map = app_url('/boutiques.php');
$redirect_params = [];
if ($search !== '') {
    $redirect_params['q'] = $search;
}
if ($filter_type_id > 0) {
    $redirect_params['type'] = $filter_type_id;
}
if ($filter_dist > 0) {
    $redirect_params['dist'] = $filter_dist;
} else {
    $redirect_params['dist'] = boutique_types_distance_default_km();
}
$redirect_params['open_map'] = 1;
$redirect_map .= '?' . http_build_query($redirect_params, '', '&', PHP_QUERY_RFC3986);

if (!function_exists('boutiques_catalog_page_url')) {
    function boutiques_catalog_page_url(int $target_page, string $search_q = '', int $type_id = 0, int $dist_km = 0): string
    {
        $params = [];
        if (trim($search_q) !== '') {
            $params['q'] = trim($search_q);
        }
        if ($type_id > 0) {
            $params['type'] = $type_id;
        }
        if ($dist_km > 0) {
            $params['dist'] = $dist_km;
        }
        if ($target_page > 1) {
            $params['page'] = $target_page;
        }
        $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return '/boutiques.php' . ($qs !== '' ? '?' . $qs : '');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <?php require_once __DIR__ . '/includes/ui_font_awesome.php'; ?>
<link rel="preload" as="image" href="<?php echo htmlspecialchars(app_url('/image/map_dakar_nearby.webp'), ENT_QUOTES, 'UTF-8'); ?>" type="image/webp">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/boutiques-marketplace.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/boutiques-catalogue.css<?php echo asset_version_query(); ?>">
</head>

<body class="mp-bt-body bq-body">
    <?php include 'nav_bar.php'; ?>

    <?php
    $bq_catalog_url = app_url('/boutiques.php');
    $bq_has_filters = ($search !== '' || $filter_type_id > 0);
    $bq_count_label = number_format((int) $nb_boutiques, 0, ',', ' ') . ' boutique' . ($nb_boutiques > 1 ? 's' : '');
    ?>
    <main class="bq-page">
        <header class="bq-hero">
            <div class="bq-wrap bq-hero__inner">
                <div class="bq-hero__text">
                    <h1 class="bq-hero__title">Boutiques</h1>
                </div>
                <form class="bq-search" method="GET" action="<?php echo htmlspecialchars($bq_catalog_url, ENT_QUOTES, 'UTF-8'); ?>" role="search">
                    <label class="bq-search__field">
                        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                        <span class="visually-hidden">Nom de la boutique</span>
                        <input type="search" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Rechercher une boutique" autocomplete="off" enterkeyhint="search">
                    </label>
                    <?php if ($types_filter_available): ?>
                    <label class="bq-search__type">
                        <span class="visually-hidden">Type de boutique</span>
                        <select name="type" id="mpBtNearbyType" class="js-bq-autosubmit">
                            <?php echo boutique_types_filter_options_html($filter_type_id); ?>
                        </select>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </label>
                    <?php endif; ?>
                    <?php if ($filter_dist > 0): ?>
                    <input type="hidden" name="dist" value="<?php echo (int) $filter_dist; ?>">
                    <?php endif; ?>
                    <button type="submit" class="bq-search__submit">
                        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                        <span>Rechercher</span>
                    </button>
                </form>
            </div>
        </header>

        <div class="bq-wrap bq-main">
            <?php if ($geo_error): ?>
                <div class="bq-alert" role="alert">
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    Impossible d'utiliser votre position. Réessayez dans un instant.
                </div>
            <?php endif; ?>

            <section class="bq-near<?php echo $geo_loc !== null ? ' bq-near--on' : ''; ?>" aria-labelledby="bqNearTitle">
                <div class="bq-near__map" aria-hidden="true">
                    <span class="bq-near__you"><span></span></span>
                    <?php foreach ($nearby_map_shops as $ms): ?>
                        <span class="bq-near__pin"
                            style="--pin-x: <?php echo htmlspecialchars((string) $ms['left'], ENT_QUOTES, 'UTF-8'); ?>%; --pin-y: <?php echo htmlspecialchars((string) $ms['top'], ENT_QUOTES, 'UTF-8'); ?>%; --pin-x-m: <?php echo htmlspecialchars((string) $ms['left_m'], ENT_QUOTES, 'UTF-8'); ?>%; --pin-y-m: <?php echo htmlspecialchars((string) $ms['top_m'], ENT_QUOTES, 'UTF-8'); ?>%; animation-delay: <?php echo htmlspecialchars((string) $ms['delay'], ENT_QUOTES, 'UTF-8'); ?>s;">
                            <img src="<?php echo htmlspecialchars($ms['logo'], ENT_QUOTES, 'UTF-8'); ?>"
                                alt=""
                                width="36"
                                height="36"
                                loading="lazy"
                                decoding="async"
                                onerror="this.parentNode.remove();">
                        </span>
                    <?php endforeach; ?>
                </div>
                <div class="bq-near__body">
                    <p class="bq-near__eyebrow"><i class="fas fa-location-dot" aria-hidden="true"></i> Autour de vous</p>
                    <h2 class="bq-near__title" id="bqNearTitle">Boutiques proches</h2>
                    <?php if ($geo_loc === null): ?>
                        <form method="POST" action="<?php echo htmlspecialchars(app_url('/set-location.php'), ENT_QUOTES, 'UTF-8'); ?>" id="geo-locate-form" class="bq-near__form">
                            <input type="hidden" name="geo_lat" id="geo_lat" value="">
                            <input type="hidden" name="geo_lng" id="geo_lng" value="">
                            <input type="hidden" name="geo_precision" id="geo_precision" value="">
                            <input type="hidden" name="geo_source" id="geo_source" value="gps">
                            <input type="hidden" name="redirect"
                                value="<?php echo htmlspecialchars($redirect_map, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="button" class="bq-btn bq-btn--primary" id="btn-geo-locate">
                                <i class="fas fa-location-crosshairs" aria-hidden="true"></i>
                                Utiliser ma position
                            </button>
                        </form>
                        <div id="geo-status" class="bq-geo-status" hidden></div>
                    <?php else: ?>
                        <button type="button"
                            class="bq-btn bq-btn--primary"
                            id="mpBtOpenMap"
                            <?php echo empty($map_payload) ? 'disabled' : ''; ?>>
                            <i class="fas fa-map-location-dot" aria-hidden="true"></i>
                            Ouvrir la carte
                        </button>
                    <?php endif; ?>
                </div>
            </section>

            <div class="bq-results-head">
                <h2 class="bq-results-head__title">
                    <?php if ($search !== ''): ?>
                        Résultats pour « <?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?> »
                    <?php elseif ($use_geo_catalog): ?>
                        Les plus proches
                    <?php else: ?>
                        Toutes les boutiques
                    <?php endif; ?>
                </h2>
                <p class="bq-results-head__count">
                    <?php echo htmlspecialchars($bq_count_label, ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($use_geo_catalog && $filter_dist > 0 && !boutique_types_distance_is_unlimited($filter_dist)): ?>
                        · moins de <?php echo (int) $filter_dist; ?> km
                    <?php endif; ?>
                </p>
                <?php if ($bq_has_filters): ?>
                    <a class="bq-results-head__reset" href="<?php echo htmlspecialchars($bq_catalog_url, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-xmark" aria-hidden="true"></i> Effacer les filtres
                    </a>
                <?php endif; ?>
            </div>

            <?php if (empty($boutiques)): ?>
                <div class="bq-empty">
                    <span class="bq-empty__icon"><i class="fas fa-store-slash" aria-hidden="true"></i></span>
                    <h2>Aucune boutique trouvée</h2>
                    <p>
                        <?php if ($bq_has_filters): ?>
                            Essayez un autre mot-clé ou un autre type de boutique.
                        <?php else: ?>
                            Les boutiques vendeurs apparaîtront ici dès leur inscription.
                        <?php endif; ?>
                    </p>
                    <?php if ($bq_has_filters): ?>
                        <a href="<?php echo htmlspecialchars($bq_catalog_url, ENT_QUOTES, 'UTF-8'); ?>" class="bq-btn bq-btn--ghost">Voir toutes les boutiques</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="bq-grid" role="list">
                    <?php foreach ($boutiques as $boutique):
                        include __DIR__ . '/includes/partials/boutique_marketplace_card.php';
                    endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="bq-pager" role="navigation" aria-label="Pagination des boutiques">
                        <?php if ($page > 1): ?>
                            <a class="bq-pager__btn bq-pager__btn--nav" href="<?php echo htmlspecialchars(boutiques_catalog_page_url($page - 1, $search, $filter_type_id, $filter_dist), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Page précédente">
                                <i class="fas fa-chevron-left" aria-hidden="true"></i><span>Précédent</span>
                            </a>
                        <?php else: ?>
                            <span class="bq-pager__btn bq-pager__btn--nav is-disabled" aria-disabled="true"><i class="fas fa-chevron-left" aria-hidden="true"></i><span>Précédent</span></span>
                        <?php endif; ?>

                        <div class="bq-pager__pages">
                            <?php
                            $page_window_start = max(1, $page - 1);
                            $page_window_end = min($total_pages, $page + 1);
                            if ($page_window_start > 1): ?>
                                <a class="bq-pager__btn" href="<?php echo htmlspecialchars(boutiques_catalog_page_url(1, $search, $filter_type_id, $filter_dist), ENT_QUOTES, 'UTF-8'); ?>">1</a>
                                <?php if ($page_window_start > 2): ?>
                                    <span class="bq-pager__gap" aria-hidden="true">…</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($pi = $page_window_start; $pi <= $page_window_end; $pi++): ?>
                                <?php if ($pi === $page): ?>
                                    <span class="bq-pager__btn is-active" aria-current="page"><?php echo (int) $pi; ?></span>
                                <?php else: ?>
                                    <a class="bq-pager__btn" href="<?php echo htmlspecialchars(boutiques_catalog_page_url($pi, $search, $filter_type_id, $filter_dist), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $pi; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page_window_end < $total_pages): ?>
                                <?php if ($page_window_end < $total_pages - 1): ?>
                                    <span class="bq-pager__gap" aria-hidden="true">…</span>
                                <?php endif; ?>
                                <a class="bq-pager__btn" href="<?php echo htmlspecialchars(boutiques_catalog_page_url($total_pages, $search, $filter_type_id, $filter_dist), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $total_pages; ?></a>
                            <?php endif; ?>
                        </div>

                        <?php if ($page < $total_pages): ?>
                            <a class="bq-pager__btn bq-pager__btn--nav" href="<?php echo htmlspecialchars(boutiques_catalog_page_url($page + 1, $search, $filter_type_id, $filter_dist), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Page suivante">
                                <span>Suivant</span><i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </a>
                        <?php else: ?>
                            <span class="bq-pager__btn bq-pager__btn--nav is-disabled" aria-disabled="true"><span>Suivant</span><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <div class="mp-bt-map-modal bq-map" id="mpBtMapModal" hidden aria-hidden="true">
        <div class="bq-map__backdrop" data-map-close></div>
        <div class="bq-map__dialog" role="dialog" aria-modal="true" aria-labelledby="mpBtMapTitle">
            <header class="bq-map__head">
                <div class="bq-map__head-text">
                    <h2 id="mpBtMapTitle">Boutiques proches</h2>
                    <p>Touchez une boutique pour voir le détail et l'itinéraire.</p>
                </div>
                <button type="button" class="bq-map__close" data-map-close aria-label="Fermer la carte">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
                <div class="bq-map__filters" role="group" aria-label="Filtres carte">
                    <?php if ($types_filter_available): ?>
                    <label class="bq-map__type">
                        <span class="visually-hidden">Type de boutique</span>
                        <select id="mpBtMapFilterType" aria-label="Type de boutique">
                            <?php echo boutique_types_filter_options_html($filter_type_id); ?>
                        </select>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </label>
                    <?php endif; ?>
                    <div class="bq-map__limit">
                        <span class="bq-map__limit-label">Afficher</span>
                        <?php echo boutique_types_map_limit_chips_html($map_limit, 'mpBtMapFilterLimit'); ?>
                    </div>
                </div>
            </header>
            <div class="bq-map__layout">
                <aside class="mp-bt-map-list bq-map__list" id="mpBtMapList" aria-label="Liste des boutiques proches">
                    <header class="bq-map__list-head">
                        <h3>Boutiques <span class="bq-map__list-count" id="mpBtMapListCount">0</span></h3>
                        <button type="button" class="bq-map__icon-btn bq-map__list-close" id="mpBtMapListClose" aria-label="Fermer la liste">
                            <i class="fas fa-xmark" aria-hidden="true"></i>
                        </button>
                    </header>
                    <ul class="mp-bt-map-list__items" id="mpBtMapListItems" role="list"></ul>
                </aside>

                <div class="bq-map__canvas-wrap">
                    <div id="mpBtMapCanvas" class="bq-map__canvas" aria-label="Carte des boutiques"></div>

                    <button type="button"
                        class="bq-map__list-toggle"
                        id="mpBtMapListToggle"
                        aria-expanded="false"
                        aria-controls="mpBtMapList">
                        <i class="fas fa-list-ul" aria-hidden="true"></i>
                        <span>Voir la liste</span>
                    </button>

                    <aside class="mp-bt-map-detail bq-map__detail" id="mpBtMapSide" hidden aria-label="Détail boutique">
                        <div class="bq-map__detail-cover"></div>
                        <button type="button" class="bq-map__icon-btn bq-map__detail-close" data-map-detail-close aria-label="Fermer le détail">
                            <i class="fas fa-xmark" aria-hidden="true"></i>
                        </button>
                        <div class="bq-map__detail-body" id="mpBtMapSideDetail">
                            <div class="bq-map__detail-logo" id="mpBtMapSideLogo"></div>
                            <h3 class="bq-map__detail-name" id="mpBtMapSideName"></h3>
                            <p class="bq-map__detail-meta">
                                <span class="bq-map__detail-dist" id="mpBtMapSideDist"></span>
                                <span class="bq-map__detail-addr" id="mpBtMapSideAddr"></span>
                            </p>
                            <a href="#" class="bq-btn bq-btn--primary bq-btn--block" id="mpBtMapSideVisit">
                                <i class="fas fa-store" aria-hidden="true"></i> Voir la boutique
                            </a>
                            <div class="bq-map__detail-actions">
                                <a href="#" class="bq-map__action" id="mpBtMapSideMaps" target="_blank" rel="noopener">
                                    <i class="fas fa-diamond-turn-right" aria-hidden="true"></i><span>Itinéraire</span>
                                </a>
                                <button type="button" class="bq-map__action" id="mpBtMapShareShop">
                                    <i class="fas fa-share-nodes" aria-hidden="true"></i><span>Partager</span>
                                </button>
                                <button type="button" class="bq-map__action" id="mpBtMapShareGeo">
                                    <i class="fas fa-location-arrow" aria-hidden="true"></i><span>Position</span>
                                </button>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="mpBtMapData"><?php
        $map_user_payload = null;
        if ($geo_loc) {
            $map_user_payload = [
                'lat' => (float) $geo_loc['lat'],
                'lng' => (float) $geo_loc['lng'],
            ];
            if (!empty($geo_far_from_colobane)) {
                $map_user_payload = [
                    'lat' => $colobane_lat,
                    'lng' => $colobane_lng,
                    'approx' => true,
                ];
            }
        }
        echo json_encode([
            'user' => $map_user_payload,
            'boutiques' => $map_payload,
            'map_max' => $boutiques_map_max,
            'filters' => [
                'type_id' => $filter_type_id,
                'limit' => $map_limit,
            ],
            'types' => boutique_types_filter_json_list(),
            'anchor' => [
                'lat' => $colobane_lat,
                'lng' => $colobane_lng,
                'label' => 'Colobane, Dakar',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?></script>

    <?php include 'footer.php'; ?>
    <?php require __DIR__ . '/includes/partials/platform_share_modal.php'; ?>

    <div class="mp-bt-geo-loading" id="mpBtGeoLoading" hidden aria-hidden="true" role="alertdialog" aria-labelledby="mpBtGeoLoadingTitle" aria-describedby="mpBtGeoLoadingText">
        <div class="mp-bt-geo-loading__backdrop"></div>
        <div class="mp-bt-geo-loading__dialog">
            <h2 class="mp-bt-geo-loading__title" id="mpBtGeoLoadingTitle">Recherche en cours</h2>
            <p class="mp-bt-geo-loading__text" id="mpBtGeoLoadingText">Veuillez patienter s'il vous plaît.</p>
            <div class="mp-bt-geo-loading__bar" aria-hidden="true">
                <span class="mp-bt-geo-loading__bar-fill" id="mpBtGeoLoadingBar"></span>
            </div>
            <p class="mp-bt-geo-loading__pct" id="mpBtGeoLoadingPct" aria-live="polite">0&nbsp;%</p>
        </div>
    </div>

    <script src="<?php echo htmlspecialchars(app_url('/js/geo-location.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script src="<?php echo htmlspecialchars(app_url('/js/platform-share-modal.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script src="<?php echo htmlspecialchars(app_url('/js/geo-nav-apps.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script src="<?php echo htmlspecialchars(app_url('/js/boutiques-proches-map.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script src="<?php echo htmlspecialchars(app_url('/js/boutiques-page.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>

</html>
