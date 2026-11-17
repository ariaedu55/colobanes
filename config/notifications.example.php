<?php
/**
 * Configuration notifications planifiées (copier vers notifications.php).
 */
return [
    'panier_abandonne_heures' => [24, 72],
    'panier_relance_max' => 2,
    'commande_attente_vendeur_heures' => 24,
    'commande_attente_escalade_heures' => 48,
    'livraison_sla_jours' => 7,
    'livraison_sla_escalade_multiplicateur' => 2,
    'livraison_statuts_actifs' => [
        'confirmee',
        'prise_en_charge',
        'en_preparation',
        'expediee',
        'livraison_en_cours',
    ],
    'favori_prix_baisse_pct_min' => 5,
    /** Si true : alerte dès que le prix passe sous le prix de référence (favori / alerte), sans seuil de %. */
    'prix_alerte_sous_reference' => true,
    'favori_ventes_rapides_seuil' => 10,
    'favori_ventes_rapides_jours' => 7,
    /** Relances favoris — push uniquement : 48 h, 4 j (96 h), puis chaque semaine. */
    'favoris_relance_premiere_heures' => 48,
    'favoris_relance_deuxieme_heures' => 96,
    'favoris_relance_hebdo_heures' => 168,
    'produit_cible_max_par_jour_user' => 3,
    'produit_cible_recherche_jours' => 90,
    /** Relance inactivité — push + e-mail, segmentée par rayon (client_interets_rayon). */
    'engagement_inactif_jours' => 7,
    'engagement_inactif_interval_jours' => 7,
    'engagement_dispatch_batch_per_run' => 25,
    'engagement_dispatch_spread_hours' => 168,
    'engagement_dispatch_max_per_segment_per_run' => 5,
    'engagement_inactif_sync_limit_per_run' => 300,
    'engagement_interet_score_min' => 0,
    /** Nouveau produit : même titre/corps pour tous les utilisateurs du même rayon. */
    'engagement_nouveaute_use_segment_copy' => true,
    /**
     * Planification automatique du bundle (worker + e-mails en file).
     * true = lance en arrière-plan lors des visites (XAMPP / sans crontab).
     * false = uniquement CLI ou URL cron ci-dessous.
     */
    'notifications_cron_auto_on_request' => true,
    'notifications_cron_interval_minutes' => 15,
    /** Secret pour GET /scripts/cron_notifications_bundle.php?key=... (laisser vide pour désactiver l’URL). */
    'notifications_cron_secret' => '',
];
