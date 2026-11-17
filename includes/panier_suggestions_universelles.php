<?php
/**
 * Suggestions universelles au panier — sans IA, pour n'importe quel produit.
 *
 * 1. Catégories qui complètent celles du panier (config « complementarite » : Chaussures → Sacs, Bijoux…),
 *    puis les autres catégories du même rayon.
 * 2. La même boutique (autres catégories).
 * 3. Les produits populaires.
 * Toujours : jamais une catégorie déjà au panier, un produit par catégorie à tour de rôle, public respecté
 * (pas d'article « homme » pour un panier féminin), prix cohérent avec le panier.
 */

require_once __DIR__ . '/../models/model_produits.php';

/** Texte en minuscules sans accents, pour comparer les noms de catégories aux motifs de la config. */
function panier_sugg_norm(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    return strtr($s, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'œ' => 'oe', '’' => "'",
    ]);
}

/** Le texte contient-il le motif en début de mot ? (« art » trouve « Art mural », pas « smart home ») */
function panier_sugg_motif(string $texte_norm, string $motif): bool
{
    $motif = panier_sugg_norm($motif);
    if ($motif === '') {
        return false;
    }
    return (bool) preg_match('/(?<![\p{L}\p{N}])' . preg_quote($motif, '/') . '/u', $texte_norm);
}

/**
 * Catégories qui ont des produits actifs : id => [nom, rayon_id, rayon_nom].
 *
 * @return array<int, array{nom: string, rayon_id: int, rayon_nom: string}>
 */
function panier_sugg_categories_actives(): array
{
    static $cats = null;
    if ($cats !== null) {
        return $cats;
    }
    global $db;
    $cats = [];
    try {
        $st = $db->query("
            SELECT c.id, c.nom, p.categorie_generale_id AS rayon_id, g.nom AS rayon_nom, COUNT(*) AS n
            FROM produits p
            INNER JOIN categories c ON c.id = p.categorie_id
            LEFT JOIN categories_generales g ON g.id = p.categorie_generale_id
            WHERE p.statut = 'actif'
            GROUP BY c.id, c.nom, p.categorie_generale_id, g.nom
            ORDER BY n DESC
        ");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $id = (int) $r['id'];
            if (!isset($cats[$id])) {
                $cats[$id] = ['nom' => (string) $r['nom'], 'rayon_id' => (int) $r['rayon_id'], 'rayon_nom' => (string) ($r['rayon_nom'] ?? '')];
            }
        }
    } catch (PDOException $e) {
        error_log('[panier_suggestions] categories : ' . $e->getMessage());
    }
    return $cats;
}

/** Étiquette courte d'une catégorie : « Accessoires (montres, ceintures…) » → « Accessoires ». */
function panier_sugg_etiquette(string $nom): string
{
    $nom = trim((string) preg_replace('/\s*\(.*$/u', '', $nom));
    return mb_strlen($nom, 'UTF-8') > 22 ? rtrim(mb_substr($nom, 0, 21, 'UTF-8')) . '…' : $nom;
}

/**
 * Catégories qui complètent celles du panier, avec un score (règles « complementarite », puis même rayon).
 * Les règles se lisent sur le nom de la catégorie ; le rayon ne sert que si aucune ne s'applique
 * (« Beauté & Santé » ne doit pas faire d'un parfum un produit de santé).
 *
 * @param array<int, string> $cats_panier id => nom normalisé
 * @param array<int, true> $rayons_panier
 * @return array<int, int> id de catégorie => score, du plus pertinent au moins pertinent
 */
function panier_sugg_scores_categories(array $cats_panier, array $rayons_panier): array
{
    $cfg = panier_accessoires_config();
    $cats = panier_sugg_categories_actives();
    $scores = [];
    $regles = (array) ($cfg['complementarite'] ?? []);
    $applicables = static function (string $texte) use ($regles): array {
        $out = [];
        foreach ($regles as $regle) {
            foreach ((array) ($regle['si'] ?? []) as $motif) {
                if (panier_sugg_motif($texte, (string) $motif)) {
                    $out[] = $regle;
                    break;
                }
            }
        }
        return $out;
    };
    foreach ($cats_panier as $cid => $cnom) {
        $a_appliquer = $applicables($cnom);
        if ($a_appliquer === []) {
            $a_appliquer = $applicables(panier_sugg_norm((string) ($cats[$cid]['rayon_nom'] ?? '')));
        }
        foreach ($a_appliquer as $regle) {
            foreach (array_values((array) ($regle['proposer'] ?? [])) as $rang => $motif) {
                foreach ($cats as $id => $c) {
                    if (!isset($cats_panier[$id]) && panier_sugg_motif(panier_sugg_norm($c['nom']), (string) $motif)) {
                        $scores[$id] = max($scores[$id] ?? 0, 100 - $rang * 8);
                    }
                }
            }
        }
    }
    foreach ($cats as $id => $c) {
        if (!isset($cats_panier[$id]) && isset($rayons_panier[$c['rayon_id']])) {
            $scores[$id] = max($scores[$id] ?? 0, 40);
        }
    }
    /* Une voiture ou une moto n'est jamais un complément */
    foreach (array_keys($scores) as $id) {
        if (panier_sugg_article_principal((string) ($cats[$id]['nom'] ?? ''))) {
            unset($scores[$id]);
        }
    }
    arsort($scores);
    return $scores;
}

