<?php
/**
 * Relances onboarding vendeur : rappel quotidien la première semaine, rappel J+7, puis hebdomadaire.
 * Aucun compte vendeur n'est jamais supprimé ni désactivé automatiquement.
 */

require_once __DIR__ . '/../models/model_vendeur_onboarding.php';
require_once __DIR__ . '/../models/model_notification_log.php';
require_once __DIR__ . '/notification_dispatch.php';

/**
 * Texte relance activation (aligné sur la popup admin).
 *
 * @return array{title: string, body: string, type: string}
 */
function notification_vendeur_onboarding_activation_copy(array $row): array
{
    $boutique = trim((string) ($row['boutique_nom'] ?? ''));
    if ($boutique === '') {
        $boutique = trim((string) ($row['nom'] ?? 'votre boutique'));
    }
    $title = 'Activez votre compte vendeur';
    $body = 'Pour activer votre boutique « ' . $boutique . ' », il faut ajouter un produit.';
    return [
        'title' => $title,
        'body' => $body,
        'type' => 'vendeur_onboarding_relance',
    ];
}

/**
 * Envoi manuel / test de la relance activation (push + e-mail).
 *
 * @return array{ok: bool, message: string, push: bool, email: bool}
 */
function notification_vendeur_onboarding_send_activation_relance(int $admin_id, bool $force = false): array
{
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return ['ok' => false, 'message' => 'ID invalide.', 'push' => false, 'email' => false];
    }
    require_once __DIR__ . '/../models/model_admin.php';
    $admin = get_admin_by_id($admin_id);
    if (!$admin || normalize_admin_role($admin['role'] ?? '') !== 'vendeur') {
        return ['ok' => false, 'message' => 'Vendeur introuvable.', 'push' => false, 'email' => false];
    }
    if ((string) ($admin['statut'] ?? '') !== 'en_attente_produit') {
        return ['ok' => false, 'message' => 'Le compte n’est pas en attente de premier produit.', 'push' => false, 'email' => false];
    }
    if (vendeur_count_produits_for_admin($admin_id) > 0) {
        return ['ok' => false, 'message' => 'Ce vendeur a déjà un produit.', 'push' => false, 'email' => false];
    }

    vendeur_onboarding_register($admin_id);

    $today = date('Y-m-d');
    $dedupe = 'vendeur_onb_relance_' . $admin_id . '_' . $today;
    if ($force) {
        global $db;
        try {
            $db->prepare('DELETE FROM notification_log WHERE dedupe_key = :k')->execute(['k' => $dedupe]);
        } catch (PDOException $e) {
            error_log('[notification_vendeur_onboarding_send_activation_relance] ' . $e->getMessage());
        }
    } elseif (notification_log_exists($dedupe)) {
        return ['ok' => false, 'message' => 'Relance déjà envoyée aujourd’hui (utilisez --force).', 'push' => false, 'email' => false];
    }

    $copy = notification_vendeur_onboarding_activation_copy($admin);
    $cta = vendeur_onboarding_cta_href();
    $html = '<p>' . htmlspecialchars($copy['body']) . '</p>';
    $disp = notification_dispatch_to_admin(
        $admin_id,
        $copy['title'],
        $copy['body'],
        ['type' => $copy['type'], 'link' => $cta],
        '[COLObanes] ' . $copy['title'],
        $html,
        $cta
    );
    notification_log_insert([
        'type' => $copy['type'],
        'dedupe_key' => $dedupe,
        'admin_id' => $admin_id,
        'channel' => 'push_email',
        'push_sent' => !empty($disp['push']),
        'email_sent' => !empty($disp['email']),
    ]);
    vendeur_onboarding_touch_relance($admin_id);

    return [
        'ok' => true,
        'message' => 'Relance activation envoyée.',
        'push' => !empty($disp['push']),
        'email' => !empty($disp['email']),
    ];
}

/**
 * @return array{relance: int, j7: int}
 */
