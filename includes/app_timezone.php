<?php
/**
 * Fuseau horaire unique pour toute l'application.
 *
 * Priorité :
 * 1. config/site.php → timezone
 * 2. variable d'environnement APP_TIMEZONE
 * 3. fuseau de l'OS (/etc/localtime) — aligne XAMPP sur l'horloge Mac/serveur
 * 4. Africa/Dakar (marketplace Sénégal)
 */

if (!function_exists('app_timezone_name')) {
    function app_timezone_name(): string
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $candidates = [];

        $config_file = __DIR__ . '/../config/site.php';
        if (is_file($config_file)) {
            $config = include $config_file;
            if (is_array($config) && !empty($config['timezone'])) {
                $candidates[] = (string) $config['timezone'];
            }
        }

        $env = getenv('APP_TIMEZONE');
        if (is_string($env) && $env !== '') {
            $candidates[] = $env;
        }

        foreach (['/etc/localtime', '/var/db/timezone/localtime'] as $link) {
            if (!is_link($link)) {
                continue;
            }
            $target = @readlink($link);
            if (!is_string($target) || $target === '') {
                continue;
            }
            if (preg_match('#(?:zoneinfo|timezone/zoneinfo)/(.+)$#', str_replace('\\', '/', $target), $m)) {
                $candidates[] = $m[1];
            }
        }

        $ini = ini_get('date.timezone');
        if (is_string($ini) && $ini !== '' && strtoupper($ini) !== 'UTC') {
            $candidates[] = $ini;
        }

        $candidates[] = 'Africa/Dakar';

        foreach ($candidates as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            try {
                new DateTimeZone($name);
                $resolved = $name;
                return $resolved;
            } catch (Exception $e) {
                continue;
            }
        }

        $resolved = 'UTC';
        return $resolved;
    }
}

if (!function_exists('app_timezone_apply')) {
    /**
     * Applique le fuseau PHP (idempotent).
     */
    function app_timezone_apply(): string
    {
        static $applied = false;
        $name = app_timezone_name();
        if (!$applied || date_default_timezone_get() !== $name) {
            date_default_timezone_set($name);
            $applied = true;
        }
        return $name;
    }
}

if (!function_exists('app_timezone_mysql_offset')) {
    /**
     * Offset MySQL type +01:00 aligné sur le fuseau PHP courant.
     */
    function app_timezone_mysql_offset(): string
    {
        app_timezone_apply();
        return (new DateTimeImmutable('now'))->format('P');
    }
}

if (!function_exists('app_now')) {
    /**
     * Horodatage courant format MySQL DATETIME.
     */
    function app_now(): string
    {
        app_timezone_apply();
        return date('Y-m-d H:i:s');
    }
}

// Appliquer dès le chargement du fichier
app_timezone_apply();
