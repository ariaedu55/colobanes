<?php
/**
 * Formulaire genre (création ou édition) — modal
 * Attend : $row_edit_g (array|false), $csrf, $table_ok, $links_ok, $cg_list_all, $prefill_cg_ids
 */
$is_edit = is_array($row_edit_g) && !empty($row_edit_g);
$genre_nom = $is_edit ? (string) ($row_edit_g['nom'] ?? '') : '';
$genre_desc = $is_edit ? (string) ($row_edit_g['description'] ?? '') : '';
$genre_sort = $is_edit ? (int) ($row_edit_g['sort_ordre'] ?? 0) : 0;
$genre_image = $is_edit ? (string) ($row_edit_g['image'] ?? '') : '';
$fields_disabled = !$table_ok;
$rayons_disabled = !$table_ok || !$links_ok;
?>
<form class="sa-cat-form sa-genre-modal-form" method="post" action="genres-catalogue.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($is_edit): ?>
        <input type="hidden" name="g_id" value="<?php echo (int) $row_edit_g['id']; ?>">
    <?php endif; ?>

    <div class="sa-genre-modal-form__grid">
        <div class="sa-genre-modal-form__col sa-genre-modal-form__col--info">
            <div class="sa-genre-info-card">
                <header class="sa-genre-info-card__head">
                    <span class="sa-genre-info-card__head-icon" aria-hidden="true"><i class="fas fa-pen-to-square"></i></span>
                    <div>
                        <h3 class="sa-genre-info-card__title">Informations</h3>
                        <p class="sa-genre-info-card__lead">Libellé affiché aux vendeurs et dans les filtres</p>
                    </div>
                </header>

                <div class="sa-genre-info-card__body">
                    <div class="sa-genre-info-media">
                        <div class="sa-genre-info-media__preview" id="saGenreImagePreview" aria-hidden="true">
                            <?php if ($is_edit && $genre_image !== ''): ?>
                                <img src="/upload/<?php echo htmlspecialchars(str_replace('\\', '/', $genre_image), ENT_QUOTES, 'UTF-8'); ?>" alt="" width="72" height="72" decoding="async">
                            <?php else: ?>
                                <span class="sa-genre-info-media__placeholder"><i class="fas fa-image"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="sa-genre-info-media__upload">
                            <label class="sa-genre-info-upload" for="g_image">
                                <span class="sa-genre-info-upload__btn">
                                    <i class="fas fa-cloud-arrow-up" aria-hidden="true"></i>
                                    <?php echo ($is_edit && $genre_image !== '') ? 'Remplacer l’image' : 'Ajouter une image'; ?>
                                </span>
                            </label>
                            <input type="file" class="sa-genre-info-upload__input" id="g_image" name="image"
                                accept="image/jpeg,image/png,image/gif,image/webp"
                                data-sa-genre-image-input
                                <?php echo $fields_disabled ? 'disabled' : ''; ?>>
                            <p class="sa-genre-info-media__hint">JPEG, PNG, GIF ou WebP · optionnel</p>
                        </div>
                    </div>

                    <div class="sa-genre-info-fields">
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="g_description" value="<?php echo htmlspecialchars($genre_desc, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php endif; ?>

                        <div class="sa-cat-field sa-genre-info-field">
                            <label for="g_nom">Nom du genre <span class="sa-genre-modal-form__req" aria-hidden="true">*</span></label>
                            <input type="text" id="g_nom" name="g_nom" required maxlength="255"
                                class="sa-genre-info-input sa-genre-info-input--title"
                                placeholder="Ex. Huile moteur"
                                value="<?php echo htmlspecialchars($genre_nom, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $fields_disabled ? 'disabled' : ''; ?>>
                        </div>

                        <div class="sa-genre-info-ordre">
                            <label class="sa-genre-info-ordre__label" for="g_sort">
                                <i class="fas fa-sort-numeric-down" aria-hidden="true"></i>
                                Ordre d’affichage
                            </label>
                            <input type="number" id="g_sort" name="g_sort" class="sa-genre-info-ordre__input"
                                value="<?php echo $genre_sort; ?>" min="0" step="1"
                                <?php echo $fields_disabled ? 'disabled' : ''; ?>>
                            <span class="sa-genre-info-ordre__hint">Plus petit = plus haut dans la liste</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($cg_list_all)): ?>
        <div class="sa-genre-modal-form__col sa-genre-modal-form__col--rayons">
            <p class="sa-genre-modal-form__section-label">
                <i class="fas fa-sitemap" aria-hidden="true"></i>
                Rayons <span class="sa-genre-modal-form__req" aria-hidden="true">*</span>
            </p>
            <p class="sa-genre-modal-form__hint">Le genre sera proposé aux vendeurs pour les rayons cochés.</p>

            <div class="sa-genre-rayon-picks" role="group" aria-label="Rayons associés">
                <?php foreach ($cg_list_all as $__cg):
                    $__cid = (int) ($__cg['id'] ?? 0);
                    if ($__cid <= 0) {
                        continue;
                    }
                    $__chk = $is_edit && in_array($__cid, $prefill_cg_ids, true);
                    $__nom = (string) ($__cg['nom'] ?? '');
                ?>
                <label class="sa-genre-rayon-pick<?php echo $__chk ? ' is-checked' : ''; ?>">
                    <input type="checkbox" name="g_categorie_generale_ids[]" value="<?php echo $__cid; ?>"
                        <?php echo $__chk ? 'checked' : ''; ?>
                        <?php echo $rayons_disabled ? 'disabled' : ''; ?>>
                    <span class="sa-genre-rayon-pick__card">
                        <span class="sa-genre-rayon-pick__check" aria-hidden="true"><i class="fas fa-check"></i></span>
                        <span class="sa-genre-rayon-pick__label"><?php echo htmlspecialchars($__nom, ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="sa-genre-modal-form__footer">
        <?php if ($is_edit): ?>
            <button type="submit" name="update_genre" value="1" class="sa-cat-btn sa-cat-btn--primary">
                <i class="fas fa-check" aria-hidden="true"></i> Enregistrer
            </button>
            <a href="genres-catalogue.php" class="sa-cat-btn sa-cat-btn--ghost">Annuler</a>
        <?php else: ?>
            <button type="submit" name="create_genre" value="1" class="sa-cat-btn sa-cat-btn--primary" <?php echo $table_ok ? '' : 'disabled'; ?>>
                <i class="fas fa-plus" aria-hidden="true"></i> Ajouter le genre
            </button>
            <button type="button" class="sa-cat-btn sa-cat-btn--ghost" data-sa-cat-close-modal>Annuler</button>
        <?php endif; ?>
    </div>
</form>
