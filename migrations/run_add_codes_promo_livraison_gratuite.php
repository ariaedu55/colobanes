<?php
/**
 * Migration : type_remise livraison_gratuite + option boutique_livraison_gratuite
 * Executer : php migrations/run_add_codes_promo_livraison_gratuite.php
 */
require_once __DIR__ . '/../conn/conn.php';

global $db;

if (!$db) {
    echo "Erreur: connexion BDD indisponible.\n";
    exit(1);
}

try {
    $db->exec(
        "ALTER TABLE codes_promo
         MODIFY COLUMN type_remise ENUM('pourcentage','montant_fixe','livraison_gratuite') NOT NULL DEFAULT 'pourcentage'"
    );
    echo "codes_promo.type_remise : livraison_gratuite OK.\n";
} catch (PDOException $e) {
    echo "Note codes_promo: " . $e->getMessage() . "\n";
}

try {
    $col = $db->query("SHOW COLUMNS FROM admin LIKE 'boutique_livraison_gratuite'");
    if ($col && $col->fetch(PDO::FETCH_ASSOC)) {
        echo "admin.boutique_livraison_gratuite deja presente.\n";
    } else {
        $db->exec(
            "ALTER TABLE admin
             ADD COLUMN boutique_livraison_gratuite TINYINT(1) NOT NULL DEFAULT 0
             COMMENT 'Livraison offerte sur toute la boutique'"
        );
        echo "admin.boutique_livraison_gratuite ajoutee.\n";
    }
} catch (PDOException $e) {
    echo "Note admin: " . $e->getMessage() . "\n";
}

echo "Migration livraison gratuite terminee.\n";
