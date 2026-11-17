<?php
/**
 * Relances push personnalisées (style grandes marketplaces) : panier, favori en promo,
 * boutique suivie qui publie, produit déjà regardé, rayon préféré.
 *
 * Coût maîtrisé : une requête par type de signal pour TOUT le lot de destinataires
 * (jamais une requête par personne). Chaque signal produit au plus un message par compte,
 * classé du plus engageant au plus général.
 */
declare(strict_types=1);

/** Prix affiché (promotion comprise). */
function reengagement_prix_effectif(array $p): float
{
    $prix = (float) ($p['prix'] ?? 0);
    $promo = (float) ($p['prix_promotion'] ?? 0);
    return ($promo > 0 && $promo < $prix) ? $promo : $prix;
}

function reengagement_fcfa(float $n): string
{
    return number_format($n, 0, ',', ' ') . ' FCFA';
}

/** Pourcentage de remise (0 si pas de promo). */
function reengagement_pct(array $p): int
{
    $prix = (float) ($p['prix'] ?? 0);
    $promo = (float) ($p['prix_promotion'] ?? 0);
    return ($prix > 0 && $promo > 0 && $promo < $prix) ? (int) round(($prix - $promo) / $prix * 100) : 0;
}

/** URL absolue de la vignette produit (notification riche). */
function reengagement_image_url(string $img): string
{
    $img = trim(str_replace('\\', '/', $img));
    if ($img === '') {
        return '';
    }
    if (!function_exists('upload_image_url') && is_file(__DIR__ . '/../includes/image_optimizer.php')) {
        require_once __DIR__ . '/../includes/image_optimizer.php';
    }
    $url = function_exists('upload_image_url') ? upload_image_url($img, 'md') : '/upload/' . $img;
    /* Image de secours (fichier absent) : pas d'image plutôt qu'une vignette générique */
    if (strpos($url, 'produit1.jpg') !== false) {
        return '';
    }
    /* upload_image_url() inclut déjà le sous-dossier du site ; firebase_absolute_url() l'ajoute aussi */
    $base = function_exists('get_app_base_path') ? (string) get_app_base_path() : '';
    if ($base !== '' && strpos($url, $base . '/') === 0) {
        $url = substr($url, strlen($base));
    }
    return firebase_absolute_url($url);
}

function reengagement_produit_link(int $id, string $campaign): string
{
    return '/produit.php?id=' . $id . '&mp_ref=push_' . $campaign;
}

/**
 * Exécute une requête « WHERE user_id IN (…) » et renvoie la 1re ligne par compte.
 * L'ordre de la requête décide quelle ligne est gardée.
 *
 * @return array<int, array<string, mixed>>
 */
function reengagement_first_row_per_user(PDO $db, string $sql): array
{
    $out = [];
    try {
        foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0 && !isset($out[$uid])) {
                $out[$uid] = $row;
            }
        }
    } catch (PDOException $e) {
        error_log('[reengagement_personalization] ' . $e->getMessage());
    }
    return $out;
}

/**
 * Messages personnalisés par compte, du plus engageant au plus général.
 *
 * @param int[] $user_ids
 * @return array<int, list<array{key:string, title:string, body:string, link:string, image:string}>>
 */
