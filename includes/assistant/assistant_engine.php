<?php
/**
 * Moteur de l'assistante COLObanes.
 *
 * - Outils : recherche produits (multi-mots, prix, promo, catégorie, tri), boutiques, catégories,
 *   commandes du client connecté, base de connaissances de la plateforme.
 * - Compréhension intégrée (gratuite, locale) des questions en français.
 * - IA Google Gemini optionnelle (config/assistant.php) qui appelle les mêmes outils.
 *
 * Réponse normalisée : ['reply' => texte (gras **…**, liens [texte](url)), 'products' => [], 'shops' => [],
 *                       'orders' => [], 'links' => [], 'suggestions' => []]
 */

require_once __DIR__ . '/assistant_knowledge.php';
require_once dirname(__DIR__) . '/app_base.php';

/* ================= Configuration ================= */

function assistant_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $base = dirname(__DIR__, 2) . '/config/';
    $path = is_readable($base . 'assistant.php') ? $base . 'assistant.php' : $base . 'assistant.example.php';
    $cfg = is_readable($path) ? require $path : [];
    $cfg = is_array($cfg) ? $cfg : [];
    $cfg += [
        'enabled' => true,
        'bot_name' => 'Kennassy',
        'provider' => 'auto',
        'gemini_api_key' => '',
        'gemini_model' => 'gemini-3.1-flash-lite',
        'mistral_models' => ['ministral-14b-latest', 'voxtral-small-latest', 'ministral-8b-latest', 'codestral-latest', 'ministral-3b-latest'],
        'openrouter_model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        'cloudflare_model' => '@cf/meta/llama-3.1-8b-instruct',
        'rate_limit_per_minute' => 20,
    ];
    require_once __DIR__ . '/assistant_keys.php';
    $keys = assistant_keys_load();
    if ($keys['gemini_api_key'] !== '') {
        $cfg['gemini_api_key'] = $keys['gemini_api_key'];
    }
    return $cfg;
}

/* ================= Texte ================= */

function assistant_norm(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ú' => 'u', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', '’' => "'", '‘' => "'",
    ]);
    return preg_replace('/\s+/u', ' ', $s);
}

function assistant_stopwords(): array
{
    return array_flip([
        'je', 'j', 'tu', 'il', 'elle', 'on', 'nous', 'vous', 'ils', 'elles', 'me', 'moi', 'te', 'se', 'mon', 'ma', 'mes',
        'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'notre', 'votre', 'vos', 'leur', 'le', 'la', 'les', 'l', 'un', 'une', 'des',
        'du', 'de', 'd', 'au', 'aux', 'a', 'et', 'ou', 'en', 'dans', 'sur', 'sous', 'pour', 'par', 'avec', 'sans', 'chez',
        'ce', 'cet', 'cette', 'ces', 'qui', 'que', 'quoi', 'qu', 'est', 'sont', 'suis', 'es', 'ai', 'as', 'avez', 'avons',
        'veux', 'voudrais', 'veut', 'souhaite', 'aimerais', 'cherche', 'recherche', 'chercher', 'trouver', 'trouve',
        'acheter', 'achete', 'besoin', 'faut', 'moi', 'svp', 'stp', 'merci', 'bonjour', 'salut',
        'avez', 'vendez', 'vend', 'vends', 'existe', 'il', 'y', 'combien', 'coute', 'coutent', 'prix', 'cout', 'fcfa',
        'cfa', 'f', 'franc', 'francs', 'xof', 'moins', 'plus', 'cher', 'chers', 'chere', 'pas', 'max', 'maximum', 'min',
        'minimum', 'entre', 'environ', 'budget', 'jusqu', 'jusque', 'partir', 'dessus', 'dessous', 'promo', 'promotion',
        'promos', 'solde', 'soldes', 'reduction', 'bon', 'marche', 'produit', 'produits', 'article', 'articles', 'quel',
        'quels', 'quelle', 'quelles', 'tout', 'tous', 'toutes', 'autre', 'autres', 'disponible', 'dispo', 'neuf', 'neufs',
        'recent', 'recents', 'nouveau', 'nouveaux', 'nouveaute', 'nouveautes', 'mille', 'k', 'si', 'ne', 'n', 'plait',
        'peux', 'pouvez', 'puis', 'aide', 'aider', 'svp', 'please', 'meilleur', 'meilleurs', 'top', 'mieux', 'bas',
    ]);
}

/** Montant en FCFA depuis « 50 000 », « 50k », « 50 mille », « 1,5 million ». */
function assistant_parse_amount(string $num, string $unit = ''): float
{
    $n = (float) str_replace([' ', ','], ['', '.'], $num);
    $unit = trim($unit);
    if ($unit === 'k' || $unit === 'mille') {
        $n *= 1000;
    } elseif (strpos($unit, 'million') === 0 || $unit === 'm') {
        $n *= 1000000;
    }
    return $n;
}

/**
 * Analyse une demande de recherche en français.
 *
 * @return array{query:string, prix_min:?float, prix_max:?float, promo:bool, tri:string, categorie_id:?int, categorie_nom:string}
 */
function assistant_parse_search(string $message): array
{
    $t = assistant_norm($message);
    /* « montre-moi » est une demande, pas le produit « montre » */
    $t = preg_replace('/\bmontre[sz]?[\s-]+(moi|nous)\b/u', ' ', $t);
    $num = '(\d[\d\s.,]*)\s*(k|mille|millions?|m)?\b';
    $prix_min = null;
    $prix_max = null;

    if (preg_match('/entre\s+' . $num . '\s*(?:fcfa|f|cfa)?\s*(?:et|a|-)\s*' . $num . '/u', $t, $m)) {
        $prix_min = assistant_parse_amount($m[1], $m[2] ?? '');
        $prix_max = assistant_parse_amount($m[3], $m[4] ?? '');
        $t = str_replace($m[0], ' ', $t);
    }
    if ($prix_max === null && preg_match('/(?:moins de|max(?:imum)?|pas plus de|jusqu.?a|sous|budget(?: de)?|inferieur a|en dessous de|<)\s*' . $num . '/u', $t, $m)) {
        $prix_max = assistant_parse_amount($m[1], $m[2] ?? '');
        $t = str_replace($m[0], ' ', $t);
    }
    if ($prix_min === null && preg_match('/(?:plus de|a partir de|min(?:imum)?|au moins|superieur a|au dessus de|>)\s*' . $num . '/u', $t, $m)) {
        $prix_min = assistant_parse_amount($m[1], $m[2] ?? '');
        $t = str_replace($m[0], ' ', $t);
    }

    $promo = (bool) preg_match('/\b(promo|promos|promotion|solde|soldes|reduction|remise|bon plan|bons plans)\b/u', $t);
    $tri = 'date';
    if (preg_match('/\b(moins cher|moins chers|pas cher|pas chers|bon marche|le plus bas|prix bas|petit prix|economique)\b/u', $t)) {
        $tri = 'prix_asc';
    } elseif (preg_match('/\b(plus cher|haut de gamme|luxe|premium)\b/u', $t)) {
        $tri = 'prix_desc';
    }

    /* Catégorie citée dans la phrase */
    $categorie_id = null;
    $categorie_nom = '';
    foreach (assistant_categories() as $cat) {
        $cn = assistant_norm($cat['nom']);
        if ($cn !== '' && mb_strlen($cn) >= 4 && strpos($t, $cn) !== false) {
            $categorie_id = (int) $cat['id'];
            $categorie_nom = $cat['nom'];
            $t = str_replace($cn, ' ', $t);
            break;
        }
    }

    /* Mots-clés restants */
    $t = preg_replace('/\d[\d\s.,]*\s*(k|mille|fcfa|f|cfa)?/u', ' ', $t);
    $t = preg_replace("/[^\p{L}\p{N}\s-]/u", ' ', $t);
    $stop = assistant_stopwords();
    $words = [];
    foreach (preg_split('/\s+/u', $t) as $w) {
        $w = trim($w, '-');
        if ($w === '' || isset($stop[$w]) || mb_strlen($w) < 2) {
            continue;
        }
        $words[] = $w;
    }

    return [
        'query' => implode(' ', array_slice(array_unique($words), 0, 6)),
        'prix_min' => $prix_min,
        'prix_max' => $prix_max,
        'promo' => $promo,
        'tri' => $tri,
        'categorie_id' => $categorie_id,
        'categorie_nom' => $categorie_nom,
    ];
}

