<?php
/**
 * Variables partagées pour le dock mobile (#shopMobileDock).
 * Programmation procédurale uniquement
 */

if (!function_exists('shop_mobile_dock_prepare_vars')) {
    /**
     * @param array{context?: string, nav_compte_href?: string} $opts
     */
    function shop_mobile_dock_prepare_vars(array $opts = []): void
    {
        global $shop_dock_home_href, $shop_dock_home_act, $shop_dock_message_href, $shop_dock_message_act;
        global $shop_dock_panier_act, $shop_dock_compte_act, $shop_dock_compte_href, $shop_dock_compte_label, $shop_dock_compte_icon;
        global $shop_dock_messages_non_lus, $nav_panier_href, $panier_count, $__shop_dock_is_vendeur;
        global $shop_dock_vendeur_admin, $shop_dock_produits_href, $shop_dock_produits_act, $shop_dock_add_href;

        if (!function_exists('app_url')) {
            require_once __DIR__ . '/app_base.php';
        }

        $__shop_dock_is_vendeur = isset($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] > 0
            && (string) ($_SESSION['admin_role'] ?? '') === 'vendeur';

        if (!isset($panier_count)) {
            $panier_count = 0;
            $panier_invite_path = __DIR__ . '/panier_invite.php';
            if (file_exists($panier_invite_path)) {
                require_once $panier_invite_path;
            }
            if (isset($_SESSION['user_id'])) {
                $conn_path = dirname(__DIR__) . '/conn/conn.php';
                if (file_exists($conn_path)) {
                    require_once $conn_path;
                }
                $model_path = dirname(__DIR__) . '/models/model_panier.php';
                if (file_exists($model_path)) {
                    require_once $model_path;
                    $panier_vid = null;
                    if (defined('BOUTIQUE_ADMIN_ID') && (int) BOUTIQUE_ADMIN_ID > 0) {
                        $panier_vid = (int) BOUTIQUE_ADMIN_ID;
                    } elseif (!empty($GLOBALS['nav_panier_count_for_vendeur_id'])) {
                        $panier_vid = (int) $GLOBALS['nav_panier_count_for_vendeur_id'];
                    }
                    if ($panier_vid > 0 && function_exists('count_panier_items_for_vendeur')) {
                        $panier_count = count_panier_items_for_vendeur($_SESSION['user_id'], $panier_vid);
                    } elseif (function_exists('count_panier_items')) {
                        $panier_count = count_panier_items($_SESSION['user_id']);
                    }
                }
            } elseif (function_exists('panier_invite_count_items')) {
                $panier_count = panier_invite_count_items();
            }
        }

        $shop_dock_messages_non_lus = 0;
        $msg_model_path = dirname(__DIR__) . '/models/model_messages_vendeur.php';
        if (file_exists($msg_model_path)) {
            require_once $msg_model_path;
            if (function_exists('messages_vendeur_tables_exist') && messages_vendeur_tables_exist()) {
                if ($__shop_dock_is_vendeur && function_exists('message_vendeur_count_unread_for_vendeur')) {
                    $shop_dock_messages_non_lus = message_vendeur_count_unread_for_vendeur((int) $_SESSION['admin_id']);
                } elseif (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0
                    && function_exists('message_vendeur_count_unread_for_client')) {
                    $shop_dock_messages_non_lus = message_vendeur_count_unread_for_client((int) $_SESSION['user_id']);
                }
            }
        }

        $shop_nav_script = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ($_SERVER['PHP_SELF'] ?? ''));
        $__shop_dock_req_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

        $shop_dock_home_href = app_url('/index.php');
        // Accueil actif aussi sur « / » : on compare le script exécuté à l'index racine de l'app
        $__shop_dock_script_path = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $__shop_dock_home_path = (string) (parse_url($shop_dock_home_href, PHP_URL_PATH) ?: '/index.php');
        $shop_dock_home_act = (defined('BOUTIQUE_ADMIN_ID') && (int) BOUTIQUE_ADMIN_ID > 0)
            ? false
            : ($__shop_dock_script_path === $__shop_dock_home_path
                || ($shop_nav_script === 'index.php' && strpos($__shop_dock_req_uri, '/boutique/') === false));

        // Vendeur dans son espace (admin/) : Accueil · Produits · [+ Ajouter] · Messages · Menu
        $shop_dock_vendeur_admin = $__shop_dock_is_vendeur && strpos($__shop_dock_script_path, '/admin/') !== false;
        if ($shop_dock_vendeur_admin) {
            $shop_dock_home_href = app_url('/admin/dashboard.php');
            $shop_dock_home_act = (strpos($__shop_dock_script_path, '/admin/dashboard.php') !== false);
            $shop_dock_produits_href = app_url('/admin/stock/index.php');
            $shop_dock_produits_act = (strpos($__shop_dock_script_path, '/admin/stock/') !== false
                || strpos($__shop_dock_script_path, '/admin/produits/') !== false);
            $shop_dock_add_href = app_url('/admin/produits/ajouter.php');
        }

        if ($__shop_dock_is_vendeur) {
            $shop_dock_message_href = app_url('/admin/messages.php');
            $shop_dock_message_act = (strpos($__shop_dock_req_uri, '/admin/messages.php') !== false);
            $shop_dock_compte_href = app_url('/admin/dashboard.php');
            $shop_dock_compte_label = 'Boutique';
            $shop_dock_compte_icon = 'fa-solid fa-store';
            $shop_dock_compte_act = (strpos($__shop_dock_req_uri, '/admin/dashboard.php') !== false);
        } else {
            $shop_dock_message_href = app_url('/user/messages.php');
            $shop_dock_message_act = (strpos($__shop_dock_req_uri, '/user/messages.php') !== false);
            $fallback_compte = trim((string) ($opts['nav_compte_href'] ?? '/choix-connexion.php'));
            if ($fallback_compte === '') {
                $fallback_compte = '/choix-connexion.php';
            }
            if ($fallback_compte[0] !== '/') {
                $fallback_compte = '/' . ltrim($fallback_compte, '/');
            }
            $shop_dock_compte_href = app_url($fallback_compte);
            $shop_dock_compte_label = 'Compte';
            $shop_dock_compte_icon = 'fa-regular fa-circle-user';
            $shop_dock_compte_act = (strpos($__shop_dock_req_uri, '/user/') !== false && $shop_nav_script !== 'messages.php')
                || (isset($_SESSION['admin_id'])
                    && (string) ($_SESSION['admin_role'] ?? '') !== 'vendeur'
                    && strpos($__shop_dock_req_uri, '/admin/dashboard.php') !== false)
                || strpos($__shop_dock_req_uri, '/view/profil_commercent.php') !== false;
        }

        if (!isset($nav_panier_href)) {
            $nav_panier_href = isset($_SESSION['user_id'])
                ? app_url($GLOBALS['nav_panier'] ?? '/panier.php')
                : app_url('/panier.php');
        } elseif (strpos((string) $nav_panier_href, 'http') !== 0 && strpos((string) $nav_panier_href, '/') === 0) {
            $nav_panier_href = app_url((string) $nav_panier_href);
        }

        $shop_dock_panier_act = in_array($shop_nav_script, ['panier.php', 'commande.php'], true);
    }
}
