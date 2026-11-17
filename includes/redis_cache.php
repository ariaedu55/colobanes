<?php
/**
 * Cache applicatif Redis (get / set / delete).
 */

require_once __DIR__ . '/redis_client.php';

if (!function_exists('redis_cache_enabled')) {
    function redis_cache_enabled(): bool
    {
        $cfg = redis_config_load();

        return !empty($cfg['cache_enabled']) && redis_client_get() !== null;
    }
}

if (!function_exists('redis_cache_ttl')) {
    function redis_cache_ttl(): int
    {
        $cfg = redis_config_load();
        $ttl = (int) ($cfg['cache_ttl'] ?? 120);

        return $ttl > 0 ? $ttl : 120;
    }
}

if (!function_exists('redis_cache_get')) {
    /**
     * @return mixed|null
     */
    function redis_cache_get(string $key)
    {
        if (!redis_cache_enabled() || $key === '') {
            return null;
        }
        $client = redis_client_get();
        if ($client === null) {
            return null;
        }
        try {
            $raw = $client->get('cache:' . $key);
            if ($raw === null || $raw === false || $raw === '') {
                return null;
            }
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded) || !array_key_exists('v', $decoded)) {
                return null;
            }

            return $decoded['v'];
        } catch (Throwable $e) {
            error_log('[redis_cache_get] ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('redis_cache_set')) {
    /**
     * @param mixed $value
     */
    function redis_cache_set(string $key, $value, ?int $ttl = null): bool
    {
        if (!redis_cache_enabled() || $key === '') {
            return false;
        }
        $client = redis_client_get();
        if ($client === null) {
            return false;
        }
        $ttl = $ttl !== null && $ttl > 0 ? $ttl : redis_cache_ttl();
        try {
            $payload = json_encode(['v' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return false;
            }
            $full = 'cache:' . $key;
            if ($client instanceof \Predis\Client) {
                $client->setex($full, $ttl, $payload);
                return true;
            }
            if ($client instanceof Redis) {
                return (bool) $client->setex($full, $ttl, $payload);
            }
        } catch (Throwable $e) {
            error_log('[redis_cache_set] ' . $e->getMessage());
        }

        return false;
    }
}

if (!function_exists('redis_cache_delete')) {
    function redis_cache_delete(string $key): bool
    {
        if (!redis_cache_enabled() || $key === '') {
            return false;
        }
        $client = redis_client_get();
        if ($client === null) {
            return false;
        }
        try {
            $client->del('cache:' . $key);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
