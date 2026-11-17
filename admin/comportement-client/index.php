<?php
/**
 * Statistiques agrégées — comportement visiteurs / clients (marketplace).
 */
require_once __DIR__ . '/../includes/require_admin_session.php';
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_route_access.php';
require_once __DIR__ . '/../../models/model_client_comportement.php';

$admin_role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
if ($admin_role === 'vendeur') {
    header('Location: ' . (function_exists('app_url') ? app_url('/admin/dashboard.php') : '/admin/dashboard.php'));
    exit;
}

$stats = client_comportement_admin_stats_30d();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comportement clients — Administration COLObanes</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .cc-page { max-width: 1100px; margin: 0 auto; padding: 24px 20px 48px; }
        .cc-page h1 { font-size: 1.5rem; margin: 0 0 8px; color: var(--titres, #0D0D0D); }
        .cc-page .cc-sub { color: var(--gris-moyen, #737373); margin-bottom: 24px; font-size: 0.95rem; }
        .cc-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .cc-kpi { background: #fff; border-radius: 12px; padding: 18px 20px; box-shadow: 0 4px 20px rgba(53, 100, 166, 0.1); border: 1px solid rgba(53, 100, 166, 0.12); }
        .cc-kpi strong { display: block; font-size: 1.75rem; color: #3564a6; }
        .cc-kpi span { font-size: 0.85rem; color: #737373; }
        .cc-card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(53, 100, 166, 0.08); }
        .cc-card h2 { font-size: 1.1rem; margin: 0 0 14px; }
        .cc-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .cc-table th, .cc-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
        .cc-table th { color: #737373; font-weight: 600; }
        .cc-empty { color: #737373; font-size: 0.9rem; }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="contents-container">
        <div class="cc-page">
            <h1><i class="fas fa-chart-line" aria-hidden="true"></i> Comportement clients</h1>
            <p class="cc-sub">Agrégats des 30 derniers jours (tous visiteurs du site, connectés ou non).</p>

            <div class="cc-kpis">
                <div class="cc-kpi">
                    <strong><?php echo number_format((int) ($stats['total_events'] ?? 0), 0, ',', ' '); ?></strong>
                    <span>Événements enregistrés</span>
                </div>
                <div class="cc-kpi">
                    <strong><?php echo number_format((int) ($stats['users_actifs'] ?? 0), 0, ',', ' '); ?></strong>
                    <span>Clients connectés actifs</span>
                </div>
                <div class="cc-kpi">
                    <strong><?php echo number_format((int) ($stats['visiteurs_anonymes'] ?? 0), 0, ',', ' '); ?></strong>
                    <span>Visiteurs anonymes (jour passé)</span>
                </div>
            </div>

            <div class="cc-card">
                <h2>Top rayons (scores cumulés)</h2>
                <?php if (empty($stats['top_rayons'])): ?>
                    <p class="cc-empty">Pas encore de données suffisantes.</p>
                <?php else: ?>
                    <table class="cc-table">
                        <thead>
                            <tr>
                                <th>Rayon</th>
                                <th>Score agrégé</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['top_rayons'] as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) ($row['nom'] ?? 'Rayon #' . (int) ($row['categorie_generale_id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo number_format((float) ($row['score_total'] ?? 0), 1, ',', ' '); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="cc-card">
                <h2>Top recherches</h2>
                <?php if (empty($stats['top_recherches'])): ?>
                    <p class="cc-empty">Aucune recherche enregistrée sur la période.</p>
                <?php else: ?>
                    <table class="cc-table">
                        <thead>
                            <tr>
                                <th>Terme</th>
                                <th>Occurrences</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['top_recherches'] as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) ($row['t'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int) ($row['c'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>
