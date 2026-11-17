<?php
/**
 * Sélecteur « Achats / Ventes » de la messagerie unique.
 * Affiché seulement quand la session est à la fois cliente (user_id) et vendeur (admin vendeur) :
 * les deux boîtes de réception deviennent deux onglets d'une même messagerie.
 *
 * Variable attendue : $msg_role ('client' | 'vendeur') — onglet actif.
 */
if (!function_exists('app_url')) {
    require_once __DIR__ . '/../app_base.php';
}

$msg_switch_user_id = (int) ($_SESSION['user_id'] ?? 0);
$msg_switch_admin_id = (int) ($_SESSION['admin_id'] ?? 0);
$msg_switch_is_vendeur = $msg_switch_admin_id > 0 && (string) ($_SESSION['admin_role'] ?? '') === 'vendeur';

if ($msg_switch_user_id > 0 && $msg_switch_is_vendeur):
    $msg_switch_unread_client = function_exists('message_vendeur_count_unread_for_client')
        ? (int) message_vendeur_count_unread_for_client($msg_switch_user_id)
        : 0;
    $msg_switch_unread_vendeur = function_exists('message_vendeur_count_unread_for_vendeur')
        ? (int) message_vendeur_count_unread_for_vendeur($msg_switch_admin_id)
        : 0;
    $msg_role = ($msg_role ?? 'client') === 'vendeur' ? 'vendeur' : 'client';
?>
<nav class="msg-role-switch" aria-label="Type de conversations">
    <a href="<?php echo htmlspecialchars(app_url('/user/messages.php'), ENT_QUOTES, 'UTF-8'); ?>"
        class="msg-role-switch__tab<?php echo $msg_role === 'client' ? ' is-active' : ''; ?>"
        <?php echo $msg_role === 'client' ? 'aria-current="page"' : ''; ?>>
        <i class="fas fa-bag-shopping" aria-hidden="true"></i>
        Achats
        <?php if ($msg_switch_unread_client > 0): ?>
        <span class="msg-role-switch__badge" aria-label="<?php echo $msg_switch_unread_client; ?> non lu(s)"><?php echo $msg_switch_unread_client > 99 ? '99+' : $msg_switch_unread_client; ?></span>
        <?php endif; ?>
    </a>
    <a href="<?php echo htmlspecialchars(app_url('/admin/messages.php'), ENT_QUOTES, 'UTF-8'); ?>"
        class="msg-role-switch__tab<?php echo $msg_role === 'vendeur' ? ' is-active' : ''; ?>"
        <?php echo $msg_role === 'vendeur' ? 'aria-current="page"' : ''; ?>>
        <i class="fas fa-store" aria-hidden="true"></i>
        Ventes
        <?php if ($msg_switch_unread_vendeur > 0): ?>
        <span class="msg-role-switch__badge" aria-label="<?php echo $msg_switch_unread_vendeur; ?> non lu(s)"><?php echo $msg_switch_unread_vendeur > 99 ? '99+' : $msg_switch_unread_vendeur; ?></span>
        <?php endif; ?>
    </a>
</nav>
<?php endif; ?>
