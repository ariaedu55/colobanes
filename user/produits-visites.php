<?php
// URL d'image via les variantes optimisées (_md/_sm) : les originaux peuvent avoir été supprimés
if (!function_exists('upload_image_url_from_src')) {
    require_once __DIR__ . '/../includes/image_optimizer.php';
}
/**
 * Page des produits visités
 * Design identique à la page principale (index/produits)
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

require_once __DIR__ . '/../models/model_visites.php';
require_once __DIR__ . '/../includes/produit_boutique_line.php';
$produits_visites = get_produits_visites_by_user($_SESSION['user_id'], 50);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Produits Visités - COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">    <?php require_once dirname(__DIR__, 1) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .produits-visites-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .produits-visites-header {
            background: var(--couleur-dominante);
            padding: 30px 20px;
            text-align: center;
            color: var(--texte-clair);
            margin-bottom: 30px;
            border-radius: 12px;
        }

        .produits-visites-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .produits-visites-header p {
            font-size: 15px;
            opacity: 0.95;
        }

        .produits-visites .produit_vedetes {
            margin: 0;
        }

        /* Grille responsive (2 colonnes mobile → 5-6 desktop) au lieu d'une colonne centrée */
        .produits-visites .articles.carousel11 {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 0;
        }

        .produits-visites .carousel {
            position: relative;
            width: auto;
            max-width: none;
            min-width: 0;
            margin: 0;
        }

        .produits-visites .carousel .btn-add-cart {
            font-size: 13px;
        }

        @media (max-width: 767px) {
            .produits-visites-page {
                padding: 12px;
            }

            .produits-visites-header {
                padding: 18px 16px;
                margin-bottom: 14px;
            }

            .produits-visites-header h1 {
                font-size: 20px;
                margin-bottom: 4px;
            }

            .produits-visites-header p {
                font-size: 13px;
            }

            .produits-visites-page .content-section.produits-visites {
                padding: 0;
                background: transparent;
                border: 0;
                box-shadow: none;
            }
        }

        /* Date en haut-gauche : laisse la pastille « boutique » en haut-droite */
        .produits-visites .date-visite-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            right: auto;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            z-index: 5;
        }

        .produits-visites .date-visite-badge i {
            margin-right: 4px;
        }
    </style>
</head>

<body>
    <?php include 'includes/user_nav.php'; ?>

    <div class="produits-visites-page">
        <div class="produits-visites-header">
             <h1><i class="fas fa-eye"></i> Produits Visités</h1>
            <p>Historique de vos consultations (<?php echo count($produits_visites); ?>
                produit<?php echo count($produits_visites) > 1 ? 's' : ''; ?>)</p>
        </div>

        <?php if (isset($_GET['error'])): ?>
        <div style="max-width: 600px; margin: 20px auto; padding: 15px 25px; background: rgba(229, 72, 138, 0.15); border-left: 4px solid var(--couleur-dominante); border-radius: 8px; color: var(--titres);">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
        <?php endif; ?>
        <section class="content-section produits-visites">
            <?php if (empty($produits_visites)): ?>
                <div class="empty-state">
                    <i class="fas fa-eye-slash"></i>
                    <h3>Aucun produit visité</h3>
                    <p>Vous n'avez pas encore consulté de produits. Vos consultations apparaîtront ici.</p>
                    <a href="/produits.php" class="btn-primary">
                        <i class="fas fa-box"></i> Découvrir nos produits
                    </a>
                </div>
            <?php else: ?>
                <section class="produit_vedetes">
                    <article class="articles carousel11">
                        <?php foreach ($produits_visites as $produit): ?>
                            <?php
                            $prix_affichage = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix']
                                ? $produit['prix_promotion']
                                : $produit['prix'];
                            $has_promotion = !empty($produit['prix_promotion']) && $produit['prix_promotion'] < $produit['prix'];
                            ?>
                            <div class="carousel" data-produit-id="<?php echo $produit['id']; ?>">
                                <a href="/produit.php?id=<?php echo $produit['id']; ?>" class="product-card-link">
                                    <span class="date-visite-badge"
                                        title="Consulté le <?php echo date('d/m/Y à H:i', strtotime($produit['date_visite'])); ?>">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('d/m/Y', strtotime($produit['date_visite'])); ?>
                                    </span>
                                    <div class="image-wrapper">
                                        <img src="<?php echo htmlspecialchars(upload_image_url_from_src((string) ($produit['image_principale'] ?? ''), 'md'), ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="<?php echo htmlspecialchars($produit['nom'] ?? 'Produit'); ?>"
                                            onerror="this.src='/image/produit1.jpg'">
                                    </div>
                                    <div class="produit-content">
                                        <p id="nom"><?php echo htmlspecialchars($produit['nom'] ?? 'Produit sans nom'); ?></p>
                                        <?php echo produit_card_boutique_line_html($produit); ?>
                                        <?php if (!empty($produit['categorie_nom'])): ?>
                                            <p id="ville"><?php echo htmlspecialchars($produit['categorie_nom']); ?></p>
                                        <?php endif; ?>
                                        <p class="prix">
                                            <?php if ($has_promotion): ?>
                                                <span class="span2"><?php echo number_format($produit['prix'], 0, ',', ' '); ?>
                                                    FCFA</span>
                                                <span class="prix-promo"><?php echo number_format($prix_affichage, 0, ',', ' '); ?>
                                                    FCFA</span>
                                            <?php else: ?>
                                                <?php echo number_format($prix_affichage, 0, ',', ' '); ?><span class="span1">
                                                    FCFA</span>
                                            <?php endif; ?>
                                        </p>
                                        <?php if (!empty($produit['stock'])): ?>
                                            <p class="produit-card-stock-info">
                                                <strong>Stock:</strong> <?php echo $produit['stock']; ?>
                                                <?php if (!empty($produit['poids'])): ?>
                                                    (<?php echo htmlspecialchars($produit['poids']); ?>)
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <form method="POST" action="/add-to-panier.php" class="add-to-cart-form">
                                    <?php echo site_csrf_field(); ?>
                                    <input type="hidden" name="produit_id" value="<?php echo $produit['id']; ?>">
                                    <input type="hidden" name="quantite" value="1">
                                    <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/user/produits-visites.php'); ?>">
                                    <button type="submit" class="btn-add-cart">
                                        <i class="fa-solid fa-cart-shopping"></i> Ajouter au panier
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </article>
                </section>
            <?php endif; ?>
        </section>
    </div>

    <?php include 'includes/user_footer.php'; ?>
</body>

</html>