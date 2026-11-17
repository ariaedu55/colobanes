<?php
/**
 * Modèle — journal comportement client et profils d'intérêt par rayon.
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/schema_client_comportement.php';

/**
 * @return array<string, int>
 */
function client_comportement_event_weights(): array
{
    return [
        'visite_site' => 0,
        'vue_produit' => 1,
        'clic_produit' => 2,
        'recherche' => 2,
        'vue_rayon' => 3,
        'vue_categorie' => 2,
        'favori_ajout' => 5,
        'favori_retrait' => -3,
        'panier_ajout' => 6,
        'achat' => 15,
        'panier_accessoires_vue' => 0,
    ];
}

function client_comportement_resolve_generale_id_from_produit($produit): int
{
    if (!is_array($produit)) {
        $pid = (int) $produit;
        if ($pid <= 0) {
            return 0;
        }
        if (!function_exists('get_produit_by_id')) {
            require_once __DIR__ . '/model_produits.php';
        }
        $produit = get_produit_by_id($pid);
    }
    if (!is_array($produit) || empty($produit)) {
        return 0;
    }
    $gid = (int) ($produit['categorie_generale_id'] ?? 0);
    if ($gid > 0) {
        return $gid;
    }
    $cid = (int) ($produit['categorie_id'] ?? 0);
    if ($cid <= 0) {
        return 0;
    }
    if (!function_exists('get_categorie_by_id')) {
        require_once __DIR__ . '/model_categories.php';
    }
    $cat = get_categorie_by_id($cid);
    if (!is_array($cat)) {
        return 0;
    }
    return (int) ($cat['categorie_generale_id'] ?? 0);
}

/**
 * @param array<string, mixed> $row
 */
