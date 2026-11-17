<?php
/**
 * Modèle — messagerie client ↔ vendeur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * @return bool
 */
function messages_vendeur_tables_exist()
{
    global $db;

    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $db->query("SHOW TABLES LIKE 'conversations_vendeur'");
        $ok1 = (bool) $stmt->fetch(PDO::FETCH_NUM);
        $stmt = $db->query("SHOW TABLES LIKE 'messages_vendeur'");
        $ok2 = (bool) $stmt->fetch(PDO::FETCH_NUM);
        $exists = ($ok1 && $ok2);
    } catch (PDOException $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * @return bool
 */
function conversations_vendeur_statut_column_exists()
{
    global $db;

    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    if (!messages_vendeur_tables_exist()) {
        $exists = false;
        return false;
    }

    try {
        $stmt = $db->query("SHOW COLUMNS FROM conversations_vendeur LIKE 'statut'");
        $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * @param array<string, mixed> $row
 * @return string
 */
function conversation_vendeur_row_statut($row)
{
    if (!is_array($row)) {
        return 'active';
    }
    $statut = (string) ($row['statut'] ?? 'active');
    return in_array($statut, ['active', 'achat'], true) ? $statut : 'active';
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @param string $filtre
 * @return array<int, array<string, mixed>>
 */
function conversation_vendeur_filter_inbox_rows(array $rows, $filtre)
{
    $filtre = trim((string) $filtre);
    if (!in_array($filtre, ['tout', 'non_lus', 'negociations', 'achats'], true)) {
        $filtre = 'tout';
    }

    return array_values(array_filter($rows, function ($c) use ($filtre) {
        $statut = conversation_vendeur_row_statut($c);

        if ($filtre === 'achats') {
            return $statut === 'achat';
        }

        if ($statut === 'achat') {
            return false;
        }

        if ($filtre === 'non_lus') {
            return (int) ($c['nb_non_lus'] ?? 0) > 0;
        }

        if ($filtre === 'negociations') {
            return (int) ($c['produit_id'] ?? 0) > 0;
        }

        return true;
    }));
}

/**
 * @param int $user_id
 * @param int $admin_id
 * @param int $produit_id
 * @param bool $add_message
 * @return bool
 */
function conversation_vendeur_archive_achat($user_id, $admin_id, $produit_id, $add_message = true)
{
    global $db;

    if (!messages_vendeur_tables_exist() || !conversations_vendeur_statut_column_exists()) {
        return false;
    }

    $user_id = (int) $user_id;
    $admin_id = (int) $admin_id;
    $produit_id = (int) $produit_id;

    if ($user_id <= 0 || $admin_id <= 0 || $produit_id <= 0) {
        return false;
    }

    try {
        $sel = $db->prepare(
            'SELECT id, statut FROM conversations_vendeur
             WHERE user_id = ? AND admin_id = ? AND produit_id = ?
             LIMIT 1'
        );
        $sel->execute([$user_id, $admin_id, $produit_id]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        if ((string) ($row['statut'] ?? '') === 'achat') {
            return false;
        }

        $conv_id = (int) $row['id'];
        $upd = $db->prepare(
            'UPDATE conversations_vendeur
             SET statut = \'achat\', dernier_message_at = NOW()
             WHERE id = ? AND statut = \'active\''
        );
        $upd->execute([$conv_id]);

        if ($upd->rowCount() <= 0) {
            return false;
        }

        if ($add_message) {
            message_vendeur_add(
                $conv_id,
                'client',
                $user_id,
                'Produit acheté — conversation archivée dans Achats.',
                'systeme',
                ['archive' => 'achat']
            );
        }

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param int $user_id
 * @param array<int, array<string, mixed>> $panier_items
 */
function conversation_vendeur_archive_from_panier_items($user_id, array $panier_items)
{
    if (!messages_vendeur_tables_exist()) {
        return;
    }

    require_once __DIR__ . '/model_produits.php';

    foreach ($panier_items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $produit_id = (int) ($item['produit_id'] ?? 0);
        if ($produit_id <= 0) {
            continue;
        }

        $admin_id = (int) ($item['vendeur_id'] ?? $item['admin_id'] ?? 0);
        if ($admin_id <= 0) {
            $produit = get_produit_by_id($produit_id);
            $admin_id = (int) ($produit['admin_id'] ?? 0);
        }

        if ($admin_id <= 0) {
            continue;
        }

        conversation_vendeur_archive_achat((int) $user_id, $admin_id, $produit_id, true);
    }
}

/**
 * Synchronise les conversations déjà achetées (commandes passées).
 *
 * @param int $user_id
 */
function conversation_vendeur_sync_achats_from_commandes($user_id)
{
    global $db;

    if (!messages_vendeur_tables_exist() || !conversations_vendeur_statut_column_exists()) {
        return;
    }

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }

    try {
        $stmt = $db->prepare(
            'SELECT DISTINCT cp.produit_id, p.admin_id
             FROM commandes c
             INNER JOIN commande_produits cp ON cp.commande_id = c.id
             INNER JOIN produits p ON p.id = cp.produit_id
             WHERE c.user_id = ?
               AND c.statut <> \'annulee\'
               AND cp.produit_id > 0
               AND p.admin_id > 0'
        );
        $stmt->execute([$user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return;
        }
        foreach ($rows as $row) {
            conversation_vendeur_archive_achat(
                $user_id,
                (int) ($row['admin_id'] ?? 0),
                (int) ($row['produit_id'] ?? 0),
                false
            );
        }
    } catch (PDOException $e) {
        // ignore
    }
}

/**
 * @param int $user_id
 * @param array<int, array<string, mixed>> $rows
 * @return int
 */
function conversation_vendeur_count_unread_in_rows($user_id, array $rows)
{
    unset($user_id);
    $total = 0;
    foreach ($rows as $c) {
        if (conversation_vendeur_row_statut($c) === 'achat') {
            continue;
        }
        $total += (int) ($c['nb_non_lus'] ?? 0);
    }
    return $total;
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @param string $statut
 * @return int
 */
function conversation_vendeur_count_by_statut_in_rows(array $rows, $statut)
{
    $n = 0;
    foreach ($rows as $c) {
        if (conversation_vendeur_row_statut($c) === $statut) {
            $n++;
        }
    }
    return $n;
}

/**
 * @param int $user_id
 * @param int $admin_id
 * @param int|null $produit_id
 * @param string $sujet
 * @return array{success:bool, id?:int, message?:string, created?:bool}
 */
function conversation_vendeur_get_or_create($user_id, $admin_id, $produit_id = null, $sujet = '')
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return ['success' => false, 'message' => 'Messagerie indisponible.'];
    }

    $user_id = (int) $user_id;
    $admin_id = (int) $admin_id;
    $produit_id = ($produit_id !== null && (int) $produit_id > 0) ? (int) $produit_id : null;
    $sujet = trim((string) $sujet);

    if ($user_id <= 0 || $admin_id <= 0) {
        return ['success' => false, 'message' => 'Conversation invalide.'];
    }

    if (messages_vendeur_client_vendeur_bloque($user_id, $admin_id)) {
        return ['success' => false, 'message' => 'Vous avez bloqué ce vendeur.'];
    }

    try {
        if ($produit_id !== null) {
            $stmt = $db->prepare(
                'SELECT id FROM conversations_vendeur
                 WHERE user_id = ? AND admin_id = ? AND produit_id = ? LIMIT 1'
            );
            $stmt->execute([$user_id, $admin_id, $produit_id]);
        } else {
            $stmt = $db->prepare(
                'SELECT id FROM conversations_vendeur
                 WHERE user_id = ? AND admin_id = ? AND produit_id IS NULL LIMIT 1'
            );
            $stmt->execute([$user_id, $admin_id]);
        }
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return ['success' => true, 'id' => (int) $existing['id'], 'created' => false];
        }

        $ins = $db->prepare(
            'INSERT INTO conversations_vendeur (user_id, admin_id, produit_id, sujet, dernier_message_at, date_creation)
             VALUES (?, ?, ?, ?, NULL, NOW())'
        );
        $ins->execute([$user_id, $admin_id, $produit_id, $sujet]);
        $new_id = (int) $db->lastInsertId();

        return ['success' => true, 'id' => $new_id, 'created' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Impossible de créer la conversation.'];
    }
}

/**
 * @param int $id
 * @return array<string, mixed>|null
 */
function conversation_vendeur_get_by_id($id)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return null;
    }

    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }

    try {
        $stmt = $db->prepare(
            'SELECT c.*,
                    u.prenom AS client_prenom, u.nom AS client_nom, u.email AS client_email,
                    u.date_creation AS client_date_creation,
                    a.prenom AS vendeur_prenom, a.nom AS vendeur_nom,
                    a.boutique_nom AS vendeur_boutique_nom, a.boutique_slug AS vendeur_boutique_slug,
                    a.boutique_logo AS vendeur_boutique_logo,
                    a.boutique_adresse AS vendeur_boutique_adresse, a.boutique_region AS vendeur_boutique_region,
                    a.date_creation AS vendeur_date_creation, a.derniere_connexion AS vendeur_derniere_connexion,
                    p.nom AS produit_nom, p.image_principale AS produit_image,
                    p.prix AS produit_prix, p.prix_promotion AS produit_prix_promotion, p.statut AS produit_statut
             FROM conversations_vendeur c
             INNER JOIN users u ON u.id = c.user_id
             INNER JOIN admin a ON a.id = c.admin_id
             LEFT JOIN produits p ON p.id = c.produit_id
             WHERE c.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Trouve l'id d'une conversation existante (user + vendeur + produit optionnel)
 *
 * @param int $user_id
 * @param int $admin_id
 * @param int $produit_id
 * @return int
 */
function conversation_vendeur_find_id_by_parties($user_id, $admin_id, $produit_id = 0)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return 0;
    }

    $user_id = (int) $user_id;
    $admin_id = (int) $admin_id;
    $produit_id = (int) $produit_id;

    if ($user_id <= 0 || $admin_id <= 0) {
        return 0;
    }

    try {
        if ($produit_id > 0) {
            $stmt = $db->prepare(
                'SELECT id FROM conversations_vendeur
                 WHERE user_id = ? AND admin_id = ? AND produit_id = ?
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$user_id, $admin_id, $produit_id]);
        } else {
            $stmt = $db->prepare(
                'SELECT id FROM conversations_vendeur
                 WHERE user_id = ? AND admin_id = ? AND produit_id IS NULL
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$user_id, $admin_id]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) ($row['id'] ?? 0) : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * URL messagerie admin pour une offre de négociation
 *
 * @param array<string, mixed> $offre_row
 * @param int $admin_id
 * @return string
 */
function conversation_vendeur_admin_href_for_offre($offre_row, $admin_id)
{
    if (!function_exists('app_url')) {
        require_once __DIR__ . '/../includes/app_base.php';
    }

    $user_id = (int) ($offre_row['user_id'] ?? 0);
    $produit_id = (int) ($offre_row['produit_id'] ?? 0);
    $conv_id = conversation_vendeur_find_id_by_parties($user_id, (int) $admin_id, $produit_id);
    $base = app_url('/admin/messages.php');
    if ($conv_id <= 0) {
        return $base;
    }

    return $base . '?conv=' . $conv_id;
}

/**
 * URL messagerie admin — première offre en attente d'un groupe produit
 *
 * @param array<string, mixed> $groupe
 * @param int $admin_id
 * @return string
 */
function conversation_vendeur_admin_href_for_produit_groupe($groupe, $admin_id)
{
    if (!is_array($groupe) || empty($groupe['offres']) || !is_array($groupe['offres'])) {
        if (!function_exists('app_url')) {
            require_once __DIR__ . '/../includes/app_base.php';
        }
        return app_url('/admin/messages.php');
    }

    foreach ($groupe['offres'] as $offre) {
        if (!is_array($offre)) {
            continue;
        }
        if ((string) ($offre['statut'] ?? '') === 'en_attente') {
            return conversation_vendeur_admin_href_for_offre($offre, $admin_id);
        }
    }

    $first = $groupe['offres'][0] ?? null;
    if (is_array($first)) {
        return conversation_vendeur_admin_href_for_offre($first, $admin_id);
    }

    if (!function_exists('app_url')) {
        require_once __DIR__ . '/../includes/app_base.php';
    }
    return app_url('/admin/messages.php');
}

/**
 * Ajoute un message système dans la conversation liée à une négociation
 *
 * @param array<string, mixed> $neg_row
 * @param int $admin_id
 * @param string $action accept|reject_counter|reject_final
 * @param float $prix_contre
 */
function messages_vendeur_sync_negociation_vendeur_action($neg_row, $admin_id, $action, $prix_contre = 0.0)
{
    if (!is_array($neg_row) || !messages_vendeur_tables_exist()) {
        return;
    }

    $user_id = (int) ($neg_row['user_id'] ?? 0);
    $produit_id = (int) ($neg_row['produit_id'] ?? 0);
    $admin_id = (int) $admin_id;
    if ($user_id <= 0 || $admin_id <= 0) {
        return;
    }

    $conv_id = conversation_vendeur_find_id_by_parties($user_id, $admin_id, $produit_id);
    if ($conv_id <= 0) {
        return;
    }

    $contenu = '';
    if ($action === 'accept') {
        $prix = number_format((float) ($neg_row['prix_propose_client'] ?? 0), 0, ',', ' ');
        $contenu = 'Offre acceptée : ' . $prix . ' FCFA.';
    } elseif ($action === 'reject_counter' && $prix_contre > 0) {
        $contenu = 'Contre-proposition : ' . number_format($prix_contre, 0, ',', ' ') . ' FCFA.';
    } elseif ($action === 'reject_final') {
        $contenu = 'Offre refusée.';
    }

    if ($contenu !== '') {
        $meta = null;
        if ($action === 'accept') {
            $meta = [
                'kind' => 'offre_acceptee',
                'negociation_id' => (int) ($neg_row['id'] ?? 0),
            ];
        } elseif ($action === 'reject_counter') {
            $meta = [
                'kind' => 'contre_proposition',
                'negociation_id' => (int) ($neg_row['id'] ?? 0),
                'prix_contre' => (float) $prix_contre,
            ];
        } elseif ($action === 'reject_final') {
            $meta = [
                'kind' => 'offre_refusee',
                'negociation_id' => (int) ($neg_row['id'] ?? 0),
            ];
        }
        message_vendeur_add($conv_id, 'vendeur', $admin_id, $contenu, 'systeme', $meta);
    }
}

/**
 * Message système — réponse client à une contre-proposition
 *
 * @param array<string, mixed> $neg_row
 * @param int $user_id
 * @param string $action accept_counter|reject_counter
 */
function messages_vendeur_sync_negociation_client_action($neg_row, $user_id, $action)
{
    if (!is_array($neg_row) || !messages_vendeur_tables_exist()) {
        return;
    }

    $user_id = (int) $user_id;
    $admin_id = (int) ($neg_row['admin_id'] ?? 0);
    $produit_id = (int) ($neg_row['produit_id'] ?? 0);
    if ($user_id <= 0 || $admin_id <= 0) {
        return;
    }

    $conv_id = conversation_vendeur_find_id_by_parties($user_id, $admin_id, $produit_id);
    if ($conv_id <= 0) {
        return;
    }

    $prix = null;
    if (function_exists('prix_negociation_prix_convenu_effectif')) {
        $prix = prix_negociation_prix_convenu_effectif($neg_row);
    }
    if ($prix === null) {
        $prix = (float) ($neg_row['prix_contre_vendeur'] ?? 0);
    }
    $prix_fmt = $prix > 0 ? number_format((float) $prix, 0, ',', ' ') . ' FCFA' : '';

    $contenu = '';
    $meta = [
        'negociation_id' => (int) ($neg_row['id'] ?? 0),
    ];

    if ($action === 'accept_counter') {
        $contenu = $prix_fmt !== ''
            ? 'Contre-proposition acceptée : ' . $prix_fmt . '.'
            : 'Contre-proposition acceptée.';
        $meta['kind'] = 'contre_acceptee';
    } elseif ($action === 'reject_counter') {
        $contenu = 'Contre-proposition refusée.';
        $meta['kind'] = 'contre_refusee';
    }

    if ($contenu !== '') {
        message_vendeur_add($conv_id, 'client', $user_id, $contenu, 'systeme', $meta);
    }
}

/**
 * @param int $user_id
 * @param int $limit
 * @return array<int, array<string, mixed>>
 */
function conversation_vendeur_list_by_user($user_id, $limit = 60)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return [];
    }

    $user_id = (int) $user_id;
    $limit = max(1, min(200, (int) $limit));

    try {
        $blocage_sql = '';
        if (messages_vendeur_blocages_table_exists()) {
            $blocage_sql = ' AND NOT EXISTS (
                SELECT 1 FROM messages_vendeur_blocages b
                WHERE b.user_id = c.user_id AND b.admin_id = c.admin_id
            )';
        }
        $preview_filter = messages_vendeur_sql_exclude_visible_to('vendeur');

        $stmt = $db->prepare(
            'SELECT c.*,
                    a.boutique_nom AS vendeur_boutique_nom, a.boutique_slug AS vendeur_boutique_slug,
                    a.boutique_logo AS vendeur_boutique_logo,
                    p.nom AS produit_nom, p.image_principale AS produit_image,
                    p.prix AS produit_prix, p.prix_promotion AS produit_prix_promotion,
                    (
                        SELECT m.contenu FROM messages_vendeur m
                        WHERE m.conversation_id = c.id' . $preview_filter . '
                        ORDER BY m.id DESC LIMIT 1
                    ) AS dernier_message,
                    (
                        SELECT m.date_creation FROM messages_vendeur m
                        WHERE m.conversation_id = c.id' . $preview_filter . '
                        ORDER BY m.id DESC LIMIT 1
                    ) AS dernier_message_date,
                    (
                        SELECT COUNT(*) FROM messages_vendeur m
                        WHERE m.conversation_id = c.id
                          AND m.expediteur_type = \'vendeur\'
                          AND m.lu_par_client = 0
                    ) AS nb_non_lus
             FROM conversations_vendeur c
             INNER JOIN admin a ON a.id = c.admin_id
             LEFT JOIN produits p ON p.id = c.produit_id
             WHERE c.user_id = ?' . $blocage_sql . '
             ORDER BY (nb_non_lus > 0) DESC,
                      COALESCE(dernier_message_date, c.dernier_message_at, c.date_creation) DESC,
                      c.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param int $admin_id
 * @param int $limit
 * @return array<int, array<string, mixed>>
 */
function conversation_vendeur_list_by_admin($admin_id, $limit = 60)
{
    return conversation_vendeur_list_inbox((int) $admin_id, $limit);
}

/**
 * Inbox support plateforme : toutes les conversations vendeur ↔ client.
 *
 * @param int $limit
 * @return array<int, array<string, mixed>>
 */
function conversation_vendeur_list_all($limit = 120)
{
    return conversation_vendeur_list_inbox(null, $limit);
}

/**
 * @param int|null $admin_id null = toutes les boutiques
 * @param int $limit
 * @return array<int, array<string, mixed>>
 */
function conversation_vendeur_list_inbox($admin_id = null, $limit = 60)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return [];
    }

    $limit = max(1, min(200, (int) $limit));
    $filter_admin = ($admin_id !== null && (int) $admin_id > 0);

    try {
        $preview_filter = messages_vendeur_sql_exclude_visible_to('client');

        $sql = 'SELECT c.*,
                    u.prenom AS client_prenom, u.nom AS client_nom,
                    p.nom AS produit_nom, p.image_principale AS produit_image,
                    p.prix AS produit_prix, p.prix_promotion AS produit_prix_promotion,
                    (
                        SELECT m.contenu FROM messages_vendeur m
                        WHERE m.conversation_id = c.id' . $preview_filter . '
                        ORDER BY m.id DESC LIMIT 1
                    ) AS dernier_message,
                    (
                        SELECT m.date_creation FROM messages_vendeur m
                        WHERE m.conversation_id = c.id' . $preview_filter . '
                        ORDER BY m.id DESC LIMIT 1
                    ) AS dernier_message_date,
                    (
                        SELECT COUNT(*) FROM messages_vendeur m
                        WHERE m.conversation_id = c.id
                          AND m.expediteur_type = \'client\'
                          AND m.lu_par_vendeur = 0
                    ) AS nb_non_lus,
                    (
                        SELECT COUNT(*) FROM messages_vendeur m
                        WHERE m.conversation_id = c.id
                          AND m.expediteur_type = \'client\'
                          AND m.lu_par_vendeur = 1
                    ) AS ouverte_vendeur
             FROM conversations_vendeur c
             INNER JOIN users u ON u.id = c.user_id
             LEFT JOIN produits p ON p.id = c.produit_id';
        if ($filter_admin) {
            $sql .= ' WHERE c.admin_id = ?';
        }
        $sql .= ' ORDER BY (nb_non_lus > 0) DESC,
                      COALESCE(dernier_message_date, c.dernier_message_at, c.date_creation) DESC,
                      c.id DESC
             LIMIT ' . (int) $limit;

        $stmt = $db->prepare($sql);
        if ($filter_admin) {
            $stmt->execute([(int) $admin_id]);
        } else {
            $stmt->execute();
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param int $conversation_id
 * @param int $user_id
 * @return bool
 */
function conversation_vendeur_belongs_to_user($conversation_id, $user_id)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return false;
    }

    try {
        $stmt = $db->prepare(
            'SELECT id FROM conversations_vendeur WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([(int) $conversation_id, (int) $user_id]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param int $conversation_id
 * @param int $admin_id
 * @return bool
 */
function conversation_vendeur_belongs_to_admin($conversation_id, $admin_id)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return false;
    }

    try {
        $stmt = $db->prepare(
            'SELECT id FROM conversations_vendeur WHERE id = ? AND admin_id = ? LIMIT 1'
        );
        $stmt->execute([(int) $conversation_id, (int) $admin_id]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Retire les accents sans artefact iconv (ex. ndèye → ndeye).
 *
 * @param string $text
 * @return string
 */
function messages_vendeur_strip_accents($text)
{
    static $map = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];

    return strtr($text, $map);
}

/**
 * Normalise un texte pour la détection (minuscules, espaces, accents).
 *
 * @param string $text
 * @return string
 */
function messages_vendeur_normalize_for_filter($text)
{
    $text = mb_strtolower(trim((string) $text), 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = messages_vendeur_strip_accents($text);

    return $text;
}

/**
 * Normalise un texte pour contourner les tentatives d'évasion (espaces, _, @, chiffres).
 *
 * @param string $text
 * @return array{spaced:string,compact:string}
 */
function messages_vendeur_normalize_insult_obfuscation($text)
{
    $text = mb_strtolower(trim((string) $text), 'UTF-8');
    $text = messages_vendeur_strip_accents($text);

    $replacements = [
        '@' => 'a',
        '3' => 'e',
        '0' => 'o',
        '1' => 'i',
        '4' => 'a',
        '5' => 's',
        '7' => 't',
    ];
    $text = strtr($text, $replacements);
    $text = preg_replace('/[_\-\.*]+/u', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);

    return [
        'spaced' => trim($text),
        'compact' => preg_replace('/\s+/u', '', $text),
    ];
}

/**
 * @return array<int, string>
 */
function messages_vendeur_insult_haystacks($text)
{
    $lower = mb_strtolower(trim((string) $text), 'UTF-8');
    $normalized = messages_vendeur_normalize_for_filter($text);
    $obfuscated = messages_vendeur_normalize_insult_obfuscation($text);

    $haystacks = [$lower, $normalized, $obfuscated['spaced'], $obfuscated['compact']];

    return array_values(array_unique(array_filter($haystacks, static function ($value) {
        return $value !== '';
    })));
}

/**
 * Détecte une adresse e-mail (y compris formes déguisées / espacées).
 *
 * @param string $text
 * @return bool
 */
function messages_vendeur_contains_email($text)
{
    $text = trim((string) $text);
    if ($text === '') {
        return false;
    }

    if (preg_match('/\bmailto\s*:\s*[a-z0-9._%+\-]+@/iu', $text)) {
        return true;
    }

    // Forme classique : user@domaine.tld
    if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}\b/iu', $text)) {
        return true;
    }

    // Espaces autour de @ / point : user @ gmail . com
    if (preg_match(
        '/\b[a-z0-9._%+\-]{1,64}\s*@\s*[a-z0-9.\-]{1,64}\s*\.\s*[a-z]{2,}\b/iu',
        $text
    )) {
        return true;
    }

    if (preg_match(
        '/\b[a-z0-9._\-+\']+\s*(?:@|\(at\)|\[at\]|(?:\(arobase\))|(?:\[arobase\])|\bat\b|\bchez\b|\barobase\b)\s*[a-z0-9.\-]+\s*(?:\.|\bdot\b|\bpoint\b)\s*[a-z]{2,}\b/iu',
        $text
    )) {
        return true;
    }

    if (preg_match(
        '/\b[a-z0-9._\-+\']+\s*(?:@|\(at\)|\[at\]|(?:\(arobase\))|(?:\[arobase\])|\bat\b|\bchez\b|\barobase\b)\s*[a-z0-9.\-]+\b/iu',
        $text
    )) {
        return true;
    }

    // Gmail / yahoo / outlook cités avec un identifiant (sans @)
    if (preg_match(
        '/\b[a-z0-9._%+\-]{2,64}\s+(?:sur\s+)?(?:gmail|yahoo|outlook|hotmail|icloud|proton(?:mail)?|live|msn)\s*\.?\s*(?:com|fr|pt|sn)?\b/iu',
        $text
    )) {
        return true;
    }

    return false;
}

/**
 * @return array<int, string>
 */
function messages_vendeur_insult_terms()
{
    return [
        'putain', 'putin', 'merde', 'connard', 'connasse', 'conasse', 'salope', 'salaud', 'salopard',
        'encule', 'enculer', 'enculé', 'nique', 'niquer', 'niker', 'nik', 'batard', 'batarde', 'bâtard',
        'enfoire', 'enfoiré', 'fdp', 'ntm', 'trouduc', 'couillon', 'abruti', 'cretin', 'crétin',
        'pute', 'putes', 'ordure', 'cretin', 'crétin',
        'fuck', 'fucking', 'fucker', 'fucked', 'motherfucker', 'shit', 'bullshit', 'bitch', 'bastard',
        'asshole', 'arsehole', 'cunt', 'dickhead', 'wanker', 'slut', 'whore',
        'negro', 'negre', 'nègre', 'bougnoule', 'youpin', 'pede', 'pédé', 'tapette',
        'salot', 'salaud', 'gros con', 'sale con', 'sale pute', 'sale chien', 'sale merde',
        'ferme la', 'tg', 'stfu', 'wtf',
        // Wolof — termes très vulgaires (isolés, peu ambigus)
        'coof', 'tiof', 'ruq', 'rouq', 'doof', 'nopale', 'dof',
        'xadj', 'khadj', 'xaj', 'mbaar', 'mbar',
    ];
}

/**
 * @return array<int, string>
 */
function messages_vendeur_insult_phrases()
{
    return [
        'ta gueule',
        'ferme ta gueule',
        'fermes ta gueule',
        'va te faire foutre',
        'va te faire enculer',
        'fils de pute',
        'fils de putain',
        'fille de pute',
        'nique ta mere',
        'nique ta mère',
        'nique ta race',
        'nique sa mere',
        'nique sa mère',
        'nique sa race',
        'sale con',
        'sale pute',
        'sale chien',
        'sale merde',
        'gros con',
        'grosse conne',
        'ferme la',
        't es con',
        'tes con',
        'tu es con',
        'va chier',
        'creve',
        'crève',
        'suicide toi',
        'suicide-toi',
        // Wolof — expressions injurieuses
        'sa ndeye',
        'sa ndaye',
        'say ndeye',
        'say ndaye',
        'kat sa ndeye',
        'kat sa ndaye',
        'sa ndeye kat',
        'sa ndaye kat',
        'sa baay',
        'sa baye',
        'sa baay kat',
        'sa baye kat',
        'coof dekk',
    ];
}

/**
 * Motifs regex Wolof (variantes orthographiques et contournements).
 *
 * @return array<int, string>
 */
function messages_vendeur_wolof_insult_regexes()
{
    return [
        '/sa[\s_\-\.@*]*n[d3@eèé][\s_\-\.@*]*y[eèé]m?/iu',
        '/say[\s_\-\.@*]*n[d3@eèé][\s_\-\.@*]*y[eèé]m?/iu',
        '/kat[\s_\-\.@*]*sa[\s_\-\.@*]*n[d3@eèé][\s_\-\.@*]*y[eèé]/iu',
        '/sa[\s_\-\.@*]*n[d3@eèé][\s_\-\.@*]*y[eèé][\s_\-\.@*]*kat/iu',
        '/sa[\s_\-\.@*]*b[a@4][\s_\-\.@*]*a?y[eèé]/iu',
        '/sa[\s_\-\.@*]*b[a@4][\s_\-\.@*]*a?y[eèé][\s_\-\.@*]*kat/iu',
        '/(?<![a-z0-9])coof[\s_\-\.@*]*dekk(?![a-z0-9])/iu',
        '/(?<![a-z0-9])x[@a]?dj(?![a-z0-9])/iu',
        '/(?<![a-z0-9])kh[@a]?dj(?![a-z0-9])/iu',
        '/(?<![a-z0-9])sandeye(?![a-z0-9])/iu',
        '/(?<![a-z0-9])sandaye(?![a-z0-9])/iu',
        '/(?<![a-z0-9])sayndeye(?![a-z0-9])/iu',
        '/(?<![a-z0-9])sayndaye(?![a-z0-9])/iu',
        '/(?<![a-z0-9])sabaaye?(?![a-z0-9])/iu',
        '/(?<![a-z0-9])sabaye(?![a-z0-9])/iu',
    ];
}

/**
 * Détecte insultes et propos injurieux courants (FR / EN / Wolof).
 *
 * @param string $text
 * @return bool
 */
function messages_vendeur_contains_insult($text)
{
    $text = trim((string) $text);
    if ($text === '') {
        return false;
    }

    $needles = messages_vendeur_insult_haystacks($text);

    foreach (messages_vendeur_wolof_insult_regexes() as $pattern) {
        foreach ($needles as $haystack) {
            if (preg_match($pattern, $haystack)) {
                return true;
            }
        }
        if (preg_match($pattern, $text)) {
            return true;
        }
    }

    foreach (messages_vendeur_insult_phrases() as $phrase) {
        $pattern = '/\b' . preg_replace('/\s+/u', '[\s_\-\.@*]+', preg_quote($phrase, '/')) . '\b/iu';
        foreach ($needles as $haystack) {
            if (preg_match($pattern, $haystack)) {
                return true;
            }
        }
    }

    foreach (messages_vendeur_insult_terms() as $term) {
        $quoted = preg_quote($term, '/');
        $pattern = '/(?<![a-z0-9àâäéèêëïîôùûüç])' . $quoted . '(?![a-z0-9àâäéèêëïîôùûüç])/iu';
        foreach ($needles as $haystack) {
            if (preg_match($pattern, $haystack)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Motif de blocage d'un message texte : contact (tél/e-mail) ou insulte.
 *
 * @param string $text
 * @return string|null contact|insult|null
 */
function messages_vendeur_blocked_reason($text)
{
    $text = trim((string) $text);
    if ($text === '') {
        return null;
    }

    if (messages_vendeur_contains_phone_number($text) || messages_vendeur_contains_email($text)) {
        return 'contact';
    }

    if (messages_vendeur_contains_insult($text)) {
        return 'insult';
    }

    return null;
}

/**
 * Détecte un numéro de téléphone, un e-mail ou une insulte dans un message.
 *
 * @param string $text
 * @return bool
 */
function messages_vendeur_contains_contact_info($text)
{
    return messages_vendeur_blocked_reason($text) !== null;
}

/**
 * Indique si une séquence de chiffres ressemble à un montant (prix), pas à un téléphone.
 *
 * @param string $text
 * @param string $match
 * @return bool
 */
function messages_vendeur_phone_match_is_price_context($text, $match)
{
    $text = (string) $text;
    $match = (string) $match;
    $pos = mb_stripos($text, $match);
    if ($pos === false) {
        $pos = 0;
    }
    $start = max(0, $pos - 24);
    $len = mb_strlen($match) + 48;
    $window = mb_substr($text, $start, $len);

    return (bool) preg_match(
        '/(?:fcfa|cfa|xof|eur|usd|\$|€|£|francs?|prix|montant|total|co[uû]t|fcfa|f\s*\.?c\s*\.?f\s*\.?a)/iu',
        $window
    );
}

/**
 * Détecte un numéro de téléphone (tous pays), y compris formes espacées / international.
 * Les montants (ex. « 25 000 FCFA ») sont ignorés autant que possible.
 *
 * @param string $text
 * @return bool
 */
function messages_vendeur_contains_phone_number($text)
{
    $text = trim((string) $text);
    if ($text === '') {
        return false;
    }

    // Indicatif international (+351…, +221…)
    if (preg_match('/\+\s*\d(?:[\s.\-()]*\d){7,14}/u', $text)) {
        return true;
    }

    // Préfixe 00 international
    if (preg_match('/(?<!\d)00(?:[\s.\-()]*\d){8,14}/u', $text)) {
        return true;
    }

    // Mot-clé contact + assez de chiffres à proximité
    $digits_all = preg_replace('/\D/u', '', $text);
    if (
        $digits_all !== ''
        && strlen($digits_all) >= 8
        && preg_match(
            '/\b(?:whatsapp|watsapp|wassap|wasap|telegram|viber|signal|appelez|appelle|appeler|appel[eé]s?-?moi|num[eé]ro|n[°o]\.?\s*(?:de\s*)?(?:t[eé]l\.?|tel\.?)|t[eé]l\.?(?:[eé]phone)?|portable|mobile|sms|contactez[- ]?moi|joignable|mon\s+num)\b/iu',
            $text
        )
    ) {
        return true;
    }

    // Séquences 8–15 chiffres (séparateurs . - espace () autorisés) — tous pays
    if (preg_match_all('/(?<!\d)(?:\d[\s.\-()]*){7,14}\d(?!\d)/u', $text, $matches)) {
        foreach ($matches[0] as $match) {
            $digits = preg_replace('/\D/u', '', $match);
            $len = strlen($digits);
            if ($len < 8 || $len > 15) {
                continue;
            }
            if (messages_vendeur_phone_match_is_price_context($text, $match)) {
                continue;
            }
            return true;
        }
    }

    // Message quasi uniquement composé de chiffres (ex. « 920635048 »)
    $compact = preg_replace('/[\s.\-()]+/u', '', $text);
    if ($compact !== '' && preg_match('/^\+?\d{8,15}$/u', $compact)) {
        return true;
    }

    // Sénégal : 7XXXXXXXX / +221 / 00221 (filet supplémentaire)
    if (preg_match(
        '/(?:\+[\s.\-]*221|00221|00[\s.\-]*221|(?<!\d)221)[\s.\-()]*7[0156789](?:[\s.\-()]*\d){7}/u',
        $text
    )) {
        return true;
    }

    if (preg_match('/(?<!\d)7[0156789](?:[\s.\-()]*\d){7}(?!\d)/u', $text)) {
        return true;
    }

    if ($digits_all === '' || strlen($digits_all) < 8) {
        return false;
    }

    if (preg_match('/(?:00)?2217[0156789]\d{7}/', $digits_all)) {
        return true;
    }

    $len = strlen($digits_all);
    for ($i = 0; $i <= $len - 9; $i++) {
        $chunk = substr($digits_all, $i, 9);
        if (preg_match('/^7[0156789]\d{7}$/', $chunk)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $message
 * @param string $viewer_type client|vendeur
 * @return bool
 */
function messages_vendeur_message_visible_to_viewer($message, $viewer_type)
{
    $viewer_type = ($viewer_type === 'vendeur') ? 'vendeur' : 'client';
    $meta_raw = $message['meta_json'] ?? null;
    if ($meta_raw === null || $meta_raw === '') {
        return true;
    }

    $meta = json_decode((string) $meta_raw, true);
    if (!is_array($meta) || empty($meta['visible_to'])) {
        return true;
    }

    return (string) $meta['visible_to'] === $viewer_type;
}

/**
 * @param array<int, array<string, mixed>> $messages
 * @param string $viewer_type
 * @return array<int, array<string, mixed>>
 */
function messages_vendeur_filter_for_viewer(array $messages, $viewer_type)
{
    $filtered = [];
    foreach ($messages as $message) {
        if (messages_vendeur_message_visible_to_viewer($message, $viewer_type)) {
            $filtered[] = $message;
        }
    }

    return $filtered;
}

/**
 * @param string $hidden_from client|vendeur
 * @return string
 */
function messages_vendeur_sql_exclude_visible_to($hidden_from)
{
    $hidden_from = ($hidden_from === 'vendeur') ? 'vendeur' : 'client';

    return " AND (m.meta_json IS NULL OR m.meta_json = '' OR JSON_UNQUOTE(JSON_EXTRACT(m.meta_json, '$.visible_to')) IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(m.meta_json, '$.visible_to')) <> '" . $hidden_from . "')";
}

/**
 * Données de l'alerte « coordonnées bloquées » (style marketplace).
 *
 * @param array<string, mixed> $message
 * @return array{title:string,body:string,learn_more:string}|null
 */
function messages_vendeur_contact_warning_data($message)
{
    $meta_raw = $message['meta_json'] ?? null;
    if ($meta_raw === null || $meta_raw === '') {
        return null;
    }

    $meta = json_decode((string) $meta_raw, true);
    if (!is_array($meta) || empty($meta['contact_warning'])) {
        return null;
    }

    if (!function_exists('app_url')) {
        require_once __DIR__ . '/../includes/app_base.php';
    }

    $reason = isset($meta['block_reason']) ? (string) $meta['block_reason'] : 'contact';

    if ($reason === 'insult') {
        return [
            'title' => 'Message non transmis',
            'body' => 'Les insultes et propos injurieux ne sont pas acceptés sur COLObanes. Ce message n\'a pas été envoyé à votre interlocuteur. Merci de reformuler votre message respectueusement.',
            'learn_more' => app_url('/conditions-utilisation.php'),
        ];
    }

    return [
        'title' => 'Message non transmis',
        'body' => 'Pour votre sécurité, l\'envoi de numéro de téléphone ou d\'e-mail est bloqué dans la messagerie. Ce message n\'a pas été transmis à votre interlocuteur. Utilisez la messagerie et le paiement sécurisé COLObanes.',
        'learn_more' => app_url('/conditions-utilisation.php'),
    ];
}

/**
 * @param array<string, mixed> $message
 * @return bool
 */
function messages_vendeur_is_contact_blocked_attempt($message)
{
    $meta_raw = $message['meta_json'] ?? null;
    if ($meta_raw === null || $meta_raw === '') {
        return false;
    }

    $meta = json_decode((string) $meta_raw, true);

    return is_array($meta) && !empty($meta['contact_blocked_attempt']);
}

/**
 * Enregistre une tentative bloquée (visible uniquement par l'expéditeur).
 *
 * @param int $conversation_id
 * @param string $expediteur_type
 * @param int $expediteur_id
 * @param string $contenu
 * @param string $block_reason contact|insult
 * @return array{success:bool, blocked?:bool, message?:string}
 */
function message_vendeur_add_contact_blocked($conversation_id, $expediteur_type, $expediteur_id, $contenu, $block_reason = 'contact')
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return ['success' => false, 'message' => 'Messagerie indisponible.'];
    }

    $conversation_id = (int) $conversation_id;
    $expediteur_id = (int) $expediteur_id;
    $contenu = trim((string) $contenu);
    $expediteur_type = ($expediteur_type === 'vendeur') ? 'vendeur' : 'client';
    $visible_to = $expediteur_type;
    $block_reason = ($block_reason === 'insult') ? 'insult' : 'contact';

    if ($conversation_id <= 0 || $contenu === '') {
        return ['success' => false, 'message' => 'Message vide ou conversation invalide.'];
    }

    try {
        $db->beginTransaction();

        $ins = $db->prepare(
            'INSERT INTO messages_vendeur
                (conversation_id, expediteur_type, expediteur_id, contenu, type_message, meta_json,
                 lu_par_client, lu_par_vendeur, date_creation)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        $meta_attempt = json_encode([
            'visible_to' => $visible_to,
            'contact_blocked_attempt' => true,
            'block_reason' => $block_reason,
        ], JSON_UNESCAPED_UNICODE);

        $ins->execute([
            $conversation_id,
            $expediteur_type,
            $expediteur_id,
            $contenu,
            'texte',
            $meta_attempt,
            1,
            1,
        ]);

        $meta_warning = json_encode([
            'visible_to' => $visible_to,
            'contact_warning' => true,
            'block_reason' => $block_reason,
        ], JSON_UNESCAPED_UNICODE);

        $ins->execute([
            $conversation_id,
            $expediteur_type,
            $expediteur_id,
            'contact_warning',
            'systeme',
            $meta_warning,
            1,
            1,
        ]);

        $db->commit();

        return ['success' => true, 'blocked' => true];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        return ['success' => false, 'message' => 'Impossible d\'enregistrer le message.'];
    }
}

/**
 * @param int $conversation_id
 * @param string $expediteur_type
 * @param int $expediteur_id
 * @param string $contenu
 * @param string $type_message
 * @param array<string, mixed>|null $meta
 * @return array{success:bool, id?:int, message?:string}
 */
function message_vendeur_add($conversation_id, $expediteur_type, $expediteur_id, $contenu, $type_message = 'texte', $meta = null)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return ['success' => false, 'message' => 'Messagerie indisponible.'];
    }

    $conversation_id = (int) $conversation_id;
    $expediteur_id = (int) $expediteur_id;
    $contenu = trim((string) $contenu);
    $type_message = in_array($type_message, ['texte', 'offre_prix', 'systeme'], true) ? $type_message : 'texte';
    $expediteur_type = ($expediteur_type === 'vendeur') ? 'vendeur' : 'client';

    if ($conversation_id <= 0 || $contenu === '') {
        return ['success' => false, 'message' => 'Message vide ou conversation invalide.'];
    }

    $meta_json = null;
    if (is_array($meta) && $meta !== []) {
        $meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE);
    }

    $lu_client = ($expediteur_type === 'client') ? 1 : 0;
    $lu_vendeur = ($expediteur_type === 'vendeur') ? 1 : 0;

    try {
        $db->beginTransaction();

        $ins = $db->prepare(
            'INSERT INTO messages_vendeur
                (conversation_id, expediteur_type, expediteur_id, contenu, type_message, meta_json,
                 lu_par_client, lu_par_vendeur, date_creation)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $ins->execute([
            $conversation_id,
            $expediteur_type,
            $expediteur_id,
            $contenu,
            $type_message,
            $meta_json,
            $lu_client,
            $lu_vendeur,
        ]);
        $msg_id = (int) $db->lastInsertId();

        $upd = $db->prepare(
            'UPDATE conversations_vendeur SET dernier_message_at = NOW() WHERE id = ?'
        );
        $upd->execute([$conversation_id]);

        $db->commit();

        if ($expediteur_type === 'vendeur') {
            if (!function_exists('message_vendeur_try_notify_client')) {
                require_once __DIR__ . '/../services/send_message_vendeur_notification.php';
            }
            message_vendeur_try_notify_client($conversation_id, $msg_id, $type_message, $contenu);
        } elseif ($expediteur_type === 'client') {
            if (!function_exists('message_vendeur_try_notify_vendeur')) {
                require_once __DIR__ . '/../services/send_message_vendeur_notification.php';
            }
            message_vendeur_try_notify_vendeur($conversation_id, $msg_id, $type_message, $contenu);
        }

        return ['success' => true, 'id' => $msg_id];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'message' => 'Impossible d\'envoyer le message.'];
    }
}

/**
 * @param int $conversation_id
 * @param int $limit
 * @param string|null $viewer_type client|vendeur|null
 * @return array<int, array<string, mixed>>
 */
function message_vendeur_list_by_conversation($conversation_id, $limit = 200, $viewer_type = null)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return [];
    }

    $conversation_id = (int) $conversation_id;
    $limit = max(1, min(500, (int) $limit));

    try {
        $stmt = $db->prepare(
            'SELECT * FROM messages_vendeur
             WHERE conversation_id = ?
             ORDER BY date_creation ASC, id ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$conversation_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows = is_array($rows) ? $rows : [];

        if ($viewer_type !== null && $viewer_type !== '') {
            return messages_vendeur_filter_for_viewer($rows, (string) $viewer_type);
        }

        return $rows;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Messages d'une conversation après un id (poll temps réel).
 *
 * @param int $conversation_id
 * @param int $since_id
 * @param string|null $viewer_type client|vendeur|null
 * @param int $limit
 * @return array<int, array<string, mixed>>
 */
function message_vendeur_list_since($conversation_id, $since_id, $viewer_type = null, $limit = 50)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return [];
    }

    $conversation_id = (int) $conversation_id;
    $since_id = max(0, (int) $since_id);
    $limit = max(1, min(50, (int) $limit));

    try {
        $stmt = $db->prepare(
            'SELECT * FROM messages_vendeur
             WHERE conversation_id = ? AND id > ?
             ORDER BY id ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$conversation_id, $since_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows = is_array($rows) ? $rows : [];

        if ($viewer_type !== null && $viewer_type !== '') {
            return messages_vendeur_filter_for_viewer($rows, (string) $viewer_type);
        }

        return $rows;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param int $conversation_id
 * @return int
 */
function message_vendeur_last_id_for_conversation($conversation_id)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return 0;
    }

    try {
        $stmt = $db->prepare(
            'SELECT id FROM messages_vendeur WHERE conversation_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([(int) $conversation_id]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Clé date (Y-m-d) du dernier message avant since_id (séparateurs de fil).
 *
 * @param int $conversation_id
 * @param int $since_id
 * @return string
 */
function message_vendeur_last_date_key_before($conversation_id, $since_id)
{
    global $db;

    if (!messages_vendeur_tables_exist() || (int) $since_id <= 0) {
        return '';
    }

    try {
        $stmt = $db->prepare(
            'SELECT date_creation FROM messages_vendeur
             WHERE conversation_id = ? AND id <= ?
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([(int) $conversation_id, (int) $since_id]);
        $dt = trim((string) $stmt->fetchColumn());
        if ($dt === '') {
            return '';
        }

        return date('Y-m-d', strtotime($dt));
    } catch (PDOException $e) {
        return '';
    }
}

/**
 * État du fil pour le poll (offre acceptée, actions négociation).
 *
 * @param array<string, mixed>|null $conv
 * @param string $viewer_role client|vendeur
 * @return array{has_offre_acceptee_a_payer:bool, show_chat_actions:bool, can_negocier:bool}
 */
function messages_vendeur_thread_state_for_api($conv, $viewer_role = 'client')
{
    $viewer_role = ($viewer_role === 'vendeur') ? 'vendeur' : 'client';
    $state = [
        'has_offre_acceptee_a_payer' => false,
        'show_chat_actions' => false,
        'can_negocier' => false,
    ];

    if (!is_array($conv) || $conv === []) {
        return $state;
    }

    $produit_id = (int) ($conv['produit_id'] ?? 0);
    if ($produit_id <= 0) {
        return $state;
    }

    $conv_statut = conversation_vendeur_row_statut($conv);
    $has_offre = false;

    if ($viewer_role === 'client') {
        if (!function_exists('prix_negociation_find_orderable_for_conversation')) {
            require_once __DIR__ . '/model_prix_negociations.php';
        }
        if (function_exists('prix_negociation_find_orderable_for_conversation')) {
            $neg = prix_negociation_find_orderable_for_conversation($conv);
            $has_offre = ($neg !== null);
        }

        $can_negocier = false;
        if (!$has_offre && $conv_statut !== 'achat') {
            if (!function_exists('get_produit_by_id')) {
                require_once __DIR__ . '/model_produits.php';
            }
            if (function_exists('get_produit_by_id') && function_exists('produit_prix_negociable')) {
                $p_row = get_produit_by_id($produit_id);
                $can_negocier = ($p_row && produit_prix_negociable($p_row));
            }
        }

        $state['has_offre_acceptee_a_payer'] = $has_offre;
        $state['show_chat_actions'] = !$has_offre;
        $state['can_negocier'] = $can_negocier;
    }

    return $state;
}

/**
 * Présence interlocuteur pour API poll.
 *
 * @param array<string, mixed>|null $conv
 * @param array<int, array<string, mixed>> $messages
 * @param string $viewer_role client|vendeur
 * @return array{label:string, online:bool}
 */
function messages_vendeur_presence_for_api($conv, $messages, $viewer_role = 'client')
{
    $viewer_role = ($viewer_role === 'vendeur') ? 'vendeur' : 'client';
    $label = messages_vendeur_chat_presence_label($conv ?? [], $messages ?? [], $viewer_role);

    return [
        'label' => $label,
        'online' => ($label === 'En ligne'),
    ];
}

/**
 * Conversation allégée pour poll inbox.
 *
 * @param array<string, mixed> $row
 * @param string $viewer_role client|vendeur
 * @return array<string, mixed>
 */
function messages_vendeur_inbox_row_for_api($row, $viewer_role = 'client')
{
    $viewer_role = ($viewer_role === 'vendeur') ? 'vendeur' : 'client';
    $produit_nom = trim((string) ($row['produit_nom'] ?? ''));
    $date_raw = (string) ($row['dernier_message_date'] ?? $row['dernier_message_at'] ?? $row['date_creation'] ?? '');

    $out = [
        'id' => (int) ($row['id'] ?? 0),
        'nb_non_lus' => (int) ($row['nb_non_lus'] ?? 0),
        'preview' => messages_vendeur_inbox_preview_text($row['dernier_message'] ?? '', $produit_nom),
        'meta_date' => $date_raw !== '' ? messages_vendeur_inbox_meta_date($date_raw) : '',
        'statut' => conversation_vendeur_row_statut($row),
        'produit_nom' => $produit_nom,
        'produit_id' => (int) ($row['produit_id'] ?? 0),
        'updated_at' => $date_raw,
    ];

    if ($viewer_role === 'client') {
        $out['label'] = $produit_nom !== '' ? $produit_nom : 'Produit';
        $out['boutique_nom'] = trim((string) ($row['vendeur_boutique_nom'] ?? 'Boutique'));
    } else {
        $client_nom = trim((string) ($row['client_prenom'] ?? '') . ' ' . (string) ($row['client_nom'] ?? ''));
        $out['label'] = $produit_nom !== '' ? $produit_nom : 'Produit';
        $out['client_nom'] = $client_nom !== '' ? $client_nom : 'Client';
    }

    $out['prix'] = conversation_vendeur_produit_prix_affiche([
        'prix' => $row['produit_prix'] ?? 0,
        'prix_promotion' => $row['produit_prix_promotion'] ?? null,
    ]);

    return $out;
}

/**
 * @param int $conversation_id
 * @param int $user_id
 */
function message_vendeur_mark_read_by_client($conversation_id, $user_id)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return;
    }

    if (!conversation_vendeur_belongs_to_user($conversation_id, $user_id)) {
        return;
    }

    try {
        $stmt = $db->prepare(
            'UPDATE messages_vendeur SET lu_par_client = 1
             WHERE conversation_id = ? AND expediteur_type = \'vendeur\' AND lu_par_client = 0'
        );
        $stmt->execute([(int) $conversation_id]);
    } catch (PDOException $e) {
        // ignore
    }
}

/**
 * @param int $conversation_id
 * @param int $admin_id
 */
function message_vendeur_mark_read_by_vendeur($conversation_id, $admin_id)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return;
    }

    if (!conversation_vendeur_belongs_to_admin($conversation_id, $admin_id)) {
        return;
    }

    try {
        $stmt = $db->prepare(
            'UPDATE messages_vendeur SET lu_par_vendeur = 1
             WHERE conversation_id = ? AND expediteur_type = \'client\' AND lu_par_vendeur = 0'
        );
        $stmt->execute([(int) $conversation_id]);
    } catch (PDOException $e) {
        // ignore
    }
}

/**
 * @param int $user_id
 * @return int
 */
function message_vendeur_count_unread_for_client($user_id)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return 0;
    }

    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM messages_vendeur m
             INNER JOIN conversations_vendeur c ON c.id = m.conversation_id
             WHERE c.user_id = ?
               AND m.expediteur_type = \'vendeur\'
               AND m.lu_par_client = 0'
        );
        $stmt->execute([(int) $user_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * @param int $admin_id
 * @return int
 */
function message_vendeur_count_unread_for_vendeur($admin_id)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return 0;
    }

    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM messages_vendeur m
             INNER JOIN conversations_vendeur c ON c.id = m.conversation_id
             WHERE c.admin_id = ?
               AND m.expediteur_type = \'client\'
               AND m.lu_par_vendeur = 0'
        );
        $stmt->execute([(int) $admin_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * @param int $conversation_id
 * @return bool
 */
function conversation_vendeur_has_messages($conversation_id)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return false;
    }

    try {
        $stmt = $db->prepare(
            'SELECT id FROM messages_vendeur WHERE conversation_id = ? LIMIT 1'
        );
        $stmt->execute([(int) $conversation_id]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param string $datetime
 * @return string
 */
function messages_vendeur_date_separator_label($datetime)
{
    $ts = strtotime((string) $datetime);
    if ($ts === false) {
        return '';
    }
    $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $m = (int) date('n', $ts) - 1;
    return (int) date('j', $ts) . ' ' . ($months[$m] ?? '') . ' ' . date('Y', $ts);
}

/**
 * Date courte pour la liste (hier, aujourd'hui, ou date complète)
 *
 * @param string $datetime
 * @return string
 */
function messages_vendeur_inbox_meta_date($datetime)
{
    $ts = strtotime((string) $datetime);
    if ($ts === false) {
        return '';
    }
    $day = strtotime(date('Y-m-d', $ts));
    $today = strtotime('today');
    $yesterday = strtotime('yesterday');
    if ($day === $today) {
        return 'aujourd\'hui';
    }
    if ($day === $yesterday) {
        return 'hier';
    }
    return messages_vendeur_date_separator_label($datetime);
}

/**
 * Aperçu lisible du dernier message dans la liste inbox
 *
 * @param string $raw
 * @param string $produit_nom
 * @return string
 */
function messages_vendeur_inbox_preview_text($raw, $produit_nom = '')
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/Offre de prix\s*:\s*([0-9][0-9\s.,]*)\s*FCFA/i', $raw, $m)) {
        $montant = preg_replace('/\s+/u', ' ', trim((string) $m[1]));
        return 'Offre : ' . $montant . ' FCFA';
    }

    if (preg_match('/^Bonjour,\s*je souhaite négocier/i', $raw)) {
        return 'Demande de négociation du prix';
    }

    if (preg_match('/^Message\s*:/i', $raw)) {
        $raw = trim((string) preg_replace('/^Message\s*:\s*/i', '', $raw));
    }

    if (mb_strlen($raw) > 130) {
        return mb_substr($raw, 0, 130) . '…';
    }

    return $raw;
}

/**
 * Seuil (secondes) pour considérer un interlocuteur « en ligne ».
 */
function messages_vendeur_online_threshold_seconds()
{
    return 300;
}

/**
 * @param string $datetime
 * @return bool
 */
function messages_vendeur_is_online_at($datetime)
{
    $ts = strtotime(trim((string) $datetime));
    if ($ts === false) {
        return false;
    }

    return (time() - $ts) < messages_vendeur_online_threshold_seconds();
}

/**
 * Libellé court de présence : « En ligne » ou « Il y a 5m / 2h / 4j ».
 *
 * @param string $last_seen_datetime Dernière activité affichée si hors ligne
 * @param string|null $online_datetime Horodatage pour le statut en ligne (connexion, etc.)
 * @return string
 */
function messages_vendeur_activity_label($last_seen_datetime, $online_datetime = null)
{
    $online_dt = ($online_datetime !== null && trim((string) $online_datetime) !== '')
        ? trim((string) $online_datetime)
        : trim((string) $last_seen_datetime);

    if ($online_dt !== '' && messages_vendeur_is_online_at($online_dt)) {
        return 'En ligne';
    }

    $ts = strtotime(trim((string) $last_seen_datetime));
    if ($ts === false) {
        return '';
    }

    $diff = max(0, time() - $ts);
    if ($diff < 3600) {
        $m = max(1, (int) floor($diff / 60));

        return 'il y a ' . $m . ' min';
    }
    if ($diff < 86400) {
        $h = max(1, (int) floor($diff / 3600));

        return 'il y a ' . $h . ' h';
    }

    $d = max(1, (int) floor($diff / 86400));

    return 'il y a ' . $d . ' j';
}

/**
 * Message entrant non lu pour le lecteur (client ou vendeur).
 *
 * @param array<string, mixed> $message
 * @param string $viewer_role client|vendeur
 * @return bool
 */
function messages_vendeur_message_unread_for_viewer($message, $viewer_role)
{
    if (!is_array($message)) {
        return false;
    }

    $viewer_role = ($viewer_role === 'vendeur') ? 'vendeur' : 'client';
    $from = (string) ($message['expediteur_type'] ?? '');

    if ($viewer_role === 'client') {
        if ($from === 'client') {
            return false;
        }

        return empty($message['lu_par_client']);
    }

    if ($from === 'vendeur') {
        return false;
    }

    return empty($message['lu_par_vendeur']);
}

/**
 * @param array<string, mixed> $message
 * @param string $viewer_role
 * @param int $msg_id
 * @return string
 */
function messages_vendeur_unread_data_attr($message, $viewer_role, $msg_id = 0)
{
    if ($msg_id <= 0 || !messages_vendeur_message_unread_for_viewer($message, $viewer_role)) {
        return '';
    }

    return ' data-unread="1"';
}

/**
 * Libellé de présence pour le fil client ou vendeur.
 *
 * @param array<string, mixed> $conv
 * @param array<int, array<string, mixed>> $messages
 * @param string $viewer_role client|vendeur
 * @return string
 */
function messages_vendeur_chat_presence_label($conv, $messages, $viewer_role)
{
    $viewer_role = ($viewer_role === 'vendeur') ? 'vendeur' : 'client';
    $other_role = ($viewer_role === 'client') ? 'vendeur' : 'client';

    $activity_dt = trim((string) ($conv['dernier_message_at'] ?? ''));
    if ($activity_dt === '' && !empty($messages)) {
        $last_msg_row = end($messages);
        $activity_dt = trim((string) ($last_msg_row['date_creation'] ?? ''));
    }

    $other_last_seen = '';
    if (!empty($messages) && is_array($messages)) {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (!is_array($messages[$i])) {
                continue;
            }
            if ((string) ($messages[$i]['expediteur_type'] ?? '') === $other_role) {
                $other_last_seen = trim((string) ($messages[$i]['date_creation'] ?? ''));
                break;
            }
        }
    }

    $last_seen = $other_last_seen !== '' ? $other_last_seen : $activity_dt;

    if ($viewer_role === 'client') {
        $online_dt = trim((string) ($conv['vendeur_derniere_connexion'] ?? ''));
        if ($online_dt === '') {
            $online_dt = $last_seen;
        }

        return messages_vendeur_activity_label($last_seen, $online_dt);
    }

    return messages_vendeur_activity_label($last_seen, $last_seen);
}

/**
 * @param string $datetime
 * @return string
 */
function messages_vendeur_member_since_label($datetime)
{
    $ts = strtotime((string) $datetime);
    if ($ts === false) {
        return '';
    }
    $months = [
        'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];
    $m = (int) date('n', $ts) - 1;

    return 'Membre depuis ' . ($months[$m] ?? '') . ' ' . date('Y', $ts);
}

/**
 * Lieu vendeur court pour le fil client (ville ou département uniquement)
 *
 * @param array<string, mixed> $conv
 * @return string
 */
function messages_vendeur_vendeur_lieu_court($conv)
{
    if (!is_array($conv)) {
        return '';
    }

    $adresse = trim((string) ($conv['vendeur_boutique_adresse'] ?? ''));
    if ($adresse !== '' && preg_match('/D[ée]partement de\s+([^,\n]+)/iu', $adresse, $m)) {
        $dept = trim((string) $m[1]);
        $dept = preg_replace('/\s+(Dakar|S[ée]n[ée]gal)$/iu', '', $dept);
        $dept = trim((string) $dept);
        if ($dept !== '') {
            return $dept;
        }
    }

    $region_code = trim((string) ($conv['vendeur_boutique_region'] ?? ''));
    if ($region_code !== '') {
        if (!function_exists('senegal_region_label')) {
            require_once __DIR__ . '/../includes/senegal_regions.php';
        }
        if (function_exists('senegal_region_is_valid') && senegal_region_is_valid($region_code)) {
            return senegal_region_label($region_code);
        }
    }

    if ($adresse === '') {
        return '';
    }

    $parts = preg_split('/[,;\n]+/u', $adresse);
    if (is_array($parts) && !empty($parts[0])) {
        $first = trim((string) $parts[0]);
        $first = preg_replace('/\s+(Dakar|S[ée]n[ée]gal)$/iu', '', $first);
        $first = trim((string) $first);
        if ($first !== '') {
            return $first;
        }
    }

    return $adresse;
}

/**
 * Données carte « Offre envoyée / reçue »
 *
 * @param array<string, mixed> $message
 * @param bool $from_client true si l'offre vient du client
 * @param string $viewer_role client|vendeur
 * @return array{title:string,body:string,note:string,prix:float}
 */
function messages_vendeur_offre_card_data($message, $from_client, $viewer_role = 'client')
{
    $contenu = trim((string) ($message['contenu'] ?? ''));
    $prix = 0.0;
    $meta_raw = $message['meta_json'] ?? null;
    if ($meta_raw !== null && $meta_raw !== '') {
        $meta = json_decode((string) $meta_raw, true);
        if (is_array($meta) && isset($meta['prix_propose'])) {
            $prix = (float) $meta['prix_propose'];
        }
    }
    if ($prix <= 0 && preg_match('/Offre de prix\s*:\s*([0-9][0-9\s.,]*)/i', $contenu, $m)) {
        $prix = (float) preg_replace('/[^\d]/', '', (string) $m[1]);
    }

    $prix_fmt = $prix > 0 ? number_format($prix, 0, ',', ' ') . ' FCFA' : '';
    $viewer_role = ($viewer_role === 'vendeur') ? 'vendeur' : 'client';
    $from_client = (bool) $from_client;

    if ($viewer_role === 'vendeur') {
        if ($from_client) {
            return [
                'title' => 'Offre reçue',
                'body' => $prix_fmt !== ''
                    ? 'Le client vous propose ' . $prix_fmt . '.'
                    : 'Le client vous a envoyé une offre de prix.',
                'note' => 'Vous pouvez accepter, refuser ou faire une contre-proposition.',
                'prix' => $prix,
            ];
        }

        return [
            'title' => 'Contre-proposition envoyée',
            'body' => $prix_fmt !== ''
                ? 'Vous avez proposé ' . $prix_fmt . ' au client.'
                : 'Vous avez envoyé une proposition de prix au client.',
            'note' => 'En attente de la réponse du client.',
            'prix' => $prix,
        ];
    }

    if ($from_client) {
        return [
            'title' => 'Offre envoyée',
            'body' => $prix_fmt !== ''
                ? 'Votre offre de ' . $prix_fmt . ' a bien été envoyée au vendeur.'
                : 'Votre offre a bien été envoyée au vendeur.',
            'note' => 'Le vendeur vous répondra dans les meilleurs délais.',
            'prix' => $prix,
        ];
    }

    return [
        'title' => 'Proposition reçue',
        'body' => $prix_fmt !== ''
            ? 'Le vendeur vous propose ' . $prix_fmt . '.'
            : 'Le vendeur vous a envoyé une proposition de prix.',
        'note' => 'Acceptez pour payer, ou refusez pour continuer.',
        'prix' => $prix,
    ];
}

/**
 * @param string $contenu
 * @return bool
 */
function messages_vendeur_is_negociation_intro($contenu)
{
    return (bool) preg_match('/^Bonjour,\s*je souhaite négocier/i', trim((string) $contenu));
}

/**
 * Stickers « bonne humeur » pour une offre acceptée (1 seul par message).
 *
 * @return array<int, string>
 */
function messages_vendeur_accept_stickers()
{
    return [
        '🥳', '🤩', '😎', '😁', '🎉', '😍', '😊', '🙌',
        '✨', '😄', '🤗', '🥹', '😇', '😆', '😌', '👌',
        '🥰', '😉', '🙂', '🤝',
    ];
}

/**
 * Choisit 1 sticker de façon stable par message (ordre « aléatoire » fixe).
 *
 * @param int $message_id
 * @return string
 */
function messages_vendeur_accept_sticker_for_message($message_id)
{
    $list = messages_vendeur_accept_stickers();
    $n = count($list);
    if ($n < 1) {
        return '🎉';
    }

    $message_id = (int) $message_id;
    if ($message_id <= 0) {
        return $list[array_rand($list)];
    }

    $idx = (int) (sprintf('%u', crc32('accept-sticker-' . $message_id)) % $n);

    return $list[$idx];
}

/**
 * Carte orange dans le fil admin — actions négociation (acceptation, refus, etc.)
 *
 * @param string $contenu
 * @return array{kind:string,title:string,body:string,note:string,icon:string}|null
 */
function messages_vendeur_admin_system_notice($contenu)
{
    $contenu = trim((string) $contenu);
    if ($contenu === '') {
        return null;
    }

    if (preg_match('/^Offre acceptée\s*:\s*(.+)$/iu', $contenu, $m)) {
        return [
            'kind' => 'accept',
            'title' => 'Offre acceptée',
            'body' => trim((string) $m[1]),
            'note' => 'Le client a été notifié.',
            'icon' => 'fa-check-circle',
        ];
    }

    if (preg_match('/^Contre-proposition\s*:\s*(.+)$/iu', $contenu, $m)) {
        return [
            'kind' => 'counter',
            'title' => 'Contre-proposition envoyée',
            'body' => trim((string) $m[1]),
            'note' => 'En attente de la réponse du client.',
            'icon' => 'fa-handshake',
        ];
    }

    if (preg_match('/^Offre refusée\.?$/iu', $contenu)) {
        return [
            'kind' => 'reject',
            'title' => 'Offre refusée',
            'body' => '',
            'note' => 'Le client a été notifié.',
            'icon' => 'fa-times-circle',
        ];
    }

    return null;
}

/**
 * Carte bleue client — offre acceptée par le vendeur (+ action payer)
 *
 * @param array<string, mixed> $message
 * @param array<string, mixed>|null $conv
 * @return array{title:string,body:string,note:string,icon:string,negotiation_id:int,can_pay:bool}|null
 */
function messages_vendeur_client_accept_notice($message, $conv)
{
    $contenu = trim((string) ($message['contenu'] ?? ''));
    if (!preg_match('/^Offre acceptée\s*:\s*(.+)$/iu', $contenu, $m)) {
        return null;
    }

    $neg_id = 0;
    $meta_raw = $message['meta_json'] ?? null;
    if ($meta_raw !== null && $meta_raw !== '') {
        $meta = json_decode((string) $meta_raw, true);
        if (is_array($meta) && !empty($meta['negociation_id'])) {
            $neg_id = (int) $meta['negociation_id'];
        }
    }

    $can_pay = false;
    if (is_array($conv) && $conv !== []) {
        if (!function_exists('prix_negociation_find_orderable_for_conversation')) {
            require_once __DIR__ . '/model_prix_negociations.php';
        }
        if (function_exists('prix_negociation_find_orderable_for_conversation')) {
            $neg = prix_negociation_find_orderable_for_conversation($conv, $neg_id);
            if ($neg !== null) {
                $can_pay = true;
                $neg_id = (int) ($neg['id'] ?? $neg_id);
            }
        }
    }

    return [
        'title' => 'Offre acceptée',
        'body' => trim((string) $m[1]),
        'note' => $can_pay
            ? 'Prix convenu — validez votre achat.'
            : 'Le vendeur a accepté votre offre.',
        'icon' => 'fa-gift',
        'negotiation_id' => $neg_id,
        'can_pay' => $can_pay,
    ];
}

/**
 * @param array<string, mixed> $produit
 * @return float
 */
function conversation_vendeur_produit_prix_affiche($produit)
{
    if (!is_array($produit)) {
        return 0.0;
    }
    $prix = (float) ($produit['prix'] ?? 0);
    $promo = isset($produit['prix_promotion']) ? (float) $produit['prix_promotion'] : 0.0;
    if ($promo > 0 && $promo < $prix) {
        return $promo;
    }
    return $prix;
}

/**
 * @return bool
 */
function messages_vendeur_blocages_table_exists()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    global $db;
    if (!$db) {
        $cache = false;
        return false;
    }
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'messages_vendeur_blocages'");
        $cache = ($stmt && $stmt->fetchColumn());
        return (bool) $cache;
    } catch (PDOException $e) {
        $cache = false;
        return false;
    }
}

/**
 * @param int $user_id
 * @param int $admin_id
 * @return bool
 */
function messages_vendeur_client_vendeur_bloque($user_id, $admin_id)
{
    global $db;

    if (!messages_vendeur_blocages_table_exists()) {
        return false;
    }

    $user_id = (int) $user_id;
    $admin_id = (int) $admin_id;
    if ($user_id <= 0 || $admin_id <= 0) {
        return false;
    }

    try {
        $stmt = $db->prepare(
            'SELECT id FROM messages_vendeur_blocages
             WHERE user_id = ? AND admin_id = ?
             LIMIT 1'
        );
        $stmt->execute([$user_id, $admin_id]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param int $user_id
 * @param int $admin_id
 * @return array{success:bool,message?:string}
 */
function messages_vendeur_client_bloquer_vendeur($user_id, $admin_id)
{
    global $db;

    if (!messages_vendeur_blocages_table_exists()) {
        return ['success' => false, 'message' => 'Action indisponible pour le moment.'];
    }

    $user_id = (int) $user_id;
    $admin_id = (int) $admin_id;
    if ($user_id <= 0 || $admin_id <= 0) {
        return ['success' => false, 'message' => 'Vendeur introuvable.'];
    }

    if (messages_vendeur_client_vendeur_bloque($user_id, $admin_id)) {
        conversation_vendeur_delete_all_for_client_vendeur($user_id, $admin_id);
        return ['success' => true];
    }

    try {
        $db->beginTransaction();

        $ins = $db->prepare(
            'INSERT INTO messages_vendeur_blocages (user_id, admin_id, date_creation)
             VALUES (?, ?, NOW())'
        );
        $ins->execute([$user_id, $admin_id]);

        conversation_vendeur_delete_all_for_client_vendeur($user_id, $admin_id);

        $db->commit();
        return ['success' => true];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'message' => 'Impossible de bloquer ce vendeur.'];
    }
}

/**
 * @param int $user_id
 * @param int $admin_id
 * @return bool
 */
function conversation_vendeur_delete_all_for_client_vendeur($user_id, $admin_id)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return false;
    }

    $user_id = (int) $user_id;
    $admin_id = (int) $admin_id;
    if ($user_id <= 0 || $admin_id <= 0) {
        return false;
    }

    try {
        $stmt = $db->prepare(
            'DELETE FROM conversations_vendeur WHERE user_id = ? AND admin_id = ?'
        );
        $stmt->execute([$user_id, $admin_id]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param int $conversation_id
 * @param int $user_id
 * @return array{success:bool,message?:string}
 */
function conversation_vendeur_delete_for_client($conversation_id, $user_id)
{
    global $db;

    if (!messages_vendeur_tables_exist()) {
        return ['success' => false, 'message' => 'Messagerie indisponible.'];
    }

    $conversation_id = (int) $conversation_id;
    $user_id = (int) $user_id;
    if ($conversation_id <= 0 || !conversation_vendeur_belongs_to_user($conversation_id, $user_id)) {
        return ['success' => false, 'message' => 'Conversation introuvable.'];
    }

    try {
        $stmt = $db->prepare('DELETE FROM conversations_vendeur WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$conversation_id, $user_id]);
        if ($stmt->rowCount() <= 0) {
            return ['success' => false, 'message' => 'Conversation introuvable.'];
        }
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Suppression impossible.'];
    }
}

/**
 * @return string
 */
function messages_vendeur_client_csrf_token()
{
    if (empty($_SESSION['user_messages_csrf'])) {
        $_SESSION['user_messages_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['user_messages_csrf'];
}

/**
 * @param string $token
 * @return bool
 */
function messages_vendeur_client_csrf_valid($token)
{
    $expected = $_SESSION['user_messages_csrf'] ?? '';
    return is_string($token) && $expected !== '' && hash_equals($expected, $token);
}

/**
 * @return string
 */
function messages_vendeur_admin_csrf_token()
{
    if (!function_exists('admin_csrf_token')) {
        require_once __DIR__ . '/../includes/admin_csrf.php';
    }

    return admin_csrf_token();
}

/**
 * @param string $token
 * @return bool
 */
function messages_vendeur_admin_csrf_valid($token)
{
    if (!function_exists('admin_csrf_valid')) {
        require_once __DIR__ . '/../includes/admin_csrf.php';
    }

    return admin_csrf_valid($token);
}
