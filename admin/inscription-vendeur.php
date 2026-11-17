<?php
require_once __DIR__ . '/../includes/session_admin.php';
/**
 * Inscription vendeur — création boutique (sans session admin requise).
 */
session_start_persistent();
require_once __DIR__ . '/../includes/admin_csrf.php';
require_once __DIR__ . '/../includes/google_auth_coop.php';
require_once __DIR__ . '/../includes/auth_redirect.php';

if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . '/../controllers/controller_admin.php';

if (isset($_SESSION['admin_id'])) {
    auth_redirect_after_login('/admin/dashboard.php');
}

admin_csrf_token();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    admin_csrf_enforce_post();
}

$result = process_inscription_vendeur();
if (!empty($result['success'])) {
    $_SESSION['inscription_success'] = $result['message'];
    auth_redirect_after_login('/choix-connexion.php');
}

$err = (!empty($result['message']) && empty($result['success'])) ? $result['message'] : '';

require_once __DIR__ . '/../includes/asset_version.php';
require_once __DIR__ . '/../includes/site_url.php';
$url_choix_connexion = get_site_base_url() . '/choix-connexion.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ouvrir ma boutique — inscription vendeur</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-connexion.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-login-v2.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/mc-v2-shop-promo.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/../includes/auth_intl_tel_head.php'; ?>
    <?php require_once dirname(__DIR__, 1) . '/includes/ui_font_awesome.php'; ?>
</head>
<body class="auth-page auth-page--vendor page-inscription-vendeur al-page">
    <div class="auth-hub-shell">
        <?php
        $mc_v2_shop_promo_id = 'auth-shop-promo-title';
        include __DIR__ . '/../includes/mc_v2_shop_promo.php';
        ?>

        <main class="al-wrap">
            <div class="al-card">
                <a class="al-logo" href="/index.php" aria-label="Retour à l'accueil COLObanes">
                    <img src="/image/logo_market.webp" alt="COLObanes" width="220" height="60">
                </a>

                <div class="al-head">
                    <h1 class="al-title">Ouvrir ma boutique</h1>
                </div>

                <div class="al-messages">
                    <?php if ($err): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $err; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="post" action="" class="al-form auth-inscription-form" id="inscriptionVendeurForm">
