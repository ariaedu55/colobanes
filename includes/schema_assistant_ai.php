<?php
/**
 * Schéma de suivi de consommation de l'assistant multi-IA.
 */

if (!function_exists('assistant_ai_ensure_tables')) {
    function assistant_ai_ensure_tables(): bool
    {
        static $ok = null;
        if ($ok === true) {
            return true;
        }
        require_once __DIR__ . '/schema_runtime_cache.php';
        if (schema_runtime_module_ready('assistant_ai')) {
            $ok = true;
            return true;
        }
        global $db;
        if (!$db instanceof PDO) {
            $ok = false;
            return false;
        }
        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS assistant_ai_usage (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    provider VARCHAR(32) NOT NULL,
                    status VARCHAR(24) NOT NULL,
                    http_code SMALLINT NULL,
                    tokens_in INT UNSIGNED NOT NULL DEFAULT 0,
                    tokens_out INT UNSIGNED NOT NULL DEFAULT 0,
                    latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
                    error_class VARCHAR(64) NULL,
                    failover_from VARCHAR(32) NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_ai_created (created_at),
                    KEY idx_ai_provider_created (provider, created_at),
                    KEY idx_ai_status_created (status, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            schema_runtime_mark_module_ready('assistant_ai');
            $ok = true;
            return true;
        } catch (PDOException $e) {
            error_log('[assistant] schéma usage : ' . $e->getMessage());
            $ok = false;
            return false;
        }
    }
}
