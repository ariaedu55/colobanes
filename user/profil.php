<?php
require_once __DIR__ . '/../includes/securite_mdp.php';
/**
 * Page de profil utilisateur — redesign v2
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../includes/site_csrf.php';


if (!function_exists('auth_redirect_to_site_home')) {
    require_once __DIR__ . '/../includes/auth_redirect.php';
}
if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    auth_redirect_to_site_home();
    exit;
}

require_once __DIR__ . '/../models/model_users.php';
$user = get_user_by_id($_SESSION['user_id']);

if (!$user) {
    session_destroy();
    auth_redirect_to_site_home();
    exit;
}

// Initiale de l'avatar : prénom, sinon nom
$avatar_source  = trim((string)($user['prenom'] ?? '')) ?: trim((string)($user['nom'] ?? ''));
$avatar_initial = $avatar_source !== '' ? mb_strtoupper(mb_substr($avatar_source, 0, 1, 'UTF-8'), 'UTF-8') : '?';

$error_message   = '';

// Formulaire infos personnelles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_profil'])) {
    if (!site_csrf_verify_post()) {
        $error_message = site_csrf_fail_message();
    } else {
    $nom       = isset($_POST['nom'])       ? trim($_POST['nom'])       : '';
    $prenom    = isset($_POST['prenom'])    ? trim($_POST['prenom'])    : '';
    $email     = isset($_POST['email'])     ? trim($_POST['email'])     : '';
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';

    $errors = [];

    if (empty($nom)) {
        $errors[] = 'Le nom est obligatoire.';
    } elseif (strlen($nom) < 2) {
        $errors[] = 'Le nom doit contenir au moins 2 caractères.';
    }

    if ($prenom !== '' && strlen($prenom) < 2) {
        $errors[] = 'Le prénom doit contenir au moins 2 caractères.';
    }

    $email_db = null;
    if ($email !== '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "L'email n'est pas valide.";
        } else {
            $existing_user = get_user_by_email($email);
            if ($existing_user && (int)$existing_user['id'] !== (int)$_SESSION['user_id']) {
                $errors[] = 'Cet email est déjà utilisé par un autre compte.';
            } else {
                $email_db = $email;
            }
        }
    }

    if (empty($telephone)) {
        $errors[] = 'Le téléphone est obligatoire.';
    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $telephone)) {
        $errors[] = "Le format du téléphone n'est pas valide.";
    }

    if (empty($errors)) {
        $data = ['nom' => $nom, 'prenom' => $prenom, 'email' => $email_db, 'telephone' => $telephone];
        if (update_user($_SESSION['user_id'], $data)) {
            $_SESSION['user_email'] = $email_db !== null ? $email_db : '';
            require_once __DIR__ . '/../includes/flash_toast.php';
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
}

// Formulaire mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_mot_de_passe'])) {
    if (!site_csrf_verify_post()) {
        $error_message = site_csrf_fail_message();
    } else {
    $current_password  = isset($_POST['current_password'])  ? $_POST['current_password']  : '';
    $password          = isset($_POST['password'])          ? $_POST['password']          : '';
    $password_confirm  = isset($_POST['password_confirm'])  ? $_POST['password_confirm']  : '';

    $errors = [];

    if (empty($current_password)) {
        $errors[] = 'Le mot de passe actuel est obligatoire.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $errors[] = 'Le mot de passe actuel est incorrect.';
    }

    if (empty($password)) {
        $errors[] = 'Le nouveau mot de passe est obligatoire.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    } elseif ($password === $current_password) {
        $errors[] = "Le nouveau mot de passe doit être différent de l'ancien.";
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Les nouveaux mots de passe ne correspondent pas.';
    }

    if (empty($errors)) {
        require_once __DIR__ . '/../conn/conn.php';
        global $db;
        $password_hash = securite_mdp_hash($password);
        $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
        if ($stmt->execute(['id' => $_SESSION['user_id'], 'password' => $password_hash])) {
            require_once __DIR__ . '/../includes/flash_toast.php';
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
}

$nom_affiche  = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
$nom_affiche  = $nom_affiche !== '' ? $nom_affiche : 'Client';
$member_since = isset($user['date_creation']) ? date('M Y', strtotime($user['date_creation'])) : '';
$user_nav_favoris_count = 0;
if (file_exists(__DIR__ . '/../models/model_favoris.php')) {
    require_once __DIR__ . '/../models/model_favoris.php';
    $user_nav_favoris_count = count_favoris_by_user((int) $_SESSION['user_id']);
}
$user_boutiques_suivies = 0;
if (file_exists(__DIR__ . '/../models/model_boutique_abonnements.php')) {
    require_once __DIR__ . '/../models/model_boutique_abonnements.php';
    if (function_exists('boutique_abonnements_table_exists') && boutique_abonnements_table_exists()) {
        $user_boutiques_suivies = count(boutique_abonnements_list_by_user((int) $_SESSION['user_id']));
    }
}
/* Contact (masqué sur ordinateur : déjà dans les pastilles de coordonnées) + ancienneté */
$profil_subtitle_contact = !empty($user['email'])
    ? (string) $user['email']
    : (!empty($user['telephone']) ? (string) $user['telephone'] : 'Client COLObanes');
