<?php
/**
 * Accueil personnalisé — profil par utilisateur ou visiteur (session).
 */

require_once __DIR__ . '/client_tracking.php';
require_once __DIR__ . '/marketplace_personalization_config.php';
require_once __DIR__ . '/../models/model_client_comportement.php';

/**
 * @return array{user_id:?int, visiteur_key:string}
 */
function marketplace_personalization_actor(): array
{
    return client_tracking_actor();
}

/**
 * @param array{user_id:?int, visiteur_key:string} $actor
 */
function marketplace_personalization_is_warm(array $actor): bool
{
    $count = client_comportement_count_scoring_events_30d($actor['user_id'], $actor['visiteur_key']);
    return $count >= 3;
}

/**
 * Scores par rayon pour tri des catégories (sans seuil « warm »).
 *
 * @param array{user_id:?int, visiteur_key:string} $actor
 * @return array<int, float> categorie_generale_id => score
 */
function marketplace_personalization_interest_scores_for_categories(array $actor): array
{
    if (!client_comportement_ensure_tables()) {
        return [];
    }
    $cfg = marketplace_personalization_config();
    $boost = (float) ($cfg['personalization_recherche_rayon_boost'] ?? 2);
    $lookback = max(1, (int) ($cfg['personalization_recherche_lookback_days'] ?? 7));

    $scores = [];
    $tops = client_comportement_top_rayons($actor['user_id'], $actor['visiteur_key'], 24);
    foreach ($tops as $t) {
        $gid = (int) ($t['categorie_generale_id'] ?? 0);
        if ($gid > 0) {
            $scores[$gid] = (float) ($t['score'] ?? 0);
        }
    }

    global $db;
    $visiteur_key = trim((string) ($actor['visiteur_key'] ?? ''));
    try {
        if ($actor['user_id'] !== null && (int) $actor['user_id'] > 0) {
            $st = $db->prepare("
                SELECT terme_recherche FROM client_evenements
                WHERE type = 'recherche' AND user_id = :uid
                  AND terme_recherche IS NOT NULL AND TRIM(terme_recherche) <> ''
                  AND date_evenement >= DATE_SUB(NOW(), INTERVAL :d DAY)
            ");
            $st->execute(['uid' => (int) $actor['user_id'], 'd' => $lookback]);
        } elseif ($visiteur_key !== '') {
            $st = $db->prepare("
                SELECT terme_recherche FROM client_evenements
                WHERE type = 'recherche' AND visiteur_key = :vk
                  AND terme_recherche IS NOT NULL AND TRIM(terme_recherche) <> ''
                  AND date_evenement >= DATE_SUB(NOW(), INTERVAL :d DAY)
            ");
            $st->execute(['vk' => $visiteur_key, 'd' => $lookback]);
        } else {
            return $scores;
        }
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $terme = trim((string) ($row['terme_recherche'] ?? ''));
            if ($terme === '') {
                continue;
            }
            foreach (client_comportement_rayon_ids_from_terme($terme, 3) as $gid) {
                $gid = (int) $gid;
                if ($gid <= 0) {
                    continue;
                }
                if (!isset($scores[$gid])) {
                    $scores[$gid] = 0.0;
                }
                $scores[$gid] += $boost;
            }
        }
    } catch (PDOException $e) {
        error_log('[marketplace_personalization_interest_scores] ' . $e->getMessage());
    }

    return $scores;
}

/**
 * Réordonne les catégories / rayons selon les centres d’intérêt.
 *
 * @param array<int, array<string, mixed>> $nav_cats
 * @return array<int, array<string, mixed>>
 */
function marketplace_personalization_order_categories(array $nav_cats): array
{
    if ($nav_cats === []) {
        return [];
    }
    $actor = marketplace_personalization_actor();
    $scores = marketplace_personalization_interest_scores_for_categories($actor);
    if ($scores === []) {
        return $nav_cats;
    }

    $indexed = array_values($nav_cats);
    usort($indexed, static function ($a, $b) use ($scores) {
        $ida = (int) ($a['id'] ?? 0);
        $idb = (int) ($b['id'] ?? 0);
        $sa = $scores[$ida] ?? 0.0;
        $sb = $scores[$idb] ?? 0.0;
        if ($sa !== $sb) {
            return $sb <=> $sa;
        }
        return $ida <=> $idb;
    });

    return $indexed;
}

/**
 * @param array<int, array<string, mixed>> $nav_cats
 * @return array<int, array<string, mixed>>
 */
function marketplace_personalization_popular_categories(array $nav_cats, ?int $limit = null): array
{
    $cfg = marketplace_personalization_config();
    if ($limit === null) {
        $limit = (int) ($cfg['personalization_popular_categories_limit'] ?? 6);
    }
    $limit = max(1, min(14, $limit));

    $ordered = marketplace_personalization_order_categories($nav_cats);
    $actor = marketplace_personalization_actor();
    $scores = marketplace_personalization_interest_scores_for_categories($actor);
    if ($scores === []) {
        return array_slice($nav_cats, 0, $limit);
    }

    return array_slice($ordered, 0, $limit);
}

/**
 * @return array{
 *   warm: bool,
 *   top_rayon_ids: array<int, int>,
 *   produits_recommandes: array<int, array<string, mixed>>,
 *   produits_pour_vous: array<int, array<string, mixed>>
 * }
 */
function marketplace_personalization_home_context(): array
{
    $actor = marketplace_personalization_actor();
    $warm = marketplace_personalization_is_warm($actor);
    $empty = [
        'warm' => false,
        'top_rayon_ids' => [],
        'produits_recommandes' => [],
        'produits_pour_vous' => [],
    ];
    if (!$warm) {
        return $empty;
    }
    $tops = client_comportement_top_rayons($actor['user_id'], $actor['visiteur_key'], 6);
    $rayon_ids = [];
    foreach ($tops as $t) {
        $gid = (int) ($t['categorie_generale_id'] ?? 0);
        if ($gid > 0) {
            $rayon_ids[] = $gid;
        }
    }
    if ($rayon_ids === []) {
        return $empty;
    }
    $recommandes = client_comportement_produits_pour_rayons($rayon_ids, 20);
    $pour_vous = array_slice($recommandes, 0, 4);
    return [
        'warm' => true,
        'top_rayon_ids' => $rayon_ids,
        'produits_recommandes' => $recommandes,
        'produits_pour_vous' => $pour_vous,
    ];
}

/**
 * Mélange catalogue accueil : recommandations + diversité.
 *
 * @param array<int, array<string, mixed>> $catalogue_base
 * @param array<int, array<string, mixed>> $recommandes
 * @return array<int, array<string, mixed>>
 */
/**
 * Bloc accueil « Sélection pour vous » / recherches : jusqu’à 10 produits (carrousel, ~4 visibles).
 * Si profil actif : 1 produit le plus consulté par l’utilisateur, puis le reste proposé par le site.
 *
 * @param array<int, array<string, mixed>> $site_pool Produits proposés par le site (recherches / rayons)
 * @return array{produits: array<int, array<string, mixed>>, featured_produit_id: int}
 */
function marketplace_home_showcase_produits(bool $personalized, array $personalization, array $site_pool, int $total = 10): array
{
    $total = max(1, min(20, $total));
    $featured_id = 0;
    $seen = [];
    $out = [];

    if ($personalized) {
        $actor = marketplace_personalization_actor();
        $top_ids = client_comportement_top_produits_interagis($actor['user_id'], $actor['visiteur_key'], 1);
        if (!function_exists('get_produit_by_id')) {
            require_once __DIR__ . '/../models/model_produits.php';
        }
        foreach ($top_ids as $pid) {
            $p = get_produit_by_id((int) $pid);
            if (!is_array($p) || empty($p)) {
                continue;
            }
            if (!produit_est_visible_client((string) ($p['statut'] ?? ''))) {
                continue;
            }
            $out[] = $p;
            $seen[(int) $pid] = true;
            $featured_id = (int) $pid;
            break;
        }
    }

    $pools = [$site_pool];
    if ($personalized && !empty($personalization['produits_recommandes'])) {
        $pools[] = $personalization['produits_recommandes'];
    }
    foreach ($pools as $pool) {
        foreach ($pool as $p) {
            if (!is_array($p)) {
                continue;
            }
            $pid = (int) ($p['id'] ?? 0);
            if ($pid <= 0 || isset($seen[$pid])) {
                continue;
            }
            $seen[$pid] = true;
            $out[] = $p;
            if (count($out) >= $total) {
                break 2;
            }
        }
    }

    return [
        'produits' => array_slice($out, 0, $total),
        'featured_produit_id' => $featured_id,
    ];
}

function marketplace_personalization_blend_catalogue(array $catalogue_base, array $recommandes, int $target = 25): array
{
    $target = max(1, $target);
    $seen = [];
    $out = [];
    foreach ($recommandes as $p) {
        if (!is_array($p)) {
            continue;
        }
        $pid = (int) ($p['id'] ?? 0);
        if ($pid <= 0 || isset($seen[$pid])) {
            continue;
        }
        $seen[$pid] = true;
        $out[] = $p;
        if (count($out) >= min(15, $target)) {
            break;
        }
    }
    foreach ($catalogue_base as $p) {
        if (!is_array($p)) {
            continue;
        }
        $pid = (int) ($p['id'] ?? 0);
        if ($pid <= 0 || isset($seen[$pid])) {
            continue;
        }
        $seen[$pid] = true;
        $out[] = $p;
        if (count($out) >= $target) {
            break;
        }
    }
    return $out;
}
