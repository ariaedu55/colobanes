<?php
/**
 * Colonne snooze popup certification vendeur (table vendeur_onboarding).
 */

require_once __DIR__ . '/schema_vendeur_onboarding.php';

function vendeur_cert_promo_snooze_column_exists(): bool
{
    global $db;
    if (!$db || !vendeur_onboarding_table_exists()) {
        return false;
    }
    try {
        $st = $db->query("SHOW COLUMNS FROM vendeur_onboarding LIKE 'cert_promo_snooze_until'");
        return $st && (bool) $st->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

function vendeur_cert_promo_ensure_schema(): bool
{
    static $ok = null;
    if ($ok === true) {
        return true;
    }
    if (vendeur_cert_promo_snooze_column_exists()) {
        $ok = true;
        return true;
    }
    if (!vendeur_onboarding_ensure_schema()) {
        $ok = false;
        return false;
    }
    global $db;
    if (!$db) {
        $ok = false;
        return false;
    }
    $sqlFile = dirname(__DIR__) . '/migrations/migrate_vendeur_cert_promo_snooze.sql';
    if (!is_readable($sqlFile)) {
        $ok = false;
        return false;
    }
    try {
        $db->exec((string) file_get_contents($sqlFile));
        $ok = vendeur_cert_promo_snooze_column_exists();
        return $ok;
    } catch (PDOException $e) {
        if (vendeur_cert_promo_snooze_column_exists()) {
            $ok = true;
            return true;
        }
        error_log('[vendeur_cert_promo_ensure_schema] ' . $e->getMessage());
        $ok = false;
        return false;
    }
}
