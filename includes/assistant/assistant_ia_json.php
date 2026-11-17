<?php
/**
 * Petite question à l'IA qui répond en JSON (recherche de produits, produits complémentaires).
 *
 * Mêmes IA, mêmes clés et même pause automatique que l'assistante (Gemini, puis Mistral, OpenRouter, Cloudflare).
 * Chaque réponse est gardée en cache : une même question ne coûte qu'un appel.
 */

require_once __DIR__ . '/assistant_engine.php';
require_once __DIR__ . '/assistant_ai.php';

function assistant_ia_json_cache_file(string $cle): string
{
    return dirname(__DIR__, 2) . '/storage/assistant_ia/cache/' . preg_replace('/[^a-z0-9_]/', '', strtolower($cle)) . '.json';
}

/**
 * Réponse en cache (null si absente, expirée ou si le dernier essai a échoué).
 *
 * @return array{trouve: bool, data: ?array}
 */
function assistant_ia_json_cache_lire(string $cle): array
{
    $f = assistant_ia_json_cache_file($cle);
    if (!is_readable($f)) {
        return ['trouve' => false, 'data' => null];
    }
    $c = json_decode((string) @file_get_contents($f), true);
    if (!is_array($c) || (int) ($c['expire'] ?? 0) < time()) {
        return ['trouve' => false, 'data' => null];
    }
    return ['trouve' => true, 'data' => is_array($c['data'] ?? null) ? $c['data'] : null];
}

