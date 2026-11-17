<?php
/**
 * Modèle pour la gestion des favoris
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Ajoute un produit aux favoris
 * @param int $user_id L'ID de l'utilisateur
 * @param int $produit_id L'ID du produit
 * @return bool True en cas de succès, False sinon
 */
function add_favori($user_id, $produit_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO favoris (user_id, produit_id, date_ajout) 
            VALUES (:user_id, :produit_id, NOW())
            ON DUPLICATE KEY UPDATE date_ajout = NOW()
        ");
        
        return $stmt->execute([
            'user_id' => $user_id,
            'produit_id' => $produit_id
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime un produit des favoris
 * @param int $user_id L'ID de l'utilisateur
 * @param int $produit_id L'ID du produit
 * @return bool True en cas de succès, False sinon
 */
function remove_favori($user_id, $produit_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("DELETE FROM favoris WHERE user_id = :user_id AND produit_id = :produit_id");
        $ok = $stmt->execute([
            'user_id' => $user_id,
            'produit_id' => $produit_id
        ]);
        if ($ok && file_exists(__DIR__ . '/../services/notification_favoris_relance.php')) {
            $st = $db->prepare('SELECT COUNT(*) FROM favoris WHERE user_id = :user_id');
            $st->execute(['user_id' => (int) $user_id]);
            if ((int) $st->fetchColumn() === 0) {
                require_once __DIR__ . '/../services/notification_favoris_relance.php';
                notification_favoris_relance_reset((int) $user_id);
            }
        }
        return $ok;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Vérifie si un produit est dans les favoris
 * @param int $user_id L'ID de l'utilisateur
 * @param int $produit_id L'ID du produit
 * @return bool True si favori, False sinon
 */
function is_favori($user_id, $produit_id) {
    global $db;

    $user_id = (int) $user_id;
    $produit_id = (int) $produit_id;
    if ($user_id <= 0 || $produit_id <= 0) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM favoris WHERE user_id = :user_id AND produit_id = :produit_id");
        $stmt->execute([
            'user_id' => $user_id,
            'produit_id' => $produit_id
        ]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * IDs produits favoris d'un utilisateur (pour marquer les cartes catalogue).
 *
 * @param int $user_id
 * @return int[]
 */
function get_favori_produit_ids_by_user($user_id) {
    global $db;

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    try {
        $stmt = $db->prepare('SELECT produit_id FROM favoris WHERE user_id = :user_id ORDER BY date_ajout DESC');
        $stmt->execute(['user_id' => $user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$rows) {
            return [];
        }

        return array_map('intval', $rows);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Ajoute ou retire un produit des favoris.
 *
 * @param int $user_id
 * @param int $produit_id
 * @return array{success:bool, favori?:bool, message?:string}
 */
function toggle_favori($user_id, $produit_id) {
    $user_id = (int) $user_id;
    $produit_id = (int) $produit_id;
    if ($user_id <= 0 || $produit_id <= 0) {
        return ['success' => false, 'message' => 'Données invalides.'];
    }

    if (is_favori($user_id, $produit_id)) {
        if (remove_favori($user_id, $produit_id)) {
            return ['success' => true, 'favori' => false];
        }
        return ['success' => false, 'message' => 'Impossible de retirer ce favori.'];
    }

    if (add_favori($user_id, $produit_id)) {
        require_once __DIR__ . '/model_produits.php';
        require_once __DIR__ . '/model_alerte_prix.php';
        $p = get_produit_by_id($produit_id);
        if (is_array($p)) {
            favoris_snapshot_enregistrer_prix_ref($user_id, $produit_id, $p);
        }
        if (file_exists(__DIR__ . '/../services/notification_favoris_relance.php')) {
            require_once __DIR__ . '/../services/notification_favoris_relance.php';
            notification_favoris_relance_on_favori_add($user_id);
        }
        return ['success' => true, 'favori' => true];
    }

    return ['success' => false, 'message' => 'Impossible d\'ajouter aux favoris.'];
}

/**
 * Récupère tous les favoris d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @return array|false Tableau des favoris ou False en cas d'erreur
 */
function get_favoris_by_user($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT f.id AS favori_id, f.user_id, f.produit_id, f.date_ajout,
                   p.id, p.nom, p.description, p.prix, p.prix_promotion, p.stock,
                   p.categorie_id, p.image_principale, p.images, p.poids, p.unite,
                   p.statut, p.date_creation, p.date_modification,
                   c.nom AS categorie_nom
            FROM favoris f
            INNER JOIN produits p ON f.produit_id = p.id
            LEFT JOIN categories c ON p.categorie_id = c.id
            WHERE f.user_id = :user_id
            ORDER BY f.date_ajout DESC
        ");
        
        $stmt->execute(['user_id' => $user_id]);
        $favoris = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $favoris ? $favoris : [];
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Compte le nombre de favoris d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @return int Le nombre de favoris
 */
function count_favoris_by_user($user_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM favoris WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Produits les plus mis en favori (proxy « coups de cœur / mieux notés » par la communauté).
 *
 * @param int $max_rows Candidats max avant mélange accueil
 * @return array
 */
function get_produits_plus_favoris_marketplace($max_rows = 60) {
    global $db;
    $max_rows = max(5, (int) $max_rows);
    try {
        require_once __DIR__ . '/model_produits.php';
        $vj = produits_sql_vendeur_fragment();
        $sql = "
            SELECT p.*, MAX(c.nom) AS categorie_nom, COUNT(f.id) AS nb_favoris
            " . $vj['select'] . "
            FROM produits p
            INNER JOIN favoris f ON f.produit_id = p.id
            LEFT JOIN categories c ON p.categorie_id = c.id
            " . $vj['join'] . "
            WHERE p.statut IN ('actif', 'rupture_stock')
            GROUP BY p.id
            ORDER BY nb_favoris DESC, p.id DESC
            LIMIT " . (int) $max_rows;
        $stmt = $db->query($sql);
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $produits ? $produits : [];
    } catch (PDOException $e) {
        return [];
    }
}
