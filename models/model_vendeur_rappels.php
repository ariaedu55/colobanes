<?php
/**
 * Rappels vendeur — popups dashboard (config super admin).
 */

if (!function_exists('vendeur_rappel_action_types')) {
    /**
     * @return array<string, array{label:string,hint:string}>
     */
    function vendeur_rappel_action_types()
    {
        return [
            'link_certification' => [
                'label' => 'Certifier ma boutique',
                'hint' => 'Affiché si le vendeur est encore au niveau Standard.',
            ],
            'capture_geo' => [
                'label' => 'Enregistrer ma position GPS',
                'hint' => 'Affiché si la boutique n\'a pas de coordonnées GPS valides.',
            ],
            'select_region' => [
                'label' => 'Choisir pays et région',
                'hint' => 'Affiché si le pays ou la région de la boutique est manquant.',
            ],
            'select_boutique_type' => [
                'label' => 'Choisir le type de boutique',
                'hint' => 'Affiché si aucun type de boutique actif n\'est associé.',
            ],
            'upload_logo' => [
                'label' => 'Soumettre une idée de projet / application',
                'hint' => 'Formulaire texte (ancien type « logo ») — jusqu\'à soumission du vendeur.',
            ],
            'idee_projet_app' => [
                'label' => 'Soumettre une idée de projet / application',
                'hint' => 'Affiché à tous les vendeurs actifs jusqu\'à envoi de leur idée.',
            ],
            'customize_colors' => [
                'label' => 'Personnaliser les couleurs',
                'hint' => 'Affiché si les couleurs sont encore celles par défaut du site.',
            ],
        ];
    }
}

function vendeur_rappel_action_type_valid($type)
{
    return is_string($type) && isset(vendeur_rappel_action_types()[$type]);
}

/**
 * Message affiché dans la popup (sans emojis, espaces normalisés).
 */
function vendeur_rappel_message_for_display(string $message): string
{
    $message = trim($message);
    if ($message === '') {
        return '';
    }
    $message = preg_replace(
        '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE00}-\x{FE0F}\x{200D}\x{20E3}]/u',
        '',
        $message
    );
    $lines = preg_split('/\R+/u', $message) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        $line = trim(preg_replace('/[ \t]+/u', ' ', (string) $line));
        if ($line !== '') {
            $clean[] = $line;
        }
    }
    return implode("\n\n", $clean);
}

