<?php
/**
 * Vente en gros (type Alibaba) : prix dégressifs par quantité + option « gros uniquement »
 * (quantité minimum de commande).
 *
 * Données sur la table produits :
 *   - gros_paliers   TEXT NULL     JSON [{"q":10,"p":4500},{"q":50,"p":4000}] (prix unitaire dès q pièces)
 *   - gros_uniquement TINYINT(1)   1 = on ne vend pas à l'unité (quantité minimum = 1er palier)
 *
 * Le prix des paliers est appliqué partout via panier_resolve_unit_price_for_line()
 * (panier, validation, création de commande) : le client ne peut pas l'envoyer lui-même.
 */

if (!defined('VENTE_GROS_MAX_PALIERS')) {
    define('VENTE_GROS_MAX_PALIERS', 4);
}

/** Ajoute les colonnes si besoin (une seule fois par requête). */
function vente_gros_ensure_schema(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    global $db;
    if (!$db instanceof PDO) {
        return $ok = false;
    }
    try {
        $cols = $db->query("SHOW COLUMNS FROM `produits` WHERE Field IN ('gros_paliers', 'gros_uniquement')")
            ->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $add = [];
        if (!in_array('gros_paliers', $cols, true)) {
            $add[] = 'ADD COLUMN `gros_paliers` TEXT NULL DEFAULT NULL';
        }
        if (!in_array('gros_uniquement', $cols, true)) {
            $add[] = 'ADD COLUMN `gros_uniquement` TINYINT(1) NOT NULL DEFAULT 0';
        }
        if ($add) {
            $db->exec('ALTER TABLE `produits` ' . implode(', ', $add));
        }
        return $ok = true;
    } catch (PDOException $e) {
        error_log('[vente_gros_ensure_schema] ' . $e->getMessage());
        return $ok = false;
    }
}

/**
 * Paliers valides, triés par quantité croissante.
 *
 * @return list<array{q:int, p:float}>
 */
function vente_gros_paliers($produit_or_raw): array
{
    $raw = is_array($produit_or_raw) ? ($produit_or_raw['gros_paliers'] ?? '') : $produit_or_raw;
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $dec = json_decode($raw, true);
    if (!is_array($dec)) {
        return [];
    }
    $out = [];
    foreach ($dec as $row) {
        $q = (int) ($row['q'] ?? 0);
        $p = (float) ($row['p'] ?? 0);
        if ($q >= 2 && $p > 0) {
            $out[$q] = ['q' => $q, 'p' => $p];
        }
    }
    ksort($out);
    return array_values($out);
}

function vente_gros_actif(array $produit): bool
{
    return vente_gros_paliers($produit) !== [];
}

/** Quantité minimum de commande (1 si la vente à l'unité reste possible). */
function vente_gros_quantite_min(array $produit): int
{
    $paliers = vente_gros_paliers($produit);
    if ($paliers === [] || empty($produit['gros_uniquement'])) {
        return 1;
    }
    return (int) $paliers[0]['q'];
}

/** Prix unitaire du palier atteint pour cette quantité, ou null (prix normal). */
function vente_gros_prix_pour_quantite(array $produit, int $quantite): ?float
{
    $prix = null;
    foreach (vente_gros_paliers($produit) as $palier) {
        if ($quantite >= $palier['q']) {
            $prix = $palier['p'];
        }
    }
    return $prix;
}

/** Prix unitaire normal (promotion comprise). */
function vente_gros_prix_detail(array $produit): float
{
    $prix = (float) ($produit['prix'] ?? 0);
    $promo = (float) ($produit['prix_promotion'] ?? 0);
    return ($promo > 0 && $promo < $prix) ? $promo : $prix;
}

/** Prix « à partir de » affiché sur les cartes (palier le plus bas). */
function vente_gros_prix_min(array $produit): ?float
{
    $paliers = vente_gros_paliers($produit);
    return $paliers ? (float) end($paliers)['p'] : null;
}

/**
 * Lit et valide le formulaire produit.
 * Champs : vente_gros (1), gros_q[], gros_p[], gros_uniquement (1).
 *
 * @return array{paliers: ?string, uniquement: int, errors: string[]}
 */
