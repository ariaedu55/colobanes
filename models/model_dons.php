<?php
/**
 * Module Dons — objets offerts gratuitement entre utilisateurs.
 *
 * Statuts d'un don : en_moderation, disponible, reserve, donne, retire, refuse
 *   (« Demande reçue » = disponible avec au moins une demande en attente, calculé à l'affichage).
 * Statuts d'une demande : en_attente, acceptee (réservation), refusee, annulee, remise, expiree.
 *
 * Le prix n'existe pas en base : un don vaut toujours 0 FCFA, aucune requête ne peut le changer.
 * Les changements de quantité / réservation se font dans des transactions avec verrou (FOR UPDATE).
 */

require_once __DIR__ . '/../conn/conn.php';

if (!function_exists('dons_ready')) {

/* ================= Schéma ================= */

function dons_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    global $db;
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS dons (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            titre VARCHAR(120) NOT NULL,
            description TEXT NOT NULL,
            categorie_id INT UNSIGNED NULL,
            etat ENUM('neuf','tres_bon','bon','usage','a_reparer') NOT NULL DEFAULT 'bon',
            quantite_initiale INT UNSIGNED NOT NULL DEFAULT 1,
            quantite_disponible INT UNSIGNED NOT NULL DEFAULT 1,
            region VARCHAR(40) NOT NULL DEFAULT '',
            zone VARCHAR(120) NOT NULL DEFAULT '',
            modalites VARCHAR(500) NOT NULL DEFAULT '',
            statut ENUM('en_moderation','disponible','reserve','donne','retire','refuse') NOT NULL DEFAULT 'disponible',
            motif_moderation VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_statut (statut, created_at),
            KEY idx_user (user_id),
            KEY idx_cat (categorie_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS dons_images (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            don_id INT UNSIGNED NOT NULL,
            chemin VARCHAR(255) NOT NULL,
            ordre TINYINT UNSIGNED NOT NULL DEFAULT 0,
            KEY idx_don (don_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS dons_categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(80) NOT NULL,
            icone VARCHAR(60) NOT NULL DEFAULT 'fa-box',
            actif TINYINT(1) NOT NULL DEFAULT 1,
            ordre SMALLINT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS dons_demandes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            don_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            statut ENUM('en_attente','acceptee','refusee','annulee','remise','expiree') NOT NULL DEFAULT 'en_attente',
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_don_user (don_id, user_id),
            KEY idx_statut (statut, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS dons_conversations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            don_id INT UNSIGNED NOT NULL,
            demandeur_id INT UNSIGNED NOT NULL,
            donateur_id INT UNSIGNED NOT NULL,
            last_message_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_don_demandeur (don_id, demandeur_id),
            KEY idx_donateur (donateur_id),
            KEY idx_demandeur (demandeur_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS dons_messages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT UNSIGNED NOT NULL,
            sender_id INT UNSIGNED NOT NULL,
            contenu TEXT NOT NULL,
            systeme TINYINT(1) NOT NULL DEFAULT 0,
            lu TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_conv (conversation_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS dons_historique (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            don_id INT UNSIGNED NOT NULL,
            demande_id INT UNSIGNED NULL,
            acteur_user_id INT UNSIGNED NULL,
            acteur_admin_id INT UNSIGNED NULL,
            action VARCHAR(40) NOT NULL,
            details VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_don (don_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS dons_signalements (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            don_id INT UNSIGNED NOT NULL,
            conversation_id INT UNSIGNED NULL,
            user_id INT UNSIGNED NOT NULL,
            motif VARCHAR(40) NOT NULL,
            details VARCHAR(500) NOT NULL DEFAULT '',
            statut ENUM('nouveau','traite') NOT NULL DEFAULT 'nouveau',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_statut (statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS dons_parametres (
            cle VARCHAR(40) NOT NULL PRIMARY KEY,
            valeur VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if ((int) $db->query('SELECT COUNT(*) FROM dons_categories')->fetchColumn() === 0) {
            $defaults = [
                ['Vêtements', 'fa-shirt'], ['Meubles', 'fa-couch'], ['Électronique', 'fa-tv'],
                ['Informatique', 'fa-laptop'], ['Livres', 'fa-book'], ['Jouets', 'fa-puzzle-piece'],
                ['Maison & cuisine', 'fa-kitchen-set'], ['Accessoires', 'fa-bag-shopping'],
                ['Bébé & enfant', 'fa-baby'], ['Sport & loisirs', 'fa-futbol'], ['Autres', 'fa-box-open'],
            ];
            $st = $db->prepare('INSERT INTO dons_categories (nom, icone, ordre) VALUES (:n, :i, :o)');
            foreach ($defaults as $i => $c) {
                $st->execute(['n' => $c[0], 'i' => $c[1], 'o' => $i]);
            }
        }
        /* Paramètres : écrire les valeurs par défaut si la table est vide (prod neuve) */
        if ((int) $db->query('SELECT COUNT(*) FROM dons_parametres')->fetchColumn() === 0) {
            $st = $db->prepare('INSERT INTO dons_parametres (cle, valeur) VALUES (:c, :v)');
            foreach (dons_param_defaults() as $cle => $valeur) {
                $st->execute(['c' => $cle, 'v' => (string) $valeur]);
            }
        }
        $ready = true;
    } catch (PDOException $e) {
        error_log('[dons_ready] ' . $e->getMessage());
        $ready = false;
    }
    return $ready;
}

/* ================= Paramètres ================= */

function dons_param_defaults(): array
{
    return [
        'actif' => '1',                 // module ouvert (publications + demandes)
        'moderation_prealable' => '0',  // 1 = les nouvelles annonces attendent une validation
        'reservation_heures' => '72',   // durée d'une réservation avant libération automatique
        'max_annonces_jour' => '5',     // anti-spam : annonces par utilisateur et par 24 h
        'notifications' => '1',         // notifications push / e-mail du module
    ];
}

function dons_param(string $cle): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = dons_param_defaults();
        if (dons_ready()) {
            global $db;
            foreach ($db->query('SELECT cle, valeur FROM dons_parametres')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                $cache[$k] = (string) $v;
            }
        }
    }
    return (string) ($cache[$cle] ?? '');
}

function dons_param_set(string $cle, string $valeur): bool
{
    if (!array_key_exists($cle, dons_param_defaults()) || !dons_ready()) {
        return false;
    }
    global $db;
    $st = $db->prepare('INSERT INTO dons_parametres (cle, valeur) VALUES (:c, :v) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)');
    return $st->execute(['c' => $cle, 'v' => $valeur]);
}

function dons_module_actif(): bool
{
    return dons_param('actif') === '1';
}

/** Compte actif (un compte suspendu par la modération passe en lecture seule). */
function dons_user_actif(int $user_id): bool
{
    global $db;
    $st = $db->prepare('SELECT statut FROM users WHERE id = :id');
    $st->execute(['id' => $user_id]);
    return $st->fetchColumn() === 'actif';
}

/* ================= Libellés ================= */

function dons_etats(): array
{
    return [
        'neuf' => 'Neuf',
        'tres_bon' => 'Très bon état',
        'bon' => 'Bon état',
        'usage' => 'Usagé',
        'a_reparer' => 'À réparer',
    ];
}

function dons_statut_label(string $statut): string
{
    return [
        'en_moderation' => 'En modération',
        'disponible' => 'Disponible',
        'reserve' => 'Réservé',
        'donne' => 'Donné',
        'retire' => 'Retiré',
        'refuse' => 'Refusé',
    ][$statut] ?? $statut;
}

function dons_demande_label(string $statut): string
{
    return [
        'en_attente' => 'En attente',
        'acceptee' => 'Réservé pour vous',
        'refusee' => 'Refusée',
        'annulee' => 'Annulée',
        'remise' => 'Reçu',
        'expiree' => 'Réservation expirée',
    ][$statut] ?? $statut;
}

function dons_motifs_signalement(): array
{
    return [
        'interdit' => 'Objet interdit ou dangereux',
        'payant' => 'Demande d’argent (ce n’est pas un don)',
        'arnaque' => 'Arnaque ou fausse annonce',
        'inapproprie' => 'Contenu inapproprié',
        'comportement' => 'Comportement abusif',
        'autre' => 'Autre',
    ];
}

function dons_categories(bool $actives_seulement = true): array
{
    if (!dons_ready()) {
        return [];
    }
    global $db;
    $sql = 'SELECT * FROM dons_categories' . ($actives_seulement ? ' WHERE actif = 1' : '') . ' ORDER BY ordre, nom';
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dons_temps_relatif(string $datetime): string
{
    $ts = strtotime($datetime);
    if (!$ts) {
        return '';
    }
    $d = time() - $ts;
    if ($d < 3600) {
        return 'il y a ' . max(1, (int) floor($d / 60)) . ' min';
    }
    if ($d < 86400) {
        return 'il y a ' . (int) floor($d / 3600) . ' h';
    }
    if ($d < 86400 * 7) {
        $j = (int) floor($d / 86400);
        return $j === 1 ? 'hier' : 'il y a ' . $j . ' jours';
    }
    return 'le ' . date('d/m/Y', $ts);
}

function dons_user_prenom(array $row, string $prefix = ''): string
{
    $p = trim((string) ($row[$prefix . 'prenom'] ?? ''));
    $n = trim((string) ($row[$prefix . 'nom'] ?? ''));
    if ($p !== '') {
        return $p . ($n !== '' ? ' ' . mb_strtoupper(mb_substr($n, 0, 1, 'UTF-8'), 'UTF-8') . '.' : '');
    }
    return $n !== '' ? $n : 'Membre COLObanes';
}

/* ================= Historique ================= */

function dons_historique_add(int $don_id, string $action, string $details = '', ?int $user_id = null, ?int $admin_id = null, ?int $demande_id = null): void
{
    global $db;
    try {
        $db->prepare('INSERT INTO dons_historique (don_id, demande_id, acteur_user_id, acteur_admin_id, action, details)
            VALUES (:d, :dm, :u, :a, :ac, :de)')->execute([
            'd' => $don_id, 'dm' => $demande_id, 'u' => $user_id, 'a' => $admin_id,
            'ac' => mb_substr($action, 0, 40), 'de' => mb_substr($details, 0, 255),
        ]);
    } catch (PDOException $e) {
        error_log('[dons_historique_add] ' . $e->getMessage());
    }
}

/* ================= Lecture ================= */

function dons_get(int $id): ?array
{
    if ($id <= 0 || !dons_ready()) {
        return null;
    }
    global $db;
    $st = $db->prepare("SELECT d.*, c.nom AS categorie_nom, c.icone AS categorie_icone,
            u.prenom AS donateur_prenom, u.nom AS donateur_nom, u.date_creation AS donateur_depuis, u.statut AS donateur_statut,
            (SELECT COUNT(*) FROM dons_demandes dd WHERE dd.don_id = d.id AND dd.statut = 'en_attente') AS nb_demandes,
            (SELECT COUNT(*) FROM dons_demandes dd WHERE dd.don_id = d.id AND dd.statut = 'acceptee') AS nb_reservees
        FROM dons d
        LEFT JOIN dons_categories c ON c.id = d.categorie_id
        LEFT JOIN users u ON u.id = d.user_id
        WHERE d.id = :id");
    $st->execute(['id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['images'] = dons_images($id);
    return $row;
}

function dons_images(int $don_id): array
{
    global $db;
    $st = $db->prepare('SELECT id, chemin FROM dons_images WHERE don_id = :d ORDER BY ordre, id');
    $st->execute(['d' => $don_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Un don est-il visible publiquement ? (le donateur et l'administration voient tout) */
function dons_est_public(array $don): bool
{
    return in_array($don['statut'], ['disponible', 'reserve'], true) && ($don['donateur_statut'] ?? 'actif') === 'actif';
}

/**
 * Liste publique : uniquement disponibles (et réservés si demandé), filtres + recherche.
 *
 * @return array{items: array, total: int}
 */
function dons_list_public(array $f, int $limit = 24, int $offset = 0): array
{
    if (!dons_ready()) {
        return ['items' => [], 'total' => 0];
    }
    global $db;
    dons_expirer_reservations();

    $where = ["u.statut = 'actif'"];
    $params = [];
    $where[] = !empty($f['avec_reserves']) ? "d.statut IN ('disponible','reserve')" : "d.statut = 'disponible'";
    if (!empty($f['q'])) {
        $where[] = '(d.titre LIKE :q1 OR d.description LIKE :q2)';
        $params['q1'] = $params['q2'] = '%' . $f['q'] . '%';
    }
    if (!empty($f['categorie'])) {
        $where[] = 'd.categorie_id = :cat';
        $params['cat'] = (int) $f['categorie'];
    }
    if (!empty($f['region'])) {
        $where[] = 'd.region = :reg';
        $params['reg'] = (string) $f['region'];
    }
    if (!empty($f['etat']) && isset(dons_etats()[$f['etat']])) {
        $where[] = 'd.etat = :etat';
        $params['etat'] = (string) $f['etat'];
    }
    $w = implode(' AND ', $where);

    $st = $db->prepare("SELECT COUNT(*) FROM dons d JOIN users u ON u.id = d.user_id WHERE $w");
    $st->execute($params);
    $total = (int) $st->fetchColumn();

    $st = $db->prepare("SELECT d.*, c.nom AS categorie_nom,
            (SELECT chemin FROM dons_images i WHERE i.don_id = d.id ORDER BY ordre, id LIMIT 1) AS image,
            (SELECT COUNT(*) FROM dons_demandes dd WHERE dd.don_id = d.id AND dd.statut = 'en_attente') AS nb_demandes
        FROM dons d
        JOIN users u ON u.id = d.user_id
        LEFT JOIN dons_categories c ON c.id = d.categorie_id
        WHERE $w
        ORDER BY d.created_at DESC
        LIMIT :lim OFFSET :off");
    foreach ($params as $k => $v) {
        $st->bindValue(':' . $k, $v);
    }
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->bindValue(':off', $offset, PDO::PARAM_INT);
    $st->execute();
    return ['items' => $st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total];
}

function dons_list_by_user(int $user_id): array
{
    if (!dons_ready()) {
        return [];
    }
    global $db;
    dons_expirer_reservations();
    $st = $db->prepare("SELECT d.*, c.nom AS categorie_nom,
            (SELECT chemin FROM dons_images i WHERE i.don_id = d.id ORDER BY ordre, id LIMIT 1) AS image
        FROM dons d LEFT JOIN dons_categories c ON c.id = d.categorie_id
        WHERE d.user_id = :u ORDER BY d.created_at DESC");
    $st->execute(['u' => $user_id]);
    $dons = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($dons as &$d) {
        $d['demandes'] = dons_demandes_for_don((int) $d['id']);
    }
    return $dons;
}

function dons_demandes_for_don(int $don_id): array
{
    global $db;
    $st = $db->prepare("SELECT dd.*, u.prenom, u.nom, cv.id AS conversation_id,
            (SELECT COUNT(*) FROM dons_messages m WHERE m.conversation_id = cv.id AND m.lu = 0 AND m.sender_id <> cv.donateur_id) AS non_lus
        FROM dons_demandes dd
        JOIN users u ON u.id = dd.user_id
        LEFT JOIN dons_conversations cv ON cv.don_id = dd.don_id AND cv.demandeur_id = dd.user_id
        WHERE dd.don_id = :d
        ORDER BY FIELD(dd.statut, 'acceptee', 'en_attente', 'remise', 'expiree', 'refusee', 'annulee'), dd.created_at");
    $st->execute(['d' => $don_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dons_demandes_by_user(int $user_id): array
{
    if (!dons_ready()) {
        return [];
    }
    global $db;
    dons_expirer_reservations();
    $st = $db->prepare("SELECT dd.*, d.titre, d.statut AS don_statut, d.region, d.zone, cv.id AS conversation_id,
            (SELECT chemin FROM dons_images i WHERE i.don_id = d.id ORDER BY ordre, id LIMIT 1) AS image,
            (SELECT COUNT(*) FROM dons_messages m WHERE m.conversation_id = cv.id AND m.lu = 0 AND m.sender_id <> cv.demandeur_id) AS non_lus
        FROM dons_demandes dd
        JOIN dons d ON d.id = dd.don_id
        LEFT JOIN dons_conversations cv ON cv.don_id = dd.don_id AND cv.demandeur_id = dd.user_id
        WHERE dd.user_id = :u ORDER BY dd.updated_at DESC");
    $st->execute(['u' => $user_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dons_demande_get(int $don_id, int $user_id): ?array
{
    global $db;
    $st = $db->prepare('SELECT * FROM dons_demandes WHERE don_id = :d AND user_id = :u');
    $st->execute(['d' => $don_id, 'u' => $user_id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

/* ================= Publication ================= */

/**
 * Valide et enregistre un don (création ou modification par son auteur).
 * Le prix n'est jamais lu depuis la requête : un don est gratuit par construction.
 *
 * @return array{ok: bool, id?: int, errors?: array}
 */
function dons_save(int $user_id, array $in, array $files = [], int $don_id = 0): array
{
    if (!dons_ready()) {
        return ['ok' => false, 'errors' => ['Service indisponible.']];
    }
    global $db;
    $statut_initial = dons_param('moderation_prealable') === '1' ? 'en_moderation' : 'disponible';
    if (!dons_user_actif($user_id)) {
        return ['ok' => false, 'errors' => ['Votre compte est suspendu : vous ne pouvez plus publier de dons.']];
    }
    $errors = [];
    $titre = trim((string) ($in['titre'] ?? ''));
    $description = trim((string) ($in['description'] ?? ''));
    $etat = (string) ($in['etat'] ?? '');
    $cat = (int) ($in['categorie_id'] ?? 0);
    $qte = (int) ($in['quantite'] ?? 1);
    $region = trim((string) ($in['region'] ?? ''));
    $zone = trim((string) ($in['zone'] ?? ''));
    $modalites = trim((string) ($in['modalites'] ?? ''));

    if (mb_strlen($titre) < 3 || mb_strlen($titre) > 120) {
        $errors[] = 'Le titre doit faire entre 3 et 120 caractères.';
    }
    if (mb_strlen($description) < 10 || mb_strlen($description) > 3000) {
        $errors[] = 'Décrivez l’objet en au moins 10 caractères.';
    }
    if (!isset(dons_etats()[$etat])) {
        $errors[] = 'Choisissez l’état de l’objet.';
    }
    $cat_ok = false;
    foreach (dons_categories() as $c) {
        if ((int) $c['id'] === $cat) {
            $cat_ok = true;
        }
    }
    if (!$cat_ok) {
        $errors[] = 'Choisissez une catégorie.';
    }
    if ($qte < 1 || $qte > 999) {
        $errors[] = 'La quantité doit être comprise entre 1 et 999.';
    }
    if (!function_exists('senegal_region_is_valid')) {
        require_once dirname(__DIR__) . '/includes/senegal_regions.php';
    }
    if ($region === '' || !senegal_region_is_valid($region)) {
        $errors[] = 'Choisissez la région de récupération.';
    }
    if (mb_strlen($zone) > 120 || mb_strlen($modalites) > 500) {
        $errors[] = 'La zone ou les modalités sont trop longues.';
    }

    /* Contenu : pas de numéro, d'e-mail ni d'insulte dans l'annonce publique */
    if (!function_exists('messages_vendeur_blocked_reason')) {
        require_once __DIR__ . '/model_messages_vendeur.php';
    }
    foreach ([$titre, $description, $zone, $modalites] as $txt) {
        $reason = messages_vendeur_blocked_reason($txt);
        if ($reason === 'contact') {
            $errors[] = 'Pour votre sécurité, n’écrivez pas de numéro ni d’e-mail dans l’annonce : les intéressés vous contacteront par la messagerie.';
            break;
        }
        if ($reason === 'insult') {
            $errors[] = 'L’annonce contient des termes inappropriés.';
            break;
        }
    }

    $existing = null;
    if ($don_id > 0) {
        $existing = dons_get($don_id);
        if (!$existing || (int) $existing['user_id'] !== $user_id) {
            return ['ok' => false, 'errors' => ['Annonce introuvable.']];
        }
        if (in_array($existing['statut'], ['donne', 'retire'], true)) {
            return ['ok' => false, 'errors' => ['Cette annonce ne peut plus être modifiée.']];
        }
    } else {
        /* Anti-spam : nombre d'annonces par 24 h */
        $st = $db->prepare('SELECT COUNT(*) FROM dons WHERE user_id = :u AND created_at > (NOW() - INTERVAL 1 DAY)');
        $st->execute(['u' => $user_id]);
        if ((int) $st->fetchColumn() >= max(1, (int) dons_param('max_annonces_jour'))) {
            $errors[] = 'Vous avez atteint le nombre maximum d’annonces pour aujourd’hui. Réessayez demain.';
        }
    }

    /* Photos */
    $uploads = [];
    if (!empty($files['photos']['name']) && is_array($files['photos']['name'])) {
        foreach ($files['photos']['name'] as $i => $n) {
            if ((int) ($files['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $uploads[] = [
                'name' => $n,
                'type' => $files['photos']['type'][$i] ?? '',
                'tmp_name' => $files['photos']['tmp_name'][$i] ?? '',
                'error' => $files['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['photos']['size'][$i] ?? 0,
            ];
        }
    }
    $remove_ids = array_map('intval', (array) ($in['supprimer_images'] ?? []));
    $nb_existantes = $existing ? count(array_filter($existing['images'], function ($im) use ($remove_ids) {
        return !in_array((int) $im['id'], $remove_ids, true);
    })) : 0;
    if ($nb_existantes + count($uploads) === 0) {
        $errors[] = 'Ajoutez au moins une photo de l’objet.';
    }
    if ($nb_existantes + count($uploads) > 6) {
        $errors[] = '6 photos maximum.';
    }
    foreach ($uploads as $u) {
        if ((int) $u['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Une photo n’a pas pu être envoyée.';
            break;
        }
        if ((int) $u['size'] > 8 * 1024 * 1024) {
            $errors[] = 'Chaque photo doit faire moins de 8 Mo.';
            break;
        }
        $mime = function_exists('finfo_open') ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($u['tmp_name']) : '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            $errors[] = 'Formats acceptés : JPG, PNG, WEBP.';
            break;
        }
    }

    if ($errors) {
        return ['ok' => false, 'errors' => array_values(array_unique($errors))];
    }

    if (!function_exists('upload_optimize_image_file')) {
        require_once dirname(__DIR__) . '/includes/image_optimizer.php';
    }
    $dest_dir = dirname(__DIR__) . '/upload/dons';
    if (!is_dir($dest_dir)) {
        @mkdir($dest_dir, 0755, true);
    }

    try {
        $db->beginTransaction();
        if ($existing) {
            /* La quantité disponible suit la nouvelle quantité, sans passer sous ce qui est déjà donné */
            $deja_donne = (int) $existing['quantite_initiale'] - (int) $existing['quantite_disponible'];
            $qte = max($qte, $deja_donne + 1);
            $db->prepare('UPDATE dons SET titre = :t, description = :de, categorie_id = :c, etat = :e,
                    quantite_initiale = :qi, quantite_disponible = :qd, region = :r, zone = :z, modalites = :m
                WHERE id = :id AND user_id = :u')->execute([
                't' => $titre, 'de' => $description, 'c' => $cat, 'e' => $etat, 'qi' => $qte,
                'qd' => $qte - $deja_donne, 'r' => $region, 'z' => $zone, 'm' => $modalites,
                'id' => $don_id, 'u' => $user_id,
            ]);
            if ($remove_ids) {
                foreach ($existing['images'] as $im) {
                    if (in_array((int) $im['id'], $remove_ids, true)) {
                        $db->prepare('DELETE FROM dons_images WHERE id = :id AND don_id = :d')->execute(['id' => $im['id'], 'd' => $don_id]);
                        if (function_exists('image_optimizer_delete_with_variants')) {
                            image_optimizer_delete_with_variants($im['chemin']);
                        }
                    }
                }
            }
            dons_historique_add($don_id, 'modifie', 'Annonce modifiée', $user_id);
        } else {
            $statut = $statut_initial;
            $db->prepare('INSERT INTO dons (user_id, titre, description, categorie_id, etat, quantite_initiale,
                    quantite_disponible, region, zone, modalites, statut)
                VALUES (:u, :t, :de, :c, :e, :q, :q2, :r, :z, :m, :s)')->execute([
                'u' => $user_id, 't' => $titre, 'de' => $description, 'c' => $cat, 'e' => $etat,
                'q' => $qte, 'q2' => $qte, 'r' => $region, 'z' => $zone, 'm' => $modalites, 's' => $statut,
            ]);
            $don_id = (int) $db->lastInsertId();
            dons_historique_add($don_id, 'publie', $statut === 'en_moderation' ? 'En attente de validation' : 'Publié', $user_id);
        }

        $ordre = 10;
        foreach ($uploads as $u) {
            $res = upload_optimize_image_file($u, $dest_dir, 'dons', 'don_');
            if (empty($res['success']) || empty($res['relative_path'])) {
                throw new RuntimeException('Upload photo impossible : ' . ($res['message'] ?? ''));
            }
            $chemin = (string) $res['relative_path'];
            $db->prepare('INSERT INTO dons_images (don_id, chemin, ordre) VALUES (:d, :c, :o)')
                ->execute(['d' => $don_id, 'c' => $chemin, 'o' => $ordre++]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[dons_save] ' . $e->getMessage());
        return ['ok' => false, 'errors' => ['Enregistrement impossible, réessayez.']];
    }

    dons_recalculer_statut($don_id);
    return ['ok' => true, 'id' => $don_id];
}

/* ================= Statuts et quantités ================= */

/** Recalcule le statut public d'un don selon sa quantité et ses réservations (hors statuts figés). */
function dons_recalculer_statut(int $don_id): void
{
    global $db;
    $st = $db->prepare("UPDATE dons d
        SET d.statut = CASE
            WHEN d.statut IN ('en_moderation','retire','refuse','donne') THEN d.statut
            WHEN d.quantite_disponible = 0 THEN 'donne'
            WHEN (SELECT COUNT(*) FROM dons_demandes dd WHERE dd.don_id = d.id AND dd.statut = 'acceptee') >= d.quantite_disponible THEN 'reserve'
            ELSE 'disponible' END
        WHERE d.id = :id");
    $st->execute(['id' => $don_id]);
}

/** Libère les réservations expirées (durée configurable). */
function dons_expirer_reservations(): void
{
    static $done = false;
    if ($done || !dons_ready()) {
        return;
    }
    $done = true;
    global $db;
    $rows = $db->query("SELECT id, don_id, user_id FROM dons_demandes WHERE statut = 'acceptee' AND expires_at IS NOT NULL AND expires_at < NOW()")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $db->prepare("UPDATE dons_demandes SET statut = 'expiree' WHERE id = :id AND statut = 'acceptee'")->execute(['id' => $r['id']]);
        dons_historique_add((int) $r['don_id'], 'reservation_expiree', 'Réservation libérée automatiquement', null, null, (int) $r['id']);
        dons_recalculer_statut((int) $r['don_id']);
    }
}

/**
 * Déclare l'intérêt d'un utilisateur : crée la demande + la conversation (ou rouvre l'existante).
 *
 * @return array{ok: bool, conversation_id?: int, message?: string}
 */
function dons_demander(int $don_id, int $user_id, string $message = ''): array
{
    if (!dons_module_actif()) {
        return ['ok' => false, 'message' => 'Le module Dons est momentanément fermé aux nouvelles demandes.'];
    }
    $don = dons_get($don_id);
    if (!$don || !dons_est_public($don)) {
        return ['ok' => false, 'message' => 'Ce don n’est plus disponible.'];
    }
    if ((int) $don['user_id'] === $user_id) {
        return ['ok' => false, 'message' => 'C’est votre propre annonce.'];
    }
    if (!dons_user_actif($user_id)) {
        return ['ok' => false, 'message' => 'Votre compte est suspendu.'];
    }
    $message = trim($message);
    if (!function_exists('messages_vendeur_blocked_reason')) {
        require_once __DIR__ . '/model_messages_vendeur.php';
    }
    if ($message !== '' && messages_vendeur_blocked_reason($message) !== null) {
        return ['ok' => false, 'message' => 'Votre message ne peut pas contenir de numéro, d’e-mail ou de termes inappropriés.'];
    }

    global $db;
    $existante = dons_demande_get($don_id, $user_id);
    $nouvelle = false;
    if (!$existante) {
        $db->prepare('INSERT INTO dons_demandes (don_id, user_id) VALUES (:d, :u)')->execute(['d' => $don_id, 'u' => $user_id]);
        $demande_id = (int) $db->lastInsertId();
        $nouvelle = true;
        dons_historique_add($don_id, 'demande', 'Nouvelle demande', $user_id, null, $demande_id);
    } elseif (in_array($existante['statut'], ['annulee', 'expiree', 'refusee'], true)) {
        if ($existante['statut'] === 'refusee') {
            return ['ok' => false, 'message' => 'Le donateur a déjà choisi une autre personne pour ce don.'];
        }
        $db->prepare("UPDATE dons_demandes SET statut = 'en_attente', expires_at = NULL WHERE id = :id")->execute(['id' => $existante['id']]);
        $demande_id = (int) $existante['id'];
        $nouvelle = true;
        dons_historique_add($don_id, 'demande', 'Demande renouvelée', $user_id, null, $demande_id);
    }

    $conv_id = dons_conversation_get_or_create($don_id, $user_id, (int) $don['user_id']);
    if ($nouvelle) {
        dons_message_add($conv_id, $user_id, $message !== '' ? $message : 'Bonjour, je suis intéressé(e) par votre don « ' . $don['titre'] . ' ».');
        dons_notifier((int) $don['user_id'], 'Nouvelle demande pour votre don', 'Quelqu’un est intéressé par « ' . $don['titre'] . ' ».', '/dons/conversation.php?id=' . $conv_id);
    }
    return ['ok' => true, 'conversation_id' => $conv_id];
}

/**
 * Action du donateur sur une demande : accepter (réserver), refuser, confirmer la remise.
 * Verrou sur le don pour éviter les doubles attributions d'un même exemplaire.
 */
function dons_demande_action(int $demande_id, int $donateur_id, string $action): array
{
    global $db;
    /* Schéma et paramètres chargés hors transaction (un CREATE TABLE validerait la transaction) */
    if (!dons_ready()) {
        return ['ok' => false, 'message' => 'Service indisponible.'];
    }
    $h = max(1, (int) dons_param('reservation_heures'));
    try {
        $db->beginTransaction();
        $st = $db->prepare('SELECT dd.*, d.user_id AS donateur_id, d.titre, d.statut AS don_statut, d.quantite_disponible
            FROM dons_demandes dd JOIN dons d ON d.id = dd.don_id WHERE dd.id = :id FOR UPDATE');
        $st->execute(['id' => $demande_id]);
        $dm = $st->fetch(PDO::FETCH_ASSOC);
        if (!$dm || (int) $dm['donateur_id'] !== $donateur_id) {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Demande introuvable.'];
        }
        $don_id = (int) $dm['don_id'];
        $db->prepare('SELECT id FROM dons WHERE id = :id FOR UPDATE')->execute(['id' => $don_id]);

        if ($action === 'accepter') {
            if ($dm['statut'] !== 'en_attente' || !in_array($dm['don_statut'], ['disponible', 'reserve'], true)) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Cette demande ne peut pas être acceptée.'];
            }
            $st = $db->prepare("SELECT COUNT(*) FROM dons_demandes WHERE don_id = :d AND statut = 'acceptee'");
            $st->execute(['d' => $don_id]);
            if ((int) $st->fetchColumn() >= (int) $dm['quantite_disponible']) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Tous les exemplaires sont déjà réservés.'];
            }
            $db->prepare("UPDATE dons_demandes SET statut = 'acceptee', expires_at = (NOW() + INTERVAL $h HOUR) WHERE id = :id")
                ->execute(['id' => $demande_id]);
            $msg = 'Bonne nouvelle : l’objet vous est réservé pendant ' . $h . ' h. Convenons ici du lieu et de l’heure de récupération.';
            $notif = ['Votre demande est acceptée', '« ' . $dm['titre'] . ' » vous est réservé.'];
        } elseif ($action === 'refuser') {
            if (!in_array($dm['statut'], ['en_attente', 'acceptee'], true)) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Cette demande est déjà clôturée.'];
            }
            $db->prepare("UPDATE dons_demandes SET statut = 'refusee', expires_at = NULL WHERE id = :id")->execute(['id' => $demande_id]);
            $msg = 'Le donateur a attribué cet objet à une autre personne. Merci pour votre intérêt !';
            $notif = ['Demande non retenue', '« ' . $dm['titre'] . ' » a été attribué à quelqu’un d’autre.'];
        } elseif ($action === 'donner') {
            if ($dm['statut'] !== 'acceptee') {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Réservez d’abord l’objet pour cette personne.'];
            }
            $upd = $db->prepare('UPDATE dons SET quantite_disponible = quantite_disponible - 1 WHERE id = :id AND quantite_disponible > 0');
            $upd->execute(['id' => $don_id]);
            if ($upd->rowCount() !== 1) {
                $db->rollBack();
                return ['ok' => false, 'message' => 'Plus aucun exemplaire disponible.'];
            }
            $db->prepare("UPDATE dons_demandes SET statut = 'remise', expires_at = NULL WHERE id = :id")->execute(['id' => $demande_id]);
            $db->prepare("UPDATE dons SET statut = 'donne' WHERE id = :id AND quantite_disponible = 0 AND statut IN ('disponible','reserve')")
                ->execute(['id' => $don_id]);
            $msg = 'Remise confirmée. Merci pour ce beau geste !';
            $notif = ['Don remis', 'La remise de « ' . $dm['titre'] . ' » est confirmée.'];
        } else {
            $db->rollBack();
            return ['ok' => false, 'message' => 'Action inconnue.'];
        }
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[dons_demande_action] ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Action impossible, réessayez.'];
    }

    dons_recalculer_statut($don_id);
    dons_historique_add($don_id, 'demande_' . $action, $msg, $donateur_id, null, $demande_id);
    $conv = dons_conversation_get_or_create($don_id, (int) $dm['user_id'], $donateur_id);
    dons_message_add($conv, $donateur_id, $msg, true);
    dons_notifier((int) $dm['user_id'], $notif[0], $notif[1], '/dons/conversation.php?id=' . $conv);
    return ['ok' => true, 'message' => $msg];
}

/** Le demandeur annule sa demande (libère la réservation éventuelle). */
function dons_demande_annuler(int $don_id, int $user_id): array
{
    global $db;
    $dm = dons_demande_get($don_id, $user_id);
    if (!$dm || !in_array($dm['statut'], ['en_attente', 'acceptee'], true)) {
        return ['ok' => false, 'message' => 'Aucune demande en cours.'];
    }
    $db->prepare("UPDATE dons_demandes SET statut = 'annulee', expires_at = NULL WHERE id = :id")->execute(['id' => $dm['id']]);
    dons_recalculer_statut($don_id);
    dons_historique_add($don_id, 'demande_annulee', 'Demande annulée par le demandeur', $user_id, null, (int) $dm['id']);
    $don = dons_get($don_id);
    if ($don) {
        $conv = dons_conversation_get_or_create($don_id, $user_id, (int) $don['user_id']);
        dons_message_add($conv, $user_id, 'J’ai annulé ma demande. Merci quand même !', true);
    }
    return ['ok' => true, 'message' => 'Demande annulée.'];
}

/** Le donateur retire son annonce : les demandes en cours sont clôturées. */
function dons_retirer(int $don_id, int $user_id): array
{
    global $db;
    $don = dons_get($don_id);
    if (!$don || (int) $don['user_id'] !== $user_id || in_array($don['statut'], ['donne', 'retire'], true)) {
        return ['ok' => false, 'message' => 'Annonce introuvable.'];
    }
    $db->prepare("UPDATE dons SET statut = 'retire' WHERE id = :id")->execute(['id' => $don_id]);
    $db->prepare("UPDATE dons_demandes SET statut = 'annulee', expires_at = NULL WHERE don_id = :id AND statut IN ('en_attente','acceptee')")
        ->execute(['id' => $don_id]);
    dons_historique_add($don_id, 'retire', 'Annonce retirée par le donateur', $user_id);
    return ['ok' => true, 'message' => 'Annonce retirée.'];
}

/* ================= Conversations ================= */

function dons_conversation_get_or_create(int $don_id, int $demandeur_id, int $donateur_id): int
{
    global $db;
    $db->prepare('INSERT IGNORE INTO dons_conversations (don_id, demandeur_id, donateur_id) VALUES (:d, :u, :o)')
        ->execute(['d' => $don_id, 'u' => $demandeur_id, 'o' => $donateur_id]);
    $st = $db->prepare('SELECT id FROM dons_conversations WHERE don_id = :d AND demandeur_id = :u');
    $st->execute(['d' => $don_id, 'u' => $demandeur_id]);
    return (int) $st->fetchColumn();
}

/** Conversation, uniquement si l'utilisateur en est participant. */
function dons_conversation_for_user(int $conv_id, int $user_id): ?array
{
    if (!dons_ready()) {
        return null;
    }
    global $db;
    $st = $db->prepare("SELECT cv.*, d.titre, d.statut AS don_statut, d.user_id AS don_user_id,
            (SELECT chemin FROM dons_images i WHERE i.don_id = d.id ORDER BY ordre, id LIMIT 1) AS image,
            dd.id AS demande_id, dd.statut AS demande_statut, dd.expires_at,
            ud.prenom AS demandeur_prenom, ud.nom AS demandeur_nom,
            uo.prenom AS donateur_prenom, uo.nom AS donateur_nom
        FROM dons_conversations cv
        JOIN dons d ON d.id = cv.don_id
        LEFT JOIN dons_demandes dd ON dd.don_id = cv.don_id AND dd.user_id = cv.demandeur_id
        LEFT JOIN users ud ON ud.id = cv.demandeur_id
        LEFT JOIN users uo ON uo.id = cv.donateur_id
        WHERE cv.id = :id AND (cv.demandeur_id = :u1 OR cv.donateur_id = :u2)");
    $st->execute(['id' => $conv_id, 'u1' => $user_id, 'u2' => $user_id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function dons_conversations_by_user(int $user_id): array
{
    if (!dons_ready()) {
        return [];
    }
    global $db;
    $st = $db->prepare("SELECT cv.*, d.titre,
            (SELECT chemin FROM dons_images i WHERE i.don_id = d.id ORDER BY ordre, id LIMIT 1) AS image,
            (SELECT contenu FROM dons_messages m WHERE m.conversation_id = cv.id ORDER BY id DESC LIMIT 1) AS dernier,
            (SELECT COUNT(*) FROM dons_messages m WHERE m.conversation_id = cv.id AND m.lu = 0 AND m.sender_id <> :u0) AS non_lus,
            IF(cv.donateur_id = :u1, ud.prenom, uo.prenom) AS autre_prenom,
            IF(cv.donateur_id = :u2, ud.nom, uo.nom) AS autre_nom
        FROM dons_conversations cv
        JOIN dons d ON d.id = cv.don_id
        LEFT JOIN users ud ON ud.id = cv.demandeur_id
        LEFT JOIN users uo ON uo.id = cv.donateur_id
        WHERE cv.demandeur_id = :u3 OR cv.donateur_id = :u4
        ORDER BY COALESCE(cv.last_message_at, cv.created_at) DESC");
    $st->execute(['u0' => $user_id, 'u1' => $user_id, 'u2' => $user_id, 'u3' => $user_id, 'u4' => $user_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dons_count_non_lus(int $user_id): int
{
    if ($user_id <= 0 || !dons_ready()) {
        return 0;
    }
    global $db;
    $st = $db->prepare('SELECT COUNT(*) FROM dons_messages m JOIN dons_conversations cv ON cv.id = m.conversation_id
        WHERE m.lu = 0 AND m.sender_id <> :u0 AND (cv.demandeur_id = :u1 OR cv.donateur_id = :u2)');
    $st->execute(['u0' => $user_id, 'u1' => $user_id, 'u2' => $user_id]);
    return (int) $st->fetchColumn();
}

function dons_message_add(int $conv_id, int $sender_id, string $contenu, bool $systeme = false): int
{
    global $db;
    $db->prepare('INSERT INTO dons_messages (conversation_id, sender_id, contenu, systeme) VALUES (:c, :s, :t, :y)')
        ->execute(['c' => $conv_id, 's' => $sender_id, 't' => mb_substr($contenu, 0, 2000), 'y' => $systeme ? 1 : 0]);
    $id = (int) $db->lastInsertId();
    $db->prepare('UPDATE dons_conversations SET last_message_at = NOW() WHERE id = :id')->execute(['id' => $conv_id]);
    return $id;
}

/**
 * Envoi d'un message par un participant (filtres : numéros, e-mails, insultes).
 */
function dons_message_envoyer(int $conv_id, int $user_id, string $contenu): array
{
    $conv = dons_conversation_for_user($conv_id, $user_id);
    if (!$conv) {
        return ['ok' => false, 'message' => 'Conversation introuvable.'];
    }
    $contenu = trim($contenu);
    if ($contenu === '' || mb_strlen($contenu) > 2000) {
        return ['ok' => false, 'message' => 'Message vide ou trop long.'];
    }
    if (!function_exists('messages_vendeur_blocked_reason')) {
        require_once __DIR__ . '/model_messages_vendeur.php';
    }
    $reason = messages_vendeur_blocked_reason($contenu);
    if ($reason === 'contact') {
        return ['ok' => false, 'message' => 'Pour votre sécurité, les numéros et e-mails ne sont pas envoyés. Convenez du lieu de rencontre ici.'];
    }
    if ($reason === 'insult') {
        return ['ok' => false, 'message' => 'Message bloqué : termes inappropriés.'];
    }
    $id = dons_message_add($conv_id, $user_id, $contenu);
    $dest = (int) $conv['donateur_id'] === $user_id ? (int) $conv['demandeur_id'] : (int) $conv['donateur_id'];
    dons_notifier($dest, 'Nouveau message — Dons', mb_strimwidth($contenu, 0, 90, '…', 'UTF-8'), '/dons/conversation.php?id=' . $conv_id, true);
    return ['ok' => true, 'id' => $id];
}

function dons_messages(int $conv_id, int $after_id = 0): array
{
    global $db;
    $st = $db->prepare('SELECT id, sender_id, contenu, systeme, created_at FROM dons_messages
        WHERE conversation_id = :c AND id > :a ORDER BY id LIMIT 300');
    $st->execute(['c' => $conv_id, 'a' => $after_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dons_messages_marquer_lus(int $conv_id, int $user_id): void
{
    global $db;
    $db->prepare('UPDATE dons_messages SET lu = 1 WHERE conversation_id = :c AND sender_id <> :u AND lu = 0')
        ->execute(['c' => $conv_id, 'u' => $user_id]);
}

/* ================= Signalements ================= */

function dons_signaler(int $don_id, int $user_id, string $motif, string $details = '', ?int $conv_id = null): array
{
    if (!isset(dons_motifs_signalement()[$motif])) {
        return ['ok' => false, 'message' => 'Choisissez un motif.'];
    }
    global $db;
    $st = $db->prepare("SELECT COUNT(*) FROM dons_signalements WHERE don_id = :d AND user_id = :u AND statut = 'nouveau'");
    $st->execute(['d' => $don_id, 'u' => $user_id]);
    if ((int) $st->fetchColumn() > 0) {
        return ['ok' => true, 'message' => 'Votre signalement est déjà en cours de traitement.'];
    }
    $db->prepare('INSERT INTO dons_signalements (don_id, conversation_id, user_id, motif, details) VALUES (:d, :c, :u, :m, :t)')
        ->execute(['d' => $don_id, 'c' => $conv_id, 'u' => $user_id, 'm' => $motif, 't' => mb_substr(trim($details), 0, 500)]);
    dons_historique_add($don_id, 'signale', dons_motifs_signalement()[$motif], $user_id);
    if (dons_param('notifications') === '1' && function_exists('notification_dispatch_to_platform_admins') === false) {
        @include_once dirname(__DIR__) . '/services/notification_dispatch.php';
    }
    if (dons_param('notifications') === '1' && function_exists('notification_dispatch_to_platform_admins')) {
        try {
            notification_dispatch_to_platform_admins('Signalement — Dons', 'Une annonce de don a été signalée.', ['link' => '/admin/dons/index.php?tab=signalements']);
        } catch (Throwable $e) {
            error_log('[dons_signaler] ' . $e->getMessage());
        }
    }
    return ['ok' => true, 'message' => 'Merci, l’équipe COLObanes va vérifier.'];
}

/* ================= Notifications ================= */

function dons_notifier(int $user_id, string $titre, string $texte, string $lien, bool $push_seul = false): void
{
    if ($user_id <= 0 || dons_param('notifications') !== '1') {
        return;
    }
    try {
        if (!function_exists('notification_dispatch_to_user')) {
            require_once dirname(__DIR__) . '/services/notification_dispatch.php';
        }
        if ($push_seul) {
            notification_dispatch_push_only_to_user($user_id, $titre, $texte, ['link' => $lien, 'type' => 'dons']);
        } else {
            notification_dispatch_to_user($user_id, $titre, $texte, ['link' => $lien, 'type' => 'dons']);
        }
    } catch (Throwable $e) {
        error_log('[dons_notifier] ' . $e->getMessage());
    }
}

/* ================= Administration ================= */

function dons_admin_list(array $f, int $limit = 50): array
{
    if (!dons_ready()) {
        return [];
    }
    global $db;
    $where = ['1=1'];
    $params = [];
    if (!empty($f['statut'])) {
        $where[] = 'd.statut = :s';
        $params['s'] = $f['statut'];
    }
    if (!empty($f['q'])) {
        $where[] = '(d.titre LIKE :q OR u.nom LIKE :q2 OR u.prenom LIKE :q3)';
        $params['q'] = $params['q2'] = $params['q3'] = '%' . $f['q'] . '%';
    }
    $st = $db->prepare('SELECT d.*, u.prenom, u.nom, u.statut AS user_statut, c.nom AS categorie_nom,
            (SELECT chemin FROM dons_images i WHERE i.don_id = d.id ORDER BY ordre, id LIMIT 1) AS image,
            (SELECT COUNT(*) FROM dons_signalements s WHERE s.don_id = d.id AND s.statut = \'nouveau\') AS nb_signalements,
            (SELECT COUNT(*) FROM dons_demandes dd WHERE dd.don_id = d.id) AS nb_demandes
        FROM dons d JOIN users u ON u.id = d.user_id LEFT JOIN dons_categories c ON c.id = d.categorie_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY d.created_at DESC LIMIT ' . (int) $limit);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dons_admin_set_statut(int $don_id, string $statut, int $admin_id, string $motif = ''): bool
{
    if (!in_array($statut, ['disponible', 'retire', 'refuse', 'en_moderation'], true)) {
        return false;
    }
    global $db;
    $db->prepare('UPDATE dons SET statut = :s, motif_moderation = :m WHERE id = :id')
        ->execute(['s' => $statut, 'm' => $motif !== '' ? mb_substr($motif, 0, 255) : null, 'id' => $don_id]);
    if (in_array($statut, ['retire', 'refuse'], true)) {
        $db->prepare("UPDATE dons_demandes SET statut = 'annulee', expires_at = NULL WHERE don_id = :id AND statut IN ('en_attente','acceptee')")
            ->execute(['id' => $don_id]);
    }
    if ($statut === 'disponible') {
        dons_recalculer_statut($don_id);
    }
    dons_historique_add($don_id, 'admin_' . $statut, $motif !== '' ? $motif : dons_statut_label($statut), null, $admin_id);
    $don = dons_get($don_id);
    if ($don && $statut !== 'en_moderation') {
        $txt = $statut === 'disponible' ? 'Votre annonce « ' . $don['titre'] . ' » est en ligne.' : 'Votre annonce « ' . $don['titre'] . ' » a été retirée par la modération.';
        dons_notifier((int) $don['user_id'], 'Annonce de don', $txt, '/dons/mes-dons.php');
    }
    return true;
}

function dons_admin_signalements(string $statut = 'nouveau'): array
{
    if (!dons_ready()) {
        return [];
    }
    global $db;
    $st = $db->prepare('SELECT s.*, d.titre, d.statut AS don_statut, d.user_id AS donateur_id,
            u.prenom, u.nom, uo.prenom AS donateur_prenom, uo.nom AS donateur_nom, uo.statut AS donateur_statut
        FROM dons_signalements s
        JOIN dons d ON d.id = s.don_id
        LEFT JOIN users u ON u.id = s.user_id
        LEFT JOIN users uo ON uo.id = d.user_id
        WHERE s.statut = :s ORDER BY s.created_at DESC LIMIT 100');
    $st->execute(['s' => $statut]);
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function dons_admin_stats(): array
{
    if (!dons_ready()) {
        return [];
    }
    global $db;
    $out = $db->query('SELECT statut, COUNT(*) FROM dons GROUP BY statut')->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    $out['signalements'] = (int) $db->query("SELECT COUNT(*) FROM dons_signalements WHERE statut = 'nouveau'")->fetchColumn();
    return $out;
}

}
