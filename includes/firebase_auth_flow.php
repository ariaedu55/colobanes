<?php
/**
 * Flux commun connexion/inscription Firebase (Google, Apple).
 */

require_once __DIR__ . '/firebase_auth_token.php';

/**
 * Garantit $db en portée globale (conn.php inclus depuis une fonction ne expose pas $db aux modèles).
 */
function firebase_auth_bootstrap_database()
{
    global $db;

    if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
        $db = $GLOBALS['db'];
        return;
    }

    if (!isset($db) || !($db instanceof PDO)) {
        require_once __DIR__ . '/../conn/conn.php';
    }

    if (isset($db) && $db instanceof PDO) {
        $GLOBALS['db'] = $db;
        return;
    }

    if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
        $db = $GLOBALS['db'];
    }
}

function firebase_auth_load_models()
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    firebase_auth_bootstrap_database();
    require_once __DIR__ . '/../models/model_users.php';
    require_once __DIR__ . '/../models/model_admin.php';
    $loaded = true;
}

function firebase_auth_ensure_database()
{
    firebase_auth_bootstrap_database();
    global $db;
    if (!isset($db) || !($db instanceof PDO)) {
        firebase_auth_json_response(false, 'Connexion à la base de données indisponible.');
    }
    firebase_auth_load_models();
}

if (!function_exists('firebase_auth_redirect_safe')) {
    function firebase_auth_redirect_safe($url)
    {
        require_once __DIR__ . '/flash_toast.php';
        http_redirect_safe($url);
    }
}

