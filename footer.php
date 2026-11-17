<?php
if (!defined('SITE_BRAND_NAME')) {
    require_once __DIR__ . '/includes/site_brand.php';
}
if (!function_exists('asset_version_query')) {
    require_once __DIR__ . '/includes/asset_version.php';
}
if (!function_exists('boutique_url')) {
    require_once __DIR__ . '/includes/marketplace_helpers.php';
}
if (!function_exists('boutique_adresse_publique')) {
    require_once __DIR__ . '/includes/boutique_vendeur_display.php';
}
$__footer_vd = isset($GLOBALS['BOUTIQUE_VENDEUR_DISPLAY']) && is_array($GLOBALS['BOUTIQUE_VENDEUR_DISPLAY'])
    ? $GLOBALS['BOUTIQUE_VENDEUR_DISPLAY']
    : null;
$__footer_slug = defined('BOUTIQUE_SLUG') ? (string) BOUTIQUE_SLUG : '';
$__footer_is_boutique = ($__footer_slug !== '' && $__footer_vd !== null);
$__footer_nom = $__footer_is_boutique
    ? (trim((string) ($__footer_vd['boutique_nom'] ?? '')) !== '' ? trim((string) $__footer_vd['boutique_nom']) : (defined('BOUTIQUE_NOM') ? BOUTIQUE_NOM : 'Boutique'))
    : '';
$__footer_logo_src = '/image/logo_market.webp';
$__footer_logo_alt = SITE_BRAND_NAME;
if ($__footer_is_boutique && !empty($__footer_vd['boutique_logo'])) {
    $__footer_logo_src = '/upload/' . str_replace('\\', '/', $__footer_vd['boutique_logo']);
    $__footer_logo_alt = $__footer_nom;
}
$__footer_home = $__footer_is_boutique ? boutique_url('index.php', $__footer_slug) : '/index.php';
$__footer_panier = $__footer_is_boutique ? boutique_url('panier.php', $__footer_slug) : '/panier.php';
$__footer_produits = $__footer_is_boutique ? boutique_url('produits.php', $__footer_slug) : '/produits.php';
$__footer_contact = $__footer_is_boutique ? boutique_url('contact.php', $__footer_slug) : '/contact.php';
$__footer_mail = '';
$__footer_addr = '';
if ($__footer_is_boutique) {
    $__footer_mail = trim((string) ($__footer_vd['email'] ?? ''));
    $__footer_addr = boutique_adresse_publique($__footer_vd);
    if ($__footer_mail === '') {
        $__footer_mail = 'contact@colobanes.com';
    }
    if ($__footer_addr === '') {
        $__footer_addr = 'Adresse non renseignée — renseignez-la dans Paramètres (admin).';
    }
} else {
    $__footer_mail = 'contact@colobanes.com';
    $__footer_addr = 'Rond Point Colobane, Dakar, Sénégal';
}

$__footer_social_cfg = file_exists(__DIR__ . '/config/social.php') ? require __DIR__ . '/config/social.php' : [];
$__footer_social_cfg = is_array($__footer_social_cfg) ? $__footer_social_cfg : [];
$__ig_t = trim((string) ($__footer_social_cfg['instagram'] ?? ''));
$__fb_t = trim((string) ($__footer_social_cfg['facebook'] ?? ''));
$__li_t = trim((string) ($__footer_social_cfg['linkedin'] ?? ''));
$__tt_t = trim((string) ($__footer_social_cfg['tiktok'] ?? ''));
$__footer_show_social = $__ig_t !== '' || $__fb_t !== '' || $__li_t !== '' || $__tt_t !== '';
if (!function_exists('app_mobile_version_load')) {
    require_once __DIR__ . '/includes/app_mobile_version.php';
}
$__footer_app_cfg = app_mobile_version_load();
$__footer_store_ios = trim((string) ($__footer_app_cfg['store_ios'] ?? '')) ?: 'https://apps.apple.com/app/id6771130832';
$__footer_store_android = trim((string) ($__footer_app_cfg['store_android'] ?? '')) ?: 'https://play.google.com/store/apps/details?id=com.colobanes.app&hl=fr';
$__footer_logged = isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
$__footer_year = date('Y');
$__footer_social = [];
if ($__fb_t !== '') { $__footer_social[] = ['Facebook', 'fab fa-facebook-f', $__fb_t]; }
if ($__ig_t !== '') { $__footer_social[] = ['Instagram', 'fab fa-instagram', $__ig_t]; }
if ($__tt_t !== '') { $__footer_social[] = ['TikTok', 'fab fa-tiktok', $__tt_t]; }
if ($__li_t !== '') { $__footer_social[] = ['LinkedIn', 'fab fa-linkedin-in', $__li_t]; }

