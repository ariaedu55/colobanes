<?php
/**
 * Suivi comportement client (tous visiteurs, connectés ou non).
 */

require_once __DIR__ . '/catalogue_shuffle.php';
require_once __DIR__ . '/../models/model_client_comportement.php';

function client_tracking_is_admin_request(): bool
{
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if (strpos($path, '/admin/') === 0 || strpos($path, '/super_admin/') === 0) {
        return true;
    }
    return PHP_SAPI === 'cli';
}

function client_tracking_visiteur_key(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    return catalogue_shuffle_visiteur_cle();
}

/**
 * @return array{user_id:?int, visiteur_key:string}
 */
function client_tracking_actor(): array
{
    $user_id = null;
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
        $uid = (int) $_SESSION['user_id'];
        if ($uid > 0) {
            $user_id = $uid;
        }
    }
    $vk = client_tracking_visiteur_key();
    return ['user_id' => $user_id, 'visiteur_key' => $vk];
}

/**
 * Appelé une fois par requête front après session.
 */
function client_tracking_bootstrap(): void
{
    if (client_tracking_is_admin_request()) {
        return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    if (!function_exists('schema_runtime_module_ready')) {
        require_once __DIR__ . '/schema_runtime_cache.php';
    }
    if (!schema_runtime_module_ready('client_comportement')) {
        client_comportement_ensure_tables();
    }
    catalogue_shuffle_visiteur_cle();
    $actor = client_tracking_actor();
    if ($actor['visiteur_key'] === '' && $actor['user_id'] === null) {
        return;
    }
    if (client_comportement_visite_site_logged_today($actor['user_id'], $actor['visiteur_key'])) {
        return;
    }
    $ok = client_comportement_insert_event([
        'type' => 'visite_site',
        'user_id' => $actor['user_id'],
        'visiteur_key' => $actor['visiteur_key'],
        'poids' => 0,
    ]);
    if ($ok && $actor['user_id'] !== null && (int) $actor['user_id'] > 0) {
        if (!function_exists('notification_engagement_inactif_reset')) {
            require_once __DIR__ . '/../services/notification_engagement_inactif.php';
        }
        notification_engagement_inactif_reset((int) $actor['user_id']);
    }
}

/**
 * @param array<string, mixed> $ctx produit_id, categorie_id, categorie_generale_id, terme_recherche, commande_id, source, produit (array)
 */
function client_tracking_log_event(string $type, array $ctx = []): bool
{
    $type = trim($type);
    if ($type === '') {
        return false;
    }
    $actor = client_tracking_actor();
    if ($actor['user_id'] === null && $actor['visiteur_key'] === '') {
        return false;
    }

    $produit_id = isset($ctx['produit_id']) ? (int) $ctx['produit_id'] : 0;
    if ($produit_id <= 0 && isset($ctx['produit']) && is_array($ctx['produit'])) {
        $produit_id = (int) ($ctx['produit']['id'] ?? 0);
    }

    $categorie_generale_id = isset($ctx['categorie_generale_id']) ? (int) $ctx['categorie_generale_id'] : 0;
    $categorie_id = isset($ctx['categorie_id']) ? (int) $ctx['categorie_id'] : 0;
    if ($categorie_generale_id <= 0 && $produit_id > 0) {
        $categorie_generale_id = client_comportement_resolve_generale_id_from_produit(
            isset($ctx['produit']) && is_array($ctx['produit']) ? $ctx['produit'] : $produit_id
        );
    }
    if ($categorie_generale_id <= 0 && $categorie_id > 0) {
        if (!function_exists('get_categorie_by_id')) {
            require_once __DIR__ . '/../models/model_categories.php';
        }
        $cat_row = get_categorie_by_id($categorie_id);
        if (is_array($cat_row)) {
            $categorie_generale_id = (int) ($cat_row['categorie_generale_id'] ?? 0);
        }
    }

    if (in_array($type, ['vue_produit', 'clic_produit'], true) && $produit_id > 0) {
        if (client_comportement_recent_duplicate($type, $actor['user_id'], $actor['visiteur_key'], $produit_id, 15)) {
            return false;
        }
    }

    $weights = client_comportement_event_weights();
    $poids = (int) ($weights[$type] ?? 0);

    $ok = client_comportement_insert_event([
        'type' => $type,
        'user_id' => $actor['user_id'],
        'visiteur_key' => $actor['visiteur_key'],
        'produit_id' => $produit_id > 0 ? $produit_id : null,
        'categorie_id' => $categorie_id > 0 ? $categorie_id : null,
        'categorie_generale_id' => $categorie_generale_id > 0 ? $categorie_generale_id : null,
        'terme_recherche' => isset($ctx['terme_recherche']) ? (string) $ctx['terme_recherche'] : null,
        'commande_id' => isset($ctx['commande_id']) ? (int) $ctx['commande_id'] : null,
        'source' => isset($ctx['source']) ? (string) $ctx['source'] : null,
        'poids' => $poids,
    ]);

    if ($ok && $categorie_generale_id > 0 && $poids !== 0) {
        client_comportement_apply_rayon_score($actor['user_id'], $actor['visiteur_key'], $categorie_generale_id, (float) $poids);
    }

    if ($ok && $type === 'recherche') {
        $terme = isset($ctx['terme_recherche']) ? trim((string) $ctx['terme_recherche']) : '';
        if ($terme !== '') {
            client_comportement_apply_recherche_rayon_scores($actor['user_id'], $actor['visiteur_key'], $terme);
        }
    }

    if ($ok && $actor['user_id'] !== null && (int) $actor['user_id'] > 0) {
        if (!function_exists('notification_engagement_inactif_reset')) {
            require_once __DIR__ . '/../services/notification_engagement_inactif.php';
        }
        notification_engagement_inactif_reset((int) $actor['user_id']);
    }

    return $ok;
}

function client_tracking_merge_after_login(int $user_id): void
{
    $user_id = (int) $user_id;
    if ($user_id <= 0 || session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $vk = client_tracking_visiteur_key();
    if ($vk === '') {
        return;
    }
    client_comportement_merge_visiteur_vers_user($user_id, $vk);
}
