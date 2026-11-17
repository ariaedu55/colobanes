<?php
/**
 * Élargit tranche_age pour les clés chaussure_adulte, habille_bebe, etc.
 *
 * Usage : php migrations/run_migrate_categories_tranche_age_widen.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

$q = $db->prepare("
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'tranche_age'
");
$q->execute();
if ((int) $q->fetchColumn() === 0) {
    echo "Colonne tranche_age absente — exécutez run_migrate_categories_tranche_age.php.\n";
    exit(1);
}

$db->exec("
    ALTER TABLE `categories`
    MODIFY COLUMN `tranche_age` VARCHAR(32) NULL DEFAULT NULL
    COMMENT 'chaussure_adulte, habille_bebe, …'
");
$db->exec("
    UPDATE `categories`
    SET `tranche_age` = NULL
    WHERE `tranche_age` IN ('bebe', 'enfant', 'adulte')
");
echo "OK — tranche_age élargie, anciennes valeurs bebe/enfant/adulte réinitialisées.\n";
