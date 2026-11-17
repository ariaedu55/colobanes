<?php
/**
 * Affichage « Livraison offerte » — uniquement si configuré par le vendeur ou code promo dédié.
 */

if (!function_exists('commande_panier_vendeur_ids')) {
    /**
     * @param array<int, array<string, mixed>> $panier_items
     * @param int|null $limit_vendeur_id
     * @return array<int, int>
     */
    function commande_panier_vendeur_ids(array $panier_items, $limit_vendeur_id = null)
    {
        if (!function_exists('panier_item_effective_vendeur_id')) {
            require_once __DIR__ . '/../models/model_panier.php';
        }

        $limit_vendeur_id = $limit_vendeur_id !== null ? (int) $limit_vendeur_id : 0;
        $ids = [];

        foreach ($panier_items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $vid = panier_item_effective_vendeur_id($item);
            if ($vid <= 0) {
                continue;
            }
            if ($limit_vendeur_id > 0 && $vid !== $limit_vendeur_id) {
                continue;
            }
            $ids[$vid] = $vid;
        }

        return array_values($ids);
    }
}

if (!function_exists('commande_vendeur_a_colonne_livraison_gratuite')) {
    function commande_vendeur_a_colonne_livraison_gratuite()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        global $db;
        if (!$db) {
            $cache = false;
            return false;
        }
        try {
            $stmt = $db->query("SHOW COLUMNS FROM admin LIKE 'boutique_livraison_gratuite'");
            $cache = ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
            return (bool) $cache;
        } catch (PDOException $e) {
            $cache = false;
            return false;
        }
    }
}

if (!function_exists('commande_vendeur_livraison_gratuite_active')) {
    /**
     * Le vendeur a activé la livraison gratuite (option boutique ou zones à 0 FCFA).
     *
     * @param int $admin_id
     * @return bool
     */
    function commande_vendeur_livraison_gratuite_active($admin_id)
    {
        $admin_id = (int) $admin_id;
        if ($admin_id <= 0) {
            return false;
        }

        if (commande_vendeur_a_colonne_livraison_gratuite()) {
            if (!function_exists('get_admin_by_id')) {
                require_once __DIR__ . '/../models/model_admin.php';
            }
            $adm = get_admin_by_id($admin_id);
            if ($adm && !empty($adm['boutique_livraison_gratuite'])) {
                return true;
            }
        }

        if (!function_exists('get_all_zones_livraison')) {
            require_once __DIR__ . '/../models/model_zones_livraison.php';
        }

        $zones = get_all_zones_livraison('actif', $admin_id);
        if (!is_array($zones) || $zones === []) {
            return false;
        }

        foreach ($zones as $zone) {
            if ((float) ($zone['prix_livraison'] ?? 0) > 0) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('commande_panier_promo_livraison_gratuite')) {
    /**
     * Code promo en session offrant la livraison gratuite.
     */
    function commande_panier_promo_livraison_gratuite()
    {
        if (!function_exists('panier_code_promo_session_get')) {
            require_once __DIR__ . '/panier_code_promo.php';
        }

        $promo = panier_code_promo_session_get();
        if (!is_array($promo)) {
            return false;
        }

        if (!empty($promo['livraison_gratuite'])) {
            return true;
        }

        return (string) ($promo['type_remise'] ?? '') === 'livraison_gratuite';
    }
}

if (!function_exists('commande_affiche_badge_livraison_offerte')) {
    /**
     * @param array<int, array<string, mixed>> $panier_items
     * @param int|null $limit_vendeur_id
     * @return bool
     */
    function commande_affiche_badge_livraison_offerte(array $panier_items, $limit_vendeur_id = null)
    {
        if (commande_panier_promo_livraison_gratuite()) {
            return true;
        }

        $vendeur_ids = commande_panier_vendeur_ids($panier_items, $limit_vendeur_id);
        if ($vendeur_ids === []) {
            return false;
        }

        foreach ($vendeur_ids as $vid) {
            if (!commande_vendeur_livraison_gratuite_active($vid)) {
                return false;
            }
        }

        return true;
    }
}
