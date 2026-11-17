<?php
/**
 * Assistant COLObanes (chatbot maison) — bulle flottante + fenêtre de discussion.
 * Remplace l'ancien agent Jotform. Moteur : includes/assistant/assistant_engine.php, API : api/assistant.php.
 */
if (defined('ASSISTANCE_FLOATING_LOADED')) {
    return;
}
define('ASSISTANCE_FLOATING_LOADED', true);

require_once __DIR__ . '/assistant/assistant_engine.php';
require_once __DIR__ . '/asset_version.php';

$ca_cfg = assistant_config();
if (empty($ca_cfg['enabled'])) {
    return;
}
$ca_bot = (string) $ca_cfg['bot_name'];
/* Photo du visage de l'assistante : déposer image/assistant/colo-avatar.jpg (ou .webp / .png).
   Sans photo, l'assistant garde son icône habituelle. */
$ca_avatar_url = '';
foreach (['webp', 'jpg', 'jpeg', 'png'] as $ca_ext) {
    $ca_avatar_file = dirname(__DIR__) . '/image/assistant/colo-avatar.' . $ca_ext;
    if (is_file($ca_avatar_file)) {
        $ca_avatar_url = app_url('/image/assistant/colo-avatar.' . $ca_ext) . '?v=' . (int) filemtime($ca_avatar_file);
        break;
    }
}
$ca_user_prenom = '';
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
    $ca_user_prenom = trim((string) ($_SESSION['user_prenom'] ?? ''));
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/colo-assistant.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">

<button type="button" class="ca-launcher<?php echo $ca_avatar_url !== '' ? ' ca-launcher--photo' : ''; ?>" id="caLauncher" aria-haspopup="dialog" aria-controls="caPanel" aria-expanded="false">
    <?php if ($ca_avatar_url !== ''): ?>
    <span class="ca-launcher__photo" aria-hidden="true">
        <img src="<?php echo htmlspecialchars($ca_avatar_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="44" height="44">
    </span>
    <?php else: ?>
    <span class="ca-launcher__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M8.5 11.5h.01M12 11.5h.01M15.5 11.5h.01"/></svg>
    </span>
    <?php endif; ?>
    <span class="ca-launcher__label">Une question ?</span>
    <span class="ca-launcher__dot" aria-hidden="true"></span>
</button>

<section class="ca-panel" id="caPanel" role="dialog" aria-modal="false" aria-labelledby="caTitle" hidden
    data-endpoint="<?php echo htmlspecialchars(app_url('/api/assistant.php'), ENT_QUOTES, 'UTF-8'); ?>"
    data-bot="<?php echo htmlspecialchars($ca_bot, ENT_QUOTES, 'UTF-8'); ?>"
    data-user="<?php echo htmlspecialchars($ca_user_prenom, ENT_QUOTES, 'UTF-8'); ?>"
    data-avatar="<?php echo htmlspecialchars($ca_avatar_url, ENT_QUOTES, 'UTF-8'); ?>">
    <header class="ca-head">
        <span class="ca-head__avatar<?php echo $ca_avatar_url !== '' ? ' ca-head__avatar--photo' : ''; ?>" aria-hidden="true">
            <img src="<?php echo htmlspecialchars($ca_avatar_url !== '' ? $ca_avatar_url : app_url('/site-icons/site-icon-96.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="" width="40" height="40">
        </span>
        <div class="ca-head__text">
            <h2 class="ca-head__title" id="caTitle"><?php echo htmlspecialchars($ca_bot, ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="ca-head__status"><span aria-hidden="true"></span> En ligne</p>
        </div>
        <button type="button" class="ca-head__btn" id="caReset" aria-label="Nouvelle conversation" title="Nouvelle conversation">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
        </button>
        <button type="button" class="ca-head__btn" id="caClose" aria-label="Fermer l'assistant">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </header>

    <div class="ca-body" id="caBody" aria-live="polite"></div>

    <form class="ca-input" id="caForm" autocomplete="off">
        <label class="visually-hidden" for="caText">Votre message</label>
        <textarea id="caText" rows="1" maxlength="500" placeholder="Posez votre question…" enterkeyhint="send"></textarea>
        <button type="submit" class="ca-input__send" id="caSend" aria-label="Envoyer" disabled>
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M3.4 20.4 21 12 3.4 3.6 3.4 10l12.6 2-12.6 2z"/></svg>
        </button>
    </form>
</section>

<script src="<?php echo htmlspecialchars(app_url('/js/colo-assistant.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
