<?php
/**
 * API de l'assistant COLObanes (chatbot).
 * POST JSON : { message: string, history?: [{role:'user'|'bot', text}], context?: {query, categorie_id, categorie_nom} }
 * Réponse : { success, reply, products[], shops[], orders[], links[], suggestions[], context?, engine }
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/api_rate_limit.php';
require_once __DIR__ . '/../includes/assistant/assistant_engine.php';

$cfg = assistant_config();

if (empty($cfg['enabled'])) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Assistant indisponible.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* Appels acceptés uniquement depuis le site lui-même */
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
$origin_host = strtolower((string) parse_url($origin, PHP_URL_HOST));
$origin_port = parse_url($origin, PHP_URL_PORT);
if ($origin_port) {
    $origin_host .= ':' . $origin_port;
}
if ($origin === '' || $origin_host !== $host) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Requête refusée.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!api_rate_limit_check('assistant', max(5, (int) $cfg['rate_limit_per_minute']), 60)) {
    api_rate_limit_deny_json();
}

$input = json_decode((string) file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];

$message = trim((string) ($input['message'] ?? ''));
$message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message);
if ($message === '' || mb_strlen($message, 'UTF-8') > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message vide ou trop long (500 caractères maximum).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$history = [];
if (!empty($input['history']) && is_array($input['history'])) {
    foreach (array_slice($input['history'], -10) as $h) {
        if (is_array($h) && isset($h['role'], $h['text'])) {
            $history[] = [
                'role' => $h['role'] === 'bot' ? 'bot' : 'user',
                'text' => mb_substr((string) $h['text'], 0, 1500, 'UTF-8'),
            ];
        }
    }
}

$context = [];
if (!empty($input['context']) && is_array($input['context'])) {
    $context = [
        'query' => mb_substr(trim((string) ($input['context']['query'] ?? '')), 0, 120, 'UTF-8'),
        'categorie_id' => (int) ($input['context']['categorie_id'] ?? 0),
        'categorie_nom' => mb_substr((string) ($input['context']['categorie_nom'] ?? ''), 0, 80, 'UTF-8'),
    ];
}

/* Les commandes de l'utilisateur sont lues dans la session : on la libère ensuite pour ne pas bloquer le site */
$result = assistant_answer($message, $history, $context);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

echo json_encode(['success' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
