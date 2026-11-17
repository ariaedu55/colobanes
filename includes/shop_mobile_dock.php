<?php
/**
 * Dock mobile/tablette — deux blocs blancs + panier flottant central.
 * Le conteneur est un <div role="navigation"> : les styles legacy `nav { … }`
 * de css/nabare.css passeraient sinon la barre en colonne sur mobile.
 *
 * @var string $shop_dock_home_href
 * @var string $shop_dock_message_href
 * @var string $nav_panier_href
 * @var string $nav_compte_href
 * @var string $shop_dock_compte_href
 * @var bool $shop_dock_home_act
 * @var bool $shop_dock_message_act
 * @var bool $shop_dock_panier_act
 * @var bool $shop_dock_compte_act
 * @var string $shop_dock_compte_label
 * @var string $shop_dock_compte_icon
 * @var int $panier_count
 * @var int $shop_dock_messages_non_lus
 */
// Une seule barre par page (la messagerie vendeur charge aussi l'en-tête du site)
if (defined('SHOP_MOBILE_DOCK_RENDERED')) {
    return;
}
define('SHOP_MOBILE_DOCK_RENDERED', true);
$shop_dock_vendeur_admin = !empty($shop_dock_vendeur_admin);
$panier_count = isset($panier_count) ? (int) $panier_count : 0;
$shop_dock_messages_non_lus = isset($shop_dock_messages_non_lus) ? (int) $shop_dock_messages_non_lus : 0;
// Compte : icône pleine quand l'onglet est actif (cas boutique / icône Font Awesome)
$shop_dock_compte_icon_resolved = (string) ($shop_dock_compte_icon ?? 'fa-regular fa-circle-user');
if (!empty($shop_dock_compte_act)) {
    $shop_dock_compte_icon_resolved = str_replace('fa-regular', 'fa-solid', $shop_dock_compte_icon_resolved);
}
// Icônes SVG du dock (dessin de la maquette : maison pleine, bulle carrée à 3 points, profil, panier en contour)
$shop_dock_svg = [
    'home' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M10.55 2.9a2.1 2.1 0 0 1 2.9 0l7.7 7.05c.86.79.3 2.22-.86 2.22H19.2v7.1a1.9 1.9 0 0 1-1.9 1.9h-2.7v-5.3a1.1 1.1 0 0 0-1.1-1.1h-3a1.1 1.1 0 0 0-1.1 1.1v5.3H6.7a1.9 1.9 0 0 1-1.9-1.9v-7.1H3.71c-1.16 0-1.72-1.43-.86-2.22z"/></svg>',
    'message' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.3" stroke-linejoin="round" d="M7.2 3.6h9.6a4.2 4.2 0 0 1 4.2 4.2v5.4a4.2 4.2 0 0 1-4.2 4.2h-6.6l-3.9 3.1c-.35.28-.85.03-.85-.41V17.3A4.2 4.2 0 0 1 3 13.2V7.8a4.2 4.2 0 0 1 4.2-4.2z"/><circle cx="8.1" cy="10.5" r="1.25" fill="currentColor"/><circle cx="12" cy="10.5" r="1.25" fill="currentColor"/><circle cx="15.9" cy="10.5" r="1.25" fill="currentColor"/></svg>',
    'compte' => '<svg viewBox="0 0 24 24" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="9.2"/><circle cx="12" cy="9.6" r="2.9"/><path stroke-linecap="round" d="M6.4 18.6a7.4 7.4 0 0 1 11.2 0"/></g></svg>',
    'menu' => '<svg viewBox="0 0 24 24" aria-hidden="true"><g fill="currentColor"><circle cx="4.6" cy="6" r="1.7"/><circle cx="4.6" cy="12" r="1.7"/><circle cx="4.6" cy="18" r="1.7"/></g><g stroke="currentColor" stroke-width="2.3" stroke-linecap="round"><path d="M9.2 6h11.2M9.2 12h11.2M9.2 18h11.2"/></g></svg>',
    'produits' => '<svg viewBox="0 0 24 24" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2.3" stroke-linejoin="round"><path d="M12 2.8 20.2 7v10L12 21.2 3.8 17V7z"/><path d="M3.8 7 12 11.2 20.2 7M12 11.2v10"/></g></svg>',
    'plus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>',
    'panier' => '<svg viewBox="0 0 24 24" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.6 4.2h2.1c.5 0 .9.3 1 .8l.5 2"/><path d="M6.2 7h14.1c.6 0 1 .6.9 1.2l-1.1 4.2c-.15.55-.65.9-1.2.9H8.1"/><path d="M8.1 13.3 6.2 7"/><path d="M8.4 16.6h10"/></g><circle cx="9.3" cy="19.9" r="1.55" fill="currentColor"/><circle cx="17.3" cy="19.9" r="1.55" fill="currentColor"/></svg>',
];
?>
<div class="shop-mobile-dock" id="shopMobileDock">
    <div class="shop-mobile-dock__bar" id="shopDockBar">
        <div class="shop-mobile-dock__nav" role="navigation" aria-label="Raccourcis boutique">
            <div class="shop-mobile-dock__group">
                <a href="<?php echo htmlspecialchars($shop_dock_home_href ?? '/index.php', ENT_QUOTES, 'UTF-8'); ?>"
                    class="shop-mobile-dock__item<?php echo !empty($shop_dock_home_act) ? ' is-active' : ''; ?>">
                    <span class="shop-mobile-dock__icon" aria-hidden="true"><?php echo $shop_dock_svg['home']; ?></span>
                    <span class="shop-mobile-dock__label">Accueil</span>
                </a>
