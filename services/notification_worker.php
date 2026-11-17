<?php
/**
 * Tâches planifiées notifications (push + e-mail).
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_notification_log.php';
require_once __DIR__ . '/notification_dispatch.php';
require_once __DIR__ . '/notification_panier_relance.php';
require_once __DIR__ . '/notification_favoris_relance.php';
require_once __DIR__ . '/notification_engagement_inactif.php';
require_once __DIR__ . '/notification_segment_queue.php';
require_once __DIR__ . '/notification_vendeur_onboarding.php';
require_once __DIR__ . '/../includes/site_url.php';

function notification_produit_prix_effectif(array $produit): float
{
    if (isset($produit['prix_promotion']) && $produit['prix_promotion'] !== '' && $produit['prix_promotion'] !== null) {
        $pp = (float) $produit['prix_promotion'];
        if ($pp > 0) {
            return $pp;
        }
    }
    return (float) ($produit['prix'] ?? 0);
}

/**
 * @return array<string, int>
 */
function notification_worker_run_all(): array
{
    $stats = [
        'cmd_attente' => 0,
        'cmd_retard' => 0,
        'panier' => 0,
        'favoris' => 0,
        'favoris_relance' => 0,
        'engagement_inactif' => 0,
        'segment_queue' => ['sent' => 0, 'skipped' => 0, 'by_campaign' => []],
        'vendeur_onboarding' => ['relance' => 0, 'j7' => 0, 'j9' => 0, 'supprime' => 0],
    ];
    $stats['cmd_attente'] = notification_worker_commandes_en_attente();
    $stats['cmd_retard'] = notification_worker_commandes_retard_livraison();
    $stats['panier'] = notification_worker_panier_abandonne();
    $stats['favoris'] = notification_worker_favoris_ventes_rapides();
    $stats['favoris_relance'] = notification_worker_favoris_relance();
    $stats['engagement_inactif'] = notification_worker_engagement_inactif();
    $stats['segment_queue'] = notification_segment_queue_dispatch_batch();
    $stats['vendeur_onboarding'] = notification_worker_vendeur_onboarding();
    return $stats;
}

