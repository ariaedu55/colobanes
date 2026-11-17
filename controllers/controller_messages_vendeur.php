<?php
/**
 * Contrôleur — messagerie client ↔ vendeur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_messages_vendeur.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../includes/flash_toast.php';

if (!function_exists('app_url')) {
    require_once __DIR__ . '/../includes/app_base.php';
}

/**
 * @param string $redirect
 * @param string $fallback
 * @return string
 */
function messages_vendeur_safe_redirect($redirect, $fallback = '')
{
    if ($fallback === '') {
        $fallback = app_url('/user/messages.php');
    }
    $redirect = trim((string) $redirect);
    if ($redirect === '') {
        return $fallback;
    }
    if (strpos($redirect, '//') !== false || preg_match('#^https?://#i', $redirect)) {
        return $fallback;
    }
    if ($redirect[0] !== '/') {
        $redirect = '/' . $redirect;
    }
    return app_url($redirect);
}

/**
 * Ouvre ou crée une conversation produit et redirige vers le fil
 *
 * @return never
 */
function messages_vendeur_open_product_conversation()
{
    if (empty($_SESSION['user_id'])) {
        $produit_id = isset($_GET['produit_id']) ? (int) $_GET['produit_id'] : 0;
        $target = app_url('/user/messages.php?produit_id=' . $produit_id . '&negocier=1');
        header('Location: ' . app_url('/choix-connexion.php?redirect=' . rawurlencode($target)));
        exit;
    }

    if (!messages_vendeur_tables_exist()) {
        flash_toast_push('error', 'La messagerie est temporairement indisponible.');
        header('Location: ' . app_url('/index.php'));
        exit;
    }

    $user_id = (int) $_SESSION['user_id'];
    $produit_id = isset($_GET['produit_id']) ? (int) $_GET['produit_id'] : 0;
    $negocier = isset($_GET['negocier']) && (string) $_GET['negocier'] === '1';

    if ($produit_id <= 0) {
        header('Location: ' . app_url('/user/messages.php'));
        exit;
    }

    $produit = get_produit_by_id($produit_id);
    if (!$produit || !produit_est_visible_client($produit['statut'] ?? '')) {
        flash_toast_push('error', 'Ce produit n\'est pas disponible.');
        header('Location: ' . app_url('/index.php'));
        exit;
    }

    $admin_id = (int) ($produit['admin_id'] ?? 0);
    if ($admin_id <= 0) {
        flash_toast_push('error', 'Vendeur introuvable pour ce produit.');
        header('Location: ' . app_url('/produit.php?id=' . $produit_id));
        exit;
    }

    $sujet = 'Discussion — ' . trim((string) ($produit['nom'] ?? 'Produit'));
    $conv = conversation_vendeur_get_or_create($user_id, $admin_id, $produit_id, $sujet);
    if (empty($conv['success']) || empty($conv['id'])) {
        flash_toast_push('error', $conv['message'] ?? 'Impossible d\'ouvrir la conversation.');
        header('Location: ' . app_url('/produit.php?id=' . $produit_id));
        exit;
    }

    $conv_id = (int) $conv['id'];

    if (!empty($conv['created']) || !conversation_vendeur_has_messages($conv_id)) {
        $prix = conversation_vendeur_produit_prix_affiche($produit);
        $prix_fmt = number_format($prix, 0, ',', ' ');
        $intro = $negocier
            ? 'Bonjour, je souhaite négocier le prix de « ' . trim((string) ($produit['nom'] ?? '')) . ' » (prix affiché : ' . $prix_fmt . ' FCFA).'
            : 'Discussion ouverte à propos de « ' . trim((string) ($produit['nom'] ?? '')) . ' ».';
        message_vendeur_add($conv_id, 'client', $user_id, $intro, 'systeme', [
            'produit_id' => $produit_id,
            'prix_affiche' => $prix,
        ]);
    }

    $url = app_url('/user/messages.php?conv=' . $conv_id);
    if ($negocier) {
        $url .= '&negocier=1';
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Client — envoyer un message texte
 *
 * @return never
 */
function process_message_vendeur_client_send()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_redirect_safe(app_url('/user/messages.php'));
    }

    if (empty($_SESSION['user_id'])) {
        flash_toast_push('error', 'Connectez-vous pour envoyer un message.');
        http_redirect_safe(app_url('/choix-connexion.php'));
    }

    if (!messages_vendeur_tables_exist()) {
        flash_toast_push('error', 'Messagerie indisponible.');
        http_redirect_safe(app_url('/user/messages.php'));
    }

    $user_id = (int) $_SESSION['user_id'];
    $conv_id = isset($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
    $contenu = isset($_POST['contenu']) ? trim((string) $_POST['contenu']) : '';
    $redirect = messages_vendeur_safe_redirect($_POST['redirect'] ?? '');

    $csrf = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!messages_vendeur_client_csrf_valid($csrf)) {
        flash_toast_push('error', 'Session expirée. Réessayez.');
        http_redirect_safe($redirect !== '' ? $redirect : app_url('/user/messages.php?conv=' . $conv_id));
    }

    if ($conv_id <= 0 || !conversation_vendeur_belongs_to_user($conv_id, $user_id)) {
        flash_toast_push('error', 'Conversation introuvable.');
        http_redirect_safe(app_url('/user/messages.php'));
    }

    if ($contenu === '') {
        flash_toast_push('error', 'Écrivez un message avant d\'envoyer.');
        http_redirect_safe($redirect !== '' ? $redirect : app_url('/user/messages.php?conv=' . $conv_id));
    }

    if (mb_strlen($contenu) > 4000) {
        flash_toast_push('error', 'Message trop long (4000 caractères max).');
        http_redirect_safe($redirect !== '' ? $redirect : app_url('/user/messages.php?conv=' . $conv_id));
    }

    $block_reason = messages_vendeur_blocked_reason($contenu);
    if ($block_reason !== null) {
        $res = message_vendeur_add_contact_blocked($conv_id, 'client', $user_id, $contenu, $block_reason);
        if (empty($res['success'])) {
            flash_toast_push('error', $res['message'] ?? 'Envoi impossible.');
        }
        http_redirect_safe($redirect !== '' ? $redirect : app_url('/user/messages.php?conv=' . $conv_id));
    }

    $res = message_vendeur_add($conv_id, 'client', $user_id, $contenu, 'texte');
    if (empty($res['success'])) {
        flash_toast_push('error', $res['message'] ?? 'Envoi impossible.');
    } else {
        flash_toast_push('success', 'Message envoyé.');
    }

    http_redirect_safe($redirect !== '' ? $redirect : app_url('/user/messages.php?conv=' . $conv_id));
}

