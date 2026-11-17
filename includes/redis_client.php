<?php
/**
 * Client Redis partagé (Predis, sinon extension phpredis).
 */

if (!function_exists('redis_config_load')) {
    /**
     * @return array<string, mixed>
     */
    function redis_config_load(): array
    {
        static $cfg = null;
        if ($cfg !== null) {
            return $cfg;
        }

        $path = __DIR__ . '/../config/redis.php';
        if (!is_file($path)) {
            $cfg = [];
            return $cfg;
        }

        $loaded = require $path;
        $cfg = is_array($loaded) ? $loaded : [];

        return $cfg;
    }
}

if (!function_exists('redis_is_configured')) {
    function redis_is_configured(): bool
    {
        $cfg = redis_config_load();

        return $cfg !== [] && !empty($cfg['host']);
    }
}

if (!function_exists('redis_client_get')) {
    /**
     * @return object|null Predis\Client|\Redis
     */
    function redis_client_get()
    {
        static $client = null;
        static $failed = false;

        if ($failed) {
            return null;
        }
        if ($client !== null) {
            return $client;
        }

        if (!redis_is_configured()) {
            $failed = true;
            return null;
        }

        $cfg = redis_config_load();
        $host = (string) ($cfg['host'] ?? '127.0.0.1');
        $port = (int) ($cfg['port'] ?? 6379);
        $database = (int) ($cfg['database'] ?? 0);
        $password = $cfg['password'] ?? null;
        $timeout = (float) ($cfg['timeout'] ?? 1.5);
        $rw_timeout = (float) ($cfg['read_write_timeout'] ?? 1.5);

        try {
            $autoload = __DIR__ . '/../vendor/autoload.php';
            if (is_file($autoload) && !class_exists('\Predis\Client', false)) {
                require_once $autoload;
            }

            if (class_exists('\Predis\Client')) {
                $params = [
                    'scheme' => 'tcp',
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'timeout' => $timeout,
                    'read_write_timeout' => $rw_timeout,
                ];
                if (is_string($password) && $password !== '') {
                    $params['password'] = $password;
                }
                $options = [];
                $prefix = trim((string) ($cfg['prefix'] ?? ''));
                if ($prefix !== '') {
                    $options['prefix'] = $prefix;
                }
                $predis = new \Predis\Client($params, $options);
                $predis->ping();
                $client = $predis;
                return $client;
            }

            if (class_exists('Redis')) {
                $redis = new Redis();
                if (!$redis->connect($host, $port, $timeout)) {
                    throw new RuntimeException('Redis connect failed');
                }
                if (is_string($password) && $password !== '') {
                    $redis->auth($password);
                }
                if ($database > 0) {
                    $redis->select($database);
                }
                $prefix = trim((string) ($cfg['prefix'] ?? ''));
                if ($prefix !== '' && method_exists($redis, 'setOption')) {
                    $redis->setOption(Redis::OPT_PREFIX, $prefix);
                }
                $redis->ping();
                $client = $redis;
                return $client;
            }
        } catch (Throwable $e) {
            error_log('[redis_client] ' . $e->getMessage());
            $failed = true;
            $client = null;
            return null;
        }

        $failed = true;
        return null;
    }
}

if (!function_exists('redis_ping_ok')) {
    function redis_ping_ok(): bool
    {
        $client = redis_client_get();
        if ($client === null) {
            return false;
        }
        try {
            if ($client instanceof \Predis\Client) {
                $pong = $client->ping();
                return $pong === 'PONG' || $pong === true || (is_object($pong) && method_exists($pong, '__toString') && (string) $pong === 'PONG');
            }
            if ($client instanceof Redis) {
                $pong = $client->ping();
                return $pong === true || $pong === '+PONG' || $pong === 'PONG';
            }
        } catch (Throwable $e) {
            return false;
        }

        return false;
    }
}
