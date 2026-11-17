<?php
/**
 * Contrôleur pour la gestion du panier
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_panier.php';
require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../models/model_variantes.php';
require_once __DIR__ . '/../includes/panier_invite.php';
require_once __DIR__ . '/../includes/panier_pricing.php';
require_once __DIR__ . '/../includes/site_csrf.php';
require_once __DIR__ . '/../includes/panier_added_snackbar.php';

/**
 * Ajoute au panier un produit au prix convenu via une négociation validée
 *
 * @param int $negotiation_id
 * @param int $user_id
 * @return array{success:bool, message?:string}
 */
function process_add_to_panier_from_negociation($negotiation_id, $user_id)
{
    $negotiation_id = (int) $negotiation_id;
    $user_id = (int) $user_id;

    if ($negotiation_id <= 0 || $user_id <= 0) {
        return ['success' => false, 'message' => 'Négociation invalide.'];
    }

    require_once __DIR__ . '/../models/model_prix_negociations.php';
    if (!prix_negociations_table_exists()) {
        return ['success' => false, 'message' => 'Négociation indisponible.'];
    }

    $neg = prix_negociation_get_by_id($negotiation_id);
    if (!$neg || (int) ($neg['user_id'] ?? 0) !== $user_id) {
        return ['success' => false, 'message' => 'Négociation introuvable.'];
    }

    if (!prix_negociation_peut_commander($neg)) {
        return ['success' => false, 'message' => 'Aucun prix convenu pour cette négociation.'];
    }

    $prix_convenu = prix_negociation_prix_convenu_effectif($neg);
    if ($prix_convenu === null || $prix_convenu <= 0) {
        return ['success' => false, 'message' => 'Prix convenu invalide.'];
    }

    $produit_id = (int) ($neg['produit_id'] ?? 0);
    $produit = get_produit_by_id($produit_id);
    if (!$produit || !produit_est_visible_client($produit['statut'] ?? '')) {
        return ['success' => false, 'message' => 'Ce produit n\'est plus disponible.'];
    }

    $opts = prix_negociation_options_json_decode($neg['options_json'] ?? null);
    $variante_id = (int) ($neg['variante_id'] ?? 0);
    if ($variante_id <= 0 && !empty($opts['variante_id'])) {
        $variante_id = (int) $opts['variante_id'];
    }

    $variante = null;
    if ($variante_id > 0) {
        $v = get_variante_by_id($variante_id);
        if ($v && (int) $v['produit_id'] === $produit_id) {
            $variante = $v;
        }
    }

    $couleur = trim((string) ($opts['couleur'] ?? ''));
    $poids = trim((string) ($opts['poids'] ?? ''));
    $taille = trim((string) ($opts['taille'] ?? ''));
    $surcout_poids = (float) ($opts['surcout_poids'] ?? 0);
    $surcout_taille = (float) ($opts['surcout_taille'] ?? 0);
    // Nom/image de variante uniquement depuis la base (variante du produit)
    $variante_nom = $variante ? (string) ($variante['nom'] ?? '') : '';
    $variante_image = $variante ? (string) ($variante['image'] ?? '') : '';
    $vendeur_id = !empty($produit['admin_id']) ? (int) $produit['admin_id'] : null;

    if (add_to_panier(
        $user_id,
        $produit_id,
        1,
        $couleur !== '' ? $couleur : null,
        $poids !== '' ? $poids : null,
        $taille !== '' ? $taille : null,
        $variante ? (int) $variante['id'] : null,
        $variante_nom !== '' ? $variante_nom : null,
        $variante_image !== '' ? $variante_image : null,
        $surcout_poids,
        $surcout_taille,
        $prix_convenu,
        $vendeur_id
    )) {
        panier_added_snackbar_mark($produit_id);
        return ['success' => true, 'message' => 'Produit ajouté au panier au prix convenu.'];
    }

    return ['success' => false, 'message' => 'Erreur lors de l\'ajout au panier.'];
}

