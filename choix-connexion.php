<?php
/**
 * Connexion unifiée (client + équipe / admin via email + mot de passe).
 * La création de compte est proposée via choix-inscription.php.
 */
require_once __DIR__ . '/includes/session_user.php';
require_once __DIR__ . '/includes/auth_redirect.php';
require_once __DIR__ . '/includes/site_csrf.php';
session_start_persistent();

/* Seule page autorisée à ouvrir une session super administrateur */
define('AUTH_SUPER_ADMIN_LOGIN_PAGE', true);
require_once __DIR__ . '/includes/google_auth_coop.php';

$redirect_after = isset($_POST['redirect']) ? trim($_POST['redirect']) : (isset($_GET['redirect']) ? trim($_GET['redirect']) : '');
if ($redirect_after && $redirect_after[0] !== '/') {
    $redirect_after = '/' . $redirect_after;
}
$redirect_url = (!empty($redirect_after) && strpos($redirect_after, '//') === false && !preg_match('/[\\\\\x00-\x1F\x7F]/', (string) $redirect_after)) ? $redirect_after : '/index.php';

if (!empty($_SESSION['super_admin_id'])) {
    $sa_dest = auth_super_admin_dashboard_url();
    $sa_path = auth_super_admin_local_path($redirect_after);
    if ($sa_path !== '') {
        $sa_dest = app_url($sa_path);
    }
    auth_redirect_after_login($sa_dest);
}

if (auth_session_is_vendeur()) {
    auth_redirect_after_login(auth_vendeur_dashboard_url());
}

if (auth_user_is_logged_in()) {
    auth_redirect_after_login($redirect_url);
}

require_once __DIR__ . '/controllers/controller_users.php';

$result = ['success' => false, 'message' => '', 'type' => null, 'super_admin' => null, 'admin' => null, 'user' => null, 'vendeur_collaborateur' => null];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Jeton anti-falsification : empêche un site tiers de soumettre ce formulaire */
    $result = site_csrf_verify_post()
        ? process_unified_login()
        : ['success' => false, 'message' => site_csrf_fail_message(), 'type' => null, 'super_admin' => null, 'admin' => null, 'user' => null, 'vendeur_collaborateur' => null];
}

if (isset($result['success']) && $result['success'] && $result['type'] === 'super_admin' && !empty($result['super_admin'])) {
    auth_complete_super_admin_login($result['super_admin']);
    $sa_dest = auth_super_admin_dashboard_url();
    $sa_path = auth_super_admin_local_path($redirect_after);
    if ($sa_path !== '') {
        $sa_dest = app_url($sa_path);
    }
    auth_redirect_after_login($sa_dest);
}

if (isset($result['success']) && $result['success'] && $result['type'] === 'admin' && $result['admin']) {
    session_regenerate_persistent();
    $_SESSION['admin_id'] = $result['admin']['id'];
    $_SESSION['admin_nom'] = $result['admin']['nom'];
    $_SESSION['admin_prenom'] = $result['admin']['prenom'];
    $_SESSION['admin_email'] = $result['admin']['email'] ?? '';
    $_SESSION['admin_statut'] = $result['admin']['statut'];
    $_SESSION['admin_role'] = normalize_admin_role($result['admin']['role'] ?? 'admin');
    $_SESSION['admin_boutique_nom'] = trim((string) ($result['admin']['boutique_nom'] ?? ''));
    $_SESSION['admin_boutique_slug'] = trim((string) ($result['admin']['boutique_slug'] ?? ''));

    if (!empty($result['vendeur_collaborateur']) && is_array($result['vendeur_collaborateur'])) {
        $_SESSION['vendeur_collaborateur_id'] = (int) ($result['vendeur_collaborateur']['id'] ?? 0);
        $_SESSION['vendeur_collaborateur_nom'] = trim((string) ($result['vendeur_collaborateur']['nom'] ?? ''));
    } else {
        unset($_SESSION['vendeur_collaborateur_id'], $_SESSION['vendeur_collaborateur_nom']);
    }

    $login_role = normalize_admin_role($result['admin']['role'] ?? 'admin');
    auth_set_portal_cookie($login_role === 'vendeur' ? 'vendeur' : 'admin');

    if (file_exists(__DIR__ . '/models/model_vendeur_onboarding.php')) {
        require_once __DIR__ . '/models/model_vendeur_onboarding.php';
        vendeur_onboarding_prepare_popup_on_login($result['admin']);
    }

    auth_redirect_after_login(auth_login_redirect_url_for_admin());
}

