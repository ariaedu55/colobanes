<?php
/**
 * Calcul sécurisé des prix panier / commande (jamais faire confiance au POST client)
 */

require_once __DIR__ . '/../models/model_produits.php';

if (!function_exists('panier_compute_catalog_unit_price')) {
    /**
     * @param array<string, mixed> $produit
     * @param array<string, mixed>|null $variante
     */
    function panier_compute_catalog_unit_price(array $produit, $variante, float $surcout_poids, float $surcout_taille): float
    {
        $prix_base = (float) ($produit['prix'] ?? 0);
        if (is_array($variante)) {
            if (!empty($variante['prix_promotion'])
                && (float) $variante['prix_promotion'] > 0
                && (float) $variante['prix_promotion'] < (float) $variante['prix']) {
                $prix_base = (float) $variante['prix_promotion'];
            } else {
                $prix_base = (float) ($variante['prix'] ?? $prix_base);
            }
        } elseif (!empty($produit['prix_promotion'])
            && (float) $produit['prix_promotion'] > 0
            && (float) $produit['prix_promotion'] < (float) $produit['prix']) {
            $prix_base = (float) $produit['prix_promotion'];
        }

        return max(0.0, $prix_base + $surcout_poids + $surcout_taille);
    }
}

if (!function_exists('panier_negociation_price_allowed')) {
    /**
     * Prix panier autorisé s'il correspond à une négociation acceptée du client.
     *
     * @param array<string, mixed> $item
     */
    function panier_negociation_price_allowed(int $user_id, array $item, float $prix): bool
    {
        if ($user_id <= 0 || $prix <= 0) {
            return false;
        }

        if (!function_exists('prix_negociations_table_exists')) {
            require_once __DIR__ . '/../models/model_prix_negociations.php';
        }
        if (!prix_negociations_table_exists()) {
            return false;
        }

        global $db;
        $produit_id = (int) ($item['produit_id'] ?? $item['id'] ?? 0);
        $admin_id = (int) ($item['vendeur_id'] ?? $item['admin_id'] ?? 0);
        if ($produit_id <= 0 || $admin_id <= 0) {
            return false;
        }

        try {
            $stmt = $db->prepare('
                SELECT id FROM prix_negociations
                WHERE user_id = :user_id
                  AND admin_id = :admin_id
                  AND produit_id = :produit_id
                  AND statut IN (\'acceptee\', \'contre_proposee\')
                  AND (
                    ABS(COALESCE(prix_convenu, 0) - :prix) < 1
                    OR ABS(COALESCE(prix_contre_vendeur, 0) - :prix) < 1
                  )
                ORDER BY date_maj DESC, id DESC
                LIMIT 1
            ');
            $stmt->execute([
                'user_id' => $user_id,
                'admin_id' => $admin_id,
                'produit_id' => $produit_id,
                'prix' => $prix,
            ]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('panier_resolve_unit_price_for_line')) {
    /**
     * Prix unitaire fiable pour une ligne panier (catalogue ou négociation validée).
     *
     * @param array<string, mixed> $item
     */
    function panier_resolve_unit_price_for_line(array $item, int $user_id = 0): float
    {
        $produit_id = (int) ($item['produit_id'] ?? $item['id'] ?? 0);
        if ($produit_id <= 0) {
            return 0.0;
        }

        $produit = get_produit_by_id($produit_id);
        if (!$produit) {
            return 0.0;
        }

        $variante = null;
        $variante_id = (int) ($item['panier_variante_id'] ?? $item['variante_id'] ?? 0);
        if ($variante_id > 0) {
            if (!function_exists('get_variante_by_id')) {
                require_once __DIR__ . '/../models/model_variantes.php';
            }
            $v = get_variante_by_id($variante_id);
            if ($v && (int) ($v['produit_id'] ?? 0) === $produit_id) {
                $variante = $v;
            }
        }

        $poids_val = trim((string) ($item['panier_poids'] ?? $item['poids'] ?? ''));
        $taille_val = trim((string) ($item['panier_taille'] ?? $item['taille'] ?? ''));
        $poids_options = parse_options_with_surcharge($produit['poids'] ?? null);
        $taille_options = parse_options_with_surcharge($produit['taille'] ?? null);
        $surcout_poids = get_surcharge_for_option($poids_options, $poids_val);
        $surcout_taille = get_surcharge_for_option($taille_options, $taille_val);

        $catalog = panier_compute_catalog_unit_price($produit, $variante, $surcout_poids, $surcout_taille);

        /* Vente en gros : le palier atteint remplace le prix de base (les surcoûts d'options restent) */
        if (!function_exists('vente_gros_prix_pour_quantite')) {
            require_once __DIR__ . '/produit_vente_gros.php';
        }
        $prix_palier = vente_gros_prix_pour_quantite($produit, (int) ($item['quantite'] ?? 1));
        if ($prix_palier !== null && $variante === null) {
            $remise_gros = vente_gros_prix_detail($produit) - $prix_palier;
            if ($remise_gros > 0) {
                $catalog = max(0.0, round($catalog - $remise_gros, 2));
            }
        }

        $stored = null;
        if (isset($item['panier_prix_unitaire']) && $item['panier_prix_unitaire'] !== null && (float) $item['panier_prix_unitaire'] > 0) {
            $stored = (float) $item['panier_prix_unitaire'];
        }

        if ($stored === null || abs($stored - $catalog) < 0.01) {
            return $catalog;
        }

        // Règle retenue : une négociation vaut pour UNE seule unité.
        // Au-delà d'une unité sur la ligne, on revient au prix catalogue.
        if ((int) ($item['quantite'] ?? 1) > 1) {
            return $catalog;
        }

        if ($user_id > 0 && panier_negociation_price_allowed($user_id, $item, $stored)) {
            return $stored;
        }

        return $catalog;
    }
}

if (!function_exists('panier_line_uses_negociation_price')) {
    /**
     * Vrai si la ligne (ramenée à 1 unité) serait facturée au prix négocié et non au prix catalogue.
     *
     * @param array<string, mixed> $item
     */
    function panier_line_uses_negociation_price(array $item, int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }
        $item['quantite'] = 1;
        $prix_client = panier_resolve_unit_price_for_line($item, $user_id);
        $prix_catalogue = panier_resolve_unit_price_for_line($item, 0);

        return abs($prix_client - $prix_catalogue) >= 0.01;
    }
}

if (!function_exists('panier_compute_line_price_from_post')) {
    /**
     * Calcule le prix catalogue à partir des options POST validées (sans prix client).
     *
     * @param array<string, mixed> $produit
     * @param array<string, mixed>|null $variante
     */
    function panier_compute_line_price_from_post(
        array $produit,
        $variante,
        string $option_poids,
        string $option_taille
    ): float {
        $poids_options = parse_options_with_surcharge($produit['poids'] ?? null);
        $taille_options = parse_options_with_surcharge($produit['taille'] ?? null);
        $surcout_poids = get_surcharge_for_option($poids_options, $option_poids);
        $surcout_taille = get_surcharge_for_option($taille_options, $option_taille);

        return panier_compute_catalog_unit_price($produit, $variante, $surcout_poids, $surcout_taille);
    }
}

if (!function_exists('panier_produit_stock_disponible')) {
    function panier_produit_stock_disponible(int $produit_id): int
    {
        global $db;
        $produit_id = (int) $produit_id;
        if ($produit_id <= 0) {
            return 0;
        }

        try {
            $stmt = $db->prepare('SELECT stock FROM produits WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $produit_id]);

            return max(0, (int) ($stmt->fetchColumn() ?: 0));
        } catch (PDOException $e) {
            return 0;
        }
    }
}

if (!function_exists('panier_item_produit_id')) {
    /**
     * @param array<string, mixed> $item
     */
    function panier_item_produit_id(array $item): int
    {
        if (!empty($item['produit_id'])) {
            return (int) $item['produit_id'];
        }

        return (int) ($item['id'] ?? 0);
    }
}

if (!function_exists('panier_statuts_reservation_stock')) {
    /**
     * Statuts de commande qui réservent du stock (non encore décrémenté en BDD).
     *
     * @return string[]
     */
    function panier_statuts_reservation_stock(): array
    {
        return [
            'en_attente',
            'confirmee',
            'prise_en_charge',
            'en_preparation',
            'expediee',
            'livraison_en_cours',
        ];
    }
}

if (!function_exists('panier_quantite_commandee_en_cours')) {
    /**
     * Quantité déjà engagée par des commandes actives (hors annulées / payées / livrées).
     */
    function panier_quantite_commandee_en_cours(int $produit_id, ?int $exclude_commande_id = null): int
    {
        global $db;
        $produit_id = (int) $produit_id;
        if ($produit_id <= 0) {
            return 0;
        }

        $statuts = panier_statuts_reservation_stock();
        if ($statuts === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($statuts), '?'));
        $sql = '
            SELECT COALESCE(SUM(cp.quantite), 0)
            FROM commande_produits cp
            INNER JOIN commandes c ON c.id = cp.commande_id
            WHERE cp.produit_id = ?
              AND c.statut IN (' . $placeholders . ')
        ';
        $params = array_merge([$produit_id], $statuts);
        if ($exclude_commande_id !== null && (int) $exclude_commande_id > 0) {
            $sql .= ' AND c.id != ?';
            $params[] = (int) $exclude_commande_id;
        }

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return max(0, (int) ($stmt->fetchColumn() ?: 0));
        } catch (PDOException $e) {
            return 0;
        }
    }
}

if (!function_exists('panier_produit_stock_disponible_effectif')) {
    /**
     * Stock réellement disponible à la vente (stock produit − commandes en cours).
     */
    function panier_produit_stock_disponible_effectif(int $produit_id, ?int $exclude_commande_id = null): int
    {
        $stock_db = panier_produit_stock_disponible($produit_id);
        $reserve = panier_quantite_commandee_en_cours($produit_id, $exclude_commande_id);

        return max(0, $stock_db - $reserve);
    }
}

if (!function_exists('panier_aggregate_quantites_par_produit')) {
    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array{quantite:int, nom:string}>
     */
    function panier_aggregate_quantites_par_produit(array $items): array
    {
        $by_product = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $produit_id = panier_item_produit_id($item);
            if ($produit_id <= 0) {
                continue;
            }
            $qty = max(1, (int) ($item['quantite'] ?? 1));
            if (!isset($by_product[$produit_id])) {
                $nom = trim((string) ($item['nom'] ?? 'Produit'));
                $by_product[$produit_id] = ['quantite' => 0, 'nom' => $nom !== '' ? $nom : 'Produit'];
            }
            $by_product[$produit_id]['quantite'] += $qty;
        }

        return $by_product;
    }
}

if (!function_exists('panier_validate_stock_for_cart_items')) {
    /**
     * Vérifie le stock pour un panier (agrégation par produit + réservations commandes).
     *
     * @param array<int, array<string, mixed>> $items
     */
    function panier_validate_stock_for_cart_items(array $items, ?string &$error_message = null): bool
    {
        $error_message = '';
        $by_product = panier_aggregate_quantites_par_produit($items);
        if ($by_product === []) {
            $error_message = 'Aucun produit valide dans le panier.';

            return false;
        }

        return true;
    }
}

if (!function_exists('panier_validate_stock_for_single_line')) {
    /**
     * Vérifie qu'une quantité peut être ajoutée / mise à jour pour un produit.
     *
     * @param array<int, array<string, mixed>> $cart_items Lignes panier existantes (BDD ou session)
     */
    function panier_validate_stock_for_single_line(
        int $produit_id,
        int $quantite_demandee,
        array $cart_items,
        ?int $exclude_line_id = null,
        ?string &$error_message = null
    ): bool {
        $error_message = '';
        $produit_id = (int) $produit_id;
        $quantite_demandee = max(0, (int) $quantite_demandee);
        if ($produit_id <= 0 || $quantite_demandee <= 0) {
            $error_message = 'Quantité invalide.';

            return false;
        }

        $qty_dans_panier = 0;
        foreach ($cart_items as $line_id => $item) {
            if (!is_array($item)) {
                continue;
            }
            $line_key = is_int($line_id) ? $line_id : (int) ($item['panier_id'] ?? 0);
            if ($exclude_line_id !== null && $line_key === (int) $exclude_line_id) {
                continue;
            }
            if (panier_item_produit_id($item) !== $produit_id) {
                continue;
            }
            $qty_dans_panier += max(0, (int) ($item['quantite'] ?? 0));
        }

        return true;
    }
}

if (!function_exists('panier_quantite_deja_au_panier')) {
    function panier_quantite_deja_au_panier(int $user_id, int $produit_id, ?int $exclude_panier_id = null): int
    {
        global $db;
        if ($user_id <= 0 || $produit_id <= 0) {
            return 0;
        }

        try {
            $sql = 'SELECT COALESCE(SUM(quantite), 0) FROM panier WHERE user_id = :user_id AND produit_id = :produit_id';
            $params = ['user_id' => $user_id, 'produit_id' => $produit_id];
            if ($exclude_panier_id !== null && $exclude_panier_id > 0) {
                $sql .= ' AND id != :exclude_id';
                $params['exclude_id'] = (int) $exclude_panier_id;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return max(0, (int) $stmt->fetchColumn());
        } catch (PDOException $e) {
            return 0;
        }
    }
}
