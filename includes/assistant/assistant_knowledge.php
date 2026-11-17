<?php
/**
 * Base de connaissances de l'assistant (« entraînement ») : tout le fonctionnement de COLObanes.
 * Les réponses reprennent les écrans réels du site et les conditions d'utilisation.
 * Elle est répartie par thème dans includes/assistant/kb/*.php (chaque fichier renvoie une liste d'entrées).
 *
 * Chaque entrée :
 *  - id, title, audience (client | vendeur | tous)
 *  - keywords  : expressions fortes, sans accents, en minuscules (une correspondance exacte pèse lourd)
 *  - questions : phrases d'entraînement (façons de demander), comparées mot à mot avec tolérance aux fautes
 *  - answer    : réponse (markdown léger) ; {{jeton}} = donnée vivante remplie par assistant_kb_render()
 *  - links     : [libellé, chemin]
 */

if (!function_exists('assistant_knowledge')) {
    function assistant_knowledge(): array
    {
        static $kb = null;
        if ($kb !== null) {
            return $kb;
        }
        $kb = [];
        foreach (['decouvrir', 'compte', 'commandes', 'echanges', 'dons', 'confiance', 'vendeur'] as $theme) {
            $file = __DIR__ . '/kb/' . $theme . '.php';
            if (is_file($file)) {
                $entries = require $file;
                if (is_array($entries)) {
                    foreach ($entries as $e) {
                        $kb[] = $e + ['audience' => 'tous', 'keywords' => [], 'questions' => [], 'links' => []];
                    }
                }
            }
        }
        return $kb;
    }
}
