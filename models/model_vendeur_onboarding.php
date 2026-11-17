<?php
/**
 * Onboarding vendeur : en_attente_produit → actif au 1er produit, relances cron.
 */

require_once __DIR__ . '/../includes/schema_vendeur_onboarding.php';

function vendeur_onboarding_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $path = dirname(__DIR__) . '/config/vendeur_onboarding.php';
    if (!is_readable($path)) {
        $path = dirname(__DIR__) . '/config/vendeur_onboarding.example.php';
    }
    $cfg = is_readable($path) ? (require $path) : [];
    if (!is_array($cfg)) {
        $cfg = [];
    }
    return $cfg;
}

/**
 * Connexion admin autorisée (vendeur en attente de 1er produit inclus).
 */
function admin_vendeur_statut_allows_login($statut): bool
{
    $s = (string) $statut;
    return $s === 'actif' || $s === 'en_attente_produit';
}

/**
 * @param array<string, mixed> $admin
 */
function admin_statut_allows_admin_login(array $admin): bool
{
    $statut = (string) ($admin['statut'] ?? '');
    if ($statut === 'inactif') {
        return false;
    }
    if ($statut === 'actif') {
        return true;
    }
    if ($statut === 'en_attente_produit') {
        require_once __DIR__ . '/model_admin.php';
        $role = function_exists('normalize_admin_role')
            ? normalize_admin_role($admin['role'] ?? '')
            : (string) ($admin['role'] ?? '');
        return $role === 'vendeur';
    }
    return false;
}

/**
 * @param array<string, mixed> $owner
 */
function vendeur_owner_statut_allows_collab_login(array $owner): bool
{
    require_once __DIR__ . '/model_admin.php';
    $role = function_exists('normalize_admin_role')
        ? normalize_admin_role($owner['role'] ?? '')
        : (string) ($owner['role'] ?? '');
    if ($role !== 'vendeur') {
        return false;
    }
    return admin_vendeur_statut_allows_login($owner['statut'] ?? '');
}

function vendeur_onboarding_register(int $admin_id): void
{
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !vendeur_onboarding_ensure_schema()) {
        return;
    }
    global $db;
    try {
        $db->prepare('
            INSERT INTO vendeur_onboarding (admin_id, cycle_started_at, relance_count)
            VALUES (:id, NOW(), 0)
            ON DUPLICATE KEY UPDATE admin_id = admin_id
        ')->execute(['id' => $admin_id]);
    } catch (PDOException $e) {
        error_log('[vendeur_onboarding_register] ' . $e->getMessage());
    }
}

function vendeur_onboarding_clear(int $admin_id): void
{
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !vendeur_onboarding_table_exists()) {
        return;
    }
    global $db;
    try {
        $db->prepare('DELETE FROM vendeur_onboarding WHERE admin_id = :id')->execute(['id' => $admin_id]);
    } catch (PDOException $e) {
        error_log('[vendeur_onboarding_clear] ' . $e->getMessage());
    }
}

function vendeur_count_produits_for_admin(int $admin_id): int
{
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return 0;
    }
    global $db;
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM produits WHERE admin_id = :aid');
        $st->execute(['aid' => $admin_id]);
        return (int) $st->fetchColumn();
    } catch (PDOException $e) {
        error_log('[vendeur_count_produits_for_admin] ' . $e->getMessage());
        return 0;
    }
}

function vendeur_count_commandes_for_admin(int $admin_id): int
{
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return 0;
    }
    global $db;
    try {
        require_once __DIR__ . '/model_commandes.php';
        if (function_exists('_commandes_has_vendeur_id_column') && _commandes_has_vendeur_id_column()) {
            $st = $db->prepare('SELECT COUNT(*) FROM commandes WHERE vendeur_id = :vid');
            $st->execute(['vid' => $admin_id]);
            return (int) $st->fetchColumn();
        }
        $st2 = $db->prepare('
            SELECT COUNT(DISTINCT cp.commande_id)
            FROM commande_produits cp
            INNER JOIN produits p ON p.id = cp.produit_id
            WHERE p.admin_id = :aid
        ');
        $st2->execute(['aid' => $admin_id]);
        return (int) $st2->fetchColumn();
    } catch (PDOException $e) {
        error_log('[vendeur_count_commandes_for_admin] ' . $e->getMessage());
    }
    return 0;
}

