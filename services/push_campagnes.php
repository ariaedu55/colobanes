<?php
/**
 * Campagnes push automatiques — faire revenir les gens sur COLObanes pour acheter, vendre ou donner.
 *
 * Clients (appareils type « user ») :
 *   - nouveautes_boutiques : « 5 boutiques ont ajouté des nouveautés » (au plus tous les 3 jours) ;
 *   - dons                 : « Il fait frais : offrez les habits que vous ne portez plus » (tous les 10 jours,
 *                            message selon la saison, en alternance avec « N objets offerts près de chez vous »).
 * Vendeurs (appareils type « admin ») :
 *   - vendeurs_annonces    : nouvelle fonctionnalité (ex. vente en gros), envoyée UNE fois par vendeur ;
 *   - vendeurs_conseils    : conseil utile (ajouter des produits, faire une promo), au plus tous les 7 jours.
 *
 * Anti-saturation (comme les grandes marketplaces) :
 *   - plages horaires où les gens regardent leur téléphone (celles des relances : 10h–13h, 17h–21h) ;
 *   - 1 campagne maximum par appareil et par 24 h, 3 maximum par 7 jours ;
 *   - rien si l'appareil a reçu une relance d'inactivité ou une autre notification dans les 24 h ;
 *   - un message n'est envoyé que s'il a du contenu réel (ex. au moins 3 nouveaux produits).
 *
 * Lancé automatiquement par services/taches_auto.php (toutes les 15 min).
 * Aperçu sans envoi : php scripts/cron_push_campagnes.php --dry-run
 */
declare(strict_types=1);

require_once __DIR__ . '/reengagement_push.php';

function push_campagnes_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $defaults = [
        'enabled' => true,
        'campagnes' => [
            'vendeurs_annonces' => true,
            'nouveautes_boutiques' => true,
            'dons' => true,
            'vendeurs_conseils' => true,
        ],
        /** Jours minimum entre deux envois d'une même campagne à un appareil. */
        'cooldown_jours' => [
            'nouveautes_boutiques' => 3,
            'dons' => 10,
            'vendeurs_conseils' => 7,
        ],
        'max_par_24h' => 1,
        'max_par_7j' => 3,
        /** Appareils non revus depuis N jours : ignorés (téléphone changé, app désinstallée). */
        'appareil_actif_jours' => 180,
        /** Appareils traités par campagne et par exécution. */
        'max_par_campagne' => 400,
        /** Nouveautés : fenêtre et minimum pour qu'un message vaille la peine. */
        'nouveautes_jours' => 3,
        'nouveautes_min_produits' => 3,
    ];
    $file = __DIR__ . '/../config/push_campagnes.php';
    $custom = is_file($file) ? require $file : [];
    $cfg = array_replace_recursive($defaults, is_array($custom) ? $custom : []);
    return $cfg;
}

/**
 * Annonces de fonctionnalités aux vendeurs. Chaque annonce part une seule fois par boutique.
 * Pour en ajouter une : une nouvelle entrée avec un identifiant jamais utilisé.
 *
 * @return array<string, array{titre:string, texte:string, lien:string, cible_sql:string}>
 */
function push_campagnes_annonces_vendeurs(): array
{
    return [
        'vente_gros_v1' => [
            'titre' => '📦 Nouveau : vendez en gros sur COLObanes',
            'texte' => 'Fixez un prix par quantité (10 pièces, 50 pièces…) et attirez les revendeurs. Ça se règle dans la fiche produit.',
            'lien' => '/admin/produits/index.php',
            /* Seulement les boutiques qui n'ont encore aucun produit en gros */
            'cible_sql' => "NOT EXISTS (SELECT 1 FROM produits p WHERE p.admin_id = a.id AND p.gros_paliers IS NOT NULL AND p.gros_paliers <> '' AND p.gros_paliers <> '[]')",
        ],
    ];
}

