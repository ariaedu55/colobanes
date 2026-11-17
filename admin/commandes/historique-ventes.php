<?php
/**
 * Historique des ventes / Comptabilité vendeur & admin
 * Filtres : jour, mois, plage, année + statut + tri
 */

require_once __DIR__ . '/../includes/require_admin_session.php';



require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_route_access.php';
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/includes/commandes_v2_helpers.php';

$vf_hist = admin_vendeur_filter_id();

// Filtre appliqué uniquement si le formulaire a été soumis (ou paramètres explicites)
$filtre_applique = isset($_GET['periode']) || isset($_GET['date_jour']) || isset($_GET['date_debut']) || isset($_GET['date_fin']) || isset($_GET['filtre_statut']);

$periode = 'jour';
if (isset($_GET['periode'])) {
    $periode = (string) $_GET['periode'];
    if ($periode === 'plage') {
        $periode = 'periode';
    }
}
if (!in_array($periode, ['jour', 'periode'], true)) {
    $periode = 'jour';
}

$annee = (int) date('Y');
$mois = (int) date('n');
$jour = (int) date('j');
$date_debut = isset($_GET['date_debut']) ? trim((string) $_GET['date_debut']) : '';
$date_fin = isset($_GET['date_fin']) ? trim((string) $_GET['date_fin']) : '';

$filtre_statut = isset($_GET['filtre_statut']) ? (string) $_GET['filtre_statut'] : 'toutes';
if (!in_array($filtre_statut, ['toutes', 'vendues', 'en_cours', 'annulees'], true)) {
    $filtre_statut = 'toutes';
}

$tri = 'date_desc';

if (!empty($_GET['date_jour'])) {
    $parts = explode('-', (string) $_GET['date_jour']);
    if (count($parts) === 3) {
        $annee = (int) $parts[0];
        $mois = (int) $parts[1];
        $jour = (int) $parts[2];
    }
} elseif (isset($_GET['annee']) || isset($_GET['mois']) || isset($_GET['jour'])) {
    $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : $annee;
    $mois = isset($_GET['mois']) ? (int) $_GET['mois'] : $mois;
    $jour = isset($_GET['jour']) ? (int) $_GET['jour'] : $jour;
}

if ($mois < 1 || $mois > 12) {
    $mois = (int) date('n');
}
if ($jour < 1 || $jour > 31) {
    $jour = (int) date('j');
}
if ($annee < 2000 || $annee > 2100) {
    $annee = (int) date('Y');
}

// Sans filtre explicite « période », toujours le jour courant
if (!$filtre_applique || $periode !== 'periode') {
    $periode = 'jour';
    if (empty($_GET['date_jour']) && !isset($_GET['jour'])) {
        $annee = (int) date('Y');
        $mois = (int) date('n');
        $jour = (int) date('j');
    }
}

$filtrer_vendues_sql = ($filtre_statut === 'vendues');
$periode_query = ($periode === 'periode') ? 'plage' : 'jour';
$commandes = get_commandes_by_periode(
    $periode_query,
    $annee,
    $mois,
    ($periode === 'periode' && $date_debut !== '') ? $date_debut : null,
    ($periode === 'periode' && $date_fin !== '') ? $date_fin : null,
    $jour,
    $filtrer_vendues_sql,
    $vf_hist
);

if (!$filtrer_vendues_sql) {
    $commandes = cmd_v2_filtre_statut_commandes($commandes, $filtre_statut);
}

$commandes = cmd_v2_tri_commandes($commandes, $tri);
$stats = get_stats_comptabilite_periode($commandes);

$libelle_periode = '';
switch ($periode) {
    case 'jour':
        $libelle_periode = date('d/m/Y', strtotime(sprintf('%04d-%02d-%02d', $annee, $mois, $jour)));
        break;
    case 'periode':
        if ($date_debut !== '' && $date_fin !== '') {
            $libelle_periode = date('d/m/Y', strtotime($date_debut)) . ' — ' . date('d/m/Y', strtotime($date_fin));
        } elseif ($date_debut !== '') {
            $libelle_periode = 'À partir du ' . date('d/m/Y', strtotime($date_debut));
        } elseif ($date_fin !== '') {
            $libelle_periode = "Jusqu'au " . date('d/m/Y', strtotime($date_fin));
        } else {
            $libelle_periode = 'Période';
        }
        break;
}

