<?php
/**
 * Action POST — messagerie vendeur (admin)
 */
require_once __DIR__ . '/includes/require_admin_session.php';
require_once __DIR__ . '/../controllers/controller_messages_vendeur.php';

process_message_vendeur_admin_send();
