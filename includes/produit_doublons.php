<?php
/**
 * Produits en double : même boutique, même nom, même description, même prix (et même promo).
 * Comparaison insensible à la casse et aux espaces multiples.
 *
 * - produit_doublon_trouver() : refus à l'ajout / la modification (controllers/controller_produits.php)
 * - produits_doublons_groupes() : nettoyage de l'existant (scripts/nettoyer_produits_doublons.php)
 */

/** Texte comparable : minuscules, espaces normalisés. */
function produit_doublon_normaliser($texte): string
{
    $t = mb_strtolower(trim((string) $texte), 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
    return trim($t, " .!\t\n\r");
}

/** Prix comparable (promotion vide ou nulle = pas de promotion). */
function produit_doublon_prix($v): string
{
    $f = (float) str_replace([' ', ','], ['', '.'], (string) $v);
    return $f > 0 ? number_format($f, 2, '.', '') : '0';
}

function produit_doublon_cle($nom, $description, $prix, $prix_promotion): string
{
    return produit_doublon_normaliser($nom) . '|' . produit_doublon_normaliser($description)
        . '|' . produit_doublon_prix($prix) . '|' . produit_doublon_prix($prix_promotion);
}

/**
 * Produit identique déjà publié par la même boutique.
 * Ajout : tous statuts (republier à l'identique un produit masqué ou bloqué est refusé).
 * Modification / remise en ligne : seulement les produits visibles ($visibles_seulement).
 *
 * @return array<string, mixed>|null
 */
function produit_doublon_trouver(int $admin_id, $nom, $description, $prix, $prix_promotion, int $exclude_id = 0, bool $visibles_seulement = false): ?array
{
    global $db;
    if ($admin_id <= 0 || !$db instanceof PDO || produit_doublon_normaliser($nom) === '') {
        return null;
    }
    $cle = produit_doublon_cle($nom, $description, $prix, $prix_promotion);
    try {
        /* Filtre SQL large (même boutique, même prix), comparaison fine en PHP */
        $st = $db->prepare('
            SELECT id, nom, description, prix, prix_promotion, statut, date_creation
            FROM produits
            WHERE admin_id = :a AND id <> :x AND ABS(prix - :p) < 0.01
            ' . ($visibles_seulement ? "AND statut IN ('actif', 'rupture_stock')" : '') . '
        ');
        $st->execute([
            'a' => $admin_id,
            'x' => $exclude_id,
            'p' => (float) produit_doublon_prix($prix),
        ]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (produit_doublon_cle($row['nom'], $row['description'], $row['prix'], $row['prix_promotion']) === $cle) {
                return $row;
            }
        }
    } catch (PDOException $e) {
        error_log('[produit_doublon_trouver] ' . $e->getMessage());
    }
    return null;
}

/** Message affiché au vendeur quand le produit existe déjà. */
function produit_doublon_message(array $existant): string
{
    $nom = htmlspecialchars((string) $existant['nom'], ENT_QUOTES, 'UTF-8');
    $date = !empty($existant['date_creation']) ? date('d/m/Y', strtotime((string) $existant['date_creation'])) : '';
    return 'Ce produit existe déjà dans votre boutique (« ' . $nom . ' »'
        . ($date !== '' ? ', ajouté le ' . $date : '') . ', même nom, même description et même prix). '
        . 'Modifiez le produit existant (stock, photos, variantes) au lieu de le publier une deuxième fois.';
}

/**
 * Groupes de doublons encore visibles sur la plateforme : pour chaque groupe,
 * le produit gardé (le plus vendu, sinon le plus ancien) et ceux à retirer.
 *
 * @return list<array{garde: array, retires: list<array>}>
 */
function produits_doublons_groupes(): array
{
    global $db;
    $rows = $db->query("
        SELECT p.id, p.admin_id, p.nom, p.description, p.prix, p.prix_promotion, p.statut, p.date_creation,
               (SELECT COALESCE(SUM(cp.quantite), 0) FROM commande_produits cp WHERE cp.produit_id = p.id) AS ventes
        FROM produits p
        WHERE p.admin_id IS NOT NULL AND p.admin_id > 0
        ORDER BY p.id
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $groupes = [];
    foreach ($rows as $r) {
        $groupes[(int) $r['admin_id'] . '#' . produit_doublon_cle($r['nom'], $r['description'], $r['prix'], $r['prix_promotion'])][] = $r;
    }

    $out = [];
    foreach ($groupes as $liste) {
        $visibles = array_values(array_filter($liste, static function ($r) {
            return in_array($r['statut'], ['actif', 'rupture_stock'], true);
        }));
        if (count($visibles) < 2) {
            continue;
        }
        usort($visibles, static function ($a, $b) {
            return [(int) $b['ventes'], (int) $a['id']] <=> [(int) $a['ventes'], (int) $b['id']];
        });
        $out[] = ['garde' => $visibles[0], 'retires' => array_slice($visibles, 1)];
    }
    return $out;
}

/**
 * Masque les doublons visibles (statut « inactif »). Utilisé par le script et par les tâches automatiques.
 *
 * @return int nombre de produits masqués
 */
function produits_doublons_masquer(): int
{
    global $db;
    $ids = [];
    foreach (produits_doublons_groupes() as $g) {
        foreach ($g['retires'] as $r) {
            $ids[] = (int) $r['id'];
        }
    }
    if ($ids) {
        $db->exec("UPDATE produits SET statut = 'inactif', date_modification = NOW() WHERE id IN (" . implode(',', $ids) . ")");
    }
    return count($ids);
}
