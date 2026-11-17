<?php
require_once __DIR__ . '/../includes/securite_mdp.php';
/**
 * Page de profil administrateur - Modification des informations
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/includes/require_admin_session.php';



// Récupérer les informations de l'administrateur
require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../includes/senegal_regions.php';
$admin = get_admin_by_id($_SESSION['admin_id']);

if (!$admin) {
    session_destroy();
    require_once __DIR__ . '/../includes/auth_redirect.php';
    auth_clear_portal_cookie();
    admin_redirect_to_login();
}

$is_vendeur = (($admin['role'] ?? '') === 'vendeur');
$profil_nom = (string) ($admin['nom'] ?? '');
$profil_prenom = (string) ($admin['prenom'] ?? '');
$profil_email = (string) ($admin['email'] ?? '');
$profil_telephone = (string) ($admin['telephone'] ?? '');
$profil_display_name = trim($profil_prenom . ' ' . $profil_nom);
if ($profil_display_name === '') {
    $profil_display_name = $profil_nom !== '' ? $profil_nom : 'Mon compte';
}
$profil_boutique_nom = (string) ($admin['boutique_nom'] ?? '');
$profil_boutique_region = (string) ($admin['boutique_region'] ?? '');
$profil_boutique_region_label = $profil_boutique_region !== ''
    ? senegal_region_label($profil_boutique_region)
    : '';
$profil_boutique_logo = trim((string) ($admin['boutique_logo'] ?? ''));
$profil_boutique_logo_url = '';
if ($profil_boutique_logo !== '') {
    require_once __DIR__ . '/../includes/image_optimizer.php';
    $profil_boutique_logo_url = upload_image_url($profil_boutique_logo, 'sm');
}

// Traitement des formulaires
require_once __DIR__ . '/../includes/flash_toast.php';
$error_message = '';

// Traitement du formulaire d'informations personnelles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_profil'])) {
    // Récupération et nettoyage des données
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
    
    $errors = [];
    
    // Validation du nom
    if (empty($nom)) {
        $errors[] = 'Le nom est obligatoire.';
    } elseif (strlen($nom) < 2) {
        $errors[] = 'Le nom doit contenir au moins 2 caractères.';
    }
    
    // Validation du prénom (facultatif pour les vendeurs)
    if (!$is_vendeur) {
        if (empty($prenom)) {
            $errors[] = 'Le prénom est obligatoire.';
        } elseif (strlen($prenom) < 2) {
            $errors[] = 'Le prénom doit contenir au moins 2 caractères.';
        }
    } elseif ($prenom !== '' && strlen($prenom) < 2) {
        $errors[] = 'Le prénom doit contenir au moins 2 caractères.';
    }
    
    // Validation de l'email (facultatif pour les vendeurs)
    if ($email === '') {
        if (!$is_vendeur) {
            $errors[] = 'L\'email est obligatoire.';
        }
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'email n\'est pas valide.';
    } else {
        // Vérifier si l'email existe déjà pour un autre administrateur
        $existing_admin = get_admin_by_email($email);
        if ($existing_admin && $existing_admin['id'] != $_SESSION['admin_id']) {
            $errors[] = 'Cet email est déjà utilisé par un autre administrateur.';
        }
    }
    
    // Validation du téléphone (obligatoire)
    if (empty($telephone)) {
        $errors[] = 'Le téléphone est obligatoire.';
    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $telephone)) {
        $errors[] = 'Le format du téléphone n\'est pas valide.';
    }
    
    // Si aucune erreur, procéder à la mise à jour
    if (empty($errors)) {
        // Préparer les données à mettre à jour
        $data = [
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email !== '' ? $email : null,
            'telephone' => $telephone
        ];

        // Mettre à jour les informations de base
        if (update_admin($_SESSION['admin_id'], $data)) {
            if ($email !== '') {
                $_SESSION['admin_email'] = $email;
            } else {
                unset($_SESSION['admin_email']);
            }

            // Redirection pour éviter la double soumission (pattern Post-Redirect-Get)
            flash_toast_push('success', 'Vos informations personnelles ont été mises à jour avec succès !');
            header('Location: profil.php');
            exit;
        } else {
            $error_message = 'Une erreur est survenue lors de la mise à jour. Veuillez réessayer.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Traitement du formulaire gestion boutique (vendeurs)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_boutique']) && $is_vendeur) {
    $boutique_nom = isset($_POST['boutique_nom']) ? trim((string) $_POST['boutique_nom']) : '';
    $boutique_region = isset($_POST['boutique_region']) ? trim((string) $_POST['boutique_region']) : '';
    /* Formulaire envoyé depuis « Ma boutique » (parametres.php) : on y retourne, erreurs comprises. */
    $boutique_retour_parametres = (($_POST['retour'] ?? '') === 'parametres');

    $errors = [];

    if (mb_strlen($boutique_nom) < 2) {
        $errors[] = 'Le nom de la boutique est obligatoire (2 caractères minimum).';
    }

    if (admin_has_boutique_region_column()) {
        if ($boutique_region === '' || !senegal_region_is_valid($boutique_region)) {
            $errors[] = 'Veuillez sélectionner une région valide pour votre boutique.';
        }
    }

    if (empty($errors)) {
        if (update_vendeur_boutique_profil($_SESSION['admin_id'], $boutique_nom, $boutique_region)) {
            flash_toast_push('success', 'Les informations de votre boutique ont été mises à jour avec succès !');
            header('Location: ' . ($boutique_retour_parametres ? 'parametres.php' : 'profil.php'));
            exit;
        }
        $error_message = 'Impossible d\'enregistrer les informations de la boutique. Réessayez.';
    } else {
        $error_message = implode('<br>', $errors);
        $profil_boutique_nom = $boutique_nom;
        $profil_boutique_region = $boutique_region;
        $profil_boutique_region_label = $boutique_region !== '' ? senegal_region_label($boutique_region) : '';
    }
    if ($boutique_retour_parametres && $error_message !== '') {
        flash_toast_push('error', strip_tags(str_replace('<br>', ' ', $error_message)));
        header('Location: parametres.php');
        exit;
    }
}