$profil_subtitle_since = $member_since !== '' ? 'Membre depuis ' . $member_since : '';

$prf_aide_email   = 'contact@colobanes.com';
$prf_aide_adresse = 'Rond Point Colobane, Dakar, Sénégal';

require_once __DIR__ . '/../includes/flash_toast.php';
if (!empty($error_message)) {
    flash_toast_queue_page('error', str_replace('<br>', ' — ', $error_message));
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Mon profil &mdash; COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">    <?php require_once dirname(__DIR__, 1) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-mes-commandes.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-profil.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/../includes/auth_intl_tel_head.php'; ?>
    <script>
    (function () {
        try {
            if (localStorage.getItem('colobanes-ui-theme') === 'dark') {
                document.documentElement.classList.add('prf-theme-dark');
            }
        } catch (e) {}
    })();
    </script>
</head>

<body class="user-page-profil">
    <?php include 'includes/user_nav.php'; ?>

    <div class="prf-app">

        <div class="prf-app-layout">

        <aside class="prf-app-side">
        <header class="prf-app-header">
            <a href="/index.php" class="prf-app-back" aria-label="Retour à l'accueil">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span class="prf-app-back__text">Retour à la boutique</span>
            </a>
            <button type="button" class="prf-app-theme-toggle" id="prfThemeToggle" aria-label="Activer le mode sombre" aria-pressed="false" title="Mode sombre">
                <i class="fas fa-moon" aria-hidden="true"></i>
            </button>

            <div class="prf-app-avatar-wrap">
                <div class="prf-app-avatar" aria-hidden="true">
                    <?php if ($avatar_initial !== '?'): ?>
                        <?php echo htmlspecialchars($avatar_initial, ENT_QUOTES, 'UTF-8'); ?>
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <button type="button" class="prf-app-avatar-edit" id="prfAvatarEditBtn" aria-label="Modifier le profil">
                    <i class="fas fa-pen" aria-hidden="true"></i>
                </button>
            </div>

            <div class="prf-app-identity">
                <p class="prf-app-eyebrow">Mon compte COLObanes</p>
                <h1 class="prf-app-name"><?php echo htmlspecialchars($nom_affiche, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="prf-app-subtitle">
                    <span class="prf-app-subtitle__contact"><?php echo htmlspecialchars($profil_subtitle_contact, ENT_QUOTES, 'UTF-8'); ?><?php echo $profil_subtitle_since !== '' ? ' · ' : ''; ?></span><?php echo htmlspecialchars($profil_subtitle_since, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>

        </header>

        <?php /* Coordonnées : affichées sous l'en-tête sur ordinateur uniquement */ ?>
        <?php if (!empty($user['telephone']) || !empty($user['email'])): ?>
        <ul class="prf-app-meta">
            <?php if (!empty($user['telephone'])): ?>
            <li><i class="fas fa-phone" aria-hidden="true"></i><?php echo htmlspecialchars((string) $user['telephone'], ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endif; ?>
            <?php if (!empty($user['email'])): ?>
            <li><i class="fas fa-envelope" aria-hidden="true"></i><?php echo htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>

        <?php /* Raccourcis chiffrés (commandes en cours, favoris, boutiques suivies) */ ?>
        <nav class="prf-stats" aria-label="Résumé du compte">
            <a href="mes-commandes.php" class="prf-stats__item">
                <span class="prf-stats__value"><?php echo (int) ($user_nav_commandes_actives ?? 0); ?></span>
                <span class="prf-stats__label">En cours</span>
            </a>
            <a href="favoris.php" class="prf-stats__item">
                <span class="prf-stats__value"><?php echo (int) $user_nav_favoris_count; ?></span>
                <span class="prf-stats__label">Favoris</span>
            </a>
            <a href="mes-boutiques-abonnees.php" class="prf-stats__item">
                <span class="prf-stats__value"><?php echo (int) $user_boutiques_suivies; ?></span>
                <span class="prf-stats__label">Boutiques</span>
            </a>
        </nav>
        </aside>

        <div class="prf-app-main">

        <header class="prf-app-main-head">
            <h2 class="prf-app-main-head__title">Gestion du compte</h2>
            <p class="prf-app-main-head__desc">Commandes, boutiques abonnées et paramètres de votre espace client.</p>
        </header>

        <div class="prf-app-sheet">

            <nav class="prf-app-menu" id="prfMenuView" aria-label="Menu profil">

                <section class="prf-app-group prf-app-group--activity">
                <p class="prf-app-menu-section">Activité</p>
                <div class="prf-app-group__card">
                <a href="mes-commandes.php" class="prf-app-menu-row prf-app-menu-row--accent">
                    <span class="prf-app-menu-row__icon"><i class="fas fa-bag-shopping" aria-hidden="true"></i></span>
                    <span class="prf-app-menu-row__text">
                        <span class="prf-app-menu-row__label">Mes commandes</span>
                        <span class="prf-app-menu-row__desc">Suivi, livraisons et historique</span>
                    </span>
                    <?php if (!empty($user_nav_commandes_actives) && (int) $user_nav_commandes_actives > 0): ?>
                    <span class="prf-app-menu-row__badge" aria-label="<?php echo (int) $user_nav_commandes_actives; ?> commande<?php echo (int) $user_nav_commandes_actives > 1 ? 's' : ''; ?> en cours"><?php echo (int) $user_nav_commandes_actives; ?></span>
                    <?php endif; ?>
                    <span class="prf-app-menu-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </a>

                <a href="messages.php" class="prf-app-menu-row prf-app-menu-row--accent">
                    <span class="prf-app-menu-row__icon"><i class="fas fa-comment-dots" aria-hidden="true"></i></span>
                    <span class="prf-app-menu-row__text">
                        <span class="prf-app-menu-row__label">Messages</span>
                        <span class="prf-app-menu-row__desc">Discussions et négociations avec les vendeurs</span>
                    </span>
                    <?php if (!empty($user_nav_messages_non_lus) && (int) $user_nav_messages_non_lus > 0): ?>
                    <span class="prf-app-menu-row__badge" aria-label="<?php echo (int) $user_nav_messages_non_lus; ?> message<?php echo (int) $user_nav_messages_non_lus > 1 ? 's' : ''; ?> non lu<?php echo (int) $user_nav_messages_non_lus > 1 ? 's' : ''; ?>"><?php echo (int) $user_nav_messages_non_lus; ?></span>
                    <?php endif; ?>
                    <span class="prf-app-menu-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </a>

                <a href="favoris.php" class="prf-app-menu-row prf-app-menu-row--accent">
                    <span class="prf-app-menu-row__icon"><i class="fas fa-heart" aria-hidden="true"></i></span>
                    <span class="prf-app-menu-row__text">
                        <span class="prf-app-menu-row__label">Mes favoris</span>
                        <span class="prf-app-menu-row__desc">Les produits que vous avez aimés</span>
                    </span>
                    <?php if ($user_nav_favoris_count > 0): ?>
                    <span class="prf-app-menu-row__badge" aria-label="<?php echo (int) $user_nav_favoris_count; ?> favori<?php echo $user_nav_favoris_count > 1 ? 's' : ''; ?>"><?php echo (int) $user_nav_favoris_count; ?></span>
                    <?php endif; ?>
                    <span class="prf-app-menu-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </a>

                <a href="mes-boutiques-abonnees.php" class="prf-app-menu-row prf-app-menu-row--accent">
                    <span class="prf-app-menu-row__icon"><i class="fas fa-store" aria-hidden="true"></i></span>
                    <span class="prf-app-menu-row__text">
                        <span class="prf-app-menu-row__label">Mes boutiques</span>
                        <span class="prf-app-menu-row__desc">Boutiques que vous suivez</span>
                    </span>
                    <span class="prf-app-menu-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </a>
                </div>
                </section>

                <section class="prf-app-group">
                <p class="prf-app-menu-section">Paramètres du compte</p>
                <div class="prf-app-group__card">
                <button type="button" class="prf-app-menu-row prf-app-menu-row--settings" data-prf-panel="prfPanelInfos">
                    <span class="prf-app-menu-row__icon"><i class="fas fa-user-pen" aria-hidden="true"></i></span>
                    <span class="prf-app-menu-row__text"><span class="prf-app-menu-row__label">Informations personnelles</span></span>
                    <span class="prf-app-menu-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </button>
                <button type="button" class="prf-app-menu-row prf-app-menu-row--settings" data-prf-panel="prfPanelSecurite">
                    <span class="prf-app-menu-row__icon"><i class="fas fa-shield-halved" aria-hidden="true"></i></span>
                    <span class="prf-app-menu-row__text"><span class="prf-app-menu-row__label">S&eacute;curit&eacute; et mot de passe</span></span>
                    <span class="prf-app-menu-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </button>
                </div>
                </section>

                <section class="prf-app-group">
                <p class="prf-app-menu-section">Assistance &amp; informations</p>
                <div class="prf-app-group__card">
                <button type="button" class="prf-app-menu-row prf-app-menu-row--neutral" data-prf-panel="prfPanelAide">
                    <span class="prf-app-menu-row__icon"><i class="fas fa-circle-question" aria-hidden="true"></i></span>
                    <span class="prf-app-menu-row__text"><span class="prf-app-menu-row__label">Centre d&rsquo;aide</span></span>
                    <span class="prf-app-menu-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </button>
                <a href="/conditions-utilisation.php" class="prf-app-menu-row prf-app-menu-row--neutral">
                    <span class="prf-app-menu-row__icon"><i class="fas fa-file-contract" aria-hidden="true"></i></span>
                    <span class="prf-app-menu-row__text"><span class="prf-app-menu-row__label">Conditions d&rsquo;utilisation</span></span>
                    <span class="prf-app-menu-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </a>
                <a href="/politique-confidentialite.php" class="prf-app-menu-row prf-app-menu-row--neutral">
                    <span class="prf-app-menu-row__icon"><i class="fas fa-user-shield" aria-hidden="true"></i></span>
                    <span class="prf-app-menu-row__text"><span class="prf-app-menu-row__label">Confidentialit&eacute;</span></span>
                    <span class="prf-app-menu-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </a>
                <a href="/politique-suppression-compte.php" class="prf-app-menu-row prf-app-menu-row--neutral">
                    <span class="prf-app-menu-row__icon"><i class="fas fa-user-slash" aria-hidden="true"></i></span>
                    <span class="prf-app-menu-row__text"><span class="prf-app-menu-row__label">Suppression de compte</span></span>
                    <span class="prf-app-menu-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </a>
                </div>
                </section>

                <section class="prf-app-group prf-app-group--session">
                <div class="prf-app-group__card">
                <a href="deconnexion.php" class="prf-app-menu-row prf-app-menu-row--logout">
                    <span class="prf-app-menu-row__icon"><i class="fas fa-right-from-bracket" aria-hidden="true"></i></span>
                    <span class="prf-app-menu-row__text"><span class="prf-app-menu-row__label">D&eacute;connexion</span></span>
                </a>
                </div>
                </section>
            </nav>

        </div><!-- /.prf-app-sheet -->

        <div class="prf-app-panels" id="prfAppPanels" aria-live="polite">
            <div class="prf-app-panel" id="prfPanelInfos" hidden role="dialog" aria-modal="true" aria-labelledby="prfPanelInfosTitle">
                <button type="button" class="prf-app-panel-back" data-prf-back>
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
                </button>
                <h2 class="prf-app-panel-title" id="prfPanelInfosTitle">Modifier le profil</h2>
                <p class="prf-app-panel-desc">Mettez &agrave; jour vos informations de contact.</p>

                <form method="post" action="" id="form-infos">
                    <?php echo site_csrf_field(); ?>
                    <input type="hidden" name="modifier_profil" value="1">
                    <div class="prf-form-row">
                        <div class="prf-form-group">
                            <label class="prf-form-label" for="nom">Nom <span class="req">*</span></label>
                            <input type="text" id="nom" name="nom" class="prf-form-input"
                                value="<?php echo htmlspecialchars($user['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                required autocomplete="family-name" placeholder="Votre nom">
                        </div>
                        <div class="prf-form-group">
                            <label class="prf-form-label" for="prenom">Pr&eacute;nom <span class="opt">(optionnel)</span></label>
                            <input type="text" id="prenom" name="prenom" class="prf-form-input"
                                value="<?php echo htmlspecialchars((string)($user['prenom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                autocomplete="given-name" placeholder="Votre pr&eacute;nom">
                        </div>
                    </div>

                    <div class="prf-form-group">
                        <label class="prf-form-label" for="email">E-mail <span class="opt">(optionnel)</span></label>
                        <input type="email" id="email" name="email" class="prf-form-input"
                            value="<?php echo htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            autocomplete="email" placeholder="votre@email.com">
                    </div>

                    <div class="prf-form-group">
                        <label class="prf-form-label" for="telephone">T&eacute;l&eacute;phone <span class="req">*</span></label>
                        <div class="input-wrapper input-wrapper--intl-tel input-wrapper--intl-tel--profil">
                            <input type="tel" id="telephone" name="telephone" class="prf-form-input"
                                value="<?php echo htmlspecialchars((string)($user['telephone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                required placeholder="77 123 45 67" autocomplete="tel">
                        </div>
                        <span class="prf-form-hint">
                            <i class="fas fa-circle-info" aria-hidden="true"></i>
                            Indicatif pays au choix ; format international si possible.
                        </span>
                    </div>

                    <div class="prf-form-actions">
                        <button type="submit" name="modifier_profil" class="prf-submit">
                            <i class="fas fa-floppy-disk" aria-hidden="true"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>

            <!-- Panneau : sécurité -->
            <div class="prf-app-panel" id="prfPanelSecurite" hidden role="dialog" aria-modal="true" aria-labelledby="prfPanelSecuriteTitle">
                <button type="button" class="prf-app-panel-back" data-prf-back>
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
                </button>
                <h2 class="prf-app-panel-title" id="prfPanelSecuriteTitle">S&eacute;curit&eacute;</h2>
                <p class="prf-app-panel-desc">Changez votre mot de passe de connexion.</p>

                <form method="post" action="" id="form-mdp">
                    <?php echo site_csrf_field(); ?>
                    <div class="prf-form-group">
                        <label class="prf-form-label" for="current_password">Mot de passe actuel <span class="req">*</span></label>
                        <div class="prf-pw-wrapper">
                            <input type="password" id="current_password" name="current_password" class="prf-form-input"
                                placeholder="Mot de passe actuel" required autocomplete="current-password">
                            <button type="button" class="prf-pw-toggle" data-target="current_password" aria-label="Afficher ou masquer">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="prf-form-group">
                        <label class="prf-form-label" for="password">Nouveau mot de passe <span class="req">*</span></label>
                        <div class="prf-pw-wrapper">
                            <input type="password" id="password" name="password" class="prf-form-input"
                                placeholder="Nouveau mot de passe" required autocomplete="new-password">
                            <button type="button" class="prf-pw-toggle" data-target="password" aria-label="Afficher ou masquer">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="prf-pw-strength" id="pw-strength-bars" aria-hidden="true">
                            <div class="prf-pw-strength__bar" id="bar1"></div>
                            <div class="prf-pw-strength__bar" id="bar2"></div>
                            <div class="prf-pw-strength__bar" id="bar3"></div>
                            <div class="prf-pw-strength__bar" id="bar4"></div>
                        </div>
                        <span class="prf-form-hint">
                            <i class="fas fa-circle-info" aria-hidden="true"></i> Au moins 8 caract&egrave;res.
                        </span>
                    </div>

                    <div class="prf-form-group">
                        <label class="prf-form-label" for="password_confirm">Confirmer le mot de passe <span class="req">*</span></label>
                        <div class="prf-pw-wrapper">
                            <input type="password" id="password_confirm" name="password_confirm" class="prf-form-input"
                                placeholder="Confirmez le mot de passe" required autocomplete="new-password">
                            <button type="button" class="prf-pw-toggle" data-target="password_confirm" aria-label="Afficher ou masquer">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="prf-form-actions">
                        <button type="submit" name="modifier_mot_de_passe" class="prf-submit prf-submit--orange">
                            <i class="fas fa-key" aria-hidden="true"></i> Changer le mot de passe
                        </button>
                    </div>
                </form>
            </div>

            <div class="prf-app-panel" id="prfPanelAide" hidden role="dialog" aria-modal="true" aria-labelledby="prfPanelAideTitle">
                <button type="button" class="prf-app-panel-back" data-prf-back>
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
                </button>
                <h2 class="prf-app-panel-title" id="prfPanelAideTitle">Centre d&rsquo;aide</h2>
                <p class="prf-app-panel-desc">Signalement, remboursement et contact du support COLObanes.</p>

                <section class="prf-aide-block">
                    <h3 class="prf-aide-block__title">
                        <i class="fas fa-flag" aria-hidden="true"></i>
                        Signaler ou d&eacute;noncer
                    </h3>
                    <p class="prf-aide-block__text">
                        Pour signaler un produit, un vendeur ou un comportement abusif sur COLObanes :
                    </p>
                    <ul class="prf-aide-list">
                        <li>Dans <strong>Messages</strong>, ouvrez la conversation puis le menu <strong>Signaler</strong> (⋮).</li>
                        <li>Indiquez le num&eacute;ro de commande, le lien produit ou une capture d&rsquo;&eacute;cran.</li>
                        <li>Vous pouvez aussi &eacute;crire &agrave;
                            <a href="mailto:<?php echo htmlspecialchars($prf_aide_email, ENT_QUOTES, 'UTF-8'); ?>?subject=<?php echo rawurlencode('Signalement COLObanes'); ?>"><?php echo htmlspecialchars($prf_aide_email, ENT_QUOTES, 'UTF-8'); ?></a>
                            (objet&nbsp;: «&nbsp;Signalement&nbsp;»).
                        </li>
                    </ul>
                    <p class="prf-aide-block__note">
                        Les signalements sont examin&eacute;s dans un d&eacute;lai raisonnable (objectif&nbsp;: sous 72&nbsp;h ouvr&eacute;es).
                    </p>
                </section>

                <section class="prf-aide-block">
                    <h3 class="prf-aide-block__title">
                        <i class="fas fa-rotate-left" aria-hidden="true"></i>
                        Demander un remboursement
                    </h3>
                    <p class="prf-aide-block__text">
                        Pour un produit d&eacute;fectueux, non re&ccedil;u, non conforme ou une commande annul&eacute;e&nbsp;:
                    </p>
                    <ol class="prf-aide-steps">
                        <li>Contactez d&rsquo;abord le vendeur via <a href="messages.php">Messages</a> (depuis votre commande).</li>
                        <li>Si le litige persiste, envoyez un e-mail &agrave;
                            <a href="mailto:<?php echo htmlspecialchars($prf_aide_email, ENT_QUOTES, 'UTF-8'); ?>?subject=<?php echo rawurlencode('Demande de remboursement'); ?>"><?php echo htmlspecialchars($prf_aide_email, ENT_QUOTES, 'UTF-8'); ?></a>
                            avec le <strong>num&eacute;ro de commande</strong>, le produit concern&eacute; et des photos si besoin.
                        </li>
                        <li>Retrouvez vos num&eacute;ros de commande dans <a href="mes-commandes.php">Mes commandes</a>.</li>
                    </ol>
                    <p class="prf-aide-block__note">
                        Remboursement int&eacute;gral, avoir ou &eacute;change selon la situation et les options propos&eacute;es par le vendeur ou COLObanes.
                    </p>
                </section>

                <section class="prf-aide-block prf-aide-block--contact">
                    <h3 class="prf-aide-block__title">
                        <i class="fas fa-headset" aria-hidden="true"></i>
                        Nous contacter
                    </h3>
                    <div class="prf-aide-contact">
                        <div class="prf-aide-contact__row">
                            <span class="prf-aide-contact__icon" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                            <div>
                                <span class="prf-aide-contact__label">E-mail</span>
                                <a href="mailto:<?php echo htmlspecialchars($prf_aide_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($prf_aide_email, ENT_QUOTES, 'UTF-8'); ?></a>
                            </div>
                        </div>
                        <div class="prf-aide-contact__row">
                            <span class="prf-aide-contact__icon" aria-hidden="true"><i class="fas fa-location-dot"></i></span>
                            <div>
                                <span class="prf-aide-contact__label">Adresse</span>
                                <span><?php echo htmlspecialchars($prf_aide_adresse, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    </div>
                    <p class="prf-aide-block__text prf-aide-block__text--last">
                        Pr&eacute;cisez toujours votre nom, votre num&eacute;ro de t&eacute;l&eacute;phone et, si possible, votre num&eacute;ro de commande pour une r&eacute;ponse plus rapide.
                    </p>
                </section>
            </div>
        </div><!-- /.prf-app-panels -->

        </div><!-- /.prf-app-main -->
        </div><!-- /.prf-app-layout -->
    </div><!-- /.prf-app -->

    <?php include __DIR__ . '/../includes/auth_intl_tel_scripts.php'; ?>
    <script src="/js/user-profil-theme.js<?php echo asset_version_query(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var menuView = document.getElementById('prfMenuView');
            var panels = document.querySelectorAll('.prf-app-panel');
            var userContent = document.getElementById('userContent');
            var prfApp = document.querySelector('.prf-app');

            function scrollPrfToTop() {
                window.scrollTo(0, 0);
                if (userContent) {
                    userContent.scrollTop = 0;
                }
                if (prfApp) {
                    prfApp.scrollTop = 0;
                }
            }

            function showPrfPanel(panelId) {
                if (!menuView || !panelId) return;
                scrollPrfToTop();
                menuView.hidden = true;
                panels.forEach(function (panel) {
                    panel.hidden = panel.id !== panelId;
                });
                if (userContent) {
                    userContent.classList.add('user-content--prf-panel-open');
                }
                if (prfApp) {
                    prfApp.classList.add('prf-app--panel-open');
                }
            }

            function showPrfMenu() {
                if (!menuView) return;
                menuView.hidden = false;
                panels.forEach(function (panel) {
                    panel.hidden = true;
                });
                if (userContent) {
                    userContent.classList.remove('user-content--prf-panel-open');
                }
                if (prfApp) {
                    prfApp.classList.remove('prf-app--panel-open');
                }
            }

            document.querySelectorAll('[data-prf-panel]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    showPrfPanel(btn.getAttribute('data-prf-panel'));
                });
            });

            document.querySelectorAll('[data-prf-back]').forEach(function (btn) {
                btn.addEventListener('click', showPrfMenu);
            });

            var avatarEditBtn = document.getElementById('prfAvatarEditBtn');
            if (avatarEditBtn) {
                avatarEditBtn.addEventListener('click', function () {
                    showPrfPanel('prfPanelInfos');
                });
            }

            var hashPanelMap = {
                infos: 'prfPanelInfos',
                securite: 'prfPanelSecurite',
                aide: 'prfPanelAide'
            };
            var initialHash = (window.location.hash || '').replace(/^#/, '');
            if (hashPanelMap[initialHash]) {
                showPrfPanel(hashPanelMap[initialHash]);
            }

            if (typeof window.initAuthIntlTel === 'function') {
                window.initAuthIntlTel('telephone');
            }

            document.querySelectorAll('.prf-pw-toggle').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var targetId = btn.getAttribute('data-target');
                    var input = document.getElementById(targetId);
                    var icon = btn.querySelector('i');
                    if (!input || !icon) return;
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.replace('fa-eye', 'fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.replace('fa-eye-slash', 'fa-eye');
                    }
                });
            });

            var pwInput = document.getElementById('password');
            var bars = [
                document.getElementById('bar1'),
                document.getElementById('bar2'),
                document.getElementById('bar3'),
                document.getElementById('bar4')
            ];
            var colors = ['#ef4444', '#f97316', '#eab308', '#16a34a'];

            if (pwInput) {
                pwInput.addEventListener('input', function () {
                    var val = pwInput.value;
                    var score = 0;
                    if (val.length >= 6) score++;
                    if (val.length >= 10) score++;
                    if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
                    if (/[^a-zA-Z0-9]/.test(val)) score++;

                    bars.forEach(function (bar, idx) {
                        if (!bar) return;
                        bar.style.background = idx < score ? colors[Math.max(0, score - 1)] : 'rgba(53,100,166,0.1)';
                    });
                });
            }
        });
    </script>

    <?php include 'includes/user_footer.php'; ?>
</body>
</html>
