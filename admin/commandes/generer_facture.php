<?php
/**
 * Génère une facture pour une commande
 * Possible uniquement à partir du statut « livraison en cours ».
 */
require_once __DIR__ . '/../includes/require_admin_session.php';
require_once __DIR__ . '/../includes/require_access.php';

/* Création de document : POST + jeton CSRF admin uniquement (plus de GET depuis un lien) */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_csrf_valid($_POST['csrf_token'] ?? '')) {
    header('Location: index.php');
    exit;
}

$commande_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($commande_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../models/model_factures.php';
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/../../includes/commande_suivi_ui.php';
require_once __DIR__ . '/../../includes/admin_route_access.php';

$commande = get_commande_by_id($commande_id);
if (!$commande) {
    header('Location: index.php');
    exit;
}

$vf_ck = admin_vendeur_filter_id();
if ($vf_ck !== null && (int) ($commande['vendeur_id'] ?? 0) !== $vf_ck) {
    header('Location: index.php');
    exit;
}

$statut_raw = commande_statut_ui_normalize((string) ($commande['statut'] ?? ''));
$peut_facture = in_array($statut_raw, ['livraison_en_cours', 'expediee', 'livree', 'paye'], true);
if (!$peut_facture) {
    $_SESSION['success_message'] = 'La facture ne peut être générée qu’à partir du statut « En livraison ».';
    header('Location: details.php?id=' . $commande_id);
    exit;
}

$existant = get_facture_by_commande($commande_id);
if ($existant) {
    header('Location: facture.php?id=' . $existant['id']);
    exit;
}

$result = create_facture($commande_id);
if ($result && $result['success']) {
    $_SESSION['success_message'] = 'Facture #' . $result['numero_facture'] . ' générée avec succès.';
    header('Location: facture.php?id=' . $result['facture_id']);
    exit;
}

$_SESSION['success_message'] = 'Erreur lors de la génération de la facture.';
header('Location: details.php?id=' . $commande_id);
