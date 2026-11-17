<?php
/**
 * Tests onboarding vendeur (statut, relance 24h, activation produit, J+10).
 *
 * Usage : php scripts/test_vendeur_onboarding.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/models/model_vendeur_onboarding.php';
require_once $root . '/models/model_notification_log.php';
require_once $root . '/services/notification_vendeur_onboarding.php';

$pass = 0;
$fail = 0;

function vo_test(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo '  [OK]   ' . $label . ($detail !== '' ? ' — ' . $detail : '') . "\n";
    } else {
        $fail++;
        echo '  [FAIL] ' . $label . ($detail !== '' ? ' — ' . $detail : '') . "\n";
    }
}

echo "=== Test onboarding vendeur ===\n\n";

if (!vendeur_onboarding_ensure_schema()) {
    echo "Schéma vendeur_onboarding indisponible (BDD ?).\n";
    exit(1);
}

global $db;

$slug = 'test-onb-' . bin2hex(random_bytes(4));
$tel = '+22177' . random_int(1000000, 9999999);
$email = 'onb-test-' . bin2hex(random_bytes(3)) . '@example.invalid';
$pin_hash = password_hash('123456', PASSWORD_BCRYPT);

try {
    $db->prepare("
        INSERT INTO admin (nom, prenom, email, password, date_creation, statut, role, boutique_slug, boutique_nom, telephone)
        VALUES (:nom, '', :email, :pw, DATE_SUB(NOW(), INTERVAL 25 HOUR), 'en_attente_produit', 'vendeur', :slug, 'Boutique test onboarding', :tel)
    ")->execute([
        'nom' => 'Test Onboarding',
        'email' => $email,
        'pw' => $pin_hash,
        'slug' => $slug,
        'tel' => preg_replace('/\s+/', '', $tel),
    ]);
    $admin_id = (int) $db->lastInsertId();
} catch (PDOException $e) {
    echo 'Insert vendeur test échoué : ' . $e->getMessage() . "\n";
    exit(1);
}

vendeur_onboarding_register($admin_id);

$admin = $db->prepare('SELECT statut FROM admin WHERE id = :id');
$admin->execute(['id' => $admin_id]);
$row = $admin->fetch(PDO::FETCH_ASSOC);
vo_test('vendeur créé en_attente_produit', ($row['statut'] ?? '') === 'en_attente_produit');

vo_test('login autorisé en attente', admin_statut_allows_admin_login([
    'statut' => 'en_attente_produit',
    'role' => 'vendeur',
]));

$before = (int) $db->query("SELECT COUNT(*) FROM notification_log WHERE admin_id = $admin_id AND type = 'vendeur_onboarding_relance'")->fetchColumn();
$stats = notification_worker_vendeur_onboarding();
$after = (int) $db->query("SELECT COUNT(*) FROM notification_log WHERE admin_id = $admin_id AND type = 'vendeur_onboarding_relance'")->fetchColumn();
vo_test('relance 24h (1 log)', $after >= $before + 1, 'relances=' . (int) ($stats['relance'] ?? 0));

$db->prepare("
    INSERT INTO produits (nom, description, prix, stock, categorie_id, image_principale, date_creation, statut, admin_id)
    VALUES ('Produit test onboarding', 'Test', 1000, 5, NULL, 'produit1.jpg', NOW(), 'actif', :aid)
")->execute(['aid' => $admin_id]);

$activated = vendeur_onboarding_try_activate_after_produit($admin_id);
$admin->execute(['id' => $admin_id]);
$row = $admin->fetch(PDO::FETCH_ASSOC);
vo_test('activation après 1er produit', $activated && ($row['statut'] ?? '') === 'actif');

$slug2 = 'test-onb-del-' . bin2hex(random_bytes(4));
$email2 = 'onb-del-' . bin2hex(random_bytes(3)) . '@example.invalid';
$db->prepare("
    INSERT INTO admin (nom, prenom, email, password, date_creation, statut, role, boutique_slug, boutique_nom, telephone)
    VALUES ('Test Conservation', '', :email, :pw, DATE_SUB(NOW(), INTERVAL 11 DAY), 'en_attente_produit', 'vendeur', :slug, 'Boutique sans produit J+11', :tel)
")->execute([
    'email' => $email2,
    'pw' => $pin_hash,
    'slug' => $slug2,
    'tel' => '+22178' . random_int(1000000, 9999999),
]);
$del_id = (int) $db->lastInsertId();
vendeur_onboarding_register($del_id);
$db->prepare('UPDATE vendeur_onboarding SET cycle_started_at = DATE_SUB(NOW(), INTERVAL 11 DAY) WHERE admin_id = :id')
    ->execute(['id' => $del_id]);

$out = vendeur_onboarding_supprimer_si_sans_produit($del_id);
$chk = $db->prepare('SELECT id FROM admin WHERE id = :id');
$chk->execute(['id' => $del_id]);
$still = (bool) $chk->fetchColumn();
vo_test('aucune suppression à J+11 sans produit', $out === 'skipped' && $still, 'outcome=' . $out);

$db->prepare('DELETE FROM vendeur_onboarding WHERE admin_id = :id')->execute(['id' => $del_id]);
$db->prepare('DELETE FROM notification_log WHERE admin_id = :id')->execute(['id' => $del_id]);
$db->prepare('DELETE FROM admin WHERE id = :id')->execute(['id' => $del_id]);

// Nettoyage vendeur activé + produit
$db->prepare('DELETE FROM produits WHERE admin_id = :id')->execute(['id' => $admin_id]);
$db->prepare('DELETE FROM vendeur_onboarding WHERE admin_id = :id')->execute(['id' => $admin_id]);
$db->prepare('DELETE FROM notification_log WHERE admin_id = :id')->execute(['id' => $admin_id]);
$db->prepare('DELETE FROM admin WHERE id = :id')->execute(['id' => $admin_id]);

echo "\nRésumé : $pass OK, $fail échec(s)\n";
exit($fail > 0 ? 1 : 0);
