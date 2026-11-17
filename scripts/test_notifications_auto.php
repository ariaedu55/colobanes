<?php
/**
 * Test automatique des notifications (aucune saisie SQL / commande manuelle).
 * Crée des données jetables, lance le worker, vérifie notification_log, nettoie.
 *
 * Usage : php scripts/test_notifications_auto.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/includes/schema_notifications.php';
require_once $root . '/includes/schema_client_comportement.php';
require_once $root . '/services/notification_worker.php';
require_once $root . '/services/send_produit_cible_notification.php';
require_once $root . '/services/notification_engagement_segments.php';
require_once $root . '/services/notification_engagement_inactif.php';
require_once $root . '/services/notification_segment_queue.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : BDD indisponible.\n";
    exit(1);
}

notifications_ensure_tables();
client_comportement_ensure_tables();

$pass = 0;
$fail = 0;

function tn(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo '  [OK]   ' . $label . ($detail !== '' ? ' — ' . $detail : '') . "\n";
    } else {
        $fail++;
        echo '  [FAIL] ' . $label . ($detail !== '' ? ' — ' . $detail : '') . "\n";
    }
}

$test_user_id = 0;
$test_cmd_id = 0;
$test_produit_id = 0;
$dedupe_keys = [];
$numero_test = '';

echo "=== Test automatique notifications (push + file e-mail) ===\n\n";

try {
    $prod = $db->query("
        SELECT p.id, p.admin_id, p.prix, p.prix_promotion, p.nom, p.statut, p.categorie_generale_id, p.categorie_id
        FROM produits p
        INNER JOIN admin a ON a.id = p.admin_id
        WHERE p.statut = 'actif' AND a.role = 'vendeur' AND a.statut = 'actif'
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$prod) {
        echo "ABANDON : aucun produit vendeur actif.\n";
        exit(1);
    }
    $test_produit_id = (int) $prod['id'];
    $vendeur_id = (int) $prod['admin_id'];
    echo "Produit #{$test_produit_id} — vendeur #{$vendeur_id}\n\n";

    $suffix = bin2hex(random_bytes(4));
    $db->prepare("
        INSERT INTO users (nom, prenom, email, telephone, password, date_creation, statut, accepte_conditions)
        VALUES ('TestNotif', 'Auto', :email, :tel, :pwd, NOW(), 'actif', 1)
    ")->execute([
        'email' => "test.notif.$suffix@test.local",
        'tel' => '+221700' . rand(100000, 999999),
        'pwd' => password_hash('test-notif-auto', PASSWORD_BCRYPT),
    ]);
    $test_user_id = (int) $db->lastInsertId();
    tn('utilisateur de test', $test_user_id > 0, "#$test_user_id");

    /* --- 1. Panier abandonné --- */
    echo "\n1. Panier abandonné (simulation 25 h)\n";
    $db->prepare("
        INSERT INTO panier (user_id, vendeur_id, produit_id, quantite, prix_unitaire, date_ajout)
        VALUES (:uid, :vid, :pid, 1, :prix, DATE_SUB(NOW(), INTERVAL 25 HOUR))
    ")->execute([
        'uid' => $test_user_id,
        'vid' => $vendeur_id,
        'pid' => $test_produit_id,
        'prix' => (float) $prod['prix'],
    ]);
    $dedupe_keys[] = 'panier:' . $test_user_id . ':24h';

    $stats = notification_worker_run_all();
    tn('worker exécuté', is_array($stats));

    $st = $db->prepare('SELECT push_sent, email_sent FROM notification_log WHERE dedupe_key = :k');
    $st->execute(['k' => $dedupe_keys[0]]);
    $log_panier = $st->fetch(PDO::FETCH_ASSOC);
    tn('journal panier 24h', $log_panier !== false, $log_panier ? 'push=' . $log_panier['push_sent'] . ' email=' . $log_panier['email_sent'] : 'absent');
    tn('au moins un canal panier', $log_panier && ((int) $log_panier['push_sent'] === 1 || (int) $log_panier['email_sent'] === 1));

    /* --- 2. Commande en attente vendeur --- */
    echo "\n2. Rappel commande en attente (simulation 25 h)\n";
    $has_vendeur = false;
    try {
        $r = $db->query("SHOW COLUMNS FROM commandes LIKE 'vendeur_id'");
        $has_vendeur = $r && $r->rowCount() > 0;
    } catch (PDOException $e) {
        $has_vendeur = false;
    }

    if ($has_vendeur) {
        $numero_test = 'TEST-AUTO-' . strtoupper($suffix);
        $db->prepare("
            INSERT INTO commandes (
                user_id, vendeur_id, numero_commande, montant_total,
                adresse_livraison, telephone_livraison, statut, date_commande, notes
            ) VALUES (
                :uid, :vid, :num, :montant,
                'Adresse test auto', '+221770000099', 'en_attente', DATE_SUB(NOW(), INTERVAL 25 HOUR), 'test_notifications_auto'
            )
        ")->execute([
            'uid' => $test_user_id,
            'vid' => $vendeur_id,
            'num' => $numero_test,
            'montant' => (float) $prod['prix'],
        ]);
        $test_cmd_id = (int) $db->lastInsertId();
        $dedupe_keys[] = 'cmd_attente:' . $test_cmd_id . ':24h';

        notification_worker_run_all();

        $st->execute(['k' => $dedupe_keys[count($dedupe_keys) - 1]]);
        $log_cmd = $st->fetch(PDO::FETCH_ASSOC);
        tn('journal cmd_attente vendeur', $log_cmd !== false);
        tn('canal cmd_attente', $log_cmd && ((int) $log_cmd['push_sent'] === 1 || (int) $log_cmd['email_sent'] === 1));
    } else {
        tn('colonne vendeur_id (marketplace)', false, 'rappel vendeur ignoré sur cette BDD');
    }

    /* --- 3. Favori baisse de prix --- */
    echo "\n3. Favori — baisse de prix\n";
    $db->prepare('INSERT IGNORE INTO favoris (user_id, produit_id, date_ajout) VALUES (:u, :p, NOW())')
        ->execute(['u' => $test_user_id, 'p' => $test_produit_id]);

    $prix_ref = (float) $prod['prix'];
    if ($prix_ref <= 0) {
        $prix_ref = 5000.0;
    }
    $nouveau_promo = max(1, round($prix_ref * 0.85, 2));
    $avant = array_merge($prod, ['prix_promotion' => null, 'prix' => $prix_ref, 'statut' => 'actif']);

    $db->prepare('
        INSERT INTO favoris_produit_snapshot (user_id, produit_id, dernier_prix_ref, dernier_statut)
        VALUES (:u, :p, :pr, \'actif\')
        ON DUPLICATE KEY UPDATE dernier_prix_ref = VALUES(dernier_prix_ref), dernier_statut = VALUES(dernier_statut)
    ')->execute(['u' => $test_user_id, 'p' => $test_produit_id, 'pr' => $prix_ref]);

    $db->prepare('UPDATE produits SET prix_promotion = :pp, statut = \'actif\' WHERE id = :id')
        ->execute(['pp' => $nouveau_promo, 'id' => $test_produit_id]);

    notification_favoris_on_produit_change($test_produit_id, $avant);

    $dedupe_fav = 'favori_prix:' . $test_user_id . ':' . $test_produit_id . ':' . date('Y-m-d');
    $dedupe_keys[] = $dedupe_fav;
    $st->execute(['k' => $dedupe_fav]);
    $log_fav = $st->fetch(PDO::FETCH_ASSOC);
    tn('journal favori_prix', $log_fav !== false);
    tn('canal favori_prix', $log_fav && ((int) $log_fav['push_sent'] === 1 || (int) $log_fav['email_sent'] === 1));

    $db->prepare('UPDATE produits SET prix_promotion = :pp WHERE id = :id')
        ->execute([
            'pp' => $prod['prix_promotion'] !== null && $prod['prix_promotion'] !== '' ? $prod['prix_promotion'] : null,
            'id' => $test_produit_id,
        ]);

    /* --- 4. Produit ciblé (recherche) --- */
    echo "\n4. Produit ciblé (recherche comportement)\n";
    if (client_comportement_ensure_tables() && client_comportement_table_exists('client_evenements')) {
        $token = notification_produit_recherche_tokens((string) $prod['nom']);
        $terme = !empty($token) ? $token[0] : 'test';
        $db->prepare("
            INSERT INTO client_evenements (type, user_id, terme_recherche, date_evenement, poids)
            VALUES ('recherche', :uid, :t, NOW(), 2)
        ")->execute(['uid' => $test_user_id, 't' => $terme]);

        $ids_recherche = notification_produit_cible_user_ids_from_recherche((string) $prod['nom'], 90);
        tn('recherche → user test', in_array($test_user_id, $ids_recherche, true));

        require_once $root . '/services/notification_dispatch.php';
        $dedupe_pc = 'produit_cible:' . $test_produit_id . ':' . $test_user_id;
        $link = '/produit.php?id=' . $test_produit_id;
        $disp_pc = notification_dispatch_to_user(
            $test_user_id,
            'Test produit ciblé',
            'Vérification automatique (test jetable).',
            ['type' => 'produit_cible', 'link' => $link],
            '',
            '[COLObanes] Test produit ciblé',
            '<p>Test automatique notification produit ciblé.</p>',
            '',
            ['type' => 'produit_cible_test']
        );
        if ($disp_pc['push'] || $disp_pc['email']) {
            notification_log_insert([
                'type' => 'produit_cible',
                'dedupe_key' => $dedupe_pc,
                'user_id' => $test_user_id,
                'produit_id' => $test_produit_id,
                'push_sent' => $disp_pc['push'],
                'email_sent' => $disp_pc['email'],
            ]);
        }
        $st->execute(['k' => $dedupe_pc]);
        $log_pc = $st->fetch(PDO::FETCH_ASSOC);
        tn('journal produit_cible (test user seul)', $log_pc !== false);
        $dedupe_keys[] = $dedupe_pc;
    } else {
        tn('tables comportement', false, 'skip produit ciblé');
    }

    /* --- 5. Engagement segments (copies identiques) --- */
    echo "\n5. Engagement segments (nouveauté + inactif)\n";
    $gid_test = (int) ($prod['categorie_generale_id'] ?? 0);
    $c1 = notification_engagement_copy_nouveau_produit($gid_test, (string) $prod['nom']);
    $c2 = notification_engagement_copy_nouveau_produit($gid_test, (string) $prod['nom']);
    tn('copie nouveauté identique (même gid)', $c1['title'] === $c2['title'] && $c1['body'] === $c2['body']);
    tn('titre nouveauté contient Ne ratez pas', strpos($c1['title'], 'Ne ratez pas') !== false);

    if ($gid_test > 0 && client_comportement_table_exists('client_interets_rayon')) {
        $db->prepare('
            INSERT INTO client_interets_rayon (user_id, visiteur_key, categorie_generale_id, score, derniere_maj)
            VALUES (:uid, NULL, :gid, 10, NOW())
            ON DUPLICATE KEY UPDATE score = 10, derniere_maj = NOW()
        ')->execute(['uid' => $test_user_id, 'gid' => $gid_test]);
        $pgid = notification_engagement_primary_gid_for_user($test_user_id);
        tn('rayon principal utilisateur test', $pgid === $gid_test, "gid=$pgid");
    }

    echo "\n5b. Relance inactivité (simulation)\n";
    notification_engagement_inactif_ensure_table();
    if (client_comportement_table_exists('client_evenements')) {
        $db->prepare('DELETE FROM client_evenements WHERE user_id = :id')->execute(['id' => $test_user_id]);
        $db->prepare("
            INSERT INTO client_evenements (type, user_id, date_evenement, poids)
            VALUES ('vue_produit', :uid, DATE_SUB(NOW(), INTERVAL 10 DAY), 1)
        ")->execute(['uid' => $test_user_id]);
        $db->prepare('DELETE FROM engagement_relance WHERE user_id = :id')->execute(['id' => $test_user_id]);

        $yw = date('o-\WW');
        $dedupe_ei = 'engagement_inactif:' . $test_user_id . ':' . $yw;
        $dedupe_keys[] = $dedupe_ei;

        $db->prepare('DELETE FROM notification_log WHERE dedupe_key = :k')->execute(['k' => $dedupe_ei]);
        if (notifications_table_exists('notification_segment_queue')) {
            $db->prepare("DELETE FROM notification_segment_queue WHERE user_id = :uid AND campaign = 'engagement_inactif'")
                ->execute(['uid' => $test_user_id]);
        }
        $db->prepare('DELETE FROM engagement_relance WHERE user_id = :id')->execute(['id' => $test_user_id]);
        if ($test_cmd_id > 0) {
            $db->prepare('UPDATE commandes SET date_commande = DATE_SUB(NOW(), INTERVAL 20 DAY) WHERE id = :id')
                ->execute(['id' => $test_cmd_id]);
        }

        $n_inactif = notification_worker_engagement_inactif();
        tn('worker engagement_inactif enqueue', $n_inactif >= 1, 'queued=' . $n_inactif);

        $db->prepare("
            UPDATE notification_segment_queue
            SET scheduled_send_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            WHERE user_id = :uid AND campaign = 'engagement_inactif' AND sent_at IS NULL
        ")->execute(['uid' => $test_user_id]);

        $batch = notification_segment_queue_dispatch_batch();
        tn('dispatch segment_queue', ($batch['sent'] ?? 0) >= 1, json_encode($batch, JSON_UNESCAPED_UNICODE));

        $st->execute(['k' => $dedupe_ei]);
        $log_ei = $st->fetch(PDO::FETCH_ASSOC);
        tn('journal engagement_inactif', $log_ei !== false);
        tn('canal engagement_inactif', $log_ei && ((int) $log_ei['push_sent'] === 1 || (int) $log_ei['email_sent'] === 1));
    }

    echo "\n5c. File segmentée — plafond 25 par run\n";
    notification_segment_queue_ensure_table();
    $gid_batch = $gid_test > 0 ? $gid_test : 1;
    $queue_ids = [];
    for ($i = 0; $i < 30; $i++) {
        $uid_fake = 900000 + $i;
        $dedupe_fake = 'test_segment_queue:' . $uid_fake . ':' . $suffix;
        $db->prepare('
            INSERT INTO notification_segment_queue
                (user_id, categorie_generale_id, campaign, dedupe_key, payload_json, scheduled_send_at)
            VALUES
                (:uid, :gid, \'engagement_inactif\', :dedupe, :payload, DATE_SUB(NOW(), INTERVAL 1 MINUTE))
        ')->execute([
            'uid' => $uid_fake,
            'gid' => $gid_batch,
            'dedupe' => $dedupe_fake,
            'payload' => json_encode([
                'title' => 'Test batch',
                'body' => 'Test',
                'email_subject' => 'Test',
                'email_html' => '<p>Test</p>',
                'link_abs' => 'https://example.test/',
                'log_type' => 'engagement_inactif',
                'data' => ['type' => 'engagement_inactif'],
            ], JSON_UNESCAPED_UNICODE),
        ]);
        $queue_ids[] = (int) $db->lastInsertId();
        $dedupe_keys[] = $dedupe_fake;
    }
    $batch30 = notification_segment_queue_dispatch_batch();
    $attempts = (int) ($batch30['sent'] ?? 0) + (int) ($batch30['skipped'] ?? 0);
    tn('batch max 25 tentatives', $attempts <= 25, 'sent=' . ($batch30['sent'] ?? 0) . ' skipped=' . ($batch30['skipped'] ?? 0));
    if (!empty($queue_ids)) {
        $placeholders = implode(',', array_fill(0, count($queue_ids), '?'));
        $db->prepare("DELETE FROM notification_segment_queue WHERE id IN ($placeholders)")->execute($queue_ids);
    }

    /* --- 6. File e-mail --- */
    echo "\n6. Traitement file e-mail\n";
    require_once $root . '/services/email_queue.php';
    $mail_stats = email_queue_process(50);
    tn('email_queue traitée', is_array($mail_stats), json_encode($mail_stats, JSON_UNESCAPED_UNICODE));

} catch (Throwable $e) {
    tn('déroulement global', false, $e->getMessage());
}

echo "\nNettoyage…\n";
try {
    if (!empty($dedupe_keys)) {
        $st_del = $db->prepare('DELETE FROM notification_log WHERE dedupe_key = :k');
        foreach ($dedupe_keys as $k) {
            $st_del->execute(['k' => $k]);
        }
    }
    if ($test_cmd_id > 0) {
        $db->prepare('DELETE FROM commande_produits WHERE commande_id = :id')->execute(['id' => $test_cmd_id]);
        $db->prepare('DELETE FROM commandes WHERE id = :id')->execute(['id' => $test_cmd_id]);
    }
    if ($test_user_id > 0) {
        if (function_exists('client_comportement_table_exists') && client_comportement_table_exists('client_evenements')) {
            $db->prepare('DELETE FROM client_evenements WHERE user_id = :id')->execute(['id' => $test_user_id]);
        }
        $db->prepare('DELETE FROM favoris WHERE user_id = :id')->execute(['id' => $test_user_id]);
        $db->prepare('DELETE FROM favoris_produit_snapshot WHERE user_id = :id')->execute(['id' => $test_user_id]);
        $db->prepare('DELETE FROM panier_relance WHERE user_id = :id')->execute(['id' => $test_user_id]);
        if (notifications_table_exists('engagement_relance')) {
            $db->prepare('DELETE FROM engagement_relance WHERE user_id = :id')->execute(['id' => $test_user_id]);
        }
        if (client_comportement_table_exists('client_interets_rayon')) {
            $db->prepare('DELETE FROM client_interets_rayon WHERE user_id = :id')->execute(['id' => $test_user_id]);
        }
        $db->prepare('DELETE FROM panier WHERE user_id = :id')->execute(['id' => $test_user_id]);
        if (notifications_table_exists('notification_segment_queue')) {
            $db->prepare('DELETE FROM notification_segment_queue WHERE user_id = :id')->execute(['id' => $test_user_id]);
        }
        $db->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $test_user_id]);
    }
    echo "  Données de test supprimées.\n";
} catch (Throwable $e) {
    echo '  Nettoyage incomplet : ' . $e->getMessage() . "\n";
}

echo "\n=== Résultat : $pass OK / $fail FAIL ===\n";
echo "\nProduction : planifier une seule ligne cron :\n";
echo "  */15 * * * * php " . $root . "/scripts/cron_notifications_bundle.php\n";
exit($fail > 0 ? 1 : 0);
