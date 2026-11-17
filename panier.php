<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

// Inclusion des modèles et contrôleurs
require_once __DIR__ . '/models/model_panier.php';
require_once __DIR__ . '/controllers/controller_panier.php';
require_once __DIR__ . '/includes/panier_invite.php';
require_once __DIR__ . '/includes/marketplace_helpers.php';
require_once __DIR__ . '/includes/flash_toast.php';
require_once __DIR__ . '/includes/image_optimizer.php';
require_once __DIR__ . '/includes/panier_code_promo.php';
require_once __DIR__ . '/includes/site_csrf.php';
require_once __DIR__ . '/includes/panier_pricing.php';
require_once __DIR__ . '/includes/produit_disponibilite.php';

$panier_user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$promo_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ((string) $_POST['action']) {
        case 'update':
            $result = process_update_panier();
            if (!empty($result['success'])) {
                http_redirect_safe('/panier.php');
            }
            flash_toast_queue_page('error', $result['message'] ?? 'Erreur lors de la mise à jour.');
            break;
        case 'delete':
            $result = process_delete_from_panier();
            if (!empty($result['success'])) {
                http_redirect_safe('/panier.php');
            }
            flash_toast_queue_page('error', $result['message'] ?? 'Erreur lors de la suppression.');
            break;
        case 'apply_promo':
            $result = process_apply_code_promo();
            if (!empty($result['success'])) {
                http_redirect_safe('/panier.php');
            }
            $promo_error = (string) ($result['message'] ?? 'Code promo invalide.');
            break;
        case 'remove_promo':
            process_remove_code_promo();
            http_redirect_safe('/panier.php');
            break;
    }
}

$panier_utilisateur_connecte = panier_utilisateur_est_connecte();

// Récupérer les produits du panier (BDD ou session invité)
$panier_items = panier_get_items_courant();
$panier_groups = group_panier_items_by_vendeur($panier_items);

// Calculer le total et le nombre total d'articles
$panier_total = panier_get_total_courant();
$nombre_total_articles = 0;
foreach ($panier_items as $item) {
    $nombre_total_articles += $item['quantite'];
}

$promo_applied = null;
$panier_remise = 0.0;
$panier_total_final = $panier_total;
if (!empty($panier_items)) {
    $promo_state = panier_code_promo_resolve($panier_total);
    if (!$promo_state['ok']) {
        $promo_error = $promo_error !== '' ? $promo_error : (string) ($promo_state['message'] ?? '');
    } elseif (!empty($promo_state['promo'])) {
        $promo_applied = $promo_state['promo'];
        $panier_remise = (float) ($promo_state['remise'] ?? 0);
        $panier_total_final = max(0, $panier_total - $panier_remise);
    }
}

// Inclusion du fichier de connexion à la BDD (pour les autres fonctionnalités si nécessaire)
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Mon Panier - COLObanes</title>    <?php require_once __DIR__ . '/includes/ui_font_awesome.php'; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/panier-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/checkout-v2.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/panier-accessoires.css?v=<?php echo (int) @filemtime(__DIR__ . '/css/panier-accessoires.css'); ?>">
</head>

