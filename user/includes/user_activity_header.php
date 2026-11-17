<?php
/**
 * En-tête unifié — pages activité client (favoris, boutiques, commandes…)
 *
 * Variables :
 * - $user_activity_title (string, requis)
 * - $user_activity_back_href (string, défaut profil.php)
 * - $user_activity_back_label (string, défaut Retour au profil)
 */
$title = isset($user_activity_title) ? trim((string) $user_activity_title) : '';
if ($title === '') {
    return;
}
$back_href = isset($user_activity_back_href) ? trim((string) $user_activity_back_href) : 'profil.php';
$back_label = isset($user_activity_back_label) ? trim((string) $user_activity_back_label) : 'Retour au profil';
?>
<header class="uact-header">
    <a href="<?php echo htmlspecialchars($back_href, ENT_QUOTES, 'UTF-8'); ?>" class="uact-back" aria-label="<?php echo htmlspecialchars($back_label, ENT_QUOTES, 'UTF-8'); ?>">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
    </a>
    <div class="uact-header__main">
        <h1 class="uact-header__title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
</header>
