<?php
/**
 * Certification vendeurs (Standard / VIP / Premium).
 *
 * Usage : php migrations/run_migrate_vendeur_certification.php
 * (Même logique que l’auto-migration web via vendeur_certification_ensure_schema.)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/includes/schema_vendeur_certification.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

echo "Migration certification vendeurs…\n";

$before = vendeur_certification_schema_is_complete();
if (vendeur_certification_ensure_schema()) {
    if ($before) {
        echo "  Schéma déjà à jour.\n";
    } else {
        echo "  Schéma mis à jour (table/colonnes certification).\n";
    }
    echo "Terminé.\n";
    exit(0);
}

echo "Échec — vérifiez les logs PHP et la connexion MySQL.\n";
exit(1);
