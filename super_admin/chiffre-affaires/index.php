<?php
/**
 * Chiffre d'affaires plateforme — consultation par mois
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';

$mois_noms = [
    1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
    7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
];

$now_y = (int) date('Y');
$now_m = (int) date('n');

$annee = isset($_GET['annee']) ? (int) $_GET['annee'] : $now_y;
$mois = isset($_GET['mois']) ? (int) $_GET['mois'] : $now_m;

if ($mois < 1 || $mois > 12) {
    $mois = $now_m;
}
if ($annee < 2000 || $annee > 2100) {
    $annee = $now_y;
}

if ($annee > $now_y || ($annee === $now_y && $mois > $now_m)) {
    $annee = $now_y;
    $mois = $now_m;
}

$premier = super_admin_ca_premier_mois_activite();
$stats = super_admin_ca_pour_mois($annee, $mois);
$ca_boutiques = super_admin_ca_par_boutique_mois($annee, $mois);
$articles_par_boutique = super_admin_articles_vendus_boutiques_mois($annee, $mois);
$historique = super_admin_ca_historique_mois(36);

$ca = (float) ($stats['ca_total'] ?? 0);
$nb_cmd = (int) ($stats['nb_commandes'] ?? 0);
$panier_moyen = $nb_cmd > 0 ? $ca / $nb_cmd : 0.0;

$fmt_fcfa = static function ($n) {
    return number_format((float) $n, 0, ',', ' ') . ' FCFA';
};

$label_mois = ucfirst($mois_noms[$mois] ?? '') . ' ' . $annee;
$is_mois_courant = ($annee === $now_y && $mois === $now_m);

$prev_y = $mois === 1 ? $annee - 1 : $annee;
$prev_m = $mois === 1 ? 12 : $mois - 1;
$next_y = $mois === 12 ? $annee + 1 : $annee;
$next_m = $mois === 12 ? 1 : $mois + 1;

$can_prev = true;
if ($premier !== null) {
    $can_prev = !($prev_y < (int) $premier['annee']
        || ($prev_y === (int) $premier['annee'] && $prev_m < (int) $premier['mois']));
}

$can_next = !($next_y > $now_y || ($next_y === $now_y && $next_m > $now_m));

$url_mois = static function ($y, $m) {
    return 'index.php?' . http_build_query(['annee' => (int) $y, 'mois' => (int) $m]);
};

$ca_max_hist = 0.0;
foreach ($historique as $h) {
    $v = (float) ($h['ca_total'] ?? 0);
    if ($v > $ca_max_hist) {
        $ca_max_hist = $v;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chiffre d'affaires — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-ca.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-ca-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-ca-shell">
        <header class="sa-ca-hero" aria-labelledby="sa-ca-title">
            <div class="sa-ca-hero__top">
                <div>
                    <h1 class="sa-ca-hero__title" id="sa-ca-title">Chiffre d'affaires</h1>
                    <p class="sa-ca-hero__period"><?php echo htmlspecialchars($label_mois, ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($is_mois_courant): ?>
                            <span class="sa-badge sa-badge--ok sa-ca-hero__badge">Mois en cours</span>
                        <?php endif; ?>
                    </p>
                </div>
                <nav class="sa-ca-hero__nav" aria-label="Changer de mois">
                    <?php if ($can_prev): ?>
                        <a class="sa-ca-hero__nav-btn" href="<?php echo htmlspecialchars($url_mois($prev_y, $prev_m), ENT_QUOTES, 'UTF-8'); ?>" title="Mois précédent">
                            <i class="fas fa-chevron-left" aria-hidden="true"></i>
                        </a>
                    <?php else: ?>
                        <span class="sa-ca-hero__nav-btn sa-ca-hero__nav-btn--off" aria-hidden="true"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>
                    <?php if ($can_next): ?>
                        <a class="sa-ca-hero__nav-btn" href="<?php echo htmlspecialchars($url_mois($next_y, $next_m), ENT_QUOTES, 'UTF-8'); ?>" title="Mois suivant">
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </a>
                    <?php else: ?>
                        <span class="sa-ca-hero__nav-btn sa-ca-hero__nav-btn--off" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </nav>
            </div>

            <div class="sa-ca-hero__kpis">
                <div class="sa-ca-hero-kpi sa-ca-hero-kpi--lead">
                    <span class="sa-ca-hero-kpi__label">Total TTC</span>
                    <span class="sa-ca-hero-kpi__value"><?php echo htmlspecialchars($fmt_fcfa($ca), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="sa-ca-hero-kpi">
                    <span class="sa-ca-hero-kpi__label">Commandes</span>
                    <span class="sa-ca-hero-kpi__value"><?php echo (int) $nb_cmd; ?></span>
                </div>
                <div class="sa-ca-hero-kpi">
                    <span class="sa-ca-hero-kpi__label">Panier moyen</span>
                    <span class="sa-ca-hero-kpi__value sa-ca-hero-kpi__value--sm"><?php echo htmlspecialchars($fmt_fcfa($panier_moyen), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </header>

        <div class="sa-ca-grid">
            <section class="sa-users-panel sa-ca-panel sa-ca-panel--boutiques" aria-labelledby="sa-ca-boutiques-title">
                <div class="sa-users-panel__head">
                    <h2 id="sa-ca-boutiques-title">
                        <i class="fas fa-store" aria-hidden="true"></i> Par boutique
                    </h2>
                    <p class="sa-users-panel__meta"><?php echo count($ca_boutiques); ?> boutique<?php echo count($ca_boutiques) > 1 ? 's' : ''; ?> avec vente<?php echo count($ca_boutiques) > 1 ? 's' : ''; ?> · <?php echo htmlspecialchars($label_mois, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

                <?php if (empty($ca_boutiques)): ?>
                    <div class="sa-users-empty">
                        <i class="fas fa-store-slash" aria-hidden="true"></i>
                        <p><strong>Aucune boutique avec vente</strong> pour <?php echo htmlspecialchars($label_mois, ENT_QUOTES, 'UTF-8'); ?>.</p>
                    </div>
                <?php else: ?>
                    <ul class="sa-ca-boutique-list">
                        <?php foreach ($ca_boutiques as $b):
                            $vid = (int) ($b['vendeur_id'] ?? 0);
                            $bnom = (string) ($b['boutique_nom'] ?? 'Boutique');
                            $articles = $articles_par_boutique[$vid] ?? [];
                            $nb_art = count($articles);
                            $inline_id = 'sa-ca-articles-' . $vid;
                        ?>
                        <li class="sa-ca-boutique-card">
                            <div class="sa-ca-boutique-card__grid">
                                <a class="sa-ca-boutique-card__shop" href="../boutiques/detail.php?id=<?php echo $vid; ?>">
                                    <span class="sa-ca-boutique-card__icon" aria-hidden="true"><i class="fas fa-store"></i></span>
                                    <span class="sa-ca-boutique-card__shop-body">
                                        <span class="sa-ca-boutique-card__name"><?php echo htmlspecialchars($bnom, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="sa-ca-boutique-card__cmds">
                                            <i class="fas fa-receipt" aria-hidden="true"></i>
                                            <?php echo (int) ($b['nb_commandes'] ?? 0); ?> commande<?php echo (int) ($b['nb_commandes'] ?? 0) > 1 ? 's' : ''; ?>
                                        </span>
                                    </span>
                                </a>
                                <div class="sa-ca-boutique-card__aside">
                                    <span class="sa-ca-boutique-card__ca"><?php echo htmlspecialchars($fmt_fcfa($b['ca_total'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <button type="button" class="sa-ca-boutique-card__btn"
                                        data-sa-ca-toggle
                                        aria-expanded="false"
                                        aria-controls="<?php echo htmlspecialchars($inline_id, ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="sa-ca-boutique-card__btn-label">Articles</span>
                                        <?php if ($nb_art > 0): ?>
                                            <span class="sa-ca-boutique-card__count"><?php echo (int) $nb_art; ?></span>
                                        <?php endif; ?>
                                        <i class="fas fa-chevron-down sa-ca-boutique-card__chevron" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="sa-ca-articles-inline" id="<?php echo htmlspecialchars($inline_id, ENT_QUOTES, 'UTF-8'); ?>" hidden>
                                <?php if (empty($articles)): ?>
                                    <p class="sa-ca-articles-inline__empty">Aucun détail article pour cette période.</p>
                                <?php else: ?>
                                    <ul class="sa-ca-articles-inline__list">
                                        <?php foreach ($articles as $art): ?>
                                            <li class="sa-ca-articles-inline__item">
                                                <span class="sa-ca-articles-inline__name"><?php echo htmlspecialchars((string) ($art['produit_nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="sa-ca-articles-inline__meta">
                                                    <span>× <?php echo (int) ($art['quantite'] ?? 0); ?></span>
                                                    <span class="sa-ca-articles-inline__amount"><?php echo htmlspecialchars($fmt_fcfa($art['montant'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></span>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <aside class="sa-users-panel sa-ca-panel sa-ca-panel--months" aria-labelledby="sa-ca-hist-title">
                <div class="sa-users-panel__head">
                    <h2 id="sa-ca-hist-title"><i class="fas fa-calendar-days" aria-hidden="true"></i> Autres mois</h2>
                </div>

                <?php if (empty($historique)): ?>
                    <div class="sa-users-empty sa-users-empty--compact">
                        <p><strong>Aucun historique.</strong></p>
                    </div>
                <?php else: ?>
                    <ul class="sa-ca-month-list">
                        <?php foreach ($historique as $h):
                            $hy = (int) $h['annee'];
                            $hm = (int) $h['mois'];
                            $is_sel = ($hy === $annee && $hm === $mois);
                            $h_label = ucfirst($mois_noms[$hm] ?? '') . ' ' . $hy;
                            $h_ca = (float) ($h['ca_total'] ?? 0);
                            $bar_pct = $ca_max_hist > 0 ? min(100, round(($h_ca / $ca_max_hist) * 100)) : 0;
                        ?>
                        <li class="sa-ca-month-item<?php echo $is_sel ? ' sa-ca-month-item--active' : ''; ?>">
                            <?php if ($is_sel): ?>
                                <div class="sa-ca-month-item__link sa-ca-month-item__link--current">
                                    <span class="sa-ca-month-item__label"><?php echo htmlspecialchars($h_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="sa-ca-month-item__ca"><?php echo htmlspecialchars($fmt_fcfa($h_ca), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="sa-ca-month-item__bar" style="--sa-ca-pct: <?php echo (int) $bar_pct; ?>%;" aria-hidden="true"></span>
                                </div>
                            <?php else: ?>
                                <a class="sa-ca-month-item__link" href="<?php echo htmlspecialchars($url_mois($hy, $hm), ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="sa-ca-month-item__label"><?php echo htmlspecialchars($h_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="sa-ca-month-item__meta"><?php echo (int) $h['nb_commandes']; ?> cmd.</span>
                                    <span class="sa-ca-month-item__ca"><?php echo htmlspecialchars($fmt_fcfa($h_ca), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="sa-ca-month-item__bar" style="--sa-ca-pct: <?php echo (int) $bar_pct; ?>%;" aria-hidden="true"></span>
                                </a>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <script>
    (function () {
        document.querySelectorAll('[data-sa-ca-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('aria-controls');
                var panel = id ? document.getElementById(id) : null;
                if (!panel) return;
                var open = panel.hidden;
                document.querySelectorAll('.sa-ca-articles-inline').forEach(function (p) {
                    p.hidden = true;
                });
                document.querySelectorAll('[data-sa-ca-toggle]').forEach(function (b) {
                    b.setAttribute('aria-expanded', 'false');
                    b.classList.remove('is-open');
                });
                if (open) {
                    panel.hidden = false;
                    btn.setAttribute('aria-expanded', 'true');
                    btn.classList.add('is-open');
                }
            });
        });
    })();
    </script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
