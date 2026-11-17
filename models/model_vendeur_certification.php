<?php
/**
 * Certification vendeurs — Standard / VIP / Premium.
 * Programmation procédurale uniquement.
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_admin.php';
require_once __DIR__ . '/../includes/schema_vendeur_certification.php';

function vendeur_certification_table_exists() {
    static $cached = null;
    if ($cached === true) {
        return true;
    }
    require_once __DIR__ . '/../includes/schema_runtime_cache.php';
    if (schema_runtime_module_ready('vendeur_certification')) {
        $cached = true;
        return true;
    }
    vendeur_certification_ensure_schema();
    global $db;
    $cached = false;
    if (!$db) {
        return false;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vendeur_certification_demandes'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function vendeur_certification_admin_column_exists() {
    return admin_has_column('certification_niveau');
}

function vendeur_certification_niveaux() {
    return [
        'standard' => [
            'label' => 'Standard',
            'badge' => 'cert-badge--standard',
            'icon' => 'fa-shield',
            'color' => '#737373',
            'desc' => 'Gagnez la confiance des acheteurs et vendez sur la plateforme.',
        ],
        'vip' => [
            'label' => 'VIP',
            'badge' => 'cert-badge--vip',
            'icon' => 'fa-gem',
            'color' => '#3564a6',
            'desc' => 'Visibilité ×5, enchères et vente de vos produits.',
        ],
        'premium' => [
            'label' => 'Premium',
            'badge' => 'cert-badge--premium',
            'icon' => 'fa-crown',
            'color' => '#d97706',
            'desc' => 'Niveau le plus élevé : status 24 h, enchères, visibilité ×10.',
        ],
    ];
}

function vendeur_certification_niveau_ordre($niveau) {
    $map = ['standard' => 1, 'vip' => 2, 'premium' => 3];
    return $map[(string) $niveau] ?? 0;
}

/**
 * Score SQL (0–3) pour tri marketplace : premium > vip > standard > non certifié.
 */
function vendeur_certification_sql_score_expr(string $admin_alias = 'a'): string
{
    if (!vendeur_certification_admin_column_exists()) {
        return '0';
    }
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', $admin_alias);
    if ($a === '') {
        $a = 'a';
    }
    return "(CASE
        WHEN {$a}.certification_niveau = 'premium' THEN 3
        WHEN {$a}.certification_niveau = 'vip' THEN 2
        WHEN {$a}.certification_niveau = 'standard' THEN 1
        ELSE 0
    END)";
}

/**
 * Préfixe ORDER BY pour listes marketplace globales (pas une boutique unique).
 */
function vendeur_certification_sql_order_before(string $primary_order, int|string|null $boutique_scope_id = null, string $admin_alias = 'vend'): string
{
    if ($boutique_scope_id !== null && $boutique_scope_id !== '') {
        return $primary_order;
    }
    if (!vendeur_certification_admin_column_exists()) {
        return $primary_order;
    }
    return vendeur_certification_sql_score_expr($admin_alias) . ' DESC, ' . $primary_order;
}

function vendeur_certification_niveau_label($niveau) {
    $n = vendeur_certification_niveaux();
    return $n[$niveau]['label'] ?? ucfirst((string) $niveau);
}

/**
 * Chemin public de l'image badge certification.
 */
function vendeur_certification_badge_image_src($niveau) {
    $map = [
        /* WebP 512 px (~30 Ko) au lieu des PNG d'origine de 1 à 1,5 Mo */
        'standard' => '/image/badge-standar.webp',
        'vip' => '/image/badge-vip.webp',
        'premium' => '/image/badge-premium.webp',
    ];
    $niveau = (string) $niveau;
    if (!isset($map[$niveau])) {
        return '';
    }
    $parts = explode('/', $map[$niveau]);
    $file = array_pop($parts);
    $parts[] = rawurlencode($file);
    return implode('/', $parts);
}

function vendeur_certification_get_niveau_actif($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !vendeur_certification_admin_column_exists() || !$db) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT certification_niveau FROM admin WHERE id = :id LIMIT 1');
        $st->execute(['id' => $admin_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $n = trim((string) ($row['certification_niveau'] ?? ''));
        return in_array($n, ['standard', 'vip', 'premium'], true) ? $n : null;
    } catch (PDOException $e) {
        return null;
    }
}

