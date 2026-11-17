<?php
/**
 * Exécution partagée du bundle notifications (worker + file e-mail).
 */

if (!defined('NOTIFICATIONS_CRON_STORAGE_DIR')) {
    define('NOTIFICATIONS_CRON_STORAGE_DIR', dirname(__DIR__) . '/storage/notifications_cron');
}
if (!defined('NOTIFICATIONS_CRON_LAST_RUN_FILE')) {
    define('NOTIFICATIONS_CRON_LAST_RUN_FILE', NOTIFICATIONS_CRON_STORAGE_DIR . '/last_run.txt');
}
if (!defined('NOTIFICATIONS_CRON_LOCK_FILE')) {
    define('NOTIFICATIONS_CRON_LOCK_FILE', NOTIFICATIONS_CRON_STORAGE_DIR . '/bundle.lock');
}

function notifications_cron_ensure_storage(): bool
{
    if (is_dir(NOTIFICATIONS_CRON_STORAGE_DIR)) {
        return true;
    }
    return @mkdir(NOTIFICATIONS_CRON_STORAGE_DIR, 0755, true) || is_dir(NOTIFICATIONS_CRON_STORAGE_DIR);
}

function notifications_cron_interval_seconds(): int
{
    $cfg = notifications_config();
    $min = (int) ($cfg['notifications_cron_interval_minutes'] ?? 15);
    $min = max(5, min(120, $min));
    return $min * 60;
}

function notifications_cron_last_run_timestamp(): int
{
    if (!is_file(NOTIFICATIONS_CRON_LAST_RUN_FILE)) {
        return 0;
    }
    $raw = @file_get_contents(NOTIFICATIONS_CRON_LAST_RUN_FILE);
    if ($raw === false || $raw === '') {
        return 0;
    }
    return (int) trim($raw);
}

function notifications_cron_mark_ran(): void
{
    if (!notifications_cron_ensure_storage()) {
        return;
    }
    @file_put_contents(NOTIFICATIONS_CRON_LAST_RUN_FILE, (string) time(), LOCK_EX);
}

function notifications_cron_is_due(): bool
{
    $last = notifications_cron_last_run_timestamp();
    if ($last <= 0) {
        return true;
    }
    return (time() - $last) >= notifications_cron_interval_seconds();
}

/**
 * @return array{ok: bool, worker: array<string, int>, email_queue: array<string, int>, at: string, skipped?: bool, reason?: string}
 */
function notifications_cron_bundle_run(bool $force = false): array
{
    if (!function_exists('notifications_config')) {
        require_once __DIR__ . '/../models/model_notification_log.php';
    }

    if (!notifications_cron_ensure_storage()) {
        return [
            'ok' => false,
            'worker' => [],
            'email_queue' => [],
            'at' => date('c'),
            'reason' => 'storage_unavailable',
        ];
    }

    $lock_fp = @fopen(NOTIFICATIONS_CRON_LOCK_FILE, 'c+');
    if ($lock_fp === false) {
        return [
            'ok' => false,
            'worker' => [],
            'email_queue' => [],
            'at' => date('c'),
            'reason' => 'lock_open_failed',
        ];
    }

    if (!flock($lock_fp, LOCK_EX | LOCK_NB)) {
        fclose($lock_fp);
        return [
            'ok' => true,
            'skipped' => true,
            'worker' => [],
            'email_queue' => [],
            'at' => date('c'),
            'reason' => 'already_running',
        ];
    }

    if (!$force && !notifications_cron_is_due()) {
        flock($lock_fp, LOCK_UN);
        fclose($lock_fp);
        return [
            'ok' => true,
            'skipped' => true,
            'worker' => [],
            'email_queue' => [],
            'at' => date('c'),
            'reason' => 'not_due',
        ];
    }

    try {
        require_once __DIR__ . '/notification_worker.php';
        $stats_worker = notification_worker_run_all();

        require_once __DIR__ . '/email_queue.php';
        $limit = defined('EMAIL_QUEUE_BATCH_LIMIT') ? EMAIL_QUEUE_BATCH_LIMIT : 50;
        $stats_mail = email_queue_process($limit);

        // Relances « reviens sur l'app » (plages horaires et lots gérés par le service)
        require_once __DIR__ . '/reengagement_push.php';
        $stats_reengage = reengagement_run();

        notifications_cron_mark_ran();

        return [
            'ok' => true,
            'worker' => $stats_worker,
            'email_queue' => $stats_mail,
            'reengagement' => $stats_reengage,
            'at' => date('c'),
        ];
    } finally {
        flock($lock_fp, LOCK_UN);
        fclose($lock_fp);
    }
}

function notifications_cron_resolve_php_binary(): string
{
    if (defined('PHP_BINARY') && PHP_BINARY !== '' && is_file(PHP_BINARY)) {
        return PHP_BINARY;
    }
    $ini = trim((string) ini_get('php_binary'));
    if ($ini !== '' && is_file($ini)) {
        return $ini;
    }
    return 'php';
}

/**
 * Lance le bundle en processus séparé (non bloquant).
 */
function notifications_cron_spawn_bundle(): void
{
    if (!notifications_cron_is_due()) {
        return;
    }

    $script = realpath(dirname(__DIR__) . '/scripts/cron_notifications_bundle.php');
    if ($script === false || !is_readable($script)) {
        return;
    }

    $php_bin = notifications_cron_resolve_php_binary();
    $cmd = escapeshellarg($php_bin) . ' ' . escapeshellarg($script) . ' --spawned';

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @pclose(@popen('start /B "" ' . $cmd . ' > NUL 2>&1', 'r'));
        return;
    }

    @exec($cmd . ' > /dev/null 2>&1 &');
}

function notifications_cron_http_key_valid(): bool
{
    $cfg = notifications_config();
    $secret = trim((string) ($cfg['notifications_cron_secret'] ?? ''));
    if ($secret === '') {
        return false;
    }
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
    if ($key === '' && isset($_SERVER['HTTP_X_CRON_KEY'])) {
        $key = (string) $_SERVER['HTTP_X_CRON_KEY'];
    }
    return $key !== '' && hash_equals($secret, $key);
}
