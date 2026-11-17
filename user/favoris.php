<?php
/**
 * Liste des produits favoris du client
 * Programmation procédurale uniquement
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../includes/site_csrf.php';

if (!function_exists('auth_redirect_to_site_home')) {
    require_once __DIR__ . '/../includes/auth_redirect.php';
}
if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    auth_redirect_to_site_home();
    exit;
}

require_once __DIR__ . '/../includes/app_base.php';
require_once __DIR__ . '/../models/model_favoris.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/asset_version.php';

$user_id = (int) $_SESSION['user_id'];
$favoris_raw = get_favoris_by_user($user_id);
$favoris_raw = is_array($favoris_raw) ? $favoris_raw : [];

$produits_favoris = [];
foreach ($favoris_raw as $row) {
    if (!produit_est_visible_client($row['statut'] ?? '')) {
        continue;
    }
    $produits_favoris[] = $row;
}

if (!empty($produits_favoris) && file_exists(__DIR__ . '/../models/model_produits_avis.php')) {
    require_once __DIR__ . '/../models/model_produits_avis.php';
    if (function_exists('produits_avis_enrich_products')) {
        $produits_favoris = produits_avis_enrich_products($produits_favoris);
    }
}

$nb_favoris = count($produits_favoris);
$return_url = app_url('/user/favoris.php');
$card_partial = __DIR__ . '/../includes/partials/home_mp_product_card.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes favoris — Mon compte</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/variables.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">    <?php require_once dirname(__DIR__, 1) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/user-dashboard.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/mp-category-page.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/product-cards.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/user-activity-header.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/user-favoris.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/platform-share-modal.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="user-page-favoris">
    <?php include __DIR__ . '/includes/user_nav.php'; ?>

    <div class="fav-page">
        <?php
        $user_activity_title = 'Mes favoris';
        include __DIR__ . '/includes/user_activity_header.php';
        ?>

        <?php if (empty($produits_favoris)): ?>
        <div class="fav-empty">
            <div class="fav-empty__icon"><i class="far fa-heart" aria-hidden="true"></i></div>
            <h2>Aucun favori pour le moment</h2>
            <p>Appuyez sur le cœur d'un produit dans le catalogue pour le retrouver ici.</p>
            <a href="<?php echo htmlspecialchars(app_url('/produits.php'), ENT_QUOTES, 'UTF-8'); ?>" class="fav-btn fav-btn--primary">
                <i class="fas fa-shopping-bag" aria-hidden="true"></i> Parcourir le catalogue
            </a>
        </div>
        <?php else: ?>
        <div class="mp-grid fav-grid">
            <?php foreach ($produits_favoris as $produit):
                require $card_partial;
            endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/partials/platform_share_modal.php'; ?>
    <script src="<?php echo htmlspecialchars(app_url('/js/platform-share-modal.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script>window.MP_FAVORI_API=<?php echo json_encode(app_url('/api/toggle_favori.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;window.MP_FAVORI_LOGIN=<?php echo json_encode(app_url('/choix-connexion.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;</script>
    <script src="<?php echo htmlspecialchars(app_url('/js/mp-favori.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>" defer></script>

    <?php include __DIR__ . '/includes/user_footer.php'; ?>
