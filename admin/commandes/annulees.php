<?php
/**
 * Commandes annulées — Admin & Vendeur
 */

require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/includes/commandes_v2_helpers.php';

$vf_cmd = admin_vendeur_filter_id();
$toutes_commandes = get_all_commandes(null, $vf_cmd);

$commandes_annulees_all = array_values(array_filter($toutes_commandes ?: [], function ($commande) {
    return ($commande['statut'] ?? '') === 'annulee';
}));

$vue = isset($_GET['vue']) ? (string) $_GET['vue'] : 'jour';
if (!in_array($vue, ['jour', 'toutes'], true)) {
    $vue = 'jour';
}

$commandes_annulees = $commandes_annulees_all;
if ($vue === 'jour') {
    $aujourd_hui = date('Y-m-d');
    $commandes_annulees = array_values(array_filter($commandes_annulees_all, function ($c) use ($aujourd_hui) {
        $date_ref = $c['date_annulation'] ?? '';
        if ($date_ref === '') {
            $date_ref = $c['date_commande'] ?? '';
        }
        if ($date_ref === '') {
            return false;
        }
        return date('Y-m-d', strtotime($date_ref)) === $aujourd_hui;
    }));
}

$commandes_annulees = cmd_v2_tri_commandes($commandes_annulees, 'date_desc');

$nb_annulees = count_commandes_by_statut('annulee', $vf_cmd);
$montant_affiche = array_sum(array_map(function ($c) {
    return (float) ($c['montant_total'] ?? 0);
}, $commandes_annulees));

$nb_jour = count(array_filter($commandes_annulees_all, function ($c) {
    $date_ref = $c['date_annulation'] ?? '';
    if ($date_ref === '') {
        $date_ref = $c['date_commande'] ?? '';
    }
    return $date_ref !== '' && date('Y-m-d', strtotime($date_ref)) === date('Y-m-d');
}));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes annul&eacute;es &mdash; Administration COLObanes</title>
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
            $cmd_tabs_active = 'annulees';
            $cmd_tabs_vf = $vf_cmd;
            $cmd_tabs_summary = '<strong>' . number_format($montant_affiche, 0, ',', ' ') . ' FCFA</strong> '
                . ($vue === 'jour' ? 'de commandes annulées aujourd\'hui' : 'de commandes annulées au total');
            $cmd_tabs_filters = [
                ['Aujourd\'hui', '?vue=jour', $nb_jour, $vue === 'jour'],
                ['Toutes', '?vue=toutes', $nb_annulees, $vue === 'toutes'],
            ];
            require __DIR__ . '/includes/commandes_tabs.php';
            ?>

            <div class="cmd-v2-grid">
                <?php if (empty($commandes_annulees)): ?>
                    <div class="cmd-v2-empty">
                        <div class="cmd-v2-empty__icon"><i class="fas fa-ban"></i></div>
                        <h3>Aucune commande annul&eacute;e</h3>
                        <p>
                            <?php if ($vue === 'jour'): ?>
                                Aucune annulation enregistr&eacute;e aujourd'hui. Consultez &laquo;&nbsp;Toutes les annul&eacute;es&nbsp;&raquo; ou l'historique des ventes.
                            <?php else: ?>
                                Aucune commande n'a &eacute;t&eacute; annul&eacute;e pour le moment.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($commandes_annulees as $commande): ?>
                        <?php cmd_v2_render_card($commande); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>

</html>
