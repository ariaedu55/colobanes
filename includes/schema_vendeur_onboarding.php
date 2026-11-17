<?php
/**
 * Schéma onboarding vendeur (statut admin + table vendeur_onboarding).
 */

require_once __DIR__ . '/schema_notifications.php';

function vendeur_onboarding_table_exists(): bool
{
    return notifications_table_exists('vendeur_onboarding');
}

function admin_statut_enum_has_en_attente_produit(): bool
{
    global $db;
    if (!$db) {
        return false;
    }
    try {
        $st = $db->query("SHOW COLUMNS FROM admin LIKE 'statut'");
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
        if (!$row || empty($row['Type'])) {
            return false;
        }
        return stripos((string) $row['Type'], 'en_attente_produit') !== false;
    } catch (PDOException $e) {
        return false;
    }
}

function vendeur_onboarding_ensure_schema(): bool
{
    static $ok = null;
    if ($ok === true) {
        return true;
    }
    require_once __DIR__ . '/schema_runtime_cache.php';
    if (schema_runtime_module_ready('vendeur_onboarding')) {
        $ok = true;
        return true;
    }
    if (vendeur_onboarding_table_exists() && admin_statut_enum_has_en_attente_produit()) {
        schema_runtime_mark_module_ready('vendeur_onboarding');
        $ok = true;
        return true;
    }
    global $db;
    if (!$db) {
        $ok = false;
        return false;
    }
    $sqlFile = dirname(__DIR__) . '/migrations/migrate_vendeur_onboarding.sql';
    if (!is_readable($sqlFile)) {
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
        if (!vendeur_onboarding_table_exists() || !admin_statut_enum_has_en_attente_produit()) {
            throw new PDOException('Schéma vendeur_onboarding incomplet après migration');
        }
        schema_runtime_mark_module_ready('vendeur_onboarding');
        $ok = true;
        return true;
    } catch (PDOException $e) {
        error_log('[vendeur_onboarding_ensure_schema] ' . $e->getMessage());
        $ok = false;
        return false;
    }
}
