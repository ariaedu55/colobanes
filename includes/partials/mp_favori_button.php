<?php
/**
 * Bouton cœur — favoris sur carte produit (mp-card).
 * Variables : $produit_id (int) ou $produit (array), $return_url (string optionnel).
 */
$pid = 0;
if (isset($produit_id)) {
    $pid = (int) $produit_id;
} elseif (!empty($produit) && is_array($produit)) {
    $pid = (int) ($produit['id'] ?? 0);
}
if ($pid <= 0) {
    return;
}

$return_url = isset($return_url) ? (string) $return_url : (string) ($_SERVER['REQUEST_URI'] ?? '/index.php');
$user_logged_in = !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;

if (!function_exists('site_csrf_field')) {
    require_once __DIR__ . '/../site_csrf.php';
}
if (!function_exists('app_url')) {
    require_once __DIR__ . '/../app_base.php';
}

$is_favori = false;
if ($user_logged_in) {
    if (!isset($GLOBALS['_mp_favori_produit_ids']) || !is_array($GLOBALS['_mp_favori_produit_ids'])) {
        require_once __DIR__ . '/../../models/model_favoris.php';
        $GLOBALS['_mp_favori_produit_ids'] = get_favori_produit_ids_by_user((int) $_SESSION['user_id']);
    }
    $is_favori = in_array($pid, $GLOBALS['_mp_favori_produit_ids'], true);
}

$login_redirect = app_url('/choix-connexion.php') . '?redirect=' . rawurlencode($return_url);
?>
<div class="mp-favori">
    <?php if ($user_logged_in): ?>
    <form method="POST" action="<?php echo htmlspecialchars(app_url('/favori-action.php'), ENT_QUOTES, 'UTF-8'); ?>" class="mp-favori-form">
        <?php echo site_csrf_field(); ?>
        <input type="hidden" name="produit_id" value="<?php echo $pid; ?>">
        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button"
            data-mp-favori-toggle
            data-produit-id="<?php echo $pid; ?>"
            class="mp-favori-btn<?php echo $is_favori ? ' mp-favori-btn--active' : ''; ?>"
            aria-label="<?php echo $is_favori ? 'Retirer des favoris' : 'Ajouter aux favoris'; ?>"
            aria-pressed="<?php echo $is_favori ? 'true' : 'false'; ?>">
            <i class="<?php echo $is_favori ? 'fa-solid' : 'fa-regular'; ?> fa-heart" aria-hidden="true"></i>
        </button>
    </form>
    <?php else: ?>
    <a href="<?php echo htmlspecialchars($login_redirect, ENT_QUOTES, 'UTF-8'); ?>"
        class="mp-favori-btn mp-favori-btn--guest"
        aria-label="Connectez-vous pour ajouter aux favoris">
        <i class="fa-regular fa-heart" aria-hidden="true"></i>
    </a>
    <?php endif; ?>
</div>
