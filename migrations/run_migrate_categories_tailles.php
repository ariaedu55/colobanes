<?php
/**
 * Sous-catégories plateforme : tailles par défaut (JSON).
 *
 * Usage : php migrations/run_migrate_categories_tailles.php
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
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'tailles'
");
$q->execute();
if ((int) $q->fetchColumn() > 0) {
    echo "Colonne categories.tailles déjà présente.\n";
    exit(0);
}

$db->exec("
    ALTER TABLE `categories`
    ADD COLUMN `tailles` VARCHAR(512) NULL DEFAULT NULL
    COMMENT 'Tailles proposées JSON [{\"v\":\"S\",\"s\":0}]'
    AFTER `description`
");
echo "OK — categories.tailles ajoutée.\n";
