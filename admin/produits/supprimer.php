<?php
/**
 * Page de suppression de produit
 * Programmation procédurale uniquement
 */

ob_start();

require_once __DIR__ . '/../includes/require_admin_session.php';
require_once __DIR__ . '/../../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_route_access.php';
require_once __DIR__ . '/../../includes/flash_toast.php';

$stock_list_url = admin_route_build_url('stock/index.php');

$produit_id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;

if ($produit_id <= 0) {
    http_redirect_safe($stock_list_url);
}

require_once __DIR__ . '/../../models/model_produits.php';
$produit = get_produit_by_id($produit_id);

if (!$produit) {
    http_redirect_safe($stock_list_url);
}
admin_vendeur_assert_produit_owned($produit);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    require_once __DIR__ . '/../../controllers/controller_produits.php';
    $result = process_delete_produit($produit_id);

    if ($result['success']) {
        flash_toast_push('success', $result['message']);
        http_redirect_safe($stock_list_url);
    }

    $error_message = $result['message'];
    flash_toast_queue_page('error', $error_message);
}

$img_path = trim((string) ($produit['image_principale'] ?? ''));
$img_src = $img_path !== '' ? (function_exists('upload_image_url') ? upload_image_url(str_replace('\\', '/', $img_path), 'sm') : '/upload/' . ltrim((string) str_replace('\\', '/', $img_path), '/')) : '';
$prix_aff = number_format((float) ($produit['prix'] ?? 0), 0, ',', ' ') . ' FCFA';
$stock_aff = (int) ($produit['stock'] ?? 0);
$cat_aff = htmlspecialchars((string) ($produit['categorie_nom'] ?? 'Sans catégorie'), ENT_QUOTES, 'UTF-8');
$nom_aff = htmlspecialchars((string) ($produit['nom'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un produit — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>

    <div class="adm-add-product-page">
        <div class="adm-add-product-page-inner adm-delete-product-inner">
            <header class="adm-modal-add-head">
                <h2><i class="fas fa-trash-alt" aria-hidden="true"></i> Supprimer le produit</h2>
                <div class="adm-modal-add-head-actions">
                    <a href="<?php echo htmlspecialchars($stock_list_url, ENT_QUOTES, 'UTF-8'); ?>"
                        class="adm-modal-add-close"
                        title="Retour au stock"
                        aria-label="Retour au stock">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    </a>
                </div>
            </header>

            <div class="adm-modal-add-body">
                <div class="adm-delete-wrap">
                    <?php if (isset($error_message)): ?>
                    <div class="adm-delete-alert adm-delete-alert--error" role="alert">
                        <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                        <span><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="adm-delete-alert" role="alert">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        <span><strong>Action irréversible.</strong> Ce produit sera définitivement retiré de votre catalogue et de la marketplace.</span>
                    </div>

                    <article class="adm-delete-product-card">
                        <div class="adm-delete-product-card__media">
                            <?php if ($img_src !== ''): ?>
                            <img src="<?php echo htmlspecialchars($img_src, ENT_QUOTES, 'UTF-8'); ?>"
                                alt=""
                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <i class="fas fa-image" style="display:none" aria-hidden="true"></i>
                            <?php else: ?>
                            <i class="fas fa-box" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>
                        <div class="adm-delete-product-card__body">
                            <h3 class="adm-delete-product-card__name"><?php echo $nom_aff; ?></h3>
                            <ul class="adm-delete-product-meta">
                                <li>
                                    <strong>Prix</strong>
                                    <span><?php echo htmlspecialchars($prix_aff, ENT_QUOTES, 'UTF-8'); ?></span>
                                </li>
                                <li>
                                    <strong>Stock</strong>
                                    <span><?php echo $stock_aff; ?> unité<?php echo $stock_aff > 1 ? 's' : ''; ?></span>
                                </li>
                                <li style="grid-column: 1 / -1;">
                                    <strong>Catégorie</strong>
                                    <span><?php echo $cat_aff; ?></span>
                                </li>
                            </ul>
                        </div>
                    </article>

                    <form method="POST"
                        action="supprimer.php?id=<?php echo (int) $produit_id; ?>"
                        class="adm-delete-actions"
                        onsubmit="return confirm('Confirmer la suppression définitive de ce produit ?');">
                        <input type="hidden" name="confirm_delete" value="1">
                        <input type="hidden" name="id" value="<?php echo (int) $produit_id; ?>">
                        <button type="submit" class="adm-delete-btn adm-delete-btn--danger">
                            <i class="fas fa-trash-alt" aria-hidden="true"></i>
                            Confirmer la suppression
                        </button>
                        <a href="<?php echo htmlspecialchars($stock_list_url, ENT_QUOTES, 'UTF-8'); ?>"
                            class="adm-delete-btn adm-delete-btn--cancel">
                            <i class="fas fa-times" aria-hidden="true"></i>
                            Annuler
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