function vendeur_rappels_table_exists()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    global $db;
    $cached = false;
    if (!$db) {
        return false;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vendeur_rappels'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function vendeur_rappels_dismissals_table_exists()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    global $db;
    $cached = false;
    if (!$db) {
        return false;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vendeur_rappels_dismissals'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

/**
 * @return array<int, array<string, mixed>>
 */
function vendeur_rappels_list_all($include_inactive = true)
{
    global $db;
    if (!vendeur_rappels_table_exists()) {
        return [];
    }
    try {
        $sql = 'SELECT * FROM vendeur_rappels';
        if (!$include_inactive) {
            $sql .= ' WHERE actif = 1';
        }
        $sql .= ' ORDER BY sort_ordre ASC, id ASC';
        $st = $db->query($sql);
        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_vendeur_rappel_by_id($id)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !vendeur_rappels_table_exists()) {
        return false;
    }
    try {
        $st = $db->prepare('SELECT * FROM vendeur_rappels WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    } catch (PDOException $e) {
        return false;
    }
}

function vendeur_rappel_insert_row($titre, $message, $action_type, $action_label, $sort_ordre, $actif = 1)
{
    global $db;
    if (!vendeur_rappels_table_exists() || !vendeur_rappel_action_type_valid($action_type)) {
        return false;
    }
    $titre = trim((string) $titre);
    $message = trim((string) $message);
    $action_label = trim((string) $action_label);
    if ($titre === '' || $message === '' || $action_label === '') {
        return false;
    }
    try {
        $st = $db->prepare('
            INSERT INTO vendeur_rappels (titre, message, action_type, action_label, sort_ordre, actif, date_creation)
            VALUES (:titre, :msg, :atype, :alabel, :so, :actif, NOW())
        ');
        if ($st->execute([
            'titre' => $titre,
            'msg' => $message,
            'atype' => $action_type,
            'alabel' => $action_label,
            'so' => (int) $sort_ordre,
            'actif' => (int) $actif === 1 ? 1 : 0,
        ])) {
            return (int) $db->lastInsertId();
        }
    } catch (PDOException $e) {
    }
    return false;
}

function vendeur_rappel_update_row($id, $titre, $message, $action_type, $action_label, $sort_ordre, $actif = 1)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !get_vendeur_rappel_by_id($id) || !vendeur_rappel_action_type_valid($action_type)) {
        return false;
    }
    $titre = trim((string) $titre);
    $message = trim((string) $message);
    $action_label = trim((string) $action_label);
    if ($titre === '' || $message === '' || $action_label === '') {
        return false;
    }
    try {
        $st = $db->prepare('
            UPDATE vendeur_rappels
            SET titre = :titre, message = :msg, action_type = :atype, action_label = :alabel,
                sort_ordre = :so, actif = :actif
            WHERE id = :id
        ');
        return $st->execute([
            'id' => $id,
            'titre' => $titre,
            'msg' => $message,
            'atype' => $action_type,
            'alabel' => $action_label,
            'so' => (int) $sort_ordre,
            'actif' => (int) $actif === 1 ? 1 : 0,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function vendeur_rappel_delete_row($id)
{
    global $db;
    $id = (int) $id;
    if ($id <= 0 || !vendeur_rappels_table_exists()) {
        return false;
    }
    try {
        if (vendeur_rappels_dismissals_table_exists()) {
            $st = $db->prepare('DELETE FROM vendeur_rappels_dismissals WHERE rappel_id = :id');
            $st->execute(['id' => $id]);
        }
        $st = $db->prepare('DELETE FROM vendeur_rappels WHERE id = :id');
        return $st->execute(['id' => $id]);
    } catch (PDOException $e) {
        return false;
    }
}

function vendeur_rappel_get_dismissal($admin_id, $rappel_id)
{
    global $db;
    $admin_id = (int) $admin_id;
    $rappel_id = (int) $rappel_id;
    if ($admin_id <= 0 || $rappel_id <= 0 || !vendeur_rappels_dismissals_table_exists()) {
        return false;
    }
    try {
        $st = $db->prepare('
            SELECT * FROM vendeur_rappels_dismissals
            WHERE admin_id = :a AND rappel_id = :r LIMIT 1
        ');
        $st->execute(['a' => $admin_id, 'r' => $rappel_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : false;
    } catch (PDOException $e) {
        return false;
    }
}

function vendeur_rappel_is_blocked_for_admin($admin_id, $rappel_id)
{
    $d = vendeur_rappel_get_dismissal($admin_id, $rappel_id);
    if (!$d) {
        return false;
    }
    if (!empty($d['completed_at'])) {
        return true;
    }
    if (vendeur_rappel_dismissals_has_snooze_count() && (int) ($d['snooze_count'] ?? 0) >= vendeur_rappel_max_snooze_count()) {
        return true;
    }
    if (!empty($d['dismissed_until'])) {
        $until = strtotime((string) $d['dismissed_until']);
        if ($until !== false && $until > time()) {
            return true;
        }
    }
    return false;
}

function vendeur_rappel_snooze_days_default()
{
    return 7;
}

/**
 * Nombre max d'affichages (2) = 2 clics « Plus tard » puis clôture.
 */
function vendeur_rappel_max_snooze_count()
{
    return 2;
}

function vendeur_rappel_dismissals_has_snooze_count()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    global $db;
    $cached = false;
    if (!$db || !vendeur_rappels_dismissals_table_exists()) {
        return false;
    }
    try {
        $st = $db->query("SHOW COLUMNS FROM vendeur_rappels_dismissals LIKE 'snooze_count'");
        $cached = $st && (bool) $st->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function vendeur_rappel_snooze($admin_id, $rappel_id, $days = null)
{
    if ($days === null) {
        $days = vendeur_rappel_snooze_days_default();
    }
    global $db;
    $admin_id = (int) $admin_id;
    $rappel_id = (int) $rappel_id;
    $days = max(1, (int) $days);
    $max_snooze = vendeur_rappel_max_snooze_count();
    if ($admin_id <= 0 || $rappel_id <= 0 || !vendeur_rappels_dismissals_table_exists()) {
        return false;
    }
    $has_count_col = vendeur_rappel_dismissals_has_snooze_count();
    $prev = vendeur_rappel_get_dismissal($admin_id, $rappel_id);
    $next_count = 1;
    if ($has_count_col && $prev) {
        $next_count = (int) ($prev['snooze_count'] ?? 0) + 1;
    } elseif ($prev && !empty($prev['dismissed_until'])) {
        $next_count = 2;
    }

    try {
        if ($has_count_col && $next_count >= $max_snooze) {
            $st = $db->prepare('
                INSERT INTO vendeur_rappels_dismissals (admin_id, rappel_id, dismissed_until, completed_at, snooze_count)
                VALUES (:a, :r, NULL, NOW(), :c)
                ON DUPLICATE KEY UPDATE dismissed_until = NULL, completed_at = NOW(), snooze_count = :c2
            ');
            return $st->execute([
                'a' => $admin_id,
                'r' => $rappel_id,
                'c' => $max_snooze,
                'c2' => $max_snooze,
            ]);
        }

        if ($has_count_col) {
            $st = $db->prepare('
                INSERT INTO vendeur_rappels_dismissals (admin_id, rappel_id, dismissed_until, completed_at, snooze_count)
                VALUES (:a, :r, DATE_ADD(NOW(), INTERVAL :d DAY), NULL, :c)
                ON DUPLICATE KEY UPDATE
                    dismissed_until = DATE_ADD(NOW(), INTERVAL :d2 DAY),
                    completed_at = NULL,
                    snooze_count = :c2
            ');
            return $st->execute([
                'a' => $admin_id,
                'r' => $rappel_id,
                'd' => $days,
                'd2' => $days,
                'c' => $next_count,
                'c2' => $next_count,
            ]);
        }

        $st = $db->prepare('
            INSERT INTO vendeur_rappels_dismissals (admin_id, rappel_id, dismissed_until, completed_at)
            VALUES (:a, :r, DATE_ADD(NOW(), INTERVAL :d DAY), NULL)
            ON DUPLICATE KEY UPDATE dismissed_until = DATE_ADD(NOW(), INTERVAL :d2 DAY), completed_at = NULL
        ');
        return $st->execute(['a' => $admin_id, 'r' => $rappel_id, 'd' => $days, 'd2' => $days]);
    } catch (PDOException $e) {
        return false;
    }
}

function vendeur_rappel_mark_completed($admin_id, $rappel_id)
{
    global $db;
    $admin_id = (int) $admin_id;
    $rappel_id = (int) $rappel_id;
    if ($admin_id <= 0 || $rappel_id <= 0 || !vendeur_rappels_dismissals_table_exists()) {
        return false;
    }
    try {
        $st = $db->prepare('
            INSERT INTO vendeur_rappels_dismissals (admin_id, rappel_id, dismissed_until, completed_at)
            VALUES (:a, :r, NULL, NOW())
            ON DUPLICATE KEY UPDATE completed_at = NOW(), dismissed_until = NULL
        ');
        return $st->execute(['a' => $admin_id, 'r' => $rappel_id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Condition implicite selon action_type.
 */
function vendeur_rappel_trigger_matches($admin_id, $action_type)
{
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !vendeur_rappel_action_type_valid($action_type)) {
        return false;
    }

    if (!function_exists('get_admin_by_id')) {
        require_once __DIR__ . '/model_admin.php';
    }
    $admin = get_admin_by_id($admin_id);
    if (!$admin || ($admin['role'] ?? '') !== 'vendeur') {
        return false;
    }

    switch ($action_type) {
        case 'link_certification':
            if (!function_exists('vendeur_certification_get_niveau_actif')) {
                require_once __DIR__ . '/model_vendeur_certification.php';
            }
            if (!function_exists('vendeur_certification_get_niveau_actif')) {
                return false;
            }
            $niveau = vendeur_certification_get_niveau_actif($admin_id);
            return $niveau === 'standard' || $niveau === null;

        case 'capture_geo':
            if (!function_exists('geo_coords_valid')) {
                require_once dirname(__DIR__) . '/includes/geo_location_service.php';
            }
            $lat = $admin['boutique_latitude'] ?? null;
            $lng = $admin['boutique_longitude'] ?? null;
            return !geo_coords_valid($lat, $lng);

        case 'select_region':
            if (!function_exists('marketplace_country_is_valid')) {
                require_once dirname(__DIR__) . '/includes/marketplace_countries.php';
            }
            if (!function_exists('geo_region_is_valid')) {
                require_once dirname(__DIR__) . '/includes/geo_regions.php';
            }
            $country = strtoupper(trim((string) ($admin['boutique_country'] ?? '')));
            $region = trim((string) ($admin['boutique_region'] ?? ''));
            if ($country === '' || !marketplace_country_is_valid($country)) {
                return true;
            }
            return $region === '' || !geo_region_is_valid($country, $region);

        case 'select_boutique_type':
            if (!function_exists('admin_has_boutique_type_id_column')) {
                require_once __DIR__ . '/model_admin.php';
            }
            if (!admin_has_boutique_type_id_column()) {
                return false;
            }
            if (!function_exists('boutique_type_is_valid_active')) {
                require_once __DIR__ . '/model_boutique_types.php';
            }
            $tid = (int) ($admin['boutique_type_id'] ?? 0);
            return $tid <= 0 || !boutique_type_is_valid_active($tid);

        case 'upload_logo':
        case 'idee_projet_app':
            return true;

        case 'customize_colors':
            if (!function_exists('boutique_normalize_hex_color')) {
                require_once dirname(__DIR__) . '/includes/boutique_vendeur_display.php';
            }
            $c1 = boutique_normalize_hex_color($admin['boutique_couleur_principale'] ?? '') ?: '#3564a6';
            $c2 = boutique_normalize_hex_color($admin['boutique_couleur_accent'] ?? '') ?: '#ff6b35';
            return strtoupper($c1) === '#3564A6' && strtoupper($c2) === '#FF6B35';

        default:
            return false;
    }
}

/**
 * Premier rappel actif à afficher sur le dashboard vendeur.
 *
 * @return array<string, mixed>|false
 */
function vendeur_rappel_get_pending_for_dashboard($admin_id)
{
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !vendeur_rappels_table_exists()) {
        return false;
    }

    foreach (vendeur_rappels_list_all(false) as $row) {
        $id = (int) ($row['id'] ?? 0);
        $atype = (string) ($row['action_type'] ?? '');
        if ($id <= 0 || !vendeur_rappel_action_type_valid($atype)) {
            continue;
        }
        if (vendeur_rappel_is_blocked_for_admin($admin_id, $id)) {
            continue;
        }
        if (vendeur_rappel_trigger_matches($admin_id, $atype)) {
            return $row;
        }
    }
    return false;
}

/**
 * IDs des vendeurs actifs.
 *
 * @return array<int, int>
 */
function vendeur_rappel_list_active_vendeur_ids()
{
    global $db;
    if (!$db) {
        return [];
    }
    try {
        $st = $db->query("SELECT id FROM admin WHERE role = 'vendeur' AND statut = 'actif'");
        $ids = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    } catch (PDOException $e) {
        return [];
    }
}

function vendeur_rappel_is_completed_for_admin($admin_id, $rappel_id)
{
    $d = vendeur_rappel_get_dismissal((int) $admin_id, (int) $rappel_id);
    return $d && !empty($d['completed_at']);
}

/**
 * Vendeurs qui doivent encore voir ce rappel (condition implicite vraie, non complété).
 *
 * @return array<int, int>
 */
function vendeur_rappel_list_concerned_admin_ids($rappel_id)
{
    $rappel_id = (int) $rappel_id;
    $rappel = $rappel_id > 0 ? get_vendeur_rappel_by_id($rappel_id) : false;
    if (!$rappel || (int) ($rappel['actif'] ?? 0) !== 1) {
        return [];
    }
    $atype = (string) ($rappel['action_type'] ?? '');
    if (!vendeur_rappel_action_type_valid($atype)) {
        return [];
    }

    $concerned = [];
    foreach (vendeur_rappel_list_active_vendeur_ids() as $admin_id) {
        if (vendeur_rappel_is_completed_for_admin($admin_id, $rappel_id)) {
            continue;
        }
        if (vendeur_rappel_trigger_matches($admin_id, $atype)) {
            $concerned[] = $admin_id;
        }
    }
    return $concerned;
}

function vendeur_rappel_clear_snooze_for_admin($admin_id, $rappel_id)
{
    global $db;
    $admin_id = (int) $admin_id;
    $rappel_id = (int) $rappel_id;
    if ($admin_id <= 0 || $rappel_id <= 0 || !vendeur_rappels_dismissals_table_exists()) {
        return false;
    }
    try {
        $st = $db->prepare('
            UPDATE vendeur_rappels_dismissals
            SET dismissed_until = NULL
            WHERE admin_id = :a AND rappel_id = :r AND completed_at IS NULL
        ');
        return $st->execute(['a' => $admin_id, 'r' => $rappel_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function vendeur_rappel_republish_clear_snoozes($rappel_id)
{
    global $db;
    $rappel_id = (int) $rappel_id;
    if ($rappel_id <= 0 || !vendeur_rappels_dismissals_table_exists() || !$db) {
        return;
    }
    try {
        $st = $db->prepare('DELETE FROM vendeur_rappels_dismissals WHERE rappel_id = :r');
        $st->execute(['r' => $rappel_id]);
    } catch (PDOException $e) {
        foreach (vendeur_rappel_list_concerned_admin_ids($rappel_id) as $admin_id) {
            vendeur_rappel_clear_snooze_for_admin($admin_id, $rappel_id);
        }
    }
}

function vendeur_rappel_idees_projet_table_exists()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    global $db;
    $cached = false;
    if (!$db) {
        return false;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vendeur_rappel_idees_projet'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function vendeur_rappel_idees_projet_ensure_table()
{
    if (vendeur_rappel_idees_projet_table_exists()) {
        return true;
    }
    global $db;
    if (!$db) {
        return false;
    }
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `vendeur_rappel_idees_projet` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `admin_id` INT(11) NOT NULL,
                `rappel_id` INT(11) NOT NULL,
                `idee` TEXT NOT NULL,
                `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_vrip_admin_rappel` (`admin_id`, `rappel_id`),
                KEY `idx_vrip_rappel` (`rappel_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        return vendeur_rappel_idees_projet_table_exists();
    } catch (PDOException $e) {
        error_log('[vendeur_rappel_idees_projet_ensure_table] ' . $e->getMessage());
        return false;
    }
}

/**
 * Enregistre l'idée de projet soumise via un rappel vendeur.
 */
function vendeur_rappel_save_idee_projet($admin_id, $rappel_id, $idee)
{
    global $db;
    $admin_id = (int) $admin_id;
    $rappel_id = (int) $rappel_id;
    $idee = trim((string) $idee);
    if ($admin_id <= 0 || $rappel_id <= 0 || $idee === '') {
        return false;
    }
    if (!vendeur_rappel_idees_projet_ensure_table()) {
        return false;
    }
    try {
        $st = $db->prepare('
            INSERT INTO vendeur_rappel_idees_projet (admin_id, rappel_id, idee, date_creation)
            VALUES (:a, :r, :i, NOW())
            ON DUPLICATE KEY UPDATE idee = VALUES(idee), date_creation = NOW()
        ');
        return $st->execute(['a' => $admin_id, 'r' => $rappel_id, 'i' => $idee]);
    } catch (PDOException $e) {
        error_log('[vendeur_rappel_save_idee_projet] ' . $e->getMessage());
        return false;
    }
}
