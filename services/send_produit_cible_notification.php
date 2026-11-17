<?php
/**
 * Notifications ciblées (nouveau produit) — push + e-mail.
 */

require_once __DIR__ . '/../models/model_notification_log.php';
require_once __DIR__ . '/notification_dispatch.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_client_comportement.php';
require_once __DIR__ . '/../includes/marketplace_helpers.php';
require_once __DIR__ . '/../includes/site_url.php';
require_once __DIR__ . '/notification_engagement_segments.php';
require_once __DIR__ . '/notification_segment_queue.php';

/**
 * Mots significatifs du nom produit pour recouper les recherches clients.
 *
 * @return list<string>
 */
function notification_produit_recherche_tokens(string $nom): array
{
    $nom = mb_strtolower(trim($nom), 'UTF-8');
    if ($nom === '') {
        return [];
    }
    $parts = preg_split('/[\s\-\/\.,;:]+/u', $nom, -1, PREG_SPLIT_NO_EMPTY);
    $stop = ['les', 'des', 'une', 'pour', 'avec', 'de', 'du', 'la', 'le', 'et', 'the'];
    $tokens = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if (mb_strlen($p, 'UTF-8') < 3) {
            continue;
        }
        if (in_array($p, $stop, true)) {
            continue;
        }
        $tokens[] = $p;
    }
    return array_values(array_unique(array_slice($tokens, 0, 6)));
}

/**
 * @return list<int>
 */
function notification_produit_cible_user_ids_from_recherche(string $nom_produit, int $jours): array
{
    if (!client_comportement_ensure_tables()) {
        return [];
    }
    $tokens = notification_produit_recherche_tokens($nom_produit);
    if (empty($tokens)) {
        return [];
    }
    global $db;
    $conds = [];
    $params = ['j' => $jours];
    $i = 0;
    foreach ($tokens as $t) {
        $key = 't' . $i;
        $conds[] = 'LOWER(terme_recherche) LIKE :' . $key;
        $params[$key] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $t) . '%';
        $i++;
    }
    try {
        $sql = '
            SELECT DISTINCT user_id FROM client_evenements
            WHERE type = \'recherche\' AND user_id IS NOT NULL
              AND date_evenement >= DATE_SUB(NOW(), INTERVAL :j DAY)
              AND (' . implode(' OR ', $conds) . ')
        ';
        $st = $db->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $uid = (int) ($r['user_id'] ?? 0);
            if ($uid > 0) {
                $out[] = $uid;
            }
        }
        return $out;
    } catch (PDOException $e) {
        error_log('[notification_produit_cible recherche] ' . $e->getMessage());
        return [];
    }
}

/**
 * @return int Nombre d'utilisateurs mis en file (envoi via notification_segment_queue)
 */
