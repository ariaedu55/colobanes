<?php
/**
 * Boutiques marketplace — liste publique, recherche, filtre proximité.
 * Programmation procédurale uniquement.
 */

if (!function_exists('marketplace_boutiques_search_where')) {
    /**
     * @param array<string, mixed> $params
     */
    function marketplace_boutiques_search_where(string $search, ?string $country, array &$params, int $boutique_type_id = 0): string
    {
        $parts = [
            "a.role = 'vendeur'",
            "a.statut = 'actif'",
            "a.boutique_slug IS NOT NULL",
            "TRIM(a.boutique_slug) <> ''",
            "TRIM(COALESCE(a.boutique_nom, '')) <> ''",
        ];

        if ($country !== null && $country !== '') {
            if (!function_exists('geo_column_exists')) {
                require_once dirname(__DIR__) . '/includes/geo_location_service.php';
            }
            if (geo_column_exists('admin', 'boutique_country')) {
                $parts[] = 'a.boutique_country = :mp_country';
                $params['mp_country'] = strtoupper(trim($country));
            }
        }

        if (!function_exists('marketplace_region_filter_applies')) {
            require_once dirname(__DIR__) . '/includes/marketplace_region_filter.php';
        }
        if (marketplace_region_filter_applies()) {
            $region_code = marketplace_get_selected_region_code();
            if ($region_code !== null && $region_code !== '') {
                $parts[] = 'a.boutique_region = :mp_region_filter';
                $params['mp_region_filter'] = $region_code;
            }
        }

        $search = trim($search);
        if ($search !== '') {
            $parts[] = '(
                a.boutique_nom LIKE :mp_q1
                OR a.boutique_slug LIKE :mp_q2
                OR COALESCE(a.boutique_region, \'\') LIKE :mp_q3
                OR COALESCE(a.boutique_adresse, \'\') LIKE :mp_q4
            )';
            $like = '%' . $search . '%';
            $params['mp_q1'] = $like;
            $params['mp_q2'] = $like;
            $params['mp_q3'] = $like;
            $params['mp_q4'] = $like;
        }

        $boutique_type_id = (int) $boutique_type_id;
        if ($boutique_type_id > 0) {
            if (!function_exists('admin_has_boutique_type_id_column')) {
                require_once dirname(__DIR__) . '/models/model_admin.php';
            }
            if (admin_has_boutique_type_id_column()) {
                $parts[] = 'a.boutique_type_id = :mp_boutique_type_id';
                $params['mp_boutique_type_id'] = $boutique_type_id;
            }
        }

        return implode(' AND ', $parts);
    }
}

if (!function_exists('marketplace_count_boutiques')) {
    function marketplace_count_boutiques(
        string $search = '',
        ?string $country = null,
        ?float $lat = null,
        ?float $lng = null,
        float $rayon_km = 0.0,
        bool $proche_only = false,
        int $boutique_type_id = 0
    ): int {
        global $db;

        if (!isset($db) || !($db instanceof PDO)) {
            return 0;
        }


        if (!function_exists('geo_boutiques_ready')) {
            require_once dirname(__DIR__) . '/includes/geo_location_service.php';
        }

        $params = [];
        $where = marketplace_boutiques_search_where($search, $country, $params, $boutique_type_id);

        $geo_sql = '';
        $having = '';
        if ($proche_only || ($lat !== null && $lng !== null && geo_coords_valid($lat, $lng))) {
            if (!geo_boutiques_ready() || $lat === null || $lng === null || !geo_coords_valid($lat, $lng)) {
                return $proche_only ? 0 : marketplace_count_boutiques($search, $country, null, null, 0, false, $boutique_type_id);
            }

            $distance_expr = geo_sql_distance_expr('a.boutique_latitude', 'a.boutique_longitude');
            $geo_sql = ", $distance_expr AS distance_km";
            $where .= '
                AND a.boutique_latitude IS NOT NULL
                AND a.boutique_longitude IS NOT NULL
            ';

            if ($rayon_km > 0) {
                $having = ' HAVING distance_km <= :mp_rayon ';
                $params['mp_rayon'] = $rayon_km;
            }

            $params['geo_lat'] = $lat;
            $params['geo_lat2'] = $lat;
            $params['geo_lng'] = $lng;
        }

        if ($proche_only && $geo_sql === '') {
            return 0;
        }

        try {
            if ($geo_sql !== '') {
                $sql = "
                    SELECT COUNT(*) FROM (
                        SELECT a.id $geo_sql
                        FROM admin a
                        WHERE $where
                        $having
                    ) AS mp_b_cnt
                ";
            } else {
                $sql = "SELECT COUNT(*) FROM admin a WHERE $where";
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('[marketplace_count_boutiques] ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('marketplace_list_boutiques')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function marketplace_list_boutiques(
        string $search = '',
        int $limit = 24,
        int $offset = 0,
        ?string $country = null,
        ?float $lat = null,
        ?float $lng = null,
        float $rayon_km = 0.0,
        bool $proche_only = false,
        int $boutique_type_id = 0
    ): array {
        global $db;

        if (!isset($db) || !($db instanceof PDO)) {
            return [];
        }

        $limit = max(1, min($limit, 250));
        $offset = max(0, $offset);

        if (!function_exists('geo_boutiques_ready')) {
            require_once dirname(__DIR__) . '/includes/geo_location_service.php';
        }

        $params = [];
        $where = marketplace_boutiques_search_where($search, $country, $params, $boutique_type_id);

        $distance_select = 'NULL AS distance_km';
        $having = '';
        $cert_order = '';
        if (file_exists(dirname(__DIR__) . '/models/model_vendeur_certification.php')) {
            require_once dirname(__DIR__) . '/models/model_vendeur_certification.php';
            if (vendeur_certification_admin_column_exists()) {
                $cert_order = vendeur_certification_sql_score_expr('a') . ' DESC, ';
            }
        }
        $order = $cert_order . 'a.boutique_nom ASC, a.id ASC';

        if ($proche_only || ($lat !== null && $lng !== null && geo_coords_valid($lat, $lng))) {
            if (!geo_boutiques_ready() || $lat === null || $lng === null || !geo_coords_valid($lat, $lng)) {
                return $proche_only
                    ? []
                    : marketplace_list_boutiques($search, $limit, $offset, $country, null, null, 0, false, $boutique_type_id);
            }

            $distance_expr = geo_sql_distance_expr('a.boutique_latitude', 'a.boutique_longitude');
            $distance_select = "$distance_expr AS distance_km";
            $where .= '
                AND a.boutique_latitude IS NOT NULL
                AND a.boutique_longitude IS NOT NULL
            ';
            $order = 'distance_km ASC, ' . $cert_order . 'a.boutique_nom ASC';

            if ($rayon_km > 0) {
                $having = ' HAVING distance_km <= :mp_rayon ';
                $params['mp_rayon'] = $rayon_km;
            }

            $params['geo_lat'] = $lat;
            $params['geo_lat2'] = $lat;
            $params['geo_lng'] = $lng;
        }

        if ($proche_only && $distance_select === 'NULL AS distance_km') {
            return [];
        }

        $nb_produits_select = '0 AS nb_produits';
        try {
            $chk = $db->query("SHOW TABLES LIKE 'produits'");
            if ($chk && $chk->fetchColumn()) {
                $nb_produits_select = "(
                    SELECT COUNT(p.id)
                    FROM produits p
                    WHERE p.admin_id = a.id
                    AND p.statut IN ('actif', 'rupture_stock')
                ) AS nb_produits";
            }
        } catch (PDOException $e) {
            /* garde 0 par défaut */
        }

        $type_select = '';
        if (function_exists('admin_has_boutique_type_id_column') && admin_has_boutique_type_id_column()) {
            $type_select = 'a.boutique_type_id,';
        } elseif (!function_exists('admin_has_boutique_type_id_column')) {
            require_once dirname(__DIR__) . '/models/model_admin.php';
            if (admin_has_boutique_type_id_column()) {
                $type_select = 'a.boutique_type_id,';
            }
        }

        $cert_select = '';
        if (function_exists('vendeur_certification_admin_column_exists') && vendeur_certification_admin_column_exists()) {
            $cert_select = 'a.certification_niveau,';
        }

        try {
            $sql = "
                SELECT
                    a.id,
                    a.boutique_slug,
                    a.boutique_nom,
                    $cert_select
                    a.boutique_logo,
                    a.boutique_adresse,
                    a.boutique_region,
                    a.boutique_latitude,
                    a.boutique_longitude,
                    a.boutique_couleur_principale,
                    a.boutique_couleur_accent,
                    a.telephone,
                    $type_select
                    $nb_produits_select,
                    $distance_select
                FROM admin a
                WHERE $where
                $having
                ORDER BY $order
                LIMIT $limit OFFSET $offset
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (PDOException $e) {
            error_log('[marketplace_list_boutiques] ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('marketplace_boutiques_featured')) {
    /**
     * Boutiques mises en avant sur l'accueil (ordre aléatoire à chaque chargement).
     *
     * @return array<int, array<string, mixed>>
     */
    function marketplace_boutiques_featured(int $limit = 6, ?string $country = null, bool $logo_only = false): array
    {
        $limit = max(1, min($limit, 12));
        $pool = max($limit * 4, 48);
        $all = marketplace_list_boutiques('', $pool, 0, $country, null, null, 0, false);

        if ($logo_only) {
            if (!function_exists('marketplace_boutique_has_logo')) {
                require_once dirname(__DIR__) . '/includes/marketplace_boutique_card_helpers.php';
            }
            $all = array_values(array_filter($all, 'marketplace_boutique_has_logo'));
        }

        if ($all === []) {
            return [];
        }

        if (!function_exists('vendeur_certification_niveau_ordre')) {
            require_once dirname(__DIR__) . '/models/model_vendeur_certification.php';
        }
        $tiers = [3 => [], 2 => [], 1 => [], 0 => []];
        foreach ($all as $row) {
            $tier = vendeur_certification_niveau_ordre((string) ($row['certification_niveau'] ?? ''));
            if (!isset($tiers[$tier])) {
                $tier = 0;
            }
            $tiers[$tier][] = $row;
        }
        $picked = [];
        foreach ([3, 2, 1, 0] as $tier) {
            if (empty($tiers[$tier])) {
                continue;
            }
            shuffle($tiers[$tier]);
            foreach ($tiers[$tier] as $row) {
                $picked[] = $row;
                if (count($picked) >= $limit) {
                    return $picked;
                }
            }
        }
        return $picked;
    }
}

if (!function_exists('marketplace_boutiques_top_ventes')) {
    /**
     * Boutiques avec le plus de ventes (quantités commandées), logo obligatoire.
     *
     * @return array<int, array<string, mixed>>
     */
    function marketplace_boutiques_top_ventes(int $limit = 6, ?string $country = null): array
    {
        global $db;

        if (!isset($db) || !($db instanceof PDO)) {
            return [];
        }

        $limit = max(1, min(12, $limit));
        $params = [];
        $where = marketplace_boutiques_search_where('', $country, $params, 0);
        $where .= " AND TRIM(COALESCE(a.boutique_logo, '')) <> ''";

        $cert_select = '';
        if (file_exists(dirname(__DIR__) . '/models/model_vendeur_certification.php')) {
            require_once dirname(__DIR__) . '/models/model_vendeur_certification.php';
            if (function_exists('vendeur_certification_admin_column_exists') && vendeur_certification_admin_column_exists()) {
                $cert_select = 'a.certification_niveau,';
            }
        }

        $type_select = '';
        if (!function_exists('admin_has_boutique_type_id_column')) {
            require_once dirname(__DIR__) . '/models/model_admin.php';
        }
        if (function_exists('admin_has_boutique_type_id_column') && admin_has_boutique_type_id_column()) {
            $type_select = 'a.boutique_type_id,';
        }

        $nb_produits_select = '0 AS nb_produits';
        try {
            $chk = $db->query("SHOW TABLES LIKE 'produits'");
            if ($chk && $chk->fetchColumn()) {
                $nb_produits_select = "(
                    SELECT COUNT(p.id)
                    FROM produits p
                    WHERE p.admin_id = a.id
                    AND p.statut IN ('actif', 'rupture_stock')
                ) AS nb_produits";
            }
        } catch (PDOException $e) {
        }

        try {
            $sql = "
                SELECT
                    a.id,
                    a.boutique_slug,
                    a.boutique_nom,
                    $cert_select
                    a.boutique_logo,
                    a.boutique_adresse,
                    a.boutique_region,
                    a.boutique_latitude,
                    a.boutique_longitude,
                    a.boutique_couleur_principale,
                    a.boutique_couleur_accent,
                    a.telephone,
                    $type_select
                    $nb_produits_select,
                    NULL AS distance_km,
                    COALESCE(s.qte_vendue, 0) AS qte_vendue
                FROM admin a
                LEFT JOIN (
                    SELECT p.admin_id, SUM(cp.quantite) AS qte_vendue
                    FROM commande_produits cp
                    INNER JOIN commandes co ON co.id = cp.commande_id AND co.statut <> 'annulee'
                    INNER JOIN produits p ON p.id = cp.produit_id
                    GROUP BY p.admin_id
                ) s ON s.admin_id = a.id
                WHERE $where
                ORDER BY qte_vendue DESC, nb_produits DESC, a.boutique_nom ASC, a.id ASC
                LIMIT " . (int) $limit;
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $rows = is_array($rows) ? $rows : [];

            if (count($rows) >= $limit) {
                return $rows;
            }

            if (!function_exists('marketplace_boutiques_featured')) {
                return $rows;
            }

            $seen = [];
            foreach ($rows as $r) {
                $seen[(int) ($r['id'] ?? 0)] = true;
            }
            $extra = marketplace_boutiques_featured($limit, $country, true);
            foreach ($extra as $r) {
                $id = (int) ($r['id'] ?? 0);
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $rows[] = $r;
                if (count($rows) >= $limit) {
                    break;
                }
            }

            return $rows;
        } catch (PDOException $e) {
            error_log('[marketplace_boutiques_top_ventes] ' . $e->getMessage());
            if (function_exists('marketplace_boutiques_featured')) {
                return marketplace_boutiques_featured($limit, $country, true);
            }
            return [];
        }
    }
}
