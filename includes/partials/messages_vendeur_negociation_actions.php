<?php
/**
 * Actions vendeur — valider / rejeter une offre (messagerie admin)
 *
 * Variables : $neg, $messages_page_url, $conv_id
 * Optionnel : $msg_negociation_actions_part = 'bar' | 'overlay' | 'all' (défaut)
 */
if (!isset($neg) || !is_array($neg)) {
    return;
}
if (!function_exists('app_url')) {
    require_once __DIR__ . '/../app_base.php';
}
if (!function_exists('admin_csrf_token')) {
    require_once __DIR__ . '/../admin_csrf.php';
}

$neg_id = (int) ($neg['id'] ?? 0);
$statut = (string) ($neg['statut'] ?? '');
if ($neg_id <= 0 || $statut !== 'en_attente') {
    return;
}

$part = isset($msg_negociation_actions_part) ? (string) $msg_negociation_actions_part : 'all';
if (!in_array($part, ['bar', 'overlay', 'all'], true)) {
    $part = 'all';
}

$prix_propose = (float) ($neg['prix_propose_client'] ?? 0);
$prix_ref = (float) ($neg['prix_reference'] ?? 0);
$reject_overlay_id = 'msgNegReject' . $neg_id;
$messages_redirect = ($messages_page_url ?? app_url('/admin/messages.php')) . '?conv=' . (int) ($conv_id ?? 0);
$action_url = app_url('/admin/prix-negociation-action.php');
$csrf = admin_csrf_token();

if ($part === 'bar' || $part === 'all'):
?>
<div class="msg-admin-offer-actions">
    <div class="msg-admin-offer-actions__head">
        <strong>Offre en attente</strong>
        <span><?php echo number_format($prix_propose, 0, ',', ' '); ?> FCFA</span>
        <?php if ($prix_ref > 0): ?>
        <small>Prix affiché : <?php echo number_format($prix_ref, 0, ',', ' '); ?> FCFA</small>
        <?php endif; ?>
    </div>
    <div class="msg-admin-offer-actions__btns">
        <form method="POST" action="<?php echo htmlspecialchars($action_url, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="accept">
            <input type="hidden" name="negotiation_id" value="<?php echo $neg_id; ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($messages_redirect, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="msg-admin-offer-actions__accept">
                <i class="fas fa-check" aria-hidden="true"></i> Valider l'offre
            </button>
        </form>
        <button type="button" class="msg-admin-offer-actions__reject"
            data-prix-neg-reject-open="<?php echo htmlspecialchars($reject_overlay_id, ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas fa-times" aria-hidden="true"></i> Rejeter / Proposition
        </button>
    </div>
</div>
<?php
endif;

if ($part === 'overlay' || $part === 'all'):
?>
<div class="prix-neg-reject-overlay" id="<?php echo htmlspecialchars($reject_overlay_id, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true" hidden>
    <div class="prix-neg-reject-overlay__backdrop" data-prix-neg-reject-close tabindex="-1"></div>
    <div class="prix-neg-reject-panel" role="dialog" aria-modal="true" aria-labelledby="msgNegRejectTitle<?php echo $neg_id; ?>">
        <div class="prix-neg-reject-panel__head">
            <h3 id="msgNegRejectTitle<?php echo $neg_id; ?>">Rejeter ou proposition</h3>
            <button type="button" class="prix-neg-modal__close" data-prix-neg-reject-close aria-label="Fermer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <p class="prix-neg-reject-panel__hint">Saisissez un prix puis envoyez votre proposition, ou refusez l'offre sans prix.</p>
        <form method="POST" action="<?php echo htmlspecialchars($action_url, ENT_QUOTES, 'UTF-8'); ?>" class="prix-neg-reject-panel__form" data-prix-neg-reject-form>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="negotiation_id" value="<?php echo $neg_id; ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($messages_redirect, ENT_QUOTES, 'UTF-8'); ?>">
            <label for="msg-prix-contre-<?php echo $neg_id; ?>">Votre prix (FCFA)</label>
            <input type="text" name="prix_contre" id="msg-prix-contre-<?php echo $neg_id; ?>"
                inputmode="numeric" autocomplete="off" placeholder="Ex : 420000"
                data-prix-neg-reject-input>
            <div class="prix-neg-reject-panel__actions">
                <button type="button" class="prix-neg-modal__btn prix-neg-modal__btn--cancel" data-prix-neg-reject-close>Annuler</button>
                <button type="submit" name="action" value="reject_counter" class="prix-neg-btn prix-neg-btn--accept" data-prix-neg-reject-submit>
                    <i class="fas fa-paper-plane"></i> Envoyer ma proposition
                </button>
                <button type="submit" name="action" value="reject_final" class="prix-neg-btn prix-neg-btn--reject" data-prix-neg-reject-final>
                    <i class="fas fa-times"></i> Rejeter
                </button>
            </div>
        </form>
    </div>
</div>
<?php
endif;