function push_campagnes_ensure_schema(PDO $db): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `push_campagnes_envois` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `campagne` VARCHAR(40) NOT NULL,
                `cle` VARCHAR(64) NOT NULL DEFAULT '',
                `token_id` INT NOT NULL,
                `user_id` INT NULL DEFAULT NULL,
                `admin_id` INT NULL DEFAULT NULL,
                `titre` VARCHAR(190) NOT NULL DEFAULT '',
                `date_envoi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_token_date` (`token_id`, `date_envoi`),
                KEY `idx_campagne_token` (`campagne`, `token_id`),
                KEY `idx_campagne_admin` (`campagne`, `cle`, `admin_id`),
                KEY `idx_date` (`date_envoi`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        return $ok = true;
    } catch (PDOException $e) {
        error_log('[push_campagnes_ensure_schema] ' . $e->getMessage());
        return $ok = false;
    }
}

/**
 * Appareils autorisés à recevoir une campagne maintenant (règles anti-saturation communes).
 * $extra_where / $params : filtre propre à la campagne.
 */
function push_campagnes_cibles(PDO $db, string $type, string $extra_where, array $params, array $cfg): array
{
    $actif_j = max(7, (int) $cfg['appareil_actif_jours']);
    $max24 = max(1, (int) $cfg['max_par_24h']);
    $max7 = max(1, (int) $cfg['max_par_7j']);
    $lim = max(1, (int) $cfg['max_par_campagne']);
    $join_admin = $type === 'admin' ? 'INNER JOIN admin a ON a.id = ft.admin_id AND a.statut = \'actif\'' : '';
    $sql = "
        SELECT ft.id, ft.token, ft.user_id, ft.admin_id, ft.user_agent
        FROM fcm_tokens ft
        $join_admin
        WHERE ft.type = :type
          AND ft.token <> ''
          AND COALESCE(ft.last_seen_at, ft.date_creation) >= NOW() - INTERVAL $actif_j DAY
          AND (ft.last_reengage_at IS NULL OR ft.last_reengage_at < NOW() - INTERVAL 24 HOUR)
          AND (SELECT COUNT(*) FROM push_campagnes_envois e
               WHERE e.token_id = ft.id AND e.date_envoi >= NOW() - INTERVAL 24 HOUR) < $max24
          AND (SELECT COUNT(*) FROM push_campagnes_envois e
               WHERE e.token_id = ft.id AND e.date_envoi >= NOW() - INTERVAL 7 DAY) < $max7
          AND (ft.user_id IS NULL OR NOT EXISTS (
               SELECT 1 FROM notification_log nl
               WHERE nl.user_id = ft.user_id AND nl.push_sent = 1
                 AND nl.date_envoi >= NOW() - INTERVAL 24 HOUR))
          AND ($extra_where)
        ORDER BY COALESCE(ft.last_seen_at, ft.date_creation) DESC
        LIMIT $lim
    ";
    try {
        $st = $db->prepare($sql);
        $st->execute([':type' => $type] + $params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[push_campagnes_cibles] ' . $e->getMessage());
        return [];
    }
}

/** Pas reçu cette campagne depuis N jours. */
function push_campagnes_sql_cooldown(string $campagne, int $jours): string
{
    $c = preg_replace('/[^a-z_]/', '', $campagne);
    return "NOT EXISTS (SELECT 1 FROM push_campagnes_envois e2 WHERE e2.token_id = ft.id
            AND e2.campagne = '$c' AND e2.date_envoi >= NOW() - INTERVAL " . max(1, $jours) . ' DAY)';
}

function push_campagnes_message_fcm(array $cible, array $msg): array
{
    $abs = firebase_absolute_url($msg['lien']);
    $notification = ['title' => $msg['titre'], 'body' => $msg['texte']];
    if (($msg['image'] ?? '') !== '') {
        $notification['image'] = $msg['image'];
    }
    $collapse = 'campagne_' . $msg['campagne'];
    $data = firebase_prepare_push_data($msg['titre'], $msg['texte'], [
        'type' => 'campagne',
        'campaign' => $msg['campagne'],
        'link' => $msg['lien'],
        'url' => $abs,
    ]);
    $message = [
        'token' => (string) $cible['token'],
        'notification' => $notification,
        'data' => $data,
        'android' => [
            'priority' => 'normal',
            'ttl' => '86400s',
            'collapse_key' => $collapse,
            'notification' => ['channel_id' => 'colobanes_channel', 'tag' => $collapse],
        ],
        'apns' => [
            'headers' => [
                'apns-priority' => '5',
                'apns-collapse-id' => $collapse,
                'apns-expiration' => (string) (time() + 86400),
            ],
            'payload' => ['aps' => ['sound' => 'default', 'mutable-content' => 1]],
        ],
    ];
    /* Navigateur (vendeurs sur le site, PWA) : clic → bonne page */
    if (stripos((string) ($cible['user_agent'] ?? ''), 'ColobanesApp') === false) {
        $message['webpush'] = [
            'notification' => [
                'title' => $msg['titre'],
                'body' => $msg['texte'],
                'icon' => firebase_absolute_url('/image/logo_market.jpeg'),
            ],
            'fcm_options' => ['link' => $abs],
        ];
    }
    return ['message' => $message];
}

/* ───────────── Contenu des campagnes ───────────── */

/** Nouveautés des boutiques sur les derniers jours (calculé une fois par exécution). */
function push_campagnes_contenu_nouveautes(PDO $db, array $cfg): ?array
{
    $jours = max(1, (int) $cfg['nouveautes_jours']);
    try {
        $rows = $db->query("
            SELECT p.admin_id, COALESCE(NULLIF(TRIM(a.boutique_nom), ''), TRIM(CONCAT(a.prenom, ' ', a.nom))) AS boutique,
                   COUNT(*) AS n, MAX(p.date_creation) AS dernier,
                   SUBSTRING_INDEX(GROUP_CONCAT(p.image_principale ORDER BY p.date_creation DESC SEPARATOR '\n'), '\n', 1) AS image
            FROM produits p
            INNER JOIN admin a ON a.id = p.admin_id AND a.statut = 'actif'
            WHERE p.statut = 'actif' AND p.date_creation >= NOW() - INTERVAL $jours DAY
            GROUP BY p.admin_id
            ORDER BY n DESC, dernier DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[push_campagnes_contenu_nouveautes] ' . $e->getMessage());
        return null;
    }
    $total = (int) array_sum(array_column($rows, 'n'));
    if ($total < max(1, (int) $cfg['nouveautes_min_produits'])) {
        return null;
    }
    $nb = count($rows);
    $noms = array_values(array_filter(array_map(static fn ($r) => reengagement_short((string) $r['boutique'], 22), $rows)));
    require_once __DIR__ . '/reengagement_personalization.php';
    $image = reengagement_image_url((string) ($rows[0]['image'] ?? ''));

    if ($nb >= 2) {
        $titre = "🆕 {$nb} boutiques ont ajouté des nouveautés";
        $qui = $nb === 2 ? "{$noms[0]} et {$noms[1]}" : "{$noms[0]}, {$noms[1]} et " . ($nb - 2) . ' autre' . ($nb - 2 > 1 ? 's' : '');
        $texte = "{$qui} viennent de mettre en ligne {$total} articles. Soyez les premiers à les voir !";
    } else {
        $titre = "🆕 {$noms[0]} a ajouté {$total} nouveautés";
        $texte = 'De nouveaux articles viennent d’arriver sur COLObanes. Soyez les premiers à les voir !';
    }
    return ['campagne' => 'nouveautes_boutiques', 'cle' => date('Y-m-d'), 'titre' => $titre, 'texte' => $texte, 'lien' => '/nouveautes.php', 'image' => $image];
}

/**
 * Messages « dons » : un message de saison pour donner, et un pour voir les dons disponibles.
 *
 * @return list<array>
 */
function push_campagnes_contenu_dons(PDO $db, ?int $mois = null): array
{
    if (!function_exists('dons_module_actif')) {
        $f = __DIR__ . '/../models/model_dons.php';
        if (is_file($f)) {
            require_once $f;
        }
    }
    if (!function_exists('dons_module_actif') || !dons_module_actif()) {
        return [];
    }
    $mois = $mois ?? (int) date('n');
    if (in_array($mois, [11, 12, 1, 2], true)) {
        $donner = ['🧥 Il fait frais : vos habits peuvent réchauffer quelqu’un',
            'Pulls, vestes, couvertures que vous ne portez plus ? Offrez-les en 1 minute sur COLObanes.'];
    } elseif (in_array($mois, [9, 10], true)) {
        $donner = ['🎒 C’est la rentrée : offrez ce qui ne sert plus',
            'Sacs, livres, cahiers, uniformes… Un élève près de chez vous en a besoin. Publiez-les gratuitement.'];
    } elseif (in_array($mois, [6, 7, 8], true)) {
        $donner = ['☀️ Rangement d’été : donnez une seconde vie à vos affaires',
            'Habits, meubles, objets que vous n’utilisez plus : quelqu’un près de chez vous peut en profiter.'];
    } else {
        $donner = ['🤝 Ce que vous n’utilisez plus peut aider quelqu’un',
            'Habits, meubles, objets… Publiez-les gratuitement sur COLObanes, les personnes intéressées vous contactent.'];
    }
    $out = [[
        'campagne' => 'dons', 'cle' => 'donner', 'titre' => $donner[0], 'texte' => $donner[1],
        'lien' => '/dons/offrir.php', 'image' => '',
    ]];
    try {
        $n = (int) $db->query("SELECT COALESCE(SUM(quantite_disponible), 0) FROM dons WHERE statut = 'disponible'")->fetchColumn();
    } catch (PDOException $e) {
        $n = 0;
    }
    if ($n >= 3) {
        $out[] = [
            'campagne' => 'dons', 'cle' => 'recevoir', 'titre' => "🎁 {$n} objets offerts gratuitement",
            'texte' => 'Des habitants donnent des habits et des objets près de chez vous. Venez voir avant qu’ils ne partent.',
            'lien' => '/dons/', 'image' => '',
        ];
    }
    return $out;
}

/** Conseils vendeurs, dans l'ordre de rotation. */
function push_campagnes_conseils_vendeurs(): array
{
    return [
        'ajouter_produits' => [
            'titre' => '🛍️ Les clients attendent vos nouveautés',
            'texte' => 'Ajoutez 1 ou 2 produits aujourd’hui : les nouveautés sont annoncées aux clients sur leur téléphone.',
            'lien' => '/admin/produits/ajouter.php',
            /* Aucun produit ajouté depuis 10 jours */
            'cible_sql' => 'NOT EXISTS (SELECT 1 FROM produits p WHERE p.admin_id = a.id AND p.date_creation >= NOW() - INTERVAL 10 DAY)',
        ],
        'faire_promo' => [
            'titre' => '🔥 Une promo pour vendre plus vite ?',
            'texte' => 'Les produits en promotion apparaissent dans la page Promos et dans les alertes bons plans des clients.',
            'lien' => '/admin/produits/index.php',
            /* Des produits, mais aucun en promotion */
            'cible_sql' => "EXISTS (SELECT 1 FROM produits p WHERE p.admin_id = a.id AND p.statut = 'actif')
                AND NOT EXISTS (SELECT 1 FROM produits p WHERE p.admin_id = a.id AND p.statut = 'actif'
                    AND p.prix_promotion IS NOT NULL AND p.prix_promotion > 0 AND p.prix_promotion < p.prix)",
        ],
    ];
}

/* ───────────── Exécution ───────────── */

/**
 * @return array{ok:bool, envoyes:int, invalides:int, erreurs:int, par_campagne:array, skipped?:string, apercu?:array}
 */
function push_campagnes_run(bool $force = false, bool $dry_run = false): array
{
    global $db;
    $cfg = push_campagnes_config();
    $out = ['ok' => true, 'envoyes' => 0, 'invalides' => 0, 'erreurs' => 0, 'par_campagne' => []];
    if (empty($cfg['enabled'])) {
        return $out + ['skipped' => 'désactivé'];
    }
    if (!$db instanceof PDO || !push_campagnes_ensure_schema($db) || !reengagement_ensure_schema($db)) {
        return ['ok' => false] + $out + ['skipped' => 'schéma'];
    }
    if (!$force && !reengagement_in_send_window(reengagement_config())) {
        return $out + ['skipped' => 'hors plage horaire'];
    }
    if (!is_dir(REENGAGEMENT_STORAGE_DIR)) {
        @mkdir(REENGAGEMENT_STORAGE_DIR, 0775, true);
    }
    $lock = @fopen(REENGAGEMENT_STORAGE_DIR . '/push_campagnes.lock', 'c+');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        return $out + ['skipped' => 'déjà en cours'];
    }

    try {
        $on = (array) $cfg['campagnes'];
        $cd = (array) $cfg['cooldown_jours'];
        /** @var list<array{cible:array, msg:array}> $lots */
        $lots = [];
        $deja = [];   // un seul message par appareil et par exécution
        $ajouter = static function (array $cibles, callable $msg_pour) use (&$lots, &$deja) {
            foreach ($cibles as $c) {
                $id = (int) $c['id'];
                if (isset($deja[$id])) {
                    continue;
                }
                $m = $msg_pour($c);
                if ($m) {
                    $deja[$id] = true;
                    $lots[] = ['cible' => $c, 'msg' => $m];
                }
            }
        };

        /* 1. Vendeurs : annonces de nouvelles fonctionnalités (une fois par boutique) */
        if (!empty($on['vendeurs_annonces'])) {
            foreach (push_campagnes_annonces_vendeurs() as $cle => $a) {
                $cle_sql = preg_replace('/[^a-z0-9_]/', '', $cle);
                $where = "{$a['cible_sql']} AND NOT EXISTS (SELECT 1 FROM push_campagnes_envois e3
                    WHERE e3.campagne = 'vendeurs_annonces' AND e3.cle = '$cle_sql' AND e3.admin_id = ft.admin_id)";
                $msg = ['campagne' => 'vendeurs_annonces', 'cle' => $cle, 'titre' => $a['titre'], 'texte' => $a['texte'], 'lien' => $a['lien'], 'image' => ''];
                $ajouter(push_campagnes_cibles($db, 'admin', $where, [], $cfg), static fn ($c) => $msg);
            }
        }

        /* 2. Clients : nouveautés des boutiques */
        if (!empty($on['nouveautes_boutiques']) && ($nv = push_campagnes_contenu_nouveautes($db, $cfg))) {
            $where = push_campagnes_sql_cooldown('nouveautes_boutiques', (int) ($cd['nouveautes_boutiques'] ?? 3));
            $ajouter(push_campagnes_cibles($db, 'user', $where, [], $cfg), static fn ($c) => $nv);
        }

        /* 3. Clients : dons (message de saison / dons disponibles, en alternance) */
        if (!empty($on['dons']) && ($dons = push_campagnes_contenu_dons($db))) {
            $where = push_campagnes_sql_cooldown('dons', (int) ($cd['dons'] ?? 10));
            $cibles = push_campagnes_cibles($db, 'user', $where, [], $cfg);
            $deja_recus = push_campagnes_compte_par_token($db, 'dons', array_column($cibles, 'id'));
            $ajouter($cibles, static fn ($c) => $dons[($deja_recus[(int) $c['id']] ?? 0) % count($dons)]);
        }

        /* 4. Vendeurs : conseils (rotation, seulement si le conseil les concerne) */
        if (!empty($on['vendeurs_conseils'])) {
            $cooldown = push_campagnes_sql_cooldown('vendeurs_conseils', (int) ($cd['vendeurs_conseils'] ?? 7));
            foreach (push_campagnes_conseils_vendeurs() as $cle => $t) {
                $cle_sql = preg_replace('/[^a-z0-9_]/', '', $cle);
                /* Le même conseil ne revient pas avant 30 jours */
                $where = "$cooldown AND {$t['cible_sql']} AND NOT EXISTS (SELECT 1 FROM push_campagnes_envois e4
                    WHERE e4.campagne = 'vendeurs_conseils' AND e4.cle = '$cle_sql' AND e4.admin_id = ft.admin_id
                      AND e4.date_envoi >= NOW() - INTERVAL 30 DAY)";
                $msg = ['campagne' => 'vendeurs_conseils', 'cle' => $cle, 'titre' => $t['titre'], 'texte' => $t['texte'], 'lien' => $t['lien'], 'image' => ''];
                $ajouter(push_campagnes_cibles($db, 'admin', $where, [], $cfg), static fn ($c) => $msg);
            }
        }

        foreach ($lots as $l) {
            $k = $l['msg']['campagne'];
            $out['par_campagne'][$k] = ($out['par_campagne'][$k] ?? 0) + 1;
        }
        if (!$lots) {
            return $out;
        }
        if ($dry_run) {
            $out['apercu'] = array_map(static fn ($l) => [
                'appareil' => (int) $l['cible']['id'],
                'client' => (int) ($l['cible']['user_id'] ?? 0),
                'vendeur' => (int) ($l['cible']['admin_id'] ?? 0),
                'campagne' => $l['msg']['campagne'] . '/' . $l['msg']['cle'],
                'titre' => $l['msg']['titre'],
                'texte' => $l['msg']['texte'],
                'lien' => $l['msg']['lien'],
            ], $lots);
            return $out;
        }

        $fcm_cfg = _firebase_get_config();
        $cred = (string) ($fcm_cfg['credentials_path'] ?? '');
        $access_token = firebase_get_access_token($cred);
        $project_id = (string) _firebase_get_project_id($cred);
        if (!$access_token || $project_id === '') {
            return ['ok' => false] + $out + ['skipped' => 'identifiants Firebase manquants'];
        }

        $jobs = [];
        $par_id = [];
        foreach ($lots as $l) {
            $id = (int) $l['cible']['id'];
            $par_id[$id] = $l;
            $jobs[] = ['id' => $id, 'payload' => push_campagnes_message_fcm($l['cible'], $l['msg'])];
        }
        $res = reengagement_send_parallel($access_token, $project_id, $jobs, reengagement_config());

        $ins = $db->prepare('INSERT INTO push_campagnes_envois (campagne, cle, token_id, user_id, admin_id, titre) VALUES (?, ?, ?, ?, ?, ?)');
        $invalides = [];
        foreach ($res as $id => $r) {
            if ($r === 'ok') {
                $l = $par_id[$id];
                $ins->execute([
                    $l['msg']['campagne'], mb_substr((string) $l['msg']['cle'], 0, 64), $id,
                    ($l['cible']['user_id'] ?? null) ?: null, ($l['cible']['admin_id'] ?? null) ?: null,
                    mb_substr($l['msg']['titre'], 0, 190),
                ]);
                $out['envoyes']++;
            } elseif ($r === 'invalid') {
                $invalides[] = (int) $id;
            } else {
                $out['erreurs']++;
            }
        }
        if ($invalides) {
            $db->exec('DELETE FROM fcm_tokens WHERE id IN (' . implode(',', $invalides) . ')');
            $out['invalides'] = count($invalides);
        }
        /* Ménage : l'historique au-delà de 180 jours ne sert plus */
        if (random_int(1, 50) === 1) {
            $db->exec('DELETE FROM push_campagnes_envois WHERE date_envoi < NOW() - INTERVAL 180 DAY');
        }
        return $out;
    } catch (Throwable $e) {
        error_log('[push_campagnes_run] ' . $e->getMessage());
        return ['ok' => false] + $out;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Nombre d'envois passés d'une campagne, par appareil (sert à alterner les messages). */
function push_campagnes_compte_par_token(PDO $db, string $campagne, array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $st = $db->prepare('SELECT token_id, COUNT(*) FROM push_campagnes_envois WHERE campagne = ? AND token_id IN ('
        . implode(',', $ids) . ') GROUP BY token_id');
    $st->execute([$campagne]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: []);
}
