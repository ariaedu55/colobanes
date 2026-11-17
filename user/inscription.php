<?php
require_once __DIR__ . '/../includes/session_user.php';
/**
 * Page d'inscription utilisateur
 * Programmation procédurale uniquement
 */

session_start_persistent();
require_once __DIR__ . '/../includes/google_auth_coop.php';
require_once __DIR__ . '/../includes/auth_redirect.php';

if (ob_get_level() === 0) {
    ob_start();
}

$inscription_redirect_get = isset($_GET['redirect']) ? trim((string) $_GET['redirect']) : '';
if ($inscription_redirect_get === '' || !preg_match('/^[a-z0-9_-]+$/i', $inscription_redirect_get)) {
    $inscription_redirect_get = '';
}

// Si l'utilisateur est déjà connecté, rediriger vers le tableau de bord
if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
    header('Location: profil.php');
    exit;
}

// Traiter le formulaire
require_once __DIR__ . '/../controllers/controller_users.php';
$result = process_user_inscription();

// Si l'inscription est réussie, rediriger vers la page de connexion
if (isset($result['success']) && $result['success']) {
    $_SESSION['inscription_success'] = $result['message'];
    $loc = '/choix-connexion.php';
    if ($inscription_redirect_get !== '') {
        $loc .= '?' . http_build_query(['redirect' => $inscription_redirect_get]);
    }
    auth_redirect_after_login($loc);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Inscription - COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-connexion.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-login-v2.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/mc-v2-shop-promo.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/../includes/auth_intl_tel_head.php'; ?>
    <?php require_once dirname(__DIR__, 1) . '/includes/ui_font_awesome.php'; ?>
</head>

<body class="auth-page auth-page--email page-inscription-client al-page">
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
                    <h1 class="al-title">Créer un compte</h1>
                </div>

                <div class="al-messages">
                    <?php if (isset($result['message']) && !empty($result['message']) && !$result['success']): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i> <?php echo nl2br(htmlspecialchars(str_ireplace(['<br>', '<br/>', '<br />'], "\n", (string) $result['message']), ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="POST" action="" id="inscriptionForm" class="al-form auth-inscription-form">
<?php echo function_exists('protection_champs_formulaire') ? protection_champs_formulaire() : ''; ?>
                    <label class="visually-hidden" for="nom">Nom</label>
                    <div class="al-field">
                        <input type="text" id="nom" name="nom" placeholder="Nom complet" required autocomplete="name"
                            value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
                    </div>

                    <label class="visually-hidden" for="telephone">Numéro de téléphone</label>
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
                        <button type="button" class="al-eye" aria-label="Afficher le code PIN" onclick="togglePassword('pin', this)">
                            <i class="fas fa-eye-slash" aria-hidden="true"></i>
                        </button>
                    </div>

                    <label class="visually-hidden" for="pin_confirm">Confirmer le code PIN</label>
                    <div class="al-field al-field--password">
                        <input type="password" id="pin_confirm" name="pin_confirm" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                            placeholder="Confirmer le code PIN" required autocomplete="new-password" title="6 chiffres">
                        <button type="button" class="al-eye" aria-label="Afficher la confirmation du PIN" onclick="togglePassword('pin_confirm', this)">
                            <i class="fas fa-eye-slash" aria-hidden="true"></i>
                        </button>
                    </div>

                    <button type="submit" class="al-submit">Créer mon compte</button>
                    <input type="hidden" name="insc_geo_lat" id="insc_geo_lat" value="">
                    <input type="hidden" name="insc_geo_lng" id="insc_geo_lng" value="">
                    <input type="hidden" name="insc_geo_precision" id="insc_geo_precision" value="">
                </form>

                <div class="al-sep" aria-hidden="true"><span>Ou continuer avec</span></div>

                <?php
                $social_auth_type = 'client';
                $social_auth_redirect = '/index.php';
                $social_auth_position = 'top';
                $social_auth_variant = 'tiles';
                include __DIR__ . '/../includes/google_auth_button.php';
                ?>

                <p class="al-signup">
                    Déjà un compte ?
                    <a href="/choix-connexion.php<?php echo $inscription_redirect_get !== '' ? htmlspecialchars('?' . http_build_query(['redirect' => $inscription_redirect_get])) : ''; ?>">Se connecter</a>
                </p>
            </div>
        </main>
    </div>

    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');

            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', show);
            icon.classList.toggle('fa-eye-slash', !show);
        }
    </script>
    <?php include __DIR__ . '/../includes/google_auth_scripts.php'; ?>
    <?php include __DIR__ . '/../includes/auth_intl_tel_scripts.php'; ?>
    <?php require_once __DIR__ . '/../includes/geo_native_bridge_script.php'; ?>
    <script src="/js/geo-address-format.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-inscription-location.js<?php echo asset_version_query(); ?>" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.initAuthIntlTel === 'function') {
                window.initAuthIntlTel('telephone');
            }
        });
    </script>
    <?php include __DIR__ . '/../includes/social_floating.php'; ?>
</body>

</html>
