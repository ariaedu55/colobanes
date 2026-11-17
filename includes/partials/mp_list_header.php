<?php
/**
 * En-tête commun des listes de l'accueil (« Voir tout ») : fil d'Ariane, titre et onglets
 * Top ventes / Les plus consultés / Nouveautés.
 * Variables attendues : $lp_active ('consultes'|'nouveautes'), $lp_title, $lp_sub, $lp_icon, $lp_count (string).
 */
if (!function_exists('app_url')) {
    require_once __DIR__ . '/../app_base.php';
}
$lp_tabs = [
    'ventes' => ['Top ventes', 'fa-trophy', '/top-ventes.php'],
    'consultes' => ['Les plus consultés', 'fa-eye', '/plus-consultes.php'],
    'nouveautes' => ['Nouveautés', 'fa-bolt', '/nouveautes.php'],
];
?>
<div class="lp-head">
    <div class="lp-shell">
        <div class="lp-head__row">
            <span class="lp-head__icon lp-head__icon--<?php echo htmlspecialchars($lp_active, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas <?php echo htmlspecialchars($lp_icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i></span>
            <div class="lp-head__text">
                <h1><?php echo htmlspecialchars($lp_title, ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>
            <?php if (!empty($lp_count)): ?><span class="lp-head__count"><?php echo htmlspecialchars($lp_count, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
        </div>
        <?php if (!isset($lp_show_tabs) || $lp_show_tabs): ?>
        <div class="lp-tabs" role="navigation" aria-label="Listes de produits">
            <?php foreach ($lp_tabs as $key => [$label, $icon, $path]): ?>
                <a href="<?php echo htmlspecialchars(app_url($path), ENT_QUOTES, 'UTF-8'); ?>" class="lp-tab<?php echo $key === $lp_active ? ' is-active' : ''; ?>"<?php echo $key === $lp_active ? ' aria-current="page"' : ''; ?>>
                    <i class="fas <?php echo $icon; ?>" aria-hidden="true"></i> <?php echo $label; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="lp-head__spacer" aria-hidden="true"></div>
        <?php endif; ?>
    </div>
</div>
