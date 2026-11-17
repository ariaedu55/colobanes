<?php
/**
 * Formulaire sous-catégorie (modal)
 * Attend : $row_edit, $rayons, $edit_rayon, $csrf, $table_ok
 */
$is_edit = is_array($row_edit) && !empty($row_edit);
$sc_nom = $is_edit ? (string) ($row_edit['nom'] ?? '') : '';
$sc_desc = $is_edit ? (string) ($row_edit['description'] ?? '') : '';
$sc_image = $is_edit ? (string) ($row_edit['image'] ?? '') : '';
$sc_tailles_selected = [];
$sc_tailles_custom = '';
if ($is_edit && function_exists('categorie_tailles_selected_values')) {
    $sc_tailles_selected = categorie_tailles_selected_values($row_edit);
}
$sc_tailles_presets = function_exists('categorie_tailles_preset_labels') ? categorie_tailles_preset_labels() : ['S', 'M', 'L', 'XL'];
$sc_tailles_has_col = function_exists('categories_table_has_column') && categories_table_has_column('tailles');
if ($is_edit && !empty($sc_tailles_selected)) {
    $custom_parts = [];
    foreach ($sc_tailles_selected as $tv) {
        if (!in_array($tv, $sc_tailles_presets, true)) {
            $custom_parts[] = $tv;
        }
    }
    $sc_tailles_custom = implode(', ', $custom_parts);
}
?>
<form class="sa-cat-form sa-sc-modal-form" method="post" enctype="multipart/form-data" action="sous-categories-catalogue.php">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($is_edit): ?>
        <input type="hidden" name="sc_id" value="<?php echo (int) $row_edit['id']; ?>">
    <?php endif; ?>

    <div class="sa-sc-modal-form__grid">
        <div class="sa-sc-modal-form__col">
            <div class="sa-genre-info-card sa-sc-info-card">
                <header class="sa-genre-info-card__head">
                    <span class="sa-genre-info-card__head-icon" aria-hidden="true"><i class="fas fa-tag"></i></span>
                    <div>
                        <h3 class="sa-genre-info-card__title">Sous-catégorie</h3>
                        <p class="sa-genre-info-card__lead">Libellé et visuel pour les vendeurs</p>
                    </div>
                </header>
                <div class="sa-genre-info-card__body">
                    <div class="sa-genre-info-media">
                        <div class="sa-genre-info-media__preview" id="saScImagePreview" aria-hidden="true">
                            <?php if ($is_edit && $sc_image !== ''): ?>
                                <img src="/upload/<?php echo htmlspecialchars(str_replace('\\', '/', $sc_image), ENT_QUOTES, 'UTF-8'); ?>" alt="" width="174" height="174" decoding="async">
                            <?php else: ?>
                                <span class="sa-genre-info-media__placeholder"><i class="fas fa-image"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="sa-genre-info-media__upload">
                            <label class="sa-genre-info-upload" for="sc_image">
                                <span class="sa-genre-info-upload__btn">
                                    <i class="fas fa-cloud-arrow-up" aria-hidden="true"></i>
                                    <?php echo ($is_edit && $sc_image !== '') ? 'Remplacer l’image' : 'Ajouter une image'; ?>
                                </span>
                            </label>
                            <input type="file" class="sa-genre-info-upload__input" id="sc_image" name="image"
                                accept="image/jpeg,image/png,image/gif,image/webp" data-sa-sc-image-input
                                <?php echo !$table_ok ? 'disabled' : ''; ?>>
                            <p class="sa-genre-info-media__hint">Optionnel · JPEG, PNG, GIF ou WebP</p>
                        </div>
                    </div>
                    <div class="sa-cat-field sa-genre-info-field">
                        <label for="sc_nom">Nom <span class="sa-genre-modal-form__req" aria-hidden="true">*</span></label>
                        <input type="text" id="sc_nom" name="sc_nom" required maxlength="255"
                            class="sa-genre-info-input sa-genre-info-input--title"
                            placeholder="Ex. Huiles essentielles"
                            value="<?php echo htmlspecialchars($sc_nom, ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo !$table_ok ? 'disabled' : ''; ?>>
                    </div>
                    <?php if ($sc_tailles_has_col): ?>
                    <div class="sa-sc-tailles sa-cat-field">
                        <span class="sa-sc-tailles__label">Tailles proposées</span>
                        <p class="sa-sc-tailles__hint">Ex. S, M, L, XL — utilisées comme repère pour les produits de cette rubrique</p>
                        <div class="sa-sc-tailles__chips" role="group" aria-label="Tailles">
                            <?php foreach ($sc_tailles_presets as $sz):
                                $sz_u = strtoupper($sz);
                                $sz_on = in_array($sz_u, $sc_tailles_selected, true);
                            ?>
                            <label class="sa-sc-taille-chip<?php echo $sz_on ? ' is-on' : ''; ?>">
                                <input type="checkbox" name="sc_tailles_preset[]" value="<?php echo htmlspecialchars($sz_u, ENT_QUOTES, 'UTF-8'); ?>"
                                    <?php echo $sz_on ? 'checked' : ''; ?>
                                    <?php echo !$table_ok ? 'disabled' : ''; ?>>
                                <span><?php echo htmlspecialchars($sz_u, ENT_QUOTES, 'UTF-8'); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <label class="sa-sc-tailles__custom-label" for="sc_tailles_custom">Autres tailles</label>
                        <input type="text" id="sc_tailles_custom" name="sc_tailles_custom" class="sa-genre-info-input"
                            placeholder="Ex. SL, 38, 40 (séparées par des virgules)"
                            value="<?php echo htmlspecialchars($sc_tailles_custom, ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo !$table_ok ? 'disabled' : ''; ?>>
                    </div>
                    <?php endif; ?>
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="sc_description" value="<?php echo htmlspecialchars($sc_desc, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="sa-sc-modal-form__col">
            <div class="sa-sc-rayon-pick-card">
                <p class="sa-genre-modal-form__section-label">
                    <i class="fas fa-sitemap" aria-hidden="true"></i>
                    Rayon parent <span class="sa-genre-modal-form__req" aria-hidden="true">*</span>
                </p>
                <div class="sa-sc-rayon-select-list" role="radiogroup" aria-label="Rayon">
                    <?php foreach ($rayons as $r):
                        $rid = (int) ($r['id'] ?? 0);
                        if ($rid <= 0) {
                            continue;
                        }
                        $r_nom = (string) ($r['nom'] ?? '');
                        $checked = ($is_edit && $edit_rayon === $rid) || (!$is_edit && $edit_rayon === $rid);
                    ?>
                    <label class="sa-sc-rayon-select<?php echo $checked ? ' is-checked' : ''; ?>">
                        <input type="radio" name="sc_categorie_generale_id" value="<?php echo $rid; ?>"
                            <?php echo $checked ? 'checked' : ''; ?>
                            <?php echo !$table_ok ? 'disabled' : ''; ?>
                            required>
                        <span class="sa-sc-rayon-select__card">
                            <span class="sa-sc-rayon-select__dot" aria-hidden="true"></span>
                            <span class="sa-sc-rayon-select__label"><?php echo htmlspecialchars($r_nom, ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="sa-genre-modal-form__footer">
        <button type="submit" name="<?php echo $is_edit ? 'update_sc' : 'create_sc'; ?>" value="1"
            class="sa-cat-btn sa-cat-btn--primary" <?php echo $table_ok ? '' : 'disabled'; ?>>
            <i class="fas fa-check" aria-hidden="true"></i>
            <?php echo $is_edit ? 'Enregistrer' : 'Ajouter la sous-catégorie'; ?>
        </button>
        <?php if ($is_edit): ?>
            <a href="sous-categories-catalogue.php" class="sa-cat-btn sa-cat-btn--ghost">Annuler</a>
        <?php else: ?>
            <button type="button" class="sa-cat-btn sa-cat-btn--ghost" data-sa-cat-close-modal>Annuler</button>
        <?php endif; ?>
    </div>
</form>