function notification_produit_cible_audience(int $produit_id): int
{
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0) {
        return 0;
    }
    $produit = get_produit_by_id($produit_id);
    if (!$produit || ($produit['statut'] ?? '') !== 'actif') {
        return 0;
    }

    $cfg = notifications_config();
    $max_day = (int) ($cfg['produit_cible_max_par_jour_user'] ?? 3);
    $recherche_jours = (int) ($cfg['produit_cible_recherche_jours'] ?? 90);
    $admin_id = (int) ($produit['admin_id'] ?? 0);
    $gid = client_comportement_resolve_generale_id_from_produit($produit);
    $nom_produit = trim((string) ($produit['nom'] ?? ''));

    global $db;
    $user_ids = [];

    if ($admin_id > 0 && function_exists('boutique_abonnements_subscriber_user_ids')) {
        require_once __DIR__ . '/../models/model_boutique_abonnements.php';
        if (boutique_abonnements_table_exists()) {
            foreach (boutique_abonnements_subscriber_user_ids($admin_id) as $uid) {
                $user_ids[(int) $uid] = true;
            }
        }
    }

    if ($gid > 0 && client_comportement_ensure_tables()) {
        try {
            $st = $db->prepare('
                SELECT user_id FROM client_interets_rayon
                WHERE categorie_generale_id = :gid AND user_id IS NOT NULL AND score > 0
                ORDER BY score DESC LIMIT 500
            ');
            $st->execute(['gid' => $gid]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $uid = (int) ($r['user_id'] ?? 0);
                if ($uid > 0) {
                    $user_ids[$uid] = true;
                }
            }
        } catch (PDOException $e) {
            error_log('[notification_produit_cible rayons] ' . $e->getMessage());
        }
    }

    foreach (notification_produit_cible_user_ids_from_recherche($nom_produit, $recherche_jours) as $uid) {
        $user_ids[$uid] = true;
    }

    if ($gid > 0) {
        try {
            $st = $db->prepare("
                SELECT DISTINCT c.user_id
                FROM commandes c
                INNER JOIN commande_produits cp ON cp.commande_id = c.id
                INNER JOIN produits p ON p.id = cp.produit_id
                WHERE c.user_id IS NOT NULL AND c.statut NOT IN ('annulee')
                  AND (p.categorie_generale_id = :gid OR p.categorie_id IN (
                      SELECT id FROM categories WHERE categorie_generale_id = :gid2
                  ))
                LIMIT 800
            ");
            $st->execute(['gid' => $gid, 'gid2' => $gid]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $uid = (int) ($r['user_id'] ?? 0);
                if ($uid > 0) {
                    $user_ids[$uid] = true;
                }
            }
        } catch (PDOException $e) {
            error_log('[notification_produit_cible achats] ' . $e->getMessage());
        }

        try {
            $st = $db->prepare("
                SELECT DISTINCT f.user_id
                FROM favoris f
                INNER JOIN produits p ON p.id = f.produit_id
                WHERE p.categorie_generale_id = :gid OR p.categorie_id IN (
                    SELECT id FROM categories WHERE categorie_generale_id = :gid2
                )
            ");
            $st->execute(['gid' => $gid, 'gid2' => $gid]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $uid = (int) ($r['user_id'] ?? 0);
                if ($uid > 0) {
                    $user_ids[$uid] = true;
                }
            }
        } catch (PDOException $e) {
            error_log('[notification_produit_cible favoris] ' . $e->getMessage());
        }
    }

    $link_path = '/produit.php?id=' . $produit_id;
    $base = rtrim(get_site_base_url(), '/');
    $link_abs = $base . $link_path;

    $use_segment = (bool) ($cfg['engagement_nouveaute_use_segment_copy'] ?? true);
    if ($use_segment) {
        $copy = notification_engagement_copy_nouveau_produit($gid, $nom_produit);
        $title = $copy['title'];
        $body = $copy['body'];
        $email_html = $copy['email_html'];
        $email_subject = $copy['email_subject'];
    } else {
        require_once __DIR__ . '/../models/model_admin.php';
        $vendeur = $admin_id > 0 ? get_admin_by_id($admin_id) : null;
        $boutique_nom = trim((string) ($vendeur['boutique_nom'] ?? $vendeur['nom'] ?? 'COLObanes'));
        $title = $boutique_nom . ' — Nouveau pour vous';
        $body = $nom_produit !== '' ? $nom_produit . ' pourrait vous intéresser.' : 'Découvrez un nouveau produit.';
        $email_html = '<p>Un produit correspond à vos centres d\'intérêt : <strong>' . htmlspecialchars($nom_produit) . '</strong>.</p>';
        $email_subject = '[COLObanes] ' . $title;
    }

    if (!notification_segment_queue_ensure_table()) {
        return 0;
    }

    $segment_gid = $gid > 0 ? $gid : 0;
    $queued = 0;
    foreach (array_keys($user_ids) as $user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            continue;
        }
        if ($max_day > 0 && notification_count_today_for_user($user_id, 'produit_cible') >= $max_day) {
            continue;
        }
        $dedupe_boutique = 'boutique_abo:' . $admin_id . ':nouveau_produit:' . $produit_id . ':' . $user_id;
        if (notification_log_exists($dedupe_boutique)) {
            continue;
        }
        $dedupe = 'produit_cible:' . $produit_id . ':' . $user_id;
        if (notification_log_exists($dedupe)) {
            continue;
        }

        $scheduled = notification_segment_queue_schedule_at($segment_gid, 'produit_cible', $produit_id);
        $ok = notification_segment_queue_enqueue(
            $user_id,
            $segment_gid,
            'produit_cible',
            $dedupe,
            [
                'title' => $title,
                'body' => $body,
                'email_subject' => $email_subject,
                'email_html' => $email_html,
                'link_abs' => $link_abs,
                'log_type' => 'produit_cible',
                'produit_id' => $produit_id,
                'admin_id' => $admin_id > 0 ? $admin_id : null,
                'data' => [
                    'type' => 'produit_cible',
                    'link' => $link_path,
                    'redirect_url' => $link_abs,
                    'produit_id' => (string) $produit_id,
                    'tag' => 'produit-cible-' . $produit_id,
                ],
                'email_meta' => ['type' => 'produit_cible', 'produit_id' => $produit_id],
            ],
            $produit_id,
            $scheduled
        );
        if ($ok) {
            $queued++;
        }
    }

    return $queued;
}
