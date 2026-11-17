<?php
/**
 * Gestion des demandes de certification vendeurs — Super Admin
 * Onglets : en cours | validées | refusées
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_vendeur_certification.php';
require_once dirname(__DIR__, 2) . '/includes/senegal_regions.php';

$tab = isset($_GET['tab']) ? trim((string) $_GET['tab']) : 'en_cours';
$tab_map = [
    'en_cours'  => 'en_attente',
    'validees'  => 'approuvee',
    'refusees'  => 'refusee',
];
if (!isset($tab_map[$tab])) {
    $tab = 'en_cours';
}
$statut_sql = $tab_map[$tab];

$success = '';
$error = '';

$msg_ok = $_SESSION['super_admin_flash_ok'] ?? '';
$msg_err = $_SESSION['super_admin_flash_err'] ?? '';
unset($_SESSION['super_admin_flash_ok'], $_SESSION['super_admin_flash_err']);

$counts = vendeur_certification_counts_par_statut();
$demandes = vendeur_certification_list_par_statut($statut_sql, 150);

$tab_labels = [
    'en_cours' => 'En cours',
    'validees' => 'Validées',
    'refusees' => 'Non validées',
];
$tab_desc = [
    'en_cours' => 'Demandes envoyées par les vendeurs, en attente de votre validation.',
    'validees' => 'Certifications approuvées — le badge est actif sur la boutique.',
    'refusees' => 'Demandes refusées avec le motif indiqué au vendeur.',
];
$tab_panel_titles = [
    'en_cours' => 'Demandes en cours',
    'validees' => 'Certifications validées',
    'refusees' => 'Demandes non validées',
];
$nb_liste = count($demandes);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certifications vendeurs — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/vendor-cert-ribbon.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-certification.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-parametres.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-certifications.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-cert-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-cert-shell">
        <header class="sa-param-hero sa-cert-hero" aria-labelledby="sa-cert-title">
            <div class="sa-param-hero__inner">
                <div class="sa-param-hero__intro">
                    <h1 class="sa-param-hero__title" id="sa-cert-title">Demandes de certification</h1>
                </div>
                <div class="sa-param-hero__kpis" role="group" aria-label="Synthèse des demandes">
                    <div class="sa-param-hero-kpi<?php echo $counts['en_attente'] > 0 ? ' sa-cert-kpi--urgent' : ''; ?>">
                        <span class="sa-param-hero-kpi__label">En cours</span>
                        <span class="sa-param-hero-kpi__value"><?php echo (int) $counts['en_attente']; ?></span>
                    </div>
                    <div class="sa-param-hero-kpi">
                        <span class="sa-param-hero-kpi__label">Validées</span>
                        <span class="sa-param-hero-kpi__value"><?php echo (int) $counts['approuvee']; ?></span>
                    </div>
                    <div class="sa-param-hero-kpi">
                        <span class="sa-param-hero-kpi__label">Non validées</span>
                        <span class="sa-param-hero-kpi__value"><?php echo (int) $counts['refusee']; ?></span>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($msg_err !== ''): ?>
            <div class="sa-cert-alert sa-cert-alert--err" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($msg_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($msg_ok !== ''): ?>
            <div class="sa-cert-alert sa-cert-alert--ok" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($msg_ok, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <section class="sa-users-panel sa-cert-panel" aria-labelledby="sa-cert-panel-title">
            <div class="sa-users-panel__head sa-cert-panel__head">
                <div class="sa-cert-panel__intro">
                    <h2 id="sa-cert-panel-title">
                        <i class="fas fa-award" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($tab_panel_titles[$tab], ENT_QUOTES, 'UTF-8'); ?>
                    </h2>
                    <p class="sa-users-panel__meta">
                        <?php echo htmlspecialchars($tab_desc[$tab], ENT_QUOTES, 'UTF-8'); ?>
                        · <strong><?php echo (int) $nb_liste; ?></strong> demande<?php echo $nb_liste > 1 ? 's' : ''; ?> affichée<?php echo $nb_liste > 1 ? 's' : ''; ?>.
                    </p>
                </div>
                <nav class="sa-cert-segment" aria-label="Filtrer les demandes">
                    <a href="?tab=en_cours" class="sa-cert-segment__link<?php echo $tab === 'en_cours' ? ' is-active' : ''; ?>">
                        <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($tab_labels['en_cours'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($counts['en_attente'] > 0): ?>
                            <span class="sa-cert-segment__badge sa-cert-segment__badge--urgent"><?php echo (int) $counts['en_attente']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?tab=validees" class="sa-cert-segment__link<?php echo $tab === 'validees' ? ' is-active' : ''; ?>">
                        <i class="fas fa-circle-check" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($tab_labels['validees'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <a href="?tab=refusees" class="sa-cert-segment__link<?php echo $tab === 'refusees' ? ' is-active' : ''; ?>">
                        <i class="fas fa-circle-xmark" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($tab_labels['refusees'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </nav>
            </div>

            <?php if (empty($demandes)): ?>
                <div class="sa-users-empty sa-cert-empty">
                    <?php if ($tab === 'en_cours'): ?>
                        <i class="fas fa-inbox" aria-hidden="true"></i>
                        <p><strong>Aucune demande en cours</strong></p>
                        <p>Les nouvelles demandes des vendeurs apparaîtront ici automatiquement.</p>
                    <?php elseif ($tab === 'validees'): ?>
                        <i class="fas fa-award" aria-hidden="true"></i>
                        <p><strong>Aucune certification validée</strong></p>
                        <p>Les demandes approuvées seront listées dans cet onglet.</p>
                    <?php else: ?>
                        <i class="fas fa-ban" aria-hidden="true"></i>
                        <p><strong>Aucune demande refusée</strong></p>
                        <p>Les rejets avec motif apparaîtront ici.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <ul class="sa-cert-list">
                    <?php foreach ($demandes as $d):
                        require __DIR__ . '/includes/demande_card_summary.php';
                    endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
