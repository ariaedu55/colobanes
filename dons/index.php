<?php
require_once __DIR__ . '/../includes/dons_bootstrap.php';

$f = [
    'q' => trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 80)),
    'categorie' => (int) ($_GET['categorie'] ?? 0),
    'region' => senegal_region_is_valid((string) ($_GET['region'] ?? '')) ? (string) $_GET['region'] : '',
    'etat' => isset(dons_etats()[(string) ($_GET['etat'] ?? '')]) ? (string) $_GET['etat'] : '',
    'avec_reserves' => !empty($_GET['reserves']),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$par_page = 24;
$res = dons_list_public($f, $par_page, ($page - 1) * $par_page);
$categories = dons_categories();
$nb_pages = (int) ceil($res['total'] / $par_page);
$has_filters = $f['q'] !== '' || $f['categorie'] || $f['region'] !== '' || $f['etat'] !== '' || $f['avec_reserves'];

$dons_url = function (array $override) use ($f) {
    $q = array_filter([
        'q' => $f['q'], 'categorie' => $f['categorie'] ?: null, 'region' => $f['region'],
        'etat' => $f['etat'], 'reserves' => $f['avec_reserves'] ? 1 : null,
    ]);
    foreach ($override as $k => $v) {
        if ($v === null || $v === '' || $v === 0) {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    return '/dons/' . ($q ? '?' . http_build_query($q) : '');
};

$dons_title = 'Dons — objets offerts gratuitement';
$dons_tab = 'explorer';
require __DIR__ . '/../includes/partials/dons_head.php';
?>

<div class="dn-chips" role="navigation" aria-label="Catégories">
    <a href="<?php echo dons_e($dons_url(['categorie' => null, 'page' => null])); ?>" class="dn-chip<?php echo !$f['categorie'] ? ' is-active' : ''; ?>">
        <i class="fa-solid fa-border-all" aria-hidden="true"></i>Tout
    </a>
    <?php foreach ($categories as $c): ?>
        <a href="<?php echo dons_e($dons_url(['categorie' => (int) $c['id'], 'page' => null])); ?>"
            class="dn-chip<?php echo $f['categorie'] === (int) $c['id'] ? ' is-active' : ''; ?>">
            <i class="fa-solid <?php echo dons_e($c['icone']); ?>" aria-hidden="true"></i><?php echo dons_e($c['nom']); ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="dn-resultbar">
    <p><strong><?php echo (int) $res['total']; ?></strong> don<?php echo $res['total'] > 1 ? 's' : ''; ?> <?php echo $f['avec_reserves'] ? 'disponibles ou réservés' : 'disponible' . ($res['total'] > 1 ? 's' : ''); ?></p>
    <?php if ($has_filters): ?><a href="/dons/" class="dn-link">Effacer les filtres</a><?php endif; ?>
</div>

<?php if (empty($res['items'])): ?>
    <div class="dn-empty">
        <span class="dn-empty__icon"><i class="fa-solid fa-gift" aria-hidden="true"></i></span>
        <h2><?php echo $has_filters ? 'Aucun don ne correspond' : 'Aucun don pour le moment'; ?></h2>
        <p><?php echo $has_filters ? 'Essayez une autre catégorie ou une autre région.' : 'Soyez le premier à offrir un objet à la communauté.'; ?></p>
    </div>
<?php else: ?>
    <div class="dn-grid">
        <?php foreach ($res['items'] as $d): ?>
            <article class="dn-card">
                <a href="/dons/don.php?id=<?php echo (int) $d['id']; ?>" class="dn-card__media" tabindex="-1" aria-hidden="true">
                    <img src="<?php echo dons_e(dons_img($d['image'] ?? null, 'md')); ?>" alt="" loading="lazy" decoding="async">
                    <span class="dn-card__free">Gratuit</span>
                    <?php echo dons_statut_pill($d); ?>
                </a>
                <div class="dn-card__body">
                    <h2 class="dn-card__title"><a href="/dons/don.php?id=<?php echo (int) $d['id']; ?>"><?php echo dons_e($d['titre']); ?></a></h2>
                    <p class="dn-card__desc"><?php echo dons_e(mb_strimwidth(trim((string) $d['description']), 0, 90, '…', 'UTF-8')); ?></p>
                    <ul class="dn-card__meta">
                        <li><i class="fa-solid fa-star-half-stroke" aria-hidden="true"></i><?php echo dons_e(dons_etats()[$d['etat']] ?? ''); ?></li>
                        <li><i class="fa-solid fa-layer-group" aria-hidden="true"></i><?php echo (int) $d['quantite_disponible']; ?> disponible<?php echo $d['quantite_disponible'] > 1 ? 's' : ''; ?></li>
                        <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?php echo dons_e(dons_lieu($d)); ?></li>
                        <li><i class="fa-regular fa-clock" aria-hidden="true"></i><?php echo dons_e(dons_temps_relatif($d['created_at'])); ?></li>
                    </ul>
                    <div class="dn-card__actions">
                        <a href="/dons/don.php?id=<?php echo (int) $d['id']; ?>" class="dn-btn dn-btn--ghost">Voir le don</a>
                        <?php if ($dons_uid > 0 && (int) $d['user_id'] === $dons_uid): ?>
                            <a href="/dons/mes-dons.php#don-<?php echo (int) $d['id']; ?>" class="dn-btn dn-btn--soft">Mon annonce</a>
                        <?php else: ?>
                            <a href="/dons/don.php?id=<?php echo (int) $d['id']; ?>#interet" class="dn-btn dn-btn--primary">Je suis intéressé</a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($nb_pages > 1): ?>
        <div class="dn-pager" role="navigation" aria-label="Pages">
            <?php if ($page > 1): ?><a class="dn-btn dn-btn--ghost" href="<?php echo dons_e($dons_url(['page' => $page - 1])); ?>"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Précédent</a><?php endif; ?>
            <span>Page <?php echo $page; ?> / <?php echo $nb_pages; ?></span>
            <?php if ($page < $nb_pages): ?><a class="dn-btn dn-btn--ghost" href="<?php echo dons_e($dons_url(['page' => $page + 1])); ?>">Suivant <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/partials/dons_foot.php'; ?>
