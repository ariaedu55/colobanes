<?php
/**
 * Notifications push — négociations de prix
 * Programmation procédurale uniquement
 */

/**
 * @param int $negotiation_id
 * @param string $event nouvelle_offre|offre_acceptee|contre_proposee|offre_refusee
 * @return void
 */
function send_prix_negociation_push($negotiation_id, $event)
{
    $negotiation_id = (int) $negotiation_id;
    if ($negotiation_id <= 0) {
        return;
    }

    if (!in_array($event, ['nouvelle_offre', 'offre_acceptee', 'contre_proposee', 'offre_refusee'], true)) {
        return;
    }

    require_once __DIR__ . '/../models/model_prix_negociations.php';
    if (!prix_negociations_table_exists()) {
        return;
    }

    require_once __DIR__ . '/../models/model_fcm.php';
    require_once __DIR__ . '/firebase_push.php';

    $row = prix_negociation_get_by_id($negotiation_id);
    if (!$row) {
        return;
    }

    $produit_nom = trim((string) ($row['produit_nom'] ?? 'Produit'));
    $client_nom = trim((string) (($row['user_prenom'] ?? '') . ' ' . ($row['user_nom'] ?? '')));
    if ($client_nom === '') {
        $client_nom = 'Un client';
    }

    $conv_id = 0;
    if (file_exists(__DIR__ . '/../models/model_messages_vendeur.php')) {
        require_once __DIR__ . '/../models/model_messages_vendeur.php';
        if (function_exists('conversation_vendeur_find_id_by_parties')) {
            $conv_id = conversation_vendeur_find_id_by_parties(
                (int) ($row['user_id'] ?? 0),
                (int) ($row['admin_id'] ?? 0),
                (int) ($row['produit_id'] ?? 0)
            );
        }
    }

    if ($event === 'nouvelle_offre') {
        $admin_id = (int) ($row['admin_id'] ?? 0);
        if ($admin_id <= 0) {
            return;
        }
        $tokens = get_fcm_tokens_by_admin($admin_id);
        if (empty($tokens)) {
            return;
        }
        $prix = number_format((float) ($row['prix_propose_client'] ?? 0), 0, ',', ' ');
        $title = 'Nouvelle offre de prix';
        $body = $client_nom . ' propose ' . $prix . ' FCFA pour « ' . $produit_nom . ' ».';
        $link = $conv_id > 0 ? '/admin/messages.php?conv=' . $conv_id : '/admin/messages.php';
    } else {
        $user_id = (int) ($row['user_id'] ?? 0);
        if ($user_id <= 0) {
            return;
        }
        $tokens = get_fcm_tokens_by_user($user_id);
        if (empty($tokens)) {
            return;
        }
        $link = $conv_id > 0 ? '/user/messages.php?conv=' . $conv_id : '/user/messages.php';
        if ($event === 'offre_acceptee') {
            $title = 'Offre acceptée';
            $body = 'Le vendeur a accepté votre offre pour « ' . $produit_nom . ' ».';
        } elseif ($event === 'contre_proposee') {
            $prix = number_format((float) ($row['prix_contre_vendeur'] ?? 0), 0, ',', ' ');
            $title = 'Contre-proposition reçue';
            $body = 'Le vendeur propose ' . $prix . ' FCFA pour « ' . $produit_nom . ' ».';
        } else {
            $title = 'Offre refusée';
            $body = 'Votre offre pour « ' . $produit_nom . ' » a été refusée.';
        }
    }

    $link_abs = firebase_absolute_url($link);
    $data = [
        'link' => $link,
        'redirect_url' => $link_abs,
        'url' => $link_abs,
        'type' => 'prix_negociation',
        'event' => $event,
        'negotiation_id' => (string) $negotiation_id,
        'conversation_id' => $conv_id > 0 ? (string) $conv_id : '',
        'produit_id' => (string) (int) ($row['produit_id'] ?? 0),
        'tag' => 'prix-neg-' . $negotiation_id . '-' . $event,
        'unread_delta' => ($event !== 'nouvelle_offre') ? '1' : '0',
    ];

    firebase_send_notification($tokens, $title, $body, $data);
}

/**
 * @param int $negotiation_id
 * @param string $event
 * @return void
 */
function prix_negociation_try_notify_vendor($negotiation_id, $event)
{
    try {
        send_prix_negociation_push($negotiation_id, $event);
    } catch (Throwable $e) {
        error_log('[prix_negociation_push] ' . $e->getMessage());
    }
}

/**
 * @param int $negotiation_id
 * @param string $event
 * @return void
 */
function prix_negociation_try_notify_client($negotiation_id, $event)
{
    try {
        send_prix_negociation_push($negotiation_id, $event);
    } catch (Throwable $e) {
        error_log('[prix_negociation_push] ' . $e->getMessage());
    }
}
