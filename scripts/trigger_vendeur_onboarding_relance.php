<?php
/**
 * Déclenche la relance push + e-mail « activation compte vendeur » (test).
 *
 * Usage :
 *   php scripts/trigger_vendeur_onboarding_relance.php "+221760000000"
 *   php scripts/trigger_vendeur_onboarding_relance.php 85
 *   php scripts/trigger_vendeur_onboarding_relance.php "+221760000000" --force
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/models/model_admin.php';
require_once $root . '/services/notification_vendeur_onboarding.php';

$arg = $argv[1] ?? '';
$force = in_array('--force', $argv, true) || in_array('-f', $argv, true);

if ($arg === '') {
    fwrite(STDERR, "Usage: php scripts/trigger_vendeur_onboarding_relance.php <telephone|admin_id> [--force]\n");
    exit(1);
}

$admin = false;
if (ctype_digit($arg)) {
    $admin = get_admin_by_id((int) $arg);
} else {
    $admin = get_admin_by_telephone($arg);
}

if (!$admin) {
    fwrite(STDERR, "Vendeur non trouvé.\n");
    exit(1);
}

$result = notification_vendeur_onboarding_send_activation_relance((int) $admin['id'], $force);

echo ($result['message'] ?? '') . "\n";
if (!empty($result['ok'])) {
    echo 'Push : ' . (!empty($result['push']) ? 'oui' : 'non') . "\n";
    echo 'E-mail : ' . (!empty($result['email']) ? 'oui' : 'non') . "\n";
    echo 'Boutique : ' . ($admin['boutique_nom'] ?? '') . ' (id ' . (int) $admin['id'] . ")\n";
    exit(0);
}

exit(1);
