<?php
/**
 * Vitrine boutique — en-tête commun (bannière thématisée, identité, actions, onglets, recherche).
 * Page publique visitée par lien : en lecture seule (aucun contrôle de modification).
 * À inclure juste après nav_bar.php dans les pages boutique/*.
 *
 * Thème : les couleurs choisies par le vendeur (principale / accent) sont exposées en variables CSS
 * (--shop-primary, --shop-accent) et pilotent toute la vitrine (css/boutique-vitrine-v2.css).
 */
if (!defined('BOUTIQUE_ADMIN_ID')) {
    return;
}
if (!function_exists('app_url')) {
    require_once __DIR__ . '/../app_base.php';
}
if (!function_exists('boutique_url')) {
    require_once __DIR__ . '/../marketplace_helpers.php';
}
if (!function_exists('upload_image_url_from_src')) {
    require_once __DIR__ . '/../image_optimizer.php';
}
if (!function_exists('boutique_vendeur_display_from_row')) {
    require_once __DIR__ . '/../boutique_vendeur_display.php';
}
if (!function_exists('site_csrf_field')) {
    require_once __DIR__ . '/../site_csrf.php';
}

$bv = isset($GLOBALS['BOUTIQUE_VENDEUR_DISPLAY']) && is_array($GLOBALS['BOUTIQUE_VENDEUR_DISPLAY'])
    ? $GLOBALS['BOUTIQUE_VENDEUR_DISPLAY']
    : [];
$bv_admin_id = (int) BOUTIQUE_ADMIN_ID;
$bv_nom = defined('BOUTIQUE_NOM') ? (string) BOUTIQUE_NOM : trim((string) ($bv['boutique_nom'] ?? 'Boutique'));
$bv_initial = mb_strtoupper(mb_substr($bv_nom !== '' ? $bv_nom : 'B', 0, 1, 'UTF-8'), 'UTF-8');
$bv_logo = trim((string) ($bv['boutique_logo'] ?? ''));
$bv_logo_url = $bv_logo !== '' ? upload_image_url_from_src($bv_logo, 'sm') : '';
$bv_cover = trim((string) ($bv['boutique_couverture'] ?? ''));
$bv_cover_url = $bv_cover !== '' ? upload_image_url_from_src($bv_cover, 'md') : '';

// Couleurs du vendeur (repli sur la palette du site)
$bv_hex = static function ($c, $fallback) {
    $c = trim((string) $c);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : $fallback;
};
$bv_primary = $bv_hex($bv['boutique_couleur_principale'] ?? '', '#3564a6');
$bv_accent = $bv_hex($bv['boutique_couleur_accent'] ?? '', '#ff6b35');

// Chiffres de la boutique
$bv_nb_produits = function_exists('count_all_produits_actifs') ? (int) count_all_produits_actifs($bv_admin_id) : 0;
if (!function_exists('count_all_produits_actifs') && file_exists(__DIR__ . '/../../models/model_produits.php')) {
    require_once __DIR__ . '/../../models/model_produits.php';
    $bv_nb_produits = (int) count_all_produits_actifs($bv_admin_id);
}
$bv_nb_abonnes = 0;
$bv_abonne = false;
if (file_exists(__DIR__ . '/../../models/model_boutique_abonnements.php')) {
    require_once __DIR__ . '/../../models/model_boutique_abonnements.php';
    if (function_exists('boutique_abonnements_table_exists') && boutique_abonnements_table_exists()) {
        $bv_nb_abonnes = (int) boutique_abonnements_count_by_vendeur($bv_admin_id);
        if (!empty($_SESSION['user_id'])) {
            $bv_abonne = (bool) boutique_abonnement_is_subscribed((int) $_SESSION['user_id'], $bv_admin_id);
        }
    }
}
$bv_cert = null;
if (file_exists(__DIR__ . '/../../models/model_vendeur_certification.php')) {
    require_once __DIR__ . '/../../models/model_vendeur_certification.php';
    if (function_exists('vendeur_certification_get_niveau_actif')) {
        $bv_cert = vendeur_certification_get_niveau_actif($bv_admin_id);
    }
}