/**
 * Traite l'ajout d'un produit au panier
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_add_to_panier()
{
    if (!site_csrf_verify_post()) {
        return ['success' => false, 'message' => site_csrf_fail_message()];
    }

    if (!isset($_POST['produit_id']) || !isset($_POST['quantite'])) {
        return ['success' => false, 'message' => 'Données manquantes.'];
    }

    $user_connecte = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
    $user_id = $user_connecte ? (int) $_SESSION['user_id'] : 0;
    $produit_id = (int) $_POST['produit_id'];
    $quantite = (int) $_POST['quantite'];

    $option_couleur = isset($_POST['option_couleur']) ? trim($_POST['option_couleur']) : '';
    $option_poids = isset($_POST['option_poids']) ? trim($_POST['option_poids']) : '';
    $option_taille = isset($_POST['option_taille']) ? trim($_POST['option_taille']) : '';
    $option_variante_id = isset($_POST['option_variante_id']) ? (int)$_POST['option_variante_id'] : null;
    $option_variante_nom = isset($_POST['option_variante_nom']) ? trim($_POST['option_variante_nom']) : null;
    $option_variante_image = isset($_POST['option_variante_image']) ? trim($_POST['option_variante_image']) : null;

    if ($quantite <= 0) {
        return ['success' => false, 'message' => 'La quantité doit être supérieure à 0.'];
    }

    // Vérifier que le produit existe et est publié côté client
    $produit = get_produit_by_id($produit_id);
    if (!$produit || !produit_est_visible_client($produit['statut'] ?? '')) {
        return ['success' => false, 'message' => 'Ce produit n\'est pas disponible.'];
    }

    // Validation des options si le produit en a
    $couleurs_options = [];
    $poids_options = parse_options_with_surcharge($produit['poids'] ?? null);
    $taille_options = function_exists('produit_client_taille_options')
        ? produit_client_taille_options($produit)
        : parse_options_with_surcharge($produit['taille'] ?? null);
    if (!empty($produit['couleurs'])) {
        $cr = trim($produit['couleurs']);
        $dec = json_decode($cr, true);
        if (is_array($dec)) {
            $couleurs_options = array_filter($dec, function ($c) {
                return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
            });
        }
        if (empty($couleurs_options)) {
            $couleurs_options = array_map('trim', array_filter(explode(',', $cr)));
        }
    }
    $poids_values = array_map(function($x) { return $x['v']; }, $poids_options);
    $taille_values = array_map(function($x) { return $x['v']; }, $taille_options);
    if (!empty($taille_options)) {
        if ($option_taille === '') {
            return ['success' => false, 'message' => 'Choisissez une taille avant d\'ajouter au panier.'];
        }
        $taille_ok = false;
        foreach ($taille_values as $tv) {
            if (strcasecmp((string) $tv, $option_taille) === 0) {
                $taille_ok = true;
                // Étiquette canonique du produit (celle qui sert au calcul du surcoût)
                $option_taille = (string) $tv;
                break;
            }
        }
        if (!$taille_ok) {
            return ['success' => false, 'message' => 'Taille sélectionnée invalide.'];
        }
    } else {
        // Pas de taille proposée pour ce produit : ignorer la saisie du client
        $option_taille = '';
    }

    // Poids : validé contre les options du produit comme la taille
    if ($option_poids !== '') {
        $poids_ok = false;
        foreach ($poids_values as $pv) {
            if (strcasecmp((string) $pv, $option_poids) === 0) {
                $poids_ok = true;
                $option_poids = (string) $pv;
                break;
            }
        }
        if (!$poids_ok) {
            if (!empty($poids_options)) {
                return ['success' => false, 'message' => 'Poids sélectionné invalide.'];
            }
            // Pas de poids proposé pour ce produit : ignorer la saisie du client
            $option_poids = '';
        }
    }

    // Variante sélectionnée (pour nom/image) : doit appartenir au produit
    $variante = ($option_variante_id && ($v = get_variante_by_id($option_variante_id)) && $v['produit_id'] == $produit_id) ? $v : null;
    if ($option_variante_id && $option_variante_id > 0 && !$variante) {
        return ['success' => false, 'message' => 'Variante sélectionnée invalide.'];
    }
    $surcout_poids = get_surcharge_for_option($poids_options, $option_poids);
    $surcout_taille = get_surcharge_for_option($taille_options, $option_taille);

    $prix_final = panier_compute_line_price_from_post($produit, $variante, $option_poids, $option_taille);

    // Ajouter au panier avec options
    // Nom/image de variante uniquement depuis la base (jamais la saisie du client)
    $vid = $variante ? (int) $variante['id'] : null;
    $vnom = $variante ? $variante['nom'] : null;
    $vimg = $variante ? $variante['image'] : null;
    $vendeur_id = !empty($produit['admin_id']) ? (int) $produit['admin_id'] : null;

    if ($user_connecte) {
        if (add_to_panier($user_id, $produit_id, $quantite, $option_couleur ?: null, $option_poids ?: null, $option_taille ?: null,
            $vid, $vnom, $vimg, $surcout_poids, $surcout_taille, $prix_final, $vendeur_id)) {
            require_once __DIR__ . '/../includes/client_tracking.php';
            client_tracking_log_event('panier_ajout', ['produit' => $produit, 'produit_id' => $produit_id]);
            panier_added_snackbar_mark($produit_id);
            return ['success' => true, 'message' => 'Produit ajouté au panier avec succès.'];
        }
        return ['success' => false, 'message' => 'Erreur lors de l\'ajout au panier.'];
    }

    if (panier_invite_add_line($produit_id, $quantite, $option_couleur ?: null, $option_poids ?: null, $option_taille ?: null,
        $vid, $vnom, $vimg, $surcout_poids, $surcout_taille, $prix_final, $vendeur_id)) {
        require_once __DIR__ . '/../includes/client_tracking.php';
        client_tracking_log_event('panier_ajout', ['produit' => $produit, 'produit_id' => $produit_id]);
        panier_added_snackbar_mark($produit_id);
        return ['success' => true, 'message' => 'Produit ajouté au panier avec succès.'];
    }
    return ['success' => false, 'message' => 'Erreur lors de l\'ajout au panier.'];
}

/**
 * Traite la mise à jour de la quantité d'un produit dans le panier
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_update_panier()
{
    if (!site_csrf_verify_post()) {
        return ['success' => false, 'message' => site_csrf_fail_message()];
    }

    if (!isset($_POST['panier_id']) || !isset($_POST['quantite'])) {
        return ['success' => false, 'message' => 'Données manquantes.'];
    }

    $panier_id = (int) $_POST['panier_id'];
    $quantite = (int) $_POST['quantite'];

    if ($quantite <= 0) {
        return ['success' => false, 'message' => 'La quantité doit être supérieure à 0.'];
    }

    if (!isset($_SESSION['user_id'])) {
        if (panier_invite_update_quantite($panier_id, $quantite)) {
            return ['success' => true, 'message' => 'Quantité mise à jour.'];
        }
        return ['success' => false, 'message' => 'Élément du panier introuvable.'];
    }

    // Récupérer l'élément du panier
    $panier_items = get_panier_by_user($_SESSION['user_id']);
    $item = null;
    foreach ($panier_items as $panier_item) {
        if ($panier_item['panier_id'] == $panier_id) {
            $item = $panier_item;
            break;
        }
    }

    if (!$item) {
        return ['success' => false, 'message' => 'Élément du panier introuvable.'];
    }

    // Une négociation vaut pour UNE unité : pas de quantité > 1 sur une ligne au prix négocié
    if ($quantite > 1 && panier_line_uses_negociation_price($item, (int) $_SESSION['user_id'])) {
        return ['success' => false, 'message' => 'Le prix négocié est valable pour une seule unité. Ajoutez les autres unités au prix normal depuis la fiche produit.'];
    }

    // Mettre à jour
    if (update_panier_quantite($panier_id, $quantite, (int) $_SESSION['user_id'])) {
        return ['success' => true, 'message' => 'Quantité mise à jour.'];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
    }
}

/**
 * Traite la suppression d'un produit du panier
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_delete_from_panier()
{
    if (!site_csrf_verify_post()) {
        return ['success' => false, 'message' => site_csrf_fail_message()];
    }

    if (!isset($_POST['panier_id'])) {
        return ['success' => false, 'message' => 'Données manquantes.'];
    }

    $panier_id = (int) $_POST['panier_id'];

    if (!isset($_SESSION['user_id'])) {
        if (panier_invite_delete_line($panier_id)) {
            return ['success' => true, 'message' => 'Produit retiré du panier.'];
        }
        return ['success' => false, 'message' => 'Erreur lors de la suppression.'];
    }

    if (delete_from_panier($panier_id, (int) $_SESSION['user_id'])) {
        return ['success' => true, 'message' => 'Produit retiré du panier.'];
    } else {
        return ['success' => false, 'message' => 'Erreur lors de la suppression.'];
    }
}

/**
 * Applique un code promo au panier (session).
 *
 * @return array{success:bool, message:string}
 */
function process_apply_code_promo()
{
    if (!site_csrf_verify_post()) {
        return ['success' => false, 'message' => site_csrf_fail_message()];
    }

    require_once __DIR__ . '/../includes/panier_code_promo.php';
    require_once __DIR__ . '/../includes/panier_invite.php';

    $code = isset($_POST['code_promo']) ? trim((string) $_POST['code_promo']) : '';
    if ($code === '') {
        return ['success' => false, 'message' => 'Saisissez un code promo.'];
    }

    $sous_total = panier_get_total_courant();
    $check = codes_promo_validate_for_panier($code, $sous_total);
    if (!$check['ok'] || empty($check['promo'])) {
        return ['success' => false, 'message' => $check['message'] ?? 'Code promo invalide.'];
    }

    panier_code_promo_session_set($check['promo']);
    return ['success' => true, 'message' => ''];
}

/**
 * Retire le code promo du panier.
 *
 * @return array{success:bool, message:string}
 */
function process_remove_code_promo()
{
    if (!site_csrf_verify_post()) {
        return ['success' => false, 'message' => site_csrf_fail_message()];
    }

    require_once __DIR__ . '/../includes/panier_code_promo.php';
    panier_code_promo_session_clear();
    return ['success' => true, 'message' => ''];
}

?>