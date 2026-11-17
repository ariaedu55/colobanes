<?php
/**
 * Accessoires intelligents au panier — résolution marketplace.
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/produits_similaires_engine.php';

function panier_accessoires_text_contains_keyword(string $haystack, string $keyword): bool
{
    $keyword = mb_strtolower(trim($keyword), 'UTF-8');
    if ($keyword === '' || $haystack === '') {
        return false;
    }
    if (mb_strlen($keyword, 'UTF-8') <= 3) {
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($keyword, '/') . '(?![\p{L}\p{N}])/u';
        return (bool) preg_match($pattern, $haystack);
    }
    return mb_strpos($haystack, $keyword) !== false;
}

function panier_accessoires_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $path = dirname(__DIR__) . '/config/panier_accessoires.php';
    $cfg = is_readable($path) ? (require $path) : [];
    if (!is_array($cfg)) {
        $cfg = [];
    }
    return $cfg;
}

/**
 * @param array<string, mixed> $produit
 * @return list<array<string, mixed>>
 */
function panier_accessoires_profiles_for_produit(array $produit): array
{
    $cfg = panier_accessoires_config();
    $profiles = $cfg['profiles'] ?? [];
    if (!is_array($profiles)) {
        $profiles = [];
    }

    $haystack = mb_strtolower(
        trim((string) ($produit['nom'] ?? '')) . ' '
        . trim((string) ($produit['description'] ?? '')) . ' '
        . trim((string) ($produit['categorie_nom'] ?? '')),
        'UTF-8'
    );
    $gid = (int) ($produit['categorie_generale_id'] ?? 0);
    $generale_nom = '';
    if ($gid > 0 && function_exists('get_categorie_generale_by_id')) {
        require_once __DIR__ . '/../models/model_categories.php';
        $gr = get_categorie_generale_by_id($gid);
        if (is_array($gr)) {
            $generale_nom = mb_strtolower(trim((string) ($gr['nom'] ?? '')), 'UTF-8');
        }
    }

    $matched = [];
    foreach ($profiles as $profile) {
        if (!is_array($profile) || empty($profile['id'])) {
            continue;
        }
        $hit = false;
        $rayon_bits = $profile['rayon_nom_contains'] ?? [];
        if (is_array($rayon_bits)) {
            foreach ($rayon_bits as $bit) {
                $bit = mb_strtolower(trim((string) $bit), 'UTF-8');
                if ($bit === '') {
                    continue;
                }
                if ($generale_nom !== '' && mb_strpos($generale_nom, $bit) !== false) {
                    $hit = true;
                    break;
                }
                if ($haystack !== '' && mb_strpos($haystack, $bit) !== false) {
                    $hit = true;
                    break;
                }
            }
        }
        if (!$hit) {
            $keywords = $profile['keywords'] ?? [];
            if (is_array($keywords)) {
                foreach ($keywords as $kw) {
                    $kw = mb_strtolower(trim((string) $kw), 'UTF-8');
                    if ($kw !== '' && panier_accessoires_text_contains_keyword($haystack, $kw)) {
                        $hit = true;
                        break;
                    }
                }
            }
        }
        if ($hit) {
            $matched[] = $profile;
        }
    }

    /* Profils précis (téléphonie, informatique…) d'abord. Sinon le profil que l'IA a déjà préparé pour ce produit
       (cache, jamais d'appel ici), plus fin que les règles de base ; à défaut, les règles de base (fallback). */
    $precis = array_values(array_filter($matched, static fn ($p) => empty($p['fallback'])));
    if ($precis !== []) {
        $matched = $precis;
    } elseif (empty($GLOBALS['panier_accessoires_sans_ia'])) {
        $ia = panier_accessoires_ia_profil($produit, false);
        if ($ia !== null) {
            $matched = [$ia];
        }
    }

    usort($matched, static function ($a, $b) {
        return (int) ($b['priority'] ?? 0) <=> (int) ($a['priority'] ?? 0);
    });

    return $matched;
}

/* ================= Profils préparés par l'IA (tout type de produit) ================= */

function panier_accessoires_ia_cle(array $produit): string
{
    return 'complements_' . (int) ($produit['id'] ?? 0) . '_' . substr(md5((string) ($produit['nom'] ?? '')), 0, 8);
}

function panier_accessoires_ia_consigne(): string
{
    return "Tu es vendeur conseil sur COLObanes, un marché en ligne au Sénégal. On te donne un produit qu'un client "
        . "vient de mettre dans son panier. Propose les produits COMPLÉMENTAIRES qu'il achète souvent avec "
        . "(ex. téléphone → coque, chargeur, écouteurs ; robe → sac à main, chaussures, bijoux ; farine → sucre, levure, "
        . "moule ; bébé → couches, lingettes, biberon). Jamais un produit du même type ni un concurrent du produit.\n"
        . "Pour chaque complément, donne les noms qu'on trouve dans le NOM de ces produits : des noms d'objets précis "
        . "qui ne désignent que ce complément (minuscules, sans marque, variantes et synonymes français). Jamais "
        . "d'adjectif ni de mot vague (taille, corps, cuir, top, bas, protection, silicone, spray, accessoire…).\n"
        . "Donne aussi les mots qui rendent un produit inadapté (ex. jupe → homme ; costume → femme ; iPhone → android).\n"
        . "Réponds UNIQUEMENT avec ce JSON :\n"
        . "{\"complements\": [3 à 5 objets {\"label\": \"nom court du complément (1-2 mots)\", "
        . "\"termes\": [2 à 5 noms d'objets]}], \"exclure\": [0 à 4 mots], "
        . "\"public\": \"femme\", \"homme\" ou \"mixte\" (à qui ce produit est surtout destiné), "
        . "\"exemple\": \"produit + complément + complément\"}";
}

