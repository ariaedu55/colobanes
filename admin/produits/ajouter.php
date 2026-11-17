<?php
/**
 * Page d'ajout de produ — formulaire (peut aussi être ouvert depuis la liste en modal).
 */

require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../controllers/controller_produits.php';
$result = process_add_produit();

if (isset($result['success']) && $result['success']) {
    $_SESSION['success_message'] = $result['message'];
    $categorie_id = isset($_POST['categorie_id']) ? (int) $_POST['categorie_id'] : 0;
    if ($categorie_id > 0) {
        header('Location: ../categories/produits.php?id=' . $categorie_id);
    } else {
        header('Location: ../stock/index.php');
    }
    exit;
}

require_once __DIR__ . '/../../models/model_categories.php';
require_once __DIR__ . '/../../models/model_genres.php';
$categories = admin_categories_list_for_session();
$__role_add = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
$fap_use_category_hierarchy = categories_hierarchy_enabled() && ($__role_add === 'vendeur');
$vendeur_genre_ids_prefill = [];
$vcat_prefill_sub = 0;
$vcat_prefill_generale = 0;
$categorie_id_prefill = isset($_GET['categorie_id']) ? (int) $_GET['categorie_id'] : 0;
if ($fap_use_category_hierarchy && $categorie_id_prefill > 0) {
    $cp = get_categorie_by_id($categorie_id_prefill);
    if ($cp && function_exists('categorie_est_utilisable_par_vendeur')
        && categorie_est_utilisable_par_vendeur((int) $cp['id'], (int) $_SESSION['admin_id'])) {
        $vcat_prefill_sub = (int) $cp['id'];
        if (function_exists('categories_has_categorie_generale_id_column') && categories_has_categorie_generale_id_column()) {
            $vcat_prefill_generale = (int) ($cp['categorie_generale_id'] ?? 0);
        }
    }
}
$add_produit_modal = false;
$add_produit_form_action = '';
$add_produit_error_message = '';
if (isset($result['message']) && !empty($result['message']) && empty($result['success'])) {
    $add_produit_error_message = $result['message'];
}
$add_product_back_href = $categorie_id_prefill > 0
    ? '../categories/produits.php?id=' . $categorie_id_prefill
    : '../stock/index.php';
$add_product_back_label = 'Produits';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="adm-add-product-page">
        <div class="adm-add-product-page-inner">
            <header class="vd-page-head adm-add-head">
                <div class="vd-page-head__text">
                    <a href="<?php echo htmlspecialchars($add_product_back_href, ENT_QUOTES, 'UTF-8'); ?>" class="vd-page-head__back">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i> <?php echo htmlspecialchars($add_product_back_label, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <h1 class="vd-page-head__title">Ajouter un produit</h1>
                    <p class="vd-page-head__sub">Photos, prix et stock : votre produit sera visible dès son enregistrement.</p>
                </div>
            </header>
            <div class="adm-modal-add-body">
                <div class="form-add-container">
                    <?php require __DIR__ . '/inc_form_ajouter_produit.php'; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
