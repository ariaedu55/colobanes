<?php
require_once __DIR__ . '/../includes/securite_mdp.php';
/**
 * Contrôleur Super Administrateur (marketplace)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_super_admin.php';
require_once __DIR__ . '/../includes/super_admin_csrf.php';
require_once __DIR__ . '/../includes/login_rate_limit.php';

/**
 * Valide un mot de passe super admin
 * @return array{ok:bool,message:string}
 */
function super_admin_validate_password($password, $password_confirm = null) {
    if ($password === '') {
        return ['ok' => false, 'message' => 'Le mot de passe est obligatoire.'];
    }
    if (strlen($password) < 10) {
        return ['ok' => false, 'message' => 'Le mot de passe doit contenir au moins 10 caractères.'];
    }
    if (!preg_match('/[A-Z]/u', $password) || !preg_match('/[a-z]/u', $password) || !preg_match('/[0-9]/', $password)) {
        return ['ok' => false, 'message' => 'Le mot de passe doit contenir une majuscule, une minuscule et un chiffre.'];
    }
    if ($password_confirm !== null && $password !== $password_confirm) {
        return ['ok' => false, 'message' => 'Les mots de passe ne correspondent pas.'];
    }
    return ['ok' => true, 'message' => ''];
}

/**
 * Création d'un compte super admin par un super admin connecté
 * @return array{success:bool,message:string}
 */
function process_super_admin_create_account() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    $errors = [];
    $nom = isset($_POST['nom']) ? trim((string) $_POST['nom']) : '';
    $email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? (string) $_POST['password_confirm'] : '';

    if ($nom === '' || strlen($nom) < 2) {
        $errors[] = 'Le nom est obligatoire (au moins 2 caractères).';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email est invalide.';
    } elseif (super_admin_email_exists($email)) {
        $errors[] = 'Cet email est déjà utilisé.';
    }

    $pw_check = super_admin_validate_password($password, $password_confirm);
    if (!$pw_check['ok']) {
        $errors[] = $pw_check['message'];
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($csrf)) {
        $errors[] = 'Session expirée ou formulaire invalide. Réessayez.';
    }

    /* Action la plus sensible : le super admin connecté confirme avec son propre mot de passe */
    $actor_row = get_super_admin_by_id((int) ($_SESSION['super_admin_id'] ?? 0));
    $actor_password = isset($_POST['actor_password']) ? (string) $_POST['actor_password'] : '';
    if (!$actor_row || $actor_password === '' || !password_verify($actor_password, (string) ($actor_row['password'] ?? ''))) {
        $errors[] = 'Votre mot de passe actuel est incorrect.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    $hash = securite_mdp_hash($password);
    $id = create_super_admin($nom, '', $email, $hash);
    if (!$id) {
        return ['success' => false, 'message' => 'Erreur lors de la création du compte.'];
    }

    $actor_id = (int) ($_SESSION['super_admin_id'] ?? 0);
    if ($actor_id > 0) {
        super_admin_log_action($actor_id, 'super_admin_cree', 'super_admin', $id, $email);
    }

    return ['success' => true, 'message' => 'Compte super administrateur créé avec succès.'];
}

/**
 * Mise à jour du profil du super admin connecté
 * @return array{success:bool,message:string}
 */
function process_super_admin_update_own_profile() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    $errors = [];
    $sa_id = (int) ($_SESSION['super_admin_id'] ?? 0);
    $nom = isset($_POST['nom']) ? trim((string) $_POST['nom']) : '';
    $prenom = isset($_POST['prenom']) ? trim((string) $_POST['prenom']) : '';
    $email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';

    if ($sa_id <= 0) {
        $errors[] = 'Session invalide.';
    }
    if ($nom === '' || strlen($nom) < 2) {
        $errors[] = 'Le nom est obligatoire (au moins 2 caractères).';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email est invalide.';
    } else {
        $existing = get_super_admin_by_email($email);
        if ($existing && (int) ($existing['id'] ?? 0) !== $sa_id) {
            $errors[] = 'Cet email est déjà utilisé.';
        }
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($csrf)) {
        $errors[] = 'Session expirée ou formulaire invalide. Réessayez.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    if (!super_admin_update_profile($sa_id, $nom, $prenom, $email)) {
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour du profil.'];
    }

    $_SESSION['super_admin_nom'] = $nom;
    $_SESSION['super_admin_prenom'] = $prenom;
    $_SESSION['super_admin_email'] = $email;
    super_admin_log_action($sa_id, 'profil_modifie', 'super_admin', $sa_id, $email);

    return ['success' => true, 'message' => 'Profil mis à jour avec succès.'];
}

/**
 * Changement du mot de passe du super admin connecté
 * @return array{success:bool,message:string}
 */
function process_super_admin_update_own_password() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }

    $errors = [];
    $sa_id = (int) ($_SESSION['super_admin_id'] ?? 0);
    $current = isset($_POST['current_password']) ? (string) $_POST['current_password'] : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? (string) $_POST['password_confirm'] : '';

    if ($sa_id <= 0) {
        $errors[] = 'Session invalide.';
    }
    if ($current === '') {
        $errors[] = 'Le mot de passe actuel est obligatoire.';
    }

    $row = get_super_admin_by_id($sa_id);
    if (!$row || !password_verify($current, (string) ($row['password'] ?? ''))) {
        $errors[] = 'Mot de passe actuel incorrect.';
    }

    $pw_check = super_admin_validate_password($password, $password_confirm);
    if (!$pw_check['ok']) {
        $errors[] = $pw_check['message'];
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($csrf)) {
        $errors[] = 'Session expirée ou formulaire invalide. Réessayez.';
    }

    if (!empty($errors)) {
        return ['success' => false, 'message' => implode('<br>', $errors)];
    }

    $hash = securite_mdp_hash($password);
    if (!super_admin_update_password($sa_id, $hash)) {
        return ['success' => false, 'message' => 'Erreur lors du changement de mot de passe.'];
    }

    super_admin_log_action($sa_id, 'mot_de_passe_modifie', 'super_admin', $sa_id, '');

    return ['success' => true, 'message' => 'Mot de passe modifié avec succès.'];
}
