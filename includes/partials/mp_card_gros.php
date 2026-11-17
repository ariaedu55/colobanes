<?php
/**
 * Repère « vente en gros » sur une carte produit (.mp-card), partout sur le site.
 * Variables : $produit (tableau), $mp_gros_part = 'badge' (sur l'image) | 'line' (sous le prix).
 * Rien n'est affiché pour un produit vendu seulement au détail.
 */
if (empty($produit) || !is_array($produit)) {
    return;
}
if (!function_exists('vente_gros_prix_min')) {
    require_once __DIR__ . '/../produit_vente_gros.php';
}
$mpg_prix = vente_gros_prix_min($produit);
if ($mpg_prix === null) {
    return;
}
$mpg_part = isset($mp_gros_part) ? (string) $mp_gros_part : 'line';

if (!defined('MP_CARD_GROS_CSS')) {
    define('MP_CARD_GROS_CSS', true);
    echo '<style>'
        . '.mp-card-badge--gros{background:#ff6b35;color:#fff}'
        . '.mp-card-badge+.mp-card-badge--gros{top:36px}'
        . '.mp-card-gros{display:flex;flex-wrap:wrap;align-items:center;gap:2px 5px;margin:4px 0 0;font-size:11.5px;font-weight:600;color:#b54708;line-height:1.3}'
        . '.mp-card-gros i{font-size:11px}'
        . '.mp-card-gros strong{color:#ff6b35;font-weight:800;white-space:nowrap}'
        . '.mp-card-gros small{color:#667085;font-weight:500;white-space:nowrap}'
        . '</style>';
}

if ($mpg_part === 'badge'): ?>
<span class="mp-card-badge mp-card-badge--gros"><i class="fas fa-boxes-stacked" aria-hidden="true"></i> Gros</span>
<?php else:
    $mpg_min = vente_gros_quantite_min($produit); ?>
<p class="mp-card-gros">
    <i class="fas fa-boxes-stacked" aria-hidden="true"></i>
    Gros dès <strong><?php echo number_format($mpg_prix, 0, ',', ' '); ?> F</strong>/pc
    <?php if ($mpg_min > 1): ?><small>· min. <?php echo (int) $mpg_min; ?></small><?php endif; ?>
</p>
<?php endif;
