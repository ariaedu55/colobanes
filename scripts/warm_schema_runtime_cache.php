<?php
/**
 * Prépare le cache schéma (à lancer après déploiement ou migration).
 * Usage : php scripts/warm_schema_runtime_cache.php
 */
$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/includes/schema_client_comportement.php';
require_once $root . '/includes/schema_notifications.php';
require_once $root . '/includes/schema_vendeur_onboarding.php';
require_once $root . '/includes/schema_vendeur_certification.php';

client_comportement_ensure_tables();
notifications_ensure_tables();
vendeur_onboarding_ensure_schema();
vendeur_certification_ensure_schema();

echo "Cache schéma prêt : " . $root . "/config/cache/schema_modules.json\n";