$date_jour_value = sprintf('%04d-%02d-%02d', $annee, $mois, $jour);
$est_aujourdhui = ($periode === 'jour' && $date_jour_value === date('Y-m-d'));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des ventes &mdash; Administration COLObanes</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-commandes-v2.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="contents-container">
        <div class="cmd-v2-page">

            <header class="vd-page-head">
                <div class="vd-page-head__text">
                    <a href="index.php" class="vd-page-head__back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Commandes</a>
                    <h1 class="vd-page-head__title">Historique des ventes</h1>
                </div>
            </header>

            <form method="get" action="historique-ventes.php" class="cmd-v2-filters" id="hist-filter-form">
                <div class="cmd-v2-filters__grid">
                    <div class="cmd-v2-filters__group">
                        <label for="periode-select"><i class="fas fa-filter"></i> Type</label>
                        <select name="periode" id="periode-select">
                            <option value="jour" <?php echo $periode === 'jour' ? 'selected' : ''; ?>>Jour</option>
                            <option value="periode" <?php echo $periode === 'periode' ? 'selected' : ''; ?>>P&eacute;riode</option>
                        </select>
                    </div>
                    <div class="cmd-v2-filters__group" id="wrap-jour" style="display:<?php echo $periode === 'jour' ? 'block' : 'none'; ?>;">
                        <label for="date-jour"><i class="fas fa-calendar-day"></i> Date</label>
                        <input type="date" name="date_jour" id="date-jour" value="<?php echo htmlspecialchars($date_jour_value, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="cmd-v2-filters__group" id="wrap-plage" style="display:<?php echo $periode === 'periode' ? 'block' : 'none'; ?>;">
                        <label for="date-debut">Date d&eacute;but</label>
                        <input type="date" name="date_debut" id="date-debut" value="<?php echo htmlspecialchars($date_debut !== '' ? $date_debut : date('Y-m-01'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="cmd-v2-filters__group" id="wrap-plage-fin" style="display:<?php echo $periode === 'periode' ? 'block' : 'none'; ?>;">
                        <label for="date-fin">Date fin</label>
                        <input type="date" name="date_fin" id="date-fin" value="<?php echo htmlspecialchars($date_fin !== '' ? $date_fin : date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="cmd-v2-filters__group">
                        <label for="filtre-statut"><i class="fas fa-tags"></i> Statut</label>
                        <select name="filtre_statut" id="filtre-statut">
                            <option value="toutes" <?php echo $filtre_statut === 'toutes' ? 'selected' : ''; ?>>Toutes</option>
                            <option value="vendues" <?php echo $filtre_statut === 'vendues' ? 'selected' : ''; ?>>Ventes finalis&eacute;es (livr&eacute;e / pay&eacute;e)</option>
                            <option value="en_cours" <?php echo $filtre_statut === 'en_cours' ? 'selected' : ''; ?>>En cours de traitement</option>
                            <option value="annulees" <?php echo $filtre_statut === 'annulees' ? 'selected' : ''; ?>>Annul&eacute;es</option>
                        </select>
                    </div>
                    <div class="cmd-v2-filters__group">
                        <label>&nbsp;</label>
                        <button type="submit" class="cmd-v2-filters__submit">
                            <i class="fas fa-search"></i> Appliquer
                        </button>
                    </div>
                </div>
            </form>

            <div class="cmd-v2-section-head">
                <div>
                    <h2><i class="fas fa-calendar-check"></i>
                        <?php if ($est_aujourdhui): ?>
                            Ventes du jour
                        <?php else: ?>
                            <?php echo htmlspecialchars($libelle_periode, ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </h2>
                    <p><?php echo count($commandes); ?> r&eacute;sultat<?php echo count($commandes) > 1 ? 's' : ''; ?></p>
                </div>
            </div>
            <div class="cmd-v2-kpi-row" style="margin-bottom:22px;">
                <div class="cmd-v2-kpi cmd-v2-kpi--highlight">
                    <label>Gains</label>
                    <strong><?php echo number_format($stats['montant_livrees'], 0, ',', ' '); ?> FCFA</strong>
                </div>
                <div class="cmd-v2-kpi">
                    <label>Commandes</label>
                    <strong><?php echo $stats['nb_commandes']; ?></strong>
                </div>
            </div>

            <?php if (empty($commandes)): ?>
                <div class="cmd-v2-empty">
                    <div class="cmd-v2-empty__icon"><i class="fas fa-receipt"></i></div>
                    <h3>Aucune vente sur cette p&eacute;riode</h3>
                    <p>Choisissez un autre jour ou une p&eacute;riode, puis cliquez sur Appliquer.</p>
                </div>
            <?php else: ?>
                <div class="cmd-v2-table-wrap">
                    <table class="cmd-v2-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Statut</th>
                                <th class="montant">Montant</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandes as $c): ?>
                                <?php
                                $client = trim(($c['user_prenom'] ?? '') . ' ' . ($c['user_nom'] ?? ''));
                                if ($client === '') {
                                    $client = 'Client inconnu';
                                }
                                $st = $c['statut'] ?? '';
                                ?>
                                <tr>
                                    <td><?php echo !empty($c['date_commande']) ? date('d/m/Y H:i', strtotime($c['date_commande'])) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($client, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="cmd-badge <?php echo cmd_v2_statut_class($st); ?>">
                                            <?php echo htmlspecialchars(cmd_v2_statut_label($st), ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="montant"><?php echo number_format((float) ($c['montant_total'] ?? 0), 0, ',', ' '); ?> FCFA</td>
                                    <td>
                                        <a href="details.php?id=<?php echo (int) ($c['id'] ?? 0); ?>" class="cmd-v2-table__link" title="Voir le d&eacute;tail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
    (function () {
        var periodeSelect = document.getElementById('periode-select');
        function togglePeriodeFields(periode) {
            var map = {
                jour: ['wrap-jour'],
                periode: ['wrap-plage', 'wrap-plage-fin']
            };
            ['wrap-jour', 'wrap-plage', 'wrap-plage-fin'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });
            (map[periode] || []).forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'block';
            });
        }
        if (periodeSelect) {
            periodeSelect.addEventListener('change', function () {
                togglePeriodeFields(this.value);
            });
            togglePeriodeFields(periodeSelect.value);
        }

        var form = document.getElementById('hist-filter-form');
        if (form) {
            form.addEventListener('submit', function () {
                form.querySelectorAll('.cmd-v2-filters__group').forEach(function (group) {
                    if (group.style.display === 'none') {
                        group.querySelectorAll('select, input').forEach(function (el) {
                            el.disabled = true;
                        });
                    }
                });
            });
        }
    })();
    </script>
</body>

</html>