if (isset($result['success']) && $result['success'] && $result['type'] === 'user' && $result['user']) {
    session_regenerate_persistent();
    $_SESSION['user_id'] = $result['user']['id'];
    $_SESSION['user_nom'] = $result['user']['nom'];
    $_SESSION['user_prenom'] = $result['user']['prenom'];
    $_SESSION['user_email'] = (string) ($result['user']['email'] ?? '');
    $_SESSION['user_telephone'] = $result['user']['telephone'];
    $_SESSION['user_statut'] = $result['user']['statut'];

    auth_set_portal_cookie('client');

    if (file_exists(__DIR__ . '/includes/panier_invite.php')) {
        try {
            require_once __DIR__ . '/includes/panier_invite.php';
            panier_fusionner_invite_apres_connexion((int) $result['user']['id']);
        } catch (Throwable $e) {
            error_log('[choix-connexion] fusion panier invité : ' . $e->getMessage());
        }
    }
    try {
        require_once __DIR__ . '/includes/client_tracking.php';
        client_tracking_merge_after_login((int) $result['user']['id']);
    } catch (Throwable $e) {
        error_log('[choix-connexion] fusion suivi comportement : ' . $e->getMessage());
    }

    auth_redirect_after_login($redirect_url);
}

$inscription_success = '';
if (isset($_SESSION['inscription_success'])) {
    $inscription_success = $_SESSION['inscription_success'];
    unset($_SESSION['inscription_success']);
}

/* Téléphone par défaut (le plus utilisé) ; e-mail si le formulaire envoyé était celui de l'e-mail */
$active_login_mode = 'phone';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_mode']) && (string) $_POST['login_mode'] === 'email') {
    $active_login_mode = 'email';
}

$login_locked = false;
$login_remaining_seconds = 0;
$login_show_warning = false;
$login_remaining_attempts = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_identifier = login_attempt_extract_identifier_from_post();
    if ($login_identifier !== '') {
        login_attempt_bind_identifier($login_identifier);
        login_attempt_unlock_if_expired();
        $login_locked = login_attempt_is_locked();
        $login_remaining_seconds = login_attempt_remaining_seconds();
        $login_show_warning = login_attempt_show_warning();
        $login_remaining_attempts = login_attempt_remaining_before_lock();
    }
}

