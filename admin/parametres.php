<?php
/**
 * Page des paramètres admin — redesign v2
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/includes/require_admin_session.php';



require_once __DIR__ . '/includes/require_access.php';

$__param_role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');
$__param_show_site_modules = in_array($__param_role, ['admin', 'plateforme', 'vendeur'], true);
$__param_show_comptes      = in_array($__param_role, ['admin', 'plateforme', 'vendeur', 'rh'], true);
$__param_retour            = admin_role_default_redirect_path($__param_role);

require_once __DIR__ . '/../includes/site_url.php';
require_once __DIR__ . '/../includes/marketplace_helpers.php';

$__cert_niveau_actif = null;
$__cert_demande_en_cours = false;
if ($__param_role === 'vendeur' && file_exists(__DIR__ . '/../models/model_vendeur_certification.php')) {
    require_once __DIR__ . '/../models/model_vendeur_certification.php';
    $__cert_niveau_actif = vendeur_certification_get_niveau_actif((int) ($_SESSION['admin_id'] ?? 0));
    $__cert_demande_en_cours = vendeur_certification_get_demande_en_cours((int) ($_SESSION['admin_id'] ?? 0)) !== null;
}

$__vendeur_boutique_slug = ($__param_role === 'vendeur')
    ? trim((string)($_SESSION['admin_boutique_slug'] ?? ''))
    : '';
$__vendeur_site_path     = '';
$__vendeur_site_full_url = '';
if ($__vendeur_boutique_slug !== '') {
    $__vendeur_site_path     = boutique_url('index.php', $__vendeur_boutique_slug);
    $__vendeur_site_full_url = rtrim(get_site_base_url(), '/') . $__vendeur_site_path;
}
$__voir_site_href = ($__vendeur_boutique_slug !== '') ? $__vendeur_site_path : '../index.php';
$__vendeur_boutique_nom_aff = trim((string)($_SESSION['admin_boutique_nom'] ?? ''));
if ($__vendeur_boutique_nom_aff === '') { $__vendeur_boutique_nom_aff = 'Ma boutique'; }

if ($__param_role === 'vendeur') {
    require_once __DIR__ . '/../models/model_admin.php';
    $vbl_admin_id = (int) ($_SESSION['admin_id'] ?? 0);
    $vbl_admin = $vbl_admin_id > 0 ? get_admin_by_id($vbl_admin_id) : null;
    if ($vbl_admin && ($vbl_admin['role'] ?? '') === 'vendeur') {
        admin_sync_vendeur_boutique_session_from_admin($vbl_admin);
        $__vendeur_boutique_slug = trim((string) ($vbl_admin['boutique_slug'] ?? ''));
        $__vendeur_boutique_nom_aff = trim((string) ($vbl_admin['boutique_nom'] ?? ''));
        if ($__vendeur_boutique_nom_aff === '') {
            $__vendeur_boutique_nom_aff = 'Ma boutique';
        }
        $__vendeur_site_path = '';
        $__vendeur_site_full_url = '';
        if ($__vendeur_boutique_slug !== '') {
            $__vendeur_site_path = boutique_url('index.php', $__vendeur_boutique_slug);
            $__vendeur_site_full_url = rtrim(get_site_base_url(), '/') . $__vendeur_site_path;
        }
        $__voir_site_href = ($__vendeur_boutique_slug !== '') ? $__vendeur_site_path : '../index.php';
    }
    require_once __DIR__ . '/includes/vendeur_boutique_localisation.php';
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Infos admin
$admin_prenom   = trim((string)($_SESSION['admin_prenom'] ?? ''));
$admin_nom      = trim((string)($_SESSION['admin_nom'] ?? ''));
$admin_nom_aff  = trim($admin_prenom . ' ' . $admin_nom);
if ($admin_nom_aff === '') { $admin_nom_aff = 'Admin'; }
$admin_initial  = $admin_prenom !== '' ? mb_strtoupper(mb_substr($admin_prenom, 0, 1, 'UTF-8'), 'UTF-8') : 'A';

// Labels de rôle
$role_labels = [
    'admin'      => ['label' => 'Administrateur', 'icon' => 'fa-shield-halved', 'color' => '#3564a6'],
    'plateforme' => ['label' => 'Gestionnaire',   'icon' => 'fa-layer-group',   'color' => '#7c3aed'],
    'vendeur'    => ['label' => 'Vendeur',         'icon' => 'fa-store',         'color' => '#FF6B35'],
    'rh'         => ['label' => 'RH',              'icon' => 'fa-users',         'color' => '#16a34a'],
];
$rl = $role_labels[$__param_role] ?? ['label' => ucfirst($__param_role), 'icon' => 'fa-user-cog', 'color' => '#3564a6'];

$prm_hero_logo_url = '';
$prm_hero_title = $admin_nom_aff;
$prm_hero_owner = '';
if ($__param_role === 'vendeur') {
    if (!empty($vbl_admin)) {
        $prm_logo_rel = trim((string) ($vbl_admin['boutique_logo'] ?? ''));
        if ($prm_logo_rel !== '') {
            require_once __DIR__ . '/../includes/image_optimizer.php';
            $prm_hero_logo_url = upload_image_url($prm_logo_rel, 'sm');
        }
    }
    if ($__vendeur_boutique_nom_aff !== '' && $__vendeur_boutique_nom_aff !== 'Ma boutique') {
        $prm_hero_title = $__vendeur_boutique_nom_aff;
        if ($admin_nom_aff !== '' && $admin_nom_aff !== 'Admin'
            && mb_strtolower($admin_nom_aff, 'UTF-8') !== mb_strtolower($prm_hero_title, 'UTF-8')) {
            $prm_hero_owner = $admin_nom_aff;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Param&egrave;tres &mdash; Administration</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 1) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-vendeur-share.css<?php echo asset_version_query(); ?>">
    <?php if ($__param_role === 'vendeur'): ?>
    <link rel="stylesheet" href="/css/admin-boutique-localisation.css<?php echo asset_version_query(); ?>">
    <?php endif; ?>
    <style>
        /* ===== PARAMÈTRES v2 ===== */

        .prm-page {
            max-width: 980px;
            margin: 0 auto;
            padding: clamp(16px, 4vw, 36px) clamp(14px, 4vw, 24px) 80px;
            display: flex;
            flex-direction: column;
            gap: 22px;
            font-family: var(--font-corps, 'Poppins', sans-serif);
        }

        /* ---- Page header ---- */
        .prm-page-header {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .prm-page-header__row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .prm-page-header__back {
            flex-shrink: 0;
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            box-shadow: none;
            outline: none;
            color: var(--couleur-dominante, #3564a6);
            text-decoration: none;
            font-size: 1.05rem;
            transition: opacity 0.2s;
        }

        .prm-page-header__back:hover {
            opacity: 0.75;
        }

        .prm-page-header__back:focus-visible {
            outline: 2px solid rgba(53, 100, 166, 0.35);
            outline-offset: 2px;
            border-radius: 4px;
        }

        .prm-page-header__left  { display: flex; flex-direction: column; gap: 3px; flex: 1; min-width: 0; }

        .prm-page-header__eyebrow {
            font-size: 0.73rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.12em;
            color: var(--couleur-dominante, #3564a6);
            display: flex; align-items: center; gap: 5px;
        }

        .prm-page-header__title {
            margin: 0;
            font-size: clamp(1.3rem, 3vw, 1.75rem);
            font-weight: 800; color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
            line-height: 1.15; letter-spacing: -0.025em;
        }

        .prm-page-header__actions { display: flex; gap: 9px; align-items: center; flex-wrap: wrap; }

        /* ---- Boutons header ---- */
        .prm-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px; border-radius: 11px;
            font-size: 0.81rem; font-weight: 700;
            cursor: pointer; border: none;
            text-decoration: none; font-family: var(--font-corps, 'Poppins', sans-serif);
            transition: all 0.2s; white-space: nowrap;
        }

        .prm-btn--primary { background: var(--couleur-dominante, #3564a6); color: #fff; }
        .prm-btn--primary:hover { background: var(--bleu-fonce, #2d5690); }
        .prm-btn--outline { background: #fff; color: var(--couleur-dominante, #3564a6); border: 1px solid rgba(53,100,166,0.22); }
        .prm-btn--outline:hover { background: rgba(53,100,166,0.05); }
        .prm-btn--orange { background: var(--couleur-dominante, #3564a6); color: #fff; }
        .prm-btn--orange:hover { background: var(--bleu-fonce, #2d5690); }

        /* ---- Hero identité — carte professionnelle ---- */
        .prm-hero {
            background: #fff;
            border: 1px solid rgba(53, 100, 166, 0.1);
            border-radius: 18px;
            padding: 18px 20px 18px 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(53, 100, 166, 0.08);
        }

        .prm-hero::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, var(--couleur-dominante, #3564a6) 0%, var(--bleu-clair, #4a7ab8) 100%);
            pointer-events: none;
        }

        .prm-hero__inner {
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
        }

        .prm-hero__avatar {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: rgba(53, 100, 166, 0.08);
            border: 1px solid rgba(53, 100, 166, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--couleur-dominante, #3564a6);
            font-family: var(--font-titres, 'Poppins', sans-serif);
            flex-shrink: 0;
            overflow: hidden;
        }

        .prm-hero__avatar--logo {
            background: #fff;
            padding: 3px;
        }

        .prm-hero__avatar--logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
            display: block;
        }

        .prm-hero__body {
            flex: 1;
            min-width: 0;
        }

        .prm-hero__eyebrow {
            margin: 0 0 4px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--gris-moyen, #737373);
        }

        .prm-hero__name {
            margin: 0;
            font-size: clamp(1.05rem, 2.5vw, 1.28rem);
            font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        .prm-hero__owner {
            margin: 4px 0 0;
            font-size: 0.78rem;
            color: var(--gris-fonce, #4a4a4a);
        }

        .prm-hero__meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }

        .prm-hero__role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            background: rgba(53, 100, 166, 0.1);
            color: var(--couleur-dominante, #3564a6);
        }

        .prm-hero__role-badge i {
            font-size: 0.68rem;
        }

        .prm-hero__email {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.76rem;
            color: var(--gris-moyen, #737373);
            min-width: 0;
            word-break: break-word;
        }

        .prm-hero__email i {
            font-size: 0.68rem;
            flex-shrink: 0;
        }

        /* ---- Alert success ---- */
        .prm-alert {
            display: flex; align-items: flex-start; gap: 11px;
            padding: 14px 18px; border-radius: 14px;
            font-size: 0.84rem; font-weight: 500;
            border: 1px solid transparent;
        }

        .prm-alert--success { background: rgba(34,197,94,0.09); border-color: rgba(34,197,94,0.22); color: #15803d; }

        .prm-alert i { margin-top: 2px; font-size: 1rem; flex-shrink: 0; }

        /* ---- Section boutique vendeur ---- */
        .prm-boutique {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(53, 100, 166, 0.1);
            box-shadow: 0 8px 28px rgba(53, 100, 166, 0.08);
            overflow: hidden;
            position: relative;
        }

        .prm-boutique::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, var(--couleur-dominante, #3564a6) 0%, var(--bleu-clair, #4a7ab8) 100%);
            pointer-events: none;
        }

        .prm-boutique__head {
            padding: 18px 20px 0 24px;
        }

        .prm-boutique__head-icon {
            display: none;
        }

        .prm-boutique__head-text h3 {
            font-size: 1rem;
            font-weight: 800;
            color: var(--titres, #0d0d0d);
            margin: 0;
            font-family: var(--font-titres, 'Poppins', sans-serif);
            letter-spacing: -0.02em;
        }

        .prm-boutique__head-text p {
            font-size: 0.78rem;
            color: var(--gris-moyen, #737373);
            margin: 6px 0 0;
            line-height: 1.45;
        }

        .prm-boutique__body {
            padding: 16px 20px 20px 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .prm-boutique__field-label {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--gris-moyen, #737373);
            margin-bottom: 8px;
        }

        .prm-boutique__url-box {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0;
            border: 1px solid rgba(53, 100, 166, 0.16);
            border-radius: 14px;
            background: var(--blanc-casse, #fafafa);
            overflow: hidden;
        }

        .prm-boutique__url-row {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .prm-boutique__url-input {
            flex: 1 1 auto;
            min-width: 0;
            width: 100%;
            padding: 12px 14px;
            border: none;
            border-radius: 0;
            background: transparent;
            font-size: 0.8rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            color: var(--titres, #0d0d0d);
            outline: none;
        }

        .prm-boutique__actions {
            display: flex;
            gap: 0;
            flex-shrink: 0;
            border-top: 1px solid rgba(53, 100, 166, 0.12);
        }

        .prm-boutique__action-btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 11px 14px;
            border-radius: 0;
            font-size: 0.78rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-family: var(--font-corps, 'Poppins', sans-serif);
            transition: background 0.18s ease, color 0.18s ease;
        }

        .prm-boutique__action-btn + .prm-boutique__action-btn {
            border-left: 1px solid rgba(53, 100, 166, 0.12);
        }

        .prm-boutique__action-btn--copy {
            background: #fff;
            color: var(--couleur-dominante, #3564a6);
        }

        .prm-boutique__action-btn--copy:hover {
            background: rgba(53, 100, 166, 0.06);
        }

        .prm-boutique__action-btn--share {
            background: var(--couleur-dominante, #3564a6);
            color: #fff;
        }

        .prm-boutique__action-btn--share:hover {
            background: var(--bleu-fonce, #2d5690);
        }

        .prm-boutique__feedback {
            font-size: 0.78rem;
            font-weight: 600;
            color: #15803d;
            min-height: 1.2em;
            padding-left: 2px;
        }

        .prm-boutique__deeplink {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 11px 14px;
            background: rgba(53, 100, 166, 0.05);
            border: 1px solid rgba(53, 100, 166, 0.1);
            border-radius: 12px;
            font-size: 0.78rem;
            color: var(--gris-fonce, #4a4a4a);
            line-height: 1.45;
        }

        .prm-boutique__deeplink i {
            flex-shrink: 0;
            margin-top: 2px;
            color: var(--couleur-dominante, #3564a6);
        }

        .prm-boutique__warn {
            padding: 14px 16px;
            border-radius: 12px;
            background: rgba(255, 107, 53, 0.08);
            border: 1px solid rgba(255, 107, 53, 0.18);
            font-size: 0.82rem;
            color: #7c2d12;
            line-height: 1.5;
        }

        .prm-boutique__warn a {
            color: var(--couleur-dominante, #3564a6);
            font-weight: 600;
        }

        @media (min-width: 560px) {
            .prm-boutique__url-box {
                flex-direction: row;
                align-items: center;
            }

            .prm-boutique__actions {
                border-top: none;
                border-left: 1px solid rgba(53, 100, 166, 0.12);
            }

            .prm-boutique__action-btn {
                flex: 0 0 auto;
                padding: 0 16px;
                min-height: 46px;
            }
        }

        /* ---- Titre de section ---- */
        .prm-section-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            padding-bottom: 9px;
            border-bottom: 1px solid rgba(53, 100, 166, 0.12);
        }

        .prm-section-head__title {
            margin: 0;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: var(--gris-moyen, #737373);
            font-family: var(--font-corps, 'Poppins', sans-serif);
        }

        .prm-section-head__meta {
            margin: 0;
            font-size: 0.75rem;
            color: var(--gris-clair, #a3a3a3);
        }

        /* ---- Liste de navigation ---- */
        .prm-nav-list {
            background: #fff;
            border: 1px solid rgba(53, 100, 166, 0.1);
            border-radius: 16px;
            overflow: hidden;
        }

        .prm-nav-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 15px 18px;
            text-decoration: none;
            color: var(--titres, #0d0d0d);
            border-top: 1px solid rgba(53, 100, 166, 0.08);
        }

        .prm-nav-row:first-child {
            border-top: none;
        }

        .prm-nav-row:hover,
        .prm-nav-row:focus-visible {
            background: rgba(53, 100, 166, 0.04);
            outline: none;
        }

        .prm-nav-row__icon {
            width: 24px;
            flex-shrink: 0;
            text-align: center;
            font-size: 0.95rem;
            color: var(--couleur-dominante, #3564a6);
        }

        .prm-nav-row__body {
            flex: 1;
            min-width: 0;
        }

        .prm-nav-row__title {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            line-height: 1.3;
        }

        .prm-nav-row__sub {
            display: block;
            margin-top: 2px;
            font-size: 0.74rem;
            color: var(--gris-moyen, #737373);
            line-height: 1.4;
        }

        .prm-nav-row__chev {
            flex-shrink: 0;
            font-size: 0.72rem;
            color: var(--gris-clair, #a3a3a3);
        }

        /* ---- Responsive ---- */
        @media (max-width: 600px) {
            .prm-hero__inner { align-items: flex-start; }
        }
    </style>
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="prm-page">

        <!-- ===== PAGE HEADER ===== -->
        <?php if ($__param_role === 'vendeur'): ?>
        <header class="vd-page-head">
            <div class="vd-page-head__text">
                <h1 class="vd-page-head__title">Ma boutique</h1>
                <p class="vd-page-head__sub">Informations publiques, lien, localisation et apparence de votre boutique.</p>
            </div>
        </header>
        <?php else: ?>
        <?php
        $__param_back_href = $__param_retour;
        ?>
        <header class="prm-page-header">
            <div class="prm-page-header__row">
                <a href="<?php echo htmlspecialchars($__param_back_href, ENT_QUOTES, 'UTF-8'); ?>" class="prm-page-header__back" aria-label="Retour">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                </a>
                <h1 class="prm-page-header__title">
                    <?php echo $__param_show_site_modules ? 'Param&egrave;tres' : 'Compte &amp; raccourcis'; ?>
                </h1>
            </div>
        </header>
        <?php endif; ?>

        <!-- ===== HERO IDENTITÉ ===== -->
        <div class="prm-hero">
            <div class="prm-hero__inner">
                <div class="prm-hero__avatar<?php echo ($prm_hero_logo_url !== '') ? ' prm-hero__avatar--logo' : ''; ?>">
                    <?php if ($prm_hero_logo_url !== ''): ?>
                    <img src="<?php echo htmlspecialchars($prm_hero_logo_url, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="<?php echo htmlspecialchars($prm_hero_title, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php else: ?>
                    <?php echo htmlspecialchars($admin_initial); ?>
                    <?php endif; ?>
                </div>
                <div class="prm-hero__body">
                    <p class="prm-hero__eyebrow"><?php echo $__param_role === 'vendeur' ? 'Boutique en ligne' : 'Compte administrateur'; ?></p>
                    <h2 class="prm-hero__name"><?php echo htmlspecialchars($prm_hero_title, ENT_QUOTES, 'UTF-8'); ?></h2>
                    <?php if ($prm_hero_owner !== ''): ?>
                    <p class="prm-hero__owner"><?php echo htmlspecialchars($prm_hero_owner, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <div class="prm-hero__meta">
                        <span class="prm-hero__role-badge">
                            <i class="fas <?php echo htmlspecialchars($rl['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                            <?php echo htmlspecialchars($rl['label'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <?php if (!empty($_SESSION['admin_email'])): ?>
                        <span class="prm-hero__email">
                            <i class="fas fa-envelope" aria-hidden="true"></i>
                            <?php echo htmlspecialchars((string) $_SESSION['admin_email'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== ALERT SUCCESS ===== -->
        <?php if (!empty($success_message)): ?>
            <div class="prm-alert prm-alert--success" role="status">
                <i class="fas fa-circle-check"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <!-- ===== BOUTIQUE VENDEUR ===== -->
        <?php if ($__param_role === 'vendeur'): ?>
            <div class="prm-boutique" role="region" aria-labelledby="prm-boutique-title">
                <div class="prm-boutique__head">
                    <div class="prm-boutique__head-text">
                        <h3 id="prm-boutique-title">Lien de ma boutique en ligne</h3>
                        <p>Le lien de votre vitrine.</p>
                    </div>
                </div>
                <div class="prm-boutique__body">
                    <?php if ($__vendeur_boutique_slug !== ''): ?>
                        <div class="prm-boutique__url-row">
                            <span class="prm-boutique__field-label">URL publique</span>
                            <div class="prm-boutique__url-box">
                                <label class="visually-hidden" for="vendeurBoutiquePublicUrl">URL publique de la boutique</label>
                                <input type="text" id="vendeurBoutiquePublicUrl"
                                    class="prm-boutique__url-input" readonly
                                    value="<?php echo htmlspecialchars($__vendeur_site_full_url, ENT_QUOTES, 'UTF-8'); ?>"
                                    autocomplete="off">
                                <div class="prm-boutique__actions">
                                    <button type="button" class="prm-boutique__action-btn prm-boutique__action-btn--copy"
                                        id="vendeurCopyBoutiqueUrl">
                                        <i class="fas fa-copy" aria-hidden="true"></i> Copier
                                    </button>
                                    <button type="button" class="prm-boutique__action-btn prm-boutique__action-btn--share"
                                        id="vendeurShareBoutiqueUrl"
                                        aria-haspopup="dialog"
                                        aria-controls="platformShareModal">
                                        <i class="fas fa-share-nodes" aria-hidden="true"></i> Partager
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p id="vendeurCopyBoutiqueFeedback" class="prm-boutique__feedback" aria-live="polite"></p>
                    <?php else: ?>
                        <div class="prm-boutique__warn">
                            <i class="fas fa-circle-exclamation" style="margin-right:6px;"></i>
                            Aucun identifiant de boutique n&apos;est associ&eacute; &agrave; votre compte. D&eacute;finissez le nom et l&apos;URL
                            de votre boutique dans <a href="profil.php">votre profil administrateur</a>, puis reconnectez-vous si besoin.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            require_once __DIR__ . '/../includes/senegal_regions.php';
            $prm_boutique_region = (string) ($vbl_admin['boutique_region'] ?? '');
            ?>
            <section class="vd-card prm-identity-form" aria-labelledby="prmIdentityTitle">
                <div class="vd-card__head">
                    <h2 class="vd-card__title" id="prmIdentityTitle">Nom et région</h2>
                    <p class="vd-card__sub">Affichés sur votre vitrine et dans la liste des boutiques.</p>
                </div>
                <form method="POST" action="profil.php" class="prm-identity-form__body">
                    <input type="hidden" name="retour" value="parametres">
                    <label class="prm-field">
                        <span>Nom de la boutique</span>
                        <input type="text" name="boutique_nom" required maxlength="255" autocomplete="organization"
                            value="<?php echo htmlspecialchars((string) ($vbl_admin['boutique_nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <?php if (admin_has_boutique_region_column()): ?>
                    <label class="prm-field">
                        <span>Région</span>
                        <select name="boutique_region" required>
                            <?php echo senegal_regions_options_html($prm_boutique_region, true, 'Sélectionnez une région'); ?>
                        </select>
                    </label>
                    <?php endif; ?>
                    <div class="prm-identity-form__actions">
                        <button type="submit" name="modifier_boutique" class="vd-btn vd-btn--primary">Enregistrer</button>
                    </div>
                </form>
            </section>

            <?php require __DIR__ . '/includes/partials/vendeur_boutique_localisation_ui.php'; ?>
        <?php endif; ?>

        <!-- ===== MODULES / RACCOURCIS ===== -->
        <?php if ($__param_role === 'vendeur'): ?>

            <p class="vd-section-label">Personnaliser</p>
            <nav class="vd-list" aria-label="Personnaliser ma boutique">
                <a href="parametres-boutique-vendeur.php" class="vd-list__row">
                    <span class="vd-list__icon"><i class="fas fa-palette" aria-hidden="true"></i></span>
                    <span class="vd-list__body">
                        <span class="vd-list__title">Apparence de la vitrine</span>
                        <span class="vd-list__sub">Logo et couleurs de votre boutique</span>
                    </span>
                    <i class="fas fa-chevron-right vd-list__chev" aria-hidden="true"></i>
                </a>
                <a href="slider/index.php" class="vd-list__row">
                    <span class="vd-list__icon"><i class="fas fa-images" aria-hidden="true"></i></span>
                    <span class="vd-list__body">
                        <span class="vd-list__title">Images d&apos;affiche</span>
                        <span class="vd-list__sub">Bannières affichées en haut de votre vitrine</span>
                    </span>
                    <i class="fas fa-chevron-right vd-list__chev" aria-hidden="true"></i>
                </a>
                <a href="abonnes/index.php" class="vd-list__row">
                    <span class="vd-list__icon"><i class="fas fa-user-check" aria-hidden="true"></i></span>
                    <span class="vd-list__body">
                        <span class="vd-list__title">Abonnés</span>
                        <span class="vd-list__sub">Clients qui suivent votre boutique</span>
                    </span>
                    <i class="fas fa-chevron-right vd-list__chev" aria-hidden="true"></i>
                </a>
                <?php if ($__param_show_comptes): ?>
                <a href="comptes/index.php" class="vd-list__row">
                    <span class="vd-list__icon"><i class="fas fa-user-shield" aria-hidden="true"></i></span>
                    <span class="vd-list__body">
                        <span class="vd-list__title">Comptes d&apos;acc&egrave;s</span>
                        <span class="vd-list__sub">Collaborateurs et permissions</span>
                    </span>
                    <i class="fas fa-chevron-right vd-list__chev" aria-hidden="true"></i>
                </a>
                <?php endif; ?>
            </nav>

        <?php elseif ($__param_show_site_modules): ?>

            <div class="prm-section-head">
                <h2 class="prm-section-head__title">Configuration</h2>
            </div>

            <nav class="prm-nav-list" aria-label="Modules &agrave; configurer">

                <?php if ($__param_role !== 'vendeur'): ?>
                    <a href="parametres/section4.php" class="prm-nav-row">
                        <span class="prm-nav-row__icon"><i class="fas fa-house" aria-hidden="true"></i></span>
                        <span class="prm-nav-row__body">
                            <span class="prm-nav-row__title">Banni&egrave;re d&apos;accueil</span>
                            <span class="prm-nav-row__sub">Titre, accroche et image de fond</span>
                        </span>
                        <span class="prm-nav-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                    </a>

                    <a href="parametres/trending.php" class="prm-nav-row">
                        <span class="prm-nav-row__icon"><i class="fas fa-star" aria-hidden="true"></i></span>
                        <span class="prm-nav-row__body">
                            <span class="prm-nav-row__title">Section mise en avant</span>
                            <span class="prm-nav-row__sub">Titre promotionnel et visuel</span>
                        </span>
                        <span class="prm-nav-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                    </a>
                <?php endif; ?>

                <a href="slider/index.php" class="prm-nav-row">
                    <span class="prm-nav-row__icon"><i class="fas fa-images" aria-hidden="true"></i></span>
                    <span class="prm-nav-row__body">
                        <span class="prm-nav-row__title">Images d&apos;affiche pub</span>
                        <span class="prm-nav-row__sub">Slider affich&eacute; en haut de la boutique</span>
                    </span>
                    <span class="prm-nav-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </a>

                <?php if ($__param_role !== 'vendeur'): ?>
                    <a href="parametres/videos.php" class="prm-nav-row">
                        <span class="prm-nav-row__icon"><i class="fas fa-video" aria-hidden="true"></i></span>
                        <span class="prm-nav-row__body">
                            <span class="prm-nav-row__title">Section vid&eacute;os</span>
                            <span class="prm-nav-row__sub">Carrousel de vid&eacute;os</span>
                        </span>
                        <span class="prm-nav-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                    </a>

                    <a href="parametres/logos.php" class="prm-nav-row">
                        <span class="prm-nav-row__icon"><i class="fas fa-certificate" aria-hidden="true"></i></span>
                        <span class="prm-nav-row__body">
                            <span class="prm-nav-row__title">Logos partenaires</span>
                            <span class="prm-nav-row__sub">Carrousel de logos</span>
                        </span>
                        <span class="prm-nav-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                    </a>
                <?php endif; ?>

                <?php if ($__param_show_comptes): ?>
                    <a href="comptes/index.php" class="prm-nav-row">
                        <span class="prm-nav-row__icon"><i class="fas fa-user-shield" aria-hidden="true"></i></span>
                        <span class="prm-nav-row__body">
                            <span class="prm-nav-row__title">Comptes d&apos;acc&egrave;s</span>
                            <span class="prm-nav-row__sub">Administrateurs et permissions</span>
                        </span>
                        <span class="prm-nav-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                    </a>
                <?php endif; ?>

            </nav>

        <?php else: ?>

            <div class="prm-section-head">
                <h2 class="prm-section-head__title">Raccourcis</h2>
            </div>

            <nav class="prm-nav-list" aria-label="Raccourcis">
                <a href="profil.php" class="prm-nav-row">
                    <span class="prm-nav-row__icon"><i class="fas fa-user-pen" aria-hidden="true"></i></span>
                    <span class="prm-nav-row__body">
                        <span class="prm-nav-row__title">Mon profil</span>
                        <span class="prm-nav-row__sub">Modifier mes informations</span>
                    </span>
                    <span class="prm-nav-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </a>
                <?php if ($__param_show_comptes): ?>
                    <a href="comptes/index.php" class="prm-nav-row">
                        <span class="prm-nav-row__icon"><i class="fas fa-user-shield" aria-hidden="true"></i></span>
                        <span class="prm-nav-row__body">
                            <span class="prm-nav-row__title">Comptes d&apos;acc&egrave;s</span>
                            <span class="prm-nav-row__sub">G&eacute;rer les administrateurs</span>
                        </span>
                        <span class="prm-nav-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                    </a>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($__param_retour); ?>" class="prm-nav-row">
                    <span class="prm-nav-row__icon"><i class="fas fa-gauge-high" aria-hidden="true"></i></span>
                    <span class="prm-nav-row__body">
                        <span class="prm-nav-row__title">Tableau de bord</span>
                        <span class="prm-nav-row__sub">Retour &agrave; l&apos;accueil admin</span>
                    </span>
                    <span class="prm-nav-row__chev"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </a>
            </nav>

        <?php endif; ?>

    </div><!-- /.prm-page -->

    <?php require_once __DIR__ . '/includes/vendeur_share_boutique.php'; ?>
    <?php if (vendeur_share_boutique_is_available()): ?>
        <?php include dirname(__DIR__) . '/includes/partials/platform_share_modal.php'; ?>
        <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>" defer></script>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <?php if (vendeur_share_boutique_is_available()): ?>
    <?php
    vendeur_share_boutique_render_script([
        'open_button_ids' => ['vendeurShareBoutiqueUrl'],
        'external_copy_button_id' => 'vendeurCopyBoutiqueUrl',
        'feedback_id' => 'vendeurCopyBoutiqueFeedback',
    ]);
    ?>
    <?php endif; ?>

</body>
</html>
