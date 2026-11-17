<?php
/**
 * API — basculer un produit en favori (sans rechargement de page)
 * POST: produit_id, csrf_token
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../includes/api_rate_limit.php';
require_once __DIR__ . '/../includes/site_csrf.php';
require_once __DIR__ . '/../controllers/controller_favoris.php';

header('Content-Type: application/json; charset=utf-8');

if (!api_rate_limit_check('toggle_favori', 60, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Trop de requêtes. Réessayez dans un instant.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!site_csrf_verify_post()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => site_csrf_fail_message()], JSON_UNESCAPED_UNICODE);
    exit;
}

$produit_id = isset($_POST['produit_id']) ? (int) $_POST['produit_id'] : 0;
$user_id = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$result = favori_execute_toggle($user_id, $produit_id);

if (!empty($result['auth_required'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Connectez-vous pour enregistrer vos favoris.',
        'auth_required' => true,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($result['success'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Action impossible.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'favori' => !empty($result['favori']),
    'message' => $result['message'] ?? '',
    'count' => isset($result['count']) ? (int) $result['count'] : 0,
], JSON_UNESCAPED_UNICODE);
