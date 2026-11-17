<?php
/**
 * Recommandations produits similaires — score multi-critères.
 * Catégorie / rayon, proximité de prix, marque (si colonne), région boutique,
 * popularité (vues, achats), affinité visiteur (vues / achats passés).
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/catalogue_shuffle.php';

/**
 * @param array<string, mixed> $produit
 */
function produits_similaires_prix_effectif(array $produit): float
{
    if (isset($produit['prix_promotion']) && $produit['prix_promotion'] !== '' && $produit['prix_promotion'] !== null) {
        $pp = (float) $produit['prix_promotion'];
        if ($pp > 0) {
            return $pp;
        }
    }
    return (float) ($produit['prix'] ?? 0);
}

/**
 * @return string
 */
function produits_similaires_marque_valeur(array $produit): string
{
    foreach (['marque', 'brand', 'fabricant'] as $col) {
        if (function_exists('produits_has_column') && produits_has_column($col)) {
            $v = trim((string) ($produit[$col] ?? ''));
            if ($v !== '') {
                return mb_strtolower($v, 'UTF-8');
            }
        }
    }
    return '';
}

/**
 * @return array<int, int> produit_id => count
 */
function produits_similaires_co_achat_counts(int $produit_id, int $limit = 25): array
{
    global $db;
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0) {
        return [];
    }
    $limit = max(1, min(50, $limit));
    try {
        $st = $db->prepare("
            SELECT cp2.produit_id AS pid, COUNT(*) AS n
            FROM commande_produits cp1
            INNER JOIN commande_produits cp2 ON cp2.commande_id = cp1.commande_id AND cp2.produit_id != cp1.produit_id
            INNER JOIN commandes c ON c.id = cp1.commande_id AND c.statut NOT IN ('annulee')
            WHERE cp1.produit_id = :pid
            GROUP BY cp2.produit_id
            ORDER BY n DESC
            LIMIT {$limit}
        ");
        $st->execute(['pid' => $produit_id]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $pid = (int) ($row['pid'] ?? 0);
            if ($pid > 0) {
                $out[$pid] = (int) ($row['n'] ?? 0);
            }
        }
        return $out;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return array<int, true>
 */
function produits_similaires_actor_produits_vus(?int $user_id, ?string $visiteur_key, int $jours = 45): array
{
    if (!function_exists('client_comportement_ensure_tables') || !client_comportement_ensure_tables()) {
        return [];
    }
    global $db;
    $jours = max(7, min(120, $jours));
    $visiteur_key = $visiteur_key !== null ? trim($visiteur_key) : '';
    try {
        if ($user_id !== null && $user_id > 0) {
            $st = $db->prepare("
                SELECT DISTINCT produit_id FROM client_evenements
                WHERE user_id = :uid AND produit_id IS NOT NULL
                  AND type IN ('vue_produit', 'clic_produit')
                  AND date_evenement >= DATE_SUB(NOW(), INTERVAL {$jours} DAY)
            ");
            $st->execute(['uid' => $user_id]);
        } elseif ($visiteur_key !== '') {
            $st = $db->prepare("
                SELECT DISTINCT produit_id FROM client_evenements
                WHERE visiteur_key = :vk AND produit_id IS NOT NULL
                  AND type IN ('vue_produit', 'clic_produit')
                  AND date_evenement >= DATE_SUB(NOW(), INTERVAL {$jours} DAY)
            ");
            $st->execute(['vk' => $visiteur_key]);
        } else {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $pid = (int) ($row['produit_id'] ?? 0);
            if ($pid > 0) {
                $out[$pid] = true;
            }
        }
        return $out;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function produits_similaires_fetch_candidats_pool(
    int $exclude_id,
    int $generale_id,
    int $categorie_id,
    int $pool_limit = 80
): array {
    global $db;
    $pool_limit = max(12, min(120, $pool_limit));
    $exclude_id = (int) $exclude_id;
    if ($exclude_id <= 0) {
        return [];
    }

    $conds = [];
    $exec_params = [$exclude_id];
    if ($generale_id > 0 && produits_has_column('categorie_generale_id')) {
        $conds[] = 'p.categorie_generale_id = ?';
        $exec_params[] = $generale_id;
    }
    if ($categorie_id > 0) {
        $conds[] = 'p.categorie_id = ?';
        $exec_params[] = $categorie_id;
    }
    if ($generale_id > 0 && function_exists('categorie_generale_leaf_category_ids')) {
        require_once __DIR__ . '/../models/model_categories.php';
        if (function_exists('categories_has_categorie_generale_id_column') && categories_has_categorie_generale_id_column()) {
            $leaf_ids = categorie_generale_leaf_category_ids($generale_id);
            if (!empty($leaf_ids)) {
                $ph = implode(',', array_fill(0, count($leaf_ids), '?'));
                $conds[] = "p.categorie_id IN ($ph)";
                foreach ($leaf_ids as $lid) {
                    $exec_params[] = (int) $lid;
                }
            }
        }
    }
    if ($conds === []) {
        return [];
    }

    $region_select = ', NULL AS vendeur_boutique_region';
    $vj = produits_sql_vendeur_fragment();
    require_once __DIR__ . '/../models/model_admin.php';
    if (function_exists('admin_has_boutique_region_column') && admin_has_boutique_region_column()
        && strpos($vj['join'], 'vend') !== false) {
        $region_select = ', vend.boutique_region AS vendeur_boutique_region';
    }

    $marque_cols = '';
    foreach (['marque', 'brand', 'fabricant'] as $col) {
        if (produits_has_column($col)) {
            $marque_cols .= ', p.' . $col;
        }
    }

    $vues_sql = '0 AS nb_vues_90';
    $achats_sql = '0 AS nb_achats_180';
    require_once __DIR__ . '/schema_client_comportement.php';
    if (function_exists('client_comportement_table_exists') && client_comportement_table_exists('client_evenements')) {
        $vues_sql = "(SELECT COUNT(*) FROM client_evenements ce
            WHERE ce.produit_id = p.id AND ce.type IN ('vue_produit','clic_produit')
              AND ce.date_evenement >= DATE_SUB(NOW(), INTERVAL 90 DAY)) AS nb_vues_90";
    }
    $achats_sql = "(SELECT COUNT(*) FROM commande_produits cp
        INNER JOIN commandes cmd ON cmd.id = cp.commande_id AND cmd.statut NOT IN ('annulee')
        WHERE cp.produit_id = p.id AND cmd.date_commande >= DATE_SUB(NOW(), INTERVAL 180 DAY)) AS nb_achats_180";

    $where_or = '(' . implode(' OR ', array_unique($conds)) . ')';
    $where = "p.statut IN ('actif', 'rupture_stock') AND p.id != ? AND $where_or";
    produits_append_region_positional($where, $exec_params, null);

    try {
        $sql = "
            SELECT p.*, c.nom AS categorie_nom, {$vues_sql}, {$achats_sql}
                " . $vj['select'] . $region_select . $marque_cols . "
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            " . $vj['join'] . "
            WHERE $where
            ORDER BY p.date_creation DESC
            LIMIT {$pool_limit}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($exec_params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        error_log('[produits_similaires_fetch_candidats_pool] ' . $e->getMessage());
        return [];
    }
}

/**
 * @param array<string, mixed> $source
 * @param array<string, mixed> $candidat
 * @param array<string, mixed> $ctx
 */
function produits_similaires_score_ligne(array $source, array $candidat, array $ctx): float
{
    $score = 0.0;
    $src_cat = (int) ($source['categorie_id'] ?? 0);
    $cand_cat = (int) ($candidat['categorie_id'] ?? 0);
    $src_gen = (int) ($source['categorie_generale_id'] ?? 0);
    $cand_gen = (int) ($candidat['categorie_generale_id'] ?? 0);

    if ($src_cat > 0 && $cand_cat === $src_cat) {
        $score += 38;
    } elseif ($src_gen > 0 && $cand_gen === $src_gen) {
        $score += 24;
    } elseif ($src_cat > 0 && $cand_cat > 0) {
        $score += 8;
    }

    $src_prix = produits_similaires_prix_effectif($source);
    $cand_prix = produits_similaires_prix_effectif($candidat);
    if ($src_prix > 0 && $cand_prix > 0) {
        $ratio = abs($cand_prix - $src_prix) / $src_prix;
        if ($ratio <= 0.12) {
            $score += 30;
        } elseif ($ratio <= 0.3) {
            $score += 20;
        } elseif ($ratio <= 0.55) {
            $score += 10;
        }
    }

    $src_marque = produits_similaires_marque_valeur($source);
    $cand_marque = produits_similaires_marque_valeur($candidat);
    if ($src_marque !== '' && $cand_marque !== '' && $src_marque === $cand_marque) {
        $score += 22;
    }

    $src_region = trim((string) ($source['vendeur_boutique_region'] ?? $source['boutique_region'] ?? ''));
    $cand_region = trim((string) ($candidat['vendeur_boutique_region'] ?? ''));
    if ($src_region !== '' && $cand_region !== '' && $src_region === $cand_region) {
        $score += 14;
    }

    $vues = (int) ($candidat['nb_vues_90'] ?? 0);
    $score += min(18.0, log($vues + 1) * 4.5);

    $achats = (int) ($candidat['nb_achats_180'] ?? 0);
    $score += min(22.0, log($achats + 1) * 5.5);

    $cand_id = (int) ($candidat['id'] ?? 0);
    $co = $ctx['co_achat'] ?? [];
    if ($cand_id > 0 && isset($co[$cand_id])) {
        $score += min(28.0, 12 + (int) $co[$cand_id] * 2);
    }

    $vus = $ctx['actor_vus'] ?? [];
    if ($cand_id > 0 && isset($vus[$cand_id])) {
        $score += 6;
    }

    if (($candidat['statut'] ?? '') === 'actif') {
        $score += 4;
    }

    return $score;
}

/**
 * @param array<string, mixed> $produit Ligne produit courante (get_produit_by_id)
 * @return array<int, array<string, mixed>>
 */
function get_produits_similaires_recommandes(
    array $produit,
    int $generale_id,
    int $limit = 8,
    int $pool = 80
): array {
    $produit_id = (int) ($produit['id'] ?? 0);
    if ($produit_id <= 0) {
        return [];
    }
    $limit = max(1, min(24, $limit));
    $pool = max($limit * 3, min(120, $pool));
    $categorie_id = (int) ($produit['categorie_id'] ?? 0);

    if ($generale_id <= 0 && $categorie_id > 0 && function_exists('get_categorie_by_id')) {
        require_once __DIR__ . '/../models/model_categories.php';
        $cat = get_categorie_by_id($categorie_id);
        if (is_array($cat) && !empty($cat['categorie_generale_id'])) {
            $generale_id = (int) $cat['categorie_generale_id'];
        }
    }

    $source = $produit;
    if (empty($source['vendeur_boutique_region']) && !empty($source['admin_id'])) {
        require_once __DIR__ . '/../models/model_admin.php';
        if (function_exists('get_admin_by_id')) {
            $adm = get_admin_by_id((int) $source['admin_id']);
            if (is_array($adm)) {
                $source['vendeur_boutique_region'] = $adm['boutique_region'] ?? '';
            }
        }
    }

    $candidats = produits_similaires_fetch_candidats_pool($produit_id, $generale_id, $categorie_id, $pool);
    if ($candidats === []) {
        return [];
    }

    require_once __DIR__ . '/client_tracking.php';
    $actor = client_tracking_actor();
    $ctx = [
        'co_achat' => produits_similaires_co_achat_counts($produit_id),
        'actor_vus' => produits_similaires_actor_produits_vus($actor['user_id'], $actor['visiteur_key']),
    ];

    $scored = [];
    foreach ($candidats as $row) {
        if ((int) ($row['id'] ?? 0) === $produit_id) {
            continue;
        }
        $scored[] = [
            'score' => produits_similaires_score_ligne($source, $row, $ctx),
            'row' => $row,
        ];
    }
    if ($scored === []) {
        return [];
    }

    usort($scored, static function ($a, $b) {
        $d = ($b['score'] <=> $a['score']);
        if ($d !== 0) {
            return $d;
        }
        $da = strtotime((string) ($a['row']['date_creation'] ?? '')) ?: 0;
        $db = strtotime((string) ($b['row']['date_creation'] ?? '')) ?: 0;
        return $db <=> $da;
    });

    $top = array_slice($scored, 0, max($limit * 2, $limit + 4));
    $rows = array_map(static function ($item) {
        return $item['row'];
    }, $top);

    $visiteur = catalogue_shuffle_visiteur_cle();
    $seed = abs(crc32($visiteur . '|similaires-score|' . $produit_id)) % 2147483645 + 1;

    if (count($rows) > $limit) {
        $head = array_slice($rows, 0, (int) ceil($limit * 0.65));
        $tail = array_slice($rows, (int) ceil($limit * 0.65));
        $tail = catalogue_melanger_produits($tail, $seed);
        $rows = array_merge($head, $tail);
    }

    return catalogue_tirer_produits_similaires($rows, $produit_id, $limit);
}
