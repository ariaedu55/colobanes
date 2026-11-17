<?php
/**
 * Notifications push aux clients abonnés à une boutique
 * Programmation procédurale uniquement
 */

/**
 * @param int $admin_id
 * @param string $event nouveau_produit|promotion|modification
 * @param string $produit_nom
 * @param int $produit_id
 * @return void
 */
function send_boutique_abonnes_push($admin_id, $event, $produit_nom = '', $produit_id = 0)
{
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return;
    }

    if (!in_array($event, ['nouveau_produit', 'promotion', 'modification'], true)) {
        return;
    }

    require_once __DIR__ . '/../models/model_boutique_abonnements.php';
    if (!function_exists('boutique_abonnements_table_exists') || !boutique_abonnements_table_exists()) {
        return;
    }

    require_once __DIR__ . '/../models/model_admin.php';
    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';

    $vendeur = get_admin_by_id($admin_id);
    if (!$vendeur || ($vendeur['role'] ?? '') !== 'vendeur' || ($vendeur['statut'] ?? '') !== 'actif') {
        return;
    }

    $slug = trim((string) ($vendeur['boutique_slug'] ?? ''));
    if ($slug === '') {
        return;
    }

    $boutique_nom = trim((string) ($vendeur['boutique_nom'] ?? ''));
    if ($boutique_nom === '') {
        $boutique_nom = trim((string) ($vendeur['nom'] ?? 'Boutique'));
    }

    $user_ids = boutique_abonnements_subscriber_user_ids($admin_id);
    if (empty($user_ids)) {
        return;
    }

    $produit_nom = trim((string) $produit_nom);
    if ($produit_nom === '') {
        $produit_nom = 'un produit';
    }

    if ($event === 'nouveau_produit') {
        $title = $boutique_nom . ' — Nouveau produit';
        $body = $produit_nom . ' vient d\'être publié.';
    } elseif ($event === 'promotion') {
        $title = 'Promo chez ' . $boutique_nom;
        $body = $produit_nom . ' est en promotion.';
    } else {
        $title = $boutique_nom . ' a mis à jour un produit';
        $body = 'Découvrez ' . $produit_nom . '.';
    }

    require_once __DIR__ . '/../includes/marketplace_helpers.php';
    $link_path = boutique_vitrine_entry_href($slug);
    if (strpos($link_path, 'http') !== 0) {
        $link_abs = firebase_absolute_url($link_path);
    } else {
        $link_abs = $link_path;
    }

    $data = [
        'link' => $link_path,
        'redirect_url' => $link_abs,
        'url' => $link_abs,
        'type' => 'boutique_abonnement',
        'event' => $event,
        'boutique_slug' => $slug,
        'produit_id' => (string) (int) $produit_id,
        'tag' => 'boutique-' . $slug . '-' . $event,
    ];

    require_once __DIR__ . '/notification_dispatch.php';
    require_once __DIR__ . '/../models/model_notification_log.php';

    $email_html = '<p>' . htmlspecialchars($body) . '</p>';
    if ($produit_id > 0) {
        $email_html .= '<p><strong>Produit :</strong> ' . htmlspecialchars($produit_nom) . '</p>';
    }

    foreach ($user_ids as $uid) {
        $uid = (int) $uid;
        if ($uid <= 0) {
            continue;
        }
        $dedupe = 'boutique_abo:' . $admin_id . ':' . $event . ':' . $produit_id . ':' . $uid;
        if (notification_log_exists($dedupe)) {
            continue;
        }
        $disp = notification_dispatch_to_user(
            $uid,
            $title,
            $body,
            $data,
            '',
            '[COLObanes] ' . $title,
            $email_html,
            $link_abs,
            ['type' => 'boutique_abonnement', 'event' => $event, 'produit_id' => $produit_id]
        );
        if ($disp['push'] || $disp['email']) {
            notification_log_insert([
                'type' => 'boutique_abonnement',
                'dedupe_key' => $dedupe,
                'user_id' => $uid,
                'produit_id' => $produit_id > 0 ? $produit_id : null,
                'push_sent' => $disp['push'],
                'email_sent' => $disp['email'],
            ]);
        }
    }
}

/**
 * Indique si le produit est visible sur le catalogue (notification autorisée)
 *
 * @param string $statut
 * @return bool
 */
function boutique_abonnement_produit_visible_catalogue($statut)
{
    return ($statut ?? '') === 'actif';
}

/**
 * Détecte un changement de promotion
 *
 * @param array<string, mixed> $produit_avant
 * @param float|null $nouveau_prix_promo
 * @return bool
 */
function boutique_abonnement_promo_a_change($produit_avant, $nouveau_prix_promo)
{
    $old = isset($produit_avant['prix_promotion']) && $produit_avant['prix_promotion'] !== '' && $produit_avant['prix_promotion'] !== null
        ? (float) $produit_avant['prix_promotion'] : null;
    $new = ($nouveau_prix_promo !== null && $nouveau_prix_promo > 0) ? (float) $nouveau_prix_promo : null;

    if ($new === null) {
        return false;
    }

    return $old !== $new;
}

/**
 * Changements significatifs (hors stock seul)
 *
 * @param array<string, mixed> $produit_avant
 * @param array<string, mixed> $data_apres
 * @return bool
 */
function boutique_abonnement_modification_significative($produit_avant, $data_apres)
{
    $checks = [
        trim((string) ($produit_avant['nom'] ?? '')) !== trim((string) ($data_apres['nom'] ?? '')),
        (float) ($produit_avant['prix'] ?? 0) !== (float) ($data_apres['prix'] ?? 0),
        trim((string) ($produit_avant['description'] ?? '')) !== trim((string) ($data_apres['description'] ?? '')),
        trim((string) ($produit_avant['image_principale'] ?? '')) !== trim((string) ($data_apres['image_principale'] ?? '')),
        trim((string) ($produit_avant['images'] ?? '')) !== trim((string) ($data_apres['images'] ?? '')),
    ];

    return in_array(true, $checks, true);
}

/**
 * @param int $admin_id
 * @param string $event
 * @param string $produit_nom
 * @param int $produit_id
 * @return void
 */
function boutique_abonnement_try_notify($admin_id, $event, $produit_nom, $produit_id)
{
    try {
        send_boutique_abonnes_push($admin_id, $event, $produit_nom, $produit_id);
    } catch (Throwable $e) {
        error_log('[boutique_abonnement_push] ' . $e->getMessage());
    }
}
