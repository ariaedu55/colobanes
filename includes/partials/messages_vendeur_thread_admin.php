<?php
/**
 * Fil de discussion vendeur (admin) — design marketplace (aligné client)
 *
 * Variables : $active_conv, $messages, $conv_id, $messages_page_url, $messages_form_action,
 * $pending_negociations
 */
if (!function_exists('app_url')) {
    require_once __DIR__ . '/../../includes/app_base.php';
}

$client_nom = trim((string) ($active_conv['client_prenom'] ?? '') . ' ' . (string) ($active_conv['client_nom'] ?? ''));
$produit_id_conv = (int) ($active_conv['produit_id'] ?? 0);
$produit_nom_conv = trim((string) ($active_conv['produit_nom'] ?? ''));
$produit_img_conv = trim((string) ($active_conv['produit_image'] ?? ''));
$produit_url = $produit_id_conv > 0 ? app_url('/produit.php?id=' . $produit_id_conv) : '';
$prix_conv = conversation_vendeur_produit_prix_affiche([
    'prix' => $active_conv['produit_prix'] ?? 0,
    'prix_promotion' => $active_conv['produit_prix_promotion'] ?? null,
]);
$messages_back_url = $messages_page_url;
$activity_label = messages_vendeur_chat_presence_label($active_conv ?? [], $messages ?? [], 'vendeur');
$presence_online = ($activity_label === 'En ligne');
$client_initial = mb_strtoupper(mb_substr($client_nom !== '' ? $client_nom : 'C', 0, 1));
$messages_compose_redirect = $messages_page_url . '?conv=' . (int) $conv_id;
$pending_negociations = isset($pending_negociations) && is_array($pending_negociations) ? $pending_negociations : [];
$messages_admin_csrf = messages_vendeur_admin_csrf_token();
$member_since = messages_vendeur_member_since_label($active_conv['client_date_creation'] ?? '');
$has_header_product = ($produit_id_conv > 0 && $produit_url !== '');
?>
<section class="msg-chat msg-chat--admin<?php echo !empty($pending_negociations) ? ' msg-chat--has-offer-actions' : ''; ?>" aria-label="Fil de discussion client">
    <header class="msg-chat__header<?php echo $has_header_product ? ' msg-chat__header--combo' : ''; ?>">
        <a href="<?php echo htmlspecialchars($messages_back_url, ENT_QUOTES, 'UTF-8'); ?>" class="msg-chat__back" aria-label="Retour aux conversations">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
        </a>

        <?php if ($has_header_product): ?>
        <div class="msg-chat__header-seller">
            <div class="msg-chat__seller-avatar" aria-hidden="true">
                <span class="msg-chat__seller-initial"><?php echo htmlspecialchars($client_initial, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="msg-chat__header-main">
                <h2 class="msg-chat__title"><?php echo htmlspecialchars($client_nom !== '' ? $client_nom : 'Client', ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="msg-chat__subtitle<?php echo $presence_online ? ' msg-chat__subtitle--online' : ''; ?>"><?php echo htmlspecialchars($activity_label, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <?php
        $produit_header_label = 'Voir l\'annonce : ' . $produit_nom_conv;
        if ($prix_conv > 0) {
            $produit_header_label .= ' — ' . number_format($prix_conv, 0, ',', ' ') . ' FCFA';
        }
        ?>
        <a href="<?php echo htmlspecialchars($produit_url, ENT_QUOTES, 'UTF-8'); ?>" class="msg-chat__header-product" target="_blank" rel="noopener" aria-label="<?php echo htmlspecialchars($produit_header_label, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($produit_header_label, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="msg-chat__header-product-thumb">
                <?php if ($produit_img_conv !== ''): ?>
                <img src="<?php echo htmlspecialchars(upload_image_url($produit_img_conv, 'sm'), ENT_QUOTES, 'UTF-8'); ?>" alt="" width="46" height="46">
                <?php else: ?>
                <span class="msg-chat__product-thumb-fallback"><i class="fas fa-box" aria-hidden="true"></i></span>
                <?php endif; ?>
            </span>
            <span class="msg-chat__header-product-copy">
                <span class="msg-chat__header-product-name"><?php echo htmlspecialchars($produit_nom_conv, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if ($prix_conv > 0): ?>
                <span class="msg-chat__header-product-price"><?php echo number_format($prix_conv, 0, ',', ' '); ?> FCFA</span>
                <?php endif; ?>
            </span>
        </a>
        <?php else: ?>
        <div class="msg-chat__seller-avatar" aria-hidden="true">
            <span class="msg-chat__seller-initial"><?php echo htmlspecialchars($client_initial, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="msg-chat__header-main">
            <h2 class="msg-chat__title"><?php echo htmlspecialchars($client_nom !== '' ? $client_nom : 'Client', ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="msg-chat__subtitle<?php echo $presence_online ? ' msg-chat__subtitle--online' : ''; ?>"><?php echo htmlspecialchars($activity_label, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <?php endif; ?>
    </header>

    <div class="msg-chat__scroll" id="msgThreadScroll">
        <div class="msg-chat__thread">
        <?php if ($member_since !== ''): ?>
        <div class="msg-chat__seller-chip" aria-label="Informations client">
            <span class="msg-chat__seller-chip-item">
                <i class="fas fa-calendar-check" aria-hidden="true"></i>
                <?php echo htmlspecialchars($member_since, ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>
        <?php endif; ?>

        <?php
        $last_date_key = '';
        $msg_render_new_anim = false;
        $msg_render_data_attrs = true;
        include __DIR__ . '/messages_vendeur_thread_messages_admin.php';
        ?>
        <div id="msgThreadScrollEnd" class="msg-chat__scroll-anchor" aria-hidden="true"></div>
        </div>
    </div>

    <div class="msg-chat__footer">
        <?php
        $msg_negociation_actions_part = 'bar';
        foreach ($pending_negociations as $neg) {
            include __DIR__ . '/messages_vendeur_negociation_actions.php';
        }
        unset($msg_negociation_actions_part);
        ?>

        <form method="POST" action="<?php echo htmlspecialchars($messages_form_action, ENT_QUOTES, 'UTF-8'); ?>" class="msg-chat__compose" id="msgChatComposeForm" novalidate>
            <input type="hidden" name="action" value="send">
            <input type="hidden" name="conversation_id" value="<?php echo (int) $conv_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($messages_admin_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($messages_compose_redirect, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="msg-chat__attach" id="msgChatAttachBtn" aria-label="Pièce jointe (bientôt disponible)" disabled>
                <i class="fas fa-plus" aria-hidden="true"></i>
            </button>
            <label class="visually-hidden" for="msgContenu">Votre message</label>
            <textarea name="contenu" id="msgContenu" class="msg-chat__input" rows="1" maxlength="4000"
                placeholder="Écrivez votre message" autocomplete="off" aria-label="Écrivez votre message"></textarea>
            <button type="submit" class="msg-chat__send" id="msgChatSendBtn" aria-label="Envoyer" disabled>
                <i class="fas fa-paper-plane" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</section>
<?php
/* Overlays hors du footer (évite overflow:hidden / stacking du chat) */
$msg_negociation_actions_part = 'overlay';
foreach ($pending_negociations as $neg) {
    include __DIR__ . '/messages_vendeur_negociation_actions.php';
}
unset($msg_negociation_actions_part);
?>
