<?php
/**
 * Migration : colonne image_reference sur commandes_personnalisees
 * CLI uniquement : php run_migration_image_cp.php
 */
require_once __DIR__ . '/includes/require_cli_only.php';
require_once __DIR__ . '/conn/conn.php';

$done = false;
$message = '';
$error = '';

if (isset($db) && $db instanceof PDO) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM commandes_personnalisees LIKE 'image_reference'");
        if ($stmt && $stmt->rowCount() > 0) {
            $message = 'La colonne image_reference existe déjà.';
            $done = true;
        } else {
            $db->exec("ALTER TABLE commandes_personnalisees ADD COLUMN image_reference VARCHAR(255) NULL DEFAULT NULL AFTER description");
            $message = 'Colonne image_reference ajoutée avec succès.';
            $done = true;
        }
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
} else {
    $error = 'Connexion à la base de données indisponible.';
}

if ($error !== '') {
    fwrite(STDERR, "Erreur : {$error}\n");
    exit(1);
}

echo $message . "\n";
exit($done ? 0 : 1);
