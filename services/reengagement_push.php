<?php
/**
 * Relances push « reviens sur l'app » — appareils inactifs depuis 3 à 4 jours.
 *
 * Pensé pour ne pas charger le serveur :
 * - contenu (nouveautés, promos) calculé UNE fois par exécution, puis le même
 *   message est envoyé à tout le lot (aucune requête par destinataire) ;
 * - lots plafonnés (max_per_run) et étalés sur les plages horaires où les gens
 *   ouvrent leur téléphone (10h–13h, 17h–21h) ;
 * - envoi FCM HTTP v1 en parallèle (curl_multi, N connexions), priorité
 *   « normale » (économie batterie), un seul jeton d'accès Google par run ;
 * - verrou fichier : deux exécutions ne se chevauchent jamais ;
 * - 3 relances maximum par période d'inactivité (J+3, J+7, J+11), remise à
 *   zéro dès que l'utilisateur rouvre l'app (api/save_fcm_token.php) ;
 * - pas de relance si l'utilisateur a déjà reçu un autre push dans les 48 h.
 *
 * Lancé par le bundle cron (services/notifications_cron_bundle.php) ou :
 *   php scripts/cron_reengagement_push.php [--dry-run]
 */
declare(strict_types=1);

require_once __DIR__ . '/firebase_push.php';

if (!defined('REENGAGEMENT_STORAGE_DIR')) {
    define('REENGAGEMENT_STORAGE_DIR', __DIR__ . '/../storage/notifications_cron');
}

function reengagement_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $defaults = [
        'enabled' => true,
        /** Inactif depuis au moins N jours (dernière ouverture de l'app). */
        'inactive_days_min' => 3,
        /** Au-delà, l'appareil est considéré perdu : on arrête. */
        'inactive_days_max' => 45,
        /** Écart minimum entre deux relances pour un même appareil. */
        'gap_days' => 4,
        /** Nombre maximum de relances par période d'inactivité. */
        'max_steps' => 3,
        /** Appareils traités par exécution (cron toutes les 15 min → ~1 600/h max). */
        'max_per_run' => 400,
        /** Requêtes FCM simultanées. */
        'concurrency' => 25,
        /** Pause entre deux vagues parallèles (ms). */
        'wave_pause_ms' => 150,
        /** Délai minimum entre deux exécutions (s). */
        'min_interval_seconds' => 900,
        /** Plages d'envoi (heure locale du site, [début, fin[). */
        'send_windows' => [[10, 13], [17, 21]],
        /** Pas de relance si un autre push a été envoyé à ce compte récemment. */
        'skip_if_pushed_within_hours' => 48,
    ];
    $file = __DIR__ . '/../config/reengagement_push.php';
    $custom = is_file($file) ? require $file : [];
    $cfg = array_merge($defaults, is_array($custom) ? $custom : []);
    return $cfg;
}

/**
 * Colonnes de suivi sur fcm_tokens (idempotent, vérifié une seule fois grâce
 * à un fichier repère).
 */
function reengagement_ensure_schema(PDO $db): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    require_once dirname(__DIR__) . '/includes/repere_base.php';
    if (repere_ok('reengagement_schema_v1')) {
        return $ok = true;
    }
    try {
        $cols = $db->query("
            SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fcm_tokens'
        ")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $add = [];
        if (!in_array('platform', $cols, true)) {
            $add[] = "ADD COLUMN `platform` VARCHAR(16) NULL DEFAULT NULL AFTER `type`";
        }
        if (!in_array('last_seen_at', $cols, true)) {
            $add[] = "ADD COLUMN `last_seen_at` DATETIME NULL DEFAULT NULL";
        }
        if (!in_array('last_reengage_at', $cols, true)) {
            $add[] = "ADD COLUMN `last_reengage_at` DATETIME NULL DEFAULT NULL";
        }
        if (!in_array('reengage_count', $cols, true)) {
            $add[] = "ADD COLUMN `reengage_count` TINYINT UNSIGNED NOT NULL DEFAULT 0";
        }
        if ($add) {
            $db->exec('ALTER TABLE `fcm_tokens` ' . implode(', ', $add));
        }
        $idx = $db->query("SHOW INDEX FROM `fcm_tokens` WHERE Key_name = 'idx_reengage'")->fetch();
        if (!$idx) {
            $db->exec('ALTER TABLE `fcm_tokens` ADD INDEX `idx_reengage` (`type`, `last_seen_at`)');
        }
        $db->exec('UPDATE `fcm_tokens` SET `last_seen_at` = `date_creation` WHERE `last_seen_at` IS NULL');
        if (!is_dir(REENGAGEMENT_STORAGE_DIR)) {
            @mkdir(REENGAGEMENT_STORAGE_DIR, 0775, true);
        }
        repere_poser('reengagement_schema_v1');
        return $ok = true;
    } catch (PDOException $e) {
        error_log('[reengagement_ensure_schema] ' . $e->getMessage());
        return $ok = false;
    }
}