function vente_gros_from_post(array $post, float $prix_detail): array
{
    $res = ['paliers' => null, 'uniquement' => 0, 'errors' => []];
    if (empty($post['vente_gros'])) {
        return $res;
    }
    $qs = (array) ($post['gros_q'] ?? []);
    $ps = (array) ($post['gros_p'] ?? []);
    $rows = [];
    foreach ($qs as $i => $q_raw) {
        $q_raw = trim((string) $q_raw);
        $p_raw = trim(str_replace([' ', ','], ['', '.'], (string) ($ps[$i] ?? '')));
        if ($q_raw === '' && $p_raw === '') {
            continue;
        }
        $q = (int) $q_raw;
        $p = (float) $p_raw;
        if ($q < 2 || $p <= 0) {
            $res['errors'][] = 'Vente en gros : chaque palier doit avoir une quantité d’au moins 2 et un prix.';
            continue;
        }
        if (isset($rows[$q])) {
            $res['errors'][] = 'Vente en gros : deux paliers ont la même quantité (' . $q . ').';
            continue;
        }
        $rows[$q] = ['q' => $q, 'p' => round($p, 2)];
    }
    if ($rows === []) {
        $res['errors'][] = 'Vente en gros : ajoutez au moins un palier (quantité minimum et prix par pièce).';
        return $res;
    }
    if (count($rows) > VENTE_GROS_MAX_PALIERS) {
        $res['errors'][] = 'Vente en gros : ' . VENTE_GROS_MAX_PALIERS . ' paliers maximum.';
    }
    ksort($rows);
    $prev = $prix_detail > 0 ? $prix_detail : INF;
    foreach ($rows as $row) {
        if ($row['p'] >= $prev) {
            $res['errors'][] = 'Vente en gros : le prix doit baisser quand la quantité augmente (palier ' . $row['q'] . ' pièces).';
            break;
        }
        $prev = $row['p'];
    }
    $res['paliers'] = json_encode(array_values($rows));
    $res['uniquement'] = !empty($post['gros_uniquement']) ? 1 : 0;
    return $res;
}

function vente_gros_save(int $produit_id, ?string $paliers_json, int $uniquement): bool
{
    global $db;
    if ($produit_id <= 0 || !vente_gros_ensure_schema()) {
        return false;
    }
    try {
        $st = $db->prepare('UPDATE produits SET gros_paliers = :g, gros_uniquement = :u WHERE id = :id');
        return $st->execute(['g' => $paliers_json, 'u' => $paliers_json !== null ? $uniquement : 0, 'id' => $produit_id]);
    } catch (PDOException $e) {
        error_log('[vente_gros_save] ' . $e->getMessage());
        return false;
    }
}

/**
 * Produits vendus en gros pour l'accueil (actifs, en stock).
 *
 * @return list<array<string, mixed>>
 */
function vente_gros_produits_accueil(int $limit = 12): array
{
    global $db;
    if (!vente_gros_ensure_schema()) {
        return [];
    }
    try {
        $st = $db->prepare("
            SELECT p.id, p.nom, p.prix, p.prix_promotion, p.stock, p.image_principale, p.unite,
                   p.gros_paliers, p.gros_uniquement, a.boutique_nom
            FROM produits p
            LEFT JOIN admin a ON a.id = p.admin_id
            WHERE p.statut = 'actif' AND (p.stock IS NULL OR p.stock > 0)
              AND p.gros_paliers IS NOT NULL AND p.gros_paliers <> '' AND p.gros_paliers <> '[]'
            ORDER BY p.date_modification DESC, p.date_creation DESC
            LIMIT :lim
        ");
        $st->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $st->execute();
        return array_values(array_filter($st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'vente_gros_actif'));
    } catch (PDOException $e) {
        error_log('[vente_gros_produits_accueil] ' . $e->getMessage());
        return [];
    }
}

/** Libellé d'un palier : « 10 – 49 pièces » ou « ≥ 100 pièces » pour le dernier. */
function vente_gros_libelle_palier(array $paliers, int $i): string
{
    $q = (int) $paliers[$i]['q'];
    $next = isset($paliers[$i + 1]) ? (int) $paliers[$i + 1]['q'] - 1 : null;
    if ($next === null) {
        return '≥ ' . $q . ' pièces';
    }
    return $next > $q ? $q . ' – ' . $next . ' pièces' : $q . ' pièces';
}

/** Libellé du prix à l'unité avant le 1er palier : « 1 – 9 pièces ». */
function vente_gros_libelle_unite(array $paliers): string
{
    $fin = (int) ($paliers[0]['q'] ?? 2) - 1;
    return $fin > 1 ? '1 – ' . $fin . ' pièces' : '1 pièce';
}
