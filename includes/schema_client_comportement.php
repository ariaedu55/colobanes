<?php
/**
 * Schéma BDD suivi comportement — création automatique (CREATE IF NOT EXISTS).
 * Appelé depuis conn.php ; aucune commande CLI requise.
 */

if (!function_exists('client_comportement_table_exists')) {
    function client_comportement_table_exists(string $table): bool
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

if (!function_exists('client_comportement_ensure_tables')) {
    function client_comportement_ensure_tables(): bool
    {
        static $ok = null;
        if ($ok === true) {
            return true;
        }
        require_once __DIR__ . '/schema_runtime_cache.php';
        if (schema_runtime_module_ready('client_comportement')) {
            $ok = true;
            return true;
        }
        if (client_comportement_table_exists('client_evenements')
            && client_comportement_table_exists('client_interets_rayon')) {
            schema_runtime_mark_module_ready('client_comportement');
            $ok = true;
            return true;
        }
        $sqlFile = dirname(__DIR__) . '/migrations/migrate_client_comportement.sql';
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
            if (!client_comportement_table_exists('client_evenements')) {
                throw new PDOException('client_evenements introuvable après migration');
            }
            schema_runtime_mark_module_ready('client_comportement');
            $ok = true;
            return true;
        } catch (PDOException $e) {
            error_log('[client_comportement_ensure_tables] ' . $e->getMessage());
            $ok = false;
            return false;
        }
    }
}
