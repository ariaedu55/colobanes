<?php
/**
 * Sous-sous-catégories : tranche d’âge (bébé, enfant, adulte).
 *
 * Usage : php migrations/run_migrate_categories_tranche_age.php
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
if ((int) $q->fetchColumn() > 0) {
    echo "Colonne categories.tranche_age déjà présente.\n";
    exit(0);
}

$db->exec("
    ALTER TABLE `categories`
    ADD COLUMN `tranche_age` VARCHAR(16) NULL DEFAULT NULL
    COMMENT 'bebe, enfant, adulte'
    AFTER `description`
");
echo "OK — categories.tranche_age ajoutée.\n";
