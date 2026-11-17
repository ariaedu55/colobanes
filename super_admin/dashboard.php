<?php
/**
 * Tableau de bord super administrateur
 */
require_once __DIR__ . '/includes/require_login.php';
require_once dirname(__DIR__) . '/models/model_super_admin.php';

$kpis = super_admin_dashboard_kpis();

$fmt_fcfa = static function ($n) {
    return number_format((float) $n, 0, ',', ' ') . ' FCFA';
};
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — Super Admin</title>
    <?php require_once dirname(__DIR__) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 1) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page sa-dashboard-page">
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <div class="sa-users-shell">
        <header class="sa-dash-hero" aria-labelledby="sa-dash-title">
            <div class="sa-dash-hero__grid">
                <div>
                    <h1 class="sa-dash-hero__title" id="sa-dash-title">
                        <i class="fas fa-chart-pie" aria-hidden="true"></i>
                        <span>Tableau de bord</span>
                    </h1>
                </div>
            </div>
        </header>

        <section class="sa-dash-kpi-grid" aria-label="Indicateurs clés">
            <article class="sa-dash-kpi-card">
                <div class="sa-dash-kpi-card__icon" aria-hidden="true"><i class="fas fa-store"></i></div>
                <div class="sa-dash-kpi-card__body">
                    <span class="sa-dash-kpi-card__label">Boutiques inscrites</span>
                    <span class="sa-dash-kpi-card__value"><?php echo (int) $kpis['nb_boutiques']; ?></span>
                    <span class="sa-dash-kpi-card__hint">Accès actif : <?php echo (int) $kpis['nb_boutiques_actives']; ?> boutique<?php echo (int) $kpis['nb_boutiques_actives'] > 1 ? 's' : ''; ?></span>
                </div>
            </article>
            <article class="sa-dash-kpi-card">
                <div class="sa-dash-kpi-card__icon" aria-hidden="true"><i class="fas fa-users"></i></div>
                <div class="sa-dash-kpi-card__body">
                    <span class="sa-dash-kpi-card__label">Clients inscrits</span>
                    <span class="sa-dash-kpi-card__value"><?php echo (int) $kpis['nb_clients']; ?></span>
                    <span class="sa-dash-kpi-card__hint">Comptes actifs : <?php echo (int) $kpis['nb_clients_actifs']; ?></span>
                </div>
            </article>
            <article class="sa-dash-kpi-card">
                <div class="sa-dash-kpi-card__icon" aria-hidden="true"><i class="fas fa-receipt"></i></div>
                <div class="sa-dash-kpi-card__body">
                    <span class="sa-dash-kpi-card__label">Commandes</span>
                    <span class="sa-dash-kpi-card__value"><?php echo (int) $kpis['nb_commandes']; ?></span>
                    <span class="sa-dash-kpi-card__hint">Hors commandes annulées</span>
                </div>
            </article>
            <article class="sa-dash-kpi-card">
                <div class="sa-dash-kpi-card__icon" aria-hidden="true"><i class="fas fa-coins"></i></div>
                <div class="sa-dash-kpi-card__body">
                    <span class="sa-dash-kpi-card__label">Chiffre d’affaires du mois</span>
                    <span class="sa-dash-kpi-card__value" style="font-size:1.35rem;"><?php echo htmlspecialchars($fmt_fcfa($kpis['ca_mois'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="sa-dash-kpi-card__hint">Total TTC, période en cours (mois calendaire)</span>
                </div>
            </article>
            <article class="sa-dash-kpi-card">
                <div class="sa-dash-kpi-card__icon" aria-hidden="true"><i class="fas fa-box-open"></i></div>
                <div class="sa-dash-kpi-card__body">
                    <span class="sa-dash-kpi-card__label">Produits en catalogue</span>
                    <span class="sa-dash-kpi-card__value"><?php echo (int) $kpis['nb_produits_catalogue']; ?></span>
                    <span class="sa-dash-kpi-card__hint">Visibles pour les clients (actifs ou en rupture, vendeurs)</span>
                </div>
            </article>
        </section>

        <div class="sa-dash-section-head">
            <h2><i class="fas fa-bolt" aria-hidden="true"></i> Accès rapides</h2>
            <p>Ouvrez les espaces de gestion : boutiques, clients (commandes par fiche client) et traçabilité des actions sensibles.</p>
        </div>

        <nav class="sa-dash-links" aria-label="Raccourcis super administration">
            <a class="sa-dash-link" href="boutiques/index.php">
                <span class="sa-dash-link__icon" aria-hidden="true"><i class="fas fa-store"></i></span>
                <span>
                    <span class="sa-dash-link__title">Boutiques</span>
                    <span class="sa-dash-link__desc">Liste des vendeurs, détails, activation du compte et vitrines.</span>
                </span>
                <span class="sa-dash-link__arrow" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
            </a>
            <a class="sa-dash-link" href="utilisateurs/index.php">
                <span class="sa-dash-link__icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                <span>
                    <span class="sa-dash-link__title">Clients plateforme</span>
                    <span class="sa-dash-link__desc">Comptes acheteurs, historique des commandes et détail par commande.</span>
                </span>
                <span class="sa-dash-link__arrow" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
            </a>
            <a class="sa-dash-link" href="logs/index.php">
                <span class="sa-dash-link__icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></span>
                <span>
                    <span class="sa-dash-link__title">Journal d’audit</span>
                    <span class="sa-dash-link__desc">Historique des désactivations boutiques / clients et traçabilité.</span>
                </span>
                <span class="sa-dash-link__arrow" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
            </a>
        </nav>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
