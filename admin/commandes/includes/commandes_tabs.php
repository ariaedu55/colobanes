<?php
/**
 * En-tête commun des pages Commandes (à traiter / livrées / annulées).
 * Un seul titre, une seule barre d'actions et des onglets — remplace les anciens bandeaux
 * « montant » et compteurs qui répétaient le tableau de bord.
 *
 * Variables attendues :
 *   $cmd_tabs_active  'actives' | 'livrees' | 'annulees'
 *   $cmd_tabs_vf      filtre vendeur (admin_vendeur_filter_id())
 *   $cmd_tabs_summary texte court sous le titre (optionnel, déjà échappé)
 *   $cmd_tabs_filters [[label, href, count, active], …] sous-filtres (optionnel)
 */
if (!function_exists('count_commandes_by_statut')) {
    require_once dirname(__DIR__, 3) . '/models/model_commandes_admin.php';
}

$cmd_tabs_active = $cmd_tabs_active ?? 'actives';
$cmd_tabs_vf = $cmd_tabs_vf ?? null;
$cmd_tabs_total = (int) count_commandes_by_statut(null, $cmd_tabs_vf);
$cmd_tabs_livrees = (int) count_commandes_by_statut('livree', $cmd_tabs_vf) + (int) count_commandes_by_statut('paye', $cmd_tabs_vf);
$cmd_tabs_annulees = (int) count_commandes_by_statut('annulee', $cmd_tabs_vf);
$cmd_tabs_actives = max(0, $cmd_tabs_total - $cmd_tabs_livrees - $cmd_tabs_annulees);
$cmd_tabs_create_href = $cmd_tabs_active === 'actives' ? '' : 'index.php?modal=commande_manuelle';

$cmd_tabs_items = [
    'actives' => ['À traiter', 'index.php', $cmd_tabs_actives],
    'livrees' => ['Livrées', 'livrees.php', $cmd_tabs_livrees],
    'annulees' => ['Annulées', 'annulees.php', $cmd_tabs_annulees],
];
?>
<header class="vd-page-head">
    <div class="vd-page-head__text">
        <h1 class="vd-page-head__title">Commandes</h1>
        <?php if (!empty($cmd_tabs_summary)): ?>
            <p class="vd-page-head__sub"><?php echo $cmd_tabs_summary; ?></p>
        <?php endif; ?>
    </div>
    <div class="vd-page-head__actions">
        <a href="historique-ventes.php" class="vd-btn vd-btn--ghost">
            <i class="fas fa-chart-line" aria-hidden="true"></i><span class="vd-btn__long">Historique des ventes</span><span class="vd-btn__short">Historique</span>
        </a>
        <?php if ($cmd_tabs_create_href === ''): ?>
            <button type="button" class="vd-btn vd-btn--primary" id="btn-commande-manuelle-hero">
                <i class="fas fa-plus" aria-hidden="true"></i><span class="vd-btn__long">Créer une commande</span><span class="vd-btn__short">Nouvelle commande</span>
            </button>
        <?php else: ?>
            <a href="<?php echo htmlspecialchars($cmd_tabs_create_href, ENT_QUOTES, 'UTF-8'); ?>" class="vd-btn vd-btn--primary">
                <i class="fas fa-plus" aria-hidden="true"></i><span class="vd-btn__long">Créer une commande</span><span class="vd-btn__short">Nouvelle commande</span>
            </a>
        <?php endif; ?>
    </div>
</header>

<div class="vd-tabs" role="navigation" aria-label="Type de commandes">
    <?php foreach ($cmd_tabs_items as $cmd_tabs_key => $cmd_tabs_item): ?>
        <a href="<?php echo htmlspecialchars($cmd_tabs_item[1], ENT_QUOTES, 'UTF-8'); ?>"
            class="vd-tabs__item<?php echo $cmd_tabs_key === $cmd_tabs_active ? ' is-active' : ''; ?>"
            <?php echo $cmd_tabs_key === $cmd_tabs_active ? 'aria-current="page"' : ''; ?>>
            <?php echo htmlspecialchars($cmd_tabs_item[0], ENT_QUOTES, 'UTF-8'); ?>
            <span class="vd-tabs__count"><?php echo (int) $cmd_tabs_item[2]; ?></span>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!empty($cmd_tabs_filters)): ?>
<div class="vd-chips" role="navigation" aria-label="Filtrer">
    <?php foreach ($cmd_tabs_filters as $cmd_tabs_f): ?>
        <a href="<?php echo htmlspecialchars($cmd_tabs_f[1], ENT_QUOTES, 'UTF-8'); ?>"
            class="vd-chips__item<?php echo !empty($cmd_tabs_f[3]) ? ' is-active' : ''; ?>">
            <?php echo htmlspecialchars($cmd_tabs_f[0], ENT_QUOTES, 'UTF-8'); ?>
            <?php if ($cmd_tabs_f[2] !== null): ?><span><?php echo (int) $cmd_tabs_f[2]; ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
