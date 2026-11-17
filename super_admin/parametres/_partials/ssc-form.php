<?php
/**
 * Formulaire grille de tailles (sous-sous-catégorie) — modal.
 * Attend : $row_edit, $parents_sc, $ssc_parents_by_rayon, $edit_parent, $csrf, $table_ok
 * Création : plusieurs sous-catégories cochables (ssc_parent_ids[]). Modification : une seule (ssc_parent_id).
 */
$is_edit = is_array($row_edit) && !empty($row_edit);
$ssc_desc = $is_edit ? (string) ($row_edit['description'] ?? '') : '';
$ssc_tranche_age = $is_edit ? strtolower(trim((string) ($row_edit['tranche_age'] ?? ''))) : '';
$ssc_tranche_options = function_exists('categorie_tranche_age_options') ? categorie_tranche_age_options() : [];
$ssc_tranche_has_col = function_exists('categories_table_has_column') && categories_table_has_column('tranche_age');
$ssc_tailles_has_col = function_exists('categories_table_has_column') && categories_table_has_column('tailles');
$ssc_tailles_selected = ($is_edit && function_exists('categorie_tailles_selected_values'))
    ? categorie_tailles_selected_values($row_edit) : [];
$ssc_tailles_custom = '';
if ($is_edit && !empty($ssc_tailles_selected) && function_exists('categorie_tailles_flat_presets_for_tranche')) {
    $known = categorie_tailles_flat_presets_for_tranche($ssc_tranche_age);
    $ssc_tailles_custom = implode(', ', array_values(array_filter($ssc_tailles_selected, static function ($tv) use ($known) {
        return !in_array($tv, $known, true);
    })));
}
$ssc_tailles_groups_json = function_exists('categorie_tailles_tranche_preset_groups')
    ? json_encode(categorie_tailles_tranche_preset_groups(), JSON_UNESCAPED_UNICODE) : '{}';
$ssc_tailles_initial_html = $is_edit && function_exists('categorie_tailles_render_preset_groups_html')
    ? categorie_tailles_render_preset_groups_html($ssc_tranche_age, $ssc_tailles_selected, !$table_ok)
    : '<p class="ssc-sizes__empty">Choisissez d’abord le type.</p>';
$ssc_parents_by_rayon = isset($ssc_parents_by_rayon) && is_array($ssc_parents_by_rayon) ? $ssc_parents_by_rayon : ['' => $parents_sc];
$dis = $table_ok ? '' : ' disabled';
?>
<form class="ssc-form" method="post" action="sous-sous-categories-catalogue.php">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($is_edit): ?>
        <input type="hidden" name="ssc_id" value="<?php echo (int) $row_edit['id']; ?>">
        <input type="hidden" name="ssc_description" value="<?php echo htmlspecialchars($ssc_desc, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <div class="ssc-form__body">
        <div class="ssc-form__col">
            <?php if ($ssc_tranche_has_col): ?>
            <div class="ssc-field">
                <label for="ssc_tranche_age"><span class="ssc-step">1</span> Type de tailles</label>
                <select id="ssc_tranche_age" name="ssc_tranche_age" required data-ssc-tranche-select<?php echo $dis; ?>>
                    <option value="">— Choisir —</option>
                    <?php foreach ($ssc_tranche_options as $opt):
                        $ov = (string) ($opt['v'] ?? '');
                        if ($ov === '') {
                            continue;
                        }
                    ?>
                    <option value="<?php echo htmlspecialchars($ov, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $ssc_tranche_age === $ov ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars((string) ($opt['label'] ?? $ov), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($ssc_tailles_has_col): ?>
            <div class="ssc-field">
                <span class="ssc-field__label"><span class="ssc-step">2</span> Tailles proposées aux vendeurs</span>
                <div id="sscTaillesGroups" class="ssc-sizes"
                    data-presets="<?php echo htmlspecialchars($ssc_tailles_groups_json, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo $ssc_tailles_initial_html; ?>
                </div>
                <label class="ssc-field__sub" for="ssc_tailles_custom">Autres tailles (facultatif)</label>
                <input type="text" id="ssc_tailles_custom" name="ssc_tailles_custom"
                    placeholder="Ex. 4XL, 47, Taille unique"
                    value="<?php echo htmlspecialchars($ssc_tailles_custom, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $dis; ?>>
            </div>
            <?php endif; ?>
        </div>

        <div class="ssc-form__col ssc-form__col--pick">
            <div class="ssc-field ssc-field--grow">
                <span class="ssc-field__label">
                    <span class="ssc-step">3</span>
                    <?php echo $is_edit ? 'Sous-catégorie' : 'Sous-catégories concernées'; ?>
                    <small id="sscParentCount" class="ssc-pick-count"></small>
                </span>
                <label class="ssc-search ssc-search--sm">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="sscParentSearch" placeholder="Filtrer (ex. t-shirt, chaussure…)" autocomplete="off">
                </label>
                <div class="ssc-pick-list" id="sscParentList" role="group" aria-label="Sous-catégories">
                    <?php foreach ($ssc_parents_by_rayon as $rayon => $rows): ?>
                    <div class="ssc-pick-group">
                        <?php if ($rayon !== ''): ?><p class="ssc-pick-group__title"><?php echo htmlspecialchars((string) $rayon, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                        <div class="ssc-pick-group__items">
                            <?php foreach ($rows as $p):
                                $pid = (int) ($p['id'] ?? 0);
                                if ($pid <= 0) {
                                    continue;
                                }
                                $p_nom = (string) ($p['nom'] ?? '');
                                $checked = $edit_parent === $pid;
                            ?>
                            <label class="ssc-pick<?php echo $checked ? ' is-checked' : ''; ?>"
                                data-search="<?php echo htmlspecialchars($p_nom . ' ' . $rayon, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($is_edit): ?>
                                <input type="radio" name="ssc_parent_id" value="<?php echo $pid; ?>" required<?php echo $checked ? ' checked' : ''; ?><?php echo $dis; ?>>
                                <?php else: ?>
                                <input type="checkbox" name="ssc_parent_ids[]" value="<?php echo $pid; ?>"<?php echo $checked ? ' checked' : ''; ?><?php echo $dis; ?>>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($p_nom, ENT_QUOTES, 'UTF-8'); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="ssc-form__footer">
        <?php if ($is_edit): ?>
            <a href="sous-sous-categories-catalogue.php" class="ssc-btn ssc-btn--ghost">Annuler</a>
        <?php else: ?>
            <button type="button" class="ssc-btn ssc-btn--ghost" data-sa-cat-close-modal>Annuler</button>
        <?php endif; ?>
        <button type="submit" name="<?php echo $is_edit ? 'update_ssc' : 'create_ssc'; ?>" value="1" class="ssc-btn ssc-btn--primary"<?php echo $dis; ?>>
            <i class="fas fa-check" aria-hidden="true"></i> <?php echo $is_edit ? 'Enregistrer' : 'Ajouter'; ?>
        </button>
    </div>
</form>
