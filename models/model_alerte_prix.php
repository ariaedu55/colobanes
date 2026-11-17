<?php
/**
 * Alertes prix — prix de référence choisi par le client.
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/schema_alerte_prix.php';
require_once __DIR__ . '/../includes/produit_disponibilite.php';

function alerte_prix_user_has(int $user_id, int $produit_id): bool
{
    $user_id = (int) $user_id;
    $produit_id = (int) $produit_id;
    if ($user_id <= 0 || $produit_id <= 0 || !alerte_prix_ensure_table()) {
        return false;
    }
    global $db;
    try {
        $st = $db->prepare('
            SELECT 1 FROM produit_alerte_prix
            WHERE user_id = :u AND produit_id = :p AND actif = 1 LIMIT 1
        ');
        $st->execute(['u' => $user_id, 'p' => $produit_id]);
        return (bool) $st->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return array{success: bool, message?: string, actif?: bool, prix_reference?: float}
 */
function alerte_prix_activer(int $user_id, int $produit_id, array $produit): array
{
    $user_id = (int) $user_id;
    $produit_id = (int) $produit_id;
    if ($user_id <= 0 || $produit_id <= 0) {
        return ['success' => false, 'message' => 'Connexion requise.'];
    }
    if (!alerte_prix_ensure_table()) {
        return ['success' => false, 'message' => 'Service temporairement indisponible.'];
    }
    $prix_ref = produit_prix_effectif_client($produit);
    if ($prix_ref <= 0) {
        return ['success' => false, 'message' => 'Prix invalide pour cette alerte.'];
    }
    global $db;
    try {
        $st = $db->prepare('
            INSERT INTO produit_alerte_prix (user_id, produit_id, prix_reference, actif, date_creation)
            VALUES (:u, :p, :pr, 1, NOW())
            ON DUPLICATE KEY UPDATE prix_reference = VALUES(prix_reference), actif = 1, date_maj = NOW()
        ');
        $st->execute(['u' => $user_id, 'p' => $produit_id, 'pr' => $prix_ref]);
        return ['success' => true, 'actif' => true, 'prix_reference' => $prix_ref];
    } catch (PDOException $e) {
        error_log('[alerte_prix_activer] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Impossible d’enregistrer l’alerte.'];
    }
}

/**
 * @return array{success: bool, message?: string, actif?: bool}
 */
function alerte_prix_desactiver(int $user_id, int $produit_id): array
{
    $user_id = (int) $user_id;
    $produit_id = (int) $produit_id;
    if ($user_id <= 0 || $produit_id <= 0 || !alerte_prix_ensure_table()) {
        return ['success' => false, 'message' => 'Données invalides.'];
    }
    global $db;
    try {
        $st = $db->prepare('
            UPDATE produit_alerte_prix SET actif = 0, date_maj = NOW()
            WHERE user_id = :u AND produit_id = :p
        ');
        $st->execute(['u' => $user_id, 'p' => $produit_id]);
        return ['success' => true, 'actif' => false];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Impossible de retirer l’alerte.'];
    }
}

/**
 * @return array{prix_reference: float}|null
 */
function alerte_prix_get_row(int $user_id, int $produit_id): ?array
{
    $user_id = (int) $user_id;
    $produit_id = (int) $produit_id;
    if ($user_id <= 0 || $produit_id <= 0 || !alerte_prix_ensure_table()) {
        return null;
    }
    global $db;
    try {
        $st = $db->prepare('
            SELECT prix_reference FROM produit_alerte_prix
            WHERE user_id = :u AND produit_id = :p AND actif = 1 LIMIT 1
        ');
        $st->execute(['u' => $user_id, 'p' => $produit_id]);
        $pr = $st->fetchColumn();
        if ($pr === false) {
            return null;
        }
        return ['prix_reference' => (float) $pr];
    } catch (PDOException $e) {
        return null;
    }
}

function favoris_snapshot_enregistrer_prix_ref(int $user_id, int $produit_id, array $produit): void
{
    $user_id = (int) $user_id;
    $produit_id = (int) $produit_id;
    if ($user_id <= 0 || $produit_id <= 0) {
        return;
    }
    require_once __DIR__ . '/../includes/schema_notifications.php';
    if (!notifications_ensure_tables()) {
        return;
    }
    $prix = produit_prix_effectif_client($produit);
    $statut = (string) ($produit['statut'] ?? '');
    global $db;
    try {
        $db->prepare('
            INSERT INTO favoris_produit_snapshot (user_id, produit_id, dernier_prix_ref, dernier_statut)
            VALUES (:u, :p, :pr, :st)
            ON DUPLICATE KEY UPDATE dernier_prix_ref = VALUES(dernier_prix_ref), dernier_statut = VALUES(dernier_statut)
        ')->execute(['u' => $user_id, 'p' => $produit_id, 'pr' => $prix, 'st' => $statut]);
    } catch (PDOException $e) {
        error_log('[favoris_snapshot_enregistrer_prix_ref] ' . $e->getMessage());
    }
}
