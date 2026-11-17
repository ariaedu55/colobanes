<?php
/**
 * Bootstrap commun — endpoints API messagerie temps réel
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../models/model_messages_vendeur.php';

if (!function_exists('app_url')) {
    require_once __DIR__ . '/app_base.php';
}

/**
 * @return array{role:string, id:int}|null
 */
function messages_vendeur_api_resolve_auth()
{
    if (!empty($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] > 0) {
        return ['role' => 'vendeur', 'id' => (int) $_SESSION['admin_id']];
    }
    if (!empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
        return ['role' => 'client', 'id' => (int) $_SESSION['user_id']];
    }

    return null;
}

/**
 * @param string $message
 * @param int $code
 * @return never
 */
function messages_vendeur_api_json_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode([
        'ok' => false,
        'error' => (string) $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @param array<string, mixed> $payload
 * @return never
 */
function messages_vendeur_api_json_ok(array $payload)
{
    $payload['ok'] = true;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @param int $conv_id
 * @param array{role:string, id:int} $auth
 * @return array<string, mixed>|null
 */
function messages_vendeur_api_load_conv($conv_id, array $auth)
{
    $conv_id = (int) $conv_id;
    if ($conv_id <= 0 || !messages_vendeur_tables_exist()) {
        return null;
    }

    if ($auth['role'] === 'client') {
        if (!conversation_vendeur_belongs_to_user($conv_id, (int) $auth['id'])) {
            return null;
        }
    } else {
        if (!conversation_vendeur_belongs_to_admin($conv_id, (int) $auth['id'])) {
            return null;
        }
    }

    return conversation_vendeur_get_by_id($conv_id);
}

require_once __DIR__ . '/api_rate_limit.php';
$rl_script = basename($_SERVER['SCRIPT_FILENAME'] ?? 'messages_vendeur', '.php');
if (!api_rate_limit_check('messages_vendeur_' . $rl_script, 120, 60)) {
    messages_vendeur_api_json_error('Trop de requêtes. Réessayez dans un instant.', 429);
}
