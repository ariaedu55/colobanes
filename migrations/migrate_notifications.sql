-- Notifications planifiées — journal anti-doublon et snapshots favoris

CREATE TABLE IF NOT EXISTS `notification_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(64) NOT NULL,
  `dedupe_key` VARCHAR(191) NOT NULL,
  `user_id` INT(11) NULL DEFAULT NULL,
  `admin_id` INT(11) NULL DEFAULT NULL,
  `produit_id` INT(11) NULL DEFAULT NULL,
  `commande_id` INT(11) NULL DEFAULT NULL,
  `channel` VARCHAR(16) NOT NULL DEFAULT 'push_email',
  `push_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `email_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `date_envoi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notification_dedupe` (`dedupe_key`),
  KEY `idx_notification_type_date` (`type`, `date_envoi`),
  KEY `idx_notification_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `favoris_produit_snapshot` (
  `user_id` INT(11) NOT NULL,
  `produit_id` INT(11) NOT NULL,
  `dernier_prix_ref` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `dernier_statut` VARCHAR(32) NOT NULL DEFAULT '',
  `date_maj` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `produit_id`),
  KEY `idx_favoris_snap_produit` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `panier_relance` (
  `user_id` INT(11) NOT NULL,
  `derniere_relance_at` DATETIME NULL DEFAULT NULL,
  `relance_count` INT(11) NOT NULL DEFAULT 0,
  `cycle_started_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `favoris_relance` (
  `user_id` INT(11) NOT NULL,
  `cycle_started_at` DATETIME NOT NULL,
  `derniere_favori_at` DATETIME NOT NULL,
  `derniere_relance_at` DATETIME NULL DEFAULT NULL,
  `relance_count` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  KEY `idx_favoris_relance_cycle` (`cycle_started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `engagement_relance` (
  `user_id` INT(11) NOT NULL,
  `cycle_started_at` DATETIME NOT NULL,
  `derniere_relance_at` DATETIME NULL DEFAULT NULL,
  `relance_count` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  KEY `idx_engagement_relance_cycle` (`cycle_started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_segment_queue` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `categorie_generale_id` INT(11) NOT NULL DEFAULT 0,
  `campaign` VARCHAR(32) NOT NULL,
  `produit_id` INT(11) NULL DEFAULT NULL,
  `dedupe_key` VARCHAR(191) NOT NULL,
  `payload_json` TEXT NOT NULL,
  `scheduled_send_at` DATETIME NOT NULL,
  `sent_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_segment_queue_dedupe` (`dedupe_key`),
  KEY `idx_segment_queue_due` (`sent_at`, `scheduled_send_at`),
  KEY `idx_segment_queue_segment_due` (`categorie_generale_id`, `sent_at`, `scheduled_send_at`),
  KEY `idx_segment_queue_campaign` (`campaign`, `produit_id`, `sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
