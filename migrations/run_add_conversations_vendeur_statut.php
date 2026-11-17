<?php
/**
 * Migration: statut conversation (active / achat)
 * Executer: php migrations/run_add_conversations_vendeur_statut.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

if (!$db) {
    echo "Erreur: connexion BDD indisponible.\n";
    exit(1);
}

$check = $db->query("SHOW TABLES LIKE 'conversations_vendeur'");
if (!$check || !$check->fetchColumn()) {
    echo "Table conversations_vendeur absente. Executez run_add_messages_vendeur.php d'abord.\n";
    exit(1);
}

try {
    $col = $db->query("SHOW COLUMNS FROM conversations_vendeur LIKE 'statut'");
    if ($col && $col->fetch(PDO::FETCH_ASSOC)) {
        echo "Colonne statut deja presente.\n";
    } else {
        $db->exec(
            "ALTER TABLE conversations_vendeur
             ADD COLUMN statut ENUM('active','achat') NOT NULL DEFAULT 'active' AFTER sujet"
        );
        $db->exec(
            "ALTER TABLE conversations_vendeur
             ADD KEY idx_user_statut (user_id, statut, dernier_message_at)"
        );
        echo "Colonne statut ajoutee.\n";
    }
} catch (PDOException $e) {
    echo "Erreur ALTER: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Migration conversations_vendeur statut terminee.\n";
