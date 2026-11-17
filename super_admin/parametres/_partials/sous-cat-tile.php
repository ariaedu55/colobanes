<?php
/**
 * Carte sous-catégorie — attend $sc (array), $csrf, $sc_return_anchor (string)
 */
if (!isset($sc, $csrf) || empty($sc['id'])) {
    return;
}
$scid = (int) $sc['id'];
$sc_nom = (string) ($sc['nom'] ?? '');
$sc_return_anchor = isset($sc_return_anchor) ? trim((string) $sc_return_anchor) : '';
$sc_edit_href = 'sous-categories-catalogue.php?edit_sc=' . $scid;
if ($sc_return_anchor !== '' && preg_match('/^sa-sc-rayon-[a-z0-9-]+$/i', $sc_return_anchor)) {
    $sc_edit_href .= '#' . $sc_return_anchor;
}
$__sc_img = function_exists('categorie_image_public_path') ? categorie_image_public_path($sc) : null;
?>
<article class="sa-sc-tile">
    <div class="sa-sc-tile__visual">
        <?php if (is_string($__sc_img) && $__sc_img !== ''): ?>
            <img src="<?php echo htmlspecialchars($__sc_img, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
        <?php else: ?>
            <span class="sa-sc-tile__visual-placeholder" aria-hidden="true"><i class="fas fa-shapes"></i></span>
        <?php endif; ?>
    </div>
    <div class="sa-sc-tile__body">
        <h4 class="sa-sc-tile__name"><?php echo htmlspecialchars($sc_nom, ENT_QUOTES, 'UTF-8'); ?></h4>
        <div class="sa-sc-tile__actions">
            <a href="<?php echo htmlspecialchars($sc_edit_href, ENT_QUOTES, 'UTF-8'); ?>" class="sa-btn-action sa-btn-action--primary sa-sc-tile__action">
                <i class="fas fa-pen" aria-hidden="true"></i> Modifier
            </a>
            <form method="post" action="sous-categories-catalogue.php" class="sa-sc-tile__delete-form" onsubmit="return confirm('Supprimer cette sous-catégorie ?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="sc_id" value="<?php echo $scid; ?>">
                <?php if ($sc_return_anchor !== ''): ?>
                <input type="hidden" name="sc_return_anchor" value="<?php echo htmlspecialchars($sc_return_anchor, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <button type="submit" name="delete_sc" value="1" class="sa-btn-action sa-btn-action--danger sa-sc-tile__action">
                    <i class="fas fa-trash-alt" aria-hidden="true"></i> Supprimer
                </button>
            </form>
        </div>
    </div>
</article>