function notification_worker_commandes_en_attente(): int
{
    require_once __DIR__ . '/../models/model_commandes.php';
    if (!function_exists('_commandes_has_vendeur_id_column') || !_commandes_has_vendeur_id_column()) {
        return 0;
    }
    $cfg = notifications_config();
    $h24 = (int) ($cfg['commande_attente_vendeur_heures'] ?? 24);
    $h48 = (int) ($cfg['commande_attente_escalade_heures'] ?? 48);
    global $db;
    $sent = 0;
    $base = rtrim(get_site_base_url(), '/');

    try {
        $st = $db->prepare("
            SELECT id, vendeur_id, numero_commande, montant_total, date_commande
            FROM commandes
            WHERE statut = 'en_attente'
              AND vendeur_id IS NOT NULL AND vendeur_id > 0
              AND date_commande < DATE_SUB(NOW(), INTERVAL :h HOUR)
        ");
        $st->execute(['h' => $h24]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[notification_worker_commandes_en_attente] ' . $e->getMessage());
        return 0;
    }

    foreach ($rows as $cmd) {
        $cmd_id = (int) ($cmd['id'] ?? 0);
        $vendeur_id = (int) ($cmd['vendeur_id'] ?? 0);
        $numero = (string) ($cmd['numero_commande'] ?? '');
        if ($cmd_id <= 0 || $vendeur_id <= 0) {
            continue;
        }
        $dedupe = 'cmd_attente:' . $cmd_id . ':24h';
        if (notification_log_exists($dedupe)) {
            continue;
        }
        $link = $base . '/admin/commandes/details.php?id=' . $cmd_id;
        $montant = number_format((float) ($cmd['montant_total'] ?? 0), 0, ',', ' ');
        $title = 'Commande en attente';
        $body = "La commande #{$numero} ({$montant} FCFA) attend votre validation.";
        $email_html = '<p>La commande <strong>#' . htmlspecialchars($numero) . '</strong> est toujours en attente de traitement.</p>'
            . '<p><strong>Montant :</strong> ' . $montant . ' FCFA</p>';
        $disp = notification_dispatch_to_admin(
            $vendeur_id,
            $title,
            $body,
            [
                'type' => 'cmd_attente_vendeur',
                'link' => $link,
                'commande_id' => (string) $cmd_id,
                'tag' => 'cmd-attente-' . $cmd_id,
            ],
            '[COLObanes] Rappel — commande #' . $numero . ' en attente',
            $email_html,
            $link,
            ['type' => 'cmd_attente_vendeur', 'commande_id' => $cmd_id]
        );
        if ($disp['push'] || $disp['email']) {
            notification_log_insert([
                'type' => 'cmd_attente_vendeur',
                'dedupe_key' => $dedupe,
                'admin_id' => $vendeur_id,
                'commande_id' => $cmd_id,
                'push_sent' => $disp['push'],
                'email_sent' => $disp['email'],
            ]);
            $sent++;
        }
    }

    try {
        $st2 = $db->prepare("
            SELECT id, vendeur_id, numero_commande, montant_total
            FROM commandes
            WHERE statut = 'en_attente'
              AND date_commande < DATE_SUB(NOW(), INTERVAL :h HOUR)
        ");
        $st2->execute(['h' => $h48]);
        $rows48 = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return $sent;
    }

    foreach ($rows48 as $cmd) {
        $cmd_id = (int) ($cmd['id'] ?? 0);
        $numero = (string) ($cmd['numero_commande'] ?? '');
        if ($cmd_id <= 0) {
            continue;
        }
        $dedupe = 'cmd_attente:' . $cmd_id . ':48h';
        if (notification_log_exists($dedupe)) {
            continue;
        }
        $link = $base . '/admin/commandes/details.php?id=' . $cmd_id;
        $title = 'Escalade — commande non validée';
        $body = "Commande #{$numero} toujours en attente après {$h48} h.";
        $email_html = '<p>Escalade plateforme : la commande <strong>#' . htmlspecialchars($numero) . '</strong> n\'a pas été validée par le vendeur.</p>';
        $disp = notification_dispatch_to_platform_admins(
            $title,
            $body,
            ['type' => 'cmd_attente_escalade', 'link' => $link, 'tag' => 'cmd-attente-esc-' . $cmd_id],
            '[COLObanes] Escalade commande #' . $numero,
            $email_html,
            $link,
            ['type' => 'cmd_attente_escalade', 'commande_id' => $cmd_id]
        );
        if ($disp['push'] || $disp['email']) {
            notification_log_insert([
                'type' => 'cmd_attente_escalade',
                'dedupe_key' => $dedupe,
                'commande_id' => $cmd_id,
                'push_sent' => $disp['push'],
                'email_sent' => $disp['email'],
            ]);
            $sent++;
        }
    }

    return $sent;
}

function notification_worker_commandes_retard_livraison(): int
{
    require_once __DIR__ . '/../models/model_commandes.php';
    if (!function_exists('_commandes_has_vendeur_id_column') || !_commandes_has_vendeur_id_column()) {
        return 0;
    }
    $cfg = notifications_config();
    $sla = (int) ($cfg['livraison_sla_jours'] ?? 7);
    $mult = (int) ($cfg['livraison_sla_escalade_multiplicateur'] ?? 2);
    $statuts = $cfg['livraison_statuts_actifs'] ?? [];
    if (empty($statuts)) {
        return 0;
    }
    global $db;
    $sent = 0;
    $base = rtrim(get_site_base_url(), '/');
    $placeholders = implode(',', array_fill(0, count($statuts), '?'));

    try {
        $sql = "
            SELECT id, vendeur_id, numero_commande, date_commande
            FROM commandes
            WHERE statut IN ($placeholders)
              AND date_commande < DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        $params = array_merge($statuts, [$sla]);
        $st = $db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[notification_worker_commandes_retard_livraison] ' . $e->getMessage());
        return 0;
    }

    foreach ($rows as $cmd) {
        $cmd_id = (int) ($cmd['id'] ?? 0);
        $vendeur_id = (int) ($cmd['vendeur_id'] ?? 0);
        $numero = (string) ($cmd['numero_commande'] ?? '');
        if ($cmd_id <= 0 || $vendeur_id <= 0) {
            continue;
        }
        $dedupe = 'cmd_retard:' . $cmd_id . ':sla' . $sla;
        if (notification_log_exists($dedupe)) {
            continue;
        }
        $link = $base . '/admin/commandes/details.php?id=' . $cmd_id;
        $title = 'Livraison en retard';
        $body = "Commande #{$numero} : plus de {$sla} jours depuis la commande.";
        $email_html = '<p>La commande <strong>#' . htmlspecialchars($numero) . '</strong> dépasse le délai de livraison attendu ('
            . (int) $sla . ' jours).</p>';
        $disp = notification_dispatch_to_admin(
            $vendeur_id,
            $title,
            $body,
            ['type' => 'cmd_retard_livraison', 'link' => $link, 'tag' => 'cmd-retard-' . $cmd_id],
            '[COLObanes] Retard livraison #' . $numero,
            $email_html,
            $link,
            ['type' => 'cmd_retard_livraison', 'commande_id' => $cmd_id]
        );
        if ($disp['push'] || $disp['email']) {
            notification_log_insert([
                'type' => 'cmd_retard_livraison',
                'dedupe_key' => $dedupe,
                'admin_id' => $vendeur_id,
                'commande_id' => $cmd_id,
                'push_sent' => $disp['push'],
                'email_sent' => $disp['email'],
            ]);
            $sent++;
        }
    }

    $sla2 = $sla * max(2, $mult);
    try {
        $sql2 = "
            SELECT id, numero_commande
            FROM commandes
            WHERE statut IN ($placeholders)
              AND date_commande < DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        $params2 = array_merge($statuts, [$sla2]);
        $st2 = $db->prepare($sql2);
        $st2->execute($params2);
        $rows2 = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return $sent;
    }

    foreach ($rows2 as $cmd) {
        $cmd_id = (int) ($cmd['id'] ?? 0);
        $numero = (string) ($cmd['numero_commande'] ?? '');
        if ($cmd_id <= 0) {
            continue;
        }
        $dedupe = 'cmd_retard:' . $cmd_id . ':sla_esc' . $sla2;
        if (notification_log_exists($dedupe)) {
            continue;
        }
        $link = $base . '/admin/commandes/details.php?id=' . $cmd_id;
        $title = 'Escalade livraison';
        $body = "Commande #{$numero} : retard critique ({$sla2} j).";
        $disp = notification_dispatch_to_platform_admins(
            $title,
            $body,
            ['type' => 'cmd_retard_escalade', 'link' => $link],
            '[COLObanes] Escalade retard #' . $numero,
            '<p>Retard critique sur la commande <strong>#' . htmlspecialchars($numero) . '</strong>.</p>',
            $link,
            ['type' => 'cmd_retard_escalade', 'commande_id' => $cmd_id]
        );
        if ($disp['push'] || $disp['email']) {
            notification_log_insert([
                'type' => 'cmd_retard_escalade',
                'dedupe_key' => $dedupe,
                'commande_id' => $cmd_id,
                'push_sent' => $disp['push'],
                'email_sent' => $disp['email'],
            ]);
            $sent++;
        }
    }

    return $sent;
}

function notification_worker_panier_abandonne(): int
{
    $cfg = notifications_config();
    $paliers = $cfg['panier_abandonne_heures'] ?? [24, 72];
    $max_relances = (int) ($cfg['panier_relance_max'] ?? 2);
    if (!is_array($paliers) || empty($paliers)) {
        return 0;
    }
    global $db;
    $sent = 0;
    $base = rtrim(get_site_base_url(), '/');
    $link_panier = $base . '/panier.php';

    try {
        $st = $db->query('
            SELECT p.user_id, MAX(p.date_ajout) AS last_add, COUNT(*) AS nb_lignes
            FROM panier p
            INNER JOIN users u ON u.id = p.user_id AND u.statut = \'actif\'
            GROUP BY p.user_id
            HAVING nb_lignes > 0
        ');
        $users = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[notification_worker_panier_abandonne] ' . $e->getMessage());
        return 0;
    }

    require_once __DIR__ . '/../models/model_produits.php';

    foreach ($users as $row) {
        $user_id = (int) ($row['user_id'] ?? 0);
        $last_add = (string) ($row['last_add'] ?? '');
        if ($user_id <= 0 || $last_add === '') {
            continue;
        }

        $st_cmd = $db->prepare('
            SELECT COUNT(*) FROM commandes
            WHERE user_id = :uid AND date_commande >= :since
        ');
        $st_cmd->execute(['uid' => $user_id, 'since' => $last_add]);
        if ((int) $st_cmd->fetchColumn() > 0) {
            continue;
        }

        $rel = ['relance_count' => 0];
        if (notifications_ensure_tables()) {
            $st_r = $db->prepare('SELECT relance_count, cycle_started_at, derniere_relance_at FROM panier_relance WHERE user_id = :uid');
            $st_r->execute(['uid' => $user_id]);
            $rel = $st_r->fetch(PDO::FETCH_ASSOC) ?: ['relance_count' => 0];
        }
        notification_panier_relance_sync_cycle(
            $user_id,
            $last_add,
            isset($rel['derniere_relance_at']) ? (string) $rel['derniere_relance_at'] : null
        );
        $st_r2 = $db->prepare('SELECT relance_count FROM panier_relance WHERE user_id = :uid');
        $st_r2->execute(['uid' => $user_id]);
        $relance_count = (int) ($st_r2->fetchColumn() ?: 0);

        if ($relance_count >= $max_relances) {
            continue;
        }

        $hours_since = (time() - strtotime($last_add)) / 3600;
        $paliers_sorted = array_values(array_filter(array_map('intval', $paliers), static function ($h) {
            return $h > 0;
        }));
        sort($paliers_sorted, SORT_NUMERIC);
        $palier_ok = null;
        foreach ($paliers_sorted as $h) {
            if ($hours_since < $h) {
                continue;
            }
            $dedupe = 'panier:' . $user_id . ':' . $h . 'h';
            if (!notification_log_exists($dedupe)) {
                $palier_ok = ['h' => $h, 'dedupe' => $dedupe];
                break;
            }
        }
        if ($palier_ok === null) {
            continue;
        }

        $st_lines = $db->prepare('
            SELECT p.produit_id, p.quantite FROM panier p
            WHERE p.user_id = :uid ORDER BY p.date_ajout DESC LIMIT 2
        ');
        $st_lines->execute(['uid' => $user_id]);
        $lines = $st_lines->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $noms = [];
        foreach ($lines as $ln) {
            $pr = get_produit_by_id((int) ($ln['produit_id'] ?? 0));
            if ($pr && !empty($pr['nom'])) {
                $noms[] = $pr['nom'];
            }
        }
        $detail = !empty($noms) ? implode(', ', $noms) : 'vos articles';
        $title = 'Votre panier vous attend';
        $body = 'Vous avez laissé ' . $detail . ' dans votre panier. Finalisez votre commande.';
        $email_html = '<p>Bonjour,</p><p>Votre panier contient encore : <strong>' . htmlspecialchars($detail) . '</strong>.</p>';

        $disp = notification_dispatch_to_user(
            $user_id,
            $title,
            $body,
            [
                'type' => 'panier_abandonne',
                'link' => '/panier.php',
                'redirect_url' => $link_panier,
                'tag' => 'panier-' . $user_id,
            ],
            '',
            '[COLObanes] Votre panier vous attend',
            $email_html,
            $link_panier,
            ['type' => 'panier_abandonne', 'user_id' => $user_id]
        );
        if (!$disp['push'] && !$disp['email']) {
            continue;
        }
        notification_log_insert([
            'type' => 'panier_abandonne',
            'dedupe_key' => $palier_ok['dedupe'],
            'user_id' => $user_id,
            'push_sent' => $disp['push'],
            'email_sent' => $disp['email'],
        ]);
        $db->prepare('
            INSERT INTO panier_relance (user_id, derniere_relance_at, relance_count, cycle_started_at)
            VALUES (:uid, NOW(), 1, NOW())
            ON DUPLICATE KEY UPDATE
                derniere_relance_at = NOW(),
                relance_count = relance_count + 1
        ')->execute(['uid' => $user_id]);
        $sent++;
    }

    return $sent;
}

function notification_worker_favoris_relance(): int
{
    if (!notification_favoris_relance_ensure_table()) {
        return 0;
    }
    global $db;
    $sent = 0;
    require_once __DIR__ . '/../includes/app_base.php';
    $base = rtrim(get_site_base_url(), '/');
    $link_favoris = $base . app_url('/user/favoris.php');

    try {
        $st = $db->query('
            SELECT f.user_id, MAX(f.date_ajout) AS last_favori, COUNT(*) AS nb_favoris
            FROM favoris f
            INNER JOIN users u ON u.id = f.user_id AND u.statut = \'actif\'
            GROUP BY f.user_id
            HAVING nb_favoris > 0
        ');
        $users = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[notification_worker_favoris_relance] ' . $e->getMessage());
        return 0;
    }

    require_once __DIR__ . '/../models/model_produits.php';

    foreach ($users as $row) {
        $user_id = (int) ($row['user_id'] ?? 0);
        $last_favori = (string) ($row['last_favori'] ?? '');
        if ($user_id <= 0 || $last_favori === '') {
            continue;
        }

        $st_rel = $db->prepare('
            SELECT cycle_started_at, derniere_favori_at, derniere_relance_at, relance_count
            FROM favoris_relance WHERE user_id = :uid
        ');
        $st_rel->execute(['uid' => $user_id]);
        $rel = $st_rel->fetch(PDO::FETCH_ASSOC);
        if (!$rel || empty($rel['cycle_started_at'])) {
            notification_favoris_relance_on_favori_add($user_id);
            $st_rel->execute(['uid' => $user_id]);
            $rel = $st_rel->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$rel || empty($rel['cycle_started_at'])) {
            continue;
        }

        notification_favoris_relance_sync_cycle(
            $user_id,
            $last_favori,
            isset($rel['derniere_relance_at']) ? (string) $rel['derniere_relance_at'] : null
        );
        $st_rel->execute(['uid' => $user_id]);
        $rel = $st_rel->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$rel || empty($rel['cycle_started_at'])) {
            continue;
        }

        $cycle_start = (string) $rel['cycle_started_at'];
        $relance_count = (int) ($rel['relance_count'] ?? 0);
        $next_index = $relance_count;
        $heures_requises = notification_favoris_relance_heures_pour_index($next_index);
        $hours_since = (time() - strtotime($cycle_start)) / 3600;
        if ($hours_since < $heures_requises) {
            continue;
        }

        $st_cmd = $db->prepare('
            SELECT COUNT(*) FROM commandes
            WHERE user_id = :uid AND date_commande >= :since AND statut NOT IN (\'annulee\')
        ');
        $st_cmd->execute(['uid' => $user_id, 'since' => $cycle_start]);
        if ((int) $st_cmd->fetchColumn() > 0) {
            notification_favoris_relance_reset($user_id);
            continue;
        }

        $dedupe = 'favoris_relance:' . $user_id . ':' . $next_index;
        if (notification_log_exists($dedupe)) {
            continue;
        }

        $st_lines = $db->prepare('
            SELECT produit_id FROM favoris WHERE user_id = :uid ORDER BY date_ajout DESC LIMIT 2
        ');
        $st_lines->execute(['uid' => $user_id]);
        $noms = [];
        foreach ($st_lines->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ln) {
            $pr = get_produit_by_id((int) ($ln['produit_id'] ?? 0));
            if ($pr && !empty($pr['nom'])) {
                $noms[] = (string) $pr['nom'];
            }
        }
        $detail = $noms !== [] ? implode(', ', $noms) : 'vos favoris';

        if ($next_index === 0) {
            $title = 'Vos favoris vous attendent';
            $body = 'Vous avez enregistré ' . $detail . '. Passez commande quand vous voulez.';
        } elseif ($next_index === 1) {
            $title = 'Toujours dans vos favoris';
            $body = $detail . ' — une idée pour votre prochaine commande ?';
        } else {
            $title = 'Rappel — vos favoris';
            $body = 'N’oubliez pas : ' . $detail . ' sont toujours disponibles sur COLObanes.';
        }

        $push_ok = notification_dispatch_push_only_to_user(
            $user_id,
            $title,
            $body,
            [
                'type' => 'favoris_relance',
                'link' => '/user/favoris.php',
                'redirect_url' => $link_favoris,
                'tag' => 'favoris-' . $user_id,
            ]
        );
        if (!$push_ok) {
            continue;
        }

        notification_log_insert([
            'type' => 'favoris_relance',
            'dedupe_key' => $dedupe,
            'user_id' => $user_id,
            'channel' => 'push',
            'push_sent' => 1,
            'email_sent' => 0,
        ]);

        $db->prepare('
            UPDATE favoris_relance
            SET derniere_relance_at = NOW(), relance_count = relance_count + 1
            WHERE user_id = :uid
        ')->execute(['uid' => $user_id]);
        $sent++;
    }

    return $sent;
}

function notification_worker_favoris_ventes_rapides(): int
{
    $cfg = notifications_config();
    $seuil = (int) ($cfg['favori_ventes_rapides_seuil'] ?? 10);
    $jours = (int) ($cfg['favori_ventes_rapides_jours'] ?? 7);
    if ($seuil <= 0) {
        return 0;
    }
    global $db;
    $sent = 0;
    $base = rtrim(get_site_base_url(), '/');

    try {
        $st = $db->prepare("
            SELECT f.user_id, f.produit_id, COUNT(cp.id) AS nb_ventes, p.nom
            FROM favoris f
            INNER JOIN produits p ON p.id = f.produit_id AND p.statut = 'actif'
            INNER JOIN commande_produits cp ON cp.produit_id = f.produit_id
            INNER JOIN commandes c ON c.id = cp.commande_id
                AND c.date_commande >= DATE_SUB(NOW(), INTERVAL :j DAY)
                AND c.statut NOT IN ('annulee')
            GROUP BY f.user_id, f.produit_id, p.nom
            HAVING nb_ventes >= :seuil
        ");
        $st->execute(['j' => $jours, 'seuil' => $seuil]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[notification_worker_favoris_ventes_rapides] ' . $e->getMessage());
        return 0;
    }

    foreach ($rows as $row) {
        $user_id = (int) ($row['user_id'] ?? 0);
        $produit_id = (int) ($row['produit_id'] ?? 0);
        $nom = (string) ($row['nom'] ?? 'Produit');
        if ($user_id <= 0 || $produit_id <= 0) {
            continue;
        }
        $week = date('Y-W');
        $dedupe = 'favori_ventes:' . $user_id . ':' . $produit_id . ':' . $week;
        if (notification_log_exists($dedupe)) {
            continue;
        }
        $link = $base . '/produit.php?id=' . $produit_id . '&mp_ref=notif_favori';
        $title = 'Ça se vend vite !';
        $body = $nom . ' est très demandé en ce moment.';
        $email_html = '<p>Le produit <strong>' . htmlspecialchars($nom) . '</strong> de vos favoris connaît une forte demande.</p>';
        $disp = notification_dispatch_to_user(
            $user_id,
            $title,
            $body,
            [
                'type' => 'favori_ventes',
                'link' => '/produit.php?id=' . $produit_id,
                'redirect_url' => $link,
                'produit_id' => (string) $produit_id,
                'tag' => 'favori-ventes-' . $produit_id,
            ],
            '',
            '[COLObanes] ' . $title,
            $email_html,
            $link,
            ['type' => 'favori_ventes', 'produit_id' => $produit_id]
        );
        if ($disp['push'] || $disp['email']) {
            notification_log_insert([
                'type' => 'favori_ventes',
                'dedupe_key' => $dedupe,
                'user_id' => $user_id,
                'produit_id' => $produit_id,
                'push_sent' => $disp['push'],
                'email_sent' => $disp['email'],
            ]);
            $sent++;
        }
    }

    return $sent;
}

function notification_envoyer_baisse_prix_utilisateur(
    int $user_id,
    int $produit_id,
    string $nom,
    float $ref_prix,
    float $new_prix,
    string $type_log,
    string $title_push,
    string $base
): void {
    $user_id = (int) $user_id;
    $produit_id = (int) $produit_id;
    if ($user_id <= 0 || $produit_id <= 0 || $new_prix <= 0 || $ref_prix <= 0 || $new_prix >= $ref_prix) {
        return;
    }
    $dedupe = $type_log . ':' . $user_id . ':' . $produit_id . ':' . date('Y-m-d');
    if (notification_log_exists($dedupe)) {
        return;
    }
    $link = $base . '/produit.php?id=' . $produit_id . '&mp_ref=notif_prix';
    $body = $nom . ' : ' . number_format($new_prix, 0, ',', ' ') . ' FCFA (vous aviez '
        . number_format($ref_prix, 0, ',', ' ') . ' FCFA).';
    $disp = notification_dispatch_to_user(
        $user_id,
        $title_push,
        $body,
        ['type' => $type_log, 'link' => '/produit.php?id=' . $produit_id, 'redirect_url' => $link],
        '',
        '[COLObanes] Baisse de prix — ' . $nom,
        '<p>Le prix de <strong>' . htmlspecialchars($nom) . '</strong> est passé sous votre référence '
        . '(' . number_format($ref_prix, 0, ',', ' ') . ' → ' . number_format($new_prix, 0, ',', ' ') . ' FCFA).</p>',
        $link,
        ['type' => $type_log, 'produit_id' => $produit_id]
    );
    if ($disp['push'] || $disp['email']) {
        notification_log_insert([
            'type' => $type_log,
            'dedupe_key' => $dedupe,
            'user_id' => $user_id,
            'produit_id' => $produit_id,
            'push_sent' => $disp['push'],
            'email_sent' => $disp['email'],
        ]);
    }
}

function notification_alerte_prix_on_produit_change(int $produit_id, array $produit_avant): void
{
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0) {
        return;
    }
    require_once __DIR__ . '/../models/model_produits.php';
    require_once __DIR__ . '/../includes/schema_alerte_prix.php';
    if (!alerte_prix_ensure_table()) {
        return;
    }
    $apres = get_produit_by_id($produit_id);
    if (!$apres) {
        return;
    }
    $new_prix = notification_produit_prix_effectif($apres);
    $nom = (string) ($apres['nom'] ?? 'Produit');
    $cfg = notifications_config();
    $pct_min = (float) ($cfg['favori_prix_baisse_pct_min'] ?? 5);
    $sous_ref = !empty($cfg['prix_alerte_sous_reference']);
    $base = rtrim(get_site_base_url(), '/');
    global $db;
    try {
        $st = $db->prepare('
            SELECT user_id, prix_reference FROM produit_alerte_prix
            WHERE produit_id = :pid AND actif = 1
        ');
        $st->execute(['pid' => $produit_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $user_id = (int) ($row['user_id'] ?? 0);
            $ref_prix = (float) ($row['prix_reference'] ?? 0);
            if ($user_id <= 0 || $ref_prix <= 0 || $new_prix >= $ref_prix) {
                continue;
            }
            $baisse = (($ref_prix - $new_prix) / $ref_prix) * 100;
            if (!$sous_ref && $baisse < $pct_min) {
                continue;
            }
            notification_envoyer_baisse_prix_utilisateur(
                $user_id,
                $produit_id,
                $nom,
                $ref_prix,
                $new_prix,
                'alerte_prix',
                'Alerte prix : bonne nouvelle',
                $base
            );
        }
        $db->prepare('
            UPDATE produit_alerte_prix SET date_maj = NOW() WHERE produit_id = :pid AND actif = 1
        ')->execute(['pid' => $produit_id]);
    } catch (PDOException $e) {
        error_log('[notification_alerte_prix_on_produit_change] ' . $e->getMessage());
    }
}

/**
 * Appelé après modification produit (prix / stock).
 */
function notification_favoris_on_produit_change(int $produit_id, array $produit_avant): void
{
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0) {
        return;
    }
    require_once __DIR__ . '/../models/model_produits.php';
    $apres = get_produit_by_id($produit_id);
    if (!$apres) {
        return;
    }
    $cfg = notifications_config();
    $pct_min = (float) ($cfg['favori_prix_baisse_pct_min'] ?? 5);
    global $db;
    $base = rtrim(get_site_base_url(), '/');

    $old_prix = notification_produit_prix_effectif($produit_avant);
    $new_prix = notification_produit_prix_effectif($apres);
    $old_statut = (string) ($produit_avant['statut'] ?? '');
    $new_statut = (string) ($apres['statut'] ?? '');
    $nom = (string) ($apres['nom'] ?? 'Produit');

    try {
        $st = $db->prepare('SELECT user_id, dernier_prix_ref, dernier_statut FROM favoris_produit_snapshot WHERE produit_id = :pid');
        $st->execute(['pid' => $produit_id]);
        $snaps = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($snaps)) {
            $st2 = $db->prepare('SELECT user_id FROM favoris WHERE produit_id = :pid');
            $st2->execute(['pid' => $produit_id]);
            foreach ($st2->fetchAll(PDO::FETCH_ASSOC) ?: [] as $f) {
                $uid = (int) ($f['user_id'] ?? 0);
                if ($uid > 0) {
                    $db->prepare('
                        INSERT INTO favoris_produit_snapshot (user_id, produit_id, dernier_prix_ref, dernier_statut)
                        VALUES (:u, :p, :pr, :st)
                        ON DUPLICATE KEY UPDATE dernier_prix_ref = VALUES(dernier_prix_ref), dernier_statut = VALUES(dernier_statut)
                    ')->execute(['u' => $uid, 'p' => $produit_id, 'pr' => $old_prix, 'st' => $old_statut]);
                }
            }
            $st->execute(['pid' => $produit_id]);
            $snaps = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (PDOException $e) {
        error_log('[notification_favoris_on_produit_change] ' . $e->getMessage());
        return;
    }

    foreach ($snaps as $snap) {
        $user_id = (int) ($snap['user_id'] ?? 0);
        if ($user_id <= 0) {
            continue;
        }
        $ref_prix = (float) ($snap['dernier_prix_ref'] ?? $old_prix);
        $ref_statut = (string) ($snap['dernier_statut'] ?? $old_statut);

        if ($ref_prix > 0 && $new_prix < $ref_prix) {
            $baisse = (($ref_prix - $new_prix) / $ref_prix) * 100;
            $sous_ref = !empty($cfg['prix_alerte_sous_reference']);
            $do_notify = $sous_ref || $baisse >= $pct_min;
            if ($do_notify) {
                notification_envoyer_baisse_prix_utilisateur(
                    $user_id,
                    $produit_id,
                    $nom,
                    $ref_prix,
                    $new_prix,
                    'favori_prix',
                    'Baisse de prix sur un favori',
                    $base
                );
            }
        }

        if ($ref_statut === 'rupture_stock' && $new_statut === 'actif') {
            $dedupe = 'favori_stock:' . $user_id . ':' . $produit_id . ':' . date('Y-m-d');
            if (!notification_log_exists($dedupe)) {
                $link = $base . '/produit.php?id=' . $produit_id . '&mp_ref=notif_favori';
                $title = 'De retour en stock';
                $body = $nom . ' est à nouveau disponible.';
                $disp = notification_dispatch_to_user(
                    $user_id,
                    $title,
                    $body,
                    ['type' => 'favori_stock', 'link' => '/produit.php?id=' . $produit_id, 'redirect_url' => $link],
                    '',
                    '[COLObanes] ' . $nom . ' est de retour',
                    '<p><strong>' . htmlspecialchars($nom) . '</strong> est de nouveau en stock.</p>',
                    $link,
                    ['type' => 'favori_stock', 'produit_id' => $produit_id]
                );
                if ($disp['push'] || $disp['email']) {
                    notification_log_insert([
                        'type' => 'favori_stock',
                        'dedupe_key' => $dedupe,
                        'user_id' => $user_id,
                        'produit_id' => $produit_id,
                        'push_sent' => $disp['push'],
                        'email_sent' => $disp['email'],
                    ]);
                }
            }
        }
    }

    try {
        $db->prepare('
            UPDATE favoris_produit_snapshot
            SET dernier_prix_ref = :pr, dernier_statut = :st, date_maj = NOW()
            WHERE produit_id = :pid
        ')->execute(['pr' => $new_prix, 'st' => $new_statut, 'pid' => $produit_id]);
        $db->prepare('
            INSERT INTO favoris_produit_snapshot (user_id, produit_id, dernier_prix_ref, dernier_statut)
            SELECT user_id, produit_id, :pr, :st FROM favoris WHERE produit_id = :pid
            ON DUPLICATE KEY UPDATE dernier_prix_ref = VALUES(dernier_prix_ref), dernier_statut = VALUES(dernier_statut)
        ')->execute(['pr' => $new_prix, 'st' => $new_statut, 'pid' => $produit_id]);
    } catch (PDOException $e) {
        error_log('[notification_favoris_snapshot_update] ' . $e->getMessage());
    }

    notification_alerte_prix_on_produit_change($produit_id, $produit_avant);
}
