<?php
/**
 * Demande unique d'autorisation des notifications (interface vendeur).
 */
if (empty($enable_firebase_notifications)) {
    return;
}
if (($firebase_notify_type ?? '') !== 'admin') {
    return;
}
?>
<div class="vn-notify-overlay" id="vendorNotifyPrompt" hidden role="dialog" aria-modal="true" aria-labelledby="vendorNotifyTitle">
    <div class="vn-notify-modal">
        <div class="vn-notify-modal__icon" aria-hidden="true">
            <i class="fas fa-bell"></i>
        </div>
        <h2 id="vendorNotifyTitle" class="vn-notify-modal__title">Autoriser les notifications</h2>
        <p class="vn-notify-modal__text">
            Un clic suffit. Vous recevrez ensuite les alertes de votre boutique, sans rien d'autre à activer.
        </p>
        <ul class="vn-notify-modal__list">
            <li><i class="fas fa-shopping-bag"></i> Nouvelles commandes</li>
            <li><i class="fas fa-bullhorn"></i> Messages de la plateforme</li>
            <li><i class="fas fa-comments"></i> Messages des clients</li>
        </ul>
        <div class="vn-notify-modal__actions">
            <button type="button" id="btn-vendor-notify-allow" class="vn-notify-btn vn-notify-btn--primary" data-notify-type="admin">
                <i class="fas fa-bell"></i> Autoriser
            </button>
            <button type="button" id="btn-vendor-notify-later" class="vn-notify-btn vn-notify-btn--ghost">
                Plus tard
            </button>
        </div>
    </div>
</div>
