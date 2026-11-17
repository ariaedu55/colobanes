<?php
/**
 * Charge les clés API de l'assistant depuis un fichier HORS de la racine web.
 * Aucune clé n'est lue depuis config/ (dossier éventuellement exposé en cas de mauvaise config Apache).
 *
 * Fichier attendu : assistant_keys.php retournant un tableau.
 * Emplacements essayés (le premier lisible gagne) :
 *   1) Variable d'environnement COLOBANES_ASSISTANT_KEYS (chemin absolu)
 *   2) /home/colobanes/secrets/assistant_keys.php          (production)
 *   3) {parent de htdocs}/secrets/assistant_keys.php       (XAMPP local)
 *   4) Variables d'environnement GEMINI_API_KEY, MISTRAL_API_KEY, OPENROUTER_API_KEY, CLOUDFLARE_*
 * Les clés saisies dans Super Admin → Assistant IA (chiffrées en base) passent avant :
 * aucune intervention sur le serveur n'est nécessaire pour changer de clé.
 */

/** Noms des clés gérées. */
function assistant_keys_names(): array
{
    return ['gemini_api_key', 'gemini_project_id', 'mistral_api_key', 'openrouter_api_key', 'cloudflare_account_id', 'cloudflare_api_token',
        'turnstile_site_key', 'turnstile_secret_key'];
}