/** Heure courante dans une plage d'envoi ? */
function reengagement_in_send_window(array $cfg, ?int $hour = null): bool
{
    $hour = $hour ?? (int) date('G');
    foreach ((array) $cfg['send_windows'] as $w) {
        if (is_array($w) && count($w) === 2 && $hour >= (int) $w[0] && $hour < (int) $w[1]) {
            return true;
        }
    }
    return false;
}

/**
 * Chiffres du moment, calculés une seule fois par exécution (3 requêtes légères).
 *
 * @return array{promo_count:int, new_count:int, promo_nom:string, promo_pct:int, promo_image:string}
 */
function reengagement_campaign_content(PDO $db): array
{
    $content = ['promo_count' => 0, 'new_count' => 0, 'promo_nom' => '', 'promo_pct' => 0, 'promo_image' => ''];
    $catalogue = "statut IN ('actif', 'rupture_stock')";
    $promo = "prix_promotion IS NOT NULL AND prix_promotion > 0 AND prix_promotion < prix";
    try {
        $content['promo_count'] = (int) $db->query("SELECT COUNT(*) FROM produits WHERE $catalogue AND $promo")->fetchColumn();
        $content['new_count'] = (int) $db->query("
            SELECT COUNT(*) FROM produits
            WHERE $catalogue AND date_creation >= NOW() - INTERVAL 7 DAY
        ")->fetchColumn();
        $top = $db->query("
            SELECT nom, image_principale, ROUND((prix - prix_promotion) / prix * 100) AS pct
            FROM produits
            WHERE $catalogue AND $promo AND stock > 0
            ORDER BY pct DESC, date_creation DESC
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);
        if ($top) {
            $content['promo_nom'] = trim((string) $top['nom']);
            $content['promo_pct'] = (int) $top['pct'];
            require_once __DIR__ . '/reengagement_personalization.php';
            $content['promo_image'] = reengagement_image_url((string) ($top['image_principale'] ?? ''));
        }
    } catch (PDOException $e) {
        error_log('[reengagement_campaign_content] ' . $e->getMessage());
    }
    return $content;
}

/** Raccourcit un nom de produit pour tenir dans une notification. */
function reengagement_short(string $text, int $max = 32): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $max - 1)) . '…';
}

/**
 * Message selon l'étape de relance (0 = J+3, 1 = J+7, 2 = J+11).
 * Ordre : messages personnels (panier, favori en promo, boutique suivie, produit vu, rayon)
 * puis messages généraux (bon plan du jour, nouveautés, rappel). Chaque relance prend le
 * message suivant de la liste : jamais deux fois le même texte de suite.
 *
 * @param list<array{key:string, title:string, body:string, link:string, image:string}> $personal
 * @return array{key:string, title:string, body:string, link:string, image:string}
 */
