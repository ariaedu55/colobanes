<?php
/**
 * Administration du module Dons : annonces, signalements, catégories, paramètres.
 * Réservé aux rôles admin / plateforme (le rôle vendeur est refusé par admin_route_access).
 * Contenu partagé avec le super admin : includes/dons_admin_panel.php
 */
require_once __DIR__ . '/../includes/require_admin_session.php';
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/dons_admin_panel.php';

$dons_ctx = [
    'self' => '/admin/dons/index.php',
    'csrf_field' => admin_csrf_field(),
    'actor_admin' => (int) $_SESSION['admin_id'],
];

/* CSRF vérifié par require_admin_session */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    dons_admin_panel_handle_post($dons_ctx);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dons - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dons.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include '../includes/nav.php'; ?>
    <div class="content-header">
        <h1><i class="fas fa-hand-holding-heart"></i> Dons</h1>
        <div class="header-actions">
            <a href="<?php echo htmlspecialchars(app_url('/dons/'), ENT_QUOTES, 'UTF-8'); ?>" class="btn-primary" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Voir l’espace public</a>
        </div>
    </div>
    <?php dons_admin_panel_render($dons_ctx); ?>
    <?php include '../includes/footer.php'; ?>
