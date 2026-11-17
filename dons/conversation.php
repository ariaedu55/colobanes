<?php
require_once __DIR__ . '/../includes/dons_bootstrap.php';
$uid = dons_require_login();
dons_expirer_reservations();

$conv = dons_conversation_for_user((int) ($_GET['id'] ?? 0), $uid);
if (!$conv) {
    dons_flash('err', 'Conversation introuvable.');
    dons_redirect('/dons/messages.php');
}
$cid = (int) $conv['id'];
$je_suis_donateur = (int) $conv['donateur_id'] === $uid;
$autre = $je_suis_donateur ? dons_user_prenom($conv, 'demandeur_') : dons_user_prenom($conv, 'donateur_');
$messages = dons_messages($cid);
dons_messages_marquer_lus($cid, $uid);
$dst = (string) ($conv['demande_statut'] ?? '');
$don_ouvert = in_array($conv['don_statut'], ['disponible', 'reserve'], true);

$dons_title = 'Conversation — ' . $conv['titre'];
$dons_tab = 'messages';
require __DIR__ . '/../includes/partials/dons_head.php';
?>

<div class="dn-chat" data-conversation="<?php echo $cid; ?>" data-last="<?php echo $messages ? (int) end($messages)['id'] : 0; ?>">
    <header class="dn-chat__head">
        <a href="/dons/messages.php" class="dn-iconbtn" aria-label="Retour aux messages"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
        <a href="/dons/don.php?id=<?php echo (int) $conv['don_id']; ?>" class="dn-chat__don">
            <img src="<?php echo dons_e(dons_img($conv['image'] ?? null, 'sm')); ?>" alt="">
            <span>
                <strong><?php echo dons_e($autre); ?></strong>
                <small><?php echo dons_e(mb_strimwidth($conv['titre'], 0, 48, '…', 'UTF-8')); ?></small>
            </span>
        </a>
        <span class="dn-pill dn-pill--req-<?php echo dons_e($dst); ?>"><?php echo dons_e(dons_demande_label($dst)); ?></span>
    </header>

    <?php if ($je_suis_donateur && $don_ouvert && in_array($dst, ['en_attente', 'acceptee'], true)): ?>
        <div class="dn-chat__bar">
            <?php if ($dst === 'en_attente'): ?>
                <p>Voulez-vous réserver l’objet pour <?php echo dons_e($autre); ?> ?</p>
                <div class="dn-chat__bar-actions">
                    <form method="post" action="/dons/action.php">
                        <?php echo site_csrf_field(); ?>
                        <input type="hidden" name="action" value="refuser"><input type="hidden" name="demande_id" value="<?php echo (int) $conv['demande_id']; ?>">
                        <input type="hidden" name="retour" value="/dons/conversation.php?id=<?php echo $cid; ?>">
                        <button class="dn-btn dn-btn--ghost dn-btn--sm" type="submit">Refuser</button>
                    </form>
                    <form method="post" action="/dons/action.php">
                        <?php echo site_csrf_field(); ?>
                        <input type="hidden" name="action" value="accepter"><input type="hidden" name="demande_id" value="<?php echo (int) $conv['demande_id']; ?>">
                        <input type="hidden" name="retour" value="/dons/conversation.php?id=<?php echo $cid; ?>">
                        <button class="dn-btn dn-btn--primary dn-btn--sm" type="submit">Lui réserver</button>
                    </form>
                </div>
            <?php else: ?>
                <p>Réservé jusqu’au <?php echo dons_e(date('d/m à H\hi', strtotime((string) $conv['expires_at']))); ?>. L’objet a été remis ?</p>
                <div class="dn-chat__bar-actions">
                    <form method="post" action="/dons/action.php" data-confirm="Libérer la réservation ? L’objet redeviendra disponible.">
                        <?php echo site_csrf_field(); ?>
                        <input type="hidden" name="action" value="refuser"><input type="hidden" name="demande_id" value="<?php echo (int) $conv['demande_id']; ?>">
                        <input type="hidden" name="retour" value="/dons/conversation.php?id=<?php echo $cid; ?>">
                        <button class="dn-btn dn-btn--ghost dn-btn--sm" type="submit">Libérer</button>
                    </form>
                    <form method="post" action="/dons/action.php" data-confirm="Confirmer que l’objet a été remis ?">
                        <?php echo site_csrf_field(); ?>
                        <input type="hidden" name="action" value="donner"><input type="hidden" name="demande_id" value="<?php echo (int) $conv['demande_id']; ?>">
                        <input type="hidden" name="retour" value="/dons/conversation.php?id=<?php echo $cid; ?>">
                        <button class="dn-btn dn-btn--primary dn-btn--sm" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Objet remis</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif (!$je_suis_donateur && $dst === 'acceptee'): ?>
        <div class="dn-chat__bar dn-chat__bar--ok">
            <p><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Réservé pour vous jusqu’au <?php echo dons_e(date('d/m à H\hi', strtotime((string) $conv['expires_at']))); ?>. Fixez le rendez-vous ici.</p>
        </div>
    <?php endif; ?>

    <div class="dn-chat__thread" id="dnThread" aria-live="polite">
        <p class="dn-chat__notice"><i class="fa-solid fa-user-lock" aria-hidden="true"></i> Conversation privée. Ne partagez ni numéro ni adresse exacte : convenez d’un lieu public.</p>
        <?php foreach ($messages as $m): ?>
            <div class="dn-msg<?php echo $m['systeme'] ? ' dn-msg--sys' : ''; ?><?php echo (int) $m['sender_id'] === $uid ? ' dn-msg--mine' : ''; ?>" data-id="<?php echo (int) $m['id']; ?>">
                <div class="dn-msg__bubble"><?php echo nl2br(dons_e($m['contenu'])); ?></div>
                <time><?php echo dons_e(date('d/m H:i', strtotime((string) $m['created_at']))); ?></time>
            </div>
        <?php endforeach; ?>
    </div>

    <form class="dn-chat__compose" id="dnCompose" method="post" action="/dons/action.php">
        <?php echo site_csrf_field(); ?>
        <input type="hidden" name="action" value="message">
        <input type="hidden" name="conversation_id" value="<?php echo $cid; ?>">
        <label class="dn-sr" for="dnMsg">Votre message</label>
        <textarea id="dnMsg" name="contenu" rows="1" maxlength="2000" placeholder="Écrire un message…" required></textarea>
        <button type="submit" class="dn-chat__send" aria-label="Envoyer"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
    </form>
    <p class="dn-chat__error" id="dnChatError" role="alert" hidden></p>

    <details class="dn-report dn-report--chat">
        <summary><i class="fa-regular fa-flag" aria-hidden="true"></i> Signaler cette conversation</summary>
        <form method="post" action="/dons/action.php" class="dn-form">
            <?php echo site_csrf_field(); ?>
            <input type="hidden" name="action" value="signaler">
            <input type="hidden" name="conversation_id" value="<?php echo $cid; ?>">
            <label class="dn-field"><span>Motif</span>
                <select name="motif" required>
                    <option value="">Choisir…</option>
                    <?php foreach (dons_motifs_signalement() as $k => $l): ?><option value="<?php echo dons_e($k); ?>"><?php echo dons_e($l); ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="dn-field"><span>Précisions <em>(facultatif)</em></span><textarea name="details" rows="2" maxlength="500"></textarea></label>
            <button type="submit" class="dn-btn dn-btn--ghost">Envoyer le signalement</button>
        </form>
    </details>
</div>

<?php require __DIR__ . '/../includes/partials/dons_foot.php'; ?>
