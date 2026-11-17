<?php
require_once __DIR__ . '/../includes/dons_bootstrap.php';

dons_expirer_reservations();
$id = (int) ($_GET['id'] ?? 0);
$don = dons_get($id);
$est_auteur = $don && $dons_uid > 0 && (int) $don['user_id'] === $dons_uid;

if (!$don || (!$est_auteur && !dons_est_public($don))) {
    http_response_code(404);
    $dons_title = 'Don introuvable';
    require __DIR__ . '/../includes/partials/dons_head.php';
    ?>
    <div class="dn-empty">
        <span class="dn-empty__icon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></span>
        <h1>Ce don n’est plus disponible</h1>
        <p>Il a peut-être déjà été donné ou retiré par son auteur.</p>
        <a href="/dons/" class="dn-btn dn-btn--primary">Voir les autres dons</a>
    </div>
    <?php
    require __DIR__ . '/../includes/partials/dons_foot.php';
    exit;
}

$demande = $dons_uid > 0 && !$est_auteur ? dons_demande_get($id, $dons_uid) : null;
$conv_id = 0;
if ($demande) {
    $conv_id = dons_conversation_get_or_create($id, $dons_uid, (int) $don['user_id']);
}
global $db;
$st = $db->prepare("SELECT COUNT(*) FROM dons_demandes dd JOIN dons d ON d.id = dd.don_id WHERE d.user_id = :u AND dd.statut = 'remise'");
$st->execute(['u' => $don['user_id']]);
$nb_remis = (int) $st->fetchColumn();

$images = $don['images'] ?: [['id' => 0, 'chemin' => '']];
$dons_title = $don['titre'];
$dons_description = mb_strimwidth(trim((string) $don['description']), 0, 155, '…', 'UTF-8');
require __DIR__ . '/../includes/partials/dons_head.php';
?>

<div class="dn-crumbs" role="navigation" aria-label="Fil d’Ariane">
    <a href="/dons/">Dons</a>
    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
    <?php if (!empty($don['categorie_nom'])): ?>
        <a href="/dons/?categorie=<?php echo (int) $don['categorie_id']; ?>"><?php echo dons_e($don['categorie_nom']); ?></a>
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
    <?php endif; ?>
    <span aria-current="page"><?php echo dons_e(mb_strimwidth($don['titre'], 0, 40, '…', 'UTF-8')); ?></span>
</div>

<?php if ($est_auteur && !dons_est_public($don)): ?>
    <div class="dn-alert dn-alert--info"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i>
        Seul vous voyez cette annonce : statut « <?php echo dons_e(dons_statut_label($don['statut'])); ?> »<?php echo !empty($don['motif_moderation']) ? ' — ' . dons_e($don['motif_moderation']) : ''; ?>.</div>
<?php endif; ?>