/** Clé de chiffrement locale (créée automatiquement dans storage/, dossier interdit au web). */
function assistant_keys_cipher_key(): string
{
    $dir = dirname(__DIR__, 2) . '/storage/secrets';
    $file = $dir . '/assistant_cle';
    if (is_readable($file)) {
        $k = (string) file_get_contents($file);
        if (strlen($k) === 32) {
            return $k;
        }
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $k = random_bytes(32);
    if (@file_put_contents($file, $k, LOCK_EX) !== 32) {
        return '';
    }
    @chmod($file, 0600);
    return $k;
}

function assistant_keys_db_ensure(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $db->exec("CREATE TABLE IF NOT EXISTS `assistant_ai_cles` (
        `nom` VARCHAR(40) NOT NULL PRIMARY KEY,
        `valeur` TEXT NOT NULL,
        `maj` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Clés enregistrées depuis le Super Admin (déchiffrées). */
function assistant_keys_from_db(): array
{
    global $db;
    if (!$db instanceof PDO || !function_exists('openssl_decrypt')) {
        return [];
    }
    try {
        assistant_keys_db_ensure($db);
        $rows = $db->query('SELECT nom, valeur FROM assistant_ai_cles')->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    } catch (PDOException $e) {
        return [];
    }
    $key = $rows ? assistant_keys_cipher_key() : '';
    $out = [];
    foreach ($rows as $nom => $val) {
        $raw = base64_decode((string) $val, true);
        if ($key === '' || $raw === false || strlen($raw) < 29) {
            continue;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if (is_string($plain) && $plain !== '') {
            $out[(string) $nom] = $plain;
        }
    }
    return $out;
}

/** Enregistre (ou efface avec une valeur vide) une clé saisie dans le Super Admin. */
function assistant_keys_save(string $nom, string $valeur): bool
{
    global $db;
    if (!in_array($nom, assistant_keys_names(), true) || !$db instanceof PDO) {
        return false;
    }
    assistant_keys_db_ensure($db);
    $valeur = trim($valeur);
    if ($valeur === '') {
        $db->prepare('DELETE FROM assistant_ai_cles WHERE nom = ?')->execute([$nom]);
        return true;
    }
    $key = assistant_keys_cipher_key();
    if ($key === '') {
        return false;
    }
    $iv = random_bytes(12);
    $tag = '';
    $enc = openssl_encrypt($valeur, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($enc === false) {
        return false;
    }
    $db->prepare('INSERT INTO assistant_ai_cles (nom, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)')
        ->execute([$nom, base64_encode($iv . $tag . $enc)]);
    return true;
}

if (!function_exists('assistant_keys_load')) {
    /**
     * @return array{gemini_api_key:string,openrouter_api_key:string,cloudflare_account_id:string,cloudflare_api_token:string,source:string}
     */
    function assistant_keys_load(bool $recharger = false): array
    {
        static $keys = null;
        if ($keys !== null && !$recharger) {
            return $keys;
        }
        $keys = assistant_keys_load_fichier();
        foreach (assistant_keys_names() as $n) {
            $keys[$n] = (string) ($keys[$n] ?? '');
        }
        $keys['admin'] = [];
        foreach (assistant_keys_from_db() as $n => $v) {
            if (in_array($n, assistant_keys_names(), true)) {
                $keys[$n] = $v;
                $keys['admin'][$n] = true;
            }
        }
        return $keys;
    }

    /** Clés du fichier hors web / des variables d'environnement. */
    function assistant_keys_load_fichier(): array
    {
        $keys = null;

        $empty = [
            'gemini_api_key' => '',
            'gemini_project_id' => '',
            'mistral_api_key' => '',
            'openrouter_api_key' => '',
            'cloudflare_account_id' => '',
            'cloudflare_api_token' => '',
            'source' => 'none',
        ];

        $candidates = [];
        $env_path = trim((string) getenv('COLOBANES_ASSISTANT_KEYS'));
        if ($env_path !== '') {
            $candidates[] = $env_path;
        }
        $candidates[] = '/home/colobanes/secrets/assistant_keys.php';
        $doc = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($doc !== '') {
            $parent = dirname(rtrim($doc, '/'));
            if ($parent !== '' && $parent !== '/' && $parent !== '.') {
                $candidates[] = $parent . '/secrets/assistant_keys.php';
            }
        }
        /* includes/assistant → xamppfiles/secrets (4 niveaux) */
        $candidates[] = dirname(__DIR__, 3) . '/../secrets/assistant_keys.php';
        $candidates[] = dirname(__DIR__, 4) . '/secrets/assistant_keys.php';

        foreach ($candidates as $path) {
            $path = str_replace('\\', '/', (string) $path);
            if ($path === '' || !is_readable($path)) {
                continue;
            }
            /* Refus si le fichier est sous la racine web */
            $real = realpath($path);
            $web = $doc !== '' ? realpath($doc) : false;
            if ($real && $web && strpos($real, rtrim($web, '/') . '/') === 0) {
                error_log('[assistant] Fichier de clés ignoré (dans le web root) : ' . basename($real));
                continue;
            }
            $loaded = include $path;
            if (!is_array($loaded)) {
                continue;
            }
            $keys = [
                'gemini_api_key' => trim((string) ($loaded['gemini_api_key'] ?? '')),
                'gemini_project_id' => trim((string) ($loaded['gemini_project_id'] ?? '')),
                'mistral_api_key' => trim((string) ($loaded['mistral_api_key'] ?? '')),
                'openrouter_api_key' => trim((string) ($loaded['openrouter_api_key'] ?? '')),
                'cloudflare_account_id' => trim((string) ($loaded['cloudflare_account_id'] ?? '')),
                'cloudflare_api_token' => trim((string) ($loaded['cloudflare_api_token'] ?? '')),
                'source' => $real ?: $path,
            ];
            return $keys;
        }

        $from_env = [
            'gemini_api_key' => trim((string) getenv('GEMINI_API_KEY')),
            'mistral_api_key' => trim((string) getenv('MISTRAL_API_KEY')),
            'openrouter_api_key' => trim((string) getenv('OPENROUTER_API_KEY')),
            'cloudflare_account_id' => trim((string) getenv('CLOUDFLARE_ACCOUNT_ID')),
            'cloudflare_api_token' => trim((string) getenv('CLOUDFLARE_API_TOKEN')),
            'source' => 'env',
        ];
        if ($from_env['gemini_api_key'] !== ''
            || $from_env['openrouter_api_key'] !== ''
            || $from_env['mistral_api_key'] !== ''
            || $from_env['cloudflare_api_token'] !== '') {
            $keys = $from_env;
            return $keys;
        }

        $keys = $empty;
        return $keys;
    }
}

if (!function_exists('assistant_keys_public_status')) {
    /**
     * État des clés pour l'admin — jamais la valeur, seulement configuré / manquant.
     *
     * @return array<string, array{configured:bool,hint:string}>
     */
    function assistant_keys_public_status(): array
    {
        $k = assistant_keys_load();
        $src = (string) ($k['source'] ?? 'none');
        $src_label = $src === 'none' ? 'aucun fichier' : ($src === 'env' ? 'variables d\'environnement' : 'fichier hors web');
        return [
            'gemini' => [
                'configured' => $k['gemini_api_key'] !== '',
                'hint' => $src_label,
            ],
            'mistral' => [
                'configured' => $k['mistral_api_key'] !== '',
                'hint' => $src_label,
            ],
            'openrouter' => [
                'configured' => $k['openrouter_api_key'] !== '',
                'hint' => $src_label,
            ],
            'cloudflare' => [
                'configured' => $k['cloudflare_api_token'] !== '' && $k['cloudflare_account_id'] !== '',
                'hint' => $src_label,
            ],
        ];
    }
}
