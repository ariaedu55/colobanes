<?php
/**
 * Actions client — accepter (payer) / rejeter une contre-proposition vendeur
 *
 * Variables : $neg, $messages_page_url, $conv_id
 */
if (!isset($neg) || !is_array($neg)) {
    return;
}
if (!function_exists('app_url')) {
    require_once __DIR__ . '/../app_base.php';
}
if (!function_exists('site_csrf_field')) {
    require_once __DIR__ . '/../site_csrf.php';
}

$neg_id = (int) ($neg['id'] ?? 0);
$statut = (string) ($neg['statut'] ?? '');
if ($neg_id <= 0 || $statut !== 'contre_proposee') {
    return;
}

$prix_contre = (float) ($neg['prix_contre_vendeur'] ?? 0);
if ($prix_contre <= 0 && function_exists('prix_negociation_prix_convenu_effectif')) {
    $eff = prix_negociation_prix_convenu_effectif($neg);
    $prix_contre = $eff !== null ? (float) $eff : 0.0;
}
$prix_ref = (float) ($neg['prix_reference'] ?? 0);
$messages_redirect = ($messages_page_url ?? app_url('/user/messages.php')) . '?conv=' . (int) ($conv_id ?? 0);
$action_url = app_url('/user/prix-negociation-action.php');
?>
<div class="msg-admin-offer-actions msg-client-offer-actions">
    <div class="msg-admin-offer-actions__head">
        <strong>Proposition du vendeur</strong>
        <span><?php echo number_format($prix_contre, 0, ',', ' '); ?> FCFA</span>
        <?php if ($prix_ref > 0): ?>
        <small>Prix affiché : <?php echo number_format($prix_ref, 0, ',', ' '); ?> FCFA</small>
        <?php endif; ?>
    </div>
    <div class="msg-admin-offer-actions__btns">
        <form method="POST" action="<?php echo htmlspecialchars($action_url, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo site_csrf_field(); ?>
            <input type="hidden" name="action" value="commander">
            <input type="hidden" name="negotiation_id" value="<?php echo $neg_id; ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($messages_redirect, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="msg-admin-offer-actions__accept">
                <i class="fas fa-check" aria-hidden="true"></i> Accepter et payer
            </button>
        </form>
        <form method="POST" action="<?php echo htmlspecialchars($action_url, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo site_csrf_field(); ?>
            <input type="hidden" name="action" value="reject_counter">
            <input type="hidden" name="negotiation_id" value="<?php echo $neg_id; ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($messages_redirect, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="msg-admin-offer-actions__reject">
                <i class="fas fa-times" aria-hidden="true"></i> Rejeter
            </button>
        </form>
    </div>
</div>