/**
 * Vendeur — envoyer un message texte
 *
 * @return never
 */
function process_message_vendeur_admin_send()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_redirect_safe(app_url('/admin/messages.php'));
    }

    if (empty($_SESSION['admin_id'])) {
        flash_toast_push('error', 'Session expirée.');
        http_redirect_safe(app_url('/choix-connexion.php'));
    }

    if (!messages_vendeur_tables_exist()) {
        flash_toast_push('error', 'Messagerie indisponible.');
        http_redirect_safe(app_url('/admin/messages.php'));
    }

    $admin_id = (int) $_SESSION['admin_id'];
    $admin_role = function_exists('admin_normalize_role_for_route')
        ? admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin')
        : (string) ($_SESSION['admin_role'] ?? 'admin');
    $is_support = in_array($admin_role, ['admin', 'plateforme'], true);
    $conv_id = isset($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
    $contenu = isset($_POST['contenu']) ? trim((string) $_POST['contenu']) : '';
    $admin_fallback = app_url('/admin/messages.php' . ($conv_id > 0 ? '?conv=' . $conv_id : ''));
    $redirect = messages_vendeur_safe_redirect($_POST['redirect'] ?? '', $admin_fallback);

    $csrf = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!messages_vendeur_admin_csrf_valid($csrf)) {
        flash_toast_push('error', 'Session expirée. Réessayez.');
        http_redirect_safe($redirect);
    }

    $conv_row = ($conv_id > 0) ? conversation_vendeur_get_by_id($conv_id) : null;
    $can_reply = $conv_row && (
        $is_support || conversation_vendeur_belongs_to_admin($conv_id, $admin_id)
    );
    if (!$can_reply) {
        flash_toast_push('error', 'Conversation introuvable.');
        http_redirect_safe(app_url('/admin/messages.php'));
    }
    /* Support : répondre au nom de la boutique propriétaire du fil */
    $expediteur_admin_id = $is_support
        ? (int) ($conv_row['admin_id'] ?? 0)
        : $admin_id;
    if ($expediteur_admin_id <= 0) {
        flash_toast_push('error', 'Vendeur introuvable pour cette conversation.');
        http_redirect_safe(app_url('/admin/messages.php'));
    }

    if ($contenu === '') {
        flash_toast_push('error', 'Écrivez un message avant d\'envoyer.');
        http_redirect_safe($redirect);
    }

    if (mb_strlen($contenu) > 4000) {
        flash_toast_push('error', 'Message trop long (4000 caractères max).');
        http_redirect_safe($redirect);
    }

    $block_reason = messages_vendeur_blocked_reason($contenu);
    if ($block_reason !== null) {
        $res = message_vendeur_add_contact_blocked($conv_id, 'vendeur', $expediteur_admin_id, $contenu, $block_reason);
        if (empty($res['success'])) {
            flash_toast_push('error', $res['message'] ?? 'Envoi impossible.');
        }
        http_redirect_safe($redirect);
    }

    $res = message_vendeur_add($conv_id, 'vendeur', $expediteur_admin_id, $contenu, 'texte');
    if (empty($res['success'])) {
        flash_toast_push('error', $res['message'] ?? 'Envoi impossible.');
    }

    http_redirect_safe($redirect);
}

