-- Rappel popup certification vendeur (snooze)
ALTER TABLE `vendeur_onboarding`
  ADD COLUMN `cert_promo_snooze_until` DATETIME NULL DEFAULT NULL AFTER `rappel_j9_envoye_at`;
