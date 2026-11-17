<?php
/**
 * Réinitialise le PIN d’un vendeur (environnement local / CLI uniquement).
 *
 * Usage : php scripts/reset_vendeur_pin_local.php "+221760000000" 469258
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/models/model_admin.php';

$tel = $argv[1] ?? '';
$pin = $argv[2] ?? '';
if ($tel === '' || !preg_match('/^\d{6}$/', $pin)) {
    fwrite(STDERR, "Usage: php scripts/reset_vendeur_pin_local.php \"+221760000000\" 123456\n");
    exit(1);
}

$admin = get_admin_by_telephone($tel);
if (!$admin || normalize_admin_role($admin['role'] ?? '') !== 'vendeur') {
    fwrite(STDERR, "Aucun vendeur trouvé pour ce téléphone.\n");
    exit(1);
}

$hash = password_hash($pin, PASSWORD_BCRYPT);
$db->prepare('UPDATE admin SET password = :p WHERE id = :id')->execute([
    'p' => $hash,
    'id' => (int) $admin['id'],
]);

$norm = admin_normalize_telephone_for_storage($tel);
if ($norm !== '' && ($admin['telephone'] ?? '') !== $norm) {
    $other = get_admin_by_telephone($norm);
    if (!$other || (int) ($other['id'] ?? 0) === (int) $admin['id']) {
        try {
            $db->prepare('UPDATE admin SET telephone = :t WHERE id = :id')->execute([
                't' => $norm,
                'id' => (int) $admin['id'],
            ]);
        } catch (PDOException $e) {
            fwrite(STDERR, 'Attention : doublon téléphone, PIN mis à jour mais numéro non fusionné (' . $e->getMessage() . ")\n");
        }
    } else {
        fwrite(STDERR, "Attention : un autre compte utilise déjà le format $norm (id=" . (int) $other['id'] . "). PIN mis à jour sur id=" . (int) $admin['id'] . ".\n");
    }
}

echo 'OK vendeur id=' . (int) $admin['id'] . ' tel=' . ($admin['telephone'] ?? '') . " — PIN réinitialisé.\n";
echo "Connexion : /choix-connexion.php → onglet Téléphone, PIN à 6 chiffres, cochez les CGU.\n";
