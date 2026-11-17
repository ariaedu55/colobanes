<?php
/**
 * Snooze « Plus tard » — rappel vendeur (7 jours, max 2 affichages).
 */
require_once __DIR__ . '/includes/require_admin_session.php';
require_once __DIR__ . '/includes/require_access.php';
require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../models/model_vendeur_rappels.php';
require_once __DIR__ . '/../includes/flash_toast.php';

$redirect = 'dashboard.php';

$role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? '');
if ($role !== 'vendeur') {
    header('Location: ' . $redirect);
    exit;
}

$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
if ($admin_id <= 0 || !get_admin_by_id($admin_id)) {
    header('Location: ' . $redirect);
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        flash_toast_queue_page('error', 'Session expirée. Veuillez recharger la page.');
    } else {
        $rappel_id = (int) ($_POST['rappel_id'] ?? 0);
        $rappel = $rappel_id > 0 ? get_vendeur_rappel_by_id($rappel_id) : false;
        if (!$rappel) {
            flash_toast_queue_page('error', 'Rappel introuvable.');
        } elseif (vendeur_rappel_snooze($admin_id, $rappel_id)) {
            $d = vendeur_rappel_get_dismissal($admin_id, $rappel_id);
            if ($d && !empty($d['completed_at'])) {
                flash_toast_queue_page('success', 'Ce rappel ne s\'affichera plus sur votre tableau de bord.');
            } else {
                flash_toast_queue_page('success', 'Rappel reporté : réaffichage dans ' . vendeur_rappel_snooze_days_default() . ' jours.');
            }
        } else {
            flash_toast_queue_page('error', 'Impossible de reporter ce rappel.');
        }
    }
}

header('Location: ' . $redirect);
exit;
