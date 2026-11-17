<?php
/**
 * Boucle messages admin (fil complet ou fragment poll)
 *
 * Variables : $messages, $last_date_key (opt, ref),
 * $msg_render_new_anim (bool), $msg_render_data_attrs (bool)
 */
if (!isset($msg_render_new_anim)) {
    $msg_render_new_anim = false;
}
if (!isset($msg_render_data_attrs)) {
    $msg_render_data_attrs = false;
}
if (!isset($last_date_key)) {
    $last_date_key = '';
}
$msg_viewer_role = 'vendeur';

foreach ($messages as $m):
    $from_vendeur = ((string) ($m['expediteur_type'] ?? '') === 'vendeur');
    $type_msg = (string) ($m['type_message'] ?? 'texte');
    $date_m = (string) ($m['date_creation'] ?? '');
    $msg_id = (int) ($m['id'] ?? 0);
    $date_key = $date_m !== '' ? date('Y-m-d', strtotime($date_m)) : '';
    if ($date_key !== '' && $date_key !== $last_date_key):
        $last_date_key = $date_key;
?>
<div class="msg-chat__date" role="separator"<?php echo $msg_render_data_attrs ? ' data-msg-date-key="' . htmlspecialchars($date_key, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
    <span><?php echo htmlspecialchars(messages_vendeur_date_separator_label($date_m), ENT_QUOTES, 'UTF-8'); ?></span>
</div>
<?php endif; ?>

<?php if ($type_msg === 'systeme'):
    if (messages_vendeur_is_negociation_intro($m['contenu'] ?? '')):
        // Message d'ouverture : encart centré (souvent le seul message de la conversation)
        $heure_intro = $date_m !== '' ? date('H:i', strtotime($date_m)) : '';
        $intro_txt = preg_replace('/^Bonjour,\s*/iu', '', trim((string) ($m['contenu'] ?? '')));
        $intro_txt = mb_strtoupper(mb_substr($intro_txt, 0, 1)) . mb_substr($intro_txt, 1);
?>
<div class="msg-chat__intro"<?php echo $msg_render_data_attrs && $msg_id > 0 ? ' data-msg-id="' . $msg_id . '"' : ''; ?>>
    <span class="msg-chat__intro-icon" aria-hidden="true"><i class="fas fa-handshake"></i></span>
    <strong class="msg-chat__intro-title">Nouvelle demande de négociation</strong>
    <p class="msg-chat__intro-text"><?php echo htmlspecialchars($intro_txt, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($heure_intro !== ''): ?>
    <time class="msg-chat__intro-time" datetime="<?php echo htmlspecialchars($date_m, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($heure_intro, ENT_QUOTES, 'UTF-8'); ?></time>
    <?php endif; ?>
</div>
<?php
        continue;
    endif;
    $contact_warning = messages_vendeur_contact_warning_data($m);
    $notice = messages_vendeur_admin_system_notice($m['contenu'] ?? '');
    $heure_notice = $date_m !== '' ? date('H:i', strtotime($date_m)) : '';
    $new_cls = $msg_render_new_anim ? ' msg-chat__notice--new-in' : '';
    $unread_cls = messages_vendeur_message_unread_for_viewer($m, $msg_viewer_role) ? ' msg-chat__notice--unread' : '';
    $unread_attr = messages_vendeur_unread_data_attr($m, $msg_viewer_role, $msg_id);
    if ($contact_warning !== null):
?>
<div class="msg-chat__notice msg-chat__notice--contact<?php echo $new_cls . $unread_cls; ?>" role="status"<?php echo $msg_render_data_attrs && $msg_id > 0 ? ' data-msg-id="' . $msg_id . '"' : ''; ?><?php echo $unread_attr; ?>>
    <div class="msg-chat__notice-copy msg-chat__notice-copy--full">
        <strong class="msg-chat__notice-title"><?php echo htmlspecialchars($contact_warning['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
        <p class="msg-chat__notice-body"><?php echo htmlspecialchars($contact_warning['body'], ENT_QUOTES, 'UTF-8'); ?></p>
        <a class="msg-chat__notice-link" href="<?php echo htmlspecialchars($contact_warning['learn_more'], ENT_QUOTES, 'UTF-8'); ?>">En savoir plus</a>
        <?php if ($heure_notice !== ''): ?>
        <div class="msg-chat__notice-time-row">
            <time class="msg-chat__inline-time" datetime="<?php echo htmlspecialchars($date_m, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($heure_notice, ENT_QUOTES, 'UTF-8'); ?></time>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($notice !== null):
    $is_accept_notice = (($notice['kind'] ?? '') === 'accept');
    $accept_sticker = $is_accept_notice ? messages_vendeur_accept_sticker_for_message($msg_id) : '';
?>
<div class="msg-chat__notice msg-chat__notice--orange msg-chat__notice--<?php echo htmlspecialchars($notice['kind'], ENT_QUOTES, 'UTF-8'); ?><?php echo $is_accept_notice ? ' msg-chat__notice--celebrate' : ''; ?><?php echo $new_cls . $unread_cls; ?>" role="status"<?php echo $msg_render_data_attrs && $msg_id > 0 ? ' data-msg-id="' . $msg_id . '"' : ''; ?><?php echo $unread_attr; ?>>
    <?php if (!$is_accept_notice): ?>
    <div class="msg-chat__notice-icon" aria-hidden="true">
        <i class="fas <?php echo htmlspecialchars($notice['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
    </div>
    <?php endif; ?>
    <div class="msg-chat__notice-copy<?php echo $is_accept_notice ? ' msg-chat__notice-copy--full' : ''; ?>">
        <?php if ($is_accept_notice): ?>
        <div class="msg-chat__notice-title-row">
            <strong class="msg-chat__notice-title"><?php echo htmlspecialchars($notice['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
            <span class="msg-chat__notice-sticker" aria-hidden="true"><?php echo $accept_sticker; ?></span>
        </div>
        <?php else: ?>
        <strong class="msg-chat__notice-title"><?php echo htmlspecialchars($notice['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
        <?php endif; ?>
        <?php if ($notice['body'] !== ''): ?>
        <p class="msg-chat__notice-body<?php echo $is_accept_notice ? ' msg-chat__notice-body--price' : ''; ?>"><?php echo htmlspecialchars($notice['body'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($notice['note'] !== ''): ?>
        <p class="msg-chat__notice-note"><?php echo htmlspecialchars($notice['note'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($heure_notice !== ''): ?>
        <div class="msg-chat__notice-time-row">
            <time class="msg-chat__inline-time" datetime="<?php echo htmlspecialchars($date_m, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($heure_notice, ENT_QUOTES, 'UTF-8'); ?></time>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="msg-chat__system"<?php echo $msg_render_data_attrs && $msg_id > 0 ? ' data-msg-id="' . $msg_id . '"' : ''; ?>>
    <p><?php echo nl2br(htmlspecialchars((string) ($m['contenu'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
    <?php if ($heure_notice !== ''): ?>
    <div class="msg-chat__notice-time-row">
        <time class="msg-chat__inline-time" datetime="<?php echo htmlspecialchars($date_m, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($heure_notice, ENT_QUOTES, 'UTF-8'); ?></time>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php elseif ($type_msg === 'offre_prix'):
    $card = messages_vendeur_offre_card_data($m, !$from_vendeur, 'vendeur');
    $heure_offre = $date_m !== '' ? date('H:i', strtotime($date_m)) : '';
    $new_cls = $msg_render_new_anim ? ' msg-chat__offer-block--new-in' : '';
    $unread_cls = messages_vendeur_message_unread_for_viewer($m, $msg_viewer_role) ? ' msg-chat__offer-block--unread' : '';
    $unread_attr = messages_vendeur_unread_data_attr($m, $msg_viewer_role, $msg_id);
?>
<div class="msg-chat__offer-block msg-chat__offer-block--<?php echo $from_vendeur ? 'out' : 'in'; ?><?php echo $new_cls . $unread_cls; ?>"<?php echo $msg_render_data_attrs && $msg_id > 0 ? ' data-msg-id="' . $msg_id . '"' : ''; ?><?php echo $unread_attr; ?>>
    <div class="msg-chat__offer-card">
        <h4 class="msg-chat__offer-card-title"><?php echo htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
        <p class="msg-chat__offer-card-body"><?php echo htmlspecialchars($card['body'], ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="msg-chat__offer-card-foot">
            <?php if ($card['note'] !== ''): ?>
            <p class="msg-chat__offer-card-note"><?php echo htmlspecialchars($card['note'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($heure_offre !== ''): ?>
            <time class="msg-chat__offer-card-time" datetime="<?php echo htmlspecialchars($date_m, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($heure_offre, ENT_QUOTES, 'UTF-8'); ?></time>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else:
    $heure_m = $date_m !== '' ? date('H:i', strtotime($date_m)) : '';
    $lu = !empty($m['lu_par_client']) && $from_vendeur;
    $row_cls = 'msg-chat__row msg-chat__row--' . ($from_vendeur ? 'out' : 'in');
    if ($msg_render_new_anim) {
        $row_cls .= ' msg-chat__row--new-in';
    }
    if (!$from_vendeur && messages_vendeur_message_unread_for_viewer($m, $msg_viewer_role)) {
        $row_cls .= ' msg-chat__row--unread';
    }
    $bubble_cls = messages_vendeur_is_contact_blocked_attempt($m) ? ' msg-chat__bubble--contact-blocked' : '';
    $unread_attr = messages_vendeur_unread_data_attr($m, $msg_viewer_role, $msg_id);
?>
<div class="<?php echo htmlspecialchars($row_cls, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $msg_render_data_attrs && $msg_id > 0 ? ' data-msg-id="' . $msg_id . '"' : ''; ?><?php echo $unread_attr; ?>>
    <?php if (!$from_vendeur && messages_vendeur_message_unread_for_viewer($m, $msg_viewer_role)): ?>
    <span class="msg-chat__unread-pill msg-chat__unread-pill--above">Nouveau</span>
    <?php endif; ?>
    <div class="msg-chat__bubble<?php echo $bubble_cls; ?>">
        <div class="msg-chat__bubble-text"><?php echo nl2br(htmlspecialchars((string) ($m['contenu'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
        <?php if ($heure_m !== ''): ?>
        <div class="msg-chat__bubble-meta">
            <time datetime="<?php echo htmlspecialchars($date_m, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($heure_m, ENT_QUOTES, 'UTF-8'); ?></time>
            <?php if ($from_vendeur): ?>
                <?php if ($bubble_cls !== ''): ?>
            <span class="msg-chat__fail" aria-label="Non transmis" title="Message non transmis">
                <i class="fas fa-times" aria-hidden="true"></i>
            </span>
                <?php else: ?>
            <span class="msg-chat__ticks<?php echo $lu ? ' msg-chat__ticks--read' : ''; ?>" aria-label="<?php echo $lu ? 'Lu' : 'Envoyé'; ?>">
                <i class="fas fa-check" aria-hidden="true"></i><i class="fas fa-check" aria-hidden="true"></i>
            </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>