<?php echo function_exists('protection_champs_formulaire') ? protection_champs_formulaire() : ''; ?>
                    <?php echo admin_csrf_field(); ?>
                    <label class="visually-hidden" for="identite">Votre nom (affiché)</label>
                    <div class="al-field">
                        <input type="text" id="identite" name="identite" placeholder="Votre nom" required maxlength="200" autocomplete="name"
                            value="<?php echo isset($_POST['identite']) ? htmlspecialchars($_POST['identite']) : ''; ?>">
                    </div>

                    <label class="visually-hidden" for="boutique_nom">Nom de la boutique</label>
                    <div class="al-field">
                        <input type="text" id="boutique_nom" name="boutique_nom" placeholder="Nom de la boutique" required maxlength="255" autocomplete="organization"
                            value="<?php echo isset($_POST['boutique_nom']) ? htmlspecialchars($_POST['boutique_nom']) : ''; ?>">
                    </div>

                    <?php
                    require_once __DIR__ . '/../includes/marketplace_countries.php';
                    require_once __DIR__ . '/../includes/geo_regions.php';
                    require_once __DIR__ . '/../includes/ip_geo_resolver.php';
                    $sel_country = isset($_POST['boutique_country'])
                        ? strtoupper((string) $_POST['boutique_country'])
                        : ip_geo_detect_country_code();
                    if (!marketplace_country_is_valid($sel_country)) {
                        $sel_country = marketplace_country_default_code();
                    }
                    $sel_region = isset($_POST['boutique_region']) ? (string) $_POST['boutique_region'] : '';
                    require_once __DIR__ . '/../includes/boutique_types.php';
                    $sel_boutique_type = isset($_POST['boutique_type_id']) ? (int) $_POST['boutique_type_id'] : 0;
                    $boutique_types_required = boutique_types_inscription_required();
                    ?>
                    <script type="application/json" id="geoRegionsData"><?php echo geo_regions_json_for_js(); ?></script>
                    <label class="visually-hidden" for="boutique_country">Pays de la boutique</label>
                    <div class="al-field al-field--select">
                        <select id="boutique_country" name="boutique_country" required class="auth-select">
                            <?php echo marketplace_countries_options_html($sel_country, false); ?>
                        </select>
                    </div>

                    <label class="visually-hidden" for="boutique_region">Région de la boutique</label>
                    <div class="al-field al-field--select">
                        <select id="boutique_region" name="boutique_region" required class="auth-select"
                            data-selected="<?php echo htmlspecialchars($sel_region, ENT_QUOTES, 'UTF-8'); ?>"
                            data-empty-label="Région de la boutique">
                            <?php echo geo_regions_options_html($sel_country, $sel_region, true, 'Région de la boutique'); ?>
                        </select>
                    </div>

                    <?php if ($boutique_types_required): ?>
                    <label class="visually-hidden" for="boutique_type_id">Type de boutique</label>
                    <div class="al-field al-field--select">
                        <select id="boutique_type_id" name="boutique_type_id" required class="auth-select">
                            <?php echo boutique_types_options_html($sel_boutique_type, true, 'Type de boutique'); ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <label class="visually-hidden" for="telephone">Numéro de téléphone (connexion)</label>
                    <div class="al-field input-wrapper--intl-tel">
                        <input type="tel" id="telephone" name="telephone" placeholder="Numéro de téléphone" required autocomplete="tel"
                            value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>">
                    </div>

                    <label class="visually-hidden" for="email">Adresse e-mail (facultatif)</label>
                    <div class="al-field">
                        <input type="email" id="email" name="email" placeholder="E-mail (facultatif)" autocomplete="email"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <label class="visually-hidden" for="pin">Code PIN (6 chiffres)</label>
                    <div class="al-field al-field--password">
                        <input type="password" id="pin" name="pin" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                            placeholder="Code PIN (6 chiffres)" required autocomplete="new-password" title="6 chiffres">
                        <button type="button" class="al-eye" aria-label="Afficher le code PIN" onclick="togglePin('pin', this)">
                            <i class="fas fa-eye-slash" aria-hidden="true"></i>
                        </button>
                    </div>

                    <label class="visually-hidden" for="pin_confirm">Confirmer le code PIN</label>
                    <div class="al-field al-field--password">
                        <input type="password" id="pin_confirm" name="pin_confirm" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                            placeholder="Confirmer le code PIN" required autocomplete="new-password" title="6 chiffres">
                        <button type="button" class="al-eye" aria-label="Afficher la confirmation du PIN" onclick="togglePin('pin_confirm', this)">
                            <i class="fas fa-eye-slash" aria-hidden="true"></i>
                        </button>
                    </div>

                    <button type="submit" class="al-submit">Créer ma boutique</button>
                    <input type="hidden" name="insc_geo_lat" id="insc_geo_lat" value="">
                    <input type="hidden" name="insc_geo_lng" id="insc_geo_lng" value="">
                    <input type="hidden" name="insc_geo_precision" id="insc_geo_precision" value="">
                    <input type="hidden" name="insc_geo_manual" id="insc_geo_manual" value="">
                </form>

                <div class="al-sep" aria-hidden="true"><span>Ou continuer avec</span></div>

                <?php
                $social_auth_type = 'vendor';
                $social_auth_redirect = '/admin/dashboard.php';
                $social_auth_position = 'top';
                $social_auth_variant = 'tiles';
                include __DIR__ . '/../includes/google_auth_button.php';
                ?>

                <p class="al-signup">
                    Déjà vendeur ?
                    <a href="<?php echo htmlspecialchars($url_choix_connexion, ENT_QUOTES, 'UTF-8'); ?>">Se connecter</a>
                </p>
            </div>
        </main>
    </div>

    <script>
        function togglePin(inputId, button) {
            var input = document.getElementById(inputId);
            var icon = button.querySelector('i');
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', show);
            icon.classList.toggle('fa-eye-slash', !show);
        }
    </script>
    <?php include __DIR__ . '/../includes/auth_intl_tel_scripts.php'; ?>
    <script>
        (function () {
            function bootIntlTelVendeur() {
                if (typeof window.initAuthIntlTel === 'function') {
                    window.initAuthIntlTel('telephone');
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootIntlTelVendeur);
            } else {
                bootIntlTelVendeur();
            }
        })();
    </script>
    <?php include __DIR__ . '/../includes/google_auth_scripts.php'; ?>
    <script src="/js/geo-country-region.js" defer></script>
    <?php require_once __DIR__ . '/../includes/geo_native_bridge_script.php'; ?>
    <script src="/js/geo-address-format.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-inscription-location.js<?php echo asset_version_query(); ?>" defer></script>
    <?php include __DIR__ . '/../includes/social_floating.php'; ?>
    <?php admin_csrf_emit_form_inject_script(); ?>
</body>
</html>