/**
 * Profil d'accessoires créé par l'IA pour un produit sans profil configuré.
 *
 * @param bool $reseau false = cache seulement (affichage de page) ; true = appelle l'IA si besoin (API en arrière-plan)
 * @return array<string, mixed>|null
 */
function panier_accessoires_ia_profil(array $produit, bool $reseau): ?array
{
    if ((int) ($produit['id'] ?? 0) <= 0 || trim((string) ($produit['nom'] ?? '')) === '') {
        return null;
    }
    require_once __DIR__ . '/assistant/assistant_ia_json.php';

    $demande = 'Produit : ' . mb_substr(trim((string) $produit['nom']), 0, 120, 'UTF-8');
    if (!empty($produit['categorie_nom'])) {
        $demande .= "\nCatégorie : " . mb_substr((string) $produit['categorie_nom'], 0, 60, 'UTF-8');
    }
    $desc = trim(strip_tags((string) ($produit['description'] ?? '')));
    if ($desc !== '') {
        $demande .= "\nDescription : " . mb_substr(preg_replace('/\s+/u', ' ', $desc), 0, 300, 'UTF-8');
    }

    $data = assistant_ia_json(panier_accessoires_ia_consigne(), $demande, panier_accessoires_ia_cle($produit), 2592000, 8.0, $reseau);
    if (!is_array($data)) {
        return null;
    }

    $accessories = [];
    foreach (array_slice((array) ($data['complements'] ?? []), 0, 5) as $c) {
        if (!is_array($c)) {
            continue;
        }
        $label = assistant_ia_termes([$c['label'] ?? ''], 1)[0] ?? '';
        $termes = array_values(array_filter(
            array_map(static fn ($t) => mb_strtolower($t, 'UTF-8'), assistant_ia_termes($c['termes'] ?? [], 6)),
            static fn ($t) => mb_strlen($t, 'UTF-8') >= 3
        ));
        if ($label === '' || $termes === []) {
            continue;
        }
        $accessories[] = ['label' => mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1), 'termes' => $termes];
    }
    if ($accessories === []) {
        return null;
    }

    return [
        'id' => 'ia',
        'priority' => 1,
        'ia' => true,
        'public' => in_array($data['public'] ?? '', ['femme', 'homme'], true) ? $data['public'] : '',
        'bundle_example' => mb_substr(trim((string) ($data['exemple'] ?? '')), 0, 80, 'UTF-8'),
        'accessories' => $accessories,
        /* Un complément doit évoquer un des termes proposés dans son nom */
        'candidate_nom_termes_only' => true,
        'candidate_exclude_nom' => array_map(
            static fn ($t) => mb_strtolower($t, 'UTF-8'),
            assistant_ia_termes($data['exclure'] ?? [], 4)
        ),
    ];
}

/**
 * Produits du panier sans profil configuré dont l'IA n'a pas encore préparé les compléments.
 *
 * @return list<array<string, mixed>>
 */
