<?php
/**
 * Tâches automatiques de COLObanes — aucune intervention manuelle, aucune crontab obligatoire.
 *
 * Déclenchement :
 *   - automatiquement pendant les visites (taches_auto_enregistrer(), appelé par includes/session_user.php) :
 *     les tâches tournent APRÈS l'envoi de la page (fastcgi_finish_request), le visiteur n'attend pas ;
 *   - ou par une crontab si elle existe : php scripts/cron_taches_auto.php
 * Un verrou empêche deux exécutions en même temps ; chaque tâche a sa fréquence.
 * Le résultat de chaque tâche est enregistré dans storage/taches_auto/etat.json.
 */

if (!defined('TACHES_AUTO_DIR')) {
    define('TACHES_AUTO_DIR', dirname(__DIR__) . '/storage/taches_auto');
}
/** Une vérification au plus toutes les 60 s (fichier repère) : coût quasi nul par visite. */
if (!defined('TACHES_AUTO_TICK_SECONDES')) {
    define('TACHES_AUTO_TICK_SECONDES', 60);
}

/**
 * Liste des tâches : fréquence en secondes (0 = une seule fois) et ce qu'elles font.
 *
 * @return array<string, array{frequence:int, libelle:string, run:callable}>
 */
function taches_auto_liste(): array
{
    $root = dirname(__DIR__);
    return [
        'notifications' => [
            'frequence' => 900,
            'libelle' => 'Notifications, relances, e-mails et relances « reviens sur l’app »',
            'run' => static function () use ($root) {
                require_once $root . '/services/notifications_cron_bundle.php';
                $r = notifications_cron_bundle_run(true);
                return !empty($r['ok']) ? 'ok' : ('échec : ' . ($r['reason'] ?? 'inconnu'));
            },
        ],
        'push_campagnes' => [
            'frequence' => 900,
            'libelle' => 'Campagnes push : nouveautés des boutiques, dons, annonces et conseils vendeurs',
            'run' => static function () use ($root) {
                require_once $root . '/services/push_campagnes.php';
                $r = push_campagnes_run();
                if (empty($r['ok'])) {
                    return 'échec : ' . ($r['skipped'] ?? 'voir le journal PHP');
                }
                return isset($r['skipped']) ? 'en attente (' . $r['skipped'] . ')'
                    : $r['envoyes'] . ' envoyée(s), ' . $r['erreurs'] . ' erreur(s), ' . $r['invalides'] . ' appareil(s) retiré(s)';
            },
        ],
        'images_legeres' => [
            'frequence' => 900,
            'libelle' => 'Images légères : versions 400 / 800 px et WebP de toutes les photos',
            'run' => static function () use ($root) {
                require_once $root . '/services/images_auto.php';
                $r = images_auto_run(20.0);
                return $r['message'];
            },
        ],
        'produits_doublons' => [
            'frequence' => 86400,
            'libelle' => 'Masquer les produits en double',
            'run' => static function () use ($root) {
                require_once $root . '/includes/produit_doublons.php';
                return produits_doublons_masquer() . ' doublon(s) masqué(s)';
            },
        ],
        'schema_cache' => [
            'frequence' => 86400,
            'libelle' => 'Vérifier les tables et le cache du schéma',
            'run' => static function () use ($root) {
                require_once $root . '/includes/schema_client_comportement.php';
                require_once $root . '/includes/schema_notifications.php';
                require_once $root . '/includes/schema_vendeur_onboarding.php';
                require_once $root . '/includes/schema_vendeur_certification.php';
                require_once $root . '/includes/produit_vente_gros.php';
                client_comportement_ensure_tables();
                notifications_ensure_tables();
                vendeur_onboarding_ensure_schema();
                vendeur_certification_ensure_schema();
                vente_gros_ensure_schema();
                return 'ok';
            },
        ],
        'categories_webp' => [
            'frequence' => 86400,
            'libelle' => 'Images de catégories en WebP',
            'run' => static function () use ($root) {
                global $db;
                require_once $root . '/includes/image_optimizer.php';
                require_once $root . '/includes/image_optimizer_db.php';
                $details = [];
                $n = image_db_sync_table_column($db, 'categories', 'image', $details)
                    + image_db_sync_table_column($db, 'categories_generales', 'image', $details);
                return $n . ' image(s) mise(s) à jour';
            },
        ],
        'firebase_cles' => [
            'frequence' => 86400,
            'libelle' => 'Clés publiques Google (vérification des connexions Google/Apple)',
            'run' => static function () use ($root) {
                return taches_auto_script_cli($root . '/scripts/sync_firebase_google_keys_cache.php');
            },
        ],
        'firebase_sw' => [
            'frequence' => 3600,
            'libelle' => 'Service worker des notifications (après changement de configuration)',
            'run' => static function () use ($root) {
                $cfg = $root . '/config/firebase_config.php';
                $sw = $root . '/firebase-messaging-sw.js';
                if (!is_file($cfg) || (is_file($sw) && filemtime($sw) >= filemtime($cfg))) {
                    return 'à jour';
                }
                return taches_auto_script_cli($root . '/scripts/sync_firebase_sw.php');
            },
        ],
        'migration_references_boutique' => [
            'frequence' => 0,
            'libelle' => 'Migration : références produits avec le code de 3 lettres de chaque boutique',
            'run' => static function () use ($root) {
                require_once $root . '/models/model_produits.php';
                $r = produits_migrer_references();
                return $r['converties'] . ' référence(s) convertie(s), ' . $r['inchangees'] . ' déjà à jour';
            },
        ],
        'migration_categories_nom' => [
            'frequence' => 0,
            'libelle' => 'Migration : plusieurs sous-sous-catégories peuvent porter le même nom',
            'run' => static function () {
                global $db;
                $rows = $db->query("SHOW INDEX FROM `categories` WHERE Non_unique = 0 AND Column_name = 'nom'")->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $faits = [];
                foreach ($rows as $row) {
                    $key = (string) ($row['Key_name'] ?? '');
                    if ($key !== '' && $key !== 'PRIMARY' && !isset($faits[$key])) {
                        $db->exec('ALTER TABLE `categories` DROP INDEX `' . str_replace('`', '', $key) . '`');
                        $faits[$key] = true;
                    }
                }
                if ($faits) {
                    $exists = $db->query("SHOW INDEX FROM `categories` WHERE Key_name = 'idx_categories_nom'")->fetch();
                    if (!$exists) {
                        $db->exec('ALTER TABLE `categories` ADD INDEX `idx_categories_nom` (`nom`)');
                    }
                }
                return $faits ? 'index unique supprimé' : 'déjà fait';
            },
        ],
    ];
}

