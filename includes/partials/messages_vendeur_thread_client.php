<?php
/**
 * Fil de discussion client — design type marketplace (Leboncoin)
 *
 * Variables attendues :
 * $active_conv, $messages, $conv_id, $messages_page_url, $messages_form_action,
 * $show_negocier, $can_negocier, $prix_conv, $produit_id_conv, $produit_url, $conv_statut
 * $has_offre_acceptee_a_payer (bool, optionnel)
 */
if (!function_exists('app_url')) {
    require_once __DIR__ . '/../../includes/app_base.php';
}
if (!function_exists('boutique_adresse_publique')) {
    require_once __DIR__ . '/../boutique_vendeur_display.php';
}
if (!function_exists('boutique_vitrine_entry_href')) {
    require_once __DIR__ . '/../marketplace_helpers.php';
}

$boutique_nom = trim((string) ($active_conv['vendeur_boutique_nom'] ?? 'Boutique'));
$boutique_slug = trim((string) ($active_conv['vendeur_boutique_slug'] ?? ''));
$boutique_url = $boutique_slug !== '' ? app_url(boutique_vitrine_entry_href($boutique_slug)) : '';
$produit_nom_conv = trim((string) ($active_conv['produit_nom'] ?? ''));
$produit_img_conv = trim((string) ($active_conv['produit_image'] ?? ''));
$messages_back_url = isset($messages_list_url) ? $messages_list_url : $messages_page_url;

$vendeur_logo = trim((string) ($active_conv['vendeur_boutique_logo'] ?? ''));
$seller_initial = mb_strtoupper(mb_substr($boutique_nom !== '' ? $boutique_nom : 'B', 0, 1));

$activity_label = messages_vendeur_chat_presence_label($active_conv ?? [], $messages ?? [], 'client');
$presence_online = ($activity_label === 'En ligne');

