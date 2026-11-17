<?php
/**
 * API poll — liste conversations (inbox)
 * GET ?filtre=tout|non_lus|negociations|achats
 */

require_once __DIR__ . '/../includes/messages_vendeur_api_bootstrap.php';

$auth = messages_vendeur_api_resolve_auth();
if ($auth === null) {
    messages_vendeur_api_json_error('Non authentifié.', 401);
}

if (!messages_vendeur_tables_exist()) {
    messages_vendeur_api_json_error('Messagerie indisponible.', 503);
}

$filtre = isset($_GET['filtre']) ? trim((string) $_GET['filtre']) : 'tout';
$rows = [];

if ($auth['role'] === 'client') {
    if (!in_array($filtre, ['tout', 'non_lus', 'negociations', 'achats'], true)) {
        $filtre = 'tout';
    }
    conversation_vendeur_sync_achats_from_commandes((int) $auth['id']);
    $rows = conversation_vendeur_list_by_user((int) $auth['id'], 80);
    $rows = conversation_vendeur_filter_inbox_rows($rows, $filtre);
} else {
    if (!in_array($filtre, ['tout', 'non_lus', 'negociations'], true)) {
        $filtre = 'tout';
    }
    $rows = conversation_vendeur_list_by_admin((int) $auth['id'], 80);
    if ($filtre === 'non_lus') {
        $rows = array_values(array_filter($rows, function ($c) {
            return (int) ($c['nb_non_lus'] ?? 0) > 0;
        }));
    } elseif ($filtre === 'negociations') {
        $rows = array_values(array_filter($rows, function ($c) {
            return (int) ($c['produit_id'] ?? 0) > 0;
        }));
    }
}

$conversations = [];
foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }
    $conversations[] = messages_vendeur_inbox_row_for_api($row, $auth['role']);
}

$total_unread = 0;
if ($auth['role'] === 'client') {
    $total_unread = message_vendeur_count_unread_for_client((int) $auth['id']);
} else {
    $total_unread = message_vendeur_count_unread_for_vendeur((int) $auth['id']);
}

messages_vendeur_api_json_ok([
    'role' => $auth['role'],
    'filtre' => $filtre,
    'total_unread' => $total_unread,
    'conversations' => $conversations,
]);