<?php if ($shop_dock_vendeur_admin): ?>
                <a href="<?php echo htmlspecialchars($shop_dock_produits_href ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    class="shop-mobile-dock__item<?php echo !empty($shop_dock_produits_act) ? ' is-active' : ''; ?>">
                    <span class="shop-mobile-dock__icon" aria-hidden="true"><?php echo $shop_dock_svg['produits']; ?></span>
                    <span class="shop-mobile-dock__label">Produits</span>
                </a>
                <?php else: ?>
                <a href="<?php echo htmlspecialchars($shop_dock_message_href ?? '/contact.php', ENT_QUOTES, 'UTF-8'); ?>"
                    class="shop-mobile-dock__item shop-mobile-dock__item--messages<?php echo !empty($shop_dock_message_act) ? ' is-active' : ''; ?><?php echo $shop_dock_messages_non_lus > 0 ? ' has-badge' : ''; ?>"
                    id="shopDockMessagesLink"
                    aria-label="Messages<?php echo $shop_dock_messages_non_lus > 0 ? ' — ' . $shop_dock_messages_non_lus . ' non lu' . ($shop_dock_messages_non_lus > 1 ? 's' : '') : ''; ?>">
                    <?php if ($shop_dock_messages_non_lus > 0): ?>
                    <span class="shop-mobile-dock__badge shop-mobile-dock__badge--messages"
                        id="shopDockMessagesBadge"
                        aria-hidden="true"><?php echo $shop_dock_messages_non_lus > 99 ? '99+' : $shop_dock_messages_non_lus; ?></span>
                    <?php endif; ?>
                    <span class="shop-mobile-dock__icon" aria-hidden="true"><?php echo $shop_dock_svg['message']; ?></span>
                    <span class="shop-mobile-dock__label">Message</span>
                </a>
                <?php endif; ?>
            </div>

            <?php /* Raccord blanc entre les deux blocs, creusé en demi-cercle sous le panier */ ?>
            <div class="shop-mobile-dock__slot" aria-hidden="true">
                <?php /* Creux = arc de rayon 34,5 (panier + 5px d'air) centré 15px sous le bord, raccordé par deux arrondis de rayon 18 */ ?>
                <svg class="shop-mobile-dock__notch" viewBox="0 0 106 54" preserveAspectRatio="none" focusable="false">
                    <path fill="#ffffff" d="M0 0H0.59A18 18 0 0 1 18.56 16.97A34.5 34.5 0 0 0 87.44 16.97A18 18 0 0 1 105.41 0H106V54H0Z"/>
                </svg>
            </div>

            <div class="shop-mobile-dock__group">