$vendeur_lieu = messages_vendeur_vendeur_lieu_court($active_conv ?? []);
$member_since = messages_vendeur_member_since_label($active_conv['vendeur_date_creation'] ?? '');
$show_chat_actions = ($produit_id_conv > 0 && empty($has_offre_acceptee_a_payer) && empty($pending_counters));
$pending_counters = isset($pending_counters) && is_array($pending_counters) ? $pending_counters : [];
$has_pending_counters = !empty($pending_counters);
?>
<section class="msg-chat<?php echo $show_chat_actions ? ' msg-chat--has-actions' : ''; ?><?php echo !empty($has_offre_acceptee_a_payer) ? ' msg-chat--offer-accepted' : ''; ?><?php echo $has_pending_counters ? ' msg-chat--has-offer-actions' : ''; ?>" aria-label="Fil de discussion">
    <header class="msg-chat__header<?php echo ($produit_id_conv > 0 && $produit_url !== '') ? ' msg-chat__header--combo' : ''; ?>">
        <a href="<?php echo htmlspecialchars($messages_back_url, ENT_QUOTES, 'UTF-8'); ?>" class="msg-chat__back" aria-label="Retour aux conversations">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
        </a>
        <?php if ($produit_id_conv > 0 && $produit_url !== ''): ?>
        <?php
        $boutique_link_label = 'Voir la boutique ' . $boutique_nom;
        $produit_header_label = 'Voir l\'annonce : ' . $produit_nom_conv;
        if ($prix_conv > 0) {
            $produit_header_label .= ' — ' . number_format($prix_conv, 0, ',', ' ') . ' FCFA';
        }
        ?>
        <?php if ($boutique_url !== ''): ?>
        <a href="<?php echo htmlspecialchars($boutique_url, ENT_QUOTES, 'UTF-8'); ?>" class="msg-chat__header-seller" aria-label="<?php echo htmlspecialchars($boutique_link_label, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($boutique_link_label, ENT_QUOTES, 'UTF-8'); ?>">
        <?php else: ?>
        <div class="msg-chat__header-seller">
        <?php endif; ?>
            <div class="msg-chat__seller-avatar" aria-hidden="true">
                <?php if ($vendeur_logo !== ''): ?>
                <img src="<?php echo htmlspecialchars(upload_image_url($vendeur_logo, 'sm'), ENT_QUOTES, 'UTF-8'); ?>" alt="">
                <?php else: ?>
                <span class="msg-chat__seller-initial"><?php echo htmlspecialchars($seller_initial, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
            <div class="msg-chat__header-main">
                <h2 class="msg-chat__title"><?php echo htmlspecialchars($boutique_nom, ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="msg-chat__subtitle<?php echo $presence_online ? ' msg-chat__subtitle--online' : ''; ?>"><?php echo htmlspecialchars($activity_label, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php if ($boutique_url !== ''): ?>
        </a>
        <?php else: ?>
        </div>
        <?php endif; ?>
        <a href="<?php echo htmlspecialchars($produit_url, ENT_QUOTES, 'UTF-8'); ?>" class="msg-chat__header-product" aria-label="<?php echo htmlspecialchars($produit_header_label, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($produit_header_label, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="msg-chat__header-product-thumb">
                <?php if ($produit_img_conv !== ''): ?>
                <img src="<?php echo htmlspecialchars(upload_image_url($produit_img_conv, 'sm'), ENT_QUOTES, 'UTF-8'); ?>" alt="" width="44" height="44">
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
            <?php if ($vendeur_logo !== ''): ?>
            <img src="<?php echo htmlspecialchars(upload_image_url($vendeur_logo, 'sm'), ENT_QUOTES, 'UTF-8'); ?>" alt="">
            <?php else: ?>
            <span class="msg-chat__seller-initial"><?php echo htmlspecialchars($seller_initial, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>
        <div class="msg-chat__header-main">
            <h2 class="msg-chat__title"><?php echo htmlspecialchars($boutique_nom, ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="msg-chat__subtitle"><?php echo htmlspecialchars($activity_label, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <?php endif; ?>
        <?php if ((int) $conv_id > 0): ?>
        <?php include __DIR__ . '/messages_vendeur_chat_menu.php'; ?>
        <?php endif; ?>
    </header>

    <div class="msg-chat__scroll" id="msgThreadScroll">
        <div class="msg-chat__thread">

        <?php if ($vendeur_lieu !== '' || $member_since !== ''): ?>
        <div class="msg-chat__seller-chip" aria-label="Informations vendeur">
            <?php if ($vendeur_lieu !== ''): ?>
            <span class="msg-chat__seller-chip-item">
                <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                <?php echo htmlspecialchars($vendeur_lieu, ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <?php endif; ?>
            <?php if ($member_since !== ''): ?>
            <span class="msg-chat__seller-chip-item">
                <i class="fas fa-calendar-check" aria-hidden="true"></i>
                <?php echo htmlspecialchars($member_since, ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php
        $last_date_key = '';
        $msg_render_new_anim = false;
        $msg_render_data_attrs = true;
        $msg_viewer_role = 'client';
        include __DIR__ . '/messages_vendeur_thread_messages_client.php';
        ?>
        <div id="msgThreadScrollEnd" class="msg-chat__scroll-anchor" aria-hidden="true"></div>
        </div>
    </div>

    <div class="msg-chat__footer">
    <?php if ($has_pending_counters): ?>
        <?php foreach ($pending_counters as $neg): ?>
            <?php include __DIR__ . '/messages_vendeur_client_counter_actions.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($show_chat_actions): ?>
    <div class="msg-chat__actions">
        <?php if ($can_negocier): ?>
        <button type="button" class="msg-chat__action msg-chat__action--offer" id="msgOfferOpenBtn">
            Faire une offre
        </button>
        <?php endif; ?>
        <?php if (($conv_statut ?? 'active') === 'achat'): ?>
        <span class="msg-chat__action msg-chat__action--bought">Produit acheté</span>
        <?php else: ?>
        <a href="<?php echo htmlspecialchars($produit_url, ENT_QUOTES, 'UTF-8'); ?>" class="msg-chat__action msg-chat__action--buy<?php echo !$can_negocier ? ' msg-chat__action--buy-full' : ''; ?>">
            Acheter
        </a>
        <?php endif; ?>
    </div>
    <?php elseif (!empty($has_offre_acceptee_a_payer) && is_array($neg_orderable ?? null)): ?>
        <?php
        $neg = $neg_orderable;
        $neg_id = (int) ($neg['id'] ?? 0);
        $prix_pay = function_exists('prix_negociation_prix_convenu_effectif')
            ? prix_negociation_prix_convenu_effectif($neg)
            : null;
        $messages_redirect = ($messages_page_url ?? app_url('/user/messages.php')) . '?conv=' . (int) ($conv_id ?? 0);
        if ($neg_id > 0 && $prix_pay !== null):
        ?>
        <div class="msg-admin-offer-actions msg-client-offer-actions">
            <div class="msg-admin-offer-actions__head">
                <strong>Prix convenu</strong>
                <span><?php echo number_format((float) $prix_pay, 0, ',', ' '); ?> FCFA</span>
            </div>
            <div class="msg-admin-offer-actions__btns msg-admin-offer-actions__btns--single">
                <form method="POST" action="<?php echo htmlspecialchars(app_url('/user/prix-negociation-action.php'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php
                    if (!function_exists('site_csrf_field')) {
                        require_once __DIR__ . '/../site_csrf.php';
                    }
                    echo site_csrf_field();
                    ?>
                    <input type="hidden" name="action" value="commander">
                    <input type="hidden" name="negotiation_id" value="<?php echo $neg_id; ?>">
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($messages_redirect, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="msg-admin-offer-actions__accept">
                        <i class="fas fa-cart-shopping" aria-hidden="true"></i> Passer au paiement
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars($messages_form_action, ENT_QUOTES, 'UTF-8'); ?>" class="msg-chat__compose" id="msgChatComposeForm" novalidate>
        <input type="hidden" name="action" value="send">
        <input type="hidden" name="conversation_id" value="<?php echo (int) $conv_id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($messages_menu_csrf ?? messages_vendeur_client_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($messages_compose_redirect ?? ($messages_page_url . '?conv=' . (int) $conv_id), ENT_QUOTES, 'UTF-8'); ?>">
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

<?php if ($can_negocier): ?>
<div class="msg-offer-modal" id="msgOfferModal" role="dialog" aria-modal="true" aria-labelledby="msgOfferTitle" hidden>
    <div class="msg-offer-modal__backdrop" data-msg-offer-close tabindex="-1"></div>
    <div class="msg-offer-modal__panel">
        <header class="msg-offer-modal__head">
            <h3 id="msgOfferTitle">Faire une offre</h3>
            <button type="button" class="msg-offer-modal__close" data-msg-offer-close aria-label="Fermer">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </header>
        <?php if ($prix_conv > 0): ?>
        <p class="msg-offer-modal__ref">Prix affiché : <strong><?php echo number_format($prix_conv, 0, ',', ' '); ?> FCFA</strong></p>
        <?php endif; ?>
        <form method="POST" action="<?php echo htmlspecialchars($messages_form_action, ENT_QUOTES, 'UTF-8'); ?>" class="msg-offer-modal__form">
            <input type="hidden" name="action" value="propose_prix">
            <input type="hidden" name="conversation_id" value="<?php echo (int) $conv_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($messages_menu_csrf ?? messages_vendeur_client_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="produit_id" value="<?php echo (int) $produit_id_conv; ?>">
            <input type="hidden" name="prix_reference" value="<?php echo (float) $prix_conv; ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($messages_compose_redirect ?? ($messages_page_url . '?conv=' . (int) $conv_id), ENT_QUOTES, 'UTF-8'); ?>">
            <label class="msg-offer-modal__label" for="msgPrixPropose">Votre prix (FCFA)</label>
            <input type="number" name="prix_propose" id="msgPrixPropose" class="msg-offer-modal__input"
                min="1" step="1" required placeholder="Ex. <?php echo max(1, (int) round($prix_conv * 0.9)); ?>" inputmode="numeric">
            <div class="msg-offer-modal__actions">
                <button type="button" class="msg-offer-modal__btn msg-offer-modal__btn--ghost" data-msg-offer-close>Annuler</button>
                <button type="submit" class="msg-offer-modal__btn msg-offer-modal__btn--primary">Envoyer l'offre</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