function vendeur_certification_get_demande_en_cours($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !vendeur_certification_table_exists() || !$db) {
        return null;
    }
    try {
        $st = $db->prepare("
            SELECT * FROM vendeur_certification_demandes
            WHERE admin_id = :a AND statut = 'en_attente'
            ORDER BY date_creation DESC
            LIMIT 1
        ");
        $st->execute(['a' => $admin_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function vendeur_certification_get_derniere_demande($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !vendeur_certification_table_exists() || !$db) {
        return null;
    }
    try {
        $st = $db->prepare("
            SELECT * FROM vendeur_certification_demandes
            WHERE admin_id = :a
            ORDER BY date_creation DESC
            LIMIT 1
        ");
        $st->execute(['a' => $admin_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function vendeur_certification_peut_demander($admin_id, $niveau) {
    $niveau = (string) $niveau;
    if (!in_array($niveau, ['standard', 'vip', 'premium'], true)) {
        return ['ok' => false, 'message' => 'Niveau de certification invalide.'];
    }
    if (vendeur_certification_get_demande_en_cours($admin_id)) {
        return ['ok' => false, 'message' => 'Une demande est déjà en cours d\'examen.'];
    }
    $actuel = vendeur_certification_get_niveau_actif($admin_id);
    if ($actuel !== null && vendeur_certification_niveau_ordre($niveau) <= vendeur_certification_niveau_ordre($actuel)) {
        return ['ok' => false, 'message' => 'Vous possédez déjà ce niveau ou un niveau supérieur.'];
    }
    return ['ok' => true, 'message' => ''];
}

function vendeur_certification_upload_photo($file, $admin_id, $prefix = 'local') {
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => '', 'message' => 'Fichier image requis.'];
    }
    require_once __DIR__ . '/../includes/upload_image_limits.php';
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, (string) ($file['tmp_name'] ?? ''));
            finfo_close($finfo);
        }
    }
    if ($mime === '') {
        $info = @getimagesize((string) ($file['tmp_name'] ?? ''));
        $mime = is_array($info) && !empty($info['mime']) ? (string) $info['mime'] : '';
    }
    if (!in_array($mime, $allowed, true)) {
        return ['ok' => false, 'path' => '', 'message' => 'Format accepté : JPEG, PNG ou WebP.'];
    }
    if ((int) ($file['size'] ?? 0) > UPLOAD_MAX_IMAGE_BYTES) {
        return ['ok' => false, 'path' => '', 'message' => 'Image trop volumineuse (max. 20 Mo).'];
    }
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $ext = 'jpg';
    }
    $dir = __DIR__ . '/../upload/certifications/' . $admin_id;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return ['ok' => false, 'path' => '', 'message' => 'Impossible de créer le dossier d\'upload.'];
    }
    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
        return ['ok' => false, 'path' => '', 'message' => 'Échec de l\'envoi de l\'image.'];
    }
    return ['ok' => true, 'path' => 'certifications/' . $admin_id . '/' . $name, 'message' => ''];
}