function reengagement_pick_message(array $c, int $step, array $personal = [], ?int $day_seed = null): array
{
    $seed = $day_seed ?? (int) date('z');
    $promo_n = $c['promo_count'];
    $new_n = $c['new_count'];
    $pct = $c['promo_pct'];
    $nom = reengagement_short($c['promo_nom']);

    $generic = [];
    if ($promo_n >= 3) {
        $generic[] = [
            'key' => 'promos',
            'title' => ($pct >= 10 && $nom !== '') ? "⚡ Bon plan du jour : -{$pct} %" : "🔥 {$promo_n} produits en promotion",
            'body' => ($pct >= 10 && $nom !== '')
                ? "{$nom} et {$promo_n} autres articles à prix réduit. Les stocks partent vite !"
                : 'Profitez des petits prix du moment sur COLObanes, les stocks sont limités.',
            'link' => '/promo.php',
            'image' => $c['promo_image'],
        ];
    }
    if ($new_n >= 3) {
        $generic[] = [
            'key' => 'nouveautes',
            'title' => $seed % 2 ? "✨ {$new_n} nouveautés cette semaine" : "🆕 {$new_n} nouveaux articles à découvrir",
            'body' => $seed % 2
                ? 'De nouvelles boutiques et de nouveaux articles vous attendent sur COLObanes.'
                : 'Soyez parmi les premiers à les voir avant qu’ils ne partent.',
            'link' => '/nouveautes.php',
            'image' => '',
        ];
    }
    $generic[] = [
        'key' => 'top_ventes',
        'title' => '🏆 Les plus vendus du moment',
        'body' => 'Découvrez les articles que tout le monde s’arrache en ce moment sur COLObanes.',
        'link' => '/top-ventes.php',
        'image' => '',
    ];
    $generic[] = [
        'key' => 'retour',
        'title' => $seed % 2 ? '👋 Vous nous manquez !' : '🛍️ Quoi de neuf sur COLObanes ?',
        'body' => $seed % 2
            ? 'Vos boutiques ont ajouté des articles depuis votre dernière visite. Venez jeter un œil !'
            : 'Des nouveautés et des petits prix vous attendent. Ça ne prend qu’une minute.',
        'link' => '/',
        'image' => '',
    ];

    $candidates = [];
    $seen = [];
    foreach (array_merge($personal, $generic) as $m) {
        if (!isset($seen[$m['key']])) {
            $seen[$m['key']] = true;
            $candidates[] = $m;
        }
    }
    return $candidates[min(max(0, $step), count($candidates) - 1)];
}

