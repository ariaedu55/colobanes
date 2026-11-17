<?php
/**
 * Chiffrement des mots de passe et codes PIN.
 *
 * - Argon2id (vainqueur du Password Hashing Competition, recommandé par l'OWASP) quand PHP le propose ;
 *   réglages OWASP : 19 Mo de mémoire, 2 passes, 1 fil → résiste aux cartes graphiques sans surcharger le serveur.
 * - Sinon bcrypt coût 12 (4 fois plus lent à attaquer que le coût 10 par défaut).
 * - Les anciens mots de passe sont convertis automatiquement à la connexion suivante
 *   (securite_mdp_rehash_si_besoin) : personne n'a à changer son mot de passe.
 */

if (!function_exists('securite_mdp_algo')) {
    function securite_mdp_algo(): array
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return [PASSWORD_ARGON2ID, ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1]];
        }
        return [PASSWORD_BCRYPT, ['cost' => 12]];
    }

    function securite_mdp_hash(string $secret): string
    {
        [$algo, $opts] = securite_mdp_algo();
        $h = password_hash($secret, $algo, $opts);
        /* Repli de sécurité : ne jamais renvoyer une valeur vide ou fausse */
        return is_string($h) && $h !== '' ? $h : password_hash($secret, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * À appeler juste après un password_verify() réussi : remplace l'ancien chiffrement par le plus fort disponible.
     * $table est limité à la liste ci-dessous (jamais une valeur venant de l'utilisateur).
     */
    function securite_mdp_rehash_si_besoin(string $table, int $id, string $secret, string $hash_actuel): void
    {
        static $tables = ['users', 'admin', 'super_admin', 'vendeur_comptes_acces'];
        if ($id <= 0 || $secret === '' || !in_array($table, $tables, true)) {
            return;
        }
        [$algo, $opts] = securite_mdp_algo();
        if (!password_needs_rehash($hash_actuel, $algo, $opts)) {
            return;
        }
        global $db;
        if (!$db instanceof PDO) {
            return;
        }
        try {
            $st = $db->prepare("UPDATE `$table` SET password = :p WHERE id = :id AND password = :old");
            $st->execute(['p' => securite_mdp_hash($secret), 'id' => $id, 'old' => $hash_actuel]);
        } catch (Throwable $e) {
            error_log('[securite_mdp] rehash ' . $table . ' : ' . $e->getMessage());
        }
    }
}
