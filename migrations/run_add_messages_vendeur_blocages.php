<?php
/**
 * Migration : blocages vendeur par client (messagerie)
 * Executer : php migrations/run_add_messages_vendeur_blocages.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

if (!$db) {
    echo "Erreur: connexion BDD indisponible.\n";
    exit(1);
}

try {
    $db->exec(
        "CREATE TABLE IF NOT EXISTS messages_vendeur_blocages (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            admin_id INT(11) NOT NULL,
            date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_msg_blocage_user_admin (user_id, admin_id),
            KEY idx_msg_blocage_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Table messages_vendeur_blocages OK.\n";
} catch (PDOException $e) {
    echo "Erreur CREATE: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    $fk1 = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages_vendeur_blocages'
        AND CONSTRAINT_NAME = 'fk_msg_blocage_user' LIMIT 1");
    if (!$fk1 || !$fk1->fetchColumn()) {
        $db->exec(
            "ALTER TABLE messages_vendeur_blocages
             ADD CONSTRAINT fk_msg_blocage_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE"
        );
    }
    $fk2 = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages_vendeur_blocages'
        AND CONSTRAINT_NAME = 'fk_msg_blocage_admin' LIMIT 1");
    if (!$fk2 || !$fk2->fetchColumn()) {
        $db->exec(
            "ALTER TABLE messages_vendeur_blocages
             ADD CONSTRAINT fk_msg_blocage_admin FOREIGN KEY (admin_id) REFERENCES admin (id) ON DELETE CASCADE"
        );
    }
    echo "Contraintes FK OK.\n";
} catch (PDOException $e) {
    echo "Note FK: " . $e->getMessage() . "\n";
}

echo "Migration messages_vendeur_blocages terminee.\n";