<body class="panier-page co-body">

    <?php include('nav_bar.php') ?>

    <main class="co-page">
        <div class="co-shell">
            <?php $checkout_step = 1; require __DIR__ . '/includes/partials/checkout_steps.php'; ?>

        <?php if (empty($panier_items)): ?>
            <div class="co-empty" role="status" aria-live="polite">
                <span class="co-empty__icon" aria-hidden="true"><i class="fas fa-bag-shopping"></i></span>
                <h1 class="co-empty__title">Votre panier est vide</h1>
                <p class="co-empty__lead">Découvrez les produits des boutiques et ajoutez-les en un clic.</p>
                <div class="co-empty__actions">
                    <a href="/index.php" class="co-btn co-btn--primary"><i class="fas fa-store" aria-hidden="true"></i> Continuer mes achats</a>
                    <a href="/promo.php" class="co-btn co-btn--ghost">Voir les promos</a>
                </div>
            </div>
        <?php else: ?>
            <?php
            $panier_checkout_href = $panier_utilisateur_connecte
                ? '/commande.php'
                : '/choix-connexion.php?redirect=' . rawurlencode('/commande.php');
            $panier_checkout_label = $panier_utilisateur_connecte ? 'Passer la commande' : 'Se connecter pour commander';

            $panier_accessoires = ['produits' => [], 'chips' => [], 'title' => '', 'bundle_example' => ''];
            require_once __DIR__ . '/includes/panier_accessoires_engine.php';
            $panier_accessoires = panier_accessoires_resolve_for_cart($panier_items);
            /* Produits que les règles ne connaissent pas : l'IA prépare leurs compléments après l'affichage */
            $panier_accessoires_ia_attente = panier_accessoires_ia_a_preparer($panier_items, 1) !== [];
            if (!empty($panier_accessoires['produits']) && file_exists(__DIR__ . '/models/model_produits_avis.php')) {
                require_once __DIR__ . '/models/model_produits_avis.php';
                if (function_exists('produits_avis_enrich_products')) {
                    $panier_accessoires['produits'] = produits_avis_enrich_products($panier_accessoires['produits']);
                }
            }
            if (!empty($panier_accessoires['produits']) && file_exists(__DIR__ . '/includes/client_tracking.php')) {
                require_once __DIR__ . '/includes/client_tracking.php';
                if (function_exists('client_tracking_log_event')) {
                    client_tracking_log_event('panier_accessoires_vue', ['source' => 'panier']);
                }
            }
            $panier_nb_label = $nombre_total_articles . ' article' . ($nombre_total_articles > 1 ? 's' : '');
            ?>
            <div class="co-layout">
                <div class="co-main">
                    <?php foreach ($panier_groups as $g): ?>
                    <section class="co-card co-shop">
                        <header class="co-shop__head">
                            <span class="co-shop__icon" aria-hidden="true"><i class="fas fa-store"></i></span>
                            <?php if (!empty($g['slug'])): ?>
                                <a class="co-shop__name" href="<?php echo htmlspecialchars(boutique_url('index.php', $g['slug'])); ?>"><?php echo htmlspecialchars($g['label']); ?></a>
                            <?php else: ?>
                                <span class="co-shop__name"><?php echo htmlspecialchars($g['label']); ?></span>
                            <?php endif; ?>
                            <span class="co-shop__count"><?php echo count($g['items']); ?> produit<?php echo count($g['items']) > 1 ? 's' : ''; ?></span>
                        </header>

                        <?php foreach ($g['items'] as $item):
                            /* Prix unitaire recalculé côté serveur (catalogue ou négociation validée) */
                            $prix_unitaire = panier_resolve_unit_price_for_line($item, $panier_user_id);
                            $prix_total_item = $prix_unitaire * $item['quantite'];
                            $item_img = !empty($item['panier_variante_image']) ? $item['panier_variante_image'] : ($item['image_principale'] ?? '');
                            $item_nom = !empty($item['panier_variante_nom'])
                                ? $item['nom'] . ' → ' . $item['panier_variante_nom']
                                : ($item['nom'] ?? '');
                            $item_meta_parts = [];
                            if (!empty(trim($item['panier_poids'] ?? ''))) {
                                $item_meta_parts[] = 'Poids : ' . trim($item['panier_poids']);
                            } elseif (!empty(trim($item['poids'] ?? ''))) {
                                $item_meta_parts[] = 'Poids : ' . trim($item['poids']);
                            }
                            if (!empty(trim($item['panier_taille'] ?? ''))) {
                                $item_meta_parts[] = 'Taille : ' . trim($item['panier_taille']);
                            }
                            $item_meta = implode(' · ', $item_meta_parts);
                            $has_promo = !empty($item['prix_promotion'])
                                && (float) $item['prix_promotion'] < (float) $item['prix']
                                && abs($prix_unitaire - (float) $item['prix_promotion']) < 0.01;
                            $item_dispo = panier_ligne_est_disponible($item);
                            $item_produit_id = (int) ($item['produit_id'] ?? $item['id'] ?? 0);
                        ?>
                        <article class="co-item<?php echo $item_dispo ? '' : ' co-item--indispo'; ?>" data-item-id="<?php echo (int) $item['panier_id']; ?>">
                            <form method="POST" action="" class="co-item__remove-form panier-delete-form">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="panier_id" value="<?php echo (int) $item['panier_id']; ?>">
                                <?php echo site_csrf_field(); ?>
                                <button type="submit" class="co-item__remove" aria-label="Retirer du panier" title="Retirer">×</button>
                            </form>
                            <a class="co-item__img" href="/produit.php?id=<?php echo $item_produit_id; ?>">
                                <img src="<?php echo htmlspecialchars(upload_image_url((string) $item_img, 'sm'), ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?php echo htmlspecialchars((string) $item_nom, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy"
                                    onerror="this.src='/image/produit1.jpg'">
                            </a>
                            <div class="co-item__body">
                                <div class="co-item__top">
                                    <h3 class="co-item__name"><a href="/produit.php?id=<?php echo $item_produit_id; ?>"><?php echo htmlspecialchars((string) $item_nom, ENT_QUOTES, 'UTF-8'); ?></a></h3>
                                </div>
                                <?php if ($item_meta !== ''): ?>
                                    <p class="co-item__meta"><?php echo htmlspecialchars($item_meta, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <p class="co-item__unit">
                                    <?php echo number_format($prix_unitaire, 0, ',', ' '); ?> FCFA
                                    <?php if ($has_promo): ?><s><?php echo number_format((float) $item['prix'], 0, ',', ' '); ?></s><span class="co-badge">Promo</span><?php endif; ?>
                                </p>
                                <?php if (!$item_dispo): ?>
                                    <p class="co-item__indispo" role="alert">
                                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                                        <?php echo htmlspecialchars(produit_indisponibilite_message($item), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($item_produit_id > 0): ?>
                                            <a href="/produit.php?id=<?php echo $item_produit_id; ?>#alternatives-disponibles">Voir des alternatives</a>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <div class="co-item__bottom">
                                    <form method="POST" action="" class="update-form panier-update-form">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="panier_id" value="<?php echo (int) $item['panier_id']; ?>">
                                        <?php echo site_csrf_field(); ?>
                                        <div class="quantite-controls co-qty">
                                            <button type="button" class="quantite-btn decrease-btn" aria-label="Diminuer la quantité"<?php echo (int) $item['quantite'] <= 1 ? ' disabled' : ''; ?>>−</button>
                                            <input type="number" name="quantite" class="quantite-input" value="<?php echo (int) $item['quantite']; ?>" min="1" required aria-label="Quantité">
                                            <button type="button" class="quantite-btn increase-btn" aria-label="Augmenter la quantité">+</button>
                                        </div>
                                    </form>
                                    <strong class="co-item__total"><?php echo number_format($prix_total_item, 0, ',', ' '); ?> FCFA</strong>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </section>
                    <?php endforeach; ?>

                    <?php require __DIR__ . '/includes/partials/panier_accessoires_block.php'; ?>
                    <?php if (!empty($panier_accessoires_ia_attente)): ?>
                        <div id="panierAccessoiresIa" data-api="/api/suggestions_complementaires.php" hidden></div>
                    <?php endif; ?>

                    <a href="/index.php" class="co-continue"><i class="fas fa-arrow-left" aria-hidden="true"></i> Continuer mes achats</a>
                </div>

                <aside class="co-aside" aria-label="Résumé du panier">
                    <div class="co-card co-summary">
                        <h2 class="co-summary__title">Récapitulatif</h2>

                        <div class="co-promo">
                            <?php if ($promo_applied): ?>
                                <div class="co-promo__applied">
                                    <i class="fas fa-ticket" aria-hidden="true"></i>
                                    <span>Code <strong><?php echo htmlspecialchars((string) $promo_applied['code'], ENT_QUOTES, 'UTF-8'); ?></strong> appliqué<?php if (($promo_applied['type_remise'] ?? '') === 'pourcentage'): ?> (−<?php echo htmlspecialchars((string) $promo_applied['valeur'], ENT_QUOTES, 'UTF-8'); ?> %)<?php endif; ?></span>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="remove_promo">
                                        <?php echo site_csrf_field(); ?>
                                        <button type="submit" class="co-promo__remove" aria-label="Retirer le code promo">×</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <details class="co-promo__toggle"<?php echo $promo_error !== '' ? ' open' : ''; ?>>
                                    <summary><i class="fas fa-ticket" aria-hidden="true"></i> Vous avez un code promo ?</summary>
                                    <form method="POST" action="" class="co-promo__form" id="panierPromoForm">
                                        <input type="hidden" name="action" value="apply_promo">
                                        <?php echo site_csrf_field(); ?>
                                        <input type="text" name="code_promo" class="panier-promo-form__input" placeholder="Saisir le code"
                                            autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="64"
                                            value="<?php echo isset($_POST['code_promo']) ? htmlspecialchars(trim((string) $_POST['code_promo']), ENT_QUOTES, 'UTF-8') : ''; ?>">
                                        <button type="submit" class="co-btn co-btn--dark">Appliquer</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                            <?php if ($promo_error !== ''): ?>
                                <p class="co-promo__error" role="alert"><?php echo htmlspecialchars($promo_error, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                        </div>

                        <dl class="co-totals">
                            <div><dt>Sous-total (<?php echo $panier_nb_label; ?>)</dt><dd><?php echo number_format($panier_total, 0, ',', ' '); ?> FCFA</dd></div>
                            <?php if ($panier_remise > 0 && $promo_applied): ?>
                                <div class="co-totals__remise"><dt>Réduction</dt><dd>−<?php echo number_format($panier_remise, 0, ',', ' '); ?> FCFA</dd></div>
                            <?php endif; ?>
                            <div><dt>Livraison</dt><dd class="co-totals__muted">Calculée à l’étape suivante</dd></div>
                            <div class="co-totals__grand"><dt>Total</dt><dd><?php echo number_format($panier_total_final, 0, ',', ' '); ?> FCFA</dd></div>
                        </dl>

                        <a href="<?php echo htmlspecialchars($panier_checkout_href, ENT_QUOTES, 'UTF-8'); ?>" class="co-btn co-btn--primary co-btn--block co-btn--lg">
                            <?php echo htmlspecialchars($panier_checkout_label, ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </aside>
            </div>

            <div class="co-mobilebar" aria-hidden="false">
                <div class="co-mobilebar__total"><small>Total</small><strong><?php echo number_format($panier_total_final, 0, ',', ' '); ?> FCFA</strong></div>
                <a href="<?php echo htmlspecialchars($panier_checkout_href, ENT_QUOTES, 'UTF-8'); ?>" class="co-btn co-btn--primary"><?php echo $panier_utilisateur_connecte ? 'Commander' : 'Se connecter'; ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </div>
        <?php endif; ?>
        </div>
    </main>

    <?php include('footer.php') ?>


    <script>
        function submitPanierQuantiteForm(form) {
            if (!form) return;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }

        /* Téléphone : la barre « Total » s'efface quand le récapitulatif (et son bouton) est à l'écran */
        window.panierObserveRecap = function () {
            var recap = document.querySelector('.co-summary');
            if (window.__panierRecapObs) {
                window.__panierRecapObs.disconnect();
            }
            if (!recap || !('IntersectionObserver' in window)) {
                return;
            }
            window.__panierRecapObs = new IntersectionObserver(function (entries) {
                var visible = entries[0].isIntersecting;
                document.body.classList.toggle('co-recap-visible', visible);
                var bar = document.querySelector('.co-mobilebar');
                if (bar) {
                    bar.setAttribute('aria-hidden', visible ? 'true' : 'false');
                }
            }, { threshold: 0.2 });
            window.__panierRecapObs.observe(recap);
        };

        /* Liaison des contrôles du panier — rappelée après un ajout d'accessoire (contenu remplacé sans rechargement) */
        window.panierBindControls = function (root) {
        root = root || document;
        window.panierObserveRecap();
        root.querySelectorAll('.increase-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const form = this.closest('.panier-update-form');
                const input = this.parentElement.querySelector('.quantite-input');
                let value = parseInt(input.value, 10) || 1;
                value++;
                input.value = value;
                submitPanierQuantiteForm(form);
            });
        });

        root.querySelectorAll('.decrease-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const form = this.closest('.panier-update-form');
                const input = this.parentElement.querySelector('.quantite-input');
                let value = parseInt(input.value, 10) || 1;
                if (value > 1) {
                    value--;
                    input.value = value;
                    submitPanierQuantiteForm(form);
                }
            });
        });

        root.querySelectorAll('.panier-update-form .quantite-input').forEach(input => {
            input.addEventListener('change', function () {
                let value = parseInt(this.value, 10) || 1;
                if (value < 1) value = 1;
                this.value = value;
                submitPanierQuantiteForm(this.closest('.panier-update-form'));
            });
        });

        var promoForm = root.querySelector('#panierPromoForm');
        if (promoForm) {
            var promoInput = promoForm.querySelector('.panier-promo-form__input');
            if (promoInput) {
                promoInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (promoInput.value.trim().length >= 2) {
                            submitPanierQuantiteForm(promoForm);
                        }
                    }
                });
                promoInput.addEventListener('change', function () {
                    if (promoInput.value.trim().length >= 2) {
                        submitPanierQuantiteForm(promoForm);
                    }
                });
            }
        }
        };
        window.panierBindControls(document);
    </script>
    <script src="/js/panier-accessoires.js?v=<?php echo (int) @filemtime(__DIR__ . '/js/panier-accessoires.js'); ?>" defer></script>

</body>

</html>