if ($__footer_is_boutique) {
    $__footer_groups = [
        'La boutique' => [
            ['Accueil', $__footer_home],
            ['Produits', $__footer_produits],
            ['Panier', $__footer_panier],
            ['Contact', $__footer_contact],
        ],
    ];
} else {
    $__footer_groups = [
        'Acheter' => [
            ['Tous les produits', '/produits.php'],
            ['Nouveautés', '/nouveautes.php'],
            ['Promotions', '/promo.php'],
            ['Boutiques', '/boutiques.php'],
        ],
        'Mon compte' => $__footer_logged
            ? [
                ['Mon profil', '/user/profil.php'],
                ['Mes commandes', '/user/mes-commandes.php'],
                ['Mes favoris', '/user/favoris.php'],
                ['Déconnexion', '/user/deconnexion.php'],
            ]
            : [
                ['Se connecter', '/choix-connexion.php'],
                ['Créer un compte', '/choix-inscription.php'],
                ['Vendre sur ' . SITE_BRAND_NAME, '/admin/inscription-vendeur.php'],
            ],
        'Aide' => [
            ['Nous contacter', '/contact.php'],
            ['Conditions d\'utilisation', '/conditions-utilisation.php'],
            ['Confidentialité', '/politique-confidentialite.php'],
            ['Suppression de compte', '/politique-suppression-compte.php'],
        ],
    ];
}
?>
<link rel="stylesheet" href="/css/site-footer.css<?php echo asset_version_query(); ?>">
<footer class="footer sf<?php echo $__footer_is_boutique ? ' sf--boutique' : ''; ?>">
    <div class="sf__wrap">
        <div class="sf__main">
            <div class="sf__brand">
                <a href="<?php echo htmlspecialchars($__footer_home, ENT_QUOTES, 'UTF-8'); ?>" class="sf__logo">
                    <img src="<?php echo htmlspecialchars($__footer_logo_src, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="<?php echo htmlspecialchars($__footer_logo_alt, ENT_QUOTES, 'UTF-8'); ?>"
                        width="180"
                        height="48"
                        loading="lazy"
                        decoding="async"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                    <span class="sf__logo-fallback"><?php echo htmlspecialchars($__footer_is_boutique ? $__footer_nom : SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <p class="sf__tagline">
                    <?php echo $__footer_is_boutique
                        ? htmlspecialchars('Boutique partenaire sur ' . SITE_BRAND_NAME . '.', ENT_QUOTES, 'UTF-8')
                        : 'La marketplace de proximité au Sénégal : achetez auprès des boutiques de Colobane et d\'ailleurs, en ligne.'; ?>
                </p>
                <ul class="sf__contact">
                    <li>
                        <i class="fas fa-location-dot" aria-hidden="true"></i>
                        <span><?php echo nl2br(htmlspecialchars($__footer_addr, ENT_QUOTES, 'UTF-8')); ?></span>
                    </li>
                    <li>
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <a href="mailto:<?php echo htmlspecialchars($__footer_mail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($__footer_mail, ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                </ul>
                <?php if (!$__footer_is_boutique && !empty($__footer_social)): ?>
                <div class="sf__social" aria-label="Nos réseaux sociaux">
                    <?php foreach ($__footer_social as $__s): ?>
                        <a href="<?php echo htmlspecialchars($__s[2], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo htmlspecialchars($__s[0], ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($__s[0], ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="<?php echo htmlspecialchars($__s[1], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php foreach ($__footer_groups as $__g_title => $__g_links): ?>
            <details class="sf__group" open>
                <summary class="sf__title"><?php echo htmlspecialchars($__g_title, ENT_QUOTES, 'UTF-8'); ?><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                <ul class="sf__links">
                    <?php foreach ($__g_links as $__l): ?>
                        <li><a href="<?php echo htmlspecialchars($__l[1], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($__l[0], ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endforeach; ?>

            <?php if (!$__footer_is_boutique): ?>
            <div class="sf__app">
                <p class="sf__title sf__title--static">Application mobile</p>
                <p class="sf__app-text">Commandez plus vite et suivez vos livraisons depuis votre téléphone.</p>
                <div class="sf__stores">
                    <a class="sf__store" href="<?php echo htmlspecialchars($__footer_store_ios, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" aria-label="Télécharger sur l'App Store">
                        <i class="fab fa-apple" aria-hidden="true"></i>
                        <span><small>Télécharger dans</small>l'App Store</span>
                    </a>
                    <a class="sf__store" href="<?php echo htmlspecialchars($__footer_store_android, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" aria-label="Disponible sur Google Play">
                        <i class="fab fa-google-play" aria-hidden="true"></i>
                        <span><small>Disponible sur</small>Google Play</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="sf__bottom">
        <div class="sf__wrap sf__bottom-inner">
            <p class="sf__copy">
                © <?php echo htmlspecialchars($__footer_year, ENT_QUOTES, 'UTF-8'); ?>
                <?php echo htmlspecialchars($__footer_is_boutique ? $__footer_nom : SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?>. Tous droits réservés.
            </p>
            <?php if ($__footer_is_boutique): ?>
                <a class="sf__powered" href="/index.php">Propulsé par <strong><?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?></strong></a>
            <?php else: ?>
                <p class="sf__made"><i class="fas fa-location-dot" aria-hidden="true"></i> Dakar, Sénégal</p>
            <?php endif; ?>
        </div>
    </div>
</footer>
<script>(function(){if(!window.matchMedia||!window.matchMedia('(max-width: 767px)').matches){return;}document.querySelectorAll('.sf__group[open]').forEach(function(d){d.removeAttribute('open');});})();</script>
<?php include __DIR__ . '/includes/social_floating.php'; ?>
<?php
$__footer_script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$__footer_load_share = $__footer_script !== ''
    && (
        in_array(basename($__footer_script), ['index.php', 'produits.php', 'nouveautes.php', 'promo.php', 'categorie.php', 'produit.php'], true)
        || strpos($__footer_script, '/boutique/') !== false
    );
if ($__footer_load_share):
?>
<link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
<?php include __DIR__ . '/includes/partials/platform_share_modal.php'; ?>
<script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>" defer></script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/perf_lazy_assets.php'; ?>
<?php
if (session_status() === PHP_SESSION_ACTIVE
    && !empty($_SESSION['user_id'])
    && empty($_SESSION['admin_id'])
    && empty($enable_firebase_notifications)) {
    $enable_firebase_notifications = true;
    $firebase_notify_type = 'user';
}
if (!empty($enable_firebase_notifications)) {
    include __DIR__ . '/includes/firebase_notifications_scripts.php';
}
?>
<?php require_once __DIR__ . '/includes/flash_toast.php'; flash_toast_render(); ?>
<?php require_once __DIR__ . '/includes/panier_added_snackbar.php'; panier_added_snackbar_render(); ?>
<?php
if (!function_exists('app_url')) {
    require_once __DIR__ . '/includes/app_base.php';
}
?>
<script>window.MP_FAVORI_API = <?php echo json_encode(app_url('/api/toggle_favori.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;window.MP_FAVORI_LOGIN = <?php echo json_encode(app_url('/choix-connexion.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;</script>
<script src="<?php echo htmlspecialchars(app_url('/js/mp-favori.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php require __DIR__ . '/includes/partials/app_store_prompt.php'; ?>
<?php /* Invitation au don (au plus tous les 3 jours) */ require __DIR__ . '/includes/partials/dons_prompt.php'; ?>
