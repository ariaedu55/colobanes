<?php
require_once __DIR__ . '/includes/session_user.php';
/**
 * Sélection du type de compte à créer (client, vendeur) — même style que la connexion
 */
session_start_persistent();

require_once __DIR__ . '/includes/auth_redirect.php';
auth_redirect_vendeur_to_dashboard();

$redirect = isset($_GET['redirect']) ? trim((string) $_GET['redirect']) : '';
$safe_redirect = preg_match('/^[a-z0-9_-]+$/i', $redirect) ? $redirect : '';
$q = $safe_redirect !== '' ? ('?' . http_build_query(['redirect' => $safe_redirect])) : '';

require_once __DIR__ . '/includes/asset_version.php';
$vq = asset_version_query();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Créer un compte — COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo $vq; ?>">
    <link rel="stylesheet" href="/css/auth-connexion.css<?php echo $vq; ?>">
    <link rel="stylesheet" href="/css/auth-login-v2.css<?php echo $vq; ?>">
    <link rel="stylesheet" href="/css/mc-v2-shop-promo.css<?php echo $vq; ?>">
    <?php require_once __DIR__ . '/includes/ui_font_awesome.php'; ?>
</head>
<body class="auth-page page-choix-inscription al-page">
    <div class="auth-hub-shell">
        <?php
        $mc_v2_shop_promo_id = 'auth-shop-promo-title';
        include __DIR__ . '/includes/mc_v2_shop_promo.php';
        ?>

        <main class="al-wrap">
            <div class="al-card">
                <a class="al-logo" href="/index.php" aria-label="Retour à l'accueil COLObanes">
                    <img src="/image/logo_market.webp" alt="COLObanes" width="220" height="60">
                </a>

                <div class="al-head">
                    <h1 class="al-title">Créer un compte</h1>
                </div>

                <nav class="al-choices" aria-label="Type de compte">
                    <a class="al-choice al-choice--client" href="/user/inscription.php<?php echo $safe_redirect !== '' ? htmlspecialchars($q) : ''; ?>">
                        <span class="al-choice__icon" aria-hidden="true"><i class="fas fa-bag-shopping"></i></span>
                        <span class="al-choice__txt">
                            <span class="al-choice__title">Client</span>
                            <span class="al-choice__sub">Acheter sur COLObanes</span>
                        </span>
                        <i class="fas fa-chevron-right al-choice__chev" aria-hidden="true"></i>
                    </a>
                    <a class="al-choice al-choice--vendor" href="/admin/inscription-vendeur.php">
                        <span class="al-choice__icon" aria-hidden="true"><i class="fas fa-store"></i></span>
                        <span class="al-choice__txt">
                            <span class="al-choice__title">Vendeur</span>
                            <span class="al-choice__sub">Ouvrir ma boutique gratuitement</span>
                        </span>
                        <i class="fas fa-chevron-right al-choice__chev" aria-hidden="true"></i>
                    </a>
                </nav>

                <p class="al-signup">
                    Déjà un compte ?
                    <a href="/choix-connexion.php<?php echo $safe_redirect !== '' ? htmlspecialchars($q) : ''; ?>">Se connecter</a>
                </p>
            </div>
        </main>
    </div>

    <?php include __DIR__ . '/includes/social_floating.php'; ?>
</body>
</html>
