<?php
/**
 * Schéma alertes prix — création automatique.
 */

if (!function_exists('alerte_prix_table_exists')) {
    function alerte_prix_table_exists(): bool
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
            $q->execute(['t' => 'produit_alerte_prix']);
            return (int) $q->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('alerte_prix_ensure_table')) {
    function alerte_prix_ensure_table(): bool
    {
        static $ok = null;
        if ($ok === true) {
            return true;
        }
        if (alerte_prix_table_exists()) {
            $ok = true;
            return true;
        }
        $sqlFile = dirname(__DIR__) . '/migrations/migrate_alerte_prix.sql';
        if (!is_readable($sqlFile)) {
            return false;
        }
        global $db;
        if (!$db) {
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
            $ok = alerte_prix_table_exists();
            return $ok;
        } catch (PDOException $e) {
            error_log('[alerte_prix_ensure_table] ' . $e->getMessage());
            return false;
        }
    }
}
