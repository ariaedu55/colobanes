<?php
/**
 * Handler de sessions PHP via Redis (Predis / phpredis).
 * Préfixe : sessions:  (avec prefix config colobanes:)
 */

require_once __DIR__ . '/redis_client.php';

if (!function_exists('php_session_redis_enabled')) {
    function php_session_redis_enabled(): bool
    {
        $cfg = redis_config_load();

        return !empty($cfg['sessions_enabled']) && redis_client_get() !== null;
    }
}

if (!function_exists('php_session_redis_key')) {
    function php_session_redis_key(string $id): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9,-]/', '', $id);
        if (!is_string($clean) || $clean === '') {
            $clean = 'invalid';
        }

        return 'sess:' . $clean;
    }
}

if (!function_exists('php_session_redis_register')) {
    function php_session_redis_register(): bool
    {
        if (!php_session_redis_enabled()) {
            return false;
        }
        if (session_status() !== PHP_SESSION_NONE) {
            return false;
        }

        $handler = new class implements SessionHandlerInterface {
            public function open(string $path, string $name): bool
            {
                return redis_client_get() !== null;
            }

            public function close(): bool
            {
                return true;
            }

            public function read(string $id): string|false
            {
                $client = redis_client_get();
                if ($client === null) {
                    return '';
                }
                try {
                    $raw = $client->get(php_session_redis_key($id));
                    if ($raw === null || $raw === false) {
                        return '';
                    }

                    return (string) $raw;
                } catch (Throwable $e) {
                    error_log('[php_sessions_redis] read: ' . $e->getMessage());
                    return '';
                }
            }

            public function write(string $id, string $data): bool
            {
                $client = redis_client_get();
                if ($client === null) {
                    return false;
                }
                $lifetime = (int) ini_get('session.gc_maxlifetime');
                if ($lifetime < 60) {
                    $lifetime = 30 * 24 * 3600;
                }
                try {
                    $key = php_session_redis_key($id);
                    if ($client instanceof \Predis\Client) {
                        $client->setex($key, $lifetime, $data);
                        return true;
                    }
                    if ($client instanceof Redis) {
                        return (bool) $client->setex($key, $lifetime, $data);
                    }
                } catch (Throwable $e) {
                    error_log('[php_sessions_redis] write: ' . $e->getMessage());
                }

                return false;
            }

            public function destroy(string $id): bool
            {
                $client = redis_client_get();
                if ($client === null) {
                    return false;
                }
                try {
                    $client->del(php_session_redis_key($id));
                    return true;
                } catch (Throwable $e) {
                    return false;
                }
            }

            public function gc(int $max_lifetime): int|false
            {
                // TTL Redis gère l’expiration.
                return 0;
            }
        };

        return session_set_save_handler($handler, true);
    }
}
