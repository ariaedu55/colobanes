<?php
/**
 * Accueil — rangée de 4 tuiles sous la 1re ligne de « Nos produits » :
 * 3 rayons tirés au hasard à chaque visite ($mp_quad_rayons, index.php) + « Vendez sur COLObanes ».
 * Le script de index.php la recolle juste sous la 1re ligne selon le nombre de colonnes.
 */
$mp_quad_rayons = $mp_quad_rayons ?? [];
if (count($mp_quad_rayons) < 3) {
    return;
}
$quad_tons = ['rose', 'sable', 'gris'];
?>
<div class="mp-quad" id="mpQuad" role="region" aria-label="Rayons à découvrir">
    <?php foreach ($mp_quad_rayons as $qi => $qr):
        $q_nom = trim((string) $qr['nom']);
        $q_nom = mb_strtoupper(mb_substr($q_nom, 0, 1, 'UTF-8'), 'UTF-8') . mb_strtolower(mb_substr($q_nom, 1, null, 'UTF-8'), 'UTF-8');
        $q_href = function_exists('nav_categorie_generale_filtre_href')
            ? nav_categorie_generale_filtre_href((int) $qr['id'], 0, 0)
            : app_url('/categorie.php?generale=' . (int) $qr['id']);
        $q_img = function_exists('categorie_image_public_path') ? categorie_image_public_path($qr) : null;
    ?>
        <a class="mp-quad__tile mp-quad__tile--<?php echo $quad_tons[$qi] ?? 'gris'; ?>" href="<?php echo htmlspecialchars($q_href, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="mp-quad__text">
                <strong class="mp-quad__title"><?php echo htmlspecialchars($q_nom, ENT_QUOTES, 'UTF-8'); ?></strong>
                <span class="mp-quad__sub"><?php echo (int) $qr['nb']; ?> produits à découvrir</span>
                <span class="mp-quad__btn">Voir le rayon</span>
            </span>
            <?php if (is_string($q_img) && $q_img !== ''): ?>
                <span class="mp-quad__img" aria-hidden="true">
                    <img src="<?php echo htmlspecialchars(upload_image_url_from_src($q_img, 'md'), ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
                </span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
    <a class="mp-quad__tile mp-quad__tile--vendre" href="<?php echo htmlspecialchars(app_url('/admin/inscription-vendeur.php'), ENT_QUOTES, 'UTF-8'); ?>">
        <span class="mp-quad__text">
            <span class="mp-quad__brand">COLO<b>banes</b> <i class="fas fa-store" aria-hidden="true"></i></span>
            <strong class="mp-quad__title">Vendez sur COLObanes</strong>
            <span class="mp-quad__sub">Boutique gratuite, clients près de chez vous</span>
            <span class="mp-quad__btn">Ouvrir ma boutique</span>
        </span>
        <span class="mp-quad__img mp-quad__img--icon" aria-hidden="true"><i class="fas fa-shop"></i></span>
    </a>
</div>