/**
 * Client — proposer un prix via la messagerie (intègre négociation existante)
 *
 * @return never
 */
function process_message_vendeur_client_propose_prix()
{
    require_once __DIR__ . '/controller_prix_negociation.php';
    require_once __DIR__ . '/../models/model_prix_negociations.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_redirect_safe(app_url('/user/messages.php'));
    }

    if (empty($_SESSION['user_id'])) {
        flash_toast_push('error', 'Connectez-vous pour négocier.');
        http_redirect_safe(app_url('/choix-connexion.php'));
    }

    $user_id = (int) $_SESSION['user_id'];
    $conv_id = isset($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
    $redirect = messages_vendeur_safe_redirect($_POST['redirect'] ?? '');

    $csrf = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!messages_vendeur_client_csrf_valid($csrf)) {
        flash_toast_push('error', 'Session expirée. Réessayez.');
        http_redirect_safe($redirect !== '' ? $redirect : app_url('/user/messages.php?conv=' . $conv_id));
    }

    if ($conv_id <= 0 || !conversation_vendeur_belongs_to_user($conv_id, $user_id)) {
        flash_toast_push('error', 'Conversation introuvable.');
        http_redirect_safe(app_url('/user/messages.php'));
    }

    $conv = conversation_vendeur_get_by_id($conv_id);
    $produit_id = (int) ($conv['produit_id'] ?? 0);
    if ($produit_id <= 0) {
        flash_toast_push('error', 'Aucun produit associé à cette conversation.');
        http_redirect_safe(app_url('/user/messages.php?conv=' . $conv_id));
    }

    $_POST['produit_id'] = $produit_id;
    $_POST['redirect'] = $redirect !== '' ? $redirect : app_url('/user/messages.php?conv=' . $conv_id);

    $produit = get_produit_by_id($produit_id);
    if (!$produit) {
        flash_toast_push('error', 'Produit introuvable.');
        http_redirect_safe(app_url('/user/messages.php?conv=' . $conv_id));
    }

    $prix_propose = isset($_POST['prix_propose']) && is_numeric($_POST['prix_propose']) ? (float) $_POST['prix_propose'] : 0;
    if ($prix_propose <= 0) {
        flash_toast_push('error', 'Indiquez un prix valide.');
        http_redirect_safe(app_url('/user/messages.php?conv=' . $conv_id . '&negocier=1'));
    }

    if (!prix_negociations_table_exists()) {
        flash_toast_push('error', 'Négociation indisponible.');
        http_redirect_safe(app_url('/user/messages.php?conv=' . $conv_id));
    }

    $ref = prix_negociation_compute_reference_from_post($produit, $_POST);
    if (empty($ref['success'])) {
        flash_toast_push('error', $ref['message'] ?? 'Données invalides.');
        http_redirect_safe(app_url('/user/messages.php?conv=' . $conv_id . '&negocier=1'));
    }

    $result = prix_negociation_submit_offer(
        $user_id,
        (int) ($conv['admin_id'] ?? 0),
        $produit_id,
        $ref['options'],
        (float) $ref['prix'],
        $prix_propose
    );

    if (empty($result['success'])) {
        flash_toast_push('error', $result['message'] ?? 'Impossible d\'envoyer votre offre.');
        http_redirect_safe(app_url('/user/messages.php?conv=' . $conv_id . '&negocier=1'));
    }

    $prix_fmt = number_format($prix_propose, 0, ',', ' ');
    $ref_fmt = number_format((float) $ref['prix'], 0, ',', ' ');
    $msg = 'Offre de prix : ' . $prix_fmt . ' FCFA (prix affiché : ' . $ref_fmt . ' FCFA).';
    message_vendeur_add($conv_id, 'client', $user_id, $msg, 'offre_prix', [
        'negociation_id' => (int) ($result['id'] ?? 0),
        'prix_propose' => $prix_propose,
        'prix_reference' => (float) $ref['prix'],
    ]);

    if (file_exists(__DIR__ . '/../services/send_prix_negociation_notification.php')) {
        require_once __DIR__ . '/../services/send_prix_negociation_notification.php';
        prix_negociation_try_notify_vendor((int) $result['id'], 'nouvelle_offre');
    }

    http_redirect_safe(app_url('/user/messages.php?conv=' . $conv_id));
}

/**
 * Client — menu conversation (bloquer, signaler, supprimer)
 *
 * @return never
 */
