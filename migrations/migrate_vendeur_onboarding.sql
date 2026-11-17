-- Onboarding vendeur : statut en_attente_produit + suivi relances
-- Exécuté automatiquement via includes/schema_vendeur_onboarding.php

ALTER TABLE `admin`
  MODIFY COLUMN `statut` ENUM('actif', 'inactif', 'en_attente_produit') NOT NULL DEFAULT 'actif';

CREATE TABLE IF NOT EXISTS `vendeur_onboarding` (
  `admin_id` INT(11) NOT NULL,
  `cycle_started_at` DATETIME NOT NULL,
  `derniere_relance_at` DATETIME NULL DEFAULT NULL,
  `relance_count` INT(11) NOT NULL DEFAULT 0,
  `avertissement_j7_envoye_at` DATETIME NULL DEFAULT NULL,
  `rappel_j9_envoye_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`admin_id`),
  KEY `idx_vendeur_onboarding_cycle` (`cycle_started_at`),
  KEY `idx_vendeur_onboarding_relance` (`derniere_relance_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
