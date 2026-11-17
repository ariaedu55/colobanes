<?php
/**
 * Mon compte super administrateur — profil et mot de passe
 */
require_once __DIR__ . '/includes/require_login.php';
require_once dirname(__DIR__) . '/models/model_super_admin.php';
require_once dirname(__DIR__) . '/controllers/controller_super_admin.php';

$sa_id = (int) ($_SESSION['super_admin_id'] ?? 0);
$compte = get_super_admin_by_id($sa_id);
if (!$compte) {
    header('Location: logout.php');
    exit;
}

$csrf = super_admin_csrf_token();
$profile_result = ['success' => false, 'message' => ''];
$password_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $profile_result = process_super_admin_update_own_profile();
    if (!empty($profile_result['success'])) {
        $compte = get_super_admin_by_id($sa_id);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $password_result = process_super_admin_update_own_password();
}

$nom_val = isset($_POST['nom']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])
    ? trim((string) $_POST['nom'])
    : trim((string) ($compte['nom'] ?? ''));
$prenom_val = isset($_POST['prenom']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])
    ? trim((string) $_POST['prenom'])
    : trim((string) ($compte['prenom'] ?? ''));
$email_val = isset($_POST['email']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])
    ? trim((string) $_POST['email'])
    : trim((string) ($compte['email'] ?? ''));