function client_comportement_insert_event(array $row): bool
{
    if (!client_comportement_ensure_tables()) {
        return false;
    }
    global $db;
    $type = trim((string) ($row['type'] ?? ''));
    if ($type === '') {
        return false;
    }
    $weights = client_comportement_event_weights();
    $poids = isset($row['poids']) ? (int) $row['poids'] : (int) ($weights[$type] ?? 0);
    $user_id = isset($row['user_id']) && (int) $row['user_id'] > 0 ? (int) $row['user_id'] : null;
    $visiteur_key = isset($row['visiteur_key']) ? trim((string) $row['visiteur_key']) : '';
    if ($user_id === null && $visiteur_key === '') {
        return false;
    }
    try {
        $stmt = $db->prepare('
            INSERT INTO client_evenements (
                type, user_id, visiteur_key, produit_id, categorie_id, categorie_generale_id,
                terme_recherche, commande_id, source, poids, date_evenement
            ) VALUES (
                :type, :user_id, :visiteur_key, :produit_id, :categorie_id, :categorie_generale_id,
                :terme_recherche, :commande_id, :source, :poids, NOW()
            )
        ');
        $stmt->execute([
            'type' => $type,
            'user_id' => $user_id,
            'visiteur_key' => $visiteur_key !== '' ? $visiteur_key : null,
            'produit_id' => isset($row['produit_id']) && (int) $row['produit_id'] > 0 ? (int) $row['produit_id'] : null,
            'categorie_id' => isset($row['categorie_id']) && (int) $row['categorie_id'] > 0 ? (int) $row['categorie_id'] : null,
            'categorie_generale_id' => isset($row['categorie_generale_id']) && (int) $row['categorie_generale_id'] > 0
                ? (int) $row['categorie_generale_id'] : null,
            'terme_recherche' => isset($row['terme_recherche']) ? trim((string) $row['terme_recherche']) : null,
            'commande_id' => isset($row['commande_id']) && (int) $row['commande_id'] > 0 ? (int) $row['commande_id'] : null,
            'source' => isset($row['source']) ? trim((string) $row['source']) : null,
            'poids' => $poids,
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('[client_comportement_insert_event] ' . $e->getMessage());
        return false;
    }
}

function client_comportement_apply_rayon_score(?int $user_id, ?string $visiteur_key, int $categorie_generale_id, float $delta): void
{
    if ($categorie_generale_id <= 0 || $delta == 0.0) {
        return;
    }
    if (!client_comportement_ensure_tables()) {
        return;
    }
    global $db;
    $visiteur_key = $visiteur_key !== null ? trim($visiteur_key) : '';
    if (($user_id === null || $user_id <= 0) && $visiteur_key === '') {
        return;
    }
    try {
        if ($user_id !== null && $user_id > 0) {
            $stmt = $db->prepare('
                INSERT INTO client_interets_rayon (user_id, visiteur_key, categorie_generale_id, score, derniere_maj)
                VALUES (:user_id, NULL, :gid, :delta, NOW())
                ON DUPLICATE KEY UPDATE score = score + VALUES(score), derniere_maj = NOW()
            ');
            $stmt->execute(['user_id' => $user_id, 'gid' => $categorie_generale_id, 'delta' => $delta]);
        } else {
            $stmt = $db->prepare('
                INSERT INTO client_interets_rayon (user_id, visiteur_key, categorie_generale_id, score, derniere_maj)
                VALUES (NULL, :vk, :gid, :delta, NOW())
                ON DUPLICATE KEY UPDATE score = score + VALUES(score), derniere_maj = NOW()
            ');
            $stmt->execute(['vk' => $visiteur_key, 'gid' => $categorie_generale_id, 'delta' => $delta]);
        }
    } catch (PDOException $e) {
        error_log('[client_comportement_apply_rayon_score] ' . $e->getMessage());
    }
}

/**
 * Rayons plateforme dont le nom correspond au terme de recherche.
 *
 * @return list<int>
 */
function client_comportement_rayon_ids_from_terme(string $terme, int $limit = 3): array
{
    $terme = trim($terme);
    if ($terme === '' || mb_strlen($terme, 'UTF-8') < 2) {
        return [];
    }
    if (!function_exists('categories_generales_table_exists')) {
        require_once __DIR__ . '/model_categories.php';
    }
    if (!categories_generales_table_exists()) {
        return [];
    }
    global $db;
    $limit = max(1, min(6, $limit));
    $pattern = '%' . mb_strtolower($terme, 'UTF-8') . '%';
    $exact = mb_strtolower($terme, 'UTF-8');
    try {
        $st = $db->prepare('
            SELECT id FROM categories_generales
            WHERE LOWER(nom) LIKE :p
            ORDER BY CASE WHEN LOWER(nom) = :ex THEN 0 WHEN LOWER(nom) LIKE :st THEN 1 ELSE 2 END,
                     CHAR_LENGTH(nom) ASC, nom ASC
            LIMIT ' . (int) $limit);
        $st->execute(['p' => $pattern, 'ex' => $exact, 'st' => $exact . '%']);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }
        return $out;
    } catch (PDOException $e) {
        error_log('[client_comportement_rayon_ids_from_terme] ' . $e->getMessage());
        return [];
    }
}

function client_comportement_apply_recherche_rayon_scores(?int $user_id, ?string $visiteur_key, string $terme): void
{
    $terme = trim($terme);
    if ($terme === '') {
        return;
    }
    $weights = client_comportement_event_weights();
    $delta = (float) ($weights['recherche'] ?? 2);
    if ($delta == 0.0) {
        return;
    }
    foreach (client_comportement_rayon_ids_from_terme($terme, 3) as $gid) {
        client_comportement_apply_rayon_score($user_id, $visiteur_key, (int) $gid, $delta);
    }
}

function client_comportement_recent_duplicate(
    string $type,
    ?int $user_id,
    ?string $visiteur_key,
    int $produit_id,
    int $minutes = 15
): bool {
    if (!client_comportement_ensure_tables()) {
        return false;
    }
    global $db;
    $visiteur_key = $visiteur_key !== null ? trim($visiteur_key) : '';
    $conds = ['type = :type', 'date_evenement >= DATE_SUB(NOW(), INTERVAL :mins MINUTE)'];
    $params = ['type' => $type, 'mins' => max(1, $minutes)];
    if ($produit_id > 0) {
        $conds[] = 'produit_id = :pid';
        $params['pid'] = $produit_id;
    }
    if ($user_id !== null && $user_id > 0) {
        $conds[] = 'user_id = :uid';
        $params['uid'] = $user_id;
    } elseif ($visiteur_key !== '') {
        $conds[] = 'visiteur_key = :vk';
        $params['vk'] = $visiteur_key;
    } else {
        return false;
    }
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM client_evenements WHERE ' . implode(' AND ', $conds) . ' LIMIT 1');
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function client_comportement_visite_site_logged_today(?int $user_id, ?string $visiteur_key): bool
{
    if (!client_comportement_ensure_tables()) {
        return true;
    }
    global $db;
    $visiteur_key = $visiteur_key !== null ? trim($visiteur_key) : '';
    try {
        if ($user_id !== null && $user_id > 0) {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM client_evenements
                WHERE type = 'visite_site' AND user_id = :uid
                AND date_evenement >= CURDATE()
            ");
            $stmt->execute(['uid' => $user_id]);
        } elseif ($visiteur_key !== '') {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM client_evenements
                WHERE type = 'visite_site' AND visiteur_key = :vk
                AND date_evenement >= CURDATE()
            ");
            $stmt->execute(['vk' => $visiteur_key]);
        } else {
            return true;
        }
        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return true;
    }
}

/**
 * @return array<int, array{categorie_generale_id:int, score:float, nom?:string}>
 */
function client_comportement_top_rayons(?int $user_id, ?string $visiteur_key, int $limit = 6): array
{
    if (!client_comportement_ensure_tables()) {
        return [];
    }
    global $db;
    $limit = max(1, min(24, $limit));
    $visiteur_key = $visiteur_key !== null ? trim($visiteur_key) : '';
    try {
        if ($user_id !== null && $user_id > 0) {
            $stmt = $db->prepare("
                SELECT categorie_generale_id, score
                FROM client_interets_rayon
                WHERE user_id = :uid AND score > 0
                ORDER BY score DESC, derniere_maj DESC
                LIMIT {$limit}
            ");
            $stmt->execute(['uid' => $user_id]);
        } elseif ($visiteur_key !== '') {
            $stmt = $db->prepare("
                SELECT categorie_generale_id, score
                FROM client_interets_rayon
                WHERE visiteur_key = :vk AND score > 0
                ORDER BY score DESC, derniere_maj DESC
                LIMIT {$limit}
            ");
            $stmt->execute(['vk' => $visiteur_key]);
        } else {
            return [];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $gid = (int) ($r['categorie_generale_id'] ?? 0);
            if ($gid <= 0) {
                continue;
            }
            $out[] = [
                'categorie_generale_id' => $gid,
                'score' => (float) ($r['score'] ?? 0),
            ];
        }
        return $out;
    } catch (PDOException $e) {
        return [];
    }
}

function client_comportement_count_scoring_events_30d(?int $user_id, ?string $visiteur_key): int
{
    if (!client_comportement_ensure_tables()) {
        return 0;
    }
    global $db;
    $visiteur_key = $visiteur_key !== null ? trim($visiteur_key) : '';
    try {
        if ($user_id !== null && $user_id > 0) {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM client_evenements
                WHERE user_id = :uid
                AND date_evenement >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND type NOT IN ('visite_site')
                AND poids <> 0
            ");
            $stmt->execute(['uid' => $user_id]);
        } elseif ($visiteur_key !== '') {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM client_evenements
                WHERE visiteur_key = :vk
                AND date_evenement >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND type NOT IN ('visite_site')
                AND poids <> 0
            ");
            $stmt->execute(['vk' => $visiteur_key]);
        } else {
            return 0;
        }
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * @param array<int, int> $rayon_ids
 * @return array<int, array<string, mixed>>
 */
/**
 * Produits les plus consultés / cliqués (30 derniers jours).
 *
 * @return list<int>
 */
function client_comportement_top_produits_interagis(?int $user_id, ?string $visiteur_key, int $limit = 1): array
{
    if (!client_comportement_ensure_tables()) {
        return [];
    }
    global $db;
    $limit = max(1, min(10, $limit));
    $user_id = $user_id !== null && (int) $user_id > 0 ? (int) $user_id : null;
    $visiteur_key = $visiteur_key !== null ? trim($visiteur_key) : '';
    if ($user_id === null && $visiteur_key === '') {
        return [];
    }
    $types = ['vue_produit', 'clic_produit', 'favori_ajout', 'panier_ajout', 'achat'];
    $in = implode(',', array_fill(0, count($types), '?'));
    try {
        if ($user_id !== null) {
            $sql = "
                SELECT produit_id, SUM(poids) AS score
                FROM client_evenements
                WHERE produit_id IS NOT NULL AND produit_id > 0
                  AND user_id = ?
                  AND type IN ($in)
                  AND date_evenement >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY produit_id
                ORDER BY score DESC, MAX(date_evenement) DESC
                LIMIT " . (int) $limit;
            $st = $db->prepare($sql);
            $st->execute(array_merge([$user_id], $types));
        } else {
            $sql = "
                SELECT produit_id, SUM(poids) AS score
                FROM client_evenements
                WHERE produit_id IS NOT NULL AND produit_id > 0
                  AND visiteur_key = ?
                  AND type IN ($in)
                  AND date_evenement >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY produit_id
                ORDER BY score DESC, MAX(date_evenement) DESC
                LIMIT " . (int) $limit;
            $st = $db->prepare($sql);
            $st->execute(array_merge([$visiteur_key], $types));
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $pid = (int) ($row['produit_id'] ?? 0);
            if ($pid > 0) {
                $out[] = $pid;
            }
        }
        return $out;
    } catch (PDOException $e) {
        error_log('[client_comportement_top_produits_interagis] ' . $e->getMessage());
        return [];
    }
}

function client_comportement_produits_pour_rayons(array $rayon_ids, int $limit = 20): array
{
    $rayon_ids = array_values(array_unique(array_filter(array_map('intval', $rayon_ids), static function ($id) {
        return $id > 0;
    })));
    if ($rayon_ids === [] || $limit <= 0) {
        return [];
    }
    if (!function_exists('get_produits_by_categorie_generale')) {
        require_once __DIR__ . '/model_produits.php';
    }
    require_once __DIR__ . '/../includes/catalogue_shuffle.php';
    $pool = [];
    $seen = [];
    foreach ($rayon_ids as $gid) {
        $chunk = get_produits_by_categorie_generale((int) $gid, null);
        foreach ($chunk as $p) {
            if (!is_array($p)) {
                continue;
            }
            $pid = (int) ($p['id'] ?? 0);
            if ($pid <= 0 || isset($seen[$pid])) {
                continue;
            }
            $seen[$pid] = true;
            $pool[] = $p;
            if (count($pool) >= $limit * 3) {
                break 2;
            }
        }
    }
    if ($pool === []) {
        return [];
    }
    $pool = catalogue_melanger_produits($pool);
    return array_slice($pool, 0, $limit);
}

function client_comportement_merge_visiteur_vers_user(int $user_id, string $visiteur_key): bool
{
    $user_id = (int) $user_id;
    $visiteur_key = trim($visiteur_key);
    if ($user_id <= 0 || $visiteur_key === '') {
        return false;
    }
    if (!client_comportement_ensure_tables()) {
        return false;
    }
    global $db;
    try {
        $db->beginTransaction();

        $stmt = $db->prepare('
            UPDATE client_evenements
            SET user_id = :uid, visiteur_key = :vk
            WHERE visiteur_key = :vk AND (user_id IS NULL OR user_id = 0)
        ');
        $stmt->execute(['uid' => $user_id, 'vk' => $visiteur_key]);

        $sel = $db->prepare('
            SELECT categorie_generale_id, score
            FROM client_interets_rayon
            WHERE visiteur_key = :vk
        ');
        $sel->execute(['vk' => $visiteur_key]);
        $visitor_rows = $sel->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($visitor_rows as $vr) {
            $gid = (int) ($vr['categorie_generale_id'] ?? 0);
            $score = (float) ($vr['score'] ?? 0);
            if ($gid <= 0 || $score == 0.0) {
                continue;
            }
            $ins = $db->prepare('
                INSERT INTO client_interets_rayon (user_id, visiteur_key, categorie_generale_id, score, derniere_maj)
                VALUES (:uid, NULL, :gid, :score, NOW())
                ON DUPLICATE KEY UPDATE score = score + VALUES(score), derniere_maj = NOW()
            ');
            $ins->execute(['uid' => $user_id, 'gid' => $gid, 'score' => $score]);
        }

        $del = $db->prepare('DELETE FROM client_interets_rayon WHERE visiteur_key = :vk');
        $del->execute(['vk' => $visiteur_key]);

        $db->commit();
        return true;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[client_comportement_merge_visiteur_vers_user] ' . $e->getMessage());
        return false;
    }
}

/**
 * Stats admin agrégées (30 derniers jours).
 *
 * @return array<string, mixed>
 */
function client_comportement_admin_stats_30d(): array
{
    if (!client_comportement_ensure_tables()) {
        return [
            'total_events' => 0,
            'users_actifs' => 0,
            'visiteurs_anonymes' => 0,
            'top_rayons' => [],
            'top_recherches' => [],
        ];
    }
    global $db;
    $out = [
        'total_events' => 0,
        'users_actifs' => 0,
        'visiteurs_anonymes' => 0,
        'top_rayons' => [],
        'top_recherches' => [],
    ];
    try {
        $out['total_events'] = (int) $db->query("
            SELECT COUNT(*) FROM client_evenements
            WHERE date_evenement >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ")->fetchColumn();

        $out['users_actifs'] = (int) $db->query("
            SELECT COUNT(DISTINCT user_id) FROM client_evenements
            WHERE user_id IS NOT NULL AND user_id > 0
            AND date_evenement >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ")->fetchColumn();

        $out['visiteurs_anonymes'] = (int) $db->query("
            SELECT COUNT(DISTINCT visiteur_key) FROM client_evenements
            WHERE type = 'visite_site'
            AND (user_id IS NULL OR user_id = 0)
            AND visiteur_key IS NOT NULL AND visiteur_key <> ''
            AND date_evenement >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ")->fetchColumn();

        if (client_comportement_table_exists('categories_generales')) {
            $stmtRayons = $db->query("
                SELECT cir.categorie_generale_id, SUM(cir.score) AS score_total, cg.nom
                FROM client_interets_rayon cir
                LEFT JOIN categories_generales cg ON cg.id = cir.categorie_generale_id
                GROUP BY cir.categorie_generale_id, cg.nom
                HAVING score_total > 0
                ORDER BY score_total DESC
                LIMIT 15
            ");
        } else {
            $stmtRayons = $db->query("
                SELECT categorie_generale_id, SUM(score) AS score_total, NULL AS nom
                FROM client_interets_rayon
                GROUP BY categorie_generale_id
                HAVING score_total > 0
                ORDER BY score_total DESC
                LIMIT 15
            ");
        }
        $out['top_rayons'] = $stmtRayons ? ($stmtRayons->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        $stmtSearch = $db->query("
            SELECT LOWER(TRIM(terme_recherche)) AS t, COUNT(*) AS c
            FROM client_evenements
            WHERE type = 'recherche'
            AND terme_recherche IS NOT NULL AND TRIM(terme_recherche) <> ''
            AND date_evenement >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY LOWER(TRIM(terme_recherche))
            ORDER BY c DESC, t ASC
            LIMIT 15
        ");
        $out['top_recherches'] = $stmtSearch ? ($stmtSearch->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (PDOException $e) {
        error_log('[client_comportement_admin_stats_30d] ' . $e->getMessage());
    }
    return $out;
}
