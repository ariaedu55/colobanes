<?php
/**
 * API poll — fil de conversation (JSON ou HTML fragment)
 * GET ?conv={id}&since_id={id}&format=json|html
 */

require_once __DIR__ . '/../includes/messages_vendeur_api_bootstrap.php';

$auth = messages_vendeur_api_resolve_auth();
if ($auth === null) {
    messages_vendeur_api_json_error('Non authentifié.', 401);
}

if (!messages_vendeur_tables_exist()) {
    messages_vendeur_api_json_error('Messagerie indisponible.', 503);
}

$conv_id = isset($_GET['conv']) ? (int) $_GET['conv'] : 0;
$since_id = isset($_GET['since_id']) ? (int) $_GET['since_id'] : 0;
$format = isset($_GET['format']) ? trim((string) $_GET['format']) : 'json';
if (!in_array($format, ['json', 'html'], true)) {
    $format = 'json';
}

$conv = messages_vendeur_api_load_conv($conv_id, $auth);
if ($conv === null) {
    messages_vendeur_api_json_error('Conversation introuvable.', 404);
}

$viewer_type = ($auth['role'] === 'vendeur') ? 'vendeur' : 'client';

$new_messages = message_vendeur_list_since($conv_id, $since_id, $viewer_type);
$all_for_presence = message_vendeur_list_by_conversation($conv_id, 250, $viewer_type);
$last_id = message_vendeur_last_id_for_conversation($conv_id);
$presence = messages_vendeur_presence_for_api($conv, $all_for_presence, $viewer_type);
$thread_state = messages_vendeur_thread_state_for_api($conv, $viewer_type);

$payload = [
    'conv_id' => $conv_id,
    'since_id' => $since_id,
    'last_id' => $last_id,
    'count' => count($new_messages),
    'presence' => $presence,
    'thread_state' => $thread_state,
];

if ($format === 'html') {
    require_once __DIR__ . '/../includes/image_optimizer.php';
    if (!function_exists('boutique_adresse_publique')) {
        require_once __DIR__ . '/../includes/boutique_vendeur_display.php';
    }

    $messages = $new_messages;
    $active_conv = $conv;
    $messages_page_url = app_url($auth['role'] === 'vendeur' ? '/admin/messages.php' : '/user/messages.php');
    $messages_compose_redirect = $messages_page_url . '?conv=' . $conv_id;
    $last_date_key = message_vendeur_last_date_key_before($conv_id, $since_id);
    $msg_render_new_anim = true;
    $msg_render_data_attrs = true;
    $msg_viewer_role = $viewer_type;

    ob_start();
    if ($viewer_type === 'client') {
        include __DIR__ . '/../includes/partials/messages_vendeur_thread_messages_client.php';
    } else {
        include __DIR__ . '/../includes/partials/messages_vendeur_thread_messages_admin.php';
    }
    $payload['html'] = ob_get_clean();
    $payload['messages'] = [];
} else {
    $payload['messages'] = array_map(function ($row) {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'type_message' => (string) ($row['type_message'] ?? 'texte'),
            'expediteur_type' => (string) ($row['expediteur_type'] ?? ''),
            'contenu' => (string) ($row['contenu'] ?? ''),
            'date_creation' => (string) ($row['date_creation'] ?? ''),
        ];
    }, $new_messages);
    $payload['html'] = '';
}

messages_vendeur_api_json_ok($payload);