/** Fichier d'état propre à la base de données (un état copié d'un autre ordinateur est ignoré). */
function taches_auto_etat_fichier(): string
{
    require_once dirname(__DIR__) . '/includes/repere_base.php';
    return TACHES_AUTO_DIR . '/etat-' . repere_base_id() . '.json';
}

function taches_auto_etat_lire(): array
{
    $f = taches_auto_etat_fichier();
    $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
    return is_array($d) ? $d : [];
}

function taches_auto_etat_ecrire(array $etat): void
{
    @file_put_contents(taches_auto_etat_fichier(), json_encode($etat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/** Tâches dont c'est le moment. */
function taches_auto_dues(array $etat, int $now): array
{
    $dues = [];
    foreach (taches_auto_liste() as $id => $t) {
        $last = (int) ($etat[$id]['dernier'] ?? 0);
        $ok = ($etat[$id]['ok'] ?? false) === true;
        if ($t['frequence'] === 0) {
            if (!$ok) {
                $dues[] = $id;
            }
        } elseif ($now - $last >= $t['frequence']) {
            $dues[] = $id;
        }
    }
    return $dues;
}

/**
 * Exécute les tâches dues. Ne lève jamais d'exception : une tâche en erreur n'empêche pas les autres.
 *
 * @return array<string, string> résultat par tâche exécutée
 */
function taches_auto_executer(bool $forcer = false): array
{
    if (!is_dir(TACHES_AUTO_DIR) && !@mkdir(TACHES_AUTO_DIR, 0775, true)) {
        return [];
    }
    $lock = @fopen(TACHES_AUTO_DIR . '/verrou.lock', 'c+');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        return [];
    }
    $out = [];
    try {
        global $db;
        if (!$db instanceof PDO) {
            require_once dirname(__DIR__) . '/conn/conn.php';
        }
        @set_time_limit(300);
        $now = time();
        $etat = taches_auto_etat_lire();
        $liste = taches_auto_liste();
        $ids = $forcer ? array_keys($liste) : taches_auto_dues($etat, $now);
        foreach ($ids as $id) {
            $debut = microtime(true);
            try {
                $res = (string) call_user_func($liste[$id]['run']);
                $ok = strpos($res, 'échec') !== 0;
            } catch (Throwable $e) {
                $res = 'erreur : ' . $e->getMessage();
                $ok = false;
                error_log('[taches_auto] ' . $id . ' : ' . $e->getMessage());
            }
            $etat[$id] = [
                'dernier' => $now,
                'ok' => $ok,
                'resultat' => mb_substr($res, 0, 300),
                'duree_ms' => (int) round((microtime(true) - $debut) * 1000),
                'date' => date('c', $now),
            ];
            /* Une tâche en échec est retentée au prochain passage (≈ 15 min), pas le lendemain */
            if (!$ok && $liste[$id]['frequence'] > 900) {
                $etat[$id]['dernier'] = $now - $liste[$id]['frequence'] + 900;
            }
            $out[$id] = $res;
        }
        taches_auto_etat_ecrire($etat);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    return $out;
}

/** Binaire PHP en ligne de commande (pas php-fpm), ou '' si introuvable / exec désactivé. */
function taches_auto_php_cli(): string
{
    if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
        return '';
    }
    $candidats = [PHP_BINDIR . '/php', '/usr/local/bin/php', '/usr/bin/php'];
    if (PHP_SAPI === 'cli' && PHP_BINARY !== '') {
        array_unshift($candidats, PHP_BINARY);
    }
    foreach ($candidats as $bin) {
        if ($bin !== '' && is_file($bin) && is_executable($bin) && !preg_match('/(fpm|cgi)/i', basename($bin))) {
            return $bin;
        }
    }
    return '';
}

/** Lance un script CLI existant (processus séparé) et renvoie un résumé. */
function taches_auto_script_cli(string $script): string
{
    $bin = taches_auto_php_cli();
    if ($bin === '') {
        return 'ignoré : PHP en ligne de commande indisponible sur ce serveur';
    }
    $sortie = [];
    $code = 0;
    @exec(escapeshellarg($bin) . ' ' . escapeshellarg($script) . ' 2>&1', $sortie, $code);
    $resume = trim((string) end($sortie));
    return $code === 0 ? ($resume !== '' ? $resume : 'ok') : 'échec (code ' . $code . ') : ' . $resume;
}

/**
 * À appeler une fois par requête web : vérifie (au plus toutes les 60 s) s'il y a des tâches à faire,
 * et les exécute après l'envoi de la page au visiteur.
 */
function taches_auto_enregistrer(): void
{
    static $fait = false;
    if ($fait || PHP_SAPI === 'cli') {
        return;
    }
    $fait = true;
    $repere = TACHES_AUTO_DIR . '/dernier_tick';
    if (is_file($repere) && time() - (int) @filemtime($repere) < TACHES_AUTO_TICK_SECONDES) {
        return;
    }
    register_shutdown_function(static function () use ($repere) {
        if (!is_dir(TACHES_AUTO_DIR)) {
            @mkdir(TACHES_AUTO_DIR, 0775, true);
        }
        @touch($repere);
        if (!taches_auto_dues(taches_auto_etat_lire(), time())) {
            return;
        }
        /* La réponse part au visiteur d'abord ; le travail continue en arrière-plan */
        if (function_exists('fastcgi_finish_request')) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            fastcgi_finish_request();
        } else {
            /* Apache mod_php (poste local) : processus séparé si possible, sinon on laisse la main */
            $bin = taches_auto_php_cli();
            if ($bin !== '') {
                @exec(escapeshellarg($bin) . ' ' . escapeshellarg(dirname(__DIR__) . '/scripts/cron_taches_auto.php') . ' > /dev/null 2>&1 &');
                return;
            }
        }
        ignore_user_abort(true);
        taches_auto_executer(false);
    });
}

/**
 * Demande l'exécution d'une tâche tout de suite : elle tourne à la fin de la requête en cours,
 * après l'envoi de la page (le visiteur n'attend pas). Ex. : allègement des photos après un ajout de produit.
 */
function taches_auto_demander(string $id): void
{
    static $demandees = [];
    if (isset($demandees[$id]) || !isset(taches_auto_liste()[$id])) {
        return;
    }
    $demandees[$id] = true;
    if (!is_dir(TACHES_AUTO_DIR)) {
        @mkdir(TACHES_AUTO_DIR, 0775, true);
    }
    /* Rendue « due » : si cette requête ne peut pas la faire, la prochaine visite s'en charge */
    $etat = taches_auto_etat_lire();
    $etat[$id]['dernier'] = 0;
    taches_auto_etat_ecrire($etat);
    @unlink(TACHES_AUTO_DIR . '/dernier_tick');

    if (PHP_SAPI === 'cli') {
        return;
    }
    register_shutdown_function(static function () {
        if (function_exists('fastcgi_finish_request')) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            fastcgi_finish_request();
        }
        ignore_user_abort(true);
        taches_auto_executer(false);
    });
}