/**
 * Active le vendeur après le premier produit.
 */
function vendeur_onboarding_try_activate_after_produit(int $admin_id): bool
{
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return false;
    }
    if (vendeur_count_produits_for_admin($admin_id) < 1) {
        return false;
    }
    require_once __DIR__ . '/model_admin.php';
    $admin = get_admin_by_id($admin_id);
    if (!$admin || (string) ($admin['statut'] ?? '') !== 'en_attente_produit') {
        if ($admin && vendeur_count_produits_for_admin($admin_id) >= 1) {
            vendeur_onboarding_clear($admin_id);
        }
        return false;
    }
    global $db;
    try {
        $st = $db->prepare("
            UPDATE admin SET statut = 'actif'
            WHERE id = :id AND role = 'vendeur' AND statut = 'en_attente_produit'
        ");
        $st->execute(['id' => $admin_id]);
        if ($st->rowCount() > 0) {
            vendeur_onboarding_clear($admin_id);
            return true;
        }
    } catch (PDOException $e) {
        error_log('[vendeur_onboarding_try_activate_after_produit] ' . $e->getMessage());
    }
    return false;
}

/**
 * Ancienne règle « suppression J+10 » : abandonnée, aucun compte vendeur n'est supprimé.
 * Conservée pour compatibilité ; ne fait plus qu'activer le compte s'il a déjà un produit.
 *
 * @return string skipped
 */
function vendeur_onboarding_supprimer_si_sans_produit(int $admin_id): string
{
    $admin_id = (int) $admin_id;
    if ($admin_id > 0 && vendeur_count_produits_for_admin($admin_id) > 0) {
        vendeur_onboarding_try_activate_after_produit($admin_id);
    }
    return 'skipped';
}

/**
 * À appeler après établissement de la session admin (connexion / inscription).
 */
function vendeur_onboarding_prepare_popup_on_login(array $admin): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    require_once __DIR__ . '/model_admin.php';
    $role = function_exists('normalize_admin_role')
        ? normalize_admin_role($admin['role'] ?? '')
        : (string) ($admin['role'] ?? '');
    if ($role !== 'vendeur' || (string) ($admin['statut'] ?? '') !== 'en_attente_produit') {
        unset($_SESSION['vendeur_onboarding_show_popup'], $_SESSION['vendeur_onboarding_popup_dismissed']);
        return;
    }
    unset($_SESSION['vendeur_onboarding_popup_dismissed']);
    $_SESSION['vendeur_onboarding_show_popup'] = 1;
}

function vendeur_onboarding_dismiss_popup_for_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    unset($_SESSION['vendeur_onboarding_show_popup']);
    $_SESSION['vendeur_onboarding_popup_dismissed'] = 1;
}

/**
 * Afficher la modale d’activation sur l’interface vendeur.
 */
function vendeur_onboarding_should_show_popup_modal(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['admin_id'])) {
        return false;
    }
    if (!empty($_SESSION['vendeur_onboarding_popup_dismissed'])) {
        return false;
    }
    require_once __DIR__ . '/model_admin.php';
    $role = function_exists('normalize_admin_role')
        ? normalize_admin_role($_SESSION['admin_role'] ?? '')
        : (string) ($_SESSION['admin_role'] ?? '');
    if ($role !== 'vendeur') {
        return false;
    }
    $statut = (string) ($_SESSION['admin_statut'] ?? '');
    if ($statut !== 'en_attente_produit') {
        $fresh = get_admin_by_id((int) $_SESSION['admin_id']);
        if (is_array($fresh)) {
            $statut = (string) ($fresh['statut'] ?? '');
            $_SESSION['admin_statut'] = $statut;
        }
    }
    return $statut === 'en_attente_produit';
}

function vendeur_onboarding_cta_url(): string
{
    require_once __DIR__ . '/../includes/app_base.php';
    $cfg = vendeur_onboarding_config();
    $path = (string) ($cfg['vendeur_onboarding_cta_ajout_produit_path'] ?? '/admin/produits/ajouter.php');
    if ($path === '') {
        $path = '/admin/produits/ajouter.php';
    }
    return app_url($path);
}

