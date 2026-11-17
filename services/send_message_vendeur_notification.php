<?php
/**
 * Notifications push — messagerie vendeur → client
 * Programmation procédurale uniquement
 */

/**
 * @param int $conversation_id
 * @param int $message_id
 * @param string $type_message
 * @param string $contenu
 * @return void
 */
function send_message_vendeur_push_to_client($conversation_id, $message_id, $type_message, $contenu)
{
    $conversation_id = (int) $conversation_id;
    $message_id = (int) $message_id;
    if ($conversation_id <= 0 || $message_id <= 0) {
        return;
    }

    require_once __DIR__ . '/../models/model_messages_vendeur.php';
    if (!messages_vendeur_tables_exist()) {
        return;
    }

    $conv = conversation_vendeur_get_by_id($conversation_id);
    if (!$conv) {
        return;
    }

    $user_id = (int) ($conv['user_id'] ?? 0);
    if ($user_id <= 0) {
        return;
    }

    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';

    $tokens = get_fcm_tokens_by_user($user_id);
    if (empty($tokens)) {
        return;
    }

    $produit_nom = trim((string) ($conv['produit_nom'] ?? ''));
    if ($produit_nom === '') {
        $produit_nom = 'Produit';
    }

    $vendeur = trim((string) ($conv['vendeur_boutique_nom'] ?? ''));
    if ($vendeur === '') {
        $vendeur = trim((string) (($conv['vendeur_prenom'] ?? '') . ' ' . ($conv['vendeur_nom'] ?? '')));
    }
    if ($vendeur === '') {
        $vendeur = 'Le vendeur';
    }

    $contenu = trim((string) $contenu);
    $type_message = in_array($type_message, ['texte', 'offre_prix', 'systeme'], true) ? $type_message : 'texte';

    if ($type_message === 'offre_prix') {
        $title = 'Nouvelle offre du vendeur';
        $body = $vendeur . ' vous a envoyé une proposition pour « ' . $produit_nom . ' ».';
    } elseif ($type_message === 'systeme') {
        $title = 'Mise à jour négociation';
        $body = $contenu !== '' ? $contenu : ($vendeur . ' a mis à jour votre négociation.');
    } else {
        $title = 'Nouveau message';
        $preview = $contenu !== '' ? $contenu : 'Vous avez reçu un message.';
        if (mb_strlen($preview) > 140) {
            $preview = mb_substr($preview, 0, 137) . '…';
        }
        $body = $vendeur . ' : ' . $preview;
    }

    $link = '/user/messages.php?conv=' . $conversation_id;
    $link_abs = firebase_absolute_url($link);

    $data = [
        'type' => 'message_vendeur',
        'conversation_id' => (string) $conversation_id,
        'message_id' => (string) $message_id,
        'link' => $link,
        'redirect_url' => $link_abs,
        'url' => $link_abs,
        'tag' => 'msg-conv-' . $conversation_id,
        'unread_delta' => '1',
        'produit_id' => (string) (int) ($conv['produit_id'] ?? 0),
    ];

    firebase_send_notification($tokens, $title, $body, $data);
}

/**
 * @param int $conversation_id
 * @param int $message_id
 * @param string $type_message
 * @param string $contenu
 * @return void
 */
function message_vendeur_try_notify_client($conversation_id, $message_id, $type_message, $contenu)
{
    try {
        send_message_vendeur_push_to_client($conversation_id, $message_id, $type_message, $contenu);
    } catch (Throwable $e) {
        error_log('[message_vendeur_push] ' . $e->getMessage());
    }
}

/**
 * @param int $conversation_id
 * @param int $message_id
 * @param string $type_message
 * @param string $contenu
 * @return void
 */
function send_message_vendeur_push_to_vendeur($conversation_id, $message_id, $type_message, $contenu)
{
    $conversation_id = (int) $conversation_id;
    $message_id = (int) $message_id;
    if ($conversation_id <= 0 || $message_id <= 0) {
        return;
    }

    require_once __DIR__ . '/../models/model_messages_vendeur.php';
    if (!messages_vendeur_tables_exist()) {
        return;
    }

    $conv = conversation_vendeur_get_by_id($conversation_id);
    if (!$conv) {
        return;
    }

    $admin_id = (int) ($conv['admin_id'] ?? 0);
    if ($admin_id <= 0) {
        return;
    }

    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';

    $tokens = get_fcm_tokens_by_admin($admin_id);
    if (empty($tokens)) {
        return;
    }

    $client_nom = trim((string) (($conv['client_prenom'] ?? '') . ' ' . ($conv['client_nom'] ?? '')));
    if ($client_nom === '') {
        $client_nom = 'Un client';
    }

    $produit_nom = trim((string) ($conv['produit_nom'] ?? ''));
    $contenu = trim((string) $contenu);
    $type_message = in_array($type_message, ['texte', 'offre_prix', 'systeme'], true) ? $type_message : 'texte';

    if ($type_message === 'offre_prix') {
        $title = 'Nouvelle offre client';
        $body = $client_nom . ' propose un prix' . ($produit_nom !== '' ? ' pour « ' . $produit_nom . ' ».' : '.');
    } elseif ($type_message === 'systeme') {
        $title = 'Message client';
        $body = $contenu !== '' ? $contenu : ($client_nom . ' a envoyé une mise à jour.');
    } else {
        $title = 'Nouveau message client';
        $preview = $contenu !== '' ? $contenu : 'Nouveau message reçu.';
        if (mb_strlen($preview) > 140) {
            $preview = mb_substr($preview, 0, 137) . '…';
        }
        $body = $client_nom . ' : ' . $preview;
    }

    $link = '/admin/messages.php?conv=' . $conversation_id;
    $link_abs = firebase_absolute_url($link);

    $data = [
        'type' => 'message_vendeur',
        'conversation_id' => (string) $conversation_id,
        'message_id' => (string) $message_id,
        'link' => $link,
        'redirect_url' => $link_abs,
        'url' => $link_abs,
        'tag' => 'msg-conv-admin-' . $conversation_id,
        'unread_delta' => '1',
        'produit_id' => (string) (int) ($conv['produit_id'] ?? 0),
    ];

    firebase_send_notification($tokens, $title, $body, $data);
}

/**
 * @param int $conversation_id
 * @param int $message_id
 * @param string $type_message
 * @param string $contenu
 * @return void
 */
function message_vendeur_try_notify_vendeur($conversation_id, $message_id, $type_message, $contenu)
{
    try {
        send_message_vendeur_push_to_vendeur($conversation_id, $message_id, $type_message, $contenu);
    } catch (Throwable $e) {
        error_log('[message_vendeur_push_vendeur] ' . $e->getMessage());
    }
}
