-- Journal comportement client + agrégats intérêts par rayon (categories_generales)

CREATE TABLE IF NOT EXISTS `client_evenements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(32) NOT NULL,
  `user_id` INT(11) NULL DEFAULT NULL,
  `visiteur_key` VARCHAR(64) NULL DEFAULT NULL,
  `produit_id` INT(11) NULL DEFAULT NULL,
  `categorie_id` INT(11) NULL DEFAULT NULL,
  `categorie_generale_id` INT(11) NULL DEFAULT NULL,
  `terme_recherche` VARCHAR(500) NULL DEFAULT NULL,
  `commande_id` INT(11) NULL DEFAULT NULL,
  `source` VARCHAR(64) NULL DEFAULT NULL,
  `poids` SMALLINT NOT NULL DEFAULT 0,
  `date_evenement` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ce_user_date` (`user_id`, `date_evenement`),
  KEY `idx_ce_visiteur_date` (`visiteur_key`, `date_evenement`),
  KEY `idx_ce_type_date` (`type`, `date_evenement`),
  KEY `idx_ce_rayon_date` (`categorie_generale_id`, `date_evenement`),
  KEY `idx_ce_produit` (`produit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `client_interets_rayon` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL DEFAULT NULL,
  `visiteur_key` VARCHAR(64) NULL DEFAULT NULL,
  `categorie_generale_id` INT(11) NOT NULL,
  `score` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `derniere_maj` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cir_user_rayon` (`user_id`, `categorie_generale_id`),
  UNIQUE KEY `uniq_cir_visiteur_rayon` (`visiteur_key`, `categorie_generale_id`),
  KEY `idx_cir_user_score` (`user_id`, `score`),
  KEY `idx_cir_visiteur_score` (`visiteur_key`, `score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
