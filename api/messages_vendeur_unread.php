<?php
/**
 * API poll — compteur messages non lus (badges nav)
 */

require_once __DIR__ . '/../includes/messages_vendeur_api_bootstrap.php';

$auth = messages_vendeur_api_resolve_auth();
if ($auth === null) {
    messages_vendeur_api_json_error('Non authentifié.', 401);
}

if (!messages_vendeur_tables_exist()) {
    messages_vendeur_api_json_ok([
        'role' => 'guest',
        'count' => 0,
    ]);
}

$count = 0;
if ($auth['role'] === 'client') {
    $count = message_vendeur_count_unread_for_client((int) $auth['id']);
} else {
    $count = message_vendeur_count_unread_for_vendeur((int) $auth['id']);
}

messages_vendeur_api_json_ok([
    'role' => $auth['role'],
    'count' => $count,
]);
