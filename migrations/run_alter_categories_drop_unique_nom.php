<?php
/**
 * Supprime l'index UNIQUE global sur categories.nom pour permettre le même libellé
 * sous des catégories générales différentes (sous-catégories plateforme).
 * Usage : php migrations/run_alter_categories_drop_unique_nom.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

try {
    /* L'index peut s'appeler idx_nom ou nom selon l'import : on supprime tout index UNIQUE portant sur nom seul */
    $st = $db->query("SHOW INDEX FROM `categories` WHERE Non_unique = 0 AND Column_name = 'nom'");
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    $dropped = [];
    foreach ($rows as $row) {
        $key = (string) ($row['Key_name'] ?? '');
        if ($key === '' || $key === 'PRIMARY' || isset($dropped[$key])) {
            continue;
        }
        $db->exec('ALTER TABLE `categories` DROP INDEX `' . str_replace('`', '', $key) . '`');
        $dropped[$key] = true;
        echo "Index unique « {$key} » supprimé.\n";
    }
    if ($dropped === []) {
        echo "Aucun index unique sur categories.nom (déjà migré).\n";
    } else {
        /* Index simple conservé pour les recherches par nom */
        $db->exec('ALTER TABLE `categories` ADD INDEX `idx_categories_nom` (`nom`)');
    }
} catch (PDOException $e) {
    echo 'Erreur : ' . $e->getMessage() . "\n";
    exit(1);
}

echo "Terminé.\n";