require_once __DIR__ . '/includes/site_brand.php';
require_once __DIR__ . '/includes/app_base.php';
$redirect_qs = !empty($redirect_after) ? '?' . http_build_query(['redirect' => $redirect_after]) : '';
$form_action = app_url('/choix-connexion.php') . $redirect_qs;
$inscription_href = app_url('/choix-inscription.php');
$rq = isset($_GET['redirect']) ? trim((string) $_GET['redirect']) : '';
if ($rq !== '' && strpos($rq, '//') === false && !preg_match('/[\\\\\x00-\x1F\x7F]/', $rq)) {
    $inscription_href .= '?' . http_build_query(['redirect' => $rq]);
}
$app_base_js = get_app_base_path();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/includes/asset_version.php'; ?>
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Connexion - COLObanes</title>
    <script>window.__APP_BASE__=<?php echo json_encode($app_base_js, JSON_UNESCAPED_SLASHES); ?>;</script>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-connexion.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/auth-login-v2.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/mc-v2-shop-promo.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/includes/auth_intl_tel_head.php'; ?>
    <?php require_once __DIR__ . '/includes/ui_font_awesome.php'; ?>
    <style>
        .al-login-busy{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px;background:rgba(255,255,255,.92);color:#3564a6;font:600 15px/1.4 system-ui,sans-serif}
        .al-login-busy[hidden]{display:none!important}
        .al-login-busy__spin{width:40px;height:40px;border:3px solid #e8eef6;border-top-color:#3564a6;border-radius:50%;animation:alBusySpin .7s linear infinite}
        @keyframes alBusySpin{to{transform:rotate(360deg)}}
        body.al-is-logging-in{cursor:wait}
    </style>
</head>

<body class="auth-page page-choix-connexion al-page auth-page--<?php echo $active_login_mode === 'phone' ? 'phone' : 'email'; ?>">
    <div class="al-login-busy" id="alLoginBusy" hidden role="status" aria-live="polite" aria-busy="true">
        <div class="al-login-busy__spin" aria-hidden="true"></div>
        <p>Connexion en cours…</p>
    </div>

    <div class="auth-hub-shell">
        <?php
        $mc_v2_shop_promo_id = 'auth-shop-promo-title';
        include __DIR__ . '/includes/mc_v2_shop_promo.php';
        ?>

    <main class="al-wrap">
        <div class="al-card">
            <a class="al-logo" href="/index.php" aria-label="Retour à l'accueil <?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?>">
                <img src="/image/logo_market.webp" alt="<?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?>" width="220" height="60">
            </a>

            <div class="al-head">
                <h1 class="al-title">Bon retour 👋</h1>
            </div>

            <div class="al-tabs" role="tablist" aria-label="Mode de connexion">
                <button type="button" class="al-tab<?php echo $active_login_mode === 'phone' ? ' is-active' : ''; ?>" role="tab" id="tabPhone"
                    aria-selected="<?php echo $active_login_mode === 'phone' ? 'true' : 'false'; ?>" aria-controls="panel-phone" data-mode="phone">
                    <i class="fas fa-mobile-screen-button" aria-hidden="true"></i> Téléphone
                </button>
                <button type="button" class="al-tab<?php echo $active_login_mode === 'email' ? ' is-active' : ''; ?>" role="tab" id="tabEmail"
                    aria-selected="<?php echo $active_login_mode === 'email' ? 'true' : 'false'; ?>" aria-controls="panel-email" data-mode="email">
                    <i class="fas fa-envelope" aria-hidden="true"></i> E-mail
                </button>
            </div>

            <div class="al-messages">
                <?php if (!empty($inscription_success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($inscription_success); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($login_locked) && $login_remaining_seconds > 0): ?>
                    <?php include __DIR__ . '/includes/login_rate_lock_banner.php'; ?>
                <?php else: ?>
                    <?php if (!empty($login_show_warning)): ?>
                        <?php include __DIR__ . '/includes/login_rate_warning_banner.php'; ?>
                    <?php endif; ?>
                    <?php if (isset($result['message']) && $result['message'] !== '' && !$result['success']): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo login_safe_html_message($result['message']); ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Téléphone -->
            <div id="panel-phone" class="login-panel" role="tabpanel" aria-labelledby="tabPhone"<?php echo $active_login_mode !== 'phone' ? ' hidden' : ''; ?>>
                <form method="POST" action="<?php echo htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8'); ?>
<?php echo function_exists('protection_turnstile_widget') ? protection_turnstile_widget() : ''; ?>" class="al-form">
                    <input type="hidden" name="login_mode" value="phone">
                    <?php echo site_csrf_field(); ?>
                    <?php if (!empty($redirect_after)): ?>
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_after); ?>">
                    <?php endif; ?>
                    <fieldset class="login-fieldset"<?php echo $login_locked ? ' disabled' : ''; ?>>
                        <label class="visually-hidden" for="telephone">Numéro de téléphone</label>
                        <div class="al-field input-wrapper--intl-tel">
                            <input type="tel" id="telephone" name="telephone" placeholder="Numéro de téléphone" autocomplete="tel" required
                                value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>">
                        </div>

                        <label class="visually-hidden" for="pin">Code PIN ou mot de passe</label>
                        <div class="al-field al-field--password">
                            <input type="password" id="pin" name="pin" placeholder="Mot de passe ou code PIN" autocomplete="current-password" required>
                            <button type="button" class="al-eye" aria-label="Afficher le mot de passe" onclick="togglePassword('pin', this)">
                                <i class="fas fa-eye-slash" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div class="al-row">
                            <label class="al-check">
                                <input type="checkbox" id="accepte_conditions_phone" name="accepte_conditions_phone" value="1" required
                                    <?php echo (isset($_POST['accepte_conditions_phone']) && $_POST['accepte_conditions_phone'] === '1') ? 'checked' : ''; ?>>
                                <span>J'accepte les <a href="/conditions-utilisation.php" target="_blank" rel="noopener noreferrer">conditions</a></span>
                            </label>
                            <a class="al-forgot" href="/mot-de-passe-oublie.php">Mot de passe oublié ?</a>
                        </div>

                        <button type="submit" class="al-submit"<?php echo $login_locked ? ' disabled' : ''; ?>>Se connecter</button>
                    </fieldset>
                </form>
            </div>

            <!-- E-mail -->
            <div id="panel-email" class="login-panel" role="tabpanel" aria-labelledby="tabEmail"<?php echo $active_login_mode !== 'email' ? ' hidden' : ''; ?>>
                <form method="POST" action="<?php echo htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8'); ?>
<?php echo function_exists('protection_turnstile_widget') ? protection_turnstile_widget() : ''; ?>" class="al-form">
                    <input type="hidden" name="login_mode" value="email">
                    <?php echo site_csrf_field(); ?>
                    <?php if (!empty($redirect_after)): ?>
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_after); ?>">
                    <?php endif; ?>
                    <fieldset class="login-fieldset"<?php echo $login_locked ? ' disabled' : ''; ?>>
                        <label class="visually-hidden" for="email">Adresse e-mail</label>
                        <div class="al-field">
                            <input type="email" id="email" name="email" placeholder="Adresse e-mail" autocomplete="email" required
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>

                        <label class="visually-hidden" for="password">Mot de passe</label>
                        <div class="al-field al-field--password">
                            <input type="password" id="password" name="password" placeholder="Mot de passe" autocomplete="current-password" required>
                            <button type="button" class="al-eye" aria-label="Afficher le mot de passe" onclick="togglePassword('password', this)">
                                <i class="fas fa-eye-slash" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div class="al-row">
                            <label class="al-check">
                                <input type="checkbox" id="accepte_conditions" name="accepte_conditions" value="1" required
                                    <?php echo (isset($_POST['accepte_conditions']) && $_POST['accepte_conditions'] == '1') ? 'checked' : ''; ?>>
                                <span>J'accepte les <a href="/conditions-utilisation.php" target="_blank" rel="noopener noreferrer">conditions</a></span>
                            </label>
                            <a class="al-forgot" href="/mot-de-passe-oublie.php">Mot de passe oublié ?</a>
                        </div>

                        <button type="submit" class="al-submit"<?php echo $login_locked ? ' disabled' : ''; ?>>Se connecter</button>
                    </fieldset>
                </form>
            </div>

            <div class="al-sep" aria-hidden="true"><span>Ou continuer avec</span></div>

            <?php
            $google_auth_type = 'auto';
            $google_auth_redirect = $redirect_url;
            $google_auth_position = 'top';
            $google_auth_disabled = $login_locked;
            $social_auth_variant = 'tiles';
            include __DIR__ . '/includes/google_auth_button.php';
            ?>

            <p class="al-signup">
                Pas encore de compte ?
                <a href="<?php echo htmlspecialchars($inscription_href, ENT_QUOTES, 'UTF-8'); ?>">Créer un compte</a>
            </p>
        </div>

    </main>
    </div>

    <script>
        (function () {
            var tabs = document.querySelectorAll('.al-tab');
            function showMode(mode) {
                var isEmail = mode === 'email';
                document.body.classList.toggle('auth-page--email', isEmail);
                document.body.classList.toggle('auth-page--phone', !isEmail);
                document.getElementById('panel-email').hidden = !isEmail;
                document.getElementById('panel-phone').hidden = isEmail;
                tabs.forEach(function (t) {
                    var on = t.getAttribute('data-mode') === mode;
                    t.classList.toggle('is-active', on);
                    t.setAttribute('aria-selected', on ? 'true' : 'false');
                });
            }
            tabs.forEach(function (t) {
                t.addEventListener('click', function () {
                    showMode(t.getAttribute('data-mode'));
                    var first = document.querySelector(t.getAttribute('data-mode') === 'email' ? '#email' : '#telephone');
                    if (first) {
                        first.focus();
                    }
                });
            });

            /* Overlay immédiat au submit — évite l’écran blanc pendant le traitement serveur */
            function showLoginBusy() {
                var busy = document.getElementById('alLoginBusy');
                if (busy) busy.hidden = false;
                document.body.classList.add('al-is-logging-in');
            }
            document.querySelectorAll('form.al-form').forEach(function (form) {
                form.addEventListener('submit', function () {
                    showLoginBusy();
                    form.querySelectorAll('.al-submit').forEach(function (btn) {
                        btn.disabled = true;
                        btn.setAttribute('aria-busy', 'true');
                    });
                });
            });
            window.addEventListener('pageshow', function (e) {
                if (e.persisted) {
                    var busy = document.getElementById('alLoginBusy');
                    if (busy) busy.hidden = true;
                    document.body.classList.remove('al-is-logging-in');
                    document.querySelectorAll('.al-submit').forEach(function (btn) {
                        btn.disabled = false;
                        btn.removeAttribute('aria-busy');
                    });
                }
            });
        })();

        function togglePassword(inputId, button) {
            var input = document.getElementById(inputId);
            var icon = button.querySelector('i');
            if (!input || !icon) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', show);
            icon.classList.toggle('fa-eye-slash', !show);
            button.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        }
    </script>
    <?php include __DIR__ . '/includes/auth_intl_tel_scripts.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.initAuthIntlTel === 'function') {
                window.initAuthIntlTel('telephone');
            }
        });
    </script>
    <?php include __DIR__ . '/includes/google_auth_scripts.php'; ?>
    <?php include __DIR__ . '/includes/social_floating.php'; ?>
</body>

</html>