function panier_accessoires_ia_a_preparer(array $panier_items, int $max = 3): array
{
    require_once __DIR__ . '/assistant/assistant_ia_json.php';
    $out = [];
    foreach ($panier_items as $item) {
        $pid = (int) ($item['produit_id'] ?? $item['id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $produit = get_produit_by_id($pid);
        if (!is_array($produit)) {
            continue;
        }
        /* Un profil précis suffit ; les règles de base, elles, peuvent être améliorées par l'IA */
        foreach (panier_accessoires_profiles_for_produit($produit) as $prof) {
            if (empty($prof['fallback']) && empty($prof['ia'])) {
                continue 2;
            }
        }
        if (assistant_ia_json_cache_lire(panier_accessoires_ia_cle($produit))['trouve']) {
            continue;
        }
        $out[] = $produit;
        if (count($out) >= $max) {
            break;
        }
    }
    return $out;
}

/**
 * @param array<int, array<string, mixed>> $panier_items
 * @return array{termes: array<string, true>, labels: list<string>, profiles: list<array>, montage: bool}
 */
function panier_accessoires_collect_needs(array $panier_items): array
{
    $termes = [];
    $labels = [];
    $profiles_seen = [];
    $montage = false;
    $ref_price = 0.0;
    $exclude_noms = [];

    foreach ($panier_items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $pid = (int) ($item['produit_id'] ?? $item['id'] ?? 0);
        $produit = $item;
        if ($pid > 0 && (empty($item['description']) || empty($item['categorie_nom']))) {
            $full = get_produit_by_id($pid);
            if (is_array($full)) {
                $produit = array_merge($full, $item);
            }
        }
        $item_profiles = panier_accessoires_profiles_for_produit($produit);
        if ($item_profiles !== []) {
            /* Prix de référence : l'article principal le plus cher (téléphone, ordinateur…) */
            $ref_price = max($ref_price, panier_accessoires_prix_effectif($produit));
            $nom_item = mb_strtolower((string) ($produit['nom'] ?? ''), 'UTF-8');
            foreach ($item_profiles[0]['ecosystems'] ?? [] as $eco) {
                foreach ($eco['match'] ?? [] as $m) {
                    if (panier_accessoires_text_contains_keyword($nom_item, (string) $m)) {
                        foreach ($eco['exclude'] ?? [] as $ex) {
                            $exclude_noms[mb_strtolower((string) $ex, 'UTF-8')] = true;
                        }
                        break 2;
                    }
                }
            }
        }
        foreach ($item_profiles as $profile) {
            $pid_key = (string) ($profile['id'] ?? '');
            if ($pid_key !== '' && !isset($profiles_seen[$pid_key])) {
                $profiles_seen[$pid_key] = $profile;
            }
            if (!empty($profile['montage'])) {
                $montage = true;
            }
            $accessories = $profile['accessories'] ?? [];
            if (!is_array($accessories)) {
                continue;
            }
            foreach ($accessories as $acc) {
                if (!is_array($acc)) {
                    continue;
                }
                $label = trim((string) ($acc['label'] ?? ''));
                if ($label !== '' && !in_array($label, $labels, true)) {
                    $labels[] = $label;
                }
                foreach ($acc['termes'] ?? [] as $t) {
                    $t = mb_strtolower(trim((string) $t), 'UTF-8');
                    if ($t !== '' && mb_strlen($t, 'UTF-8') >= 3) {
                        $termes[$t] = true;
                    }
                }
            }
        }
    }

    return [
        'termes' => $termes,
        'labels' => $labels,
        'profiles' => array_values($profiles_seen),
        'montage' => $montage,
        'ref_price' => $ref_price,
        'exclude_noms' => array_keys($exclude_noms),
    ];
}

/**
 * Prix réellement payé (promotion comprise).
 *
 * @param array<string, mixed> $row
 */
function panier_accessoires_prix_effectif(array $row): float
{
    $prix = (float) ($row['prix'] ?? 0);
    $promo = (float) ($row['prix_promotion'] ?? 0);
    return ($promo > 0 && $promo < $prix) ? $promo : $prix;
}

/**
 * Un accessoire suggéré doit pouvoir être ajouté tout de suite (actif et en stock).
 *
 * @param array<string, mixed> $row
 */
function panier_accessoires_row_is_addable(array $row): bool
{
    if (($row['statut'] ?? '') !== 'actif') {
        return false;
    }
    return !array_key_exists('stock', $row) || $row['stock'] === null || (int) $row['stock'] > 0;
}

/**
 * Profil prioritaire pour le filtrage des suggestions (évite mélange téléphonie / mode).
 *
 * @param array{profiles: list<array>} $needs
 * @return array<string, mixed>|null
 */
function panier_accessoires_primary_profile(array $needs): ?array
{
    $profiles = $needs['profiles'] ?? [];
    if (!is_array($profiles) || $profiles === []) {
        return null;
    }
    usort($profiles, static function ($a, $b) {
        return (int) ($b['priority'] ?? 0) <=> (int) ($a['priority'] ?? 0);
    });
    return $profiles[0];
}

/**
 * @param array<string, mixed> $row
 * @param array{termes: array<string, true>, profiles: list<array>} $needs
 */
function panier_accessoires_row_is_relevant_candidate(array $row, array $needs): bool
{
    $profile = panier_accessoires_primary_profile($needs);
    if ($profile === null) {
        return true;
    }

    $nom_l = mb_strtolower(trim((string) ($row['nom'] ?? '')), 'UTF-8');
    $desc_l = mb_strtolower(trim((string) ($row['description'] ?? '')), 'UTF-8');
    $hay_nom = $nom_l;


    $exclude = $profile['candidate_exclude_nom'] ?? [];
    if (is_array($exclude)) {
        foreach ($exclude as $ex) {
            $ex = mb_strtolower(trim((string) $ex), 'UTF-8');
            if ($ex !== '' && $hay_nom !== '' && mb_strpos($hay_nom, $ex) !== false) {
                return false;
            }
        }
    }

    $cfg = panier_accessoires_config();
    $global_ex = $cfg['candidate_exclude_nom_global'] ?? [];
    if (is_array($global_ex)) {
        foreach ($global_ex as $ex) {
            $ex = mb_strtolower(trim((string) $ex), 'UTF-8');
            if ($ex !== '' && $hay_nom !== '' && mb_strpos($hay_nom, $ex) !== false) {
                return false;
            }
        }
    }

    /* Compatibilité (accessoire Android pour un iPhone…) */
    foreach ($needs['exclude_noms'] ?? [] as $ex) {
        if ($ex !== '' && panier_accessoires_text_contains_keyword($hay_nom, (string) $ex)) {
            return false;
        }
    }

    /* Profil IA ou règles de base : le nom doit contenir un des termes (mot entier : « sac » ne trouve pas « sachet ») */
    if (!empty($profile['candidate_nom_termes_only'])) {
        foreach (array_keys($needs['termes'] ?? []) as $t) {
            if (assistant_ia_nom_contient($nom_l, (string) $t)) {
                return true;
            }
        }
        return false;
    }

    /* Pas un deuxième article principal : un accessoire reste nettement moins cher */
    $ratio = (float) ($profile['candidate_max_price_ratio'] ?? 0);
    $ref_price = (float) ($needs['ref_price'] ?? 0);
    if ($ratio > 0 && $ref_price > 0 && panier_accessoires_prix_effectif($row) > $ref_price * $ratio) {
        return false;
    }

    /* Sous-catégories d'accessoires : sinon le nom doit évoquer un accessoire attendu */
    $cat_bits = $profile['candidate_categorie_contains'] ?? [];
    if (is_array($cat_bits) && $cat_bits !== []) {
        $cat_l = mb_strtolower(trim((string) ($row['categorie_nom'] ?? '')), 'UTF-8');
        foreach ($cat_bits as $bit) {
            $bit = mb_strtolower(trim((string) $bit), 'UTF-8');
            if ($bit !== '' && $cat_l !== '' && mb_strpos($cat_l, $bit) !== false) {
                return true;
            }
        }
        /* Nom uniquement : une description qui cite « USB » ne fait pas d'un projecteur un accessoire */
        foreach (array_keys($needs['termes'] ?? []) as $t) {
            if ($hay_nom !== '' && mb_strpos($hay_nom, (string) $t) !== false) {
                return true;
            }
        }
        return false;
    }

    $generale_bits = $profile['candidate_generale_contains'] ?? [];
    if (!is_array($generale_bits) || $generale_bits === []) {
        return true;
    }

    $generale_nom = '';
    $gid = (int) ($row['categorie_generale_id'] ?? 0);
    if ($gid > 0 && function_exists('get_categorie_generale_by_id')) {
        require_once __DIR__ . '/../models/model_categories.php';
        $gr = get_categorie_generale_by_id($gid);
        if (is_array($gr)) {
            $generale_nom = mb_strtolower(trim((string) ($gr['nom'] ?? '')), 'UTF-8');
        }
    }

    foreach ($generale_bits as $bit) {
        $bit = mb_strtolower(trim((string) $bit), 'UTF-8');
        if ($bit === '') {
            continue;
        }
        if ($generale_nom !== '' && mb_strpos($generale_nom, $bit) !== false) {
            return true;
        }
    }

    $termes = $needs['termes'] ?? [];
    if (is_array($termes)) {
        foreach (array_keys($termes) as $terme) {
            $terme = (string) $terme;
            if ($terme === '') {
                continue;
            }
            if ($nom_l !== '' && mb_strpos($nom_l, $terme) !== false) {
                return true;
            }
            if ($desc_l !== '' && mb_strpos($desc_l, $terme) !== false) {
                return true;
            }
        }
    }

    return false;
}

/**
 * @param list<array<string, mixed>> $profiles
 */
function panier_accessoires_resolve_generale_id_by_nom(string $nom_part): int
{
    $nom_part = trim($nom_part);
    if ($nom_part === '') {
        return 0;
    }
    require_once __DIR__ . '/../models/model_categories.php';
    if (!function_exists('categories_generales_table_exists') || !categories_generales_table_exists()) {
        return 0;
    }
    global $db;
    try {
        $st = $db->prepare('SELECT id FROM categories_generales WHERE nom LIKE :n LIMIT 1');
        $st->execute(['n' => '%' . $nom_part . '%']);
        return (int) ($st->fetchColumn() ?: 0);
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * @param array<string, true> $termes
 * @param array<int, true> $exclude_map
 * @return array<int, array{row: array, score: float, terme: string}>
 */
function panier_accessoires_search_by_termes(array $termes, array $exclude_map, int $per_term = 8): array
{
    global $db;
    $candidates = [];
    $vj = produits_sql_vendeur_fragment();

    foreach (array_keys($termes) as $terme) {
        $exec_params = [];
        $where = "p.statut IN ('actif', 'rupture_stock') AND LOWER(p.nom) LIKE ?";
        $exec_params[] = '%' . $terme . '%';
        if (!empty($exclude_map)) {
            $ids = array_keys($exclude_map);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $where .= " AND p.id NOT IN ($ph)";
            foreach ($ids as $id) {
                $exec_params[] = (int) $id;
            }
        }
        produits_append_region_positional($where, $exec_params, null);
        try {
            $sql = "
                SELECT p.*, c.nom AS categorie_nom " . $vj['select'] . "
                FROM produits p
                LEFT JOIN categories c ON p.categorie_id = c.id
                " . $vj['join'] . "
                WHERE $where
                ORDER BY p.date_creation DESC
                LIMIT " . (int) $per_term;
            $st = $db->prepare($sql);
            $st->execute($exec_params);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0 || isset($exclude_map[$id])) {
                    continue;
                }
                $nom_l = mb_strtolower((string) ($row['nom'] ?? ''), 'UTF-8');
                $score = 40.0;
                if (mb_strpos($nom_l, $terme) !== false) {
                    $score += 25;
                }
                if (!isset($candidates[$id])) {
                    $candidates[$id] = ['row' => $row, 'score' => $score, 'terme' => $terme];
                } else {
                    $candidates[$id]['score'] += 8;
                }
            }
        } catch (PDOException $e) {
            error_log('[panier_accessoires_search] ' . $e->getMessage());
        }
    }

    return $candidates;
}

/**
 * @param array<string, mixed> $row
 * @param list<string> $termes
 */
function panier_accessoires_row_matches_termes(array $row, array $termes): bool
{
    $nom_l = mb_strtolower(trim((string) ($row['nom'] ?? '')), 'UTF-8');
    $desc_l = mb_strtolower(trim((string) ($row['description'] ?? '')), 'UTF-8');
    foreach ($termes as $t) {
        $t = mb_strtolower(trim((string) $t), 'UTF-8');
        if ($t === '' || mb_strlen($t, 'UTF-8') < 3) {
            continue;
        }
        if ($nom_l !== '' && mb_strpos($nom_l, $t) !== false) {
            return true;
        }
        if ($desc_l !== '' && mb_strpos($desc_l, $t) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Le produit correspond-il au créneau (coque, sac…) ? Profils IA / règles de base : nom seul, mot entier.
 *
 * @param list<string> $termes
 */
function panier_accessoires_slot_matches(array $row, array $termes, array $profile): bool
{
    if (empty($profile['candidate_nom_termes_only'])) {
        return panier_accessoires_row_matches_termes($row, $termes);
    }
    foreach ($termes as $t) {
        if (assistant_ia_nom_contient((string) ($row['nom'] ?? ''), (string) $t)) {
            return true;
        }
    }
    return false;
}

/**
 * Une suggestion par créneau d'accessoire (coque, chargeur, souris…) — prioritaire.
 *
 * @param array<string, true> $exclude_map
 * @param array<int, array{row: array, score: float}> $scored
 * @return list<array{row: array, slot_label: string}>
 */
function panier_accessoires_pick_bundle_products(array $needs, array $exclude_map, array $scored, int $max_slots = 3): array
{
    $profile = panier_accessoires_primary_profile($needs);
    if ($profile === null) {
        return [];
    }
    $accessories = $profile['accessories'] ?? [];
    if (!is_array($accessories) || $accessories === []) {
        return [];
    }

    $picked = [];
    $picked_ids = [];

    foreach (array_slice($accessories, 0, max(1, $max_slots)) as $slot) {
        if (!is_array($slot)) {
            continue;
        }
        $termes_list = $slot['termes'] ?? [];
        if (!is_array($termes_list) || $termes_list === []) {
            continue;
        }
        $slot_label = trim((string) ($slot['label'] ?? ''));
        $best = null;
        $best_score = -1.0;

        foreach ($scored as $id => $data) {
            if (isset($exclude_map[$id]) || isset($picked_ids[$id])) {
                continue;
            }
            $row = $data['row'];
            if (!panier_accessoires_slot_matches($row, $termes_list, $profile)) {
                continue;
            }
            if (!panier_accessoires_row_is_relevant_candidate($row, $needs)) {
                continue;
            }
            $s = (float) $data['score'] + 60.0;
            if ($s > $best_score) {
                $best_score = $s;
                $best = ['row' => $row, 'slot_label' => $slot_label];
            }
        }

        if ($best === null) {
            $termes_map = [];
            foreach ($termes_list as $t) {
                $t = mb_strtolower(trim((string) $t), 'UTF-8');
                if ($t !== '' && mb_strlen($t, 'UTF-8') >= 3) {
                    $termes_map[$t] = true;
                }
            }
            if ($termes_map !== []) {
                $exclude = $exclude_map;
                foreach ($picked_ids as $pid => $_) {
                    $exclude[$pid] = true;
                }
                foreach (panier_accessoires_search_by_termes($termes_map, $exclude, 6) as $id => $data) {
                    if (isset($picked_ids[$id]) || !panier_accessoires_row_is_addable($data['row'])) {
                        continue;
                    }
                    if (!panier_accessoires_slot_matches($data['row'], $termes_list, $profile)) {
                        continue;
                    }
                    if (!panier_accessoires_row_is_relevant_candidate($data['row'], $needs)) {
                        continue;
                    }
                    $s = (float) $data['score'] + 45.0;
                    if ($s > $best_score) {
                        $best_score = $s;
                        $best = ['row' => $data['row'], 'slot_label' => $slot_label];
                    }
                }
            }
        }

        if ($best !== null) {
            $rid = (int) ($best['row']['id'] ?? 0);
            if ($rid > 0) {
                $picked[] = $best;
                $picked_ids[$rid] = true;
            }
        }
    }

    return $picked;
}

/**
 * @param array<int, array<string, mixed>> $panier_items
 * @return array{produits: array, chips: array, title: string, bundle_example: string}
 */
function panier_accessoires_resolve_for_cart(array $panier_items): array
{
    $cfg_early = panier_accessoires_config();
    $default_title = trim((string) ($cfg_early['section_title'] ?? 'Produits souvent achetés ensemble'));
    if ($default_title === '') {
        $default_title = 'Produits souvent achetés ensemble';
    }
    $empty = ['produits' => [], 'chips' => [], 'title' => $default_title, 'bundle_example' => ''];
    if ($panier_items === []) {
        return $empty;
    }

    $cfg = panier_accessoires_config();
    $max = (int) ($cfg['max_produits'] ?? 10);
    $max_chips = (int) ($cfg['max_chips'] ?? 3);
    $pool = (int) ($cfg['pool_limit'] ?? 50);

    $exclude_map = [];
    foreach ($panier_items as $item) {
        $id = (int) ($item['produit_id'] ?? $item['id'] ?? 0);
        if ($id > 0) {
            $exclude_map[$id] = true;
        }
    }

    $needs = panier_accessoires_collect_needs($panier_items);
    $scored = [];

    foreach ($panier_items as $item) {
        $pid = (int) ($item['produit_id'] ?? $item['id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $co = produits_similaires_co_achat_counts($pid, 15);
        foreach ($co as $cid => $cnt) {
            if (isset($exclude_map[$cid])) {
                continue;
            }
            if (!isset($scored[$cid])) {
                $row = get_produit_by_id($cid);
                if (!$row || !in_array($row['statut'] ?? '', ['actif', 'rupture_stock'], true)) {
                    continue;
                }
                if (!panier_accessoires_row_is_relevant_candidate($row, $needs)) {
                    continue;
                }
                $scored[$cid] = ['row' => $row, 'score' => 0.0, 'terme' => 'co_achat'];
            }
            $scored[$cid]['score'] += min(35.0, 10 + $cnt * 3);
        }
    }

    if (!empty($needs['termes'])) {
        foreach (panier_accessoires_search_by_termes($needs['termes'], $exclude_map, 6) as $id => $data) {
            if (!isset($scored[$id])) {
                $scored[$id] = $data;
            } else {
                $scored[$id]['score'] += $data['score'];
            }
        }
    }

    foreach ($needs['profiles'] as $profile) {
        $rayon_nom = trim((string) ($profile['rayon_cible_nom'] ?? ''));
        if ($rayon_nom === '') {
            continue;
        }
        $gid = panier_accessoires_resolve_generale_id_by_nom($rayon_nom);
        if ($gid <= 0) {
            continue;
        }
        $chunk = get_produits_by_categorie_generale($gid, null);
        if (!is_array($chunk)) {
            continue;
        }
        foreach (array_slice($chunk, 0, 40) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || isset($exclude_map[$id])) {
                continue;
            }
            if (!isset($scored[$id])) {
                $scored[$id] = ['row' => $row, 'score' => 18.0, 'terme' => 'rayon'];
            } else {
                $scored[$id]['score'] += 12;
            }
        }
    }

    foreach ($scored as $id => $data) {
        if (!panier_accessoires_row_is_addable($data['row'])
            || !panier_accessoires_row_is_relevant_candidate($data['row'], $needs)) {
            unset($scored[$id]);
        }
    }

    global $db;
    foreach ($scored as $id => &$data) {
        try {
            $st = $db->prepare("
                SELECT COUNT(*) FROM commande_produits cp
                INNER JOIN commandes c ON c.id = cp.commande_id AND c.statut NOT IN ('annulee')
                WHERE cp.produit_id = :pid AND c.date_commande >= DATE_SUB(NOW(), INTERVAL 180 DAY)
            ");
            $st->execute(['pid' => $id]);
            $achats = (int) $st->fetchColumn();
            $data['score'] += min(15.0, log($achats + 1) * 4);
        } catch (PDOException $e) {
            // ignore
        }
    }
    unset($data);

    uasort($scored, static function ($a, $b) {
        return ($b['score'] <=> $a['score']);
    });

    $max_bundle_slots = (int) ($cfg['max_bundle_slots'] ?? 3);
    $bundle_picks = panier_accessoires_pick_bundle_products($needs, $exclude_map, $scored, $max_bundle_slots);

    $produits = [];
    $used_ids = [];
    foreach ($bundle_picks as $pick) {
        $rid = (int) ($pick['row']['id'] ?? 0);
        if ($rid <= 0 || isset($used_ids[$rid])) {
            continue;
        }
        $row = $pick['row'];
        if ($pick['slot_label'] !== '') {
            $row['panier_bundle_slot'] = $pick['slot_label'];
        }
        $produits[] = $row;
        $used_ids[$rid] = true;
    }

    foreach (array_slice($scored, 0, $pool, true) as $id => $entry) {
        if (isset($used_ids[$id])) {
            continue;
        }
        $produits[] = $entry['row'];
        $used_ids[$id] = true;
        if (count($produits) >= $max) {
            break;
        }
    }

    $chips = array_slice($needs['labels'], 0, max(1, $max_chips));
    $primary = panier_accessoires_primary_profile($needs);
    $bundle_example = '';
    if ($primary !== null) {
        $bundle_example = trim((string) ($primary['bundle_example'] ?? ''));
    }
    if ($bundle_example === '' && $chips !== []) {
        $bundle_example = implode(' + ', array_map(static function ($label) {
            return mb_strtolower(trim((string) $label), 'UTF-8');
        }, array_slice($chips, 0, $max_bundle_slots)));
    }

    $section_title = trim((string) ($cfg['section_title'] ?? 'Produits souvent achetés ensemble'));
    if ($section_title === '') {
        $section_title = 'Produits souvent achetés ensemble';
    }

    /* Les idées de l'IA ne trouvent rien dans le catalogue : les règles de base reprennent la main */
    $avec_ia = array_filter($needs['profiles'], static fn ($p) => !empty($p['ia']));
    if ($produits === [] && $avec_ia !== [] && empty($GLOBALS['panier_accessoires_sans_ia'])) {
        $GLOBALS['panier_accessoires_sans_ia'] = true;
        try {
            return panier_accessoires_resolve_for_cart($panier_items);
        } finally {
            unset($GLOBALS['panier_accessoires_sans_ia']);
        }
    }

    /* Suggestion complète, toujours remplie, même sans IA ni règle pour ce produit :
       compléments directs (IA / règles) → catégories complémentaires → univers (« Pour elle »…) → boutique, populaires */
    require_once __DIR__ . '/panier_suggestions_universelles.php';
    $deja = static function (array $liste) use ($exclude_map): array {
        $ids = $exclude_map;
        foreach ($liste as $p) {
            $ids[(int) ($p['id'] ?? 0)] = true;
        }
        return $ids;
    };
    /* Un complément n'est ni un produit de la même catégorie (un autre téléphone, une autre moto),
       ni un article bien plus cher que le panier, ni hors des catégories compatibles
       (un mot-clé comme « huile » ne doit pas faire proposer de l'épicerie pour un soin du visage) */
    $cats_panier = [];
    $rayons_panier = [];
    $prix_max_panier = 0.0;
    foreach ($panier_items as $item) {
        $pp = get_produit_by_id((int) ($item['produit_id'] ?? $item['id'] ?? 0));
        if (!is_array($pp)) {
            continue;
        }
        if ((int) ($pp['categorie_id'] ?? 0) > 0) {
            $cats_panier[(int) $pp['categorie_id']] = panier_sugg_norm((string) ($pp['categorie_nom'] ?? ''));
        }
        if ((int) ($pp['categorie_generale_id'] ?? 0) > 0) {
            $rayons_panier[(int) $pp['categorie_generale_id']] = true;
        }
        $prix_max_panier = max($prix_max_panier, panier_accessoires_prix_effectif($pp));
    }
    $plafond = panier_sugg_plafond($prix_max_panier);
    $compatibles = panier_sugg_scores_categories($cats_panier, $rayons_panier);
    $produits = array_values(array_filter($produits, static function ($p) use ($cats_panier, $plafond, $compatibles) {
        $cid = (int) ($p['categorie_id'] ?? 0);
        return !isset($cats_panier[$cid])
            && !panier_sugg_article_principal((string) ($p['categorie_nom'] ?? ''))
            && panier_accessoires_prix_effectif($p) <= $plafond
            && ($compatibles === [] || isset($compatibles[$cid]));
    }));
    $produits = array_slice($produits, 0, 6);
    $produits = array_merge($produits, panier_suggestions_universelles($panier_items, $deja($produits), max(0, 7 - count($produits))));
    $produits = panier_accessoires_ajouter_univers($produits, $panier_items, $exclude_map, $max);
    if (count($produits) < $max) {
        $produits = array_merge($produits, panier_suggestions_universelles($panier_items, $deja($produits), $max - count($produits)));
    }

    return [
        'produits' => $produits,
        'chips' => $chips,
        'title' => $section_title,
        'bundle_example' => $bundle_example,
        /* true : les compléments viennent des idées de l'IA */
        'ia' => $avec_ia !== [] && empty($GLOBALS['panier_accessoires_sans_ia']),
    ];
}

/* ================= Univers de la personne (« Pour elle », « Pour lui ») ================= */

/**
 * À qui le produit est destiné : 'femme', 'homme' ou '' (mixte / inconnu).
 * L'IA le dit quand elle a préparé le produit ; sinon les indices du nom, de la catégorie et de la description.
 */
function panier_accessoires_public_produit(array $produit): string
{
    if (empty($GLOBALS['panier_accessoires_sans_ia'])) {
        $ia = panier_accessoires_ia_profil($produit, false);
        if ($ia !== null && ($ia['public'] ?? '') !== '') {
            return (string) $ia['public'];
        }
    }
    $univers = panier_accessoires_config()['univers'] ?? [];
    if (!is_array($univers)) {
        return '';
    }
    $nom = mb_strtolower(trim((string) ($produit['nom'] ?? '') . ' ' . (string) ($produit['categorie_nom'] ?? '')), 'UTF-8');
    $desc = mb_strtolower(mb_substr(strip_tags((string) ($produit['description'] ?? '')), 0, 400, 'UTF-8'), 'UTF-8');
    $scores = [];
    foreach ($univers as $public => $u) {
        $score = 0;
        foreach ((array) ($u['indices'] ?? []) as $indice) {
            $indice = mb_strtolower(trim((string) $indice), 'UTF-8');
            if ($indice === '') {
                continue;
            }
            /* Le nom et la catégorie comptent plus que la description */
            if (panier_accessoires_text_contains_keyword($nom, $indice)) {
                $score += 3;
            } elseif ($desc !== '' && panier_accessoires_text_contains_keyword($desc, $indice)) {
                $score += 1;
            }
        }
        $scores[(string) $public] = $score;
    }
    arsort($scores);
    $top = array_key_first($scores);
    $valeurs = array_values($scores);
    if ($top === null || $valeurs[0] <= 0 || (isset($valeurs[1]) && $valeurs[1] === $valeurs[0])) {
        return '';
    }
    return $top;
}

/**
 * Ajoute à la suite des compléments des produits de l'univers de la personne (beauté, bijoux, sacs pour elle…).
 *
 * @param list<array<string, mixed>> $produits compléments déjà choisis
 * @param array<int, true> $exclude_map produits du panier
 * @return list<array<string, mixed>>
 */
function panier_accessoires_ajouter_univers(array $produits, array $panier_items, array $exclude_map, int $max): array
{
    $univers = panier_accessoires_config()['univers'] ?? [];
    if (!is_array($univers) || $univers === []) {
        return $produits;
    }

    /* Public du panier : celui de la majorité des articles */
    $votes = [];
    $noms_panier = [];
    foreach ($panier_items as $item) {
        $pid = (int) ($item['produit_id'] ?? $item['id'] ?? 0);
        $produit = $pid > 0 ? get_produit_by_id($pid) : false;
        if (!is_array($produit)) {
            continue;
        }
        $noms_panier[] = (string) ($produit['nom'] ?? '');
        $public = panier_accessoires_public_produit($produit);
        if ($public !== '') {
            $votes[$public] = ($votes[$public] ?? 0) + 1;
        }
    }
    if ($votes === []) {
        return $produits;
    }
    arsort($votes);
    $public = (string) array_key_first($votes);
    $u = $univers[$public] ?? null;
    if (!is_array($u)) {
        return $produits;
    }

    /* Toujours de la place pour l'univers : les compléments directs gardent la tête de liste */
    $place_univers = min(6, max(3, $max - count($produits)));
    $produits = array_slice($produits, 0, max(0, $max - $place_univers));
    $used = $exclude_map;
    foreach ($produits as $p) {
        $used[(int) ($p['id'] ?? 0)] = true;
    }

    /* Pas un terme déjà dans le panier (des chaussures femme → pas d'autres chaussures femme) */
    $termes = [];
    foreach ((array) ($u['termes'] ?? []) as $t) {
        $t = mb_strtolower(trim((string) $t), 'UTF-8');
        if ($t === '') {
            continue;
        }
        foreach ($noms_panier as $nom) {
            if (assistant_ia_nom_contient($nom, $t)) {
                continue 2;
            }
        }
        $termes[$t] = true;
    }
    if ($termes === []) {
        return $produits;
    }
    $exclure = array_map(static fn ($x) => mb_strtolower(trim((string) $x), 'UTF-8'), (array) ($u['exclure'] ?? []));

    /* Une liste par terme, puis un produit de chaque à tour de rôle (parfum, bijou, sac… plutôt que 6 parfums) */
    $par_terme = [];
    foreach (panier_accessoires_search_by_termes($termes, $used, 3) as $id => $data) {
        $row = $data['row'];
        if (!panier_accessoires_row_is_addable($row)) {
            continue;
        }
        $nom_l = mb_strtolower((string) ($row['nom'] ?? ''), 'UTF-8');
        foreach ($exclure as $ex) {
            if ($ex !== '' && panier_accessoires_text_contains_keyword($nom_l, $ex)) {
                continue 2;
            }
        }
        if (!assistant_ia_nom_contient($nom_l, (string) $data['terme'])) {
            continue;
        }
        $par_terme[(string) $data['terme']][$id] = $row;
    }

    $label = trim((string) ($u['label'] ?? ''));
    $ajoutes = 0;
    for ($tour = 0; $ajoutes < $place_univers && $par_terme !== []; $tour++) {
        foreach ($par_terme as $t => $rows) {
            $row = array_shift($par_terme[$t]);
            if ($par_terme[$t] === []) {
                unset($par_terme[$t]);
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || isset($used[$id])) {
                continue;
            }
            if ($label !== '') {
                $row['panier_bundle_slot'] = $label;
            }
            $produits[] = $row;
            $used[$id] = true;
            if (++$ajoutes >= $place_univers) {
                break 2;
            }
        }
    }
    return $produits;
}
