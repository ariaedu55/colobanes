<?php
/**
 * Relances push « reviens sur l'app » (copier vers reengagement_push.php pour modifier).
 * Toutes les clés sont optionnelles : valeurs par défaut dans services/reengagement_push.php.
 */
return [
    'enabled' => true,
    'inactive_days_min' => 3,      // relance dès 3 jours sans ouvrir l'app
    'inactive_days_max' => 45,     // au-delà, on arrête
    'gap_days' => 4,               // J+3, J+7, J+11
    'max_steps' => 3,
    'max_per_run' => 400,          // appareils par passage (cron toutes les 15 min)
    'concurrency' => 25,           // requêtes FCM simultanées
    'wave_pause_ms' => 150,
    'min_interval_seconds' => 900,
    'send_windows' => [[10, 13], [17, 21]], // heure locale du site
    'skip_if_pushed_within_hours' => 48,
];
