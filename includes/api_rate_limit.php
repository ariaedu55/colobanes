<?php
/**
 * Limitation simple des requêtes API (par IP + endpoint)
 */

if (!function_exists('api_rate_limit_client_key')) {
    function api_rate_limit_client_key(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));

        return $ip !== '' ? $ip : '0.0.0.0';
    }
}

if (!function_exists('api_rate_limit_check')) {
    /**
     * @param string $endpoint Identifiant logique (ex. search_suggestions)
     * @param int $max_per_window Nombre max de requêtes
     * @param int $window_seconds Fenêtre glissante en secondes
     */
    function api_rate_limit_check(string $endpoint, int $max_per_window = 60, int $window_seconds = 60): bool
    {
        if ($max_per_window <= 0 || $window_seconds <= 0) {
            return true;
        }

        $dir = sys_get_temp_dir() . '/colobanes_api_rl';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        $file = $dir . '/' . hash('sha256', $endpoint . '|' . api_rate_limit_client_key()) . '.json';
        $now = time();
        $hits = [];

        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $decoded = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                foreach ($decoded as $ts) {
                    $ts = (int) $ts;
                    if ($ts > $now - $window_seconds) {
                        $hits[] = $ts;
                    }
                }
            }
        }

        if (count($hits) >= $max_per_window) {
            return false;
        }

        $hits[] = $now;
        @file_put_contents($file, json_encode($hits), LOCK_EX);

        return true;
    }
}

if (!function_exists('api_rate_limit_deny_json')) {
    function api_rate_limit_deny_json(): void
    {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Trop de requêtes. Réessayez dans un instant.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
