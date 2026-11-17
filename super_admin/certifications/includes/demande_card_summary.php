<?php
/**
 * Carte résumé demande certification — Super Admin (grille, vue légère)
 * Variables : $d (array)
 */
if (empty($d) || !is_array($d)) {
    return;
}
$nv = (string) ($d['niveau'] ?? 'standard');
$st_dem = (string) ($d['statut'] ?? '');
$demande_id = (int) ($d['id'] ?? 0);
$href = $st_dem === 'en_attente'
    ? 'traiter.php?id=' . $demande_id
    : 'traiter.php?id=' . $demande_id . '&vue=lecture';
$action_label = $st_dem === 'en_attente' ? 'Traiter' : 'Détail';
$action_icon = $st_dem === 'en_attente' ? 'clipboard-check' : 'arrow-right';
$action_class = $st_dem === 'en_attente' ? 'sa-cert-card__btn--primary' : '';
$show_badge = in_array($nv, ['standard', 'vip', 'premium'], true);
$date_dem = !empty($d['date_creation']) ? date('d/m/Y', strtotime((string) $d['date_creation'])) : '';
?>
<li class="sa-cert-card sa-cert-card--<?php echo htmlspecialchars($st_dem, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="sa-cert-card__main">
        <div class="sa-cert-card__icon" aria-hidden="true">
            <i class="fas fa-store"></i>
        </div>
        <div class="sa-cert-card__info">
            <h3 class="sa-cert-card__title"><?php echo htmlspecialchars((string) ($d['boutique_nom'] ?? 'Boutique'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <?php if ($show_badge): ?>
            <div class="sa-cert-card__badge">
                <?php $cert_niveau = $nv; $cert_size = 'xs'; require dirname(__DIR__, 3) . '/includes/partials/vendeur_certification_badge.php'; ?>
            </div>
            <?php endif; ?>
            <?php if ($st_dem === 'en_attente' && $date_dem !== ''): ?>
                <p class="sa-cert-card__hint">Depuis le <?php echo htmlspecialchars($date_dem, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($st_dem === 'refusee' && !empty($d['motif_refus'])): ?>
                <p class="sa-cert-card__hint sa-cert-card__hint--refus" title="<?php echo htmlspecialchars((string) $d['motif_refus'], ENT_QUOTES, 'UTF-8'); ?>">
                    Motif indiqué au vendeur
                </p>
            <?php endif; ?>
        </div>
    </div>
    <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="sa-cert-card__btn <?php echo $action_class; ?>">
        <span><?php echo htmlspecialchars($action_label, ENT_QUOTES, 'UTF-8'); ?></span>
        <i class="fas fa-<?php echo $action_icon; ?>" aria-hidden="true"></i>
    </a>
</li>
