<?php
/**
 * Modèle pour la gestion des contacts (manuels)
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * Indique si contacts.admin_id existe (carnet par boutique).
 */
function contacts_has_admin_id_column() {
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $ok = (bool) $db->query("SHOW COLUMNS FROM contacts LIKE 'admin_id'")->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * Récupère tous les contacts
 * @param string|null $recherche Recherche sur nom, prénom, téléphone
 * @param int|null $admin_id Si > 0 : carnet de cette boutique uniquement
 * @return array
 */
function get_all_contacts($recherche = null, $admin_id = null) {
    global $db;
    try {
        $sql = "SELECT * FROM contacts WHERE 1=1";
        $params = [];
        if ($admin_id !== null && (int) $admin_id > 0 && contacts_has_admin_id_column()) {
            $sql .= " AND admin_id = :admin_id";
            $params['admin_id'] = (int) $admin_id;
        }
        if (!empty(trim($recherche ?? ''))) {
            $term = '%' . trim($recherche) . '%';
            $sql .= " AND (nom LIKE :term OR prenom LIKE :term2 OR telephone LIKE :term3 OR email LIKE :term4)";
            $params['term'] = $term;
            $params['term2'] = $term;
            $params['term3'] = $term;
            $params['term4'] = $term;
        }
        $sql .= " ORDER BY nom ASC, prenom ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère un contact par téléphone (chiffres uniquement, comparaison stricte)
 * @param int|null $admin_id Limite au carnet boutique si > 0
 */
function get_contact_by_telephone($telephone, $admin_id = null) {
    global $db;
    $tel = preg_replace('/\D+/', '', $telephone ?? '');
    if ($tel === '') {
        return false;
    }
    try {
        if ($admin_id !== null && (int) $admin_id > 0 && contacts_has_admin_id_column()) {
            $stmt = $db->prepare('SELECT * FROM contacts WHERE admin_id = ?');
            $stmt->execute([(int) $admin_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $db->query('SELECT * FROM contacts');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        foreach ($rows as $r) {
            $t2 = preg_replace('/\D+/', '', $r['telephone'] ?? '');
            if ($t2 !== '' && $t2 === $tel) {
                return $r;
            }
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Carnet contacts : si aucune ligne avec ce numéro (normalisé), crée le contact.
 * Utilisé lors de la création d'un bon de livraison.
 *
 * @param int|null $admin_id Boutique propriétaire du carnet (null = plateforme)
 * @return int|false id du contact existant ou créé
 */
function ensure_contact_from_bl($nom, $prenom, $telephone, $email = null, $admin_id = null) {
    $telephone = trim($telephone ?? '');
    if ($telephone === '') {
        return false;
    }
    $existing = get_contact_by_telephone($telephone, $admin_id);
    if ($existing) {
        return (int) $existing['id'];
    }
    $id = create_contact(
        trim($nom ?? ''),
        trim($prenom ?? ''),
        $telephone,
        $email && trim($email) !== '' ? trim($email) : null,
        $admin_id
    );
    return $id ? (int) $id : false;
}

/**
 * Vérifie si un téléphone existe (users ou contacts)
 */
function telephone_exists_in_users_or_contacts($telephone) {
    global $db;
    $tel = preg_replace('/\D/', '', $telephone);
    if (empty($tel) || strlen($tel) < 8) return false;
    try {
        $stmt = $db->prepare("
            SELECT 1 FROM users WHERE REPLACE(REPLACE(REPLACE(COALESCE(telephone,''), ' ', ''), '-', ''), '+', '') LIKE :tel
            UNION ALL
            SELECT 1 FROM contacts WHERE REPLACE(REPLACE(REPLACE(COALESCE(telephone,''), ' ', ''), '-', ''), '+', '') LIKE :tel2
            LIMIT 1
        ");
        $stmt->execute(['tel' => '%' . $tel . '%', 'tel2' => '%' . $tel . '%']);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un contact par ID
 */
function get_contact_by_id($id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM contacts WHERE id = :id");
        $stmt->execute(['id' => (int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour un contact
 * @param int|null $admin_id Si > 0, n'autorise que les contacts de cette boutique
 */
function update_contact($id, $nom, $prenom, $telephone, $email = null, $admin_id = null) {
    global $db;
    try {
        $sql = "UPDATE contacts SET nom = :nom, prenom = :prenom, telephone = :telephone, email = :email WHERE id = :id";
        $params = [
            'id' => (int) $id,
            'nom' => trim($nom),
            'prenom' => trim($prenom),
            'telephone' => trim($telephone),
            'email' => $email && trim($email) !== '' ? trim($email) : null
        ];
        if ($admin_id !== null && (int) $admin_id > 0 && contacts_has_admin_id_column()) {
            $sql .= " AND admin_id = :admin_id";
            $params['admin_id'] = (int) $admin_id;
        }
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un contact
 * @param int|null $admin_id Boutique propriétaire (null = carnet plateforme)
 */
function create_contact($nom, $prenom, $telephone, $email = null, $admin_id = null) {
    global $db;
    try {
        if (contacts_has_admin_id_column()) {
            $stmt = $db->prepare("INSERT INTO contacts (admin_id, nom, prenom, telephone, email) VALUES (:admin_id, :nom, :prenom, :telephone, :email)");
            $stmt->execute([
                'admin_id' => ($admin_id !== null && (int) $admin_id > 0) ? (int) $admin_id : null,
                'nom' => trim($nom),
                'prenom' => trim($prenom),
                'telephone' => trim($telephone),
                'email' => $email && trim($email) !== '' ? trim($email) : null
            ]);
        } else {
            $stmt = $db->prepare("INSERT INTO contacts (nom, prenom, telephone, email) VALUES (:nom, :prenom, :telephone, :email)");
            $stmt->execute([
                'nom' => trim($nom),
                'prenom' => trim($prenom),
                'telephone' => trim($telephone),
                'email' => $email && trim($email) !== '' ? trim($email) : null
            ]);
        }
        return $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Recherche clients (users + contacts) pour commande manuelle.
 * Avec $vendeur_id : uniquement les clients ayant déjà commandé dans cette boutique,
 * ou un compte retrouvé par son e-mail / téléphone exact (pas d'accès à tout le fichier clients).
 */
function search_clients_for_commande($recherche, $limit = 20, $vendeur_id = null) {
    global $db;
    $term = '%' . trim($recherche) . '%';
    if (strlen(trim($recherche)) < 1) return [];
    if ($vendeur_id !== null && (int) $vendeur_id > 0) {
        $exact = trim((string) $recherche);
        $tel = preg_replace('/\D+/', '', $exact);
        try {
            $stmt = $db->prepare("
                SELECT u.id, u.nom, u.prenom, u.telephone, u.email, 'user' AS source
                FROM users u
                WHERE u.statut = 'actif'
                  AND (
                    (EXISTS (
                        SELECT 1 FROM commandes c
                        INNER JOIN commande_produits cp ON cp.commande_id = c.id
                        INNER JOIN produits p ON p.id = cp.produit_id
                        WHERE c.user_id = u.id AND p.admin_id = :vid
                    ) AND (u.nom LIKE :t1 OR u.prenom LIKE :t2 OR u.email LIKE :t3 OR u.telephone LIKE :t4))
                    OR u.email = :exact
                    OR (:tel_len >= 8 AND REPLACE(REPLACE(REPLACE(u.telephone, ' ', ''), '+', ''), '-', '') LIKE :tel)
                  )
                LIMIT :limit
            ");
            $stmt->bindValue('vid', (int) $vendeur_id, PDO::PARAM_INT);
            foreach (['t1', 't2', 't3', 't4'] as $k) {
                $stmt->bindValue($k, $term, PDO::PARAM_STR);
            }
            $stmt->bindValue('exact', $exact, PDO::PARAM_STR);
            $stmt->bindValue('tel_len', strlen($tel), PDO::PARAM_INT);
            $stmt->bindValue('tel', '%' . $tel, PDO::PARAM_STR);
            $stmt->bindValue('limit', (int) $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }
    try {
        $stmt = $db->prepare("
            (SELECT id, nom, prenom, telephone, email, 'user' as source FROM users WHERE statut = 'actif' AND (nom LIKE :t1 OR prenom LIKE :t2 OR email LIKE :t3 OR telephone LIKE :t4))
            UNION ALL
            (SELECT id, nom, prenom, telephone, email, 'contact' as source FROM contacts WHERE nom LIKE :t5 OR prenom LIKE :t6 OR email LIKE :t7 OR telephone LIKE :t8)
            LIMIT :limit
        ");
        $stmt->bindValue('t1', $term, PDO::PARAM_STR);
        $stmt->bindValue('t2', $term, PDO::PARAM_STR);
        $stmt->bindValue('t3', $term, PDO::PARAM_STR);
        $stmt->bindValue('t4', $term, PDO::PARAM_STR);
        $stmt->bindValue('t5', $term, PDO::PARAM_STR);
        $stmt->bindValue('t6', $term, PDO::PARAM_STR);
        $stmt->bindValue('t7', $term, PDO::PARAM_STR);
        $stmt->bindValue('t8', $term, PDO::PARAM_STR);
        $stmt->bindValue('limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}
