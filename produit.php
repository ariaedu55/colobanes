<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

// Inclusion des modèles et contrôleurs
require_once __DIR__ . '/models/model_produits.php';
require_once __DIR__ . '/models/model_categories.php';
require_once __DIR__ . '/includes/produit_boutique_line.php';
require_once __DIR__ . '/models/model_panier.php';
require_once __DIR__ . '/models/model_visites.php';
require_once __DIR__ . '/models/model_variantes.php';
require_once __DIR__ . '/controllers/controller_panier.php';
require_once __DIR__ . '/includes/flash_toast.php';
require_once __DIR__ . '/includes/image_optimizer.php';
require_once __DIR__ . '/includes/site_csrf.php';

// Récupérer l'ID du produit depuis l'URL ou POST
$produit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produit_id'])) {
    $produit_id = (int) $_POST['produit_id'];
}

// Traitement de l'ajout au panier
$message = '';
$message_type = '';

// Traitement du formulaire POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_panier') {
    $result = process_add_to_panier();

    if ($result['success']) {
        $back = $produit_id > 0 ? ('/produit.php?id=' . $produit_id) : '/index.php';
        http_redirect_safe($back);
    }
    $message = $result['message'] ?? '';
    $message_type = 'error';
}

if ($message !== '') {
    flash_toast_queue_page($message_type !== '' ? $message_type : 'error', $message);
}

// Récupérer les informations du produit
$produit = $produit_id > 0 ? get_produit_by_id($produit_id) : false;

// Si le produit n'existe pas, rediriger vers l'accueil
if (!$produit || !produit_est_visible_client($produit['statut'] ?? '')) {
    if (!function_exists('app_url')) {
        require_once __DIR__ . '/includes/app_base.php';
    }
    header('Location: ' . app_url('/index.php'));
    exit;
}

require_once __DIR__ . '/includes/marketplace_region_filter.php';
if (!produit_visible_in_marketplace_region($produit, false)) {
    if (!function_exists('app_url')) {
        require_once __DIR__ . '/includes/app_base.php';
    }
    header('Location: ' . app_url('/index.php'));
    exit;
}

if ($produit_id > 0) {
    $mp_ref_track = isset($_GET['mp_ref']) ? trim((string) $_GET['mp_ref']) : '';
    $track_user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    register_shutdown_function(static function () use ($produit_id, $mp_ref_track, $track_user_id) {
        require_once __DIR__ . '/includes/client_tracking.php';
        client_tracking_log_event('vue_produit', ['produit_id' => $produit_id]);
        if ($mp_ref_track !== '') {
            client_tracking_log_event('clic_produit', [
                'produit_id' => $produit_id,
                'source' => $mp_ref_track,
            ]);
        }
        if ($track_user_id > 0) {
            add_visite($track_user_id, $produit_id);
        }
    });
}

// Calculer le prix à afficher (promotion si disponible)
$prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
    ? $produit['prix_promotion']
    : $produit['prix'];
$prix_produit_detail = (float) $prix_affichage;
$prix_original = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
    ? $produit['prix']
    : null;
$pourcentage_reduction = 0;
if ($prix_original) {
    $pourcentage_reduction = round((($produit['prix'] - $produit['prix_promotion']) / $produit['prix']) * 100);
}

// Récupérer les variantes du produit
$variantes = get_variantes_by_produit($produit_id);

// Produits similaires : score catégorie, prix, marque, région, vues et achats
$produit_generale_id = 0;
if (function_exists('produits_has_column') && produits_has_column('categorie_generale_id') && !empty($produit['categorie_generale_id'])) {
    $produit_generale_id = (int) $produit['categorie_generale_id'];
}
if ($produit_generale_id <= 0 && function_exists('categories_has_categorie_generale_id_column') && categories_has_categorie_generale_id_column()) {
    $cat_row_sim = get_categorie_by_id((int) ($produit['categorie_id'] ?? 0));
    if ($cat_row_sim && !empty($cat_row_sim['categorie_generale_id'])) {
        $produit_generale_id = (int) $cat_row_sim['categorie_generale_id'];
    }
}

$similaires_affichage = 8;
require_once __DIR__ . '/includes/produits_similaires_engine.php';
$produits_similaires = get_produits_similaires_recommandes($produit, $produit_generale_id, $similaires_affichage, 30);
if (empty($produits_similaires)) {
    $fallback_sim = get_produits_by_categorie($produit['categorie_id']);
    if ($fallback_sim === false) {
        $fallback_sim = [];
    }
    require_once __DIR__ . '/includes/catalogue_shuffle.php';
    $produits_similaires = catalogue_tirer_produits_similaires(
        is_array($fallback_sim) ? array_values($fallback_sim) : [],
        $produit_id,
        $similaires_affichage
    );
}

require_once __DIR__ . '/includes/produit_disponibilite.php';
require_once __DIR__ . '/includes/produit_alternatives_engine.php';

$produit_achetable = produit_est_achetable($produit);
$produits_alternatives = [];
if (!$produit_achetable) {
    $produits_alternatives = get_produits_alternatives_disponibles($produit, $produit_generale_id, 6);
    if (!empty($produits_alternatives) && file_exists(__DIR__ . '/models/model_produits_avis.php')) {
        require_once __DIR__ . '/models/model_produits_avis.php';
        if (function_exists('produits_avis_enrich_products')) {
            $produits_alternatives = produits_avis_enrich_products($produits_alternatives);
        }
    }
}

$produit_user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if (file_exists(__DIR__ . '/models/model_produits_avis.php')) {
    require_once __DIR__ . '/models/model_produits_avis.php';
    if (function_exists('produits_avis_enrich_products')) {
        $enriched = produits_avis_enrich_products([$produit]);
        if (!empty($enriched[0])) {
            $produit = $enriched[0];
        }
        if (!empty($produits_similaires)) {
            $produits_similaires = produits_avis_enrich_products($produits_similaires);
        }
    }
}

$produit_boutique_nom = produit_public_boutique_label($produit);
$produit_boutique_slug = trim((string) ($produit['vendeur_boutique_slug'] ?? ''));
$produit_boutique_url = $produit_boutique_slug !== ''
    ? boutique_vitrine_entry_href($produit_boutique_slug)
    : '/produits.php';

$produit_boutique_logo_url = '';
$produit_boutique_logo_rel = trim((string) ($produit['vendeur_boutique_logo'] ?? ''));
if ($produit_boutique_logo_rel !== '') {
    $produit_boutique_logo_url = '/upload/' . str_replace('\\', '/', $produit_boutique_logo_rel);
}

$produit_boutique_cert_niveau = null;
if (file_exists(__DIR__ . '/models/model_vendeur_certification.php') && !empty($produit['admin_id'])) {
    require_once __DIR__ . '/models/model_vendeur_certification.php';
    $produit_boutique_cert_niveau = vendeur_certification_get_niveau_actif((int) $produit['admin_id']);
}

$produit_boutique_admin_id = (int) ($produit['admin_id'] ?? 0);
$produit_boutique_abonne = false;
$produit_boutique_peut_abonner = ($produit_boutique_admin_id > 0 && $produit_boutique_slug !== '');
if ($produit_boutique_peut_abonner && !empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/models/model_boutique_abonnements.php';
    $produit_boutique_abonne = boutique_abonnement_is_subscribed((int) $_SESSION['user_id'], $produit_boutique_admin_id);
}
$produit_abonnement_redirect = '/produit.php?id=' . (int) $produit_id;
$produit_peut_negocier = ($produit_boutique_admin_id > 0 && produit_prix_negociable($produit));
if (!function_exists('app_url')) {
    require_once __DIR__ . '/includes/app_base.php';
}
$produit_messages_href = app_url('/user/messages.php?produit_id=' . (int) $produit_id . '&negocier=1');
$produit_negociation_login_redirect = app_url('/choix-connexion.php?redirect=' . rawurlencode($produit_messages_href));

$taille_options = produit_client_taille_options($produit);
$produit_tailles_sous_image = !empty($taille_options);

require_once __DIR__ . '/includes/produit_description_excerpt.php';
$produit_desc_excerpt = produit_description_prepare_excerpt((string) ($produit['description'] ?? ''));

// Inclusion du fichier de connexion à la BDD (pour les autres fonctionnalités si nécessaire)
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';
$base = get_site_base_url();
$seo_title = $produit['nom'] . ' — ' . SITE_BRAND_NAME . ' | ' . $produit_boutique_nom;
$desc = !empty($produit['description'])
    ? strip_tags($produit['description'])
    : $produit['nom'] . ' — en vente sur ' . SITE_BRAND_NAME . ' (boutique ' . $produit_boutique_nom . '). Marketplace Sénégal, achat en ligne.';
$seo_description = mb_substr($desc, 0, 160);
$seo_keywords = site_brand_seo_keywords_default() . ', ' . $produit['nom'] . ', ' . $produit_boutique_nom;
$seo_canonical = $base . '/produit.php?id=' . (int) $produit['id'];
$seo_og_type = 'product';
$img = !empty($produit['image_principale']) ? $produit['image_principale'] : '';
$seo_image = $img ? $base . '/upload/' . ltrim(str_replace('\\', '/', $img), '/') : $base . '/site-icons/icon-512.png';
/* Partage (WhatsApp, Facebook, Instagram…) : carte 1200×630 avec photo, prix et logo COLObanes */
require_once __DIR__ . '/includes/partage_image.php';
require_once __DIR__ . '/includes/product_share.php';
$seo_image = partage_image_url($produit, (string) $produit_boutique_nom);
$seo_image_largeur = 1200;
$seo_image_hauteur = 630;
$seo_og_title = $produit['nom'] . ' — ' . product_share_price_label($produit);
$seo_og_description = product_share_accroche($produit, (string) $produit_boutique_nom);
$seo_og_image_alt = $produit['nom'] . ' sur ' . SITE_BRAND_NAME;
require_once __DIR__ . '/includes/seo_structured_data.php';
$seo_json_ld_blocks = [
    seo_structured_data_product($produit, $produit_boutique_nom),
];
?>