function notification_worker_vendeur_onboarding(): array
{
    $stats = ['relance' => 0, 'j7' => 0];
    if (!vendeur_onboarding_ensure_schema()) {
        return $stats;
    }

    $cfg = vendeur_onboarding_config();
    $interval_h = max(1, (int) ($cfg['vendeur_onboarding_relance_interval_heures'] ?? 24));
    $j7 = max(1, (int) ($cfg['vendeur_onboarding_avertissement_jours'] ?? 7));
    /* Après le rappel J+7 : une relance par semaine seulement, pour ne pas harceler le vendeur. */
    $interval_after_j7_h = max($interval_h, (int) ($cfg['vendeur_onboarding_relance_interval_apres_j7_heures'] ?? 168));
    $cta = vendeur_onboarding_cta_url();
    $today = date('Y-m-d');

    $rows = vendeur_onboarding_fetch_pending_vendeurs();
    foreach ($rows as $row) {
        $admin_id = (int) ($row['id'] ?? 0);
        if ($admin_id <= 0) {
            continue;
        }
        if (vendeur_count_produits_for_admin($admin_id) > 0) {
            vendeur_onboarding_try_activate_after_produit($admin_id);
            continue;
        }

        $days = vendeur_onboarding_days_since_creation($row);

        $boutique = trim((string) ($row['boutique_nom'] ?? ''));
        if ($boutique === '') {
            $boutique = trim((string) ($row['nom'] ?? 'votre boutique'));
        }

        if ($days >= $j7 && empty($row['avertissement_j7_envoye_at'])) {
            $dedupe = 'vendeur_onb_j7_' . $admin_id;
            if (!notification_log_exists($dedupe)) {
                $title = 'Votre boutique n’est pas encore visible';
                $body = 'Vous n’avez pas encore publié de produit. Ajoutez votre premier produit pour activer « ' . $boutique . ' » sur le marketplace.';
                $html = '<p>' . htmlspecialchars($body) . '</p>';
                $disp = notification_dispatch_to_admin($admin_id, $title, $body, [
                    'type' => 'vendeur_onboarding_j7',
                    'link' => $cta,
                ], '[COLObanes] ' . $title, $html, $cta);
                notification_log_insert([
                    'type' => 'vendeur_onboarding_j7',
                    'dedupe_key' => $dedupe,
                    'admin_id' => $admin_id,
                    'channel' => 'push_email',
                    'push_sent' => !empty($disp['push']),
                    'email_sent' => !empty($disp['email']),
                ]);
                vendeur_onboarding_mark_j7($admin_id);
                $stats['j7']++;
            }
            continue;
        }

        $last = $row['derniere_relance_at'] ?? null;
        $due = true;
        if ($last !== null && $last !== '') {
            $last_ts = strtotime((string) $last);
            $wait_h = $days >= $j7 ? $interval_after_j7_h : $interval_h;
            if ($last_ts !== false && (time() - $last_ts) < ($wait_h * 3600)) {
                $due = false;
            }
        }
        if (!$due) {
            continue;
        }

        $dedupe = 'vendeur_onb_relance_' . $admin_id . '_' . $today;
        if (notification_log_exists($dedupe)) {
            continue;
        }

        $copy = notification_vendeur_onboarding_activation_copy($row);
        $title = $copy['title'];
        $body = $copy['body'];
        $link_cta = vendeur_onboarding_cta_href();
        $html = '<p>' . htmlspecialchars($body) . '</p>';
        $disp = notification_dispatch_to_admin($admin_id, $title, $body, [
            'type' => $copy['type'],
            'link' => $link_cta,
        ], '[COLObanes] ' . $title, $html, $link_cta);
        notification_log_insert([
            'type' => 'vendeur_onboarding_relance',
            'dedupe_key' => $dedupe,
            'admin_id' => $admin_id,
            'channel' => 'push_email',
            'push_sent' => !empty($disp['push']),
            'email_sent' => !empty($disp['email']),
        ]);
        vendeur_onboarding_touch_relance($admin_id);
        $stats['relance']++;
    }

    return $stats;
}
