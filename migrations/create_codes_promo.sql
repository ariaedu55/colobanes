-- Codes promo panier / commande
CREATE TABLE IF NOT EXISTS `codes_promo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `libelle` varchar(255) DEFAULT NULL,
  `type_remise` enum('pourcentage','montant_fixe') NOT NULL DEFAULT 'pourcentage',
  `valeur` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_minimum` decimal(10,2) DEFAULT NULL,
  `date_debut` datetime DEFAULT NULL,
  `date_fin` datetime DEFAULT NULL,
  `utilisations_max` int(11) DEFAULT NULL,
  `utilisations_count` int(11) NOT NULL DEFAULT 0,
  `actif` enum('actif','inactif') NOT NULL DEFAULT 'actif',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codes_promo_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