function firebase_auth_json_response($success, $message, $redirect = '')
{
    if (!function_exists('app_url')) {
        require_once __DIR__ . '/app_base.php';
    }
    if (!function_exists('auth_normalize_redirect_path')) {
        require_once __DIR__ . '/auth_redirect.php';
    }

    $redirect = (string) $redirect;
    if ($redirect !== '') {
        $redirect = app_url(auth_normalize_redirect_path($redirect));
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode([
        'success' => (bool) $success,
        'message' => (string) $message,
        'redirect' => $redirect,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function firebase_auth_safe_redirect($redirect, $fallback)
{
    if (!function_exists('auth_normalize_redirect_path')) {
        require_once __DIR__ . '/auth_redirect.php';
    }
    if (!function_exists('app_url')) {
        require_once __DIR__ . '/app_base.php';
    }

    return app_url(auth_normalize_redirect_path($redirect, $fallback));
}

function firebase_auth_set_user_session(array $user)
{
    if (file_exists(__DIR__ . '/auth_redirect.php')) {
        require_once __DIR__ . '/auth_redirect.php';
        try {
            session_regenerate_persistent();
        } catch (Throwable $e) {
            error_log('[firebase_auth] session_regenerate_id client : ' . $e->getMessage());
        }
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_nom'] = $user['nom'];
    $_SESSION['user_prenom'] = $user['prenom'];
    $_SESSION['user_email'] = (string) ($user['email'] ?? '');
    $_SESSION['user_telephone'] = $user['telephone'];
    $_SESSION['user_statut'] = $user['statut'];
    if (function_exists('auth_set_portal_cookie')) {
        auth_set_portal_cookie('client');
    }
    if (file_exists(__DIR__ . '/panier_invite.php')) {
        try {
            require_once __DIR__ . '/panier_invite.php';
            panier_fusionner_invite_apres_connexion((int) $user['id']);
        } catch (Throwable $e) {
            error_log('[firebase_auth] fusion panier : ' . $e->getMessage());
        }
        try {
            require_once __DIR__ . '/client_tracking.php';
            client_tracking_merge_after_login((int) $user['id']);
        } catch (Throwable $e) {
            error_log('[firebase_auth] fusion suivi comportement : ' . $e->getMessage());
        }
    }
}

function firebase_auth_set_admin_session(array $admin)
{
    if (file_exists(__DIR__ . '/auth_redirect.php')) {
        require_once __DIR__ . '/auth_redirect.php';
        try {
            session_regenerate_persistent();
        } catch (Throwable $e) {
            error_log('[firebase_auth] session_regenerate_id admin : ' . $e->getMessage());
        }
    }
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_nom'] = $admin['nom'];
    $_SESSION['admin_prenom'] = $admin['prenom'];
    $_SESSION['admin_email'] = $admin['email'] ?? '';
    $_SESSION['admin_statut'] = $admin['statut'];
    $_SESSION['admin_role'] = normalize_admin_role($admin['role'] ?? 'admin');
    $_SESSION['admin_boutique_nom'] = trim((string) ($admin['boutique_nom'] ?? ''));
    $_SESSION['admin_boutique_slug'] = trim((string) ($admin['boutique_slug'] ?? ''));
    unset($_SESSION['vendeur_collaborateur_id'], $_SESSION['vendeur_collaborateur_nom']);
    if (function_exists('auth_set_portal_cookie')) {
        $role = normalize_admin_role($admin['role'] ?? 'admin');
        auth_set_portal_cookie($role === 'vendeur' ? 'vendeur' : 'admin');
    }
    if (file_exists(__DIR__ . '/../models/model_vendeur_onboarding.php')) {
        require_once __DIR__ . '/../models/model_vendeur_onboarding.php';
        vendeur_onboarding_prepare_popup_on_login($admin);
    }
}

/**
 * Liaison par e-mail d'un compte existant : uniquement si le fournisseur certifie l'e-mail
 * (email_verified === true) et si le compte n'est pas déjà lié à un autre identifiant Firebase.
 */
function firebase_auth_email_link_allowed(array $row, array $profile)
{
    if (($profile['email_verified'] ?? false) !== true) {
        return false;
    }
    $existing_uid = trim((string) ($row['firebase_uid'] ?? ''));
    return $existing_uid === '' || hash_equals($existing_uid, (string) $profile['uid']);
}

/**
 * Recherche sans écriture : la liaison éventuelle ($link_pending) est faite par l'appelant
 * après les contrôles de rôle/statut. $link_refused signale un compte trouvé par e-mail mais non liable.
 */
function firebase_auth_find_admin(array $profile, &$link_pending = false, &$link_refused = false)
{
    firebase_auth_load_models();
    $link_pending = false;
    $link_refused = false;
    $admin = get_admin_by_firebase_uid($profile['uid']);
    if (!$admin && !empty($profile['email'])) {
        $admin = get_admin_by_email($profile['email']);
        if ($admin) {
            if (firebase_auth_email_link_allowed($admin, $profile)) {
                $link_pending = true;
            } else {
                $link_refused = true;
                $admin = false;
            }
        }
    }
    return $admin ?: false;
}

function firebase_auth_find_user(array $profile, &$link_pending = false, &$link_refused = false)
{
    firebase_auth_load_models();
    $link_pending = false;
    $link_refused = false;
    $user = get_user_by_firebase_uid($profile['uid']);
    if (!$user && !empty($profile['email'])) {
        $user = get_user_by_email($profile['email']);
        if ($user) {
            if (firebase_auth_email_link_allowed($user, $profile)) {
                $link_pending = true;
            } else {
                $link_refused = true;
                $user = false;
            }
        }
    }
    return $user ?: false;
}

function firebase_auth_store_pending(array $profile, $redirect)
{
    $pending = [
        'type' => '',
        'uid' => $profile['uid'],
        'email' => $profile['email'],
        'name' => $profile['name'],
        'picture' => $profile['picture'],
        'provider' => $profile['provider_key'],
        'redirect' => firebase_auth_safe_redirect($redirect, '/index.php'),
    ];

    $_SESSION['firebase_auth_pending'] = $pending;
    $_SESSION['google_auth_pending'] = $pending;
}

function firebase_auth_get_pending()
{
    if (isset($_SESSION['firebase_auth_pending']) && is_array($_SESSION['firebase_auth_pending'])) {
        return $_SESSION['firebase_auth_pending'];
    }
    if (isset($_SESSION['google_auth_pending']) && is_array($_SESSION['google_auth_pending'])) {
        return $_SESSION['google_auth_pending'];
    }
    return null;
}

function firebase_auth_pending_provider_label(array $pending)
{
    $provider = isset($pending['provider']) ? trim((string) $pending['provider']) : 'google';
    return $provider === 'apple' ? 'Apple' : 'Google';
}

function firebase_auth_process_callback(array $payload)
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        firebase_auth_json_response(false, 'Méthode non autorisée.');
    }

    if (file_exists(__DIR__ . '/login_rate_limit.php')) {
        require_once __DIR__ . '/login_rate_limit.php';
        login_attempt_unlock_if_expired();
        if (login_attempt_is_locked()) {
            $rem = login_attempt_remaining_seconds();
            firebase_auth_json_response(
                false,
                'Trop de tentatives de connexion incorrectes. Réessayez dans ' . login_attempt_format_remaining($rem) . '.'
            );
        }
    }

    $account_type_raw = isset($payload['accountType']) ? trim((string) $payload['accountType']) : 'auto';
    if (!in_array($account_type_raw, ['auto', 'client', 'vendor'], true)) {
        $account_type_raw = 'auto';
    }

    $expected_provider = null;
    if (isset($payload['provider']) && trim((string) $payload['provider']) === 'apple') {
        $expected_provider = 'apple.com';
    } elseif (isset($payload['provider']) && trim((string) $payload['provider']) === 'google') {
        $expected_provider = 'google.com';
    }

    $redirect = firebase_auth_safe_redirect($payload['redirect'] ?? '', '/index.php');
    $token_result = firebase_auth_verify_id_token($payload['idToken'] ?? '', $expected_provider);
    if (!$token_result['success']) {
        firebase_auth_json_response(false, $token_result['message']);
    }

    $profile = firebase_auth_profile_from_claims($token_result['claims']);
    if ($profile['uid'] === '') {
        firebase_auth_json_response(false, 'Identifiant Firebase manquant.');
    }

    unset($_SESSION['firebase_auth_pending'], $_SESSION['google_auth_pending']);

    firebase_auth_ensure_database();

    $admin_link = false;
    $admin_link_refused = false;
    $user_link = false;
    $user_link_refused = false;
    $admin = firebase_auth_find_admin($profile, $admin_link, $admin_link_refused);
    $user = firebase_auth_find_user($profile, $user_link, $user_link_refused);

    /* Compte existant avec cet e-mail mais non liable (e-mail non vérifié ou déjà lié ailleurs) : pas de doublon */
    if (!$admin && !$user && ($admin_link_refused || $user_link_refused)) {
        firebase_auth_json_response(false, 'Un compte existe déjà avec cet email. Connectez-vous avec email et mot de passe.');
    }

    if ($profile['email'] === '' && $user && !empty($user['email'])) {
        $profile['email'] = trim((string) $user['email']);
    }
    if ($profile['email'] === '' && $admin && !empty($admin['email'])) {
        $profile['email'] = trim((string) $admin['email']);
    }

    if ($profile['email'] === '' && !$user && !$admin) {
        $label = firebase_auth_provider_label($profile['provider']);
        firebase_auth_json_response(false, $label . ' n’a pas fourni d’email utilisable. Autorisez le partage de l’email lors de la connexion.');
    }

    if ($admin && normalize_admin_role($admin['role'] ?? '') === 'vendeur') {
        if (!function_exists('admin_statut_allows_admin_login')) {
            require_once __DIR__ . '/../models/model_vendeur_onboarding.php';
        }
        if (!admin_statut_allows_admin_login($admin)) {
            firebase_auth_json_response(false, 'Votre compte boutique est désactivé.');
        }
        firebase_auth_load_models();
        if ($admin_link) {
            /* Liaison par e-mail seulement une fois la connexion autorisée */
            update_admin_google_identity((int) $admin['id'], $profile['uid'], $profile['provider_key']);
            $admin = get_admin_by_id((int) $admin['id']);
        }
        update_admin_last_login((int) $admin['id']);
        firebase_auth_set_admin_session($admin);
        if (function_exists('login_attempt_clear_for_account')) {
            login_attempt_clear_for_account([$admin['email'] ?? '', $admin['telephone'] ?? '']);
        }
        firebase_auth_json_response(true, '', '/admin/dashboard.php');
    }

    if ($user) {
        if (($user['statut'] ?? '') !== 'actif') {
            firebase_auth_json_response(false, 'Votre compte est désactivé. Contactez le support.');
        }
        if ($user_link) {
            /* Liaison par e-mail seulement une fois la connexion autorisée */
            update_user_google_identity((int) $user['id'], $profile['uid'], $profile['provider_key']);
            update_user_accepte_conditions((int) $user['id'], true);
            $user = get_user_by_id((int) $user['id']);
        }
        firebase_auth_set_user_session($user);
        if (function_exists('login_attempt_clear_for_account')) {
            login_attempt_clear_for_account([$user['email'] ?? '', $user['telephone'] ?? '']);
        }
        firebase_auth_json_response(true, '', $redirect);
    }

    if ($admin) {
        firebase_auth_json_response(false, 'Cet email est déjà utilisé par un compte équipe. Connectez-vous avec email et mot de passe.');
    }

    if ($account_type_raw === 'client') {
        firebase_auth_store_pending($profile, $redirect);
        $_SESSION['firebase_auth_pending']['type'] = 'client';
        $_SESSION['google_auth_pending']['type'] = 'client';
        firebase_auth_json_response(true, '', '/auth-google-complete.php?type=client');
    }

    if ($account_type_raw === 'vendor') {
        firebase_auth_store_pending($profile, $redirect);
        $_SESSION['firebase_auth_pending']['type'] = 'vendor';
        $_SESSION['google_auth_pending']['type'] = 'vendor';
        firebase_auth_json_response(true, '', '/auth-google-complete.php?type=vendor');
    }

    firebase_auth_store_pending($profile, $redirect);
    firebase_auth_json_response(true, '', '/auth-google-choose-type.php');
}