<?php
/**
 * Onboarding vendeur — premier produit (copier vers vendeur_onboarding.php).
 */
return [
    'vendeur_onboarding_relance_interval_heures' => 24,
    'vendeur_onboarding_avertissement_jours' => 7,
    /** Après J+7, espacement des relances (heures). Aucun compte n'est supprimé. */
    'vendeur_onboarding_relance_interval_apres_j7_heures' => 168,
    /** Chemin relatif admin pour ajouter un produit (préfixé par app_url). */
    'vendeur_onboarding_cta_ajout_produit_path' => '/admin/produits/ajouter.php',
];
