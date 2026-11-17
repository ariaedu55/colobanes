<?php
/**
 * Actions du module Dons (POST + CSRF) et rafraîchissement des conversations (GET poll, JSON).
 */
require_once __DIR__ . '/../includes/dons_bootstrap.php';

$uid = (int) ($_SESSION['user_id'] ?? 0);
$is_ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

function dons_action_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function dons_action_back(string $fallback): void
{
    $to = (string) ($_POST['retour'] ?? '');
    $base = get_app_base_path();
    if ($base !== '' && str_starts_with($to, $base . '/')) {
        $to = substr($to, strlen($base));
    }
    if ($to === '' || $to[0] !== '/' || str_starts_with($to, '//') || preg_match('/[\\\\\x00-\x1F\x7F]/', $to) || !str_starts_with($to, '/dons/')) {
        $to = $fallback;
    }
    dons_redirect($to);
}

/* ---- Lecture des nouveaux messages (GET) ---- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['action'] ?? '') === 'poll') {
    if ($uid <= 0) {
        dons_action_json(['ok' => false], 401);
    }
    $conv = dons_conversation_for_user((int) ($_GET['conversation'] ?? 0), $uid);
    if (!$conv) {
        dons_action_json(['ok' => false], 404);
    }
    $msgs = dons_messages((int) $conv['id'], (int) ($_GET['after'] ?? 0));
    dons_messages_marquer_lus((int) $conv['id'], $uid);
    $out = [];
    foreach ($msgs as $m) {
        $out[] = [
            'id' => (int) $m['id'],
            'mine' => (int) $m['sender_id'] === $uid,
            'systeme' => (bool) $m['systeme'],
            'html' => nl2br(dons_e($m['contenu'])),
            'heure' => date('H:i', strtotime((string) $m['created_at'])),
        ];
    }
    dons_action_json(['ok' => true, 'messages' => $out, 'statut' => $conv['demande_statut']]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    dons_redirect('/dons/');
}
if ($uid <= 0) {
    $is_ajax ? dons_action_json(['ok' => false, 'message' => 'Connectez-vous.'], 401) : header('Location: ' . auth_choix_connexion_url(app_url('/dons/')));
    exit;
}
$csrf = site_csrf_require_post();
if (!$csrf['ok']) {
    if ($is_ajax) {
        dons_action_json(['ok' => false, 'message' => $csrf['message']], 403);
    }
    dons_flash('err', $csrf['message']);
    dons_action_back('/dons/');
}

/* Compte suspendu : lecture seule */
global $db;
$st = $db->prepare('SELECT statut FROM users WHERE id = :id');
$st->execute(['id' => $uid]);
if ($st->fetchColumn() !== 'actif') {
    $msg = 'Votre compte est suspendu : vous ne pouvez plus publier ni demander de dons.';
    $is_ajax ? dons_action_json(['ok' => false, 'message' => $msg], 403) : dons_flash('err', $msg);
    dons_action_back('/dons/');
}

$action = (string) ($_POST['action'] ?? '');
$don_id = (int) ($_POST['don_id'] ?? 0);

switch ($action) {
    case 'demander':
        $r = dons_demander($don_id, $uid, (string) ($_POST['message'] ?? ''));
        if ($r['ok']) {
            dons_flash('ok', 'Demande envoyée ! Le donateur vous répondra ici.');
            dons_redirect('/dons/conversation.php?id=' . (int) $r['conversation_id']);
        }
        dons_flash('err', $r['message']);
        dons_action_back('/dons/don.php?id=' . $don_id);
        break;

    case 'annuler':
        $r = dons_demande_annuler($don_id, $uid);
        dons_flash($r['ok'] ? 'ok' : 'err', $r['message']);
        dons_action_back('/dons/don.php?id=' . $don_id);
        break;

    case 'accepter':
    case 'refuser':
    case 'donner':
        $r = dons_demande_action((int) ($_POST['demande_id'] ?? 0), $uid, $action);
        $labels = ['accepter' => 'Objet réservé pour cette personne.', 'refuser' => 'Demande refusée.', 'donner' => 'Remise confirmée, merci !'];
        dons_flash($r['ok'] ? 'ok' : 'err', $r['ok'] ? $labels[$action] : $r['message']);
        dons_action_back('/dons/mes-dons.php');
        break;

    case 'retirer':
        $r = dons_retirer($don_id, $uid);
        dons_flash($r['ok'] ? 'ok' : 'err', $r['message']);
        dons_action_back('/dons/mes-dons.php');
        break;

    case 'signaler':
        $conv = (int) ($_POST['conversation_id'] ?? 0);
        if ($conv > 0) {
            $c = dons_conversation_for_user($conv, $uid);
            $conv = $c ? (int) $c['id'] : 0;
            $don_id = $c ? (int) $c['don_id'] : $don_id;
        }
        $don = dons_get($don_id);
        if (!$don) {
            dons_flash('err', 'Annonce introuvable.');
            dons_action_back('/dons/');
        }
        $r = dons_signaler($don_id, $uid, (string) ($_POST['motif'] ?? ''), (string) ($_POST['details'] ?? ''), $conv ?: null);
        dons_flash($r['ok'] ? 'ok' : 'err', $r['message']);
        dons_action_back($conv ? '/dons/conversation.php?id=' . $conv : '/dons/don.php?id=' . $don_id);
        break;

    case 'message':
        $r = dons_message_envoyer((int) ($_POST['conversation_id'] ?? 0), $uid, (string) ($_POST['contenu'] ?? ''));
        if ($is_ajax) {
            dons_action_json($r, $r['ok'] ? 200 : 422);
        }
        if (!$r['ok']) {
            dons_flash('err', $r['message']);
        }
        dons_action_back('/dons/conversation.php?id=' . (int) ($_POST['conversation_id'] ?? 0));
        break;

    default:
        dons_redirect('/dons/');
}