// Onglets
$bv_script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
// Pas d'onglet « Contact » : le bouton « Contacter » de l'en-tête y mène déjà.
// « Boutique » plutôt qu'« Accueil » : la barre du bas a déjà un « Accueil » (retour sur COLObanes).
$bv_tabs = [
    'index.php' => ['Boutique', 'fa-store'],
    'produits.php' => ['Produits', 'fa-grip'],
    'nouveautes.php' => ['Nouveautés', 'fa-wand-magic-sparkles'],
    'promo.php' => ['Promos', 'fa-tag'],
];
$bv_recherche = isset($_GET['recherche']) ? trim((string) $_GET['recherche']) : '';
$bv_page_url = app_url(boutique_url('index.php', BOUTIQUE_SLUG));
$bv_redirect = (string) ($_SERVER['REQUEST_URI'] ?? $bv_page_url);
$bv_self_vendeur = !empty($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] === $bv_admin_id;
?>
<style>
    :root {
        --shop-primary: <?php echo $bv_primary; ?>;
        --shop-accent: <?php echo $bv_accent; ?>;
    }
</style>

<?php
// Barre unique de la vitrine : remplace l'en-tête du site et la barre MENU (masqués en CSS sur body.boutique-vitrine).
// Ordinateur uniquement : retour sur COLObanes + panier + compte. Sur téléphone/tablette, la barre du bas porte déjà
// « Accueil » (retour sur COLObanes), le panier et le compte : cette barre y est masquée.
$bv_panier_href = isset($nav_panier_href) ? (string) $nav_panier_href : app_url(boutique_url('panier.php', BOUTIQUE_SLUG));
$bv_panier_count = isset($panier_count) ? (int) $panier_count : 0;
$bv_compte_href = isset($nav_compte_href) ? (string) $nav_compte_href : '/choix-connexion.php';
if (strpos($bv_compte_href, 'http') !== 0) {
    $bv_compte_href = app_url($bv_compte_href);
}
$bv_compte_letter = trim(strip_tags((string) ($nav_compte_avatar_letter ?? '')));
$bv_compte_label = !empty($nav_compte_logged) ? 'Mon compte' : 'Se connecter';
?>
<div class="bv-topbar">
    <a class="bv-topbar__back" href="<?php echo htmlspecialchars(app_url('/index.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Retour sur COLObanes">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
        <img src="<?php echo htmlspecialchars(app_url('/image/logo_market.webp'), ENT_QUOTES, 'UTF-8'); ?>" alt="COLObanes" width="130" height="35">
        <span class="bv-topbar__back-text">Retour sur COLObanes</span>
    </a>
    <div class="bv-topbar__actions">
        <a class="bv-topbar__btn" href="<?php echo htmlspecialchars($bv_panier_href, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Panier<?php echo $bv_panier_count > 0 ? ' — ' . $bv_panier_count . ' article(s)' : ''; ?>">
            <i class="fas fa-cart-shopping" aria-hidden="true"></i>
            <?php if ($bv_panier_count > 0): ?>
            <span class="bv-topbar__badge" aria-hidden="true"><?php echo $bv_panier_count > 99 ? '99+' : $bv_panier_count; ?></span>
            <?php endif; ?>
        </a>
        <a class="bv-topbar__account" href="<?php echo htmlspecialchars($bv_compte_href, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo $bv_compte_label; ?>">
            <?php if ($bv_compte_letter !== ''): ?>
            <span class="bv-topbar__avatar"><?php echo htmlspecialchars(mb_substr($bv_compte_letter, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></span>
            <?php else: ?>
            <i class="fas fa-circle-user" aria-hidden="true"></i>
            <?php endif; ?>
            <span class="bv-topbar__account-text"><?php echo $bv_compte_label; ?></span>
        </a>
    </div>
</div>

<header class="bv-header" aria-label="<?php echo htmlspecialchars('Boutique ' . $bv_nom, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="bv-cover<?php echo $bv_cover_url !== '' ? ' bv-cover--photo' : ''; ?>" aria-hidden="true">
        <?php if ($bv_cover_url !== ''): ?>
        <img class="bv-cover__img" src="<?php echo htmlspecialchars($bv_cover_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="1200" height="320">
        <?php endif; ?>
    </div>

    <div class="bv-identity">
        <div class="bv-identity__logo">
            <?php if ($bv_logo_url !== ''): ?>
            <img src="<?php echo htmlspecialchars($bv_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars('Logo ' . $bv_nom, ENT_QUOTES, 'UTF-8'); ?>" width="96" height="96">
            <?php else: ?>
            <span><?php echo htmlspecialchars($bv_initial, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>

        <div class="bv-identity__main">
            <h1 class="bv-identity__name">
                <?php echo htmlspecialchars($bv_nom, ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($bv_cert)): ?>
                <span class="bv-identity__cert" title="Boutique certifiée"><i class="fas fa-circle-check" aria-hidden="true"></i><span class="visually-hidden">Boutique certifiée</span></span>
                <?php endif; ?>
            </h1>
            <ul class="bv-identity__meta">
                <li><strong><?php echo number_format($bv_nb_produits, 0, ',', ' '); ?></strong> produit<?php echo $bv_nb_produits > 1 ? 's' : ''; ?></li>
                <li><strong><?php echo number_format($bv_nb_abonnes, 0, ',', ' '); ?></strong> abonné<?php echo $bv_nb_abonnes > 1 ? 's' : ''; ?></li>
            </ul>
        </div>

        <div class="bv-identity__actions">
            <?php /* Vitrine publique en lecture seule : aucun lien de modification, même pour le vendeur.
                     Le vendeur ne peut pas suivre sa propre boutique : il voit seulement Contacter / Partager. */ ?>
            <?php if ($bv_self_vendeur): ?>
            <?php elseif (!empty($_SESSION['user_id'])): ?>
            <form method="post" action="<?php echo htmlspecialchars(app_url('/user/boutique-abonnement-action.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo site_csrf_field(); ?>
                <input type="hidden" name="admin_id" value="<?php echo $bv_admin_id; ?>">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($bv_redirect, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="<?php echo $bv_abonne ? 'unsubscribe' : 'subscribe'; ?>">
                <button type="submit" class="bv-btn <?php echo $bv_abonne ? 'bv-btn--ghost' : 'bv-btn--primary'; ?>">
                    <i class="fas <?php echo $bv_abonne ? 'fa-check' : 'fa-plus'; ?>" aria-hidden="true"></i>
                    <?php echo $bv_abonne ? 'Abonné' : 'Suivre'; ?>
                </button>
            </form>
            <?php else: ?>
            <a class="bv-btn bv-btn--primary" href="<?php echo htmlspecialchars(app_url('/choix-connexion.php?redirect=' . rawurlencode($bv_redirect)), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-plus" aria-hidden="true"></i> Suivre
            </a>
            <?php endif; ?>
            <a class="bv-btn bv-btn--ghost" href="<?php echo htmlspecialchars(app_url(boutique_url('contact.php', BOUTIQUE_SLUG)), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-comment-dots" aria-hidden="true"></i> Contacter
            </a>
            <button type="button" class="bv-btn bv-btn--icon" aria-label="Partager la boutique"
                onclick="if (window.openPlatformShareModal) { window.openPlatformShareModal({ url: <?php echo htmlspecialchars(json_encode($bv_page_url), ENT_QUOTES, 'UTF-8'); ?>, title: <?php echo htmlspecialchars(json_encode($bv_nom), ENT_QUOTES, 'UTF-8'); ?> }); }">
                <i class="fas fa-share-nodes" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="bv-toolbar">
        <?php /* <div role="navigation"> : les styles legacy « nav { … } » de nabare.css passeraient les onglets en colonne */ ?>
        <div class="bv-tabs" role="navigation" aria-label="Rubriques de la boutique">
            <?php foreach ($bv_tabs as $bv_file => $bv_tab): ?>
            <a href="<?php echo htmlspecialchars(app_url(boutique_url($bv_file, BOUTIQUE_SLUG)), ENT_QUOTES, 'UTF-8'); ?>"
                class="bv-tabs__item<?php echo $bv_script === $bv_file ? ' is-active' : ''; ?>"
                <?php echo $bv_script === $bv_file ? 'aria-current="page"' : ''; ?>>
                <i class="fas <?php echo $bv_tab[1]; ?>" aria-hidden="true"></i><?php echo $bv_tab[0]; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <form class="bv-search" method="get" action="<?php echo htmlspecialchars(app_url(boutique_url('produits.php', BOUTIQUE_SLUG)), ENT_QUOTES, 'UTF-8'); ?>" role="search">
            <?php if (!str_contains(boutique_url('produits.php', BOUTIQUE_SLUG), '/' . BOUTIQUE_SLUG . '/')): ?>
            <input type="hidden" name="boutique" value="<?php echo htmlspecialchars(BOUTIQUE_SLUG, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" name="recherche" value="<?php echo htmlspecialchars($bv_recherche, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="<?php echo htmlspecialchars('Rechercher dans ' . $bv_nom, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Rechercher dans la boutique">
        </form>
    </div>
</header>
