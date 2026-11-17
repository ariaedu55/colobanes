<?php
/**
 * Repères « déjà fait » (table créée, colonne ajoutée…) propres à CHAQUE base de données.
 * Un repère copié d'un ordinateur vers le serveur (dossier storage/ envoyé avec le site)
 * ne vaut rien sur l'autre base : le serveur refait donc ses propres vérifications.
 */

if (!function_exists('repere_base_id')) {
    function repere_base_id(): string
    {
        static $id = null;
        if ($id !== null) {
            return $id;
        }
        global $db;
        if (!$db instanceof PDO) {
            return 'sans-base';
        }
        try {
            $nom = (string) $db->query('SELECT DATABASE()')->fetchColumn();
            $hote = (string) $db->query('SELECT @@hostname')->fetchColumn();
        } catch (Throwable $e) {
            return 'sans-base';
        }
        return $id = substr(sha1($nom . '|' . $hote), 0, 12);
    }

    function repere_fichier(string $nom): string
    {
        return dirname(__DIR__) . '/storage/reperes/' . preg_replace('/[^a-z0-9_-]/i', '', $nom) . '-' . repere_base_id() . '.ok';
    }

    function repere_ok(string $nom): bool
    {
        return repere_base_id() !== 'sans-base' && is_file(repere_fichier($nom));
    }

    function repere_poser(string $nom): void
    {
        if (repere_base_id() === 'sans-base') {
            return;
        }
        $f = repere_fichier($nom);
        if (!is_dir(dirname($f))) {
            @mkdir(dirname($f), 0775, true);
        }
        @file_put_contents($f, date('c'));
    }
}
