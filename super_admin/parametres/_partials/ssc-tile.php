<?php
/**
 * Carte sous-sous-catégorie — attend $ssc (array), $csrf, $ssc_return_anchor (string)
 */
if (!isset($ssc, $csrf) || empty($ssc['id'])) {
    return;
}
$ssid = (int) $ssc['id'];
$ssc_nom = (string) ($ssc['nom'] ?? '');
$ssc_return_anchor = isset($ssc_return_anchor) ? trim((string) $ssc_return_anchor) : '';
$ssc_edit_href = 'sous-sous-categories-catalogue.php?edit_ssc=' . $ssid;
if ($ssc_return_anchor !== '' && preg_match('/^sa-ssc-parent-[0-9]+$/i', $ssc_return_anchor)) {
    $ssc_edit_href .= '#' . $ssc_return_anchor;
}
$__ssc_img = null;
if (function_exists('categorie_image_public_path')) {
    $__ssc_img = categorie_image_public_path($ssc);
    if (($__ssc_img === null || $__ssc_img === '') && isset($ssc_parent_sc) && is_array($ssc_parent_sc)) {
        $__ssc_img = categorie_image_public_path($ssc_parent_sc);
    }
    if (($__ssc_img === null || $__ssc_img === '') && !empty($ssc['parent_id']) && function_exists('get_categorie_by_id')) {
        $parent_row = get_categorie_by_id((int) $ssc['parent_id']);
        if (is_array($parent_row)) {
            $__ssc_img = categorie_image_public_path($parent_row);
        }
    }
}
?>
<article class="sa-sc-tile">
    <div class="sa-sc-tile__visual">
        <?php if (is_string($__ssc_img) && $__ssc_img !== ''): ?>
            <img src="<?php echo htmlspecialchars($__ssc_img, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
        <?php else: ?>
            <span class="sa-sc-tile__visual-placeholder" aria-hidden="true"><i class="fas fa-folder-open"></i></span>
        <?php endif; ?>
    </div>
    <div class="sa-sc-tile__body">
        <h4 class="sa-sc-tile__name"><?php echo htmlspecialchars($ssc_nom, ENT_QUOTES, 'UTF-8'); ?></h4>
        <div class="sa-sc-tile__actions">
            <a href="<?php echo htmlspecialchars($ssc_edit_href, ENT_QUOTES, 'UTF-8'); ?>" class="sa-btn-action sa-btn-action--primary sa-sc-tile__action">
                <i class="fas fa-pen" aria-hidden="true"></i> Modifier
            </a>
            <form method="post" action="sous-sous-categories-catalogue.php" class="sa-sc-tile__delete-form" onsubmit="return confirm('Supprimer cette sous-sous-catégorie ?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="ssc_id" value="<?php echo $ssid; ?>">
                <?php if ($ssc_return_anchor !== ''): ?>
                <input type="hidden" name="ssc_return_anchor" value="<?php echo htmlspecialchars($ssc_return_anchor, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <button type="submit" name="delete_ssc" value="1" class="sa-btn-action sa-btn-action--danger sa-sc-tile__action">
                    <i class="fas fa-trash-alt" aria-hidden="true"></i> Supprimer
                </button>
            </form>
        </div>
    </div>
</article>