/* ================= Outils (catalogue réel) ================= */

function assistant_categories(): array
{
    static $cats = null;
    if ($cats !== null) {
        return $cats;
    }
    $cats = [];
    if (!function_exists('get_all_categories')) {
        require_once dirname(__DIR__, 2) . '/models/model_categories.php';
    }
    foreach ((array) get_all_categories() as $c) {
        if (!empty($c['id']) && !empty($c['nom'])) {
            $cats[] = ['id' => (int) $c['id'], 'nom' => (string) $c['nom']];
        }
    }
    /* Les noms longs d'abord : « accessoires vape » avant « vape » */
    usort($cats, function ($a, $b) {
        return mb_strlen($b['nom']) <=> mb_strlen($a['nom']);
    });
    return $cats;
}

/** Synonymes courants (mot tapé → mots du catalogue) pour élargir une recherche. */
function assistant_synonyms(string $word): array
{
    static $map = [
        'frigo' => ['refrigerateur', 'congelateur'], 'refrigerateur' => ['frigo', 'congelateur'],
        'portable' => ['telephone', 'smartphone'], 'tel' => ['telephone', 'smartphone'], 'smartphone' => ['telephone'],
        'telephone' => ['smartphone', 'iphone', 'samsung'], 'mobile' => ['telephone', 'smartphone'],
        'ordi' => ['ordinateur', 'laptop', 'pc'], 'pc' => ['ordinateur', 'laptop'], 'laptop' => ['ordinateur', 'pc'],
        'ordinateur' => ['laptop', 'pc'], 'tele' => ['television', 'tv'], 'tv' => ['television', 'tele'],
        'television' => ['tv'], 'basket' => ['chaussures', 'sneakers', 'tennis'], 'baskets' => ['chaussures', 'sneakers'],
        'sneakers' => ['baskets', 'chaussures'], 'chaussure' => ['chaussures', 'baskets'], 'habit' => ['vetement'],
        'habits' => ['vetements'], 'fringue' => ['vetement'], 'montre' => ['montre connectee', 'smartwatch'],
        'ecouteur' => ['ecouteurs', 'airpods', 'casque'], 'ecouteurs' => ['airpods', 'casque'], 'casque' => ['ecouteurs'],
        'parfum' => ['eau de parfum', 'eau de toilette'], 'sac' => ['sacoche', 'sac a main'], 'robe' => ['robes'],
        'moto' => ['scooter'], 'scooter' => ['moto'], 'clim' => ['climatiseur'], 'climatiseur' => ['clim'],
        'micro-onde' => ['micro-ondes'], 'cuisiniere' => ['four', 'gaz'], 'ventilo' => ['ventilateur'],
        'chargeur' => ['cable', 'adaptateur'], 'jeu' => ['console', 'playstation'], 'ps5' => ['playstation'],
    ];
    $w = assistant_norm($word);
    return $map[$w] ?? [];
}

function assistant_product_card(array $p): array
{
    if (!function_exists('upload_image_url')) {
        require_once dirname(__DIR__) . '/image_optimizer.php';
    }
    $prix = (float) ($p['prix'] ?? 0);
    $promo = (float) ($p['prix_promotion'] ?? 0);
    $has_promo = $promo > 0 && $promo < $prix;
    return [
        'id' => (int) $p['id'],
        'nom' => (string) ($p['nom'] ?? ''),
        'prix' => $has_promo ? $promo : $prix,
        'ancien_prix' => $has_promo ? $prix : null,
        'image' => upload_image_url((string) ($p['image_principale'] ?? ''), 'sm'),
        'url' => app_url('/produit.php?id=' . (int) $p['id']),
        'boutique' => (string) ($p['vendeur_boutique_nom'] ?? ''),
        'categorie' => (string) ($p['categorie_nom'] ?? ''),
        'en_stock' => ($p['statut'] ?? '') !== 'rupture_stock',
    ];
}

/**
 * Recherche « poussée » : phrase complète, puis chaque mot-clé ; classement par nombre de mots trouvés.
 *
 * @return array{items: array, total_found: int}
 */