function assistant_ia_json_cache_ecrire(string $cle, ?array $data, int $ttl): void
{
    $f = assistant_ia_json_cache_file($cle);
    if (!is_dir(dirname($f))) {
        @mkdir(dirname($f), 0775, true);
    }
    @file_put_contents($f, json_encode(['expire' => time() + $ttl, 'data' => $data], JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/** Objet JSON contenu dans une réponse (avec ou sans bloc ```json). */
function assistant_ia_json_decoder(string $texte): ?array
{
    $texte = trim(preg_replace('/^```(?:json)?|```$/mi', '', trim($texte)));
    $debut = strpos($texte, '{');
    $fin = strrpos($texte, '}');
    if ($debut === false || $fin === false || $fin <= $debut) {
        return null;
    }
    $data = json_decode(substr($texte, $debut, $fin - $debut + 1), true);
    return is_array($data) ? $data : null;
}

/**
 * Un appel à une IA ; renvoie le texte brut de la réponse.
 *
 * @return array{res: ?string, http_code?: int, error_class?: string, tokens_in?: int, tokens_out?: int}
 */
function assistant_ia_json_appel(string $provider, string $consigne, string $demande): array
{
    $keys = assistant_keys_load();

    if ($provider === 'gemini') {
        return assistant_ai_essayer_modeles(
            assistant_ai_modeles('gemini', ['gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-flash-lite-latest']),
            static function (string $model) use ($keys, $consigne, $demande): array {
                $http = assistant_ai_http(
                    'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent',
                    ['Content-Type: application/json', 'x-goog-api-key: ' . $keys['gemini_api_key']],
                    [
                        'system_instruction' => ['parts' => [['text' => $consigne]]],
                        'contents' => [['role' => 'user', 'parts' => [['text' => $demande]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.2, 'maxOutputTokens' => 500],
                    ],
                    8
                );
                if (!$http['ok']) {
                    return ['res' => null, 'http_code' => $http['code'], 'error_class' => assistant_ai_error_class($http)];
                }
                $texte = '';
                foreach ((array) ($http['json']['candidates'][0]['content']['parts'] ?? []) as $part) {
                    $texte .= (string) ($part['text'] ?? '');
                }
                return [
                    'res' => $texte !== '' ? $texte : null,
                    'http_code' => $http['code'],
                    'error_class' => 'empty',
                    'tokens_in' => (int) ($http['json']['usageMetadata']['promptTokenCount'] ?? 0),
                    'tokens_out' => (int) ($http['json']['usageMetadata']['candidatesTokenCount'] ?? 0),
                ];
            }
        );
    }

    if ($provider === 'mistral' || $provider === 'openrouter') {
        $endpoint = $provider === 'mistral'
            ? 'https://api.mistral.ai/v1/chat/completions'
            : 'https://openrouter.ai/api/v1/chat/completions';
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $keys[$provider . '_api_key']];
        if ($provider === 'openrouter') {
            $headers[] = 'HTTP-Referer: https://colobanes.com';
            $headers[] = 'X-Title: COLObanes';
        }
        $defauts = $provider === 'mistral'
            ? ['ministral-14b-latest', 'voxtral-small-latest', 'ministral-8b-latest', 'codestral-latest', 'ministral-3b-latest']
            : ['nvidia/nemotron-3.5-lightning:free', 'google/gemma-4-31b-it:free'];
        return assistant_ai_essayer_modeles(
            assistant_ai_modeles($provider, $defauts),
            static function (string $model) use ($endpoint, $headers, $provider, $consigne, $demande): array {
                $payload = [
                    'model' => $model,
                    'messages' => [['role' => 'system', 'content' => $consigne], ['role' => 'user', 'content' => $demande]],
                    'temperature' => 0.2,
                    'max_tokens' => 500,
                ];
                if ($provider === 'mistral') {
                    $payload['response_format'] = ['type' => 'json_object'];
                }
                $http = assistant_ai_http($endpoint, $headers, $payload, 8);
                if (!$http['ok']) {
                    return ['res' => null, 'http_code' => $http['code'], 'error_class' => assistant_ai_error_class($http)];
                }
                $texte = assistant_ai_content_text($http['json']['choices'][0]['message']['content'] ?? '');
                return [
                    'res' => $texte !== '' ? $texte : null,
                    'http_code' => $http['code'],
                    'error_class' => 'empty',
                    'tokens_in' => (int) ($http['json']['usage']['prompt_tokens'] ?? 0),
                    'tokens_out' => (int) ($http['json']['usage']['completion_tokens'] ?? 0),
                ];
            }
        );
    }

    /* Cloudflare Workers AI */
    $model = assistant_ai_modeles('cloudflare', ['@cf/meta/llama-3.3-70b-instruct-fp8-fast'])[0];
    $http = assistant_ai_http(
        'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($keys['cloudflare_account_id']) . '/ai/run/' . $model,
        ['Content-Type: application/json', 'Authorization: Bearer ' . $keys['cloudflare_api_token']],
        ['messages' => [['role' => 'system', 'content' => $consigne], ['role' => 'user', 'content' => $demande]]],
        8
    );
    if (!$http['ok']) {
        return ['res' => null, 'http_code' => $http['code'], 'error_class' => assistant_ai_error_class($http)];
    }
    $r = $http['json']['result']['response'] ?? '';
    $texte = is_array($r) ? (string) json_encode($r, JSON_UNESCAPED_UNICODE) : (string) $r;
    return ['res' => $texte !== '' ? $texte : null, 'http_code' => $http['code'], 'error_class' => 'empty'];
}

/**
 * Pose une question à l'IA et renvoie son JSON (ou null). Réponse gardée $ttl secondes ;
 * un échec est gardé 1 h pour ne pas réessayer à chaque visite.
 *
 * @param bool $reseau false = cache seulement (pour ne jamais ralentir l'affichage d'une page)
 */
function assistant_ia_json(string $consigne, string $demande, string $cle, int $ttl = 2592000, float $budget = 8.0, bool $reseau = true): ?array
{
    /* config/assistant.php : 'ia_suggestions' => false coupe l'IA ici (le site garde ses règles sans IA) */
    $cfg = assistant_config();
    if (empty($cfg['enabled']) || (isset($cfg['ia_suggestions']) && $cfg['ia_suggestions'] === false)) {
        return null;
    }
    $cache = assistant_ia_json_cache_lire($cle);
    if ($cache['trouve'] || !$reseau || !function_exists('curl_init')) {
        return $cache['data'];
    }

    $ready = array_values(array_filter(assistant_ai_provider_order(), 'assistant_ai_provider_ready'));
    $actives = array_values(array_filter($ready, static fn ($p) => !assistant_ai_paused($p)));
    $debut = microtime(true);
    $data = null;
    $failover_from = null;

    foreach ($actives as $provider) {
        $reste = $budget - (microtime(true) - $debut);
        if ($reste < 2.5) {
            break;
        }
        $GLOBALS['assistant_ai_deadline'] = microtime(true) + $reste;
        $t0 = microtime(true);
        try {
            $pack = assistant_ia_json_appel($provider, $consigne, $demande);
        } catch (Throwable $e) {
            $pack = ['res' => null, 'error_class' => 'http'];
        }
        $data = is_string($pack['res'] ?? null) ? assistant_ia_json_decoder($pack['res']) : null;
        $meta = [
            'http_code' => (int) ($pack['http_code'] ?? 0),
            'tokens_in' => (int) ($pack['tokens_in'] ?? 0),
            'tokens_out' => (int) ($pack['tokens_out'] ?? 0),
            'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
            'failover_from' => $failover_from,
        ];
        if ($data !== null) {
            assistant_ai_log($provider, 'ok', $meta);
            assistant_ai_unpause($provider);
            break;
        }
        $classe = (string) ($pack['error_class'] ?? 'parse');
        assistant_ai_log($provider, 'error', $meta + ['error_class' => $classe]);
        assistant_ai_pause($provider, $classe);
        $failover_from = $provider;
    }
    unset($GLOBALS['assistant_ai_deadline']);

    assistant_ia_json_cache_ecrire($cle, $data, $data !== null ? $ttl : 3600);
    return $data;
}

/**
 * Liste de courts termes de recherche fournie par l'IA, nettoyée (lettres, chiffres, espaces, tirets).
 *
 * @return list<string>
 */
function assistant_ia_termes($liste, int $max = 6): array
{
    $out = [];
    foreach ((array) $liste as $t) {
        if (!is_string($t)) {
            continue;
        }
        $t = trim(preg_replace('/\s+/u', ' ', preg_replace("/[^\p{L}\p{N}\s'-]/u", ' ', $t)));
        $n = assistant_norm($t);
        if (mb_strlen($n, 'UTF-8') < 2 || mb_strlen($n, 'UTF-8') > 30 || isset($out[$n])) {
            continue;
        }
        $out[$n] = $t;
        if (count($out) >= $max) {
            break;
        }
    }
    return array_values($out);
}

/** Le nom contient-il ce terme comme mot entier (pluriel accepté) ? « casque » ne trouve pas « casquette ». */
function assistant_ia_nom_contient(string $nom, string $terme): bool
{
    $nom = assistant_norm($nom);
    $mots = array_filter(explode(' ', assistant_norm($terme)), static fn ($w) => mb_strlen($w, 'UTF-8') >= 2);
    if (!$mots) {
        return false;
    }
    foreach ($mots as $w) {
        $w = preg_replace('/(s|x)$/u', '', $w);
        if (!preg_match('/(?<![\p{L}\p{N}])' . preg_quote($w, '/') . '(s|x|es)?(?![\p{L}\p{N}])/u', $nom)) {
            return false;
        }
    }
    return true;
}
