-- Alertes prix client (prix de référence choisi à l’activation)

CREATE TABLE IF NOT EXISTS `produit_alerte_prix` (
  `user_id` INT(11) NOT NULL,
  `produit_id` INT(11) NOT NULL,
  `prix_reference` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_maj` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `produit_id`),
  KEY `idx_alerte_prix_produit` (`produit_id`),
  KEY `idx_alerte_prix_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
