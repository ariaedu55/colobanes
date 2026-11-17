<?php
require_once __DIR__ . '/../includes/dons_bootstrap.php';
$uid = dons_require_login();

$edit_id = (int) ($_GET['id'] ?? $_POST['don_id'] ?? 0);
$existing = $edit_id > 0 ? dons_get($edit_id) : null;
if ($edit_id > 0 && (!$existing || (int) $existing['user_id'] !== $uid || in_array($existing['statut'], ['donne', 'retire'], true))) {
    dons_flash('err', 'Cette annonce ne peut pas être modifiée.');
    dons_redirect('/dons/mes-dons.php');
}

$errors = [];
$v = $existing ? [
    'titre' => $existing['titre'], 'description' => $existing['description'], 'categorie_id' => $existing['categorie_id'],
    'etat' => $existing['etat'], 'quantite' => $existing['quantite_initiale'], 'region' => $existing['region'],
    'zone' => $existing['zone'], 'modalites' => $existing['modalites'],
] : ['titre' => '', 'description' => '', 'categorie_id' => 0, 'etat' => '', 'quantite' => 1, 'region' => '', 'zone' => '', 'modalites' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $csrf = site_csrf_require_post();
    if (!$csrf['ok']) {
        $errors[] = $csrf['message'];
    } elseif (!$existing && !dons_module_actif()) {
        $errors[] = 'Les nouvelles publications sont momentanément suspendues.';
    } else {
        $r = dons_save($uid, $_POST, $_FILES, $existing ? $edit_id : 0);
        if ($r['ok']) {
            $don = dons_get((int) $r['id']);
            dons_flash('ok', $existing ? 'Annonce mise à jour.' : (($don['statut'] ?? '') === 'en_moderation'
                ? 'Merci ! Votre annonce sera visible dès sa validation par l’équipe.'
                : 'Merci ! Votre don est en ligne.'));
            dons_redirect('/dons/don.php?id=' . (int) $r['id']);
        }
        $errors = $r['errors'];
    }
    foreach (array_keys($v) as $k) {
        if (isset($_POST[$k])) {
            $v[$k] = (string) $_POST[$k];
        }
    }
}

$dons_title = $existing ? 'Modifier mon don' : 'Offrir un objet';
$dons_tab = 'offrir';
require __DIR__ . '/../includes/partials/dons_head.php';
?>

<div class="dn-pagehead">
    <h1><?php echo $existing ? 'Modifier mon don' : 'Offrir un objet'; ?></h1>
    <p>Publication gratuite, sans commission. L’objet sera proposé à <strong>0 FCFA</strong>.</p>
</div>

<?php if ($errors): ?>
    <div class="dn-alert dn-alert--err" role="alert">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <ul><?php foreach ($errors as $e): ?><li><?php echo dons_e($e); ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="dn-publish" id="dnPublish" novalidate>