function vendeur_certification_piece_identite_column_exists() {
    static $cached = null;
    if ($cached === true) {
        return true;
    }
    vendeur_certification_ensure_schema();
    global $db;
    $cached = false;
    if (!$db) {
        return false;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'vendeur_certification_demandes'
              AND COLUMN_NAME = 'photo_piece_identite'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function vendeur_certification_piece_identite_verso_column_exists() {
    static $cached = null;
    if ($cached === true) {
        return true;
    }
    vendeur_certification_ensure_schema();
    global $db;
    $cached = false;
    if (!$db) {
        return false;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'vendeur_certification_demandes'
              AND COLUMN_NAME = 'photo_piece_identite_verso'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function vendeur_certification_photo_local_4_column_exists() {
    static $cached = null;
    if ($cached === true) {
        return true;
    }
    vendeur_certification_ensure_schema();
    global $db;
    $cached = false;
    if (!$db) {
        return false;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'vendeur_certification_demandes'
              AND COLUMN_NAME = 'photo_local_4'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function vendeur_certification_creer_demande($admin_id, array $data) {
    global $db;
    $admin_id = (int) $admin_id;
    $niveau = (string) ($data['niveau'] ?? '');
    $check = vendeur_certification_peut_demander($admin_id, $niveau);
    if (!$check['ok']) {
        return ['ok' => false, 'message' => $check['message']];
    }
    if (!vendeur_certification_table_exists() || !$db) {
        return ['ok' => false, 'message' => 'Migration certification requise.'];
    }
    try {
        $has_piece = vendeur_certification_piece_identite_column_exists();
        $has_piece_verso = vendeur_certification_piece_identite_verso_column_exists();
        $has_local_4 = vendeur_certification_photo_local_4_column_exists();
        $local_cols = 'photo_local_1, photo_local_2, photo_local_3';
        $local_vals = ':photo_local_1, :photo_local_2, :photo_local_3';
        if ($has_local_4) {
            $local_cols .= ', photo_local_4';
            $local_vals .= ', :photo_local_4';
        }
        $cols = 'admin_id, niveau, statut, nom, prenom, email, telephone,
                boutique_nom, boutique_region, adresse_exacte, description_activite,
                numero_registre, ' . $local_cols . ',
                photo_document, message_demande, date_creation';
        $vals = ':admin_id, :niveau, \'en_attente\', :nom, :prenom, :email, :telephone,
                :boutique_nom, :boutique_region, :adresse_exacte, :description_activite,
                :numero_registre, ' . $local_vals . ',
                :photo_document, :message_demande, NOW()';
        if ($has_piece) {
            $piece_cols = 'photo_piece_identite';
            $piece_vals = ':photo_piece_identite';
            if ($has_piece_verso) {
                $piece_cols .= ', photo_piece_identite_verso';
                $piece_vals .= ', :photo_piece_identite_verso';
            }
            $cols = str_replace('photo_document, message_demande', 'photo_document, ' . $piece_cols . ', message_demande', $cols);
            $vals = str_replace(':photo_document, :message_demande', ':photo_document, ' . $piece_vals . ', :message_demande', $vals);
        }
        $st = $db->prepare("INSERT INTO vendeur_certification_demandes ($cols) VALUES ($vals)");
        $params = [
            'admin_id' => $admin_id,
            'niveau' => $niveau,
            'nom' => (string) ($data['nom'] ?? ''),
            'prenom' => (string) ($data['prenom'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'telephone' => (string) ($data['telephone'] ?? ''),
            'boutique_nom' => (string) ($data['boutique_nom'] ?? ''),
            'boutique_region' => (string) ($data['boutique_region'] ?? ''),
            'adresse_exacte' => (string) ($data['adresse_exacte'] ?? ''),
            'description_activite' => (string) ($data['description_activite'] ?? ''),
            'numero_registre' => (string) ($data['numero_registre'] ?? ''),
            'photo_local_1' => (string) ($data['photo_local_1'] ?? ''),
            'photo_local_2' => (string) ($data['photo_local_2'] ?? ''),
            'photo_local_3' => (string) ($data['photo_local_3'] ?? ''),
            'photo_document' => (string) ($data['photo_document'] ?? ''),
            'message_demande' => (string) ($data['message_demande'] ?? ''),
        ];
        if ($has_local_4) {
            $params['photo_local_4'] = (string) ($data['photo_local_4'] ?? '');
        }
        if ($has_piece) {
            $params['photo_piece_identite'] = (string) ($data['photo_piece_identite'] ?? '');
            if ($has_piece_verso) {
                $params['photo_piece_identite_verso'] = (string) ($data['photo_piece_identite_verso'] ?? '');
            }
        }
        $st->execute($params);
        return ['ok' => true, 'message' => 'Votre demande a été envoyée. Notre équipe l\'examinera sous peu.', 'id' => (int) $db->lastInsertId()];
    } catch (PDOException $e) {
        return ['ok' => false, 'message' => 'Erreur lors de l\'enregistrement de la demande.'];
    }
}

function vendeur_certification_list_en_attente($limit = 50) {
    return vendeur_certification_list_par_statut('en_attente', $limit);
}

/**
 * Liste des demandes par statut (en_attente | approuvee | refusee).
 *
 * @return array<int, array>
 */
function vendeur_certification_list_par_statut($statut, $limit = 100) {
    global $db;
    if (!vendeur_certification_table_exists() || !$db) {
        return [];
    }
    $statut = (string) $statut;
    if (!in_array($statut, ['en_attente', 'approuvee', 'refusee'], true)) {
        return [];
    }
    $limit = max(1, min(200, (int) $limit));
    $order = $statut === 'en_attente' ? 'd.date_creation ASC' : 'd.date_traitement DESC, d.date_creation DESC';
    try {
        $st = $db->prepare("
            SELECT d.*, a.boutique_slug, a.certification_niveau AS niveau_actif_admin
            FROM vendeur_certification_demandes d
            INNER JOIN admin a ON a.id = d.admin_id
            WHERE d.statut = :st
            ORDER BY $order
            LIMIT $limit
        ");
        $st->execute(['st' => $statut]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

function vendeur_certification_counts_par_statut() {
    global $db;
    $out = ['en_attente' => 0, 'approuvee' => 0, 'refusee' => 0];
    if (!vendeur_certification_table_exists() || !$db) {
        return $out;
    }
    try {
        $st = $db->query("
            SELECT statut, COUNT(*) AS nb
            FROM vendeur_certification_demandes
            WHERE statut IN ('en_attente', 'approuvee', 'refusee')
            GROUP BY statut
        ");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $s = (string) ($row['statut'] ?? '');
            if (isset($out[$s])) {
                $out[$s] = (int) ($row['nb'] ?? 0);
            }
        }
    } catch (PDOException $e) {
    }
    return $out;
}

function vendeur_certification_statut_label($statut) {
    return match ((string) $statut) {
        'en_attente' => 'En cours',
        'approuvee'  => 'Validée',
        'refusee'    => 'Refusée',
        'annulee'    => 'Annulée',
        default      => ucfirst(str_replace('_', ' ', (string) $statut)),
    };
}

function vendeur_certification_traiter_demande($demande_id, $action, $motif_refus = '', $traite_par = null) {
    global $db;
    $demande_id = (int) $demande_id;
    $action = (string) $action;
    if ($demande_id <= 0 || !vendeur_certification_table_exists() || !$db) {
        return false;
    }
    if (!in_array($action, ['approuvee', 'refusee'], true)) {
        return false;
    }
    try {
        $st = $db->prepare('SELECT * FROM vendeur_certification_demandes WHERE id = :id LIMIT 1');
        $st->execute(['id' => $demande_id]);
        $dem = $st->fetch(PDO::FETCH_ASSOC);
        if (!$dem || ($dem['statut'] ?? '') !== 'en_attente') {
            return false;
        }
        $db->beginTransaction();
        $st2 = $db->prepare("
            UPDATE vendeur_certification_demandes
            SET statut = :st, motif_refus = :motif, date_traitement = NOW(), traite_par = :tp"
            . (vendeur_certification_notif_lue_column_exists() ? ', vendeur_notif_lue = 0' : '')
            . "
            WHERE id = :id
        ");
        $st2->execute([
            'st' => $action,
            'motif' => $action === 'refusee' ? trim((string) $motif_refus) : null,
            'tp' => $traite_par !== null ? (int) $traite_par : null,
            'id' => $demande_id,
        ]);
        if ($action === 'approuvee' && vendeur_certification_admin_column_exists()) {
            $st3 = $db->prepare("
                UPDATE admin SET certification_niveau = :n, certification_date = NOW()
                WHERE id = :aid
            ");
            $st3->execute(['n' => $dem['niveau'], 'aid' => (int) $dem['admin_id']]);
        }
        $db->commit();
        return true;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}

function vendeur_certification_count_en_attente() {
    global $db;
    if (!vendeur_certification_table_exists() || !$db) {
        return 0;
    }
    try {
        return (int) $db->query("SELECT COUNT(*) FROM vendeur_certification_demandes WHERE statut = 'en_attente'")->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function vendeur_certification_notif_lue_column_exists() {
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
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'vendeur_certification_demandes'
              AND COLUMN_NAME = 'vendeur_notif_lue'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function vendeur_certification_get_demande_by_id($demande_id) {
    global $db;
    $demande_id = (int) $demande_id;
    if ($demande_id <= 0 || !vendeur_certification_table_exists() || !$db) {
        return null;
    }
    try {
        $st = $db->prepare("
            SELECT d.*, a.boutique_slug, a.certification_niveau AS niveau_actif_admin
            FROM vendeur_certification_demandes d
            INNER JOIN admin a ON a.id = d.admin_id
            WHERE d.id = :id
            LIMIT 1
        ");
        $st->execute(['id' => $demande_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function vendeur_certification_get_demande_for_vendeur($demande_id, $admin_id) {
    $demande_id = (int) $demande_id;
    $admin_id = (int) $admin_id;
    if ($demande_id <= 0 || $admin_id <= 0) {
        return null;
    }
    $row = vendeur_certification_get_demande_by_id($demande_id);
    if (!$row || (int) ($row['admin_id'] ?? 0) !== $admin_id) {
        return null;
    }
    return $row;
}

function vendeur_certification_get_notif_vendeur_pending($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !vendeur_certification_table_exists() || !$db) {
        return null;
    }
    try {
        $notif_sql = vendeur_certification_notif_lue_column_exists()
            ? ' AND (d.vendeur_notif_lue = 0 OR d.vendeur_notif_lue IS NULL)'
            : '';
        $st = $db->prepare("
            SELECT d.*, a.boutique_slug
            FROM vendeur_certification_demandes d
            INNER JOIN admin a ON a.id = d.admin_id
            WHERE d.admin_id = :aid
              AND d.statut IN ('approuvee', 'refusee')
              AND d.date_traitement IS NOT NULL
              $notif_sql
            ORDER BY d.date_traitement DESC
            LIMIT 1
        ");
        $st->execute(['aid' => $admin_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Page certification : ne pas afficher la popup promo.
 */
function vendeur_certification_promo_on_certification_page(): bool
{
    $page = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
    return in_array($page, ['certification.php', 'certification-demande.php', 'certification-suivi.php'], true);
}

/**
 * Contexte de la popup incitation certification (première certification ou montée de niveau).
 *
 * @return array<string, mixed>|null
 */
function vendeur_certification_promo_resolve_context(int $admin_id): ?array
{
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !vendeur_certification_admin_column_exists()) {
        return null;
    }
    require_once __DIR__ . '/model_admin.php';
    $admin = get_admin_by_id($admin_id);
    if (!$admin || ($admin['role'] ?? '') !== 'vendeur' || (string) ($admin['statut'] ?? '') !== 'actif') {
        return null;
    }
    if (vendeur_certification_get_demande_en_cours($admin_id)) {
        return null;
    }
    require_once __DIR__ . '/../includes/app_base.php';
    $cert_url = app_url('/admin/parametres/certification.php');

    $niveau = vendeur_certification_get_niveau_actif($admin_id);
    if ($niveau === null) {
        $meta = vendeur_certification_niveaux()['standard'];
        return [
            'mode' => 'first',
            'title' => 'Certifiez votre boutique',
            'subtitle' => 'Valorisez votre activité sur COLObanes et rassurez les acheteurs avant le premier achat.',
            'benefits' => [
                'Badge officiel visible sur votre vitrine',
                'Mise en avant de vos produits sur la marketplace',
                'Parcours de vérification simple et gratuit (niveau Standard)',
            ],
            'cta_label' => 'Découvrir la certification',
            'cta_href' => $cert_url,
        ];
    }

    $next = null;
    if ($niveau === 'standard') {
        $next = 'vip';
    } elseif ($niveau === 'vip') {
        $next = 'premium';
    }
    if ($next === null) {
        return null;
    }
    $check = vendeur_certification_peut_demander($admin_id, $next);
    if (empty($check['ok'])) {
        return null;
    }
    $next_meta = vendeur_certification_niveaux()[$next] ?? null;
    if (!$next_meta) {
        return null;
    }
    $benefits = [];
    if ($next === 'vip') {
        $benefits = [
            'Visibilité accrue de vos produits sur la marketplace',
            'Accès aux ventes aux enchères',
            'Badge VIP reconnu par les acheteurs',
        ];
    } else {
        $benefits = [
            'Visibilité maximale sur la marketplace',
            'Publication de statuts 24 h',
            'Badge Premium — niveau le plus élevé',
        ];
    }

    return [
        'mode' => 'upgrade',
        'next_niveau' => $next,
        'title' => 'Passez au niveau ' . vendeur_certification_niveau_label($next),
        'subtitle' => (string) ($next_meta['desc'] ?? ''),
        'benefits' => $benefits,
        'cta_label' => 'Voir les avantages ' . vendeur_certification_niveau_label($next),
        'cta_href' => $cert_url,
    ];
}

/** Délai minimum entre deux affichages de la popup « Passez au niveau supérieur ». */
if (!defined('VENDEUR_CERT_PROMO_INTERVAL_DAYS')) {
    define('VENDEUR_CERT_PROMO_INTERVAL_DAYS', 10);
}

function vendeur_certification_promo_get_snooze_until(int $admin_id): ?int
{
    if (!empty($_SESSION['vendeur_cert_promo_snooze_until'])) {
        $ts = (int) $_SESSION['vendeur_cert_promo_snooze_until'];
        return $ts > 0 ? $ts : null;
    }
    require_once __DIR__ . '/../includes/schema_vendeur_cert_promo.php';
    if (!vendeur_cert_promo_ensure_schema()) {
        return null;
    }
    global $db;
    if (!$db) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT cert_promo_snooze_until FROM vendeur_onboarding WHERE admin_id = :id LIMIT 1');
        $st->execute(['id' => $admin_id]);
        $raw = $st->fetchColumn();
        if ($raw === false || $raw === null || $raw === '') {
            return null;
        }
        $ts = strtotime((string) $raw);
        return $ts > 0 ? $ts : null;
    } catch (PDOException $e) {
        return null;
    }
}

function vendeur_certification_promo_set_snooze_days(int $admin_id, int $days): void
{
    $days = max(1, min(30, $days));
    $until = time() + ($days * 86400);
    $_SESSION['vendeur_cert_promo_snooze_until'] = $until;
    unset($_SESSION['vendeur_cert_promo_dismissed']);

    require_once __DIR__ . '/../includes/schema_vendeur_cert_promo.php';
    if (!vendeur_cert_promo_ensure_schema()) {
        return;
    }
    global $db;
    if (!$db) {
        return;
    }
    $dt = date('Y-m-d H:i:s', $until);
    try {
        $st = $db->prepare('
            INSERT INTO vendeur_onboarding (admin_id, cycle_started_at, cert_promo_snooze_until)
            VALUES (:id, NOW(), :snooze)
            ON DUPLICATE KEY UPDATE cert_promo_snooze_until = VALUES(cert_promo_snooze_until)
        ');
        $st->execute(['id' => $admin_id, 'snooze' => $dt]);
    } catch (PDOException $e) {
        error_log('[vendeur_certification_promo_set_snooze_days] ' . $e->getMessage());
    }
}

function vendeur_certification_promo_dismiss_for_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION['vendeur_cert_promo_dismissed'] = 1;
}

function vendeur_certification_promo_should_show_modal(int $admin_id): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE || $admin_id <= 0) {
        return false;
    }
    if (!empty($_SESSION['vendeur_cert_promo_dismissed'])) {
        return false;
    }
    if (vendeur_certification_promo_on_certification_page()) {
        return false;
    }
    $until = vendeur_certification_promo_get_snooze_until($admin_id);
    if ($until !== null && $until > time()) {
        return false;
    }
    return vendeur_certification_promo_resolve_context($admin_id) !== null;
}

function vendeur_certification_promo_sanitize_redirect(string $redirect): string
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
    if ($base === '' && $redirect[0] === '/') {
        return $redirect;
    }
    return $default;
}

function vendeur_certification_marquer_notif_vendeur_lue($demande_id, $admin_id) {
    global $db;
    $demande_id = (int) $demande_id;
    $admin_id = (int) $admin_id;
    if ($demande_id <= 0 || $admin_id <= 0 || !vendeur_certification_table_exists() || !$db) {
        return false;
    }
    if (!vendeur_certification_notif_lue_column_exists()) {
        return true;
    }
    try {
        $st = $db->prepare("
            UPDATE vendeur_certification_demandes
            SET vendeur_notif_lue = 1
            WHERE id = :id AND admin_id = :aid
        ");
        $st->execute(['id' => $demande_id, 'aid' => $admin_id]);
        return $st->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}
