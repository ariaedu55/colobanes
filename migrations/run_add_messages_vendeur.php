<?php
/**
 * Migration: messagerie client ↔ vendeur
 * Executer: php migrations/run_add_messages_vendeur.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

if (!$db) {
    echo "Erreur: connexion BDD indisponible.\n";
    exit(1);
}

$create_conversations = "CREATE TABLE IF NOT EXISTS conversations_vendeur (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    admin_id INT(11) NOT NULL,
    produit_id INT(11) NULL DEFAULT NULL,
    sujet VARCHAR(255) NOT NULL DEFAULT '',
    dernier_message_at DATETIME NULL DEFAULT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_conv_user_admin_produit (user_id, admin_id, produit_id),
    KEY idx_user_dernier (user_id, dernier_message_at),
    KEY idx_admin_dernier (admin_id, dernier_message_at),
    KEY idx_produit (produit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$create_messages = "CREATE TABLE IF NOT EXISTS messages_vendeur (
    id INT(11) NOT NULL AUTO_INCREMENT,
    conversation_id INT(11) NOT NULL,
    expediteur_type ENUM('client','vendeur') NOT NULL,
    expediteur_id INT(11) NOT NULL,
    contenu TEXT NOT NULL,
    type_message ENUM('texte','offre_prix','systeme') NOT NULL DEFAULT 'texte',
    meta_json TEXT NULL,
    lu_par_client TINYINT(1) NOT NULL DEFAULT 0,
    lu_par_vendeur TINYINT(1) NOT NULL DEFAULT 0,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conv_date (conversation_id, date_creation),
    KEY idx_conv_lu_client (conversation_id, lu_par_client, expediteur_type),
    KEY idx_conv_lu_vendeur (conversation_id, lu_par_vendeur, expediteur_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$fks = [
    "ALTER TABLE conversations_vendeur ADD CONSTRAINT fk_conv_vendeur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE",
    "ALTER TABLE conversations_vendeur ADD CONSTRAINT fk_conv_vendeur_admin FOREIGN KEY (admin_id) REFERENCES admin (id) ON DELETE CASCADE",
    "ALTER TABLE conversations_vendeur ADD CONSTRAINT fk_conv_vendeur_produit FOREIGN KEY (produit_id) REFERENCES produits (id) ON DELETE SET NULL",
    "ALTER TABLE messages_vendeur ADD CONSTRAINT fk_msg_vendeur_conv FOREIGN KEY (conversation_id) REFERENCES conversations_vendeur (id) ON DELETE CASCADE",
];

try {
    $db->exec($create_conversations);
    echo "Table conversations_vendeur creee ou deja presente.\n";
} catch (PDOException $e) {
    echo "Erreur CREATE conversations_vendeur: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    $db->exec($create_messages);
    echo "Table messages_vendeur creee ou deja presente.\n";
} catch (PDOException $e) {
    echo "Erreur CREATE messages_vendeur: " . $e->getMessage() . "\n";
    exit(1);
}

foreach ($fks as $fk_sql) {
    try {
        $db->exec($fk_sql);
        echo "FK ajoutee.\n";
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), 'Duplicate') !== false || stripos($e->getMessage(), 'already exists') !== false) {
            continue;
        }
        echo "Note FK: " . $e->getMessage() . "\n";
    }
}

$ok = $db->query("SHOW TABLES LIKE 'conversations_vendeur'")->fetchColumn()
    && $db->query("SHOW TABLES LIKE 'messages_vendeur'")->fetchColumn();

if ($ok) {
    echo "Verification OK: tables messagerie disponibles.\n";
} else {
    echo "Erreur: tables messagerie introuvables apres migration.\n";
    exit(1);
}

echo "Migration messages_vendeur terminee.\n";
