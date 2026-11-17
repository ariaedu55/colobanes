<?php
/**
 * Disponibilité produit côté client.
 */

if (!function_exists('produit_prix_effectif_client')) {
    function produit_prix_effectif_client(array $produit): float
    {
        if (isset($produit['prix_promotion']) && $produit['prix_promotion'] !== '' && $produit['prix_promotion'] !== null) {
            $pp = (float) $produit['prix_promotion'];
            if ($pp > 0) {
                return $pp;
            }
        }
        return (float) ($produit['prix'] ?? 0);
    }
}

if (!function_exists('produit_est_achetable')) {
    /**
     * Côté client : tout produit visible au catalogue peut être commandé
     * (rupture / stock à 0 — le vendeur gère ensuite la commande).
     */
    function produit_est_achetable(array $produit): bool
    {
        if (!function_exists('produit_est_visible_client')) {
            require_once __DIR__ . '/../models/model_produits.php';
        }

        return produit_est_visible_client((string) ($produit['statut'] ?? ''));
    }
}

if (!function_exists('produit_indisponibilite_message')) {
    function produit_indisponibilite_message(array $produit): string
    {
        $statut = (string) ($produit['statut'] ?? '');
        if ($statut === 'rupture_stock' || (int) ($produit['stock'] ?? 0) <= 0) {
            return 'Ce produit est en rupture de stock.';
        }
        if ($statut === 'inactif') {
            return 'Ce produit n’est plus disponible à la vente.';
        }
        return 'Ce produit est momentanément indisponible.';
    }
}

if (!function_exists('panier_ligne_est_disponible')) {
    function panier_ligne_est_disponible(array $item): bool
    {
        if (!function_exists('produit_est_visible_client')) {
            require_once __DIR__ . '/../models/model_produits.php';
        }

        return produit_est_visible_client((string) ($item['statut'] ?? 'actif'));
    }
}
