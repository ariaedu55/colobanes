<?php
/**
 * Rappels vendeur (popups dashboard) + dismissals.
 *
 * Usage : php migrations/run_migrate_vendeur_rappels.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';

if (empty($db) || !($db instanceof PDO)) {
    echo "Erreur : connexion BDD indisponible.\n";
    exit(1);
}

function vr_table_exists(PDO $db, string $table): bool
{
    $q = $db->prepare("
        SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $q->execute(['t' => $table]);
    return (int) $q->fetchColumn() > 0;
}

function vr_safe_exec(PDO $db, string $sql): bool
{
    try {
        $db->exec($sql);
        return true;
    } catch (PDOException $e) {
        echo "AVERTISSEMENT : " . $e->getMessage() . "\n";
        return false;
    }
}

echo "Migration rappels vendeur…\n";

if (!vr_table_exists($db, 'vendeur_rappels')) {
    vr_safe_exec($db, "
        CREATE TABLE `vendeur_rappels` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `titre` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `action_type` ENUM(
                'link_certification',
                'capture_geo',
                'select_region',
                'select_boutique_type',
                'upload_logo',
                'customize_colors'
            ) NOT NULL,
            `action_label` VARCHAR(120) NOT NULL,
            `sort_ordre` INT(11) NOT NULL DEFAULT 0,
            `actif` TINYINT(1) NOT NULL DEFAULT 1,
            `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_vr_actif_sort` (`actif`, `sort_ordre`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table vendeur_rappels\n";
} else {
    echo "  table vendeur_rappels déjà présente.\n";
}

if (!vr_table_exists($db, 'vendeur_rappels_dismissals')) {
    vr_safe_exec($db, "
        CREATE TABLE `vendeur_rappels_dismissals` (
            `admin_id` INT(11) NOT NULL,
            `rappel_id` INT(11) NOT NULL,
            `dismissed_until` DATETIME NULL DEFAULT NULL,
            `completed_at` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`admin_id`, `rappel_id`),
            KEY `idx_vrd_rappel` (`rappel_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table vendeur_rappels_dismissals\n";
} else {
    echo "  table vendeur_rappels_dismissals déjà présente.\n";
}

if (vr_table_exists($db, 'vendeur_rappels_dismissals')) {
    try {
        $col = $db->query("SHOW COLUMNS FROM vendeur_rappels_dismissals LIKE 'snooze_count'")->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            vr_safe_exec($db, "
                ALTER TABLE `vendeur_rappels_dismissals`
                ADD COLUMN `snooze_count` TINYINT UNSIGNED NOT NULL DEFAULT 0
                COMMENT 'Nombre de clics « Plus tard » (max 2 affichages)'
                AFTER `completed_at`
            ");
            echo "  + vendeur_rappels_dismissals.snooze_count\n";
        }
    } catch (PDOException $e) {
        echo "  (snooze_count) " . $e->getMessage() . "\n";
    }
}

if (vr_table_exists($db, 'vendeur_rappels') && vr_table_exists($db, 'boutique_types')) {
    try {
        $cnt = (int) $db->query("SELECT COUNT(*) FROM boutique_types WHERE actif = 1")->fetchColumn();
        $existing = (int) $db->query("SELECT COUNT(*) FROM vendeur_rappels WHERE action_type = 'select_boutique_type'")->fetchColumn();
        if ($cnt > 0 && $existing === 0) {
            $st = $db->prepare("
                INSERT INTO vendeur_rappels (titre, message, action_type, action_label, sort_ordre, actif, date_creation)
                VALUES (
                    :titre, :msg, 'select_boutique_type', :label, 10, 1, NOW()
                )
            ");
            $st->execute([
                'titre' => 'Choisissez votre type de boutique',
                'msg' => 'Indiquez le type de votre activité pour que vos clients vous trouvent plus facilement sur la marketplace.',
                'label' => 'Choisir mon type de boutique',
            ]);
            echo "  + rappel seed select_boutique_type\n";
        }
    } catch (PDOException $e) {
        echo "  (seed) " . $e->getMessage() . "\n";
    }
}

if (vr_table_exists($db, 'vendeur_rappels')) {
    try {
        $col = $db->query("SHOW COLUMNS FROM vendeur_rappels LIKE 'action_type'")->fetch(PDO::FETCH_ASSOC);
        $type = (string) ($col['Type'] ?? '');
        if ($type !== '' && stripos($type, 'idee_projet_app') === false) {
            vr_safe_exec($db, "
                ALTER TABLE vendeur_rappels
                MODIFY COLUMN action_type ENUM(
                    'link_certification',
                    'capture_geo',
                    'select_region',
                    'select_boutique_type',
                    'upload_logo',
                    'customize_colors',
                    'idee_projet_app'
                ) NOT NULL
            ");
            echo "  + ENUM action_type : idee_projet_app\n";
        }
    } catch (PDOException $e) {
        echo "  (enum idee_projet_app) " . $e->getMessage() . "\n";
    }
}

if (!vr_table_exists($db, 'vendeur_rappel_idees_projet')) {
    vr_safe_exec($db, "
        CREATE TABLE `vendeur_rappel_idees_projet` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `admin_id` INT(11) NOT NULL,
            `rappel_id` INT(11) NOT NULL,
            `idee` TEXT NOT NULL,
            `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_vrip_admin_rappel` (`admin_id`, `rappel_id`),
            KEY `idx_vrip_rappel` (`rappel_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  + table vendeur_rappel_idees_projet\n";
}

echo "Terminé.\n";