/** Catégorie d'articles principaux (config « jamais_en_complement » : motos, voitures…) ? */
function panier_sugg_article_principal(string $categorie_nom): bool
{
    $nom = panier_sugg_norm($categorie_nom);
    foreach ((array) (panier_accessoires_config()['jamais_en_complement'] ?? []) as $motif) {
        if (panier_sugg_motif($nom, (string) $motif)) {
            return true;
        }
    }
    return false;
}

/**
 * Prix maximal d'une suggestion : pas une moto pour compléter un bonnet, ni un ordinateur pour compléter
 * un téléphone (un complément d'un article cher reste un accessoire).
 */
function panier_sugg_plafond(float $prix_max): float
{
    return $prix_max <= 20000 ? max($prix_max * 3, 25000.0) : max($prix_max * 0.4, 25000.0);
}

/**
 * Produits candidats (mêmes colonnes que la recherche) selon une condition SQL.
 *
 * @param list<mixed> $params
 * @return list<array<string, mixed>>
 */
function panier_sugg_requete(string $condition, array $params, array $exclure_ids, string $ordre, int $limite): array
{
    global $db;
    $vj = produits_sql_vendeur_fragment();
    $where = "p.statut = 'actif' AND (p.stock IS NULL OR p.stock > 0) AND (" . $condition . ')';
    if ($exclure_ids !== []) {
        $where .= ' AND p.id NOT IN (' . implode(',', array_fill(0, count($exclure_ids), '?')) . ')';
        foreach (array_keys($exclure_ids) as $id) {
            $params[] = (int) $id;
        }
    }
    produits_append_region_positional($where, $params, null);
    try {
        $st = $db->prepare("
            SELECT p.*, c.nom AS categorie_nom " . $vj['select'] . "
            FROM produits p
            LEFT JOIN categories c ON p.categorie_id = c.id
            " . $vj['join'] . "
            WHERE $where
            ORDER BY $ordre
            LIMIT " . (int) $limite);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[panier_suggestions] ' . $e->getMessage());
        return [];
    }
}

/** Ordre « populaire » : déjà vendu, puis en promotion, puis récent. */
function panier_sugg_ordre_populaire(): string
{
    return "(SELECT COUNT(*) FROM commande_produits cp WHERE cp.produit_id = p.id) DESC,
            (p.prix_promotion > 0 AND p.prix_promotion < p.prix) DESC,
            p.date_creation DESC";
}

/**
 * Suggestions complémentaires pour n'importe quel panier, sans IA.
 *
 * @param array<int, array<string, mixed>> $panier_items
 * @param array<int, true> $exclure_ids produits déjà au panier ou déjà suggérés
 * @return list<array<string, mixed>> lignes produit avec 'panier_bundle_slot' (étiquette)
 */
function panier_suggestions_universelles(array $panier_items, array $exclure_ids, int $nombre): array
{
    if ($nombre <= 0 || $panier_items === []) {
        return [];
    }
    $cfg = panier_accessoires_config();
    $cats = panier_sugg_categories_actives();

    /* Ce que contient le panier */
    $cats_panier = [];
    $rayons_panier = [];
    $boutiques = [];
    $prix_max = 0.0;
    $votes = [];
    foreach ($panier_items as $item) {
        $pid = (int) ($item['produit_id'] ?? $item['id'] ?? 0);
        $p = $pid > 0 ? get_produit_by_id($pid) : false;
        if (!is_array($p)) {
            continue;
        }
        $exclure_ids[$pid] = true;
        if ((int) ($p['categorie_id'] ?? 0) > 0) {
            $cats_panier[(int) $p['categorie_id']] = panier_sugg_norm((string) ($p['categorie_nom'] ?? ''));
        }
        if ((int) ($p['categorie_generale_id'] ?? 0) > 0) {
            $rayons_panier[(int) $p['categorie_generale_id']] = true;
        }
        if ((int) ($p['admin_id'] ?? 0) > 0) {
            $boutiques[(int) $p['admin_id']] = true;
        }
        $prix_max = max($prix_max, panier_accessoires_prix_effectif($p));
        $public = panier_accessoires_public_produit($p);
        if ($public !== '') {
            $votes[$public] = ($votes[$public] ?? 0) + 1;
        }
    }
    if ($cats_panier === [] && $boutiques === []) {
        return [];
    }

    /* Public du panier : on écarte les articles de l'autre genre */
    $exclure_mots = [];
    if ($votes !== []) {
        arsort($votes);
        $u = $cfg['univers'][(string) array_key_first($votes)] ?? [];
        foreach ((array) ($u['exclure_genre'] ?? []) as $m) {
            $exclure_mots[] = mb_strtolower(trim((string) $m), 'UTF-8');
        }
    }
    $prix_plafond = panier_sugg_plafond($prix_max);
    /* Boutique et populaires, moins ciblés : pour un article cher, seulement de petits compléments */
    $plafond_large = $prix_max <= 20000 ? $prix_plafond : max(25000.0, $prix_max * 0.05);
    $acceptable = static function (array $row, bool $cible = true) use ($exclure_mots, $prix_plafond, $plafond_large): bool {
        if (panier_accessoires_prix_effectif($row) > ($cible ? $prix_plafond : $plafond_large)) {
            return false;
        }
        $nom = mb_strtolower((string) ($row['nom'] ?? ''), 'UTF-8');
        foreach ($exclure_mots as $m) {
            if ($m !== '' && panier_accessoires_text_contains_keyword($nom, $m)) {
                return false;
            }
        }
        return true;
    };

    /* 1. Catégories complémentaires (règles), puis le reste du même rayon */
    $scores = panier_sugg_scores_categories($cats_panier, $rayons_panier);

    $choisis = [];
    if ($scores !== []) {
        $ids_cats = array_slice(array_keys($scores), 0, 14);
        $par_cat = [];
        $rows = panier_sugg_requete(
            'p.categorie_id IN (' . implode(',', array_fill(0, count($ids_cats), '?')) . ')',
            $ids_cats,
            $exclure_ids,
            panier_sugg_ordre_populaire(),
            120
        );
        foreach ($rows as $row) {
            $cid = (int) ($row['categorie_id'] ?? 0);
            if ($acceptable($row) && count($par_cat[$cid] ?? []) < 3) {
                $par_cat[$cid][] = $row;
            }
        }
        /* Un produit par catégorie à tour de rôle, dans l'ordre de pertinence */
        for ($tour = 0; $tour < 3 && count($choisis) < $nombre; $tour++) {
            foreach ($ids_cats as $cid) {
                if (!isset($par_cat[$cid][$tour])) {
                    continue;
                }
                $row = $par_cat[$cid][$tour];
                $row['panier_bundle_slot'] = panier_sugg_etiquette((string) ($cats[$cid]['nom'] ?? ''));
                $choisis[] = $row;
                $exclure_ids[(int) $row['id']] = true;
                if (count($choisis) >= $nombre) {
                    break 2;
                }
            }
        }
    }

    /* 2. Même boutique, autres catégories */
    if (count($choisis) < $nombre && $boutiques !== []) {
        $cond = 'p.admin_id IN (' . implode(',', array_fill(0, count($boutiques), '?')) . ')';
        $params = array_keys($boutiques);
        if ($cats_panier !== []) {
            $cond .= ' AND (p.categorie_id IS NULL OR p.categorie_id NOT IN (' . implode(',', array_fill(0, count($cats_panier), '?')) . '))';
            $params = array_merge($params, array_keys($cats_panier));
        }
        foreach (panier_sugg_requete($cond, $params, $exclure_ids, panier_sugg_ordre_populaire(), 12) as $row) {
            if (!$acceptable($row, false) || panier_sugg_article_principal((string) ($row['categorie_nom'] ?? ''))) {
                continue;
            }
            $row['panier_bundle_slot'] = 'Même boutique';
            $choisis[] = $row;
            $exclure_ids[(int) $row['id']] = true;
            if (count($choisis) >= $nombre) {
                break;
            }
        }
    }

    /* 3. Populaires (variés : deux au plus par catégorie) */
    if (count($choisis) < $nombre) {
        $cond = '1 = 1';
        $params = [];
        if ($cats_panier !== []) {
            $cond = '(p.categorie_id IS NULL OR p.categorie_id NOT IN (' . implode(',', array_fill(0, count($cats_panier), '?')) . '))';
            $params = array_keys($cats_panier);
        }
        /* D'abord le même rayon que le panier */
        $ordre = panier_sugg_ordre_populaire();
        if ($rayons_panier !== []) {
            $ordre = 'p.categorie_generale_id IN (' . implode(',', array_map('intval', array_keys($rayons_panier))) . ') DESC, ' . $ordre;
        }
        $par_cat = [];
        foreach (panier_sugg_requete($cond, $params, $exclure_ids, $ordre, 80) as $row) {
            $cid = (int) ($row['categorie_id'] ?? 0);
            if (!$acceptable($row, false) || ($par_cat[$cid] ?? 0) >= 2
                || panier_sugg_article_principal((string) ($row['categorie_nom'] ?? ''))) {
                continue;
            }
            $par_cat[$cid] = ($par_cat[$cid] ?? 0) + 1;
            $row['panier_bundle_slot'] = 'Populaire';
            $choisis[] = $row;
            if (count($choisis) >= $nombre) {
                break;
            }
        }
    }

    return $choisis;
}
