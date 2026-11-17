<?php
/**
 * Super Admin — module Dons : annonces, signalements, catégories, paramètres.
 * Contenu partagé avec l'administration : includes/dons_admin_panel.php ; actions tracées dans le journal d'audit.
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/includes/dons_admin_panel.php';

$sa_id = (int) $_SESSION['super_admin_id'];
$dons_ctx = [
    'self' => '/super_admin/dons/index.php',
    'csrf_field' => super_admin_csrf_field(),
    'actor_admin' => 0,
    'on_action' => function (string $action, string $type, int $id, string $details) use ($sa_id) {
        super_admin_log_action($sa_id, $action, $type, $id > 0 ? $id : null, $details);
    },
];

/* CSRF vérifié par require_login (super_admin_csrf_enforce_post) */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    dons_admin_panel_handle_post($dons_ctx);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dons — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dons.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell">
        <header class="sa-users-hero">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow"><i class="fas fa-hand-holding-heart"></i> Espace solidaire</p>
                    <h1 class="sa-users-hero__title">Dons</h1>
                    <p class="sa-users-hero__lead">Validez ou masquez les annonces, traitez les signalements, gérez les catégories et les règles du module.</p>
                </div>
                <a href="<?php echo htmlspecialchars(app_url('/dons/'), ENT_QUOTES, 'UTF-8'); ?>" class="btn-primary" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Voir l’espace public</a>
            </div>
        </header>

        <?php dons_admin_panel_render($dons_ctx); ?>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