function reengagement_personal_messages(PDO $db, array $user_ids, ?int $day_seed = null): array
{
    $user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids))));
    if (!$user_ids) {
        return [];
    }
    $seed = $day_seed ?? (int) date('z');
    $in = implode(',', $user_ids);
    $dispo = "pr.statut = 'actif' AND (pr.stock IS NULL OR pr.stock > 0)";
    $promo = "pr.prix_promotion IS NOT NULL AND pr.prix_promotion > 0 AND pr.prix_promotion < pr.prix";
    $cols = 'pr.id, pr.nom, pr.prix, pr.prix_promotion, pr.stock, pr.image_principale';
    $msgs = [];

    /* 1. Panier : l'article le plus récemment ajouté (relance n°1 des marketplaces) */
    foreach (reengagement_first_row_per_user($db, "
        SELECT p.user_id, $cols, (SELECT COUNT(*) FROM panier p2 WHERE p2.user_id = p.user_id) AS nb
        FROM panier p INNER JOIN produits pr ON pr.id = p.produit_id
        WHERE p.user_id IN ($in) AND $dispo
        ORDER BY p.date_ajout DESC
    ") as $uid => $r) {
        $nom = reengagement_short((string) $r['nom'], 30);
        $stock = $r['stock'] !== null ? (int) $r['stock'] : 0;
        $nb = (int) $r['nb'];
        if ($stock > 0 && $stock <= 3) {
            $body = "{$nom} : plus que {$stock} en stock à " . reengagement_fcfa(reengagement_prix_effectif($r)) . '. Finalisez avant qu’il parte !';
        } elseif ($nb > 1) {
            $body = "{$nom} et " . ($nb - 1) . ' autre' . ($nb > 2 ? 's' : '') . ' article' . ($nb > 2 ? 's' : '') . ' sont toujours dans votre panier. Il ne reste qu’à valider.';
        } else {
            $body = "{$nom} est toujours dans votre panier à " . reengagement_fcfa(reengagement_prix_effectif($r)) . '. Il ne reste qu’à valider !';
        }
        $msgs[$uid][] = [
            'key' => 'panier',
            'title' => $seed % 2 ? "🛒 {$nom} vous attend" : '🛒 Vous avez oublié quelque chose ?',
            'body' => $body,
            'link' => '/panier.php',
            'image' => reengagement_image_url((string) $r['image_principale']),
        ];
    }

    /* 2. Favori en promotion (la plus forte remise d'abord) */
    foreach (reengagement_first_row_per_user($db, "
        SELECT f.user_id, $cols
        FROM favoris f INNER JOIN produits pr ON pr.id = f.produit_id
        WHERE f.user_id IN ($in) AND $dispo AND $promo
          AND NOT EXISTS (SELECT 1 FROM panier pa WHERE pa.user_id = f.user_id AND pa.produit_id = pr.id)
        ORDER BY (pr.prix - pr.prix_promotion) / pr.prix DESC
    ") as $uid => $r) {
        $nom = reengagement_short((string) $r['nom'], 34);
        $msgs[$uid][] = [
            'key' => 'favori_promo',
            'title' => '💖 Votre favori est en promo !',
            'body' => "{$nom} passe à " . reengagement_fcfa(reengagement_prix_effectif($r)) . ' (-' . reengagement_pct($r) . ' %). Offre limitée.',
            'link' => reengagement_produit_link((int) $r['id'], 'favori_promo'),
            'image' => reengagement_image_url((string) $r['image_principale']),
        ];
    }

    /* 3. Boutique suivie qui a publié depuis 7 jours (la plus active d'abord) */
    foreach (reengagement_first_row_per_user($db, "
        SELECT ba.user_id, a.boutique_nom, a.boutique_slug, COUNT(pr.id) AS n,
               SUBSTRING_INDEX(GROUP_CONCAT(pr.image_principale ORDER BY pr.date_creation DESC SEPARATOR '|'), '|', 1) AS img
        FROM boutique_abonnements ba
        INNER JOIN admin a ON a.id = ba.admin_id
        INNER JOIN produits pr ON pr.admin_id = a.id
        WHERE ba.user_id IN ($in) AND $dispo AND pr.date_creation >= NOW() - INTERVAL 7 DAY
        GROUP BY ba.user_id, a.id, a.boutique_nom, a.boutique_slug
        ORDER BY n DESC
    ") as $uid => $r) {
        $shop = reengagement_short((string) ($r['boutique_nom'] ?: 'Votre boutique'), 26);
        $n = (int) $r['n'];
        $slug = trim((string) $r['boutique_slug']);
        $msgs[$uid][] = [
            'key' => 'boutique_suivie',
            'title' => "🏪 {$shop} a publié {$n} nouveauté" . ($n > 1 ? 's' : ''),
            'body' => 'Une boutique que vous suivez vient d’ajouter des articles. Découvrez-les avant tout le monde.',
            'link' => $slug !== '' ? '/boutique/' . rawurlencode($slug) . '/' : '/nouveautes.php',
            'image' => reengagement_image_url((string) $r['img']),
        ];
    }

    /* 4. Produit regardé ces 30 derniers jours : en promo d'abord, puis le plus récent */
    foreach (reengagement_first_row_per_user($db, "
        SELECT pv.user_id, $cols, ($promo) AS en_promo, MAX(pv.date_visite) AS vu
        FROM produits_visites pv INNER JOIN produits pr ON pr.id = pv.produit_id
        WHERE pv.user_id IN ($in) AND $dispo AND pv.date_visite >= NOW() - INTERVAL 30 DAY
          AND NOT EXISTS (SELECT 1 FROM panier pa WHERE pa.user_id = pv.user_id AND pa.produit_id = pr.id)
          AND NOT EXISTS (SELECT 1 FROM favoris fa WHERE fa.user_id = pv.user_id AND fa.produit_id = pr.id)
        GROUP BY pv.user_id, pr.id, pr.nom, pr.prix, pr.prix_promotion, pr.stock, pr.image_principale
        ORDER BY en_promo DESC, vu DESC
    ") as $uid => $r) {
        $nom = reengagement_short((string) $r['nom'], 30);
        $pct = reengagement_pct($r);
        $msgs[$uid][] = $pct > 0 ? [
            'key' => 'vu_promo',
            'title' => "🔥 -{$pct} % sur {$nom}",
            'body' => 'Bonne nouvelle : un article que vous avez regardé est en promo à ' . reengagement_fcfa(reengagement_prix_effectif($r)) . '.',
            'link' => reengagement_produit_link((int) $r['id'], 'vu_promo'),
            'image' => reengagement_image_url((string) $r['image_principale']),
        ] : [
            'key' => 'vu',
            'title' => $seed % 2 ? "👀 Toujours tenté(e) par {$nom} ?" : "👀 {$nom} est encore là",
            'body' => 'Il est toujours disponible à ' . reengagement_fcfa(reengagement_prix_effectif($r)) . '. Ne le laissez pas filer !',
            'link' => reengagement_produit_link((int) $r['id'], 'vu'),
            'image' => reengagement_image_url((string) $r['image_principale']),
        ];
    }

    /* 5. Rayon préféré (sous-catégorie la plus consultée) avec des nouveautés depuis 14 jours */
    foreach (reengagement_first_row_per_user($db, "
        SELECT t.user_id, t.categorie_id, c.nom AS cat_nom, nv.n
        FROM (
            SELECT pv.user_id, pr.categorie_id, COUNT(*) AS vues
            FROM produits_visites pv INNER JOIN produits pr ON pr.id = pv.produit_id
            WHERE pv.user_id IN ($in) AND pr.categorie_id IS NOT NULL AND pv.date_visite >= NOW() - INTERVAL 60 DAY
            GROUP BY pv.user_id, pr.categorie_id
        ) t
        INNER JOIN categories c ON c.id = t.categorie_id
        INNER JOIN (
            SELECT pr.categorie_id, COUNT(*) AS n FROM produits pr
            WHERE $dispo AND pr.date_creation >= NOW() - INTERVAL 14 DAY
            GROUP BY pr.categorie_id
        ) nv ON nv.categorie_id = t.categorie_id
        ORDER BY t.vues DESC
    ") as $uid => $r) {
        $n = (int) $r['n'];
        $msgs[$uid][] = [
            'key' => 'rayon',
            'title' => '✨ Du nouveau en ' . reengagement_short((string) $r['cat_nom'], 26),
            'body' => $n . ' article' . ($n > 1 ? 's' : '') . ' ajouté' . ($n > 1 ? 's' : '') . ' dans un rayon que vous aimez. Jetez un œil !',
            'link' => '/categorie.php?id=' . (int) $r['categorie_id'] . '&mp_ref=push_rayon',
            'image' => '',
        ];
    }

    return $msgs;
}