<?php echo function_exists('protection_turnstile_widget') ? protection_turnstile_widget() : ''; ?>
    <?php echo site_csrf_field(); ?>
    <?php if ($existing): ?><input type="hidden" name="don_id" value="<?php echo (int) $existing['id']; ?>"><?php endif; ?>

    <div class="dn-publish__main">
        <section class="dn-panel">
            <h2 class="dn-panel__title"><span>1</span> Photos</h2>
            <p class="dn-panel__hint">Jusqu’à 6 photos, JPG, PNG ou WEBP. La première sera la couverture.</p>
            <div class="dn-photos" id="dnPhotos">
                <?php foreach ($existing['images'] ?? [] as $im): ?>
                    <label class="dn-photo dn-photo--existing">
                        <img src="<?php echo dons_e(dons_img($im['chemin'], 'sm')); ?>" alt="">
                        <input type="checkbox" name="supprimer_images[]" value="<?php echo (int) $im['id']; ?>">
                        <span class="dn-photo__remove" title="Retirer cette photo"><i class="fa-solid fa-trash" aria-hidden="true"></i></span>
                    </label>
                <?php endforeach; ?>
                <label class="dn-photo dn-photo--add" id="dnPhotoAdd">
                    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple id="dnPhotoInput">
                    <i class="fa-solid fa-camera" aria-hidden="true"></i>
                    <span>Ajouter</span>
                </label>
            </div>
        </section>

        <section class="dn-panel">
            <h2 class="dn-panel__title"><span>2</span> L’objet</h2>
            <label class="dn-field">
                <span>Titre</span>
                <input type="text" name="titre" maxlength="120" required value="<?php echo dons_e($v['titre']); ?>" placeholder="Ex. Table basse en bois">
            </label>
            <div class="dn-field-row">
                <label class="dn-field">
                    <span>Catégorie</span>
                    <select name="categorie_id" required>
                        <option value="">Choisir…</option>
                        <?php foreach (dons_categories() as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>"<?php echo (int) $v['categorie_id'] === (int) $c['id'] ? ' selected' : ''; ?>><?php echo dons_e($c['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="dn-field dn-field--qty">
                    <span>Quantité</span>
                    <input type="number" name="quantite" min="1" max="999" required value="<?php echo (int) $v['quantite']; ?>">
                </label>
            </div>
            <fieldset class="dn-field">
                <legend>État</legend>
                <div class="dn-radios">
                    <?php foreach (dons_etats() as $k => $l): ?>
                        <label class="dn-radio">
                            <input type="radio" name="etat" value="<?php echo dons_e($k); ?>"<?php echo $v['etat'] === $k ? ' checked' : ''; ?> required>
                            <span><?php echo dons_e($l); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <label class="dn-field">
                <span>Description</span>
                <textarea name="description" rows="5" maxlength="3000" required placeholder="Dimensions, couleur, défauts éventuels…"><?php echo dons_e($v['description']); ?></textarea>
            </label>
        </section>

        <section class="dn-panel">
            <h2 class="dn-panel__title"><span>3</span> Récupération</h2>
            <div class="dn-field-row">
                <label class="dn-field">
                    <span>Région</span>
                    <select name="region" required><?php echo senegal_regions_options_html($v['region'], true, 'Choisir…'); ?></select>
                </label>
                <label class="dn-field">
                    <span>Quartier / zone <em>(approximatif)</em></span>
                    <input type="text" name="zone" maxlength="120" value="<?php echo dons_e($v['zone']); ?>" placeholder="Ex. Mermoz">
                </label>
            </div>
            <label class="dn-field">
                <span>Modalités <em>(facultatif)</em></span>
                <textarea name="modalites" rows="2" maxlength="500" placeholder="Ex. À récupérer en semaine après 18 h, à porter à deux."><?php echo dons_e($v['modalites']); ?></textarea>
            </label>
            <p class="dn-panel__hint"><i class="fa-solid fa-user-lock" aria-hidden="true"></i> N’indiquez ni adresse exacte, ni téléphone : les intéressés vous écriront par la messagerie.</p>
        </section>
    </div>

    <aside class="dn-publish__side">
        <div class="dn-panel dn-panel--sticky">
            <p class="dn-info__price"><strong>Gratuit</strong><span>0 FCFA</span></p>
            <ul class="dn-checks">
                <li><i class="fa-solid fa-check" aria-hidden="true"></i>Aucun frais ni commission</li>
                <li><i class="fa-solid fa-check" aria-hidden="true"></i>Vous choisissez à qui donner</li>
                <li><i class="fa-solid fa-check" aria-hidden="true"></i>Vos coordonnées restent privées</li>
            </ul>
            <button type="submit" class="dn-btn dn-btn--primary dn-btn--block dn-btn--lg">
                <?php echo $existing ? 'Enregistrer' : 'Publier mon don'; ?>
            </button>
            <a href="<?php echo $existing ? '/dons/mes-dons.php' : '/dons/'; ?>" class="dn-btn dn-btn--ghost dn-btn--block">Annuler</a>
        </div>
    </aside>
</form>

<?php require __DIR__ . '/../includes/partials/dons_foot.php'; ?>
