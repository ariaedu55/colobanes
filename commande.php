<?php
/**
 * Page de commande
 * Programmation procédurale uniquement
 */

ob_start();

require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/marketplace_helpers.php';
require_once __DIR__ . '/includes/app_base.php';
require_once __DIR__ . '/includes/boutique_slug_redirect.php';
require_once __DIR__ . '/models/model_admin.php';

$commande_boutique_slug = trim((string) ($_GET['boutique'] ?? ''));
$commande_boutique_admin = null;
if ($commande_boutique_slug !== '') {
    $commande_boutique_admin = boutique_resolve_vendeur_web($commande_boutique_slug);
    if (!$commande_boutique_admin) {
        header('Location: ' . app_url('/panier.php'));
        exit;
    }
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/auth_redirect.php';
    auth_redirect_to_site_home();
}

// Inclusion des modèles et contrôleurs
require_once __DIR__ . '/models/model_panier.php';
require_once __DIR__ . '/models/model_users.php';
require_once __DIR__ . '/controllers/controller_commandes.php';
require_once __DIR__ . '/includes/site_csrf.php';

// Traitement du formulaire
$message = '';
$message_type = '';
$commande_id = null;
$numero_commande = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_commande') {
    $result = process_create_commande();

    if ($result['success']) {
        $nums = !empty($result['numeros_commandes']) ? $result['numeros_commandes'] : [$result['numero_commande']];
        /* Page de confirmation dédiée (vérifie que les commandes appartiennent bien au client) */
        $redirect_url = app_url('/commande-confirmee.php?numeros=' . urlencode(implode(',', $nums)));

        $notification_payload = [
            'notifications' => $result['notifications'] ?? [],
            'email_data' => $result['email_data'] ?? null,
            'numeros_commandes' => $nums,
            'numero_commande' => $result['numero_commande'] ?? '',
            'user_id' => (int) $_SESSION['user_id'],
            'user_email' => trim($_SESSION['user_email'] ?? ''),
        ];

        require_once __DIR__ . '/includes/commande_post_create_deferred.php';
        require_once __DIR__ . '/includes/produit_post_create_deferred.php';
        admin_redirect_then(static function () use ($notification_payload) {
            commande_run_deferred_notifications($notification_payload);
        }, $redirect_url);
    } else {
        $message = $result['message'];
        $message_type = 'error';
    }
}

// Récupérer les informations de l'utilisateur
$user = get_user_by_id($_SESSION['user_id']);

// Position déjà connue : session (fraîche) en priorité, sinon dernière position BDD
require_once __DIR__ . '/includes/geo_location_service.php';
$geo_saved = geo_session_get_location();
if ($geo_saved === null && $user) {
    $u_lat = geo_parse_coord($user['last_latitude'] ?? null);
    $u_lng = geo_parse_coord($user['last_longitude'] ?? null);
    if (geo_coords_valid($u_lat, $u_lng)) {
        $geo_saved = [
            'lat' => $u_lat,
            'lng' => $u_lng,
            'precision' => geo_parse_precision($user['last_geo_precision'] ?? null),
        ];
    }
}

// Pays marketplace pour filtrer la recherche d'adresse (Sénégal, CI, Gabon…)
$geo_search_country_iso = geo_search_country_nominatim($commande_boutique_admin);
$geo_search_country_label = geo_search_country_label($commande_boutique_admin);

// Récupérer les produits du panier (éventuellement limités à une boutique)
$panier_items = get_panier_by_user($_SESSION['user_id']);
if ($commande_boutique_admin) {
    $panier_items = filter_panier_items_by_vendeur($panier_items, (int) $commande_boutique_admin['id']);
}

// Vérifier que le panier n'est pas vide
if (empty($panier_items)) {
    if ($commande_boutique_admin && $commande_boutique_slug !== '') {
        header('Location: ' . boutique_url('panier.php', $commande_boutique_slug));
    } else {
        header('Location: ' . app_url('/panier.php'));
    }
    exit;
}

// Calculer le total (sous-ensemble affiché)
$panier_total = panier_items_sous_total($panier_items);
$nombre_total_articles = panier_items_count_quantites($panier_items);

require_once __DIR__ . '/includes/panier_code_promo.php';
require_once __DIR__ . '/includes/panier_pricing.php';
$promo_state = panier_code_promo_resolve($panier_total);
$panier_remise = 0.0;
$panier_total_final = $panier_total;
$promo_applied = null;
if (!empty($promo_state['ok']) && !empty($promo_state['promo'])) {
    $promo_applied = $promo_state['promo'];
    $panier_remise = (float) ($promo_state['remise'] ?? 0);
    $panier_total_final = max(0, $panier_total - $panier_remise);
}

