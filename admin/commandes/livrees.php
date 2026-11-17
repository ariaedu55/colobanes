<?php
/**
 * Commandes livrées / payées — Admin & Vendeur
 */

require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/includes/commandes_v2_helpers.php';

$vf_cmd = admin_vendeur_filter_id();
$toutes_commandes = get_all_commandes(null, $vf_cmd);

$commandes_livrees_all = array_values(array_filter($toutes_commandes ?: [], function ($commande) {
    return in_array($commande['statut'] ?? '', ['livree', 'paye'], true);
}));

$vue = isset($_GET['vue']) ? (string) $_GET['vue'] : 'jour';
if (!in_array($vue, ['jour', 'toutes'], true)) {
    $vue = 'jour';
}

$commandes_livrees = $commandes_livrees_all;
if ($vue === 'jour') {
    $aujourd_hui = date('Y-m-d');
    $commandes_livrees = array_values(array_filter($commandes_livrees_all, function ($c) use ($aujourd_hui) {
        $date_ref = !empty($c['date_livraison']) ? $c['date_livraison'] : ($c['date_commande'] ?? '');
        if ($date_ref === '') {
            return false;
        }
        return date('Y-m-d', strtotime($date_ref)) === $aujourd_hui;
    }));
}

$commandes_livrees = cmd_v2_tri_commandes($commandes_livrees, 'date_desc');

$total_commandes = count_commandes_by_statut(null, $vf_cmd);
$nb_livrees = count_commandes_by_statut('livree', $vf_cmd) + count_commandes_by_statut('paye', $vf_cmd);
$montant_total_livrees = get_montant_total_commandes('livree', $vf_cmd) + get_montant_total_commandes('paye', $vf_cmd);
$montant_affiche = array_sum(array_map(function ($c) {
    return (float) ($c['montant_total'] ?? 0);
}, $commandes_livrees));

$nb_jour = count(array_filter($commandes_livrees_all, function ($c) {
    $date_ref = !empty($c['date_livraison']) ? $c['date_livraison'] : ($c['date_commande'] ?? '');
    return $date_ref !== '' && date('Y-m-d', strtotime($date_ref)) === date('Y-m-d');
}));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes livr&eacute;es &mdash; Administration COLObanes</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-commandes-v2.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="contents-container">
        <div class="cmd-v2-page">

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="cmd-v2-notif" role="status">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
                </div>
            <?php endif; ?>

            <?php
            $cmd_tabs_active = 'livrees';
            $cmd_tabs_vf = $vf_cmd;
            $cmd_tabs_summary = '<strong>' . number_format($montant_affiche, 0, ',', ' ') . ' FCFA</strong> '
                . ($vue === 'jour' ? 'de commandes livrées aujourd\'hui' : 'de commandes livrées au total');
            $cmd_tabs_filters = [
                ['Aujourd\'hui', '?vue=jour', $nb_jour, $vue === 'jour'],
                ['Toutes', '?vue=toutes', $nb_livrees, $vue === 'toutes'],
            ];
            require __DIR__ . '/includes/commandes_tabs.php';
            ?>

            <div class="cmd-v2-grid">
                <?php if (empty($commandes_livrees)): ?>
                    <div class="cmd-v2-empty">
                        <div class="cmd-v2-empty__icon"><i class="fas fa-box-open"></i></div>
                        <h3>Aucune commande livr&eacute;e</h3>
                        <p>
                            <?php if ($vue === 'jour'): ?>
                                Aucune livraison enregistr&eacute;e aujourd'hui. Consultez «&nbsp;Toutes les livr&eacute;es&nbsp;» ou l'historique des ventes.
                            <?php else: ?>
                                Aucune commande n'a encore &eacute;t&eacute; marqu&eacute;e comme livr&eacute;e ou pay&eacute;e.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($commandes_livrees as $commande): ?>
                        <?php cmd_v2_render_card($commande, ['show_date' => true, 'show_delivery' => true]); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>

</html>
