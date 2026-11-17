<?php
/**
 * Contacts par boutique : colonne admin_id (NULL = carnet plateforme / RH).
 * Usage : php migrations/run_add_contacts_admin_id.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

if (!$db instanceof PDO) {
    echo "Erreur: connexion BDD indisponible.\n";
    exit(1);
}

try {
    $has = $db->query("SHOW COLUMNS FROM contacts LIKE 'admin_id'")->fetch(PDO::FETCH_ASSOC);
    if ($has) {
        echo "Colonne contacts.admin_id déjà présente.\n";
    } else {
        $db->exec(
            "ALTER TABLE contacts
             ADD COLUMN admin_id INT(11) NULL DEFAULT NULL AFTER id,
             ADD KEY idx_contacts_admin (admin_id)"
        );
        echo "Colonne contacts.admin_id ajoutée.\n";
    }
} catch (PDOException $e) {
    echo 'Erreur: ' . $e->getMessage() . "\n";
    exit(1);
}

echo "Migration contacts.admin_id terminée.\n";
