<?php
/**
 * API — marquer une conversation comme lue
 * GET ?conv={id}
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
$conv = messages_vendeur_api_load_conv($conv_id, $auth);
if ($conv === null) {
    messages_vendeur_api_json_error('Conversation introuvable.', 404);
}

if ($auth['role'] === 'client') {
    message_vendeur_mark_read_by_client($conv_id, (int) $auth['id']);
    $total_unread = message_vendeur_count_unread_for_client((int) $auth['id']);
} else {
    message_vendeur_mark_read_by_vendeur($conv_id, (int) $auth['id']);
    $total_unread = message_vendeur_count_unread_for_vendeur((int) $auth['id']);
}

messages_vendeur_api_json_ok([
    'conv_id' => $conv_id,
    'total_unread' => $total_unread,
]);