function process_message_vendeur_client_conversation_menu()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_redirect_safe(app_url('/user/messages.php'));
    }

    if (empty($_SESSION['user_id'])) {
        flash_toast_push('error', 'Connectez-vous pour continuer.');
        http_redirect_safe(app_url('/choix-connexion.php'));
    }

    if (!messages_vendeur_tables_exist()) {
        flash_toast_push('error', 'Messagerie indisponible.');
        http_redirect_safe(app_url('/user/messages.php'));
    }

    $csrf = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!messages_vendeur_client_csrf_valid($csrf)) {
        flash_toast_push('error', 'Session expirée. Réessayez.');
        http_redirect_safe(app_url('/user/messages.php'));
    }

    $user_id = (int) $_SESSION['user_id'];
    $conv_id = isset($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
    $action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
    $list_redirect = messages_vendeur_safe_redirect($_POST['redirect'] ?? '', app_url('/user/messages.php'));

    if ($conv_id <= 0 || !conversation_vendeur_belongs_to_user($conv_id, $user_id)) {
        flash_toast_push('error', 'Conversation introuvable.');
        http_redirect_safe(app_url('/user/messages.php'));
    }

    $conv = conversation_vendeur_get_by_id($conv_id);
    if (!$conv) {
        flash_toast_push('error', 'Conversation introuvable.');
        http_redirect_safe(app_url('/user/messages.php'));
    }

    $admin_id = (int) ($conv['admin_id'] ?? 0);
    $boutique_nom = trim((string) ($conv['vendeur_boutique_nom'] ?? 'Vendeur'));
    $produit_id = (int) ($conv['produit_id'] ?? 0);
    $produit_nom = trim((string) ($conv['produit_nom'] ?? ''));

    if ($action === 'supprimer_conversation') {
        $res = conversation_vendeur_delete_for_client($conv_id, $user_id);
        if (empty($res['success'])) {
            flash_toast_push('error', $res['message'] ?? 'Suppression impossible.');
            http_redirect_safe(app_url('/user/messages.php?conv=' . $conv_id));
        }
        flash_toast_push('success', 'Conversation supprimée.');
        http_redirect_safe($list_redirect);
    }

    if ($action === 'bloquer_vendeur') {
        if ($admin_id <= 0) {
            flash_toast_push('error', 'Vendeur introuvable.');
            http_redirect_safe(app_url('/user/messages.php?conv=' . $conv_id));
        }
        $res = messages_vendeur_client_bloquer_vendeur($user_id, $admin_id);
        if (empty($res['success'])) {
            flash_toast_push('error', $res['message'] ?? 'Impossible de bloquer ce vendeur.');
            http_redirect_safe(app_url('/user/messages.php?conv=' . $conv_id));
        }
        flash_toast_push('success', 'Vendeur bloqué. Vous ne recevrez plus ses messages.');
        http_redirect_safe($list_redirect);
    }

    if ($action === 'signaler_vendeur') {
        require_once __DIR__ . '/../models/model_users.php';
        $client = function_exists('get_user_by_id') ? get_user_by_id($user_id) : null;
        $client_nom = trim((string) (($client['prenom'] ?? '') . ' ' . ($client['nom'] ?? '')));
        $client_email = trim((string) ($client['email'] ?? ''));
        if ($client_email === '') {
            $client_email = 'client@colobanes.com';
        }

        $corps = "Signalement d'un vendeur via la messagerie COLObanes\n\n";
        $corps .= "Vendeur : " . $boutique_nom . " (admin_id: " . $admin_id . ")\n";
        $corps .= "Conversation : #" . $conv_id . "\n";
        if ($produit_id > 0) {
            $corps .= "Produit : " . ($produit_nom !== '' ? $produit_nom : 'Produit #' . $produit_id) . " (id: " . $produit_id . ")\n";
        }
        $corps .= "\nLe client a signalé ce vendeur depuis le menu de conversation.\n";
        $corps .= "Merci de traiter ce signalement dans les meilleurs délais.";

        $mail_ok = false;
        if (file_exists(__DIR__ . '/../services/mail.php')) {
            require_once __DIR__ . '/../services/mail.php';
            if (function_exists('mail_send_contact')) {
                $mail_res = mail_send_contact(
                    $client_nom !== '' ? $client_nom : 'Client COLObanes',
                    $client_email,
                    'Signalement vendeur — ' . $boutique_nom,
                    $corps
                );
                $mail_ok = !empty($mail_res['success']);
            }
        }

        if ($mail_ok) {
            flash_toast_push('success', 'Signalement envoyé. Notre équipe va l\'examiner.');
        } else {
            flash_toast_push('success', 'Signalement enregistré. Merci de votre vigilance.');
        }
        http_redirect_safe(app_url('/user/messages.php?conv=' . $conv_id));
    }

    flash_toast_push('error', 'Action inconnue.');
    http_redirect_safe(app_url('/user/messages.php?conv=' . $conv_id));
}