// Traitement du formulaire de changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_mot_de_passe'])) {
    // Récupération et nettoyage des données (protection XSS)
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
    
    $errors = [];
    
    // Validation du mot de passe actuel
    if (empty($current_password)) {
        $errors[] = 'Le mot de passe actuel est obligatoire.';
    } else {
        // Vérifier que le mot de passe actuel est correct
        if (!password_verify($current_password, $admin['password'])) {
            $errors[] = 'Le mot de passe actuel est incorrect.';
        }
    }
    
    // Validation du nouveau mot de passe
    if (empty($password)) {
        $errors[] = 'Le nouveau mot de passe est obligatoire.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password === $current_password) {
        $errors[] = 'Le nouveau mot de passe doit être différent de l\'ancien.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Les nouveaux mots de passe ne correspondent pas.';
    }
    
    // Si aucune erreur, procéder à la mise à jour
    if (empty($errors)) {
        require_once __DIR__ . '/../conn/conn.php';
        global $db;
        
        // Protection contre les injections SQL : utilisation de PDO avec prepared statements
        $password_hash = securite_mdp_hash($password);
        $stmt = $db->prepare("UPDATE admin SET password = :password WHERE id = :id");
        
        if ($stmt->execute([
            'id' => $_SESSION['admin_id'],
            'password' => $password_hash
        ])) {
            // Redirection pour éviter la double soumission (pattern Post-Redirect-Get)
            flash_toast_push('success', 'Votre mot de passe a été modifié avec succès !');
            header('Location: profil.php');
            exit;
        } else {
            $error_message = 'Une erreur est survenue lors de la modification du mot de passe. Veuillez réessayer.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
if (!empty($error_message)) {
    flash_toast_queue_page('error', str_replace('<br>', ' — ', $error_message));
}

$profil_initial = '';
$__initial_sources = [$profil_prenom, $profil_nom, $profil_boutique_nom, $profil_display_name];
foreach ($__initial_sources as $__src) {
    $__src = trim((string) $__src);
    if ($__src !== '') {
        $profil_initial = function_exists('mb_substr')
            ? mb_strtoupper(mb_substr($__src, 0, 1, 'UTF-8'), 'UTF-8')
            : strtoupper(substr($__src, 0, 1));
        break;
    }
}
if ($profil_initial === '') {
    $profil_initial = 'A';
}
$profil_role_label = $is_vendeur ? 'Vendeur' : 'Administrateur';
require_once __DIR__ . '/../includes/app_base.php';
$ap_aide_email = 'contact@colobanes.com';
$ap_aide_adresse = 'Rond Point Colobane, Dakar, Sénégal';

/* En-tête : propriétaire, certification, ancienneté et chiffres clés de la boutique */
$profil_owner_name = trim($profil_prenom . ' ' . $profil_nom);
if (mb_strtolower($profil_owner_name) === mb_strtolower(trim($profil_boutique_nom))) {
    $profil_owner_name = '';
}
$profil_cert_label = $profil_cert_color = $profil_cert_icon = '';
if ($is_vendeur && !empty($admin['certification_niveau'])) {
    require_once __DIR__ . '/../models/model_vendeur_certification.php';
    $__niv = vendeur_certification_niveaux()[(string) $admin['certification_niveau']] ?? null;
    if ($__niv) {
        $profil_cert_label = 'Certifié ' . $__niv['label'];
        $profil_cert_color = $__niv['color'];
        $profil_cert_icon = $__niv['icon'];
    }
}
$profil_depuis = '';
if (!empty($admin['date_creation']) && ($__ts = strtotime((string) $admin['date_creation']))) {
    $__mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $profil_depuis = $__mois[(int) date('n', $__ts) - 1] . ' ' . date('Y', $__ts);
}
$profil_stats = ['produits' => 0, 'commandes' => 0, 'livrees' => 0];
if ($is_vendeur) {
    require_once __DIR__ . '/../conn/conn.php';
    global $db;
    try {
        $__st = $db->prepare("SELECT COUNT(*) FROM produits WHERE admin_id = :id AND statut = 'actif'");
        $__st->execute(['id' => (int) $admin['id']]);
        $profil_stats['produits'] = (int) $__st->fetchColumn();
        $__st = $db->prepare("SELECT COUNT(*) AS total, SUM(statut = 'livree') AS livrees FROM commandes WHERE vendeur_id = :id AND statut <> 'annulee'");
        $__st->execute(['id' => (int) $admin['id']]);
        $__r = $__st->fetch(PDO::FETCH_ASSOC) ?: [];
        $profil_stats['commandes'] = (int) ($__r['total'] ?? 0);
        $profil_stats['livrees'] = (int) ($__r['livrees'] ?? 0);
    } catch (PDOException $e) {
        error_log('[admin/profil stats] ' . $e->getMessage());
    }
}
if (!defined('SITE_BRAND_NAME')) {
    require_once __DIR__ . '/../includes/site_brand.php';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Administration COLObanes</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 1) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-profil-page.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/../includes/auth_intl_tel_head.php'; ?>
    <script>
    (function () {
        try {
            if (localStorage.getItem('colobanes-ui-theme') === 'dark') {
                document.documentElement.classList.add('theme-dark');
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        } catch (e) {}
    })();
    </script>
    <style>
        .input-wrapper--intl-tel {
            display: block;
            position: relative;
        }
        .input-wrapper--intl-tel > .iti {
            width: 100%;
            display: block;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(53, 100, 166, 0.18);
            box-sizing: border-box;
        }
        .input-wrapper--intl-tel .iti__tel-input {
            width: 100% !important;
            padding: 0.72rem 0.95rem !important;
            padding-left: 3.4rem !important;
            font-size: 0.92rem !important;
            font-family: inherit !important;
            border: none !important;
            border-radius: 0 !important;
            background: #fff !important;
            color: #333 !important;
            box-shadow: none !important;
        }
        .input-wrapper--intl-tel .iti__selected-country {
            width: auto;
            min-width: 2.6rem;
            max-width: 3rem;
        }
        .input-wrapper--intl-tel .iti__selected-country-primary {
            font-size: 0;
            visibility: hidden;
        }
        .input-wrapper--intl-tel .iti__selected-country-primary .iti__flag {
            visibility: visible;
            margin-right: 0;
        }
        .input-wrapper--intl-tel .iti:focus-within {
            border-color: var(--couleur-dominante, #3564a6);
            box-shadow: 0 0 0 3px rgba(53, 100, 166, 0.12);
        }
        .input-wrapper--intl-tel .iti__country-list {
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        }
        .form-optional {
            font-weight: 400;
            color: #737373;
            font-size: 0.78rem;
        }
    </style>
</head>

<body class="admin-page-profil">
    <?php include 'includes/nav.php'; ?>

    <div class="contents-container">
        <div class="ap-page ap-page--shell">

            <header class="ap-hero" aria-labelledby="ap-shell-name">
                <div class="ap-hero__cover" aria-hidden="true"></div>
                <div class="ap-hero__body">
                    <?php if (!$is_vendeur): ?>
                    <a href="dashboard.php" class="ap-hero__back" aria-label="Retour au tableau de bord">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                    <div class="ap-hero__avatar-wrap">
                        <div class="ap-hero__avatar<?php echo ($is_vendeur && $profil_boutique_logo_url !== '') ? ' ap-hero__avatar--logo' : ''; ?>" aria-hidden="true">
                            <?php if ($is_vendeur && $profil_boutique_logo_url !== ''): ?>
                            <img src="<?php echo htmlspecialchars($profil_boutique_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="">
                            <?php else: ?>
                            <span><?php echo htmlspecialchars($profil_initial, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_vendeur): ?>
                        <a href="parametres-boutique-vendeur.php" class="ap-hero__avatar-edit" aria-label="Modifier le logo de la boutique" title="Modifier le logo">
                            <i class="fas fa-camera" aria-hidden="true"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="ap-hero__identity">
                        <h1 class="ap-hero__name" id="ap-shell-name"><?php echo htmlspecialchars($is_vendeur && $profil_boutique_nom !== '' ? $profil_boutique_nom : $profil_display_name, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p class="ap-hero__sub">
                            <?php if ($is_vendeur && $profil_boutique_nom !== '' && $profil_owner_name !== ''): ?><span><?php echo htmlspecialchars($profil_owner_name, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                            <span><?php echo $profil_email !== '' ? htmlspecialchars($profil_email, ENT_QUOTES, 'UTF-8') : htmlspecialchars($profil_telephone !== '' ? $profil_telephone : 'Email non renseigné', ENT_QUOTES, 'UTF-8'); ?></span>
                        </p>
                        <ul class="ap-hero__tags">
                            <li class="ap-tag ap-tag--role"><i class="fas <?php echo $is_vendeur ? 'fa-store' : 'fa-user-shield'; ?>" aria-hidden="true"></i><?php echo htmlspecialchars($profil_role_label, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php if ($profil_cert_label !== ''): ?>
                            <li class="ap-tag ap-tag--cert" style="--ap-cert: <?php echo htmlspecialchars($profil_cert_color, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas <?php echo htmlspecialchars($profil_cert_icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i><?php echo htmlspecialchars($profil_cert_label, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endif; ?>
                            <?php if ($profil_boutique_region_label !== ''): ?>
                            <li class="ap-tag"><i class="fas fa-location-dot" aria-hidden="true"></i><?php echo htmlspecialchars($profil_boutique_region_label, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endif; ?>
                            <?php if ($profil_depuis !== ''): ?>
                            <li class="ap-tag ap-tag--muted"><i class="far fa-calendar" aria-hidden="true"></i>Depuis <?php echo htmlspecialchars($profil_depuis, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <button type="button" class="ap-hero__theme" id="apThemeToggle" data-theme-toggle
                        aria-label="Activer le mode sombre" aria-pressed="false" title="Mode sombre">
                        <i class="fas fa-moon" aria-hidden="true"></i>
                    </button>
                </div>
                <?php if ($is_vendeur): ?>
                <dl class="ap-stats">
                    <div class="ap-stat">
                        <dt>Produits en ligne</dt>
                        <dd><?php echo (int) $profil_stats['produits']; ?></dd>
                    </div>
                    <div class="ap-stat">
                        <dt>Commandes</dt>
                        <dd><?php echo (int) $profil_stats['commandes']; ?></dd>
                    </div>
                    <div class="ap-stat">
                        <dt>Livrées</dt>
                        <dd><?php echo (int) $profil_stats['livrees']; ?></dd>
                    </div>
                </dl>
                <?php endif; ?>
            </header>

            <div class="ap-layout">
                <nav class="ap-nav" aria-label="Rubriques du compte">
                    <p class="ap-nav__section">Mon compte</p>
                    <div class="ap-nav__group">
                        <button type="button" class="ap-nav__item" data-ap-panel="apPanelInfos">
                            <span class="ap-nav__icon ap-nav__icon--blue"><i class="fas fa-user-pen" aria-hidden="true"></i></span>
                            <span class="ap-nav__text"><strong>Informations personnelles</strong><small>Nom, e-mail, téléphone</small></span>
                            <i class="fas fa-chevron-right ap-nav__chev" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="ap-nav__item" data-ap-panel="apPanelSecurite">
                            <span class="ap-nav__icon ap-nav__icon--green"><i class="fas fa-shield-halved" aria-hidden="true"></i></span>
                            <span class="ap-nav__text"><strong>Sécurité</strong><small>Mot de passe</small></span>
                            <i class="fas fa-chevron-right ap-nav__chev" aria-hidden="true"></i>
                        </button>
                        <?php if (!$is_vendeur): ?>
                        <a href="parametres.php" class="ap-nav__item">
                            <span class="ap-nav__icon ap-nav__icon--slate"><i class="fas fa-cog" aria-hidden="true"></i></span>
                            <span class="ap-nav__text"><strong>Paramètres</strong><small>Configuration de la plateforme</small></span>
                            <i class="fas fa-chevron-right ap-nav__chev" aria-hidden="true"></i>
                        </a>
                        <?php endif; ?>
                    </div>

                    <p class="ap-nav__section">Assistance</p>
                    <div class="ap-nav__group">
                        <button type="button" class="ap-nav__item" data-ap-panel="apPanelAide">
                            <span class="ap-nav__icon ap-nav__icon--orange"><i class="fas fa-headset" aria-hidden="true"></i></span>
                            <span class="ap-nav__text"><strong>Centre d’aide</strong><small>Support et contact</small></span>
                            <i class="fas fa-chevron-right ap-nav__chev" aria-hidden="true"></i>
                        </button>
                        <a href="<?php echo htmlspecialchars(app_url('/conditions-utilisation.php'), ENT_QUOTES, 'UTF-8'); ?>" class="ap-nav__item">
                            <span class="ap-nav__icon ap-nav__icon--slate"><i class="fas fa-file-contract" aria-hidden="true"></i></span>
                            <span class="ap-nav__text"><strong>Politique générale</strong></span>
                            <i class="fas fa-arrow-up-right-from-square ap-nav__chev" aria-hidden="true"></i>
                        </a>
                        <a href="<?php echo htmlspecialchars(app_url('/politique-confidentialite.php'), ENT_QUOTES, 'UTF-8'); ?>" class="ap-nav__item">
                            <span class="ap-nav__icon ap-nav__icon--slate"><i class="fas fa-user-shield" aria-hidden="true"></i></span>
                            <span class="ap-nav__text"><strong>Confidentialité</strong></span>
                            <i class="fas fa-arrow-up-right-from-square ap-nav__chev" aria-hidden="true"></i>
                        </a>
                    </div>

                    <?php if (!$is_vendeur): ?>
                    <div class="ap-nav__group">
                        <a href="logout.php" class="ap-nav__item ap-nav__item--danger">
                            <span class="ap-nav__icon ap-nav__icon--red"><i class="fas fa-right-from-bracket" aria-hidden="true"></i></span>
                            <span class="ap-nav__text"><strong>Déconnexion</strong></span>
                        </a>
                    </div>
                    <?php endif; ?>
                </nav>

                <div class="ap-panels" id="apPanels" aria-live="polite">
                    <section class="ap-panel" id="apPanelInfos" hidden aria-labelledby="apPanelInfosTitle">
                        <div class="ap-panel-head">
                            <button type="button" class="ap-panel-back" data-ap-back aria-label="Retour">
                                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                            </button>
                            <div>
                                <h2 class="ap-panel-title" id="apPanelInfosTitle">Informations personnelles</h2>
                                <p class="ap-panel-desc">Non visibles par les clients.</p>
                            </div>
                        </div>
                        <form method="POST" action="" class="ap-form">
                            <div class="ap-form-row">
                                <div class="ap-form-group">
                                    <label for="nom">Nom <span class="required">*</span></label>
                                    <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($profil_nom, ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="family-name">
                                </div>
                                <div class="ap-form-group">
                                    <label for="prenom">Prénom <?php if (!$is_vendeur): ?><span class="required">*</span><?php else: ?><span class="form-optional">(facultatif)</span><?php endif; ?></label>
                                    <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($profil_prenom, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $is_vendeur ? '' : ' required'; ?> autocomplete="given-name">
                                </div>
                            </div>
                            <div class="ap-form-row">
                                <div class="ap-form-group">
                                    <label for="email">E-mail <?php if (!$is_vendeur): ?><span class="required">*</span><?php else: ?><span class="form-optional">(facultatif)</span><?php endif; ?></label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profil_email, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $is_vendeur ? '' : ' required'; ?> autocomplete="email">
                                </div>
                                <div class="ap-form-group">
                                    <label for="telephone">Téléphone <span class="required">*</span></label>
                                    <div class="input-wrapper input-wrapper--intl-tel">
                                        <input type="tel" id="telephone" name="telephone"
                                            value="<?php echo htmlspecialchars($profil_telephone, ENT_QUOTES, 'UTF-8'); ?>"
                                            placeholder="77 123 45 67"
                                            required autocomplete="tel">
                                    </div>
                                </div>
                            </div>
                            <div class="ap-actions">
                                <button type="submit" name="modifier_profil" class="ap-btn ap-btn--primary">Enregistrer les modifications</button>
                            </div>
                        </form>
                    </section>

                    <section class="ap-panel" id="apPanelSecurite" hidden aria-labelledby="apPanelSecuriteTitle">
                        <div class="ap-panel-head">
                            <button type="button" class="ap-panel-back" data-ap-back aria-label="Retour">
                                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                            </button>
                            <div>
                                <h2 class="ap-panel-title" id="apPanelSecuriteTitle">Sécurité</h2>
                                <p class="ap-panel-desc">Changez votre mot de passe de connexion.</p>
                            </div>
                        </div>
                        <form method="POST" action="" class="ap-form">
                            <div class="ap-form-group">
                                <label for="current_password">Mot de passe actuel <span class="required">*</span></label>
                                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                            </div>
                            <div class="ap-form-row">
                                <div class="ap-form-group">
                                    <label for="password">Nouveau mot de passe <span class="required">*</span></label>
                                    <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password">
                                    <small class="ap-help">6 caractères minimum, différent de l’actuel.</small>
                                </div>
                                <div class="ap-form-group">
                                    <label for="password_confirm">Confirmation <span class="required">*</span></label>
                                    <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                                </div>
                            </div>
                            <div class="ap-actions">
                                <button type="submit" name="modifier_mot_de_passe" class="ap-btn ap-btn--primary">Changer le mot de passe</button>
                            </div>
                        </form>
                        <div class="ap-note">
                            <i class="fas fa-lock" aria-hidden="true"></i>
                            <p>L’équipe <?php echo htmlspecialchars(SITE_BRAND_NAME, ENT_QUOTES, 'UTF-8'); ?> ne vous demandera jamais votre mot de passe, ni par téléphone ni par message.</p>
                        </div>
                    </section>

                    <section class="ap-panel" id="apPanelAide" hidden aria-labelledby="apPanelAideTitle">
                        <div class="ap-panel-head">
                            <button type="button" class="ap-panel-back" data-ap-back aria-label="Retour">
                                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                            </button>
                            <div>
                                <h2 class="ap-panel-title" id="apPanelAideTitle">Centre d’aide</h2>
                                <p class="ap-panel-desc">Une question ou un souci ? L’équipe vous répond.</p>
                            </div>
                        </div>
                        <div class="ap-help-grid">
                            <a class="ap-help-card" href="mailto:<?php echo htmlspecialchars($ap_aide_email, ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="ap-nav__icon ap-nav__icon--blue"><i class="fas fa-envelope" aria-hidden="true"></i></span>
                                <strong>Écrire au support</strong>
                                <small><?php echo htmlspecialchars($ap_aide_email, ENT_QUOTES, 'UTF-8'); ?></small>
                            </a>
                            <a class="ap-help-card" href="mailto:<?php echo htmlspecialchars($ap_aide_email, ENT_QUOTES, 'UTF-8'); ?>?subject=<?php echo rawurlencode('Signalement vendeur COLObanes'); ?>">
                                <span class="ap-nav__icon ap-nav__icon--red"><i class="fas fa-flag" aria-hidden="true"></i></span>
                                <strong>Signaler un problème</strong>
                                <small>Indiquez le numéro de commande et joignez des captures.</small>
                            </a>
                            <div class="ap-help-card ap-help-card--static">
                                <span class="ap-nav__icon ap-nav__icon--orange"><i class="fas fa-location-dot" aria-hidden="true"></i></span>
                                <strong>Nous rendre visite</strong>
                                <small><?php echo htmlspecialchars($ap_aide_adresse, ENT_QUOTES, 'UTF-8'); ?></small>
                            </div>
                        </div>
                        <?php if ($is_vendeur): ?>
                        <h3 class="ap-subtitle">Raccourcis vendeur</h3>
                        <ul class="ap-links">
                            <li><a href="produits/index.php"><i class="fas fa-box" aria-hidden="true"></i>Gérer mes produits<i class="fas fa-chevron-right" aria-hidden="true"></i></a></li>
                            <li><a href="commandes/index.php"><i class="fas fa-receipt" aria-hidden="true"></i>Suivre mes commandes<i class="fas fa-chevron-right" aria-hidden="true"></i></a></li>
                            <li><a href="parametres/certification.php"><i class="fas fa-certificate" aria-hidden="true"></i>Ma certification<i class="fas fa-chevron-right" aria-hidden="true"></i></a></li>
                        </ul>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <?php include __DIR__ . '/../includes/auth_intl_tel_scripts.php'; ?>
    <script>
    window.COLOBANES_THEME_API = <?php echo json_encode(app_url('/api/user_ui_theme.php'), JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="<?php echo htmlspecialchars(app_url('/js/colobanes-theme.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            /* Ordinateur : une rubrique toujours ouverte à droite. Téléphone : rubrique en plein écran. */
            var panels = document.querySelectorAll('.ap-panel');
            var triggers = document.querySelectorAll('[data-ap-panel]');
            var desktopMq = window.matchMedia('(min-width: 1024px)');
            var current = '';

            function render() {
                var desktop = desktopMq.matches;
                var target = current || (desktop ? 'apPanelInfos' : '');
                panels.forEach(function (panel) {
                    panel.hidden = panel.id !== target;
                });
                triggers.forEach(function (btn) {
                    var active = btn.getAttribute('data-ap-panel') === target;
                    btn.classList.toggle('is-active', active);
                    if (active) {
                        btn.setAttribute('aria-current', 'true');
                    } else {
                        btn.removeAttribute('aria-current');
                    }
                });
                document.body.classList.toggle('admin-profil-panel-open', !desktop && target !== '');
            }

            function open(panelId) {
                current = panelId;
                render();
                if (!desktopMq.matches) {
                    window.scrollTo(0, 0);
                }
            }

            triggers.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    open(btn.getAttribute('data-ap-panel'));
                });
            });
            document.querySelectorAll('[data-ap-back]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    open('');
                });
            });
            if (typeof desktopMq.addEventListener === 'function') {
                desktopMq.addEventListener('change', render);
            } else if (typeof desktopMq.addListener === 'function') {
                desktopMq.addListener(render);
            }

            /* Lien direct vers une rubrique : profil.php#infos, #securite, #aide */
            var hashPanels = { '#infos': 'apPanelInfos', '#securite': 'apPanelSecurite', '#aide': 'apPanelAide' };
            current = hashPanels[window.location.hash] || '';
            render();

            if (typeof window.initAuthIntlTel === 'function') {
                window.initAuthIntlTel('telephone');
            }
        });
    </script>
</body>
</html>