$hero_prenom = trim((string) ($compte['prenom'] ?? ''));
$hero_nom = trim((string) ($compte['nom'] ?? ''));
$hero_display = trim($hero_prenom . ' ' . $hero_nom);
if ($hero_display === '') {
    $hero_display = $hero_nom !== '' ? $hero_nom : 'Super administrateur';
}
$hero_initials = '';
if ($hero_prenom !== '') {
    $hero_initials .= mb_strtoupper(mb_substr($hero_prenom, 0, 1, 'UTF-8'), 'UTF-8');
}
if ($hero_nom !== '') {
    $hero_initials .= mb_strtoupper(mb_substr($hero_nom, 0, 1, 'UTF-8'), 'UTF-8');
}
if ($hero_initials === '') {
    $hero_initials = 'SA';
}
$hero_statut = (string) ($compte['statut'] ?? 'actif');
$hero_email = trim((string) ($compte['email'] ?? ''));
$hero_dl = $compte['derniere_connexion'] ?? '';
$hero_dl_fmt = $hero_dl !== '' ? date('d/m/Y à H:i', strtotime((string) $hero_dl)) : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon compte — Super Admin</title>
    <?php require_once dirname(__DIR__) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 1) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-comptes.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-comptes-page sa-compte-page">
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <div class="sa-comptes-shell">
        <header class="sa-users-hero sa-compte-hero" aria-labelledby="sa-compte-title">
            <div class="sa-users-hero__inner">
                <div class="sa-compte-hero__intro">
                    <h1 class="sa-users-hero__title" id="sa-compte-title">Mon compte</h1>
                </div>
                <aside class="sa-compte-hero__card" aria-label="Profil connecté">
                    <span class="sa-compte-hero__avatar" aria-hidden="true"><?php echo htmlspecialchars($hero_initials, ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="sa-compte-hero__meta">
                        <p class="sa-compte-hero__name"><?php echo htmlspecialchars($hero_display, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="sa-compte-hero__email"><?php echo htmlspecialchars($hero_email, ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="sa-compte-hero__chips">
                            <?php if ($hero_statut === 'actif'): ?>
                                <span class="sa-badge sa-badge--ok sa-compte-hero__badge">Compte actif</span>
                            <?php else: ?>
                                <span class="sa-badge sa-badge--off sa-compte-hero__badge">Compte inactif</span>
                            <?php endif; ?>
                            <?php if ($hero_dl_fmt !== ''): ?>
                                <span class="sa-compte-hero__chip">
                                    <i class="fas fa-clock" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars($hero_dl_fmt, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </aside>
            </div>
        </header>

        <div class="sa-compte-layout">
            <section class="sa-users-panel sa-compte-panel" aria-labelledby="sa-profil-title">
                <div class="sa-users-panel__head">
                    <h2 id="sa-profil-title">
                        <i class="fas fa-user-pen" aria-hidden="true"></i> Informations personnelles
                    </h2>
                </div>

                <?php if (!empty($profile_result['message'])): ?>
                    <div class="sa-compte-panel__alert">
                        <div class="sa-alert sa-alert--<?php echo !empty($profile_result['success']) ? 'ok' : 'err'; ?>" role="<?php echo !empty($profile_result['success']) ? 'status' : 'alert'; ?>">
                            <i class="fas fa-<?php echo !empty($profile_result['success']) ? 'check-circle' : 'exclamation-circle'; ?>" aria-hidden="true"></i>
                            <span><?php echo nl2br(htmlspecialchars(str_ireplace(['<br>', '<br/>', '<br />'], "\n", (string) $profile_result['message']), ENT_QUOTES, 'UTF-8')); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" action="compte.php" class="sa-compte-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="sa-compte-panel__body">
                        <div class="sa-compte-fields-row">
                            <div class="sa-comptes-field">
                                <label for="profil_nom">Nom</label>
                                <input type="text" id="profil_nom" name="nom" required maxlength="120"
                                    value="<?php echo htmlspecialchars($nom_val, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="sa-comptes-field">
                                <label for="profil_prenom">Prénom <span class="sa-compte-label-hint">(optionnel)</span></label>
                                <input type="text" id="profil_prenom" name="prenom" maxlength="120"
                                    value="<?php echo htmlspecialchars($prenom_val, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="sa-comptes-field">
                            <label for="profil_email">Adresse e-mail</label>
                            <input type="email" id="profil_email" name="email" required autocomplete="email"
                                value="<?php echo htmlspecialchars($email_val, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="sa-compte-panel__foot">
                        <button type="submit" class="sa-compte-btn-primary">
                            <i class="fas fa-save" aria-hidden="true"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </section>

            <section class="sa-users-panel sa-compte-panel" aria-labelledby="sa-mdp-title">
                <div class="sa-users-panel__head">
                    <h2 id="sa-mdp-title">
                        <i class="fas fa-shield-halved" aria-hidden="true"></i> Sécurité
                    </h2>
                    <p class="sa-users-panel__meta">Mot de passe de connexion</p>
                </div>

                <?php if (!empty($password_result['message'])): ?>
                    <div class="sa-compte-panel__alert">
                        <div class="sa-alert sa-alert--<?php echo !empty($password_result['success']) ? 'ok' : 'err'; ?>" role="<?php echo !empty($password_result['success']) ? 'status' : 'alert'; ?>">
                            <i class="fas fa-<?php echo !empty($password_result['success']) ? 'check-circle' : 'exclamation-circle'; ?>" aria-hidden="true"></i>
                            <span><?php echo nl2br(htmlspecialchars(str_ireplace(['<br>', '<br/>', '<br />'], "\n", (string) $password_result['message']), ENT_QUOTES, 'UTF-8')); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" action="compte.php" class="sa-compte-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="update_password" value="1">

                    <div class="sa-compte-panel__body">
                        <div class="sa-comptes-field">
                            <label for="current_password">Mot de passe actuel</label>
                            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="sa-compte-fields-row">
                            <div class="sa-comptes-field">
                                <label for="new_password">Nouveau mot de passe</label>
                                <input type="password" id="new_password" name="password" required autocomplete="new-password"
                                    placeholder="Nouveau mot de passe">
                            </div>
                            <div class="sa-comptes-field">
                                <label for="new_password_confirm">Confirmation</label>
                                <input type="password" id="new_password_confirm" name="password_confirm" required autocomplete="new-password"
                                    placeholder="Répéter le mot de passe">
                            </div>
                        </div>
                    </div>

                    <div class="sa-compte-panel__foot">
                        <button type="submit" class="sa-compte-btn-primary sa-compte-btn-primary--outline">
                            <i class="fas fa-key" aria-hidden="true"></i> Mettre à jour le mot de passe
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
