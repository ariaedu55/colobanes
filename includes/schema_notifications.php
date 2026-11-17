<?php
/**
 * Schéma BDD notifications — création automatique (CREATE IF NOT EXISTS).
 */

if (!function_exists('notifications_table_exists')) {
    function notifications_table_exists(string $table): bool
    {
        global $db;
        if (!$db) {
            return false;
        }
        try {
            $q = $db->prepare('
                SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
            ');
            $q->execute(['t' => $table]);
            return (int) $q->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('notifications_ensure_tables')) {
    function notifications_ensure_tables(): bool
    {
        static $ok = null;
        if ($ok === true) {
            return true;
        }
        require_once __DIR__ . '/schema_runtime_cache.php';
        if (schema_runtime_module_ready('notifications')) {
            $ok = true;
            return true;
        }
        if (notifications_table_exists('notification_log')
            && notifications_table_exists('favoris_produit_snapshot')
            && notifications_table_exists('panier_relance')) {
            schema_runtime_mark_module_ready('notifications');
            $ok = true;
            return true;
        }
        $sqlFile = dirname(__DIR__) . '/migrations/migrate_notifications.sql';
        if (!is_readable($sqlFile)) {
            $ok = false;
            return false;
        }
        global $db;
        if (!$db) {
            $ok = false;
            return false;
        }
        $sql = (string) file_get_contents($sqlFile);
        $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
        try {
            foreach ($statements as $stmt) {
                $lines = preg_split('/\r\n|\r|\n/', $stmt);
                $lines = array_values(array_filter($lines, static function ($line) {
                    return trim($line) !== '' && !str_starts_with(ltrim($line), '--');
                }));
                $stmt = trim(implode("\n", $lines));
                if ($stmt === '') {
                    continue;
                }
                $db->exec($stmt);
            }
            if (!notifications_table_exists('notification_log')) {
                throw new PDOException('notification_log introuvable après migration');
            }
            schema_runtime_mark_module_ready('notifications');
            $ok = true;
            return true;
        } catch (PDOException $e) {
            error_log('[notifications_ensure_tables] ' . $e->getMessage());
            $ok = false;
            return false;
        }
    }
}
