<?php
/**
 * Optionnel — les tables sont créées automatiquement à la connexion BDD (conn.php).
 * Ce script ne fait que forcer la vérification en CLI (déploiement / diagnostic).
 *
 * Usage : php migrations/run_migrate_client_comportement.php
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/require_cli_only.php';

require_once dirname(__DIR__) . '/conn/conn.php';

if (client_comportement_ensure_tables()) {
    echo "OK — client_evenements / client_interets_rayon (auto ou créées).\n";
    exit(0);
}

echo "Échec — vérifiez les logs PHP et migrate_client_comportement.sql.\n";
exit(1);