<?php if ($shop_dock_vendeur_admin): ?>
                <a href="<?php echo htmlspecialchars($shop_dock_message_href ?? '/contact.php', ENT_QUOTES, 'UTF-8'); ?>"
                    class="shop-mobile-dock__item shop-mobile-dock__item--messages<?php echo !empty($shop_dock_message_act) ? ' is-active' : ''; ?><?php echo $shop_dock_messages_non_lus > 0 ? ' has-badge' : ''; ?>"
                    id="shopDockMessagesLink"
                    aria-label="Messages<?php echo $shop_dock_messages_non_lus > 0 ? ' — ' . $shop_dock_messages_non_lus . ' non lu' . ($shop_dock_messages_non_lus > 1 ? 's' : '') : ''; ?>">
                    <?php if ($shop_dock_messages_non_lus > 0): ?>
                    <span class="shop-mobile-dock__badge shop-mobile-dock__badge--messages"
                        id="shopDockMessagesBadge"
                        aria-hidden="true"><?php echo $shop_dock_messages_non_lus > 99 ? '99+' : $shop_dock_messages_non_lus; ?></span>
                    <?php endif; ?>
                    <span class="shop-mobile-dock__icon" aria-hidden="true"><?php echo $shop_dock_svg['message']; ?></span>
                    <span class="shop-mobile-dock__label">Message</span>
                </a>
                <button type="button" id="shopDockAdminMenuBtn"
                    class="shop-mobile-dock__item shop-mobile-dock__button"
                    aria-label="Ouvrir le menu vendeur" onclick="if (typeof window.toggleSidebar === 'function') { window.toggleSidebar(); }">
                    <span class="shop-mobile-dock__icon" aria-hidden="true"><?php echo $shop_dock_svg['menu']; ?></span>
                    <span class="shop-mobile-dock__label">Menu</span>
                </button>
                <?php else: ?>
                <a href="<?php echo htmlspecialchars($shop_dock_compte_href ?? $nav_compte_href ?? '/choix-connexion.php', ENT_QUOTES, 'UTF-8'); ?>"
                    class="shop-mobile-dock__item<?php echo !empty($shop_dock_compte_act) ? ' is-active' : ''; ?>"
                    aria-label="<?php echo htmlspecialchars($shop_dock_compte_label ?? 'Compte', ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="shop-mobile-dock__icon" aria-hidden="true"><?php if (str_contains($shop_dock_compte_icon_resolved, 'circle-user')): ?><?php echo $shop_dock_svg['compte']; ?><?php else: ?><i
                            class="<?php echo htmlspecialchars($shop_dock_compte_icon_resolved, ENT_QUOTES, 'UTF-8'); ?>"></i><?php endif; ?></span>
                    <span class="shop-mobile-dock__label"><?php echo htmlspecialchars($shop_dock_compte_label ?? 'Compte', ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <button type="button" id="shopDockSidebarBtn"
                    class="shop-mobile-dock__item shop-mobile-dock__button"
                    aria-label="Ouvrir le menu catalogue">
                    <span class="shop-mobile-dock__icon" aria-hidden="true"><?php echo $shop_dock_svg['menu']; ?></span>
                    <span class="shop-mobile-dock__label">Menu</span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($shop_dock_vendeur_admin): ?>
        <a href="<?php echo htmlspecialchars($shop_dock_add_href ?? '', ENT_QUOTES, 'UTF-8'); ?>"
            class="shop-mobile-dock__fab shop-mobile-dock__fab--add" aria-label="Ajouter un produit" title="Ajouter un produit">
            <?php echo $shop_dock_svg['plus']; ?>
        </a>
        <?php else: ?>
        <a href="<?php echo htmlspecialchars($nav_panier_href ?? '/panier.php', ENT_QUOTES, 'UTF-8'); ?>"
            class="shop-mobile-dock__fab<?php echo !empty($shop_dock_panier_act) ? ' is-active' : ''; ?>"
            aria-label="Panier<?php echo $panier_count > 0 ? ' — ' . $panier_count . ' article' . ($panier_count > 1 ? 's' : '') : ''; ?>">
            <?php echo $shop_dock_svg['panier']; ?>
            <?php if ($panier_count > 0): ?>
                <span class="shop-mobile-dock__badge"
                    aria-hidden="true"><?php echo $panier_count > 99 ? '99+' : $panier_count; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    function pinShopMobileDock() {
        var dock = document.getElementById('shopMobileDock');
        if (!dock || dock.parentNode === document.body && dock === document.body.lastElementChild) {
            return;
        }
        document.body.appendChild(dock);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', pinShopMobileDock, { once: true });
    } else {
        pinShopMobileDock();
    }
})();
</script>
