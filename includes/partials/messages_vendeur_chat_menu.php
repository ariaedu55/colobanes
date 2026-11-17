<?php
/**
 * Menu ⋮ — actions conversation client
 *
 * Variables attendues :
 * $conv_id, $messages_form_action, $messages_compose_redirect, $messages_list_url
 */
if (!isset($messages_menu_csrf)) {
    $messages_menu_csrf = messages_vendeur_client_csrf_token();
}
$messages_menu_redirect = isset($messages_compose_redirect)
    ? (string) $messages_compose_redirect
    : (isset($messages_page_url) ? $messages_page_url : app_url('/user/messages.php'));
$messages_menu_list_url = isset($messages_list_url)
    ? (string) $messages_list_url
    : (isset($messages_page_url) ? $messages_page_url : app_url('/user/messages.php'));
?>
<div class="msg-chat__menu-wrap" data-msg-chat-menu>
    <button type="button"
        class="msg-chat__menu msg-chat__menu--lg"
        id="msgChatMenuBtn"
        aria-label="Options de la conversation"
        aria-haspopup="menu"
        aria-expanded="false"
        aria-controls="msgChatMenuDropdown">
        <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
    </button>
    <div class="msg-chat__dropdown" id="msgChatMenuDropdown" role="menu" hidden>
        <form method="post"
            action="<?php echo htmlspecialchars($messages_form_action, ENT_QUOTES, 'UTF-8'); ?>"
            class="msg-chat__dropdown-form"
            role="none">
            <input type="hidden" name="action" value="bloquer_vendeur">
            <input type="hidden" name="conversation_id" value="<?php echo (int) $conv_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($messages_menu_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($messages_menu_list_url, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit"
                class="msg-chat__dropdown-item"
                role="menuitem"
                onclick="return confirm('Bloquer ce vendeur ? Vous ne pourrez plus échanger avec lui.');">
                Bloquer cet utilisateur
            </button>
        </form>
        <form method="post"
            action="<?php echo htmlspecialchars($messages_form_action, ENT_QUOTES, 'UTF-8'); ?>"
            class="msg-chat__dropdown-form"
            role="none">
            <input type="hidden" name="action" value="signaler_vendeur">
            <input type="hidden" name="conversation_id" value="<?php echo (int) $conv_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($messages_menu_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($messages_menu_redirect, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="msg-chat__dropdown-item" role="menuitem">
                Signaler cet utilisateur
            </button>
        </form>
        <form method="post"
            action="<?php echo htmlspecialchars($messages_form_action, ENT_QUOTES, 'UTF-8'); ?>"
            class="msg-chat__dropdown-form"
            role="none">
            <input type="hidden" name="action" value="supprimer_conversation">
            <input type="hidden" name="conversation_id" value="<?php echo (int) $conv_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($messages_menu_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($messages_menu_list_url, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit"
                class="msg-chat__dropdown-item msg-chat__dropdown-item--danger"
                role="menuitem"
                onclick="return confirm('Supprimer cette conversation ? Cette action est définitive.');">
                Supprimer la conversation
            </button>
        </form>
    </div>
</div>
