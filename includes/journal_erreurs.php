<?php
/**
 * Journal centralisé des erreurs du site (table journal_erreurs), consultable dans Super Admin → Sécurité.
 * - Avertissements PHP, exceptions non attrapées et erreurs fatales sont enregistrés automatiquement ;
 * - une même erreur (même message, fichier, ligne) n'occupe qu'une ligne : on compte ses répétitions ;
 * - aucune donnée de formulaire n'est enregistrée (seulement l'adresse de la page) ;
 * - l'erreur reste aussi dans le journal PHP habituel (error_log).
 */

if (!function_exists('journal_erreurs_noter')) {
    function journal_erreurs_niveau(int $type): string
    {
        $map = [
            E_ERROR => 'fatale', E_PARSE => 'fatale', E_CORE_ERROR => 'fatale', E_COMPILE_ERROR => 'fatale',
            E_USER_ERROR => 'erreur', E_RECOVERABLE_ERROR => 'erreur',
            E_WARNING => 'avertissement', E_USER_WARNING => 'avertissement', E_CORE_WARNING => 'avertissement',
            E_COMPILE_WARNING => 'avertissement',
        ];
        return $map[$type] ?? 'info';
    }

    /** Mémorise une erreur ; elle est écrite en base à la fin de la requête. */
    function journal_erreurs_noter(string $niveau, string $message, string $fichier = '', int $ligne = 0): void
    {
        $GLOBALS['journal_erreurs_tampon'] = $GLOBALS['journal_erreurs_tampon'] ?? [];
        if (count($GLOBALS['journal_erreurs_tampon']) >= 25) {
            return;
        }
        $racine = str_replace('\\', '/', dirname(__DIR__)) . '/';
        $GLOBALS['journal_erreurs_tampon'][] = [
            'niveau' => $niveau,
            'message' => mb_substr(trim(preg_replace('/\s+/', ' ', $message) ?? $message), 0, 1000),
            'fichier' => mb_substr(str_replace($racine, '', str_replace('\\', '/', $fichier)), 0, 255),
            'ligne' => $ligne,
        ];
    }

    function journal_erreurs_table(PDO $db): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        $repere = dirname(__DIR__) . '/storage/protection/journal_erreurs_v1';
        /* Le marqueur fichier peut exister sans la table (ex. sync local→prod) : toujours vérifier MySQL. */
        if (is_file($repere)) {
            try {
                $db->query('SELECT 1 FROM `journal_erreurs` LIMIT 1');
                return $ok = true;
            } catch (Throwable $e) {
                @unlink($repere);
            }
        }
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS `journal_erreurs` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `empreinte` CHAR(40) NOT NULL,
                `niveau` VARCHAR(16) NOT NULL,
                `message` TEXT NOT NULL,
                `fichier` VARCHAR(255) NOT NULL DEFAULT '',
                `ligne` INT NOT NULL DEFAULT 0,
                `url` VARCHAR(500) NOT NULL DEFAULT '',
                `methode` VARCHAR(8) NOT NULL DEFAULT '',
                `occurrences` INT UNSIGNED NOT NULL DEFAULT 1,
                `premiere` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `derniere` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `resolu` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_empreinte` (`empreinte`),
                KEY `idx_resolu_derniere` (`resolu`, `derniere`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            if (!is_dir(dirname($repere))) {
                @mkdir(dirname($repere), 0775, true);
            }
            @file_put_contents($repere, date('c'));
            return $ok = true;
        } catch (Throwable $e) {
            return $ok = false;
        }
    }

    /** Écrit les erreurs mémorisées (appelé à la fin de chaque requête). */
    function journal_erreurs_ecrire(): void
    {
        $tampon = $GLOBALS['journal_erreurs_tampon'] ?? [];
        $GLOBALS['journal_erreurs_tampon'] = [];
        if (!$tampon) {
            return;
        }
        global $db;
        if (!$db instanceof PDO) {
            $conn = dirname(__DIR__) . '/conn/conn.php';
            if (!is_file($conn)) {
                return;
            }
            try {
                require_once $conn;
            } catch (Throwable $e) {
                return;
            }
            if (!$db instanceof PDO) {
                return;
            }
        }
        if (!journal_erreurs_table($db)) {
            return;
        }
        $url = mb_substr((string) ($_SERVER['REQUEST_URI'] ?? (PHP_SAPI === 'cli' ? 'ligne de commande' : '')), 0, 500);
        $methode = mb_substr((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 0, 8);
        try {
            $st = $db->prepare("INSERT INTO journal_erreurs (empreinte, niveau, message, fichier, ligne, url, methode)
                VALUES (:e, :n, :m, :f, :l, :u, :me)
                ON DUPLICATE KEY UPDATE occurrences = occurrences + 1, derniere = NOW(), url = VALUES(url),
                    methode = VALUES(methode), resolu = 0");
            foreach ($tampon as $t) {
                /* Les chiffres variables (identifiants, durées) ne créent pas de nouvelles lignes */
                $cle = preg_replace('/\d+/', '#', $t['message']) . '|' . $t['fichier'] . '|' . $t['ligne'];
                $st->execute([
                    'e' => sha1($cle), 'n' => $t['niveau'], 'm' => $t['message'], 'f' => $t['fichier'],
                    'l' => $t['ligne'], 'u' => $url, 'me' => $methode,
                ]);
            }
        } catch (Throwable $e) {
            /* Le journal ne doit jamais casser une page */
        }
    }

    /* Avertissements PHP : notés puis laissés au traitement habituel (return false) */
    $journal_prec = set_error_handler(null);
    set_error_handler(static function (int $type, string $msg, string $file = '', int $line = 0) use ($journal_prec) {
        if (($type & (E_WARNING | E_USER_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR | E_CORE_WARNING | E_COMPILE_WARNING))
            && (error_reporting() & $type)) {
            journal_erreurs_noter(journal_erreurs_niveau($type), $msg, $file, $line);
        }
        return $journal_prec ? (bool) call_user_func($journal_prec, $type, $msg, $file, $line) : false;
    });
    unset($journal_prec);

    register_shutdown_function(static function () {
        $e = error_get_last();
        if ($e !== null && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            journal_erreurs_noter('fatale', (string) $e['message'], (string) $e['file'], (int) $e['line']);
        }
        journal_erreurs_ecrire();
    });
}