function assistant_tool_search_products(array $a): array
{
    if (!function_exists('search_produits_with_filters')) {
        require_once dirname(__DIR__, 2) . '/models/model_produits.php';
    }
    $query = trim((string) ($a['query'] ?? ''));
    $prix_min = isset($a['prix_min']) && $a['prix_min'] !== '' && $a['prix_min'] !== null ? (float) $a['prix_min'] : null;
    $prix_max = isset($a['prix_max']) && $a['prix_max'] !== '' && $a['prix_max'] !== null ? (float) $a['prix_max'] : null;
    $cat = isset($a['categorie_id']) && (int) $a['categorie_id'] > 0 ? (int) $a['categorie_id'] : null;
    $tri = in_array(($a['tri'] ?? 'date'), ['date', 'prix_asc', 'prix_desc'], true) ? (string) ($a['tri'] ?? 'date') : 'date';
    $promo_only = !empty($a['promo']);
    $limit = max(1, min(8, (int) ($a['limit'] ?? 6)));

    /* Catégorie donnée par son nom (IA) */
    if ($cat === null && !empty($a['categorie'])) {
        $wanted = assistant_norm((string) $a['categorie']);
        foreach (assistant_categories() as $c) {
            if (strpos(assistant_norm($c['nom']), $wanted) !== false || strpos($wanted, assistant_norm($c['nom'])) !== false) {
                $cat = $c['id'];
                break;
            }
        }
    }

    $pool = 40;
    $scores = [];
    $rows = [];
    $collect = function (array $list, int $weight) use (&$scores, &$rows) {
        foreach ($list as $i => $p) {
            $id = (int) ($p['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $rows[$id] = $p;
            $scores[$id] = ($scores[$id] ?? 0) + $weight * 100 - $i;
        }
    };

    if ($query === '') {
        $collect(search_produits_with_filters('', $prix_min, $prix_max, $cat, $tri, 0, $pool), 1);
    } else {
        $collect(search_produits_with_filters($query, $prix_min, $prix_max, $cat, $tri, 0, $pool), 3);
        $words = array_values(array_filter(preg_split('/\s+/u', $query), function ($w) {
            return mb_strlen($w) >= 3;
        }));
        if (count($words) > 1) {
            foreach ($words as $w) {
                $collect(search_produits_with_filters($w, $prix_min, $prix_max, $cat, $tri, 0, $pool), 1);
            }
        }
        /* Synonymes : « frigo » → « réfrigérateur », « ordi » → « ordinateur »… */
        if (count($rows) < $limit) {
            foreach (array_merge([$query], $words) as $w) {
                foreach (assistant_synonyms($w) as $syn) {
                    $collect(search_produits_with_filters($syn, $prix_min, $prix_max, $cat, $tri, 0, $pool), 1);
                }
            }
        }
        /* Pluriels simples : « chaussures » → « chaussure » */
        if (empty($rows)) {
            foreach ($words ?: [$query] as $w) {
                $sing = preg_replace('/(s|x)$/u', '', $w);
                if ($sing !== $w && mb_strlen($sing) >= 3) {
                    $collect(search_produits_with_filters($sing, $prix_min, $prix_max, $cat, $tri, 0, $pool), 1);
                }
            }
        }
    }

    if ($promo_only) {
        foreach ($rows as $id => $p) {
            $pp = (float) ($p['prix_promotion'] ?? 0);
            if (!($pp > 0 && $pp < (float) ($p['prix'] ?? 0))) {
                unset($rows[$id], $scores[$id]);
            }
        }
    }

    /* Pertinence : mots (ou synonymes) présents dans le NOM du produit, puis score de recherche */
    $terms = [];
    if ($query !== '') {
        foreach (preg_split('/\s+/u', assistant_norm($query)) as $w) {
            if (mb_strlen($w) < 2) {
                continue;
            }
            $variants = array_merge([$w, preg_replace('/(s|x)$/u', '', $w)], array_map('assistant_norm', assistant_synonyms($w)));
            $terms[] = array_values(array_unique(array_filter($variants)));
        }
    }
    $relevance = [];
    foreach ($rows as $id => $p) {
        $name = ' ' . assistant_norm((string) ($p['nom'] ?? '')) . ' ';
        $hits = 0;
        foreach ($terms as $variants) {
            foreach ($variants as $v) {
                /* Mot entier (pluriel accepté) : « casque » ne compte pas « casquette » */
                if (mb_strlen($v) >= 2 && preg_match('/(?<![\p{L}\p{N}])' . preg_quote($v, '/') . '(s|x|es)?(?![\p{L}\p{N}])/u', $name)) {
                    $hits++;
                    break;
                }
            }
        }
        $relevance[$id] = $hits;
    }

    $ids = array_keys($rows);
    usort($ids, function ($x, $y) use ($scores, $rows, $tri, $relevance) {
        if (($relevance[$y] ?? 0) !== ($relevance[$x] ?? 0)) {
            return ($relevance[$y] ?? 0) <=> ($relevance[$x] ?? 0);
        }
        if ($tri !== 'date') {
            $px = assistant_product_card($rows[$x])['prix'];
            $py = assistant_product_card($rows[$y])['prix'];
            return $tri === 'prix_asc' ? $px <=> $py : $py <=> $px;
        }
        return ($scores[$y] ?? 0) <=> ($scores[$x] ?? 0);
    });

    /* Si certains produits ont le mot dans leur nom, on écarte ceux qui ne l'ont que dans la description */
    $best = $ids ? ($relevance[$ids[0]] ?? 0) : 0;
    if ($best > 0) {
        $ids = array_values(array_filter($ids, function ($id) use ($relevance) {
            return ($relevance[$id] ?? 0) > 0;
        }));
    }

    $items = [];
    foreach (array_slice($ids, 0, $limit) as $id) {
        $items[] = assistant_product_card($rows[$id]);
    }
    return ['items' => $items, 'total_found' => count($ids), 'name_matched' => $best > 0];
}

/**
 * Produits qui répondent au besoin exprimé (« cadeau pour ma maman » → parfum, sac…), via la recherche assistée.
 *
 * @return list<array> cartes produit (vide si rien de pertinent)
 */
function assistant_produits_par_besoin(string $message, int $limit = 6): array
{
    try {
        require_once __DIR__ . '/assistant_recherche_ia.php';
        $r = assistant_recherche_ia($message, null, null, null, $limit);
    } catch (Throwable $e) {
        return [];
    }
    return $r ? array_map('assistant_product_card', $r['produits']) : [];
}

/**
 * Produits à proposer quand la recherche exacte ne donne rien : même rayon, puis promos, puis nouveautés.
 *
 * @param array{categorie_id?:int|null, categorie?:string} $base
 */
function assistant_produits_alternatifs(array $base = []): array
{
    $essais = [];
    if (!empty($base['categorie_id']) || !empty($base['categorie'])) {
        $essais[] = ['query' => '', 'categorie_id' => $base['categorie_id'] ?? null, 'categorie' => (string) ($base['categorie'] ?? '')];
    }
    $essais[] = ['query' => '', 'promo' => true];
    $essais[] = ['query' => '', 'tri' => 'date'];
    foreach ($essais as $a) {
        try {
            $r = assistant_tool_search_products($a + ['limit' => 6]);
        } catch (Throwable $e) {
            continue;
        }
        if (!empty($r['items'])) {
            return $r['items'];
        }
    }
    return [];
}

function assistant_tool_search_shops(array $a): array
{
    if (!function_exists('marketplace_list_boutiques')) {
        require_once dirname(__DIR__, 2) . '/models/model_boutiques_marketplace.php';
    }
    if (!function_exists('marketplace_boutique_prepare_card')) {
        require_once dirname(__DIR__) . '/marketplace_boutique_card_helpers.php';
    }
    $q = trim((string) ($a['query'] ?? ''));
    $limit = max(1, min(6, (int) ($a['limit'] ?? 4)));
    $out = [];
    foreach (marketplace_list_boutiques($q, $limit) as $b) {
        $card = marketplace_boutique_prepare_card($b);
        if (!$card) {
            continue;
        }
        $out[] = [
            'nom' => $card['nom'],
            'url' => $card['vitrine_href'],
            'logo' => $card['logo_url'],
            'produits' => (int) ($card['nb_produits'] ?? 0),
            'region' => (string) ($card['region'] ?? ''),
        ];
    }
    return ['items' => $out];
}

function assistant_tool_my_orders(): array
{
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return ['logged_in' => false, 'items' => []];
    }
    if (!function_exists('get_commandes_by_user_id')) {
        require_once dirname(__DIR__, 2) . '/models/model_commandes_admin.php';
    }
    if (!function_exists('commande_card_label')) {
        require_once dirname(__DIR__) . '/commande_card_helpers.php';
    }
    $items = [];
    foreach (array_slice((array) get_commandes_by_user_id($uid), 0, 4) as $c) {
        $ts = strtotime((string) ($c['date_commande'] ?? ''));
        $items[] = [
            'numero' => (string) ($c['numero_commande'] ?? ''),
            'statut' => html_entity_decode(commande_card_label((string) ($c['statut'] ?? '')), ENT_QUOTES, 'UTF-8'),
            'montant' => (float) ($c['montant_total'] ?? 0),
            'date' => $ts ? date('d/m/Y', $ts) : '',
            'url' => app_url('/user/commande-categorie.php?commande_id=' . (int) $c['id']),
        ];
    }
    return ['logged_in' => true, 'items' => $items];
}

/** Meilleures entrées de la base de connaissances pour un texte. */
/* ================= Compréhension des questions (base de connaissances) ================= */

/** Mots vides pour la comparaison des questions (plus courts que ceux de la recherche produit). */
function assistant_kb_stopwords(): array
{
    static $sw = null;
    if ($sw === null) {
        $sw = array_flip([
            'je', 'j', 'tu', 'il', 'elle', 'on', 'nous', 'vous', 'ils', 'me', 'moi', 'te', 'se', 's', 'mon', 'ma', 'mes', 'ton', 'ta',
            'son', 'sa', 'ses', 'votre', 'vos', 'notre', 'le', 'la', 'les', 'l', 'un', 'une', 'des', 'du', 'de', 'd', 'au', 'aux',
            'a', 'et', 'ou', 'en', 'dans', 'sur', 'pour', 'par', 'avec', 'ce', 'cet', 'cette', 'ces', 'ca', 'c', 'qui', 'que', 'qu',
            'est', 'sont', 'suis', 'ai', 'as', 'avez', 'y', 'ne', 'n', 'pas', 'plus', 'tres', 'bien', 'svp', 'stp', 'merci',
            'bonjour', 'salut', 'bonsoir', 'svp', 'il', 'faut', 'faire', 'fait', 'peut', 'peux', 'pouvez', 'puis', 'veux', 'voudrais',
            'comment', 'pourquoi', 'quand', 'quel', 'quelle', 'quels', 'quelles', 'quoi', 'est-ce', 'si', 'deja', 'aussi', 'encore',
            'moi', 'site', 'plateforme', 'colobanes', 'application', 'appli', 'please', 'help', 'aide', 'aider', 'question',
        ]);
    }
    return $sw;
}

/** Racine légère d'un mot français (pluriels, quelques terminaisons verbales). */
function assistant_kb_stem(string $w): string
{
    if (mb_strlen($w) <= 3) {
        return $w;
    }
    foreach (['ements' => 'ement', 'ations' => 'ation', 'aient' => '', 'eront' => '', 'erais' => '', 'erait' => '', 'ions' => '', 'iez' => '', 'ees' => '', 'ee' => '', 'es' => '', 'er' => '', 'ez' => '', 'e' => '', 's' => '', 'x' => ''] as $suf => $rep) {
        $l = strlen($suf);
        if (strlen($w) - $l >= 4 && substr($w, -$l) === $suf) {
            return substr($w, 0, -$l) . $rep;
        }
    }
    return $w;
}

/** Mots significatifs (racines) d'un texte. */
function assistant_kb_tokens(string $text, bool $expand = true): array
{
    $t = preg_replace("/[^a-z0-9' -]+/u", ' ', assistant_norm($text));
    $t = str_replace(["'", '-'], ' ', $t);
    $sw = assistant_kb_stopwords();
    $out = [];
    foreach (preg_split('/\s+/', $t, -1, PREG_SPLIT_NO_EMPTY) as $w) {
        if (isset($sw[$w]) || (mb_strlen($w) < 2 && !ctype_digit($w))) {
            continue;
        }
        $out[] = assistant_kb_stem($w);
        if (!$expand) {
            continue;
        }
        foreach (assistant_synonyms($w) as $syn) {
            foreach (explode(' ', assistant_norm($syn)) as $sw2) {
                if ($sw2 !== '' && !isset($sw[$sw2])) {
                    $out[] = assistant_kb_stem($sw2);
                }
            }
        }
    }
    return array_values(array_unique($out));
}

/** Index des entrées : mots de chaque entrée + poids IDF (calculé une fois par requête). */
function assistant_kb_index(): array
{
    static $idx = null;
    if ($idx !== null) {
        return $idx;
    }
    $docs = [];
    $df = [];
    foreach (assistant_knowledge() as $i => $k) {
        $text = $k['title'] . ' ' . implode(' ', $k['keywords']) . ' ' . implode(' ', $k['questions']);
        $toks = array_flip(assistant_kb_tokens($text, false));
        $docs[$i] = $toks;
        foreach ($toks as $tk => $_) {
            $df[$tk] = ($df[$tk] ?? 0) + 1;
        }
    }
    $n = max(1, count($docs));
    $idf = [];
    foreach ($df as $tk => $c) {
        $idf[$tk] = log(1 + $n / $c);
    }
    $idx = ['docs' => $docs, 'idf' => $idf, 'vocab' => array_keys($idf)];
    return $idx;
}

/** Clé phonétique française simplifiée (« livrezon » et « livraison » donnent la même clé). */
function assistant_kb_phon(string $w): string
{
    $w = preg_replace(['/tion/', '/eau|au/', '/ai|ei|e/', '/ph/', '/qu|q/', '/c(?=[eiy])/', '/c|k/', '/z|ss|s/', '/h/', '/y/', '/(.)\\1+/'], ['sion', 'o', 'e', 'f', 'k', 's', 'k', 's', '', 'i', '$1'], $w);
    return rtrim($w, 'estx');
}

/** Corrige une faute de frappe en rapprochant un mot du vocabulaire connu. */
function assistant_kb_fuzzy(string $tok, array $vocab): ?string
{
    $len = strlen($tok);
    if ($len < 4) {
        return null;
    }
    static $phon = [];
    $key = assistant_kb_phon($tok);
    $best = null;
    $bestd = $len >= 8 ? 2 : ($len >= 5 ? 1 : 0);
    foreach ($vocab as $v) {
        if (!isset($phon[$v])) {
            $phon[$v] = assistant_kb_phon($v);
        }
        if (strlen($phon[$v]) >= 3 && $phon[$v] === $key) {
            return $v;
        }
        if ($bestd === 0 || abs(strlen($v) - $len) > $bestd) {
            continue;
        }
        $d = levenshtein($tok, $v);
        if ($d <= $bestd && ($best === null || $d < $bestd)) {
            $best = $v;
            $bestd = $d;
        }
    }
    return $best;
}

/**
 * Entrées de la base les plus proches du message, avec score de confiance.
 *
 * @return array<int, array{score: float, entry: array}>
 */
function assistant_kb_scored(string $message, int $max = 3): array
{
    $kb = assistant_knowledge();
    $idx = assistant_kb_index();
    $t = ' ' . preg_replace('/\s+/', ' ', str_replace(["'", '-', '?', '!', ',', '.'], ' ', assistant_norm($message))) . ' ';

    $q = assistant_kb_tokens($message);
    $qt = [];
    foreach ($q as $tok) {
        if (isset($idx['idf'][$tok])) {
            $qt[$tok] = $idx['idf'][$tok];
        } elseif (($fix = assistant_kb_fuzzy($tok, $idx['vocab'])) !== null) {
            $qt[$fix] = $idx['idf'][$fix] * 0.85;
        }
    }
    $qsum = array_sum($qt) ?: 1.0;

    $scored = [];
    foreach ($kb as $i => $k) {
        /* 1) Expressions-clés présentes telles quelles : signal fort */
        $kw = 0.0;
        foreach ($k['keywords'] as $phrase) {
            if (strpos($t, ' ' . $phrase . ' ') !== false) {
                $kw += 1.0 + 0.6 * substr_count($phrase, ' ');
            }
        }
        /* 2) Recouvrement pondéré des mots avec l'entrée (titre, mots-clés, questions d'entraînement) */
        $hit = 0.0;
        foreach ($qt as $tok => $w) {
            if (isset($idx['docs'][$i][$tok])) {
                $hit += $w;
            }
        }
        $cover = $hit / $qsum;
        $score = $kw * 1.2 + $cover * 2.5 + ($hit > 0 ? min(1.0, $hit / 6) : 0);
        if ($score > 0.35) {
            $scored[] = ['score' => round($score, 3), 'entry' => $k];
        }
    }
    usort($scored, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    return array_slice($scored, 0, $max);
}

/** Compatibilité : entrées seules, les plus pertinentes d'abord. */
function assistant_kb_match(string $message, int $max = 2): array
{
    return array_map(function ($s) {
        return $s['entry'];
    }, array_filter(assistant_kb_scored($message, $max), function ($s) {
        return $s['score'] >= 1.0;
    }));
}

/** Remplit les données vivantes d'une réponse ({{stats}}, {{pays}}, {{categories}}…). */
function assistant_kb_render(string $answer): string
{
    if (strpos($answer, '{{') === false) {
        return $answer;
    }
    return preg_replace_callback('/\{\{([a-z_]+)\}\}/', function ($m) {
        static $cache = [];
        if (!array_key_exists($m[1], $cache)) {
            $cache[$m[1]] = assistant_kb_live_value($m[1]);
        }
        return $cache[$m[1]];
    }, $answer);
}

function assistant_kb_live_value(string $key): string
{
    global $db;
    try {
        switch ($key) {
            case 'stats':
                $p = (int) $db->query("SELECT COUNT(*) FROM produits WHERE statut IN ('actif','rupture_stock')")->fetchColumn();
                $b = (int) $db->query("SELECT COUNT(DISTINCT a.id) FROM admin a JOIN produits p ON p.admin_id = a.id AND p.statut = 'actif' WHERE a.role = 'vendeur' AND a.statut = 'actif'")->fetchColumn();
                return '**' . number_format($b, 0, ',', ' ') . ' boutiques** actives et **' . number_format($p, 0, ',', ' ') . ' produits** en ligne';
            case 'pays':
                if (!function_exists('marketplace_countries_list')) {
                    require_once dirname(__DIR__) . '/marketplace_countries.php';
                }
                return '**' . implode('**, **', array_column(marketplace_countries_list(), 'label')) . '**';
            case 'categories':
                $noms = $db->query('SELECT nom FROM categories_generales ORDER BY sort_ordre, nom')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $noms = array_map(function ($n) {
                    $n = trim((string) $n);
                    return mb_strtoupper(mb_substr($n, 0, 1, 'UTF-8'), 'UTF-8') . mb_strtolower(mb_substr($n, 1, null, 'UTF-8'), 'UTF-8');
                }, $noms);
                return $noms ? implode(', ', $noms) : 'mode, électronique, maison et bien d’autres';
            case 'certifs':
                if (!function_exists('vendeur_certification_niveaux')) {
                    require_once dirname(__DIR__, 2) . '/models/model_vendeur_certification.php';
                }
                $lignes = [];
                foreach (vendeur_certification_niveaux() as $n) {
                    $lignes[] = '• **' . $n['label'] . '** : ' . $n['desc'];
                }
                return implode("\n", $lignes);
            case 'dons_reservation':
            case 'dons_limite':
                if (!function_exists('dons_param')) {
                    require_once dirname(__DIR__, 2) . '/models/model_dons.php';
                }
                return (string) (int) dons_param($key === 'dons_reservation' ? 'reservation_heures' : 'max_annonces_jour');
        }
    } catch (Throwable $e) {
        error_log('[assistant_kb_live_value] ' . $e->getMessage());
    }
    return '';
}

function assistant_kb_links(array $entry): array
{
    $links = [];
    foreach ($entry['links'] as $l) {
        $links[] = ['label' => $l[0], 'url' => strpos($l[1], 'http') === 0 ? $l[1] : app_url($l[1])];
    }
    return $links;
}

/** Réponse tirée de la base de connaissances (données vivantes, liens, sujets voisins). */
function assistant_kb_reply(array $res, array $kb): array
{
    $main = $kb[0];
    $res['reply'] = assistant_kb_render($main['answer']);
    $res['links'] = assistant_kb_links($main);
    $res['kb_id'] = $main['id'];
    $suggest = [];
    for ($i = 1; $i < count($kb) && count($suggest) < 2; $i++) {
        $suggest[] = $kb[$i]['title'];
    }
    $res['suggestions'] = array_merge($suggest, ['Trouver un produit', 'Contacter le support']);
    return $res;
}

/* ================= Moteur intégré (sans IA externe) ================= */

function assistant_fmt_price(float $p): string
{
    return number_format($p, 0, ',', ' ') . ' FCFA';
}

function assistant_local_reply(string $message, array $context = []): array
{
    $bot = assistant_config()['bot_name'];
    $t = assistant_norm($message);
    $res = ['reply' => '', 'products' => [], 'shops' => [], 'orders' => [], 'links' => [], 'suggestions' => []];

    /* Salutations / remerciements */
    if (preg_match('/^(bonjour|bonsoir|salut|hello|hi|coucou|salam|salamalekoum|asalamalekoum|bjr|slt|yo)\b[\s!.]*$/u', $t)) {
        $res['reply'] = "Bonjour 👋 Comment puis-je vous aider ?";
        $res['suggestions'] = ['Voir les promos', 'Suivre ma commande', 'Livraison'];
        return $res;
    }
    if (preg_match('/^(merci|thanks|super|parfait|top|ok|d.?accord|cool)\b/u', $t)) {
        $res['reply'] = "Avec plaisir ! Autre chose que je peux faire pour vous ?";
        $res['suggestions'] = ['Voir les promos', 'Trouver une boutique', 'Contacter le support'];
        return $res;
    }

    /* Invitation à chercher */
    if (preg_match('/^(trouver|chercher|rechercher) (un |des )?(produit|produits|article|articles)$/u', $t)) {
        $res['reply'] = "Que cherchez-vous ? Décrivez-le librement, par exemple :\n• « iphone 13 à moins de 250 000 »\n• « robe en promo »\n• « réfrigérateur le moins cher »";
        $res['suggestions'] = ['Voir les promos', 'Voir les nouveautés'];
        return $res;
    }

    /* Compréhension de la question (base de connaissances entraînée) */
    $scored = assistant_kb_scored($message, 3);
    $kb = array_values(array_map(function ($x) {
        return $x['entry'];
    }, array_filter($scored, function ($x) {
        return $x['score'] >= 1.0;
    })));
    $kb_conf = $scored[0]['score'] ?? 0.0;
    $is_question = (bool) preg_match('/\b(comment|pourquoi|est-ce|est ce|puis-je|puis je|peut-on|peut on|faut-il|faut il|quels? sont|quelles? sont|quelle est|c.?est quoi|qu.?est-ce|comment faire|ou trouver|ou voir|ou est|ou sont|combien de temps|que faire|que veut dire|ca veut dire|je n.?arrive pas|impossible|aide|probleme|j.?ai oublie|je veux|je voudrais|qui|vous etes)\b|\?\s*$/u', $t);
    $shopping = (bool) preg_match('/\b(cherche|recherche|veux|voudrais|besoin|avez|vendez|trouve|trouver|montre|acheter|prix|combien|moins de|plus de|entre|promo|pas cher|moins cher|plus cher|le moins|le plus)\b/u', $t);

    /* Statut de mes commandes : liste réelle du client connecté */
    $order_status = (bool) preg_match('/\b(ou (est|en est|sont)|statut|etat|suivi|suivre|voir|affiche) (de |des )?(ma|mes) commandes?\b|^(ma|mes) commandes?\b|\bma derniere commande\b|\bmes achats\b|\bpas (encore )?recu ma commande\b/u', $t)
        && !preg_match('/\b(annul|retour|rembours|pay|noter|avis|frais|delai|comment passer|comment commander)/u', $t);
    if ($order_status) {
        $orders = assistant_tool_my_orders();
        if (!$orders['logged_in']) {
            /* Non connecté : on explique le suivi (ou la réponse la plus proche), avec un lien de connexion */
            $suivi = array_values(array_filter(assistant_knowledge(), function ($e) {
                return $e['id'] === 'suivi';
            }));
            $res = assistant_kb_reply($res, (!empty($kb) && $kb_conf >= 3.0 && $kb[0]['id'] !== 'commander') ? $kb : $suivi);
            array_unshift($res['links'], ['label' => 'Se connecter', 'url' => app_url('/choix-connexion.php')]);
            return $res;
        }
        if (!$orders['logged_in']) {
            $res['reply'] = "Pour voir vos commandes, **connectez-vous** d'abord. Ensuite, demandez-moi « où est ma commande ? » et je vous donne leur statut.";
            $res['links'] = [['label' => 'Se connecter', 'url' => app_url('/choix-connexion.php')]];
        } elseif (empty($orders['items'])) {
            $res['reply'] = "Vous n'avez pas encore de commande. Dites-moi ce que vous cherchez, je vous trouve des produits !";
        } else {
            $res['reply'] = "Voici vos dernières commandes :";
            $res['orders'] = $orders['items'];
            if (preg_match('/\bpas (encore )?recu\b|\bretard\b/u', $t)) {
                $res['reply'] = "Voici vos dernières commandes. Si l'une tarde, ouvrez-la et touchez **Message** ou **Appeler** pour joindre le vendeur ; sans réponse, contactez le support avec son numéro.";
            }
            $res['links'] = [['label' => 'Toutes mes commandes', 'url' => app_url('/user/mes-commandes.php')]];
        }
        return $res;
    }

    /* Requête courte sans question (« iphone », « robe rouge ») : on cherche d'abord dans le catalogue */
    if (!$is_question && !$shopping && str_word_count($t) <= 3 && $kb_conf < 4.5) {
        $quick = assistant_parse_search($message);
        if ($quick['query'] !== '' || $quick['categorie_id'] !== null) {
            $found = assistant_tool_search_products($quick + ['limit' => 6]);
            if (!empty($found['items']) && !empty($found['name_matched'])) {
                $n = $found['total_found'];
                $res['reply'] = "Voici **{$n} produit" . ($n > 1 ? 's' : '') . "** pour « " . ($quick['query'] !== '' ? $quick['query'] : $quick['categorie_nom']) . " » :";
                $res['products'] = $found['items'];
                $res['context'] = ['query' => $quick['query'], 'categorie_id' => $quick['categorie_id'], 'categorie_nom' => $quick['categorie_nom']];
                $res['suggestions'] = array_merge(['Les moins chers', 'En promotion'], !empty($kb) ? [$kb[0]['title']] : []);
                return $res;
            }
        }
    }

    /* Question claire sur la plateforme : la base de connaissances répond d'abord */
    if (!empty($kb) && $kb_conf >= 2.2 && ($is_question || !$shopping)) {
        return assistant_kb_reply($res, $kb);
    }

    /* Question très reconnue malgré un mot « shopping » (prix, promo…) : on vérifie qu'il ne s'agit pas d'un produit */
    if (!empty($kb) && $kb_conf >= 2.5 && $shopping) {
        $probe = assistant_parse_search($message);
        $probe_found = ($probe['query'] !== '') ? assistant_tool_search_products($probe + ['limit' => 1]) : ['items' => []];
        if (empty($probe_found['items']) || empty($probe_found['name_matched']) || $kb_conf >= 4.5) {
            return assistant_kb_reply($res, $kb);
        }
    }

    /* Boutiques */
    if (preg_match('/\b(boutique|boutiques|vendeur|vendeurs|magasin|magasins|shop)\b/u', $t)
        && !preg_match('/\b(devenir|ouvrir|creer|vendre|inscrire|certifi|contacter|ecrire|parler|signaler|suivre|abonner)\b/u', $t)) {
        $q = trim(preg_replace('/\b(boutique|boutiques|vendeur|vendeurs|magasin|magasins|shop|la|le|les|de|du|des|une|un|je|cherche|trouver|trouve|voir|montre|moi|quelle|quelles|il y a|existe|nom|appelee?)\b/u', ' ', $t));
        $q = trim(preg_replace('/\s+/u', ' ', $q));
        $shops = assistant_tool_search_shops(['query' => mb_strlen($q) >= 2 ? $q : '', 'limit' => 4]);
        if (!empty($shops['items'])) {
            $res['reply'] = $q !== '' ? "Voici les boutiques qui correspondent à « {$q} » :" : "Voici quelques boutiques de la plateforme :";
            $res['shops'] = $shops['items'];
            $res['links'] = [['label' => 'Toutes les boutiques', 'url' => app_url('/boutiques.php')]];
            return $res;
        }
    }

    if (!empty($kb) && ($is_question || !$shopping)) {
        return assistant_kb_reply($res, $kb);
    }

    /* Recherche de produits (un affinage seul — « les moins chers », « en promo » — reprend la recherche précédente) */
    $s = assistant_parse_search($message);
    $is_refine = (bool) preg_match('/^(les |en |du |plus |moins )?(moins chers?|plus chers?|pas chers?|promotion|promo|promos|plus recents?|recents?|nouveautes|moins de|plus de|entre|sous|max)\b/u', $t);
    if ($s['query'] === '' && $s['categorie_id'] === null && $is_refine && !empty($context['query'])) {
        $s['query'] = (string) $context['query'];
        $s['categorie_id'] = !empty($context['categorie_id']) ? (int) $context['categorie_id'] : null;
        $s['categorie_nom'] = (string) ($context['categorie_nom'] ?? '');
    }
    $res['context'] = ['query' => $s['query'], 'categorie_id' => $s['categorie_id'], 'categorie_nom' => $s['categorie_nom']];
    if ($s['query'] !== '' || $s['categorie_id'] !== null || $s['promo'] || $s['prix_max'] !== null || $s['prix_min'] !== null) {
        $found = assistant_tool_search_products($s + ['limit' => 6]);
        $crit = [];
        if ($s['query'] !== '') {
            $crit[] = '« ' . $s['query'] . ' »';
        }
        if ($s['categorie_nom'] !== '') {
            $crit[] = 'rayon ' . $s['categorie_nom'];
        }
        if ($s['promo']) {
            $crit[] = 'en promotion';
        }
        if ($s['prix_min'] !== null && $s['prix_max'] !== null) {
            $crit[] = 'entre ' . assistant_fmt_price($s['prix_min']) . ' et ' . assistant_fmt_price($s['prix_max']);
        } elseif ($s['prix_max'] !== null) {
            $crit[] = 'à moins de ' . assistant_fmt_price($s['prix_max']);
        } elseif ($s['prix_min'] !== null) {
            $crit[] = 'à partir de ' . assistant_fmt_price($s['prix_min']);
        }
        $crit_txt = implode(', ', $crit);

        if (!empty($found['items'])) {
            $n = $found['total_found'];
            $res['reply'] = "J'ai trouvé **{$n} produit" . ($n > 1 ? 's' : '') . "** " . ($crit_txt !== '' ? "pour {$crit_txt}" : '')
                . ($s['tri'] === 'prix_asc' ? ', du moins cher au plus cher' : ($s['tri'] === 'prix_desc' ? ', du plus cher au moins cher' : '')) . ' :';
            $res['products'] = $found['items'];
            if ($s['query'] !== '') {
                $res['links'] = [['label' => 'Voir tous les résultats', 'url' => app_url('/produits.php?recherche=' . rawurlencode($s['query']))]];
            }
            $res['suggestions'] = $s['tri'] === 'prix_asc' ? ['En promotion', 'Les plus récents'] : ['Les moins chers', 'En promotion'];
            return $res;
        }

        /* Jamais de « rien trouvé » sec : on élargit (même rayon, puis promos, puis nouveautés) */
        $alt = ['items' => assistant_produits_alternatifs(['categorie_id' => $s['categorie_id']])];
        $res['reply'] = ($crit_txt !== '' ? "Ce que vous cherchez ({$crit_txt}) n'est pas en ligne pour le moment. " : '')
            . (!empty($alt['items']) ? 'Voici des articles qui peuvent vous intéresser :' : 'Parcourez le catalogue, de nouveaux articles arrivent chaque jour.');
        $res['products'] = $alt['items'];
        $res['links'] = [['label' => 'Parcourir les produits', 'url' => app_url('/produits.php')]];
        $res['suggestions'] = ['Voir les promos', 'Voir les nouveautés'];
        return $res;
    }

    if (!empty($kb)) {
        return assistant_kb_reply($res, $kb);
    }

    $res['reply'] = "Je peux vous aider tout de suite 🙂 Dites-moi par exemple :\n• « chaussures homme à moins de 20 000 »\n• « où est ma commande ? »\n• « comment se passe la livraison ? »\n• « comment vendre sur COLObanes ? »";
    $res['suggestions'] = ['Trouver un produit', 'Suivre ma commande', 'Livraison', 'Contacter le support'];
    return $res;
}

/* ================= IA Google Gemini (facultative) ================= */

function assistant_gemini_system_prompt(): string
{
    $bot = assistant_config()['bot_name'];
    $kb = [];
    foreach (assistant_knowledge() as $k) {
        $kb[] = '## ' . $k['title'] . "\n" . assistant_kb_render($k['answer']);
    }
    $cats = array_map(function ($c) {
        return $c['nom'];
    }, assistant_categories());

    return "Tu es {$bot}, l'assistante officielle de COLObanes, marketplace en ligne au Sénégal (Colobane, Dakar). "
        . "STYLE : tu **vouvoies toujours** (jamais « tu »), ton chaleureux et professionnel, réponse **courte** : 2 à 4 phrases, "
        . "ou au plus 4 étapes numérotées. **Aucun émoji.** **Gras** pour les mots importants. Prix en FCFA. "
        . "Langue : celle de l'utilisateur (anglais → anglais) ; s'il écrit en **wolof**, réponds en français simple. "
        . "Ne commence pas tes réponses par « Bienvenue » ni « Bonjour » (sauf si l'utilisateur te salue).\n"
        . "QUI PARLE : si la question parle de « mes produits », « ma boutique », « mes clients », « publier », « mon stock », "
        . "« ajouter la taille », c'est un **vendeur** : réponds du point de vue de l'espace vendeur (Produits, Commandes, Certification…). "
        . "Sinon, c'est un **client** qui achète.\n"
        . "RÈGLES : 1) Pour toute recherche de produit, de prix, de promo ou de boutique, appelle TOUJOURS l'outil adapté ; "
        . "ne liste jamais de produit inventé, ne cite que les résultats des outils (les cartes produits s'affichent automatiquement, "
        . "inutile de répéter toutes les infos). 2) Pour les commandes de l'utilisateur, appelle my_orders. "
        . "3) Pour les questions sur la plateforme, base-toi sur la base de connaissances ci-dessous. "
        . "Si la réponse exacte n'y est pas, donne quand même une réponse utile et concrète (étapes générales, page à ouvrir), "
        . "puis propose le support (contact@colobanes.com) en complément. "
        . "4) Ne réponds JAMAIS par une phrase négative seule (« je ne sais pas », « je n'ai pas trouvé », « je ne peux pas vous aider ») : "
        . "si une recherche de produit ne donne rien, relance search_products avec un mot plus large (le type de produit, sans la marque ni le budget) "
        . "ou avec promo=true, et présente ces alternatives positivement. Ne commence jamais par « Désolé » : écris par exemple "
        . "« L'iPhone 17 Pro Max n'est pas encore en ligne à ce prix. Voici des modèles proches : ». "
        . "Les cartes produits s'affichent sous ta réponse : ne recopie pas la liste, 1 ou 2 phrases suffisent. "
        . "Ne cite JAMAIS un modèle, une marque ou un prix qui ne vient pas d'un résultat d'outil (pas d'« iPhone 15 en promo » imaginé) ; "
        . "ne propose pas « je peux vous les afficher » : les articles trouvés sont déjà affichés. "
        . "5) Ne demande jamais de mot de passe ni de données bancaires. "
        . "6) Reste sur le sujet COLObanes et le shopping : à une question hors sujet, réponds en une phrase puis propose ton aide sur COLObanes. "
        . "Ne promets jamais un délai, un remboursement ou une fonction qui n'est pas dans la base de connaissances. "
        . "Si aucune boutique ne correspond (correspondance_exacte = false), présente celles que l'outil renvoie comme simples suggestions "
        . "(« Voici des boutiques actives sur COLObanes ») sans affirmer ce qu'elles vendent. "
        . "Tu ne vois QUE les commandes du compte connecté (outil my_orders) : ne propose jamais de vérifier une commande à partir d'un numéro ; "
        . "si l'utilisateur n'est pas connecté, invite-le à se connecter puis à ouvrir **Mes commandes**. "
        . "7) Ne divulgue jamais tables SQL, chemins de fichiers, e-mails utilisateurs, clés API ou secrets.\n\n"
        . "Rayons du catalogue : " . implode(', ', $cats) . "\n\n"
        . "BASE DE CONNAISSANCES :\n" . implode("\n\n", $kb);
}

function assistant_gemini_tools(): array
{
    return [[
        'function_declarations' => [
            [
                'name' => 'search_products',
                'description' => 'Recherche des produits dans le catalogue COLObanes. Utiliser des mots-clés courts (type de produit, marque, modèle).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'query' => ['type' => 'STRING', 'description' => 'Mots-clés, ex. "iphone 15", "chaussures homme". Vide pour parcourir un rayon.'],
                        'categorie' => ['type' => 'STRING', 'description' => 'Nom du rayon si précisé'],
                        'prix_min' => ['type' => 'NUMBER', 'description' => 'Prix minimum en FCFA'],
                        'prix_max' => ['type' => 'NUMBER', 'description' => 'Prix maximum en FCFA'],
                        'promo' => ['type' => 'BOOLEAN', 'description' => 'Uniquement les produits en promotion'],
                        'tri' => ['type' => 'STRING', 'enum' => ['date', 'prix_asc', 'prix_desc'], 'description' => 'Ordre des résultats'],
                    ],
                ],
            ],
            [
                'name' => 'search_shops',
                'description' => 'Recherche des boutiques (vendeurs) par nom.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => ['query' => ['type' => 'STRING', 'description' => 'Nom ou partie du nom de la boutique']],
                ],
            ],
            [
                'name' => 'my_orders',
                'description' => "Dernières commandes de l'utilisateur connecté, avec leur statut.",
                'parameters' => ['type' => 'OBJECT', 'properties' => new stdClass()],
            ],
        ],
    ]];
}