require_once __DIR__ . '/includes/commande_mode_helpers.php';
require_once __DIR__ . '/includes/commande_livraison_summary.php';
require_once __DIR__ . '/includes/image_optimizer.php';
$commande_pickup_boutiques = commande_pickup_boutiques_from_panier($panier_items);
$commande_mode_selected = commande_mode_livraison_normalize($_POST['mode_livraison'] ?? 'livraison');
$commande_livraison_offerte = commande_affiche_badge_livraison_offerte(
    $panier_items,
    $commande_boutique_admin ? (int) $commande_boutique_admin['id'] : null
);
$panier_user_id = (int) $_SESSION['user_id'];
$panier_retour_href = ($commande_boutique_admin && $commande_boutique_slug !== '')
    ? boutique_url('panier.php', $commande_boutique_slug)
    : '/panier.php';
$nombre_articles_label = $nombre_total_articles . ' article' . ($nombre_total_articles > 1 ? 's' : '');

// Contexte badge panier (commande limitée à une boutique)
if ($commande_boutique_admin) {
    $GLOBALS['nav_panier_count_for_vendeur_id'] = (int) $commande_boutique_admin['id'];
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Passer la commande - COLObanes</title>
    <?php require_once __DIR__ . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-page.css?v=<?php echo (int) @filemtime(__DIR__ . '/css/commande-page.css'); ?>">
    <link rel="stylesheet" href="/css/checkout-v2.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-v3.css?v=<?php echo (int) @filemtime(__DIR__ . '/css/commande-v3.css'); ?>">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <?php require_once __DIR__ . '/includes/auth_intl_tel_head.php'; ?>
</head>

<body class="commande-page co-body">
    <?php include 'nav_bar.php'; ?>

    <div class="cmd-page">
        <?php $checkout_step = 2; require __DIR__ . '/includes/partials/checkout_steps.php'; ?>
        <header class="cmd-head">
            <a class="cmd-head__back" href="<?php echo htmlspecialchars($panier_retour_href, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Retour au panier">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
            </a>
            <h1 class="cmd-head__title">Finaliser la commande</h1>
        </header>

        <?php if ($message): ?>
            <div class="cmd-alert cmd-alert--<?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="form-commande" class="cmd-layout">
            <input type="hidden" name="action" value="create_commande">
            <?php echo site_csrf_field(); ?>
            <input type="hidden" name="mode_livraison" id="mode_livraison"
                value="<?php echo htmlspecialchars($commande_mode_selected, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($commande_boutique_admin && $commande_boutique_slug !== ''): ?>
                <input type="hidden" name="boutique_slug"
                    value="<?php echo htmlspecialchars($commande_boutique_slug, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>

            <div class="cmd-main">
                <section class="cmd-card" aria-label="Réception et contact">
                    <div class="cmd-mode" role="group" aria-label="Choisir le mode de réception">
                        <button type="button"
                            class="cmd-mode-btn<?php echo $commande_mode_selected === 'livraison' ? ' is-active' : ''; ?>"
                            data-mode="livraison" id="cmd-mode-btn-livraison">
                            <i class="fas fa-motorcycle" aria-hidden="true"></i>
                            <strong>Livraison</strong>
                        </button>
                        <button type="button"
                            class="cmd-mode-btn<?php echo $commande_mode_selected === 'retrait' ? ' is-active' : ''; ?>"
                            data-mode="retrait" id="cmd-mode-btn-retrait">
                            <i class="fas fa-store" aria-hidden="true"></i>
                            <strong>Retrait en boutique</strong>
                        </button>
                    </div>

                    <div id="panel-retrait"
                        class="cmd-mode-panel<?php echo $commande_mode_selected === 'retrait' ? ' is-visible' : ''; ?>">
                        <div class="cmd-pickup-list">
                            <?php if (empty($commande_pickup_boutiques)): ?>
                                <p class="cmd-pickup-hint">Adresse de la boutique indisponible.</p>
                            <?php else: ?>
                                <?php foreach ($commande_pickup_boutiques as $boutique_pickup): ?>
                                    <div class="cmd-pickup-card">
                                        <p class="cmd-pickup-card__name">
                                            <i class="fas fa-store" aria-hidden="true"></i>
                                            <?php echo htmlspecialchars($boutique_pickup['nom'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <?php if (!empty($boutique_pickup['adresse_ligne'])): ?>
                                            <p class="cmd-pickup-card__addr"><?php echo htmlspecialchars($boutique_pickup['adresse_ligne'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($boutique_pickup['telephone'])): ?>
                                            <p class="cmd-pickup-card__tel">
                                                <i class="fas fa-phone" aria-hidden="true"></i>
                                                <?php echo htmlspecialchars($boutique_pickup['telephone'], ENT_QUOTES, 'UTF-8'); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($boutique_pickup['maps_url'])): ?>
                                            <p class="cmd-pickup-card__maps">
                                                <a href="<?php echo htmlspecialchars($boutique_pickup['maps_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    target="_blank" rel="noopener noreferrer">
                                                    <i class="fas fa-map-location-dot" aria-hidden="true"></i> Itinéraire
                                                </a>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="panel-livraison"
                        class="cmd-mode-panel<?php echo $commande_mode_selected === 'livraison' ? ' is-visible' : ''; ?>">
                        <input type="hidden" name="geo_lat" id="geo_lat"
                            value="<?php echo $geo_saved ? htmlspecialchars(number_format($geo_saved['lat'], 8, '.', ''), ENT_QUOTES, 'UTF-8') : ''; ?>">
                        <input type="hidden" name="geo_lng" id="geo_lng"
                            value="<?php echo $geo_saved ? htmlspecialchars(number_format($geo_saved['lng'], 8, '.', ''), ENT_QUOTES, 'UTF-8') : ''; ?>">
                        <input type="hidden" name="geo_precision" id="geo_precision"
                            value="<?php echo $geo_saved && $geo_saved['precision'] !== null ? (int) $geo_saved['precision'] : ''; ?>">
                        <input type="hidden" name="geo_source" id="geo_source"
                            value="<?php echo $geo_saved ? 'gps' : ''; ?>">

                        <div class="cmd-geo">
                            <div class="cmd-search geo-search-wrap">
                                <label class="geo-search-label" for="geo_address_search">Adresse</label>
                                <div class="cmd-search__wrap geo-search-input-wrap">
                                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                    <input type="text" id="geo_address_search" class="cmd-search__input geo-search-input"
                                        autocomplete="off"
                                        placeholder="Quartier, rue, marché, ville…">
                                </div>
                                <div id="geo_search_suggestions" class="geo-search-suggestions" aria-hidden="true"></div>
                            </div>

                            <div id="geo-map" class="geo-map" style="display:none;"></div>

                            <div class="cmd-geo__actions">
                                <button type="button" id="btn-geo-capture" class="btn-geo-capture">
                                    <i class="fas fa-location-crosshairs" aria-hidden="true"></i>
                                    Ma position
                                </button>
                            </div>
                            <div id="geo-status" class="geo-status" style="display:none;"></div>
                        </div>

                        <div class="cmd-field" id="group-adresse-livraison">
                            <label for="adresse_livraison">Précisions <span>(facultatif)</span></label>
                            <textarea id="adresse_livraison" name="adresse_livraison" rows="2"
                                placeholder="Étage, villa, point de repère…"><?php echo isset($_POST['adresse_livraison']) ? htmlspecialchars($_POST['adresse_livraison'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                        </div>
                    </div>

                    <div class="cmd-field cmd-field--tel">
                        <label for="telephone_livraison"><span id="tel-label-text">Téléphone</span> *</label>
                        <input type="tel" id="telephone_livraison" name="telephone_livraison" required
                            autocomplete="tel" placeholder="+221 XX XXX XX XX"
                            value="<?php echo isset($_POST['telephone_livraison']) ? htmlspecialchars($_POST['telephone_livraison'], ENT_QUOTES, 'UTF-8') : htmlspecialchars((string) ($user['telephone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </section>

                <button type="submit" class="cmd-submit cmd-submit--mobile">
                    <i class="fas fa-check" aria-hidden="true"></i>
                    Confirmer · <?php echo number_format($panier_total_final, 0, ',', ' '); ?> FCFA
                </button>
            </div>

            <aside class="cmd-aside">
                <div class="cmd-recap">
                    <h2 class="cmd-recap__title">Votre commande</h2>
                    <div class="cmd-recap-items">
                        <?php foreach ($panier_items as $item): ?>
                            <?php
                            $prix_unitaire = panier_resolve_unit_price_for_line($item, $panier_user_id);
                            $prix_total_item = $prix_unitaire * (int) ($item['quantite'] ?? 1);
                            $item_img = !empty($item['panier_variante_image']) ? $item['panier_variante_image'] : ($item['image_principale'] ?? '');
                            $item_nom = !empty($item['panier_variante_nom'])
                                ? $item['nom'] . ' → ' . $item['panier_variante_nom']
                                : ($item['nom'] ?? '');
                            $item_qty = (int) ($item['quantite'] ?? 1);
                            ?>
                            <article class="cmd-recap-item">
                                <img src="<?php echo htmlspecialchars(upload_image_url((string) $item_img, 'sm'), ENT_QUOTES, 'UTF-8'); ?>"
                                    alt=""
                                    onerror="this.src='/image/produit1.jpg'">
                                <div class="cmd-recap-item__body">
                                    <p class="cmd-recap-item__name"><?php echo htmlspecialchars((string) $item_nom, ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php if ($item_qty > 1): ?>
                                        <p class="cmd-recap-item__meta">× <?php echo $item_qty; ?></p>
                                    <?php endif; ?>
                                    <span class="cmd-recap-item__price"><?php echo number_format($prix_total_item, 0, ',', ' '); ?> FCFA</span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="cmd-recap-totals">
                        <?php if ($panier_remise > 0): ?>
                            <div class="cmd-recap-row">
                                <span>Sous-total</span>
                                <span><?php echo number_format($panier_total, 0, ',', ' '); ?> FCFA</span>
                            </div>
                            <div class="cmd-recap-row cmd-recap-row--promo">
                                <span>Remise<?php echo !empty($promo_applied['code']) ? ' (' . htmlspecialchars((string) $promo_applied['code'], ENT_QUOTES, 'UTF-8') . ')' : ''; ?></span>
                                <span>− <?php echo number_format($panier_remise, 0, ',', ' '); ?> FCFA</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($commande_livraison_offerte): ?>
                            <div class="cmd-recap-row cmd-recap-row--free" id="summary-livraison-note">
                                <span>Livraison</span>
                                <span>Offerte</span>
                            </div>
                        <?php endif; ?>
                        <div class="cmd-recap-row cmd-recap-row--total">
                            <span>Total</span>
                            <strong id="summary-total-amount"><?php echo number_format($panier_total_final, 0, ',', ' '); ?> FCFA</strong>
                        </div>
                        <button type="submit" class="cmd-submit cmd-submit--desktop" id="btn-submit-commande">
                            <i class="fas fa-lock" aria-hidden="true"></i>
                            Confirmer la commande
                        </button>
                        <p class="cmd-paynote"><i class="fas fa-hand-holding-dollar" aria-hidden="true"></i> Paiement à la réception</p>
                    </div>
                </div>
            </aside>
        </form>
    </div>

    <div id="cmdSubmitOverlay" class="cmd-submit-overlay" hidden aria-hidden="true" role="alertdialog" aria-live="polite" aria-labelledby="cmdSubmitOverlayTitle" aria-describedby="cmdSubmitOverlayDesc">
        <div class="cmd-submit-overlay__card">
            <div class="cmd-submit-overlay__icon" aria-hidden="true"><i class="fas fa-circle-notch"></i></div>
            <h3 class="cmd-submit-overlay__title" id="cmdSubmitOverlayTitle">Envoi de la commande&hellip;</h3>
            <p class="cmd-submit-overlay__subtitle" id="cmdSubmitOverlayDesc" hidden>Veuillez patienter.</p>
            <div class="cmd-submit-overlay__track" aria-hidden="true">
                <div class="cmd-submit-overlay__bar" id="cmdSubmitOverlayBar"></div>
            </div>
            <div class="cmd-submit-overlay__pct" id="cmdSubmitOverlayPct">0&nbsp;%</div>
        </div>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>

    <?php require_once __DIR__ . '/includes/auth_intl_tel_scripts.php'; ?>
    <?php require_once __DIR__ . '/includes/geo_native_bridge_script.php'; ?>
    <script src="/js/geo-address-format.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-location.js?v=<?php echo (int) @filemtime(__DIR__ . '/js/geo-location.js'); ?>"></script>
    <script src="/js/geo-location-search.js<?php echo asset_version_query(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modeInput = document.getElementById('mode_livraison');
            var panelRetrait = document.getElementById('panel-retrait');
            var panelLivraison = document.getElementById('panel-livraison');
            var modeBtns = document.querySelectorAll('.cmd-mode-btn');

            function refreshMapSize() {
                if (window.GeoLocationCapture && window.GeoLocationCapture.map) {
                    setTimeout(function () {
                        window.GeoLocationCapture.map.invalidateSize();
                    }, 80);
                }
            }

            function setCommandeMode(mode) {
                mode = mode === 'retrait' ? 'retrait' : 'livraison';
                if (modeInput) modeInput.value = mode;
                modeBtns.forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.getAttribute('data-mode') === mode);
                });
                if (panelRetrait) panelRetrait.classList.toggle('is-visible', mode === 'retrait');
                if (panelLivraison) panelLivraison.classList.toggle('is-visible', mode === 'livraison');
                if (mode === 'livraison') refreshMapSize();
            }

            modeBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setCommandeMode(btn.getAttribute('data-mode'));
                });
            });

            if (typeof window.initAuthIntlTel === 'function') {
                window.initAuthIntlTel('telephone_livraison');
            }
            var addrField = document.getElementById('adresse_livraison');
            if (addrField) {
                addrField.addEventListener('input', function () {
                    delete addrField.dataset.geoAutofilled;
                });
            }
            if (window.GeoLocationCapture) {
                window.GeoLocationCapture.init({
                    latInput: 'geo_lat',
                    lngInput: 'geo_lng',
                    precisionInput: 'geo_precision',
                    sourceInput: 'geo_source',
                    statusEl: 'geo-status',
                    button: 'btn-geo-capture',
                    mapContainer: 'geo-map',
                    addressInput: 'adresse_livraison',
                    <?php if ($geo_saved): ?>
                    initial: {
                            lat: <?php echo json_encode((float) $geo_saved['lat']); ?>,
                            lng: <?php echo json_encode((float) $geo_saved['lng']); ?>,
                            precision: <?php echo json_encode($geo_saved['precision'] !== null ? (float) $geo_saved['precision'] : null); ?>
                        },
                    <?php endif; ?>
                auto: true
                });
            }
            if (window.GeoLocationSearch) {
                window.GeoLocationSearch.init({
                    searchInput: 'geo_address_search',
                    suggestionsList: 'geo_search_suggestions',
                    countryCode: <?php echo json_encode($geo_search_country_iso); ?>,
                    countryLabel: <?php echo json_encode($geo_search_country_label); ?>
                });
            }
        });
    </script>
    <script>
        (function () {
            var panierTotal = <?php echo json_encode((float) $panier_total_final); ?>;
            var spanTotalAmount = document.getElementById('summary-total-amount');

            function formatNumber(n) {
                return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
            }

            if (spanTotalAmount) {
                spanTotalAmount.textContent = formatNumber(panierTotal);
            }
        })();
    </script>
    <script>
        (function () {
            var form = document.getElementById('form-commande');
            var submitBtn = document.getElementById('btn-submit-commande');
            var overlay = document.getElementById('cmdSubmitOverlay');
            var bar = document.getElementById('cmdSubmitOverlayBar');
            var pct = document.getElementById('cmdSubmitOverlayPct');
            var locked = false;
            var progressTimer = null;
            var progressValue = 0;

            function showOverlay() {
                if (!overlay) return;
                overlay.removeAttribute('hidden');
                overlay.classList.add('is-visible');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('cmd-submit-busy');
            }

            function updateProgress(value) {
                progressValue = Math.max(progressValue, Math.min(98, value));
                if (bar) bar.style.width = progressValue + '%';
                if (pct) pct.textContent = Math.round(progressValue) + '\u00a0%';
            }

            function startProgress() {
                progressValue = 0;
                updateProgress(8);
                if (progressTimer) clearInterval(progressTimer);
                progressTimer = setInterval(function () {
                    if (progressValue >= 92) return;
                    var step = progressValue < 40 ? 4 + Math.random() * 5
                        : progressValue < 75 ? 2 + Math.random() * 3
                        : 0.4 + Math.random() * 1.2;
                    updateProgress(progressValue + step);
                }, 420);
            }

            function lockSubmit() {
                locked = true;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.setAttribute('aria-busy', 'true');
                }
                if (form) {
                    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (el) {
                        el.disabled = true;
                    });
                }
                showOverlay();
                startProgress();
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    if (locked) {
                        e.preventDefault();
                        return;
                    }
                    if (!form.checkValidity()) {
                        return;
                    }
                    lockSubmit();
                });
            }
        })();
    </script>
</body>

</html>
