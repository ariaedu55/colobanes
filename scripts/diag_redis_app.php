<?php
/**
 * Diagnostic Redis applicatif (CLI uniquement).
 * Usage: php scripts/diag_redis_app.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/redis_client.php';
require_once dirname(__DIR__) . '/includes/redis_cache.php';
require_once dirname(__DIR__) . '/includes/session_redis_handler.php';

$cfg = redis_config_load();
echo "config_loaded=" . ($cfg !== [] ? 'yes' : 'no') . "\n";
echo "host=" . ($cfg['host'] ?? '') . " db=" . ($cfg['database'] ?? '') . " prefix=" . ($cfg['prefix'] ?? '') . "\n";
echo "ping=" . (redis_ping_ok() ? 'ok' : 'fail') . "\n";
echo "cache_enabled=" . (redis_cache_enabled() ? 'yes' : 'no') . "\n";
echo "sessions_enabled_flag=" . (!empty($cfg['sessions_enabled']) ? 'yes' : 'no') . "\n";
echo "sessions_ready=" . (php_session_redis_enabled() ? 'yes' : 'no') . "\n";

$probe = 'diag:' . date('YmdHis');
$ok_set = redis_cache_set($probe, ['t' => time()], 60);
$got = redis_cache_get($probe);
echo "cache_roundtrip=" . ($ok_set && is_array($got) ? 'ok' : 'fail') . "\n";

$client = redis_client_get();
if ($client instanceof \Predis\Client) {
    $keys = $client->keys('cache:*');
    echo "cache_keys=" . (is_array($keys) ? count($keys) : 0) . "\n";
    $sess = $client->keys('sess:*');
    echo "sess_keys=" . (is_array($sess) ? count($sess) : 0) . "\n";
}
echo "DONE\n";