<!DOCTYPE html>
<html lang="fr">

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
    <?php require_once __DIR__ . '/includes/ui_font_awesome.php'; ?>
    <?php site_perf_emit_resource_hints(); ?>
    <?php
    $produit_css_v = asset_version_query();
    site_perf_preload_stylesheet(app_url('/css/variables.css' . $produit_css_v));
    site_perf_preload_stylesheet(app_url('/css/style.css' . $produit_css_v));
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/variables.css' . $produit_css_v), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/style.css' . $produit_css_v), ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    site_perf_stylesheet_deferred(app_url('/css/a_style.css' . $produit_css_v));
    site_perf_stylesheet_deferred(app_url('/css/product-cards.css' . $produit_css_v));
    site_perf_stylesheet_deferred(app_url('/css/vendor-cert-ribbon.css' . $produit_css_v));
    site_perf_stylesheet_deferred(app_url('/css/mp-category-page.css' . $produit_css_v));
    site_perf_stylesheet_deferred(app_url('/css/prix-negociation.css' . $produit_css_v));
    site_perf_stylesheet_deferred(app_url('/css/product-share.css' . $produit_css_v));
    site_perf_stylesheet_deferred(app_url('/css/produit-alternatives-alertes.css' . $produit_css_v));
    ?>
    <style>
        /* Styles pour la page produit - Palette gourmande */
        body {
            background: transparent;
        }

        .produit-detail-container {
            width: 100%;
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
            padding-bottom: env(safe-area-inset-bottom, 20px);
            box-sizing: border-box;
        }

        .produit-detail-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 40px;
            width: 100%;
        }

        .produit-image-section {
            position: relative;
            flex: 1 1 100%;
            min-width: 0;
            max-width: 100%;
        }

        @media (min-width: 769px) {
            .produit-image-section {
                flex: 1 1 calc(50% - 15px);
                max-width: calc(50% - 15px);
            }
        }

        /* Galerie : miniatures en colonne à gauche, image principale à droite */
        .produit-gallery-layout {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            gap: 12px;
            width: 100%;
            max-width: 100%;
        }

        .produit-gallery-main {
            position: relative;
            flex: 1 1 auto;
            min-width: 0;
            margin-bottom: 0;
            max-width: 100%;
            overflow: hidden;
        }

        .produit-tailles-sous-image {
            margin-top: 14px;
            padding: 12px 4px 4px;
            width: 100%;
        }

        .produit-tailles-sous-image__label {
            margin: 0 0 10px;
            font-size: 14px;
            font-weight: 600;
            color: var(--texte-fonce, #0d0d0d);
        }

        .produit-tailles-sous-image__label .required {
            color: var(--accent-promo, #ff6b35);
        }

        /* Sélecteurs doublés : priment sur .options-list-select (grille) et .option-swatch-select (pleine largeur) */
        .produit-tailles-sous-image .produit-tailles-sous-image__chips.options-list-select {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-start;
            grid-template-columns: none;
        }

        .produit-tailles-sous-image .produit-tailles-sous-image__chip.option-swatch-select {
            position: relative;
            display: inline-flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            width: auto;
            min-width: 48px;
            height: 40px;
            min-height: 0;
            margin: 0;
            padding: 0 14px;
            border-radius: 10px;
            border: 1px solid #d9dee7;
            background: #fff;
            box-shadow: none;
            font-size: 14px;
            font-weight: 600;
            color: var(--texte-fonce, #0d0d0d);
            cursor: pointer;
            user-select: none;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        .produit-tailles-sous-image .produit-tailles-sous-image__chip.option-swatch-select:hover {
            border-color: var(--couleur-dominante, #3564a6);
        }

        .produit-tailles-sous-image .produit-tailles-sous-image__chip .option-swatch-text {
            display: inline;
            width: auto;
            font-size: inherit;
            font-weight: inherit;
            color: inherit;
        }

        .produit-tailles-sous-image__chip input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
            pointer-events: none;
        }

        .produit-tailles-sous-image .produit-tailles-sous-image__chip.selected,
        .produit-tailles-sous-image .produit-tailles-sous-image__chip:has(input:checked) {
            color: #fff;
            background: var(--couleur-dominante, #3564a6);
            border-color: var(--couleur-dominante, #3564a6);
        }

        .produit-tailles-sous-image__err {
            margin: 0 0 8px;
            font-size: 13px;
            font-weight: 600;
            color: #e85a2a;
        }

        .produit-tailles-sous-image.is-invalid .produit-tailles-sous-image__chips {
            outline: 2px solid rgba(232, 90, 42, 0.25);
            border-radius: 12px;
            padding: 6px;
        }

        .produit-gallery-main > .pshare {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 12;
        }

        .produit-gallery-main > .mp-favori {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 13;
        }

        .produit-gallery-main > .mp-favori .mp-favori-btn {
            width: 40px;
            height: 40px;
            font-size: 16px;
            box-shadow: 0 4px 14px rgba(13, 13, 13, 0.18);
        }

        .produit-gallery-main:has(.mp-favori) > .pshare {
            right: 58px;
        }

        .produit-gallery-main > .pshare .pshare__toggle {
            width: 40px;
            height: 40px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 4px 14px rgba(13, 13, 13, 0.18);
        }

        @media (max-width: 640px) {
            .produit-gallery-main > .pshare {
                top: 10px;
                right: 10px;
            }

            .produit-gallery-main > .mp-favori {
                top: 10px;
                right: 10px;
            }

            .produit-gallery-main > .mp-favori .mp-favori-btn {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }

            .produit-gallery-main:has(.mp-favori) > .pshare {
                right: 52px;
            }

            .produit-gallery-main > .pshare .pshare__toggle {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }
        }

        .produit-gallery-thumbs {
            --thumb-size: 70px;
            --thumb-gap: 10px;
            --thumb-visible: 4;
            display: flex;
            flex: 0 0 auto;
            align-self: flex-start;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 0;
            width: auto;
            max-width: none;
        }

        .gallery-thumbs-nav {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .gallery-nav {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--border-input);
            background: var(--blanc);
            color: var(--couleur-dominante);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s;
        }

        .gallery-nav:hover:not(:disabled) {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border-color: var(--couleur-dominante);
        }

        .gallery-nav:disabled {
            opacity: 0.35;
            cursor: default;
        }

        .gallery-thumbs-nav[hidden] {
            display: none;
        }

        /* Fenêtre de 4 miniatures : les suivantes défilent via les flèches */
        .gallery-thumbs-list {
            display: flex;
            flex: 0 0 auto;
            flex-direction: column;
            gap: var(--thumb-gap);
            box-sizing: border-box;
            height: calc(
                var(--thumb-visible) * var(--thumb-size) +
                    (var(--thumb-visible) - 1) * var(--thumb-gap)
            );
            padding: 0;
            overflow-x: hidden;
            overflow-y: auto;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .gallery-thumbs-list::-webkit-scrollbar {
            width: 0;
            height: 0;
        }

        .gallery-thumb {
            flex-shrink: 0;
            box-sizing: border-box;
            width: var(--thumb-size, 70px);
            height: var(--thumb-size, 70px);
            padding: 0;
            border: 3px solid transparent;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            background: var(--blanc-neige);
            transition: all 0.3s;
        }

        .gallery-thumb img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .gallery-thumb:hover {
            border-color: var(--border-input);
        }

        .gallery-thumb.active {
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 2px var(--focus-ring);
        }

        .produit-image-main {
            width: 100%;
            max-width: 100%;
            height: 400px;
            object-fit: contain;
            object-position: center;
            border-radius: 16px;
            border: 2px solid var(--border-input);
            background: var(--blanc-casse);
            box-shadow: var(--ombre-douce);
        }

        /* Bandeau boutique — une seule ligne compacte : logo · nom + badge certifié · Suivre · Boutique */
        .produit-detail-container > .produit-boutique-rail {
            width: 100%;
            margin-bottom: 14px;
            padding: 0;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid rgba(53, 100, 166, 0.12);
            box-shadow: none;
            box-sizing: border-box;
        }

        .produit-boutique-rail__inner {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 10px 8px 8px;
        }

        .produit-boutique-rail__left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1 1 auto;
            min-width: 0;
        }

        .produit-boutique-rail__icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--couleur-dominante, #3564a6);
            color: #fff;
            font-size: 15px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .produit-boutique-rail__icon--logo {
            background: #ffffff;
            border: 1px solid rgba(53, 100, 166, 0.12);
        }

        .produit-boutique-rail__icon-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .produit-boutique-rail__nom {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
            margin: 0;
            font-family: var(--font-titres);
            font-size: 14.5px;
            font-weight: 700;
            line-height: 1.2;
            color: var(--titres);
        }

        .produit-boutique-rail__nom-text {
            min-width: 0;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        /* Badge certifié : petite pastille à côté du nom, sans texte */
        .produit-boutique-rail__nom .produit-boutique-rail__cert {
            display: inline-flex;
            flex-shrink: 0;
            margin-left: 0; /* vendor-cert-ribbon.css le poussait à droite */
        }

        /* Coche « vérifié » colorée selon le niveau */
        .produit-boutique-rail__cert {
            font-size: 15px;
            line-height: 1;
            color: #1d9bf0;
        }

        .produit-boutique-rail__cert--vip {
            color: #d4a017;
        }

        .produit-boutique-rail__cert--premium {
            color: #7c3aed;
        }

        .produit-boutique-rail__actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .produit-boutique-rail__subscribe-form {
            margin: 0;
            display: flex;
        }

        .produit-boutique-rail__subscribe,
        .produit-boutique-rail__cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 34px;
            padding: 0 12px;
            border-radius: 999px;
            font-family: var(--font-corps);
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
            text-decoration: none;
            cursor: pointer;
            box-sizing: border-box;
            transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
        }

        .produit-boutique-rail__subscribe {
            border: 1.5px solid var(--orange);
            background: transparent;
            color: var(--orange);
        }

        .produit-boutique-rail__subscribe i,
        .produit-boutique-rail__cta i {
            font-size: 11px;
        }

        .produit-boutique-rail__subscribe:hover {
            background: var(--orange-pale);
        }

        .produit-boutique-rail__subscribe--subscribed {
            border-color: rgba(53, 100, 166, 0.25);
            background: rgba(53, 100, 166, 0.08);
            color: var(--couleur-dominante);
        }

        .produit-boutique-rail__subscribe--subscribed:hover {
            background: rgba(53, 100, 166, 0.14);
        }

        .produit-boutique-rail__cta {
            border: 1.5px solid var(--couleur-dominante);
            background: var(--couleur-dominante);
            color: var(--texte-clair, #fff);
        }

        .produit-boutique-rail__cta:hover {
            border-color: var(--couleur-dominante-hover);
            background: var(--couleur-dominante-hover);
            color: var(--texte-clair, #fff);
        }

        .produit-boutique-rail__cta:focus-visible,
        .produit-boutique-rail__subscribe:focus-visible {
            outline: 3px solid var(--focus-ring);
            outline-offset: 2px;
        }

        /* Téléphone : même ligne, boutons encore plus serrés */
        @media (max-width: 480px) {
            .produit-detail-container > .produit-boutique-rail {
                margin-bottom: 12px;
                border-radius: 12px;
            }

            .produit-boutique-rail__inner {
                gap: 8px;
                padding: 6px 6px 6px 6px;
            }

            .produit-boutique-rail__left {
                gap: 8px;
            }

            .produit-boutique-rail__icon {
                width: 32px;
                height: 32px;
                border-radius: 9px;
                font-size: 13px;
            }

            .produit-boutique-rail__nom {
                font-size: 13.5px;
            }

            .produit-boutique-rail__subscribe,
            .produit-boutique-rail__cta {
                min-height: 32px;
                padding: 0 10px;
                font-size: 12.5px;
            }
        }

        .produit-info-section {
            display: flex;
            flex-direction: column;
            flex: 1 1 100%;
            min-width: 0;
            max-width: 100%;
        }

        @media (min-width: 769px) {
            .produit-info-section {
                flex: 1 1 calc(50% - 15px);
                max-width: calc(50% - 15px);
            }
        }

        .produit-nom {
            font-size: 24px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 10px;
            line-height: 1.3;
            font-family: var(--font-titres);
        }

        .produit-categorie {
            display: inline-block;
            font-size: 12px;
            color: var(--couleur-dominante);
            background: var(--bleu-pale);
            padding: 6px 12px;
            border-radius: 20px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .produit-prix-section {
            margin-bottom: 10px;
            padding: 18px;
            background: var(--blanc);
            border-radius: 12px;
            border-left: 4px solid var(--couleur-dominante);
        }

        .prix-principal {
            font-size: 26px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 5px;
        }

        .prix-original {
            font-size: 18px;
            color: var(--texte-fonce);
            text-decoration: line-through;
            margin-right: 8px;
            opacity: 0.7;
        }

        .prix-promo {
            font-size: 22px;
            color: var(--accent-promo);
            font-weight: 600;
        }

        .promo-badge {
            display: inline-block;
            background: var(--accent-promo);
            color: var(--texte-clair);
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            margin-left: 8px;
        }

        .produit-stock-info {
            margin-bottom: 15px;
            padding: 14px;
            background: var(--blanc);
            border-radius: 12px;
            border: 1px solid var(--border-input);
        }

        .stock-item {
            font-size: 13px;
            color: var(--texte-fonce);
            margin-bottom: 6px;
        }

        .stock-item strong {
            color: var(--couleur-dominante);
            font-weight: 600;
            min-width: 90px;
            display: inline-block;
        }

        .stock-value {
            color: var(--couleur-dominante);
            font-weight: 700;
        }

        .couleurs-swatches-display {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-left: 4px;
        }

        .couleur-swatch-display {
            display: inline-block;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid rgba(0, 0, 0, 0.2);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
            cursor: default;
        }

        .produit-options-section {
            margin-bottom: 20px;
            padding: 20px 22px;
            background: var(--blanc);
            border-radius: 14px;
            border: 1px solid rgba(53, 100, 166, 0.12);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            box-shadow: 0 4px 18px rgba(53, 100, 166, 0.06);
        }

        .produit-options-section.produit-section-bg {
            padding: 16px 18px;
            margin-bottom: 20px;
        }

        .option-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 16px;
        }

        .option-group:last-child {
            margin-bottom: 0;
        }

        .option-label {
            display: block;
            font-size: 12.8px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--gris-moyen, #737373);
            margin: 0;
        }

        .produit-option-chip {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            gap: 6px;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1.5px solid rgba(53, 100, 166, 0.16);
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            box-sizing: border-box;
        }

        .produit-option-chip--locked {
            width: 100%;
            min-width: 0;
            flex-direction: row;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 14px;
            padding: 14px 16px;
        }

        .options-list-select:has(> .produit-option-chip--locked) {
            grid-template-columns: 1fr;
        }

        .produit-option-chip__main {
            font-size: 16.8px;
            font-weight: 700;
            color: var(--titres);
            line-height: 1.35;
            word-break: break-word;
        }

        .produit-option-chip__surcout {
            font-size: 14.4px;
            font-weight: 700;
            color: var(--orange, #ff6b35);
            background: rgba(255, 107, 53, 0.1);
            padding: 6px 12px;
            border-radius: 999px;
            line-height: 1.3;
            max-width: 100%;
        }

        .produit-option-chip__hint {
            font-size: 15.2px;
            font-weight: 600;
            color: var(--couleur-dominante);
            white-space: nowrap;
        }

        .couleurs-swatches-select {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            width: 100%;
            max-width: 100%;
        }

        .couleur-swatch-select {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: #f5f5f5;
            border-radius: 20px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: all 0.2s;
        }

        .couleur-swatch-select:hover {
            border-color: var(--border-input);
            background: var(--blanc);
        }

        .couleur-swatch-select:has(input:checked) {
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 2px var(--focus-ring);
            background: var(--blanc);
        }

        .couleur-swatch-select input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .couleur-swatch-select .swatch-preview {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid rgba(0, 0, 0, 0.2);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
        }

        .couleur-swatch-select .swatch-text {
            font-size: 13px;
            color: var(--texte-fonce);
        }

        .option-select {
            padding: 10px 14px;
            border: 2px solid var(--border-input);
            border-radius: 8px;
            font-size: 14px;
            min-width: 140px;
            background: var(--blanc);
            cursor: pointer;
        }

        .option-select:focus {
            outline: none;
            border-color: var(--couleur-dominante);
        }

        .option-value-display {
            font-size: 14px;
            color: var(--texte-fonce);
            font-weight: 500;
        }

        .produit-description {
            margin-bottom: 24px;
            padding: 24px;
            background: var(--blanc);
            border-radius: 16px;
            line-height: 1.7;
            color: var(--texte-fonce);
            font-size: 15px;
            box-shadow: var(--ombre-douce);
            border: 1px solid var(--border-input);
            border-left: 4px solid var(--couleur-dominante);
        }

        .produit-description h3 {
            font-size: 18px;
            color: var(--couleur-dominante);
            margin-bottom: 14px;
            font-weight: 600;
        }

        .produit-description p,
        .produit-desc-full,
        .produit-desc-preview {
            margin: 0;
            color: var(--texte-fonce);
            line-height: 1.7;
        }

        .produit-desc-wrap {
            margin: 0;
        }

        .produit-desc-preview {
            font-size: inherit;
        }

        .produit-desc-excerpt,
        .produit-desc-ellipsis,
        .produit-desc-more {
            display: inline;
        }

        .produit-desc-ellipsis {
            color: var(--gris-moyen, #737373);
        }

        .produit-desc-more {
            margin: 0 0 0 0.15em;
            padding: 0;
            border: none;
            background: none;
            font: inherit;
            font-size: inherit;
            font-weight: 600;
            color: var(--couleur-dominante);
            cursor: pointer;
            text-decoration: underline;
            text-underline-offset: 2px;
            vertical-align: baseline;
            white-space: nowrap;
        }

        .produit-desc-more:hover,
        .produit-desc-more:focus-visible {
            color: var(--bleu-fonce, #2d5690);
            outline: none;
        }

        /* Aperçu : une seule ligne coupée par « … », puis un petit « voir plus » */
        .produit-desc-preview {
            display: flex;
            align-items: baseline;
            gap: 6px;
            min-width: 0;
        }

        .produit-desc-preview .produit-desc-excerpt {
            display: block;
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .produit-desc-preview .produit-desc-more {
            flex: 0 0 auto;
            font-size: 13px;
            text-decoration: none;
        }

        .produit-desc-preview .produit-desc-more:hover,
        .produit-desc-preview .produit-desc-more:focus-visible {
            text-decoration: underline;
        }

        .produit-desc-expanded[hidden],
        .produit-desc-preview[hidden] {
            display: none !important;
        }

        .produit-desc-expanded:not([hidden]) {
            display: block;
            margin-top: 0;
        }

        .produit-desc-wrap.is-expanded .produit-desc-preview {
            display: none !important;
        }

        .produit-desc-wrap.is-expanded .produit-desc-expanded {
            display: block !important;
        }

        .quantite-section {
            margin-bottom: 20px;
        }

        .quantite-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 8px;
            display: block;
        }

        .quantite-controls {
            display: flex;
            align-items: stretch;
            gap: clamp(8px, 2vw, 12px);
            margin-bottom: 15px;
            width: 100%;
            max-width: 100%;
            flex-wrap: nowrap;
            min-width: 0;
        }

        .quantite-input-wrapper {
            display: flex;
            align-items: center;
            border: 2px solid var(--border-input);
            border-radius: 12px;
            overflow: hidden;
            flex: 0 1 auto;
            min-width: 0;
            height: 48px;
        }

        .quantite-btn {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border: none;
            width: 38px;
            height: 40px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantite-btn:hover {
            background: var(--couleur-dominante-hover);
        }

        .quantite-input {
            width: 60px;
            height: 38px;
            border: none;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            color: var(--titres);
        }

        .prix-total-section {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 8px;
            height: 48px;
            min-height: 48px;
            padding: 0 16px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            border-radius: 12px;
            margin-bottom: 0;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-sizing: border-box;
        }

        .prix-total-label {
            font-size: 13px;
            margin-bottom: 0;
            opacity: 0.95;
            white-space: nowrap;
        }

        .prix-total-value {
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .produit-add-form {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .produit-checkout-row {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 12px;
            width: 100%;
        }

        .produit-checkout-row .prix-total-section {
            flex: 1 1 auto;
            min-width: 0;
            margin-bottom: 0;
        }

        .btn-add-panier {
            flex: 1 1 auto;
            width: auto;
            min-width: 0;
            height: 48px;
            min-height: 48px;
            padding: 0 18px;
            background: var(--orange);
            color: var(--texte-clair);
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-sizing: border-box;
            box-shadow: 0 4px 18px rgba(255, 107, 53, 0.35);
            white-space: nowrap;
        }

        .btn-add-panier:hover {
            background: var(--orange-fonce);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(255, 107, 53, 0.4);
        }

        .btn-add-panier:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .message {
            padding: 15px 20px;
            padding-right: 45px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            position: relative;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.success {
            background-color: var(--success-bg);
            color: var(--bleu-fonce);
            border: 1px solid var(--bleu);
        }

        .message.error {
            background-color: var(--error-bg);
            color: var(--titres);
            border: 1px solid var(--error-border);
        }

        .message-close {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 20px;
            color: inherit;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .message-close:hover {
            opacity: 1;
            background-color: rgba(0, 0, 0, 0.1);
        }

        .message.fade-out {
            animation: fadeOut 0.3s ease-out forwards;
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(-10px);
                max-height: 0;
                margin-bottom: 0;
                padding-top: 0;
                padding-bottom: 0;
            }
        }

        .produits-similaires {
            margin-top: 60px;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }

        .produits-similaires h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 30px;
            text-align: center;
            font-family: var(--font-titres);
        }

        .produit-connect-cta {
            padding: 24px;
            background: var(--blanc);
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border-input);
        }

        .produit-connect-cta p {
            margin-bottom: 18px;
            color: var(--texte-fonce);
        }

        .btn-connect-produit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: var(--couleur-dominante);
            color: var(--texte-clair);
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: var(--ombre-promo);
        }

        .btn-connect-produit:hover {
            background: var(--couleur-dominante-hover);
            transform: translateY(-2px);
            box-shadow: var(--ombre-gourmande);
            color: var(--texte-clair);
        }

        /* Responsive - Tablette */
        @media (max-width: 992px) {
            .produit-detail-wrapper {
                gap: 24px;
            }

            .produit-image-section,
            .produit-info-section {
                flex: 1 1 100%;
                max-width: 100%;
            }

            .produit-image-main {
                height: 360px;
            }

            .produit-nom {
                font-size: 22px;
            }

            .produit-prix-section {
                padding: 14px 16px;
            }

            .prix-principal {
                font-size: 24px;
            }

            .produit-options-section {
                padding: 14px 16px;
            }

            .produit-description {
                padding: 18px 20px;
            }

            .produits-similaires {
                margin-top: 40px;
            }

            .produits-similaires h2 {
                font-size: 24px;
                margin-bottom: 24px;
            }

            .produit-checkout-row {
                flex-direction: row;
                align-items: center;
                gap: 12px;
            }

            .produit-checkout-row .prix-total-section {
                flex: 1 1 auto;
                min-width: 0;
                margin-bottom: 0;
                height: 48px;
                min-height: 48px;
            }

            .produit-checkout-row .btn-add-panier {
                flex: 1 1 auto;
                width: auto;
                min-width: 0;
                height: 48px;
                min-height: 48px;
            }
        }

        /* Responsive - Mobile */
        @media (max-width: 768px) {
            .produit-detail-wrapper {
                flex-direction: column;
                gap: 20px;
                margin-bottom: 30px;
            }

            .produit-image-section,
            .produit-info-section {
                flex: 1 1 100%;
                max-width: 100%;
            }

            .produit-image-main {
                height: 280px;
                border-radius: 12px;
            }

            .produit-gallery-layout {
                gap: 10px;
            }

            .produit-gallery-thumbs {
                --thumb-size: 56px;
                --thumb-gap: 8px;
            }

            .gallery-nav {
                width: 36px;
                height: 36px;
                font-size: 12px;
            }

            .produit-nom {
                font-size: 19px;
                line-height: 1.35;
                margin-bottom: 8px;
            }

            .produit-prix-section {
                padding: 12px 14px;
                margin-bottom: 12px;
            }

            .prix-principal {
                font-size: 20px;
            }

            .prix-original {
                font-size: 15px;
            }

            .prix-promo {
                font-size: 18px;
            }

            .promo-badge {
                font-size: 11px;
                padding: 3px 8px;
            }

            .produit-description {
                padding: 16px 18px;
                margin-bottom: 18px;
                font-size: 14px;
            }

            .produit-description h3 {
                font-size: 16px;
                margin-bottom: 10px;
            }

            .produit-section-bg {
                padding: 14px 16px;
                margin-bottom: 16px;
            }

            .variante-option {
                flex: 0 0 140px;
                min-width: 140px;
                padding: 10px 12px;
            }

            .variante-option .variante-thumb {
                width: 42px;
                height: 42px;
            }

            .variante-option .variante-nom {
                font-size: 12px;
            }

            .variante-option .variante-prix {
                font-size: 11px;
            }

            .variantes-arrow {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }

            .produit-options-section .quantite-label {
                font-size: 13px;
            }

            .option-group {
                margin-bottom: 12px;
            }

            .options-list-select {
                gap: 8px;
            }

            .option-label {
                font-size: 12px;
                margin-bottom: 6px;
            }

            .couleur-swatch-select {
                padding: 5px 8px;
            }

            .couleur-swatch-select .swatch-preview {
                width: 24px;
                height: 24px;
            }

            .couleur-swatch-select .swatch-text {
                font-size: 12px;
            }

            .option-swatch-select {
                padding: 10px 12px;
            }

            .produit-option-chip {
                padding: 10px 12px;
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }

            .produit-option-chip__surcout,
            .produit-option-chip__hint {
                white-space: normal;
            }

            .quantite-section {
                margin-bottom: 16px;
            }

            .quantite-label {
                font-size: 13px;
            }

            .quantite-input-wrapper {
                border-radius: 10px;
            }

            .quantite-btn {
                width: 44px;
                height: 44px;
                font-size: 18px;
            }

            .quantite-input {
                width: 50px;
                height: 42px;
                font-size: 15px;
            }

            .produit-checkout-row {
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }

            .produit-checkout-row .prix-total-section {
                flex: 1 1 auto;
                min-width: 0;
                margin-bottom: 0;
                padding: 0 12px;
                height: 48px;
                min-height: 48px;
            }

            .prix-total-label {
                font-size: 11px;
                margin-bottom: 0;
            }

            .prix-total-value {
                font-size: clamp(14px, 3.8vw, 17px);
                line-height: 1.2;
            }

            .produit-checkout-row .btn-add-panier {
                flex: 1 1 auto;
                width: auto;
                min-width: 0;
                height: 48px;
                min-height: 48px;
                padding: 0 12px;
                font-size: 13px;
                border-radius: 12px;
            }

            .produit-detail-container {
                margin: 12px auto;
                padding: 0 12px;
            }

            .produits-similaires {
                margin-top: 36px;
                padding: 0 4px;
            }

            .produits-similaires h2 {
                font-size: 20px;
                margin-bottom: 20px;
            }

            .produits-similaires .produit_vedetes .articles,
            .produits-similaires .carousel11 {
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
                width: 100%;
            }

            .produits-similaires .carousel {
                flex: 1 1 calc(50% - 8px);
                min-width: 0;
                max-width: calc(50% - 8px);
            }

            .message {
                padding: 12px 16px;
                padding-right: 40px;
                font-size: 14px;
            }
        }

        /* Responsive - Petit mobile */
        @media (max-width: 480px) {
            .produit-detail-container {
                padding: 0 10px;
                margin: 10px auto;
            }

            .produit-image-main {
                height: 240px;
            }

            .produit-gallery-layout {
                gap: 8px;
            }

            .produit-gallery-thumbs {
                --thumb-size: 48px;
                --thumb-gap: 7px;
            }

            .gallery-nav {
                width: 32px;
                height: 32px;
                font-size: 11px;
            }

            .produit-nom {
                font-size: 19px;
                line-height: 1.3;
            }

            .prix-principal {
                font-size: 22px;
            }

            .prix-original {
                font-size: 14px;
            }

            .prix-promo {
                font-size: 16px;
            }

            .variante-option {
                flex: 0 0 120px;
                min-width: 120px;
                padding: 8px 10px;
            }

            .variante-option .variante-thumb {
                width: 36px;
                height: 36px;
            }

            .variante-option .variante-nom {
                font-size: 11px;
            }

            .variante-option .variante-prix {
                font-size: 10px;
            }

            .option-swatch-select {
                min-width: 65px;
                padding: 6px 10px;
            }

            .option-swatch-select .option-swatch-text {
                font-size: 11px;
                line-height: 1.3;
            }

            .quantite-btn {
                width: 40px;
                height: 42px;
            }

            .quantite-input {
                width: 44px;
            }

            .prix-total-value {
                font-size: 16px;
            }

            .produit-checkout-row .btn-add-panier {
                min-width: 0;
                padding: 0 10px;
                font-size: 12px;
                height: 48px;
                min-height: 48px;
            }

            .produits-similaires h2 {
                font-size: 18px;
                margin-bottom: 16px;
            }

            .produits-similaires .produit_vedetes .articles,
            .produits-similaires .carousel11 {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                width: 100%;
            }

            .produits-similaires .carousel {
                flex: 1 1 calc(50% - 6px);
                min-width: 0;
                max-width: calc(50% - 6px);
            }
        }

        /* Très petit écran : produits similaires en 1 colonne */
        @media (max-width: 360px) {
            .produits-similaires .carousel {
                flex: 1 1 100%;
                max-width: 100%;
            }
        }

        .produit-section-bg {
            background: var(--blanc);
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid var(--border-input);
            margin-bottom: 20px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .produit-variantes-section {
            margin: 0 0 16px;
            padding: 0;
            background: transparent;
            border: none;
            box-shadow: none;
        }

        .variantes-carousel-wrapper {
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 100%;
        }

        .variantes-scroll-container {
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
            width: 100%;
            max-width: 100%;
        }

        .variantes-scroll-container::-webkit-scrollbar {
            display: none;
        }

        .variantes-select {
            display: flex;
            flex-wrap: nowrap;
            gap: 10px;
            padding: 4px 0;
            width: 100%;
            min-width: 0;
        }

        .variante-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            flex: 0 0 calc(50% - 5px);
            min-width: 0;
            box-sizing: border-box;
        }

        @media (min-width: 768px) {
            .variante-option {
                flex: 0 0 calc(33.333% - 7px);
            }
        }

        @media (min-width: 1024px) {
            .variante-option {
                flex: 0 0 calc(25% - 8px);
            }
        }

        .variantes-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid var(--couleur-dominante);
            background: var(--blanc);
            color: var(--couleur-dominante);
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .variantes-arrow:hover {
            background: var(--couleur-dominante);
            color: var(--texte-clair);
        }

        .variantes-arrow.visible {
            display: flex;
        }

        .variantes-arrow-left {
            left: 8px;
        }

        .variantes-arrow-right {
            right: 8px;
        }

        .variante-option:hover,
        .variante-option.selected {
            border-color: var(--couleur-dominante);
            background: var(--bleu-pale);
        }

        .variante-option .variante-thumb {
            width: 50px;
            height: 50px;
            object-fit: contain;
            object-position: center;
            border-radius: 6px;
            margin-bottom: 6px;
        }

        .variante-option .variante-nom {
            font-size: 13px;
            font-weight: 600;
            color: var(--titres);
        }

        .variante-option .variante-prix {
            font-size: 12px;
            color: var(--couleur-dominante);
        }

        .options-list-select {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            width: 100%;
            max-width: 100%;
        }

        .option-swatch-select {
            display: flex;
            align-items: stretch;
            justify-content: center;
            padding: 12px 14px;
            background: var(--blanc);
            border-radius: 12px;
            border: 1.5px solid rgba(53, 100, 166, 0.14);
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
            min-width: 0;
            width: 100%;
            box-sizing: border-box;
        }

        .option-swatch-select:hover {
            border-color: var(--border-input);
        }

        .option-swatch-select.selected,
        .option-swatch-select:has(input:checked) {
            border-color: var(--couleur-dominante);
            background: var(--bleu-pale);
        }

        .option-swatch-select input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .option-swatch-select .option-swatch-text {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            gap: 6px;
            width: 100%;
            min-width: 0;
            text-align: left;
        }

        .option-swatch-select .option-swatch-text .produit-option-chip__main,
        .option-swatch-select .option-swatch-text .produit-option-chip__surcout,
        .option-swatch-select .option-swatch-text .produit-option-chip__hint {
            align-self: flex-start;
        }

        @media (max-width: 900px) {
            .options-list-select {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 480px) {
            .options-list-select {
                grid-template-columns: 1fr;
            }
        }

        .produit_vedetes,
        .produit_vedetes .articles,
        .produit_vedetes .carousel11 {
            width: 100%;
            max-width: 100%;
        }

        /* ===== Allègement visuel fiche produit : moins de couleurs, un seul accent par action ===== */
        .produit-detail-container .produit-image-main {
            background: #fff;
            border: 1px solid #eceff3;
            box-shadow: none;
        }

        .produit-detail-container .produit-prix-section {
            border-left: 0;
            padding-left: 0;
            padding-right: 0;
            background: transparent;
        }

        .produit-detail-container .prix-principal {
            color: var(--accent-promo, #ff6b35);
        }

        /* Sélecteur de quantité : blanc, contour fin, symboles bleus */
        .produit-detail-container .quantite-input-wrapper {
            border: 1px solid #dde2ea;
            border-radius: 12px;
            background: #fff;
            overflow: hidden;
        }

        .produit-detail-container .quantite-input-wrapper .quantite-btn {
            background: #fff;
            color: var(--couleur-dominante, #3564a6);
            font-weight: 600;
        }

        .produit-detail-container .quantite-input-wrapper .quantite-btn:hover {
            background: #f3f6fb;
        }

        /* Négocier : action secondaire en contour (vert conservé) */
        .produit-detail-container .btn-negocier-prix.btn-negocier-prix {
            background: #fff;
            color: #15803d;
            border: 1.5px solid #16a34a;
            box-shadow: none;
        }

        .produit-detail-container .btn-negocier-prix.btn-negocier-prix:hover {
            background: #f0fdf4;
            color: #15803d;
        }

        /* Prix total : simple information, plus un bouton bleu */
        .produit-detail-container .prix-total-section {
            background: #f6f7f9;
            border: 1px solid #eceff3;
            color: var(--titres, #0d0d0d);
            box-shadow: none;
        }

        .produit-detail-container .prix-total-section .prix-total-label {
            color: var(--gris-moyen, #737373);
            font-size: 12px;
        }

        .produit-detail-container .prix-total-section *:not(.prix-total-label) {
            color: var(--titres, #0d0d0d);
        }

        /* Ajouter au panier : seul bouton plein (action principale) */
        .produit-detail-container .btn-add-panier {
            box-shadow: none;
            font-size: 14px;
        }

        /* ---- Variantes : sélecteur compact type marketplace (vignettes qui passent à la ligne) ---- */
        .produit-variantes-section .variantes-heading {
            margin: 0 0 8px;
            font-size: 13px;
            color: var(--gris-moyen, #737373);
        }

        .produit-variantes-section .variantes-heading strong {
            color: var(--titres, #0d0d0d);
            font-weight: 700;
        }

        .produit-variantes-section .variantes-arrow {
            display: none !important;
        }

        .produit-variantes-section .variantes-carousel-wrapper,
        .produit-variantes-section .variantes-scroll-container {
            overflow: visible;
        }

        .produit-variantes-section .variantes-select {
            flex-wrap: wrap;
            gap: 8px;
            padding: 0;
        }

        .produit-variantes-section .variante-option {
            position: relative;
            flex: 0 0 92px;
            width: 92px;
            min-width: 0;
            padding: 6px 6px 7px;
            border: 1px solid #d5d9d9;
            border-radius: 8px;
            background: #fff;
            align-items: stretch;
            text-align: center;
            gap: 0;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .produit-variantes-section .variante-option:hover {
            border-color: #a2a6ac;
            background: #fff;
        }

        .produit-variantes-section .variante-option.selected {
            border-color: var(--couleur-dominante, #3564a6);
            background: #fff;
            box-shadow: 0 0 0 1px var(--couleur-dominante, #3564a6), 0 0 0 4px rgba(53, 100, 166, 0.12);
        }

        /* Bouton radio masqué (accessible au clavier via le label) */
        .produit-variantes-section .variante-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
            pointer-events: none;
        }

        .produit-variantes-section .variante-option:has(input:focus-visible) {
            outline: 2px solid var(--couleur-dominante, #3564a6);
            outline-offset: 2px;
        }

        .produit-variantes-section .variante-option .variante-thumb {
            width: 100%;
            height: 56px;
            margin: 0 0 5px;
            border-radius: 5px;
            object-fit: contain;
            background: #f7f8f8;
        }

        .produit-variantes-section .variante-option .variante-nom {
            display: -webkit-box;
            overflow: hidden;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 1;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.3;
            color: var(--titres, #0d0d0d);
            word-break: break-word;
        }

        .produit-variantes-section .variante-option .variante-prix {
            margin-top: 1px;
            font-size: 12px;
            font-weight: 700;
            color: var(--titres, #0d0d0d);
            white-space: nowrap;
        }

        @media (max-width: 480px) {
            .produit-variantes-section .variante-option {
                flex-basis: calc((100% - 24px) / 4);
                width: auto;
                padding: 5px 4px 6px;
            }

            .produit-variantes-section .variante-option .variante-thumb {
                height: 48px;
            }

            .produit-variantes-section .variante-option .variante-nom,
            .produit-variantes-section .variante-option .variante-prix {
                font-size: 11px;
            }
        }
        /* ---- Vente en gros : échelle de prix (type Alibaba) ---- */
        .pvg {
            margin: 0 0 18px;
            border: 1px solid #ececf0;
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
        }

        .pvg__head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 6px 12px;
            padding: 12px 16px;
            background: #fafafb;
            border-bottom: 1px solid #ececf0;
        }

        .pvg__title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.01em;
            color: var(--titres, #0d0d0d);
        }

        .pvg__title i {
            color: var(--accent-promo, #ff6b35);
        }

        .pvg__best {
            padding: 3px 10px;
            border-radius: 999px;
            background: #ecfdf3;
            color: #067647;
            font-size: 12px;
            font-weight: 700;
        }

        .pvg__ladder {
            display: grid;
            grid-template-columns: repeat(var(--pvg-cols, 3), minmax(0, 1fr));
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .pvg__step {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 14px 16px 13px;
            transition: background 0.15s ease;
        }

        .pvg__step + .pvg__step {
            border-left: 1px solid #ececf0;
        }

        /* Palier atteint : barre colorée en haut + fond teinté */
        .pvg__step::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            background: transparent;
            transition: background 0.15s ease;
        }

        .pvg__step.is-active {
            background: #fff7f3;
        }

        .pvg__step.is-active::before {
            background: var(--accent-promo, #ff6b35);
        }

        .pvg__price {
            font-size: 19px;
            font-weight: 800;
            line-height: 1.1;
            color: var(--titres, #0d0d0d);
            white-space: nowrap;
        }

        .pvg__price small {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--gris-moyen, #737373);
        }

        .pvg__step.is-active .pvg__price {
            color: var(--accent-promo, #ff6b35);
        }

        .pvg__range {
            font-size: 12.5px;
            color: var(--gris-moyen, #737373);
            white-space: nowrap;
        }

        .pvg__save {
            align-self: flex-start;
            margin-top: 2px;
            padding: 1px 7px;
            border-radius: 6px;
            background: #ecfdf3;
            color: #067647;
            font-size: 11.5px;
            font-weight: 700;
        }

        .pvg__save--none {
            background: #f2f4f7;
            color: #667085;
            font-weight: 600;
        }

        .pvg__foot {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 6px 14px;
            padding: 10px 16px;
            border-top: 1px solid #ececf0;
            font-size: 12.5px;
        }

        .pvg__moq {
            color: var(--gris-moyen, #737373);
        }

        .pvg__moq i {
            margin-right: 4px;
        }

        .pvg__moq strong {
            color: var(--titres, #0d0d0d);
        }

        .pvg__hint {
            color: #b54708;
            font-weight: 600;
        }

        .pvg__hint.is-best {
            color: #067647;
        }

        .pvg__hint i {
            margin-right: 4px;
        }

        /* Téléphone : 2 colonnes, l'échelle reste lisible sans défilement */
        @media (max-width: 560px) {
            .pvg__ladder {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .pvg__step:nth-child(odd) {
                border-left: 0;
            }

            .pvg__step:nth-child(n + 3) {
                border-top: 1px solid #ececf0;
            }

            .pvg__price {
                font-size: 17px;
            }
        }

        /* ===== Fiche produit compacte (écran large, deux colonnes) =====
           L'image remplit sa colonne (cadre carré) et reste visible au défilement ; moins de vide entre les blocs. */
        @media (min-width: 993px) {
            .produit-detail-container .produit-detail-wrapper {
                gap: 28px;
                margin-bottom: 28px;
                align-items: flex-start;
            }
            /* Image limitée : colonne photo plus étroite (42 %), photo 420 px maximum */
            .produit-detail-container .produit-image-section {
                position: sticky;
                top: 16px;
                align-self: flex-start;
                flex: 0 0 42%;
                max-width: 42%;
            }
            .produit-detail-container .produit-info-section {
                flex: 1 1 0;
                min-width: 0;
                max-width: none;
            }
            .produit-detail-container .produit-image-main {
                height: auto;
                aspect-ratio: 1 / 1;
                max-height: 420px;
            }
            .produit-detail-container .produit-nom {
                margin-bottom: 4px;
            }
            .produit-detail-container .produit-prix-section {
                padding-top: 4px;
                padding-bottom: 8px;
                margin-bottom: 8px;
            }
            .produit-detail-container .pvg {
                margin-bottom: 12px;
            }
            .produit-detail-container .produit-description {
                margin-bottom: 12px;
                padding-top: 12px;
                padding-bottom: 12px;
            }
            .produit-detail-container .quantite-section {
                margin-bottom: 12px;
            }
            .produit-detail-container .quantite-controls {
                margin-bottom: 0;
            }
            .produit-detail-container .quantite-label {
                margin-bottom: 6px;
            }
        }
    </style>
</head>

<body>

    <?php include('nav_bar.php') ?>

    <div class="produit-detail-container">

        <aside class="produit-boutique-rail" aria-label="Boutique du vendeur">
            <div class="produit-boutique-rail__inner">
                <div class="produit-boutique-rail__left">
                    <div class="produit-boutique-rail__icon<?php echo $produit_boutique_logo_url !== '' ? ' produit-boutique-rail__icon--logo' : ''; ?>"
                        aria-hidden="true">
                        <?php if ($produit_boutique_logo_url !== ''): ?>
                        <img src="<?php echo htmlspecialchars($produit_boutique_logo_url, ENT_QUOTES, 'UTF-8'); ?>"
                            alt=""
                            class="produit-boutique-rail__icon-img"
                            width="52"
                            height="52"
                            loading="lazy"
                            decoding="async">
                        <?php else: ?>
                        <i class="fas fa-store" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                    <p class="produit-boutique-rail__nom">
                        <span class="produit-boutique-rail__nom-text"><?php echo htmlspecialchars($produit_boutique_nom); ?></span>
                        <?php if ($produit_boutique_cert_niveau): ?>
                        <?php $rail_cert_label = 'Boutique certifiée ' . ucfirst((string) $produit_boutique_cert_niveau); ?>
                        <span class="produit-boutique-rail__cert produit-boutique-rail__cert--<?php echo htmlspecialchars((string) $produit_boutique_cert_niveau, ENT_QUOTES, 'UTF-8'); ?>"
                            role="img" title="<?php echo htmlspecialchars($rail_cert_label, ENT_QUOTES, 'UTF-8'); ?>"
                            aria-label="<?php echo htmlspecialchars($rail_cert_label, ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fas fa-circle-check" aria-hidden="true"></i>
                        </span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="produit-boutique-rail__actions">
                    <?php if ($produit_boutique_peut_abonner): ?>
                        <?php if (!empty($_SESSION['user_id'])): ?>
                        <form class="produit-boutique-rail__subscribe-form" method="post"
                            action="/user/boutique-abonnement-action.php">
                            <?php echo site_csrf_field(); ?>
                            <input type="hidden" name="admin_id" value="<?php echo (int) $produit_boutique_admin_id; ?>">
                            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($produit_abonnement_redirect, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if ($produit_boutique_abonne): ?>
                            <input type="hidden" name="action" value="unsubscribe">
                            <button type="submit" class="produit-boutique-rail__subscribe produit-boutique-rail__subscribe--subscribed"
                                aria-label="Ne plus suivre <?php echo htmlspecialchars($produit_boutique_nom, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-check" aria-hidden="true"></i>
                                <span>Suivi</span>
                            </button>
                            <?php else: ?>
                            <input type="hidden" name="action" value="subscribe">
                            <button type="submit" class="produit-boutique-rail__subscribe"
                                aria-label="Suivre <?php echo htmlspecialchars($produit_boutique_nom, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-plus" aria-hidden="true"></i>
                                <span>Suivre</span>
                            </button>
                            <?php endif; ?>
                        </form>
                        <?php else: ?>
                        <a href="/choix-connexion.php?redirect=<?php echo rawurlencode($produit_abonnement_redirect); ?>"
                            class="produit-boutique-rail__subscribe produit-boutique-rail__subscribe--login"
                            aria-label="Se connecter pour suivre <?php echo htmlspecialchars($produit_boutique_nom, ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fas fa-plus" aria-hidden="true"></i>
                            <span>Suivre</span>
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars($produit_boutique_url); ?>"
                        class="produit-boutique-rail__cta"
                        aria-label="Voir la boutique <?php echo htmlspecialchars($produit_boutique_nom, ENT_QUOTES, 'UTF-8'); ?>">
                        <span><?php echo $produit_boutique_slug !== '' ? 'Boutique' : 'Catalogue'; ?></span>
                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </aside>

        <div class="produit-detail-wrapper">
            <!-- Section Image avec galerie -->
            <div class="produit-image-section">
                <?php
                $galerie_images = [];
                if (!empty($produit['images'])) {
                    $dec = json_decode($produit['images'], true);
                    if (is_array($dec))
                        $galerie_images = $dec;
                }
                if (empty($galerie_images) && !empty($produit['image_principale'])) {
                    $galerie_images = [$produit['image_principale']];
                }
                ?>
                <div class="produit-gallery-layout">
                    <?php if (count($galerie_images) > 1): ?>
                        <div class="produit-gallery-thumbs">
                            <div class="gallery-thumbs-list">
                                <?php foreach ($galerie_images as $idx => $img_path): ?>
                                    <button type="button" class="gallery-thumb <?php echo $idx === 0 ? 'active' : ''; ?>"
                                        data-index="<?php echo $idx; ?>"
                                        data-src="<?php echo htmlspecialchars(upload_image_url($img_path, 'original')); ?>">
                                        <img src="<?php echo htmlspecialchars(upload_image_url($img_path, 'sm')); ?>"
                                            alt="Vue <?php echo $idx + 1; ?>" onerror="this.src='<?php echo htmlspecialchars(upload_image_fallback_url(), ENT_QUOTES, 'UTF-8'); ?>'">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div class="gallery-thumbs-nav">
                                <button type="button" class="gallery-nav gallery-prev" aria-label="Miniatures précédentes">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                                <button type="button" class="gallery-nav gallery-next" aria-label="Miniatures suivantes">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="produit-gallery-main">
                        <?php require __DIR__ . '/includes/partials/product_share_button.php'; ?>
                        <?php
                        $return_url = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : app_url('/produit.php?id=' . (int) $produit_id);
                        require __DIR__ . '/includes/partials/mp_favori_button.php';
                        ?>
                        <img src="<?php echo htmlspecialchars(upload_image_url($galerie_images[0] ?? $produit['image_principale'] ?? '', 'original')); ?>"
                            alt="<?php echo htmlspecialchars($produit['nom']); ?>" class="produit-image-main"
                            id="produit-image-main" onerror="this.src='<?php echo htmlspecialchars(upload_image_fallback_url(), ENT_QUOTES, 'UTF-8'); ?>'">
                    </div>
                </div>
                <?php if ($produit_tailles_sous_image): ?>
                <div class="produit-tailles-sous-image taille-options-list" id="produit-tailles-sous-image" data-taille-required="1">
                    <p class="produit-tailles-sous-image__label">Taille <span class="required" aria-hidden="true">*</span></p>
                    <p class="produit-tailles-sous-image__err" id="produit-taille-err" hidden role="alert">Choisissez une taille pour continuer.</p>
                    <div class="produit-tailles-sous-image__chips options-list-select">
                        <?php foreach ($taille_options as $ti => $opt): ?>
                        <label class="produit-tailles-sous-image__chip option-swatch-select<?php echo (count($taille_options) === 1) ? ' selected' : ''; ?>"
                            data-value="<?php echo htmlspecialchars($opt['v'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-surcout="<?php echo (float) ($opt['s'] ?? 0); ?>">
                            <input type="radio" form="add-to-panier-form" name="option_taille"
                                value="<?php echo htmlspecialchars($opt['v'], ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo (count($taille_options) === 1) ? 'checked' : ''; ?>>
                            <span class="option-swatch-text"><?php echo htmlspecialchars($opt['v']); ?><?php echo ($opt['s'] ?? 0) > 0 ? ' (+' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)' : ''; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Section Informations (détails produit) -->
            <div class="produit-info-section">
                <h1 class="produit-nom" id="produit-nom"><?php echo htmlspecialchars($produit['nom']); ?></h1>
                <?php if (!empty($produit['avis_count'])): ?>
                    <?php
                    $note = (float) ($produit['avis_moyenne'] ?? 0);
                    $count = (int) ($produit['avis_count'] ?? 0);
                    $size = 'md';
                    $class_extra = 'produit-rating-stars';
                    require __DIR__ . '/includes/partials/product_rating_stars.php';
                    ?>
                <?php endif; ?>

                <!-- Prix -->
                <div class="produit-prix-section">
                    <div class="prix-principal" id="produit-prix-affichage">
                        <?php if ($prix_original): ?>
                            <span class="prix-original"><?php echo number_format($produit['prix'], 0, ',', ' '); ?>
                                FCFA</span>
                            <span class="prix-promo"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA</span>
                            <span class="promo-badge">-<?php echo $pourcentage_reduction; ?>%</span>
                        <?php else: ?>
                            <?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                /* Vente en gros (type Alibaba) : prix par pièce selon la quantité */
                require_once __DIR__ . '/includes/produit_vente_gros.php';
                $vg_paliers = vente_gros_paliers($produit);
                $vg_moq = vente_gros_quantite_min($produit);
                ?>
                <?php if ($vg_paliers):
                    /* Échelle de prix : prix à l'unité (si vendu à l'unité) puis chaque palier */
                    $vg_echelle = [];
                    if ($vg_moq <= 1) {
                        $vg_echelle[] = ['q' => 1, 'p' => (float) $prix_affichage, 'label' => vente_gros_libelle_unite($vg_paliers)];
                    }
                    foreach ($vg_paliers as $i => $pl) {
                        $vg_echelle[] = ['q' => (int) $pl['q'], 'p' => (float) $pl['p'], 'label' => vente_gros_libelle_palier($vg_paliers, $i)];
                    }
                    $vg_best = (float) end($vg_paliers)['p'];
                    $vg_best_pct = $prix_affichage > 0 ? (int) round(($prix_affichage - $vg_best) / $prix_affichage * 100) : 0;
                ?>
                <section class="pvg" aria-labelledby="pvgTitle">
                    <div class="pvg__head">
                        <h2 class="pvg__title" id="pvgTitle"><i class="fas fa-boxes-stacked" aria-hidden="true"></i> Prix de gros</h2>
                        <?php if ($vg_best_pct > 0): ?>
                        <span class="pvg__best">Économisez jusqu’à <?php echo $vg_best_pct; ?> %</span>
                        <?php endif; ?>
                    </div>
                    <ol class="pvg__ladder" id="pvgTiers" style="--pvg-cols: <?php echo count($vg_echelle); ?>">
                        <?php foreach ($vg_echelle as $step):
                            $step_pct = ($prix_affichage > 0 && $step['p'] < $prix_affichage) ? (int) round(($prix_affichage - $step['p']) / $prix_affichage * 100) : 0; ?>
                        <li class="pvg__step" data-q="<?php echo (int) $step['q']; ?>" data-p="<?php echo htmlspecialchars((string) $step['p'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="pvg__price"><?php echo number_format($step['p'], 0, ',', ' '); ?> <small>FCFA</small></span>
                            <span class="pvg__range"><?php echo htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="pvg__save<?php echo $step_pct > 0 ? '' : ' pvg__save--none'; ?>"><?php echo $step_pct > 0 ? '−' . $step_pct . ' %' : 'Prix unitaire'; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                    <div class="pvg__foot">
                        <span class="pvg__moq">
                            <i class="fas fa-box" aria-hidden="true"></i>
                            <?php echo $vg_moq > 1 ? 'Commande minimum : <strong>' . (int) $vg_moq . ' pièces</strong>' : 'Vendu à l’unité et en gros'; ?>
                        </span>
                        <span class="pvg__hint" id="pvgHint" aria-live="polite"></span>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Variantes, Stock, Poids, Couleurs, Taille -->
                <?php
                $couleurs_options = [];
                if (!empty($produit['couleurs'])) {
                    $cr = trim($produit['couleurs']);
                    $dec = json_decode($cr, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        if (is_array($dec) && !empty($dec)) {
                            $couleurs_options = array_filter($dec, function ($c) {
                                return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
                            });
                        }
                    } else {
                        $couleurs_options = array_map('trim', array_filter(explode(',', $cr)));
                    }
                }
                $poids_options = parse_options_with_surcharge($produit['poids'] ?? null);
                $poids_options = array_values(array_filter($poids_options, function ($o) {
                    $v = trim((string) ($o['v'] ?? ''));
                    return $v !== '' && $v !== '[]' && $v !== '[ ]' && strtolower($v) !== 'null';
                }));
                if (!isset($taille_options) || !is_array($taille_options)) {
                    $taille_options = [];
                }
                $has_selectable_options = !empty($couleurs_options) || !empty($poids_options)
                    || (!$produit_tailles_sous_image && !empty($taille_options));
                $has_variantes = !empty($variantes);
                $prix_base_js = $prix_affichage;
                $variantes_js = [];
                foreach ($variantes as $v) {
                    $vp = !empty($v['prix_promotion']) && $v['prix_promotion'] < $v['prix'] ? $v['prix_promotion'] : $v['prix'];
                    $variantes_js[] = ['id' => $v['id'], 'nom' => $v['nom'], 'prix' => (float) $vp, 'image' => $v['image'] ?? ''];
                }
                $poids_js = [];
                foreach ($poids_options as $p) {
                    $poids_js[] = ['v' => $p['v'], 's' => (float) ($p['s'] ?? 0)];
                }
                $taille_js = [];
                foreach ($taille_options as $t) {
                    $taille_js[] = ['v' => $t['v'], 's' => (float) ($t['s'] ?? 0)];
                }
                ?>

                <!-- Variantes : juste sous le prix, sans cadre -->
                <?php if ($has_variantes): ?>
                    <?php $total_variantes = 1 + count($variantes); ?>
                    <div class="produit-variantes-section">
                        <p class="variantes-heading">Modèle&nbsp;: <strong id="variante-choisie"><?php echo htmlspecialchars($produit['nom']); ?></strong></p>
                        <div class="variantes-carousel-wrapper" data-total="<?php echo $total_variantes; ?>">
                            <button type="button" class="variantes-arrow variantes-arrow-left"
                                aria-label="Variantes précédentes" title="Précédent">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button type="button" class="variantes-arrow variantes-arrow-right"
                                aria-label="Variantes suivantes" title="Suivant">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <div class="variantes-scroll-container">
                                <div class="variantes-select">
                                    <?php
                                    $base_prix_orig = $prix_original ? (float) $produit['prix'] : 0;
                                    $base_prix_promo = $prix_affichage;
                                    $base_pct = $pourcentage_reduction;
                                    ?>
                                    <label class="variante-option variante-base selected" data-id=""
                                        data-prix="<?php echo $base_prix_promo; ?>"
                                        data-prix-original="<?php echo $base_prix_orig; ?>"
                                        data-prix-promo="<?php echo $base_prix_promo; ?>"
                                        data-pourcentage="<?php echo $base_pct; ?>"
                                        data-nom="<?php echo htmlspecialchars($produit['nom']); ?>"
                                        data-image="<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>"
                                        data-image-url="<?php echo !empty($produit['image_principale']) ? htmlspecialchars(upload_image_url_from_src((string) $produit['image_principale'], 'md'), ENT_QUOTES, 'UTF-8') : ''; ?>">
                                        <input type="radio" name="option_variante_radio" value="" checked required>
                                        <?php if (!empty($produit['image_principale'])): ?><img
                                                src="<?php echo htmlspecialchars(upload_image_url_from_src((string) $produit['image_principale'], 'sm'), ENT_QUOTES, 'UTF-8'); ?>"
                                                alt="" class="variante-thumb"
                                                onerror="this.style.display='none'"><?php endif; ?>
                                        <span class="variante-nom"><?php echo htmlspecialchars($produit['nom']); ?></span>
                                        <span
                                            class="variante-prix"><?php echo number_format($prix_affichage, 0, ',', ' '); ?>
                                            FCFA</span>
                                    </label>
                                    <?php foreach ($variantes as $var): ?>
                                        <?php
                                        $vp = !empty($var['prix_promotion']) && $var['prix_promotion'] < $var['prix'] ? $var['prix_promotion'] : $var['prix'];
                                        $v_prix_orig = (!empty($var['prix_promotion']) && $var['prix_promotion'] < $var['prix']) ? (float) $var['prix'] : 0;
                                        $v_pct = $v_prix_orig > 0 ? round((($var['prix'] - $var['prix_promotion']) / $var['prix']) * 100) : 0;
                                        ?>
                                        <label class="variante-option" data-id="<?php echo $var['id']; ?>"
                                            data-prix="<?php echo $vp; ?>" data-prix-original="<?php echo $v_prix_orig; ?>"
                                            data-prix-promo="<?php echo $vp; ?>" data-pourcentage="<?php echo $v_pct; ?>"
                                            data-nom="<?php echo htmlspecialchars($var['nom']); ?>"
                                            data-image="<?php echo htmlspecialchars($var['image'] ?? ''); ?>"
                                            data-image-url="<?php echo !empty($var['image']) ? htmlspecialchars(upload_image_url_from_src((string) $var['image'], 'md'), ENT_QUOTES, 'UTF-8') : ''; ?>">
                                            <input type="radio" name="option_variante_radio" value="<?php echo $var['id']; ?>">
                                            <?php if (!empty($var['image'])): ?><img
                                                    src="<?php echo htmlspecialchars(upload_image_url_from_src((string) $var['image'], 'sm'), ENT_QUOTES, 'UTF-8'); ?>" alt=""
                                                    class="variante-thumb" onerror="this.style.display='none'"><?php endif; ?>
                                            <span class="variante-nom"><?php echo htmlspecialchars($var['nom']); ?></span>
                                            <span class="variante-prix"><?php echo number_format($vp, 0, ',', ' '); ?>
                                                FCFA</span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Description -->
                <?php require __DIR__ . '/includes/partials/produit_description_block.php'; ?>




                <!-- Options (couleur, poids, taille) : affichées pour tous les utilisateurs -->
                <form method="POST" action="produit.php?id=<?php echo (int) $produit_id; ?>" id="add-to-panier-form" class="produit-add-form">
                    <input type="hidden" name="action" value="add_to_panier">
                    <?php echo site_csrf_field(); ?>
                    <input type="hidden" name="produit_id" value="<?php echo $produit['id']; ?>">
                    <?php if ($has_variantes): ?>
                        <input type="hidden" name="option_variante_id" id="option-variante-id" value="">
                        <input type="hidden" name="option_variante_nom" id="option-variante-nom"
                            value="<?php echo htmlspecialchars($produit['nom']); ?>">
                        <input type="hidden" name="option_variante_image" id="option-variante-image"
                            value="<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>">
                    <?php endif; ?>

                    <?php if ($has_selectable_options): ?>
                        <div class="produit-options-section produit-section-bg">
                            <?php if (!empty($couleurs_options)): ?>
                                <div class="option-group">
                                    <label class="option-label">Couleur</label>
                                    <?php if (count($couleurs_options) === 1): ?>
                                        <input type="hidden" name="option_couleur"
                                            value="<?php echo htmlspecialchars($couleurs_options[0]); ?>">
                                        <span class="couleurs-swatches-select">
                                            <?php $hex = $couleurs_options[0]; ?>
                                            <span class="couleur-swatch-select is-hex" style="opacity:0.9;">
                                                <?php if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)): ?>
                                                    <span class="swatch-preview"
                                                        style="background-color:<?php echo htmlspecialchars($hex); ?>;"
                                                        title="<?php echo htmlspecialchars($hex); ?>"></span>
                                                    <span class="swatch-text"><?php echo htmlspecialchars($hex); ?></span>
                                                <?php else: ?>
                                                    <span class="swatch-text"><?php echo htmlspecialchars($hex); ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                    <?php else: ?>
                                        <span class="couleurs-swatches-select">
                                            <?php foreach ($couleurs_options as $hex): ?>
                                                <label
                                                    class="couleur-swatch-select <?php echo preg_match('/^#[0-9A-Fa-f]{6}$/', $hex) ? 'is-hex' : ''; ?>">
                                                    <input type="radio" name="option_couleur"
                                                        value="<?php echo htmlspecialchars($hex); ?>" class="option-radio-couleur">
                                                    <?php if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)): ?>
                                                        <span class="swatch-preview"
                                                            style="background-color:<?php echo htmlspecialchars($hex); ?>;"
                                                            title="<?php echo htmlspecialchars($hex); ?>"></span>
                                                    <?php else: ?>
                                                        <span class="swatch-text"><?php echo htmlspecialchars($hex); ?></span>
                                                    <?php endif; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($poids_options) && count($poids_options) > 1): ?>
                                <div class="option-group">
                                    <label class="option-label">Poids</label>
                                    <input type="hidden" name="option_surcout_poids" id="option-surcout-poids" value="0">
                                    <span class="options-list-select poids-options-list">
                                        <label class="option-swatch-select selected" data-value="" data-surcout="0">
                                            <input type="radio" name="option_poids" value="" checked>
                                            <span class="option-swatch-text">
                                                <span class="produit-option-chip__main">Prix de base</span>
                                                <span class="produit-option-chip__hint"><?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA</span>
                                            </span>
                                        </label>
                                        <?php foreach ($poids_options as $opt): ?>
                                            <?php $opt_surcout = (float) ($opt['s'] ?? 0); ?>
                                            <label class="option-swatch-select"
                                                data-value="<?php echo htmlspecialchars($opt['v']); ?>"
                                                data-surcout="<?php echo $opt_surcout; ?>">
                                                <input type="radio" name="option_poids"
                                                    value="<?php echo htmlspecialchars($opt['v']); ?>">
                                                <span class="option-swatch-text">
                                                    <span class="produit-option-chip__main"><?php echo htmlspecialchars($opt['v']); ?></span>
                                                    <?php if ($opt_surcout > 0): ?>
                                                    <span class="produit-option-chip__surcout">+<?php echo number_format($opt_surcout, 0, ',', ' '); ?> FCFA</span>
                                                    <?php else: ?>
                                                    <span class="produit-option-chip__hint">Inclus</span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                            <?php elseif (!empty($poids_options)): ?>
                                <?php $poids_unique_surcout = (float) ($poids_options[0]['s'] ?? 0); ?>
                                <div class="option-group option-group--poids">
                                    <label class="option-label">Poids</label>
                                    <input type="hidden" name="option_poids"
                                        value="<?php echo htmlspecialchars($poids_options[0]['v']); ?>">
                                    <input type="hidden" name="option_surcout_poids" id="option-surcout-poids"
                                        value="<?php echo $poids_unique_surcout; ?>">
                                    <div class="options-list-select poids-options-list">
                                        <div class="produit-option-chip produit-option-chip--locked" aria-label="Poids sélectionné">
                                            <span class="produit-option-chip__main"><?php echo htmlspecialchars($poids_options[0]['v']); ?></span>
                                            <?php if ($poids_unique_surcout > 0): ?>
                                            <span class="produit-option-chip__surcout">+<?php echo number_format($poids_unique_surcout, 0, ',', ' '); ?> FCFA</span>
                                            <?php else: ?>
                                            <span class="produit-option-chip__hint">Inclus</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if (!$produit_tailles_sous_image && !empty($taille_options) && count($taille_options) > 1): ?>
                                <div class="option-group">
                                    <label class="option-label">Taille</label>
                                    <input type="hidden" name="option_surcout_taille" id="option-surcout-taille" value="0">
                                    <span class="options-list-select taille-options-list">
                                        <?php foreach ($taille_options as $opt): ?>
                                            <label class="option-swatch-select"
                                                data-value="<?php echo htmlspecialchars($opt['v']); ?>"
                                                data-surcout="<?php echo (float) ($opt['s'] ?? 0); ?>">
                                                <input type="radio" name="option_taille"
                                                    value="<?php echo htmlspecialchars($opt['v']); ?>">
                                                <?php $taille_surcout = (float) ($opt['s'] ?? 0); ?>
                                                <span class="option-swatch-text">
                                                    <span class="produit-option-chip__main"><?php echo htmlspecialchars($opt['v']); ?></span>
                                                    <?php if ($taille_surcout > 0): ?>
                                                    <span class="produit-option-chip__surcout">+<?php echo number_format($taille_surcout, 0, ',', ' '); ?> FCFA</span>
                                                    <?php else: ?>
                                                    <span class="produit-option-chip__hint">Inclus</span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                            <?php elseif (!$produit_tailles_sous_image && !empty($taille_options)): ?>
                                <?php $taille_unique_surcout = (float) ($taille_options[0]['s'] ?? 0); ?>
                                <div class="option-group option-group--taille">
                                    <label class="option-label">Taille</label>
                                    <input type="hidden" name="option_taille"
                                        value="<?php echo htmlspecialchars($taille_options[0]['v']); ?>">
                                    <input type="hidden" name="option_surcout_taille" id="option-surcout-taille"
                                        value="<?php echo $taille_unique_surcout; ?>">
                                    <div class="options-list-select taille-options-list">
                                        <div class="produit-option-chip produit-option-chip--locked" aria-label="Taille sélectionnée">
                                            <span class="produit-option-chip__main"><?php echo htmlspecialchars($taille_options[0]['v']); ?></span>
                                            <?php if ($taille_unique_surcout > 0): ?>
                                            <span class="produit-option-chip__surcout">+<?php echo number_format($taille_unique_surcout, 0, ',', ' '); ?> FCFA</span>
                                            <?php else: ?>
                                            <span class="produit-option-chip__hint">Inclus</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($produit_tailles_sous_image): ?>
                                <input type="hidden" name="option_surcout_taille" id="option-surcout-taille"
                                    value="<?php echo count($taille_options) === 1 ? (float) ($taille_options[0]['s'] ?? 0) : 0; ?>">
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Sélection de quantité et ajout au panier -->
                    <input type="hidden" name="option_prix_unitaire" id="option-prix-unitaire"
                        value="<?php echo $prix_affichage; ?>">
                    <div class="quantite-section">
                        <label class="quantite-label">Quantité:</label>
                        <div class="quantite-controls">
                            <div class="quantite-input-wrapper">
                                <button type="button" class="quantite-btn" id="decrease-qty">-</button>
                                <input type="number" name="quantite" id="quantite" class="quantite-input" value="<?php echo (int) ($vg_moq ?? 1); ?>"
                                    min="<?php echo (int) ($vg_moq ?? 1); ?>" required>
                                <button type="button" class="quantite-btn" id="increase-qty">+</button>
                            </div>
                            <?php if ($produit_peut_negocier): ?>
                                <?php if (!empty($_SESSION['user_id'])): ?>
                                <a href="<?php echo htmlspecialchars($produit_messages_href, ENT_QUOTES, 'UTF-8'); ?>"
                                    class="btn-negocier-prix">
                                    <i class="fas fa-handshake" aria-hidden="true"></i>
                                    <span>Négocier le prix</span>
                                </a>
                                <?php else: ?>
                                <a href="<?php echo htmlspecialchars($produit_negociation_login_redirect, ENT_QUOTES, 'UTF-8'); ?>"
                                    class="btn-negocier-prix">
                                    <i class="fas fa-handshake" aria-hidden="true"></i>
                                    <span>Négocier le prix</span>
                                </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="produit-checkout-row">
                        <div class="prix-total-section">
                            <div class="prix-total-label">Prix total:</div>
                            <div class="prix-total-value" id="prix-total">
                                <?php echo number_format($prix_affichage, 0, ',', ' '); ?> FCFA
                            </div>
                        </div>
                        <button type="submit" class="btn-add-panier" id="btn-add-panier"<?php echo $produit_achetable ? ' data-achetable="1"' : ' disabled'; ?>>
                            <i class="fa-solid fa-cart-shopping"></i>
                            <span class="btn-add-panier__label"><?php echo $produit_achetable ? 'Ajouter au panier' : 'Indisponible'; ?></span>
                        </button>
                    </div>
                </form>

                <!-- Description (en bas) -->
                <!-- <?php if (!empty($produit['description'])): ?>
                    <div class="produit-description produit-section-bg">
                        <h3>Description</h3>
                        <p><?php echo nl2br(htmlspecialchars($produit['description'])); ?></p>
                    </div>
                <?php endif; ?> -->
            </div>
        </div>

        <?php if (!empty($produits_alternatives)):
            $return_url_alt = (string) ($_SERVER['REQUEST_URI'] ?? '/produit.php?id=' . (int) $produit_id);
            require __DIR__ . '/includes/partials/produit_alternatives_block.php';
        endif; ?>

        <!-- Produits similaires (même rayon général, toutes boutiques — cartes type marketplace) -->
        <?php if (!empty($produits_similaires)): ?>
            <?php
            $card_partial_sim = __DIR__ . '/includes/partials/home_mp_product_card.php';
            $return_url_sim = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/produit.php?id=' . (int) $produit_id;
            $_produit_save = $produit;
            $_prix_affichage_save = $prix_affichage;
            ?>
            <section class="produit-similaires-mp" aria-labelledby="similaires-title">
                <header class="mp-block-head">
                    <div>
                        <h2 id="similaires-title">Produits similaires</h2>
                    </div>
                </header>
                <div class="mp-grid" id="produits-similaires-grid">
                    <?php
                    foreach ($produits_similaires as $row_sim) {
                        $produit = $row_sim;
                        $return_url = $return_url_sim;
                        require $card_partial_sim;
                    }
                    $produit = $_produit_save;
                    $prix_affichage = $_prix_affichage_save;
                    ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <?php include('footer.php') ?>

    <script>
        // Calcul automatique du prix total (variante + surcoûts)
        const prixBase = <?php echo $prix_produit_detail; ?>;
        const quantiteInput = document.getElementById('quantite');
        const prixTotalElement = document.getElementById('prix-total');
        const prixUnitaireInput = document.getElementById('option-prix-unitaire');
        const decreaseBtn = document.getElementById('decrease-qty');
        const increaseBtn = document.getElementById('increase-qty');
        const maxStock = 9999;

        /* Vente en gros : paliers [{q, p}] et quantité minimum (même règle que le panier côté serveur) */
        const venteGrosPaliers = <?php echo json_encode(array_map(static function ($pl) { return ['q' => (int) $pl['q'], 'p' => (float) $pl['p']]; }, $vg_paliers ?? [])); ?>;
        const venteGrosMin = <?php echo (int) ($vg_moq ?? 1); ?>;

        function venteGrosPrixPalier(qte) {
            var prix = null;
            venteGrosPaliers.forEach(function (pl) { if (qte >= pl.q) prix = pl.p; });
            return prix;
        }

        function venteGrosSurligner(qte) {
            var tiers = document.querySelectorAll('#pvgTiers .pvg__step');
            if (!tiers.length) return;
            var active = null;
            tiers.forEach(function (li) {
                li.classList.remove('is-active');
                if (qte >= parseInt(li.dataset.q, 10)) active = li;
            });
            if (active) active.classList.add('is-active');
            var hint = document.getElementById('pvgHint');
            if (!hint) return;
            var next = venteGrosPaliers.find(function (pl) { return pl.q > qte; });
            hint.classList.toggle('is-best', !next);
            hint.innerHTML = next
                ? '<i class="fas fa-arrow-trend-down" aria-hidden="true"></i> Encore <strong>' + (next.q - qte) + ' pièce' + (next.q - qte > 1 ? 's' : '') + '</strong> pour passer à <strong>' + Math.round(next.p).toLocaleString('fr-FR') + ' FCFA</strong> la pièce'
                : '<i class="fas fa-circle-check" aria-hidden="true"></i> Meilleur prix de gros appliqué';
        }

        function getPrixUnitaire() {
            var prix = prixBase;
            var selVariante = document.querySelector('.variante-option.selected, .variante-option input:checked');
            var avecVariante = false;
            if (selVariante) {
                var el = selVariante.classList ? selVariante : selVariante.closest('.variante-option');
                if (el && el.dataset.prix) prix = parseFloat(el.dataset.prix);
                avecVariante = !!(el && el.dataset.id);
            }
            if (!avecVariante && quantiteInput) {
                var prixGros = venteGrosPrixPalier(parseInt(quantiteInput.value, 10) || 1);
                if (prixGros !== null && prixGros < prix) prix = prixGros;
            }
            var surcP = 0,
                surcT = 0;
            var spH = document.getElementById('option-surcout-poids');
            if (spH && spH.value) surcP = parseFloat(spH.value) || 0;
            var stH = document.getElementById('option-surcout-taille');
            if (stH && stH.value) surcT = parseFloat(stH.value) || 0;
            return prix + surcP + surcT;
        }

        function updatePrixTotal() {
            if (!quantiteInput || !prixTotalElement) return;
            const prixUnitaire = getPrixUnitaire();
            const quantite = parseInt(quantiteInput.value) || 1;
            const prixTotal = prixUnitaire * quantite;
            prixTotalElement.textContent = Math.round(prixTotal).toLocaleString('fr-FR') + ' FCFA';
            venteGrosSurligner(quantite);
            if (prixUnitaireInput) prixUnitaireInput.value = prixUnitaire;

            updateAddButtonState();
        }

        function tailleUnderImageOk() {
            var wrap = document.getElementById('produit-tailles-sous-image');
            if (!wrap || wrap.getAttribute('data-taille-required') !== '1') {
                return true;
            }
            var sel = wrap.querySelector('input[name="option_taille"]:checked');
            return !!(sel && String(sel.value || '').trim() !== '');
        }

        function updateAddButtonState() {
            var btnAdd = document.getElementById('btn-add-panier');
            if (!btnAdd || !btnAdd.getAttribute('data-achetable')) {
                return;
            }
            var quantite = quantiteInput ? (parseInt(quantiteInput.value, 10) || 0) : 0;
            btnAdd.disabled = quantite <= 0 || !tailleUnderImageOk();
        }

        var produitNomBase = '<?php echo addslashes(htmlspecialchars($produit['nom'])); ?>';

        function updatePrixEtNomAffichage() {
            var prixUnitaire = getPrixUnitaire();
            var selVariante = document.querySelector('.variante-option.selected, .variante-option input:checked');
            var el = selVariante && selVariante.classList ? selVariante : (selVariante ? selVariante.closest(
                '.variante-option') : null);
            var nomAffichage = produitNomBase;
            if (el && el.dataset.nom) nomAffichage = el.dataset.nom;

            var elNom = document.getElementById('produit-nom');
            var elPrix = document.getElementById('produit-prix-affichage');
            if (elNom) elNom.textContent = nomAffichage;

            if (elPrix) {
                var prixOrig = parseFloat(el && el.dataset.prixOriginal ? el.dataset.prixOriginal : 0) || 0;
                var pourcentage = parseInt(el && el.dataset.pourcentage ? el.dataset.pourcentage : 0) || 0;
                var surcP = 0,
                    surcT = 0;
                var spH = document.getElementById('option-surcout-poids');
                if (spH && spH.value) surcP = parseFloat(spH.value) || 0;
                var stH = document.getElementById('option-surcout-taille');
                if (stH && stH.value) surcT = parseFloat(stH.value) || 0;
                var prixOriginalAvecSurc = prixOrig + surcP + surcT;

                if (prixOrig > 0 && pourcentage > 0) {
                    elPrix.innerHTML = '<span class="prix-original">' + prixOriginalAvecSurc.toLocaleString('fr-FR') +
                        ' FCFA</span> ' +
                        '<span class="prix-promo">' + prixUnitaire.toLocaleString('fr-FR') + ' FCFA</span> ' +
                        '<span class="promo-badge">-' + pourcentage + '%</span>';
                } else {
                    elPrix.textContent = prixUnitaire.toLocaleString('fr-FR') + ' FCFA';
                }
            }
        }

        document.querySelectorAll('.variante-option').forEach(function (el) {
            el.addEventListener('click', function () {
                document.querySelectorAll('.variante-option').forEach(function (x) {
                    x.classList.remove('selected');
                });
                el.classList.add('selected');
                var inp = el.querySelector('input[type="radio"]');
                if (inp) inp.checked = true;
                var hid = document.getElementById('option-variante-id');
                var hnom = document.getElementById('option-variante-nom');
                var himg = document.getElementById('option-variante-image');
                if (hid) hid.value = el.dataset.id || '';
                if (hnom) hnom.value = el.dataset.nom || '';
                var hchoix = document.getElementById('variante-choisie');
                if (hchoix) hchoix.textContent = el.dataset.nom || '';
                if (himg) himg.value = el.dataset.image || '';
                var mainImg = document.getElementById('produit-image-main');
                // URL de la variante optimisée (l'original peut ne plus exister sur le serveur)
                if (mainImg && el.dataset.imageUrl) mainImg.src = el.dataset.imageUrl;
                else if (mainImg && !el.dataset.id) mainImg.src =
                    '/upload/<?php echo htmlspecialchars($produit['image_principale'] ?? ''); ?>';
                updatePrixTotal();
                if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
            });
        });

        (function initVariantesCarousel() {
            var wrapper = document.querySelector('.variantes-carousel-wrapper');
            if (!wrapper) return;
            var scrollContainer = wrapper.querySelector('.variantes-scroll-container');
            var arrowLeft = wrapper.querySelector('.variantes-arrow-left');
            var arrowRight = wrapper.querySelector('.variantes-arrow-right');
            var total = parseInt(wrapper.dataset.total || 0, 10);
            if (!scrollContainer || !arrowLeft || !arrowRight || total <= 0) return;

            function getVisibleCount() {
                var w = window.innerWidth;
                if (w >= 1024) return 4;
                if (w >= 768) return 3;
                return 2;
            }

            function updateArrows() {
                var visible = getVisibleCount();
                if (total <= visible) {
                    arrowLeft.classList.remove('visible');
                    arrowRight.classList.remove('visible');
                    return;
                }
                var sc = scrollContainer.scrollLeft;
                var maxScroll = scrollContainer.scrollWidth - scrollContainer.clientWidth;
                arrowLeft.classList.toggle('visible', sc > 5);
                arrowRight.classList.toggle('visible', sc < maxScroll - 5);
            }

            scrollContainer.addEventListener('scroll', updateArrows);
            window.addEventListener('resize', function () {
                updateArrows();
            });

            arrowLeft.addEventListener('click', function () {
                var step = scrollContainer.clientWidth;
                scrollContainer.scrollBy({
                    left: -step,
                    behavior: 'smooth'
                });
            });
            arrowRight.addEventListener('click', function () {
                var step = scrollContainer.clientWidth;
                scrollContainer.scrollBy({
                    left: step,
                    behavior: 'smooth'
                });
            });

            updateArrows();
        })();

        function setupOptionSwatchListeners() {
            document.querySelectorAll('.poids-options-list .option-swatch-select').forEach(function (lbl) {
                lbl.addEventListener('click', function () {
                    lbl.closest('.poids-options-list').querySelectorAll('.option-swatch-select').forEach(
                        function (x) {
                            x.classList.remove('selected');
                        });
                    lbl.classList.add('selected');
                    var surc = document.getElementById('option-surcout-poids');
                    if (surc) surc.value = lbl.dataset.surcout || 0;
                    updatePrixTotal();
                    if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
                });
            });
            document.querySelectorAll('.taille-options-list .option-swatch-select').forEach(function (lbl) {
                lbl.addEventListener('click', function () {
                    lbl.closest('.taille-options-list').querySelectorAll('.option-swatch-select').forEach(
                        function (x) {
                            x.classList.remove('selected');
                        });
                    lbl.classList.add('selected');
                    var surc = document.getElementById('option-surcout-taille');
                    if (surc) surc.value = lbl.dataset.surcout || 0;
                    updatePrixTotal();
                    if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
                });
            });
            document.querySelectorAll('input[name="option_poids"]').forEach(function (rad) {
                rad.addEventListener('change', function () {
                    var lbl = this.closest('.option-swatch-select');
                    var surc = document.getElementById('option-surcout-poids');
                    if (surc && lbl) surc.value = lbl.dataset.surcout || 0;
                    updatePrixTotal();
                    if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
                });
            });
            document.querySelectorAll('input[name="option_taille"]').forEach(function (rad) {
                rad.addEventListener('change', function () {
                    var lbl = this.closest('.option-swatch-select');
                    var surc = document.getElementById('option-surcout-taille');
                    if (surc && lbl) surc.value = lbl.dataset.surcout || 0;
                    updatePrixTotal();
                    if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
                });
            });
        }
        setupOptionSwatchListeners();

        (function initTailleSousImage() {
            var wrap = document.getElementById('produit-tailles-sous-image');
            var err = document.getElementById('produit-taille-err');
            var form = document.getElementById('add-to-panier-form');
            if (!wrap) return;

            function syncChipSelectedState() {
                wrap.querySelectorAll('.produit-tailles-sous-image__chip').forEach(function (lab) {
                    var inp = lab.querySelector('input[type="radio"]');
                    lab.classList.toggle('selected', !!(inp && inp.checked));
                });
            }

            function onTaillePick() {
                syncChipSelectedState();
                var lbl = this.closest('.option-swatch-select') || this.closest('.produit-tailles-sous-image__chip');
                var surc = document.getElementById('option-surcout-taille');
                if (surc && lbl) surc.value = lbl.dataset.surcout || 0;
                if (err) err.hidden = true;
                wrap.classList.remove('is-invalid');
                updatePrixTotal();
                if (typeof updatePrixEtNomAffichage === 'function') updatePrixEtNomAffichage();
            }

            wrap.querySelectorAll('input[name="option_taille"]').forEach(function (rad) {
                rad.addEventListener('change', onTaillePick);
            });
            wrap.querySelectorAll('.produit-tailles-sous-image__chip').forEach(function (lab) {
                lab.addEventListener('click', function () {
                    setTimeout(syncChipSelectedState, 0);
                });
            });

            syncChipSelectedState();
            updateAddButtonState();

            if (form) {
                form.addEventListener('submit', function (e) {
                    if (!tailleUnderImageOk()) {
                        e.preventDefault();
                        if (err) err.hidden = false;
                        wrap.classList.add('is-invalid');
                        wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                });
            }
        })();

        var galleryThumbs = document.querySelectorAll('.gallery-thumb');
        var galleryMain = document.getElementById('produit-image-main');
        var galleryPrev = document.querySelector('.gallery-prev');
        var galleryNext = document.querySelector('.gallery-next');
        var galleryList = document.querySelector('.gallery-thumbs-list');
        var galleryNavWrap = document.querySelector('.gallery-thumbs-nav');
        if (galleryThumbs.length > 0 && galleryMain) {
            function thumbStep() {
                if (galleryThumbs.length > 1) {
                    return galleryThumbs[1].offsetTop - galleryThumbs[0].offsetTop;
                }
                return galleryThumbs[0].offsetHeight;
            }

            function updateGalleryNav() {
                if (!galleryList) return;
                var overflow = galleryList.scrollHeight - galleryList.clientHeight;
                var scrollable = overflow > 1;
                if (galleryNavWrap) galleryNavWrap.hidden = !scrollable;
                if (!scrollable) return;
                if (galleryPrev) galleryPrev.disabled = galleryList.scrollTop <= 1;
                if (galleryNext) galleryNext.disabled = galleryList.scrollTop >= overflow - 1;
            }

            function scrollThumbs(direction) {
                if (!galleryList) return;
                var step = thumbStep();
                var perPage = Math.max(1, Math.round(galleryList.clientHeight / step));
                galleryList.scrollTop += direction * step * perPage;
            }

            function setActiveThumb(idx) {
                galleryThumbs.forEach(function (t, i) {
                    t.classList.toggle('active', i === idx);
                });
                var src = galleryThumbs[idx].getAttribute('data-src');
                if (src) galleryMain.src = src;
            }
            galleryThumbs.forEach(function (thumb, idx) {
                thumb.addEventListener('click', function () {
                    setActiveThumb(idx);
                });
            });
            if (galleryPrev) galleryPrev.addEventListener('click', function () {
                scrollThumbs(-1);
            });
            if (galleryNext) galleryNext.addEventListener('click', function () {
                scrollThumbs(1);
            });
            if (galleryList) {
                galleryList.addEventListener('scroll', updateGalleryNav);
                window.addEventListener('resize', updateGalleryNav);
                window.addEventListener('load', updateGalleryNav);
                updateGalleryNav();
            }
        }

        if (quantiteInput) {
            quantiteInput.addEventListener('input', updatePrixTotal);
            quantiteInput.addEventListener('change', function () {
                let value = parseInt(this.value) || 1;
                if (value < venteGrosMin) value = venteGrosMin;
                if (value > maxStock) value = maxStock;
                this.value = value;
                updatePrixTotal();
            });
        }

        if (decreaseBtn) {
            decreaseBtn.addEventListener('click', function () {
                let value = parseInt(quantiteInput.value) || 1;
                if (value > venteGrosMin) {
                    value--;
                    quantiteInput.value = value;
                    updatePrixTotal();
                }
            });
        }

        if (increaseBtn) {
            increaseBtn.addEventListener('click', function () {
                let value = parseInt(quantiteInput.value) || 1;
                if (value < maxStock) {
                    value++;
                    quantiteInput.value = value;
                    updatePrixTotal();
                }
            });
        }

        // Initialiser le prix total au chargement
        if (quantiteInput) {
            updatePrixTotal();
        }

        document.querySelectorAll('.produit-desc-more').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var wrap = btn.closest('[data-produit-desc]');
                if (!wrap) {
                    return;
                }
                var preview = wrap.querySelector('.produit-desc-preview');
                var expanded = wrap.querySelector('.produit-desc-expanded');
                wrap.classList.add('is-expanded');
                if (preview) {
                    preview.hidden = true;
                }
                if (expanded) {
                    expanded.hidden = false;
                }
                btn.setAttribute('aria-expanded', 'true');
            });
        });
    </script>
</body>

</html>