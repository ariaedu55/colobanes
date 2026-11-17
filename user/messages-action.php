<?php
/**
 * Action POST — messagerie client
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../controllers/controller_messages_vendeur.php';

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : 'send';

if ($action === 'propose_prix') {
    process_message_vendeur_client_propose_prix();
}

if (in_array($action, ['bloquer_vendeur', 'signaler_vendeur', 'supprimer_conversation'], true)) {
    process_message_vendeur_client_conversation_menu();
}

process_message_vendeur_client_send();
