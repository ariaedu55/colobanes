<?php
/**
 * Messages envoyés depuis la page Contact (contact.php), lus dans le super admin.
 */
require_once __DIR__ . '/../conn/conn.php';

if (!function_exists('contact_messages_ready')) {

    function contact_messages_ready(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        global $db;
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS contact_messages (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                nom VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL,
                telephone VARCHAR(40) NOT NULL DEFAULT '',
                sujet VARCHAR(30) NOT NULL,
                numero_commande VARCHAR(40) NOT NULL DEFAULT '',
                message TEXT NOT NULL,
                statut ENUM('nouveau','traite') NOT NULL DEFAULT 'nouveau',
                ip_hash CHAR(64) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_statut (statut, created_at),
                KEY idx_ip (ip_hash, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $ok = true;
        } catch (PDOException $e) {
            error_log('[contact_messages_ready] ' . $e->getMessage());
            $ok = false;
        }
        return $ok;
    }

    function contact_sujets(): array
    {
        return [
            'commande' => 'Une commande',
            'livraison' => 'Une livraison',
            'vendeur' => 'Un vendeur ou une boutique',
            'compte' => 'Mon compte',
            'vendre' => 'Vendre sur COLObanes',
            'partenariat' => 'Partenariat / presse',
            'autre' => 'Autre question',
        ];
    }

    /**
     * Valide et enregistre un message (limite : 3 envois par 10 minutes et par connexion).
     *
     * @return array{ok: bool, errors?: array}
     */
    function contact_message_save(array $in, int $user_id = 0): array
    {
        if (!contact_messages_ready()) {
            return ['ok' => false, 'errors' => ['Service momentanément indisponible. Écrivez-nous à contact@colobanes.com.']];
        }
        global $db;
        $nom = trim(mb_substr((string) ($in['nom'] ?? ''), 0, 120));
        $email = trim(mb_substr((string) ($in['email'] ?? ''), 0, 190));
        $tel = trim(mb_substr((string) ($in['telephone'] ?? ''), 0, 40));
        $sujet = (string) ($in['sujet'] ?? '');
        $cmd = trim(mb_substr((string) ($in['numero_commande'] ?? ''), 0, 40));
        $message = trim((string) ($in['message'] ?? ''));

        $errors = [];
        if (mb_strlen($nom) < 2) {
            $errors[] = 'Indiquez votre nom.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Indiquez une adresse e-mail valide pour recevoir la réponse.';
        }
        if (!isset(contact_sujets()[$sujet])) {
            $errors[] = 'Choisissez le sujet de votre message.';
        }
        if (mb_strlen($message) < 10 || mb_strlen($message) > 3000) {
            $errors[] = 'Votre message doit faire entre 10 et 3 000 caractères.';
        }
        if ($tel !== '' && !preg_match('/^[0-9 +().-]{6,40}$/', $tel)) {
            $errors[] = 'Le numéro de téléphone n’est pas valide.';
        }

        $ip_hash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|colobanes-contact');
        $st = $db->prepare('SELECT COUNT(*) FROM contact_messages WHERE ip_hash = :h AND created_at > (NOW() - INTERVAL 10 MINUTE)');
        $st->execute(['h' => $ip_hash]);
        if ((int) $st->fetchColumn() >= 3) {
            $errors[] = 'Vous avez déjà envoyé plusieurs messages. Merci de patienter quelques minutes.';
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $db->prepare('INSERT INTO contact_messages (user_id, nom, email, telephone, sujet, numero_commande, message, ip_hash)
            VALUES (:u, :n, :e, :t, :s, :c, :m, :h)')->execute([
            'u' => $user_id > 0 ? $user_id : null, 'n' => $nom, 'e' => $email, 't' => $tel,
            's' => $sujet, 'c' => $cmd, 'm' => $message, 'h' => $ip_hash,
        ]);
        return ['ok' => true];
    }

    function contact_messages_list(string $statut = 'nouveau', int $limit = 100): array
    {
        if (!contact_messages_ready()) {
            return [];
        }
        global $db;
        $st = $db->prepare('SELECT * FROM contact_messages WHERE statut = :s ORDER BY created_at DESC LIMIT ' . (int) $limit);
        $st->execute(['s' => $statut === 'traite' ? 'traite' : 'nouveau']);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    function contact_messages_count_new(): int
    {
        if (!contact_messages_ready()) {
            return 0;
        }
        global $db;
        return (int) $db->query("SELECT COUNT(*) FROM contact_messages WHERE statut = 'nouveau'")->fetchColumn();
    }

    function contact_message_set_statut(int $id, string $statut): bool
    {
        if (!contact_messages_ready() || !in_array($statut, ['nouveau', 'traite'], true)) {
            return false;
        }
        global $db;
        $st = $db->prepare('UPDATE contact_messages SET statut = :s WHERE id = :id');
        $st->execute(['s' => $statut, 'id' => $id]);
        return $st->rowCount() > 0;
    }
}
