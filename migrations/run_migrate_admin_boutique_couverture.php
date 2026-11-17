<?php
/**
 * Colonne admin.boutique_couverture (image de couverture de la vitrine).
 *
 * Usage : php migrations/run_migrate_admin_boutique_couverture.php
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
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin' AND COLUMN_NAME = 'boutique_couverture'
");
$q->execute();
if ((int) $q->fetchColumn() > 0) {
    echo "colonne admin.boutique_couverture déjà présente.\n";
    exit(0);
}

$after = 'boutique_logo';
$check = $db->prepare("
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin' AND COLUMN_NAME = :c
");
$check->execute(['c' => 'boutique_logo']);
if ((int) $check->fetchColumn() === 0) {
    $after = 'telephone';
}

$db->exec("
    ALTER TABLE `admin`
    ADD COLUMN `boutique_couverture` VARCHAR(255) NULL DEFAULT NULL
    COMMENT 'Image de couverture vitrine, chemin relatif sous upload/'
    AFTER `$after`
");
echo "colonne admin.boutique_couverture ajoutée.\n";