/**
 * Redirection sûre après fermeture de la modale (compatible base /fouta-main).
 */
function vendeur_onboarding_sanitize_redirect(string $redirect): string
{
    require_once __DIR__ . '/../includes/app_base.php';
    $default = app_url('/admin/dashboard.php');
    $redirect = trim($redirect);
    if ($redirect === '' || strpos($redirect, '//') !== false || preg_match('/[\\\\\x00-\x1F\x7F]/', $redirect)) {
        return $default;
    }
    if ($redirect[0] !== '/') {
        $redirect = '/' . $redirect;
    }
    $base = get_app_base_path();
    if ($base !== '' && (strncmp($redirect, $base . '/', strlen($base) + 1) === 0 || $redirect === $base)) {
        return $redirect;
    }
    if ($base === '' && strpos($redirect, '/admin/') === 0) {
        return $redirect;
    }
    if (strpos($redirect, '/admin/') === 0) {
        return app_url($redirect);
    }
    return app_url('/admin/' . ltrim($redirect, '/'));
}

/**
 * Lien « Ajouter un produit » : ferme la popup session puis ouvre le formulaire.
 */
function vendeur_onboarding_cta_href(): string
{
    require_once __DIR__ . '/../includes/app_base.php';
    $target = vendeur_onboarding_cta_url();
    $dismiss = app_url('/admin/vendeur-onboarding-dismiss.php');
    return $dismiss . '?redirect=' . rawurlencode($target);
}

/**
 * @return array<int, array<string, mixed>>
 */
function vendeur_onboarding_fetch_pending_vendeurs(): array
{
    if (!vendeur_onboarding_ensure_schema()) {
        return [];
    }
    global $db;
    try {
        $st = $db->query("
            SELECT a.id, a.email, a.nom, a.prenom, a.boutique_nom, a.date_creation,
                   vo.cycle_started_at, vo.derniere_relance_at, vo.relance_count,
                   vo.avertissement_j7_envoye_at, vo.rappel_j9_envoye_at
            FROM admin a
            INNER JOIN vendeur_onboarding vo ON vo.admin_id = a.id
            WHERE a.role = 'vendeur' AND a.statut = 'en_attente_produit'
        ");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[vendeur_onboarding_fetch_pending_vendeurs] ' . $e->getMessage());
        return [];
    }
}

function vendeur_onboarding_days_since_creation(array $row): int
{
    $raw = (string) ($row['date_creation'] ?? $row['cycle_started_at'] ?? '');
    if ($raw === '') {
        return 0;
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return 0;
    }
    return (int) floor((time() - $ts) / 86400);
}

function vendeur_onboarding_touch_relance(int $admin_id): void
{
    if (!vendeur_onboarding_table_exists()) {
        return;
    }
    global $db;
    try {
        $db->prepare('
            UPDATE vendeur_onboarding
            SET derniere_relance_at = NOW(), relance_count = relance_count + 1
            WHERE admin_id = :id
        ')->execute(['id' => (int) $admin_id]);
    } catch (PDOException $e) {
        error_log('[vendeur_onboarding_touch_relance] ' . $e->getMessage());
    }
}

function vendeur_onboarding_mark_j7(int $admin_id): void
{
    if (!vendeur_onboarding_table_exists()) {
        return;
    }
    global $db;
    try {
        $db->prepare('
            UPDATE vendeur_onboarding SET avertissement_j7_envoye_at = NOW() WHERE admin_id = :id
        ')->execute(['id' => (int) $admin_id]);
    } catch (PDOException $e) {
        error_log('[vendeur_onboarding_mark_j7] ' . $e->getMessage());
    }
}

function vendeur_onboarding_mark_j9(int $admin_id): void
{
    if (!vendeur_onboarding_table_exists()) {
        return;
    }
    global $db;
    try {
        $db->prepare('
            UPDATE vendeur_onboarding SET rappel_j9_envoye_at = NOW() WHERE admin_id = :id
        ')->execute(['id' => (int) $admin_id]);
    } catch (PDOException $e) {
        error_log('[vendeur_onboarding_mark_j9] ' . $e->getMessage());
    }
}