<div class="dn-detail">
    <section class="dn-gallery" aria-label="Photos">
        <div class="dn-gallery__main">
            <img id="dnMainImg" src="<?php echo dons_e(dons_img($images[0]['chemin'], 'original')); ?>" alt="<?php echo dons_e($don['titre']); ?>">
            <span class="dn-card__free">Gratuit</span>
        </div>
        <?php if (count($images) > 1): ?>
            <div class="dn-gallery__thumbs">
                <?php foreach ($images as $i => $im): ?>
                    <button type="button" class="dn-thumb<?php echo $i === 0 ? ' is-active' : ''; ?>" data-src="<?php echo dons_e(dons_img($im['chemin'], 'original')); ?>" aria-label="Photo <?php echo $i + 1; ?>">
                        <img src="<?php echo dons_e(dons_img($im['chemin'], 'sm')); ?>" alt="" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="dn-info">
        <div class="dn-info__top">
            <?php echo dons_statut_pill($don); ?>
            <span class="dn-info__date"><i class="fa-regular fa-clock" aria-hidden="true"></i> Publié <?php echo dons_e(dons_temps_relatif($don['created_at'])); ?></span>
        </div>
        <h1 class="dn-info__title"><?php echo dons_e($don['titre']); ?></h1>
        <p class="dn-info__price"><strong>Gratuit</strong><span>0 FCFA</span></p>

        <dl class="dn-facts">
            <div><dt>État</dt><dd><?php echo dons_e(dons_etats()[$don['etat']] ?? ''); ?></dd></div>
            <div><dt>Quantité</dt><dd><?php echo (int) $don['quantite_disponible']; ?> sur <?php echo (int) $don['quantite_initiale']; ?></dd></div>
            <div><dt>Catégorie</dt><dd><?php echo dons_e($don['categorie_nom'] ?? '—'); ?></dd></div>
            <div><dt>Lieu</dt><dd><?php echo dons_e(dons_lieu($don)); ?></dd></div>
        </dl>

        <div class="dn-block">
            <h2>Description</h2>
            <p class="dn-prose"><?php echo nl2br(dons_e($don['description'])); ?></p>
        </div>
        <?php if (trim((string) $don['modalites']) !== ''): ?>
            <div class="dn-block">
                <h2>Récupération</h2>
                <p class="dn-prose"><?php echo nl2br(dons_e($don['modalites'])); ?></p>
            </div>
        <?php endif; ?>

        <div class="dn-donor">
            <span class="dn-avatar" aria-hidden="true"><?php echo dons_e(mb_strtoupper(mb_substr(dons_user_prenom($don, 'donateur_'), 0, 1, 'UTF-8'), 'UTF-8')); ?></span>
            <div>
                <p class="dn-donor__name"><?php echo dons_e(dons_user_prenom($don, 'donateur_')); ?></p>
                <p class="dn-donor__meta">Membre depuis <?php echo dons_e(date('m/Y', strtotime((string) ($don['donateur_depuis'] ?? 'now')))); ?> · <?php echo $nb_remis; ?> don<?php echo $nb_remis > 1 ? 's' : ''; ?> remis</p>
            </div>
        </div>
    </section>

    <aside class="dn-action" id="interet">
        <?php if ($est_auteur): ?>
            <h2 class="dn-action__title">C’est votre annonce</h2>
            <p class="dn-action__text"><?php echo (int) $don['nb_demandes']; ?> demande<?php echo $don['nb_demandes'] > 1 ? 's' : ''; ?> en attente · <?php echo (int) $don['nb_reservees']; ?> réservation<?php echo $don['nb_reservees'] > 1 ? 's' : ''; ?></p>
            <a href="/dons/mes-dons.php#don-<?php echo (int) $don['id']; ?>" class="dn-btn dn-btn--primary dn-btn--block">Gérer les demandes</a>
        <?php elseif ($dons_uid <= 0): ?>
            <h2 class="dn-action__title">Cet objet vous intéresse ?</h2>
            <p class="dn-action__text">Connectez-vous pour écrire au donateur. Vos coordonnées restent privées.</p>
            <a href="<?php echo dons_e(auth_choix_connexion_url(app_url('/dons/don.php?id=' . (int) $don['id']))); ?>" class="dn-btn dn-btn--primary dn-btn--block">Se connecter</a>
        <?php elseif ($demande && in_array($demande['statut'], ['en_attente', 'acceptee', 'remise'], true)): ?>
            <h2 class="dn-action__title"><?php echo dons_e(dons_demande_label($demande['statut'])); ?></h2>
            <p class="dn-action__text">
                <?php if ($demande['statut'] === 'en_attente'): ?>Votre demande a été envoyée. Le donateur vous répondra dans la messagerie.
                <?php elseif ($demande['statut'] === 'acceptee'): ?>L’objet vous est réservé jusqu’au <?php echo dons_e(date('d/m à H\hi', strtotime((string) $demande['expires_at']))); ?>. Convenez du rendez-vous.
                <?php else: ?>Vous avez récupéré cet objet. Merci !<?php endif; ?>
            </p>
            <a href="/dons/conversation.php?id=<?php echo $conv_id; ?>" class="dn-btn dn-btn--primary dn-btn--block"><i class="fa-solid fa-comments" aria-hidden="true"></i> Ouvrir la conversation</a>
            <?php if ($demande['statut'] !== 'remise'): ?>
                <form method="post" action="/dons/action.php" data-confirm="Annuler votre demande ?">
                    <?php echo site_csrf_field(); ?>
                    <input type="hidden" name="action" value="annuler">
                    <input type="hidden" name="don_id" value="<?php echo (int) $don['id']; ?>">
                    <button type="submit" class="dn-btn dn-btn--ghost dn-btn--block">Annuler ma demande</button>
                </form>
            <?php endif; ?>
        <?php elseif ($demande && $demande['statut'] === 'refusee'): ?>
            <h2 class="dn-action__title">Demande non retenue</h2>
            <p class="dn-action__text">Le donateur a choisi une autre personne. D’autres dons vous attendent !</p>
            <a href="/dons/" class="dn-btn dn-btn--ghost dn-btn--block">Voir les autres dons</a>
        <?php elseif ($don['statut'] !== 'disponible'): ?>
            <h2 class="dn-action__title">Déjà réservé</h2>
            <p class="dn-action__text">Tous les exemplaires sont réservés. S’il se libère, il réapparaîtra comme disponible.</p>
        <?php elseif (!dons_module_actif()): ?>
            <h2 class="dn-action__title">Demandes en pause</h2>
            <p class="dn-action__text">Les nouvelles demandes sont momentanément suspendues.</p>
        <?php else: ?>
            <h2 class="dn-action__title">Cet objet vous intéresse ?</h2>
            <form method="post" action="/dons/action.php" class="dn-form">
                <?php echo site_csrf_field(); ?>
                <input type="hidden" name="action" value="demander">
                <input type="hidden" name="don_id" value="<?php echo (int) $don['id']; ?>">
                <label class="dn-field">
                    <span>Message au donateur <em>(facultatif)</em></span>
                    <textarea name="message" rows="3" maxlength="500" placeholder="Bonjour, je suis intéressé(e). Quand puis-je passer le récupérer ?"></textarea>
                </label>
                <button type="submit" class="dn-btn dn-btn--primary dn-btn--block"><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> Je suis intéressé</button>
            </form>
        <?php endif; ?>

        <ul class="dn-safety">
            <li><i class="fa-solid fa-shield-heart" aria-hidden="true"></i>Un don est toujours gratuit : ne payez jamais.</li>
            <li><i class="fa-solid fa-user-lock" aria-hidden="true"></i>Échangez via la messagerie, sans partager vos coordonnées.</li>
            <li><i class="fa-solid fa-people-group" aria-hidden="true"></i>Rencontrez-vous dans un lieu public.</li>
        </ul>

        <?php if ($dons_uid > 0 && !$est_auteur): ?>
            <details class="dn-report">
                <summary><i class="fa-regular fa-flag" aria-hidden="true"></i> Signaler cette annonce</summary>
                <form method="post" action="/dons/action.php" class="dn-form">
                    <?php echo site_csrf_field(); ?>
                    <input type="hidden" name="action" value="signaler">
                    <input type="hidden" name="don_id" value="<?php echo (int) $don['id']; ?>">
                    <label class="dn-field"><span>Motif</span>
                        <select name="motif" required>
                            <option value="">Choisir…</option>
                            <?php foreach (dons_motifs_signalement() as $k => $l): ?><option value="<?php echo dons_e($k); ?>"><?php echo dons_e($l); ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label class="dn-field"><span>Précisions <em>(facultatif)</em></span>
                        <textarea name="details" rows="2" maxlength="500"></textarea>
                    </label>
                    <button type="submit" class="dn-btn dn-btn--ghost dn-btn--block">Envoyer le signalement</button>
                </form>
            </details>
        <?php endif; ?>
    </aside>
</div>

<?php require __DIR__ . '/../includes/partials/dons_foot.php'; ?>