/** Appareils à relancer, les plus anciennement vus d'abord. */
function reengagement_select_targets(PDO $db, array $cfg): array
{
    $skip_h = max(0, (int) $cfg['skip_if_pushed_within_hours']);
    $recent_push = $skip_h > 0 ? "
        AND (ft.user_id IS NULL OR NOT EXISTS (
            SELECT 1 FROM notification_log nl
            WHERE nl.user_id = ft.user_id AND nl.push_sent = 1
              AND nl.date_envoi >= NOW() - INTERVAL $skip_h HOUR
        ))" : '';
    $sql = "
        SELECT ft.id, ft.token, ft.user_id, ft.reengage_count
        FROM fcm_tokens ft
        WHERE ft.type = 'user'
          AND ft.token <> ''
          AND ft.last_seen_at <= NOW() - INTERVAL :min_days DAY
          AND ft.last_seen_at >= NOW() - INTERVAL :max_days DAY
          AND ft.reengage_count < :max_steps
          AND (ft.last_reengage_at IS NULL OR ft.last_reengage_at <= NOW() - INTERVAL :gap_days DAY)
          AND (ft.platform IN ('ios', 'android') OR ft.user_agent LIKE '%ColobanesApp%')
          $recent_push
        ORDER BY ft.last_seen_at ASC
        LIMIT :lim
    ";
    try {
        $st = $db->prepare($sql);
        $st->bindValue(':min_days', max(1, (int) $cfg['inactive_days_min']), PDO::PARAM_INT);
        $st->bindValue(':max_days', max(2, (int) $cfg['inactive_days_max']), PDO::PARAM_INT);
        $st->bindValue(':max_steps', max(1, (int) $cfg['max_steps']), PDO::PARAM_INT);
        $st->bindValue(':gap_days', max(1, (int) $cfg['gap_days']), PDO::PARAM_INT);
        $st->bindValue(':lim', max(1, (int) $cfg['max_per_run']), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        // notification_log absente sur une vieille base : on réessaie sans ce filtre
        if ($recent_push !== '' && stripos($e->getMessage(), 'notification_log') !== false) {
            return reengagement_select_targets($db, ['skip_if_pushed_within_hours' => 0] + $cfg);
        }
        error_log('[reengagement_select_targets] ' . $e->getMessage());
        return [];
    }
}

function reengagement_build_fcm_message(string $token, array $msg): array
{
    $abs = firebase_absolute_url($msg['link']);
    $notification = ['title' => $msg['title'], 'body' => $msg['body']];
    if ($msg['image'] !== '') {
        $notification['image'] = $msg['image'];
    }
    return ['message' => [
        'token' => $token,
        'notification' => $notification,
        'data' => [
            'type' => 'reengagement',
            'campaign' => $msg['key'],
            'link' => $msg['link'],
            'url' => $abs,
        ],
        'android' => [
            'priority' => 'normal',
            'ttl' => '86400s',
            'collapse_key' => 'reengagement',
            'notification' => ['channel_id' => 'colobanes_channel', 'tag' => 'reengagement'],
        ],
        'apns' => [
            'headers' => [
                'apns-priority' => '5',
                'apns-collapse-id' => 'reengagement',
                'apns-expiration' => (string) (time() + 86400),
            ],
            'payload' => ['aps' => ['sound' => 'default', 'mutable-content' => 1]],
        ],
    ]];
}

/**
 * Envoi parallèle. Retourne par id : 'ok' | 'invalid' (jeton à supprimer) | 'error'.
 *
 * @param array<int, array{id:int, payload:array}> $jobs
 */
function reengagement_send_parallel(string $access_token, string $project_id, array $jobs, array $cfg): array
{
    $url = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";
    $fcm_cfg = _firebase_get_config();
    $cacert = $fcm_cfg['cacert_path'] ?? __DIR__ . '/../config/cacert.pem';
    $concurrency = max(1, (int) $cfg['concurrency']);
    $pause_us = max(0, (int) $cfg['wave_pause_ms']) * 1000;
    $results = [];

    foreach (array_chunk($jobs, $concurrency) as $wave_index => $wave) {
        if ($wave_index > 0 && $pause_us > 0) {
            usleep($pause_us);
        }
        $mh = curl_multi_init();
        $handles = [];
        foreach ($wave as $job) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($job['payload'], JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            if (defined('CURL_HTTP_VERSION_2TLS')) {
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2TLS);
            }
            if (is_file($cacert)) {
                curl_setopt($ch, CURLOPT_CAINFO, $cacert);
            }
            curl_multi_add_handle($mh, $ch);
            $handles[(int) $job['id']] = $ch;
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $id => $ch) {
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $body = (string) curl_multi_getcontent($ch);
            if ($code === 200) {
                $results[$id] = 'ok';
            } else {
                $err = json_decode($body, true);
                $fcm_code = '';
                foreach ((array) ($err['error']['details'] ?? []) as $d) {
                    if (!empty($d['errorCode'])) {
                        $fcm_code = (string) $d['errorCode'];
                    }
                }
                // Jeton mort (app désinstallée, jeton expiré) : à supprimer
                $results[$id] = ($code === 404 || $fcm_code === 'UNREGISTERED'
                    || ($code === 400 && $fcm_code === 'INVALID_ARGUMENT' && stripos($body, 'registration token') !== false))
                    ? 'invalid' : 'error';
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
    }
    return $results;
}

/**
 * @return array{ok:bool, sent:int, invalid:int, errors:int, skipped?:string, targets?:int, preview?:array}
 */
function reengagement_run(bool $force = false, bool $dry_run = false): array
{
    global $db;
    $cfg = reengagement_config();
    $out = ['ok' => true, 'sent' => 0, 'invalid' => 0, 'errors' => 0];

    if (empty($cfg['enabled'])) {
        return $out + ['skipped' => 'disabled'];
    }
    if (!$db instanceof PDO || !reengagement_ensure_schema($db)) {
        return ['ok' => false] + $out + ['skipped' => 'schema'];
    }
    if (!$force && !reengagement_in_send_window($cfg)) {
        return $out + ['skipped' => 'outside_send_window'];
    }

    if (!is_dir(REENGAGEMENT_STORAGE_DIR)) {
        @mkdir(REENGAGEMENT_STORAGE_DIR, 0775, true);
    }
    $stamp = REENGAGEMENT_STORAGE_DIR . '/reengagement_last_run';
    if (!$force && is_file($stamp) && time() - (int) @filemtime($stamp) < (int) $cfg['min_interval_seconds']) {
        return $out + ['skipped' => 'not_due'];
    }
    $lock = @fopen(REENGAGEMENT_STORAGE_DIR . '/reengagement.lock', 'c+');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        return $out + ['skipped' => 'locked'];
    }

    try {
        if (!$dry_run) {
            @touch($stamp);
        }
        $targets = reengagement_select_targets($db, $cfg);
        $out['targets'] = count($targets);
        if (!$targets) {
            return $out;
        }

        $content = reengagement_campaign_content($db);
        require_once __DIR__ . '/reengagement_personalization.php';
        $personal = reengagement_personal_messages($db, array_column($targets, 'user_id'));

        $jobs = [];
        $msg_by_id = [];
        foreach ($targets as $t) {
            $uid = (int) ($t['user_id'] ?? 0);
            $msg = reengagement_pick_message($content, (int) $t['reengage_count'], $personal[$uid] ?? []);
            $msg_by_id[(int) $t['id']] = $msg + ['user_id' => $uid, 'step' => (int) $t['reengage_count']];
            $jobs[] = ['id' => (int) $t['id'], 'payload' => reengagement_build_fcm_message((string) $t['token'], $msg)];
        }

        if ($dry_run) {
            $out['preview'] = array_slice(array_map(static fn ($m) => [
                'user_id' => $m['user_id'], 'step' => $m['step'], 'key' => $m['key'],
                'title' => $m['title'], 'body' => $m['body'], 'link' => $m['link'], 'image' => $m['image'] !== '',
            ], $msg_by_id), 0, 20);
            return $out;
        }

        $fcm_cfg = _firebase_get_config();
        $cred = (string) ($fcm_cfg['credentials_path'] ?? '');
        $access_token = firebase_get_access_token($cred);
        $project_id = (string) _firebase_get_project_id($cred);
        if (!$access_token || $project_id === '') {
            return ['ok' => false] + $out + ['skipped' => 'no_fcm_credentials'];
        }

        $results = reengagement_send_parallel($access_token, $project_id, $jobs, $cfg);
        $ok_ids = array_keys(array_filter($results, static fn ($r) => $r === 'ok'));
        $invalid_ids = array_keys(array_filter($results, static fn ($r) => $r === 'invalid'));
        $error_ids = array_keys(array_filter($results, static fn ($r) => $r === 'error'));
        $out['sent'] = count($ok_ids);
        $out['invalid'] = count($invalid_ids);
        $out['errors'] = count($error_ids);

        // Mises à jour groupées : une requête par catégorie, pas une par appareil
        if ($ok_ids) {
            $db->exec('UPDATE fcm_tokens SET last_reengage_at = NOW(), reengage_count = reengage_count + 1
                WHERE id IN (' . implode(',', array_map('intval', $ok_ids)) . ')');
        }
        if ($invalid_ids) {
            $db->exec('DELETE FROM fcm_tokens WHERE id IN (' . implode(',', array_map('intval', $invalid_ids)) . ')');
        }
        if ($error_ids) {
            // Échec passager : nouvelle tentative dans ~24 h, sans compter l'étape
            $retry = max(0, (int) $cfg['gap_days'] - 1);
            $db->exec("UPDATE fcm_tokens SET last_reengage_at = NOW() - INTERVAL $retry DAY
                WHERE id IN (" . implode(',', array_map('intval', $error_ids)) . ')');
        }

        // Journal pour les comptes connectés : les autres relances en tiennent compte
        if ($ok_ids && is_file(__DIR__ . '/../models/model_notification_log.php')) {
            require_once __DIR__ . '/../models/model_notification_log.php';
        }
        if ($ok_ids && function_exists('notification_log_insert')) {
            $logged = [];
            foreach ($ok_ids as $id) {
                $m = $msg_by_id[$id];
                if ($m['user_id'] <= 0 || isset($logged[$m['user_id']])) {
                    continue;
                }
                $logged[$m['user_id']] = true;
                notification_log_insert([
                    'type' => 'reengagement_app',
                    'dedupe_key' => 'reengagement_app:' . $m['user_id'] . ':' . $m['step'] . ':' . date('Y-m-d'),
                    'user_id' => $m['user_id'],
                    'channel' => 'push',
                    'push_sent' => 1,
                ]);
            }
        }
        return $out;
    } catch (Throwable $e) {
        error_log('[reengagement_run] ' . $e->getMessage());
        return ['ok' => false] + $out;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
