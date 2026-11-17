<?php
/**
 * Tirage aléatoire sur une liste de produits « qualifiés », ou catalogue complet
 * si le volume ne dépasse pas le seuil (règle métier : > 5 produits = garde le mode « intelligent »).
 *
 * @param array $produits_source Produits ciblés (recherches, ventes, favoris, etc.)
 * @param int   $limite         Nombre max à retourner
 * @param int   $seuil          Tant que count > seuil, on mélange la source (sinon fallback catalogue)
 * @return array
 */
function marketplace_produits_aleatoires_avec_seuil($produits_source, $limite, $seuil = 5)
{
    $produits_source = is_array($produits_source) ? array_values($produits_source) : [];
    require_once __DIR__ . '/catalogue_shuffle.php';
    if (count($produits_source) > $seuil) {
        $produits_source = catalogue_melanger_produits($produits_source);
        return array_slice($produits_source, 0, $limite);
    }
    if (!function_exists('get_all_produits_paginated')) {
        require_once __DIR__ . '/../models/model_produits.php';
    }
    $seed = catalogue_nouveau_seed('marketplace_fallback');
    $tous = get_all_produits_paginated(0, max(200, $limite * 6), null, $seed);
    if (empty($tous)) {
        return [];
    }
    return array_slice($tous, 0, $limite);
}

/**
 * Retourne les produits dans l'ordre fourni (sans mélange), dédoublonnés par id.
 * Si la source est vide, tirage aléatoire sur le catalogue (sans doublon).
 *
 * @param array  $produits_source Liste ordonnée (recherches, notes, etc.)
 * @param int    $limite          Nombre max à retourner
 * @param string $contexte_fallback Contexte de graine pour le fallback aléatoire
 * @param bool   $fallback_aleatoire Si false et source vide, retourne []
 * @return array
 */
function marketplace_produits_ordre_ou_aleatoire($produits_source, $limite, $contexte_fallback = 'marketplace_fallback', $fallback_aleatoire = true)
{
    $limite = max(1, (int) $limite);
    $produits_source = is_array($produits_source) ? array_values($produits_source) : [];
    if (!empty($produits_source)) {
        $out = [];
        $seen = [];
        foreach ($produits_source as $p) {
            if (!is_array($p)) {
                continue;
            }
            $id = (int) ($p['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $p;
            if (count($out) >= $limite) {
                break;
            }
        }
        return $out;
    }
    if (!$fallback_aleatoire) {
        return [];
    }
    return marketplace_produits_aleatoires_avec_seuil([], $limite, 0);
}

/**
 * Une seule requête SQL pour les notes moyennes de plusieurs listes produits.
 *
 * @param array<int, array<int, array<string, mixed>>> $lists
 * @return array<int, array{avis_moyenne: float, avis_count: int}>
 */
function marketplace_avis_stats_for_product_lists(array $lists): array
{
    if (!function_exists('produits_avis_enrich_products')) {
        return [];
    }
    $seen = [];
    $minimal = [];
    foreach ($lists as $list) {
        if (!is_array($list)) {
            continue;
        }
        foreach ($list as $p) {
            if (!is_array($p)) {
                continue;
            }
            $id = (int) ($p['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $minimal[] = ['id' => $id];
        }
    }
    if ($minimal === []) {
        return [];
    }
    $enriched = produits_avis_enrich_products($minimal);
    $stats = [];
    foreach ($enriched as $p) {
        if (!is_array($p)) {
            continue;
        }
        $pid = (int) ($p['id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $stats[$pid] = [
            'avis_moyenne' => (float) ($p['avis_moyenne'] ?? 0.0),
            'avis_count' => (int) ($p['avis_count'] ?? 0),
        ];
    }
    return $stats;
}

/**
 * @param array<int, array<string, mixed>> $products
 * @param array<int, array{avis_moyenne: float, avis_count: int}> $stats
 * @return array<int, array<string, mixed>>
 */
function marketplace_apply_avis_stats(array $products, array $stats): array
{
    foreach ($products as &$p) {
        if (!is_array($p)) {
            continue;
        }
        $pid = (int) ($p['id'] ?? 0);
        if ($pid > 0 && isset($stats[$pid])) {
            $p['avis_moyenne'] = $stats[$pid]['avis_moyenne'];
            $p['avis_count'] = $stats[$pid]['avis_count'];
        } else {
            $p['avis_moyenne'] = 0.0;
            $p['avis_count'] = 0;
        }
    }
    unset($p);
    return $products;
}

/**
 * Fusionne plusieurs listes produits (ordre conservé, dédoublonnage par id).
 *
 * @param array<int, array<int, array<string, mixed>>> $lists
 * @return array<int, array<string, mixed>>
 */
function marketplace_merge_product_lists(array ...$lists): array
{
    if (!function_exists('produit_est_visible_client')) {
        require_once __DIR__ . '/../models/model_produits.php';
    }
    $out = [];
    $seen = [];
    foreach ($lists as $list) {
        if (!is_array($list)) {
            continue;
        }
        foreach ($list as $p) {
            if (!is_array($p)) {
                continue;
            }
            $pid = (int) ($p['id'] ?? 0);
            if ($pid <= 0 || isset($seen[$pid])) {
                continue;
            }
            if (!produit_est_visible_client((string) ($p['statut'] ?? ''))) {
                continue;
            }
            $seen[$pid] = true;
            $out[] = $p;
        }
    }
    return $out;
}

/**
 * Pool showcase accueil : priorités métier puis complément catalogue jusqu’à $target.
 *
 * @param array<int, array<string, mixed>> ...$priority_lists
 * @return array<int, array<string, mixed>>
 */
function marketplace_build_showcase_pool(int $target, array ...$priority_lists): array
{
    $target = max(1, min(20, $target));
    $out = marketplace_merge_product_lists(...$priority_lists);
    if (count($out) >= $target) {
        return array_slice($out, 0, $target);
    }
    if (!function_exists('get_all_produits_paginated')) {
        require_once __DIR__ . '/../models/model_produits.php';
    }
    require_once __DIR__ . '/catalogue_shuffle.php';
    $seen = [];
    foreach ($out as $p) {
        $seen[(int) ($p['id'] ?? 0)] = true;
    }
    $seed = catalogue_nouveau_seed('marketplace_showcase_fill');
    $catalog = get_all_produits_paginated(0, max(120, $target * 12), null, $seed);
    if (is_array($catalog) && $catalog !== []) {
        $catalog = catalogue_melanger_produits($catalog);
        foreach ($catalog as $p) {
            if (!is_array($p)) {
                continue;
            }
            $pid = (int) ($p['id'] ?? 0);
            if ($pid <= 0 || isset($seen[$pid])) {
                continue;
            }
            if (!produit_est_visible_client((string) ($p['statut'] ?? ''))) {
                continue;
            }
            $seen[$pid] = true;
            $out[] = $p;
            if (count($out) >= $target) {
                break;
            }
        }
    }
    return array_slice($out, 0, $target);
}
