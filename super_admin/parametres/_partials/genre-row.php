<?php
/**
 * Ligne genre — attend $gid (int), $genre_cards, $csrf
 */
if (!isset($gid, $genre_cards[$gid], $csrf)) {
    return;
}
$card = $genre_cards[$gid];
$gr = $card['row'];
$nbp = (int) ($card['nb_produits'] ?? 0);
$g_nom = (string) ($gr['nom'] ?? '');
$g_ordre = (int) ($gr['sort_ordre'] ?? 0);
$__d = (string) ($gr['description'] ?? '');
$__short = function_exists('mb_substr') ? mb_substr($__d, 0, 120) : substr($__d, 0, 120);
if (function_exists('mb_strlen')) {
    $__d_long = mb_strlen($__d) > 120;
} else {
    $__d_long = strlen($__d) > 120;
}
$img_path = !empty($gr['image']) ? '/upload/' . str_replace('\\', '/', (string) $gr['image']) : '';
?>
<li class="sa-cat-genre-row">
    <div class="sa-cat-genre-row__grid">
        <div class="sa-cat-genre-row__visuel" aria-hidden="true">
            <?php if ($img_path !== ''): ?>
                <img class="sa-cat-genre-row__thumb" src="<?php echo htmlspecialchars($img_path, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="48" height="48" loading="lazy" decoding="async">
            <?php else: ?>
                <span class="sa-cat-genre-row__icon-wrap"><i class="fas fa-image" aria-hidden="true"></i></span>
            <?php endif; ?>
        </div>
        <div class="sa-cat-genre-row__main">
            <div class="sa-cat-genre-row__title-line">
                <span class="sa-cat-genre-row__name"><?php echo htmlspecialchars($g_nom, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="sa-cat-genre-row__order" title="Ordre d’affichage">#<?php echo $g_ordre; ?></span>
                <span class="sa-cat-genre-row__products" title="Produits associés">
                    <i class="fas fa-box" aria-hidden="true"></i>
                    <?php echo $nbp; ?>
                </span>
            </div>
            <?php if ($__d !== ''): ?>
                <p class="sa-cat-genre-row__desc"><?php echo htmlspecialchars($__short, ENT_QUOTES, 'UTF-8'); ?><?php echo $__d_long ? '…' : ''; ?></p>
            <?php else: ?>
                <p class="sa-cat-genre-row__desc sa-cat-genre-row__desc--mute">Aucune description</p>
            <?php endif; ?>
        </div>
        <div class="sa-cat-genre-row__actions">
            <a href="genres-catalogue.php?edit_genre=<?php echo (int) $gid; ?>" class="sa-btn-action sa-btn-action--primary" style="text-decoration:none;">
                <i class="fas fa-pen" aria-hidden="true"></i> Modifier
            </a>
            <form method="post" action="genres-catalogue.php" class="sa-cat-inline-form" onsubmit="return confirm('Supprimer ce genre ?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="g_id" value="<?php echo (int) $gid; ?>">
                <button type="submit" name="delete_genre" value="1" class="sa-btn-action sa-btn-action--danger" <?php echo $nbp > 0 ? 'disabled title="Retirez ce genre des produits d’abord"' : ''; ?>>
                    <i class="fas fa-trash-alt" aria-hidden="true"></i> Supprimer
                </button>
            </form>
        </div>
    </div>
</li>
