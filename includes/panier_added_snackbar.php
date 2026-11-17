<?php
/**
 * Snackbar léger « Ajouté au panier » (apparaît puis disparaît).
 * Avec le produit ajouté, un panneau propose ensuite des produits complémentaires choisis par l'IA
 * (api/suggestions_complementaires.php) ; sur la page panier, le bloc « Complétez votre achat » s'en charge.
 */

if (!function_exists('panier_added_snackbar_mark')) {
    function panier_added_snackbar_mark(int $produit_id = 0): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!function_exists('session_start_persistent')) {
                require_once __DIR__ . '/session_user.php';
            }
            session_start_persistent();
        }
        $_SESSION['panier_added_snackbar'] = 1;
        $_SESSION['panier_added_produit_id'] = $produit_id > 0 ? $produit_id : 0;
    }
}

if (!function_exists('panier_added_snackbar_should_show')) {
    function panier_added_snackbar_should_show(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!function_exists('session_start_persistent')) {
                require_once __DIR__ . '/session_user.php';
            }
            session_start_persistent();
        }
        if (empty($_SESSION['panier_added_snackbar'])) {
            return false;
        }
        unset($_SESSION['panier_added_snackbar']);

        return true;
    }
}

if (!function_exists('panier_added_snackbar_render')) {
    function panier_added_snackbar_render(): void
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        if (!panier_added_snackbar_should_show()) {
            return;
        }
        $produit_id = (int) ($_SESSION['panier_added_produit_id'] ?? 0);
        unset($_SESSION['panier_added_produit_id']);

        if (!function_exists('asset_version_query')) {
            require_once __DIR__ . '/asset_version.php';
        }
        if (!function_exists('app_url')) {
            require_once __DIR__ . '/app_base.php';
        }

        $v = asset_version_query();
        /* Le panier affiche déjà ses suggestions : pas de panneau en double */
        $page = basename((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
        if ($produit_id > 0 && $page !== 'panier.php') {
            echo '<link rel="stylesheet" href="' . htmlspecialchars(app_url('/css/panier-accessoires.css' . $v), ENT_QUOTES, 'UTF-8') . '">' . "\n";
            echo '<script>window.__PANIER_AJOUT__=' . json_encode([
                'produitId' => $produit_id,
                'api' => app_url('/api/suggestions_complementaires.php'),
            ], JSON_UNESCAPED_SLASHES) . ';</script>' . "\n";
            echo '<script src="' . htmlspecialchars(app_url('/js/panier-accessoires.js' . $v), ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
        }
        echo '<link rel="stylesheet" href="' . htmlspecialchars(app_url('/css/panier-added-snackbar.css' . $v), ENT_QUOTES, 'UTF-8') . '">' . "\n";
        echo '<script src="' . htmlspecialchars(app_url('/js/panier-added-snackbar.js' . $v), ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
    }
}