function assistant_gemini_call(array $payload): ?array
{
    $cfg = assistant_config();
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode((string) $cfg['gemini_model']) . ':generateContent';
    $ch = curl_init($url);
    $opts = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $cfg['gemini_api_key'],
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ];
    $ca = dirname(__DIR__, 2) . '/config/cacert.pem';
    if (is_readable($ca)) {
        $opts[CURLOPT_CAINFO] = $ca;
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code !== 200) {
        error_log('[assistant] Gemini HTTP ' . $code . ' ' . substr((string) $body, 0, 300));
        return null;
    }
    $json = json_decode((string) $body, true);
    return is_array($json) ? $json : null;
}

/**
 * @param array<int, array{role:string, text:string}> $history
 */
function assistant_gemini_reply(string $message, array $history): ?array
{
    $contents = [];
    foreach (array_slice($history, -10) as $h) {
        $role = ($h['role'] ?? '') === 'bot' ? 'model' : 'user';
        $text = trim((string) ($h['text'] ?? ''));
        if ($text !== '') {
            $contents[] = ['role' => $role, 'parts' => [['text' => mb_substr($text, 0, 1500)]]];
        }
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

    $res = ['reply' => '', 'products' => [], 'shops' => [], 'orders' => [], 'links' => [], 'suggestions' => []];

    for ($turn = 0; $turn < 4; $turn++) {
        $out = assistant_gemini_call([
            'system_instruction' => ['parts' => [['text' => assistant_gemini_system_prompt()]]],
            'contents' => $contents,
            'tools' => assistant_gemini_tools(),
            'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 700],
        ]);
        $parts = $out['candidates'][0]['content']['parts'] ?? null;
        if (!is_array($parts)) {
            return null;
        }

        $calls = [];
        $text = '';
        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $calls[] = $part['functionCall'];
            } elseif (isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        if (empty($calls)) {
            $res['reply'] = trim($text);
            return $res['reply'] !== '' ? $res : null;
        }

        $contents[] = ['role' => 'model', 'parts' => $parts];
        $responses = [];
        foreach ($calls as $call) {
            $name = (string) ($call['name'] ?? '');
            $args = is_array($call['args'] ?? null) ? $call['args'] : [];
            if ($name === 'search_products') {
                $r = assistant_tool_search_products($args + ['limit' => 6]);
                $res['products'] = $r['items'];
                $data = ['total_found' => $r['total_found'], 'products' => array_map(function ($p) {
                    return ['nom' => $p['nom'], 'prix_fcfa' => $p['prix'], 'ancien_prix' => $p['ancien_prix'], 'boutique' => $p['boutique'], 'en_stock' => $p['en_stock']];
                }, $r['items'])];
            } elseif ($name === 'search_shops') {
                $r = assistant_tool_search_shops($args);
                $res['shops'] = $r['items'];
                $data = ['shops' => array_map(function ($s) {
                    return ['nom' => $s['nom'], 'produits' => $s['produits'], 'region' => $s['region']];
                }, $r['items'])];
            } elseif ($name === 'my_orders') {
                $r = assistant_tool_my_orders();
                $res['orders'] = $r['items'];
                $data = $r;
            } else {
                $data = ['error' => 'outil inconnu'];
            }
            $responses[] = ['functionResponse' => ['name' => $name, 'response' => $data]];
        }
        $contents[] = ['role' => 'user', 'parts' => $responses];
    }
    return null;
}

/* ================= Point d'entrée ================= */

function assistant_answer(string $message, array $history = [], array $context = []): array
{
    require_once __DIR__ . '/assistant_ai.php';
    try {
        $ai = assistant_ai_reply($message, $history);
    } catch (Throwable $e) {
        error_log('[assistant] IA : ' . $e->getMessage());
        $ai = null;
    }
    if (is_array($ai) && trim((string) ($ai['reply'] ?? '')) !== '') {
        return $ai;
    }
    $local = assistant_local_reply($message, $context);
    $local['engine'] = 'local';
    return $local;
}
