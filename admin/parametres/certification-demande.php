<?php
/**
 * Demande de certification vendeur — formulaire par niveau
 */
require_once __DIR__ . '/../includes/require_admin_session.php';
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_admin.php';
require_once __DIR__ . '/../../models/model_vendeur_certification.php';
require_once __DIR__ . '/../../includes/senegal_regions.php';

$role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? '');
if ($role !== 'vendeur') {
    header('Location: ../parametres.php');
    exit;
}

$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
$admin = get_admin_by_id($admin_id);
if (!$admin || ($admin['role'] ?? '') !== 'vendeur') {
    header('Location: ../parametres.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$niveaux = vendeur_certification_niveaux();
$niveau = trim((string) ($_GET['niveau'] ?? $_POST['niveau'] ?? ''));
if (!isset($niveaux[$niveau])) {
    header('Location: certification.php');
    exit;
}

$niveau_meta = $niveaux[$niveau];
$niveau_actif = vendeur_certification_get_niveau_actif($admin_id);
$demande_en_cours = vendeur_certification_get_demande_en_cours($admin_id);

$check_access = vendeur_certification_peut_demander($admin_id, $niveau);
if (!$check_access['ok'] && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: certification.php');
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['certification_submit'])) {
    $tok = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $error_message = 'Session expirée. Rechargez la page.';
    } elseif ($demande_en_cours) {
        $error_message = 'Une demande est déjà en cours d\'examen.';
    } else {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $prenom = trim((string) ($_POST['prenom'] ?? ''));
        $email = trim((string) ($admin['email'] ?? ''));
        $telephone = trim((string) ($admin['telephone'] ?? ''));
        $boutique_nom = trim((string) ($admin['boutique_nom'] ?? ''));
        $boutique_region = '';
        if (admin_has_boutique_region_column()) {
            $region_profil = trim((string) ($admin['boutique_region'] ?? ''));
            if ($region_profil !== '' && senegal_region_is_valid($region_profil)) {
                $boutique_region = $region_profil;
            } else {
                $boutique_region = trim((string) ($_POST['boutique_region'] ?? ''));
            }
        }
        $adresse_exacte = trim((string) ($_POST['adresse_exacte'] ?? ''));
        $description_activite = trim((string) ($_POST['description_activite'] ?? ''));
        $numero_registre = trim((string) ($_POST['numero_registre'] ?? ''));
        $message_demande = trim((string) ($_POST['message_demande'] ?? ''));
        $accepte = !empty($_POST['accepte_conditions']);

        $errors = [];
        if (!$check_access['ok']) {
            $errors[] = $check_access['message'];
        }
        if ($nom === '' || mb_strlen($nom) < 2) {
            $errors[] = 'Le nom est obligatoire.';
        }
        if ($prenom === '' || mb_strlen($prenom) < 2) {
            $errors[] = 'Le prénom est obligatoire.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email du profil invalide. Mettez à jour vos paramètres boutique.';
        }
        if ($telephone === '' || mb_strlen(preg_replace('/\D/', '', $telephone)) < 9) {
            $errors[] = 'Téléphone manquant sur votre profil. Mettez à jour vos paramètres boutique.';
        }
        if ($boutique_nom === '' || mb_strlen($boutique_nom) < 2) {
            $errors[] = 'Nom de boutique manquant sur votre profil. Mettez à jour vos paramètres boutique.';
        }
        if (admin_has_boutique_region_column()) {
            if ($boutique_region === '' || !senegal_region_is_valid($boutique_region)) {
                $errors[] = 'Sélectionnez une région valide.';
            }
        }
        if (!$accepte) {
            $errors[] = 'Vous devez certifier l\'exactitude des informations.';
        }

        $photo1 = '';
        $photo2 = '';
        $photo3 = '';
        $photo4 = '';
        $photo_doc = '';
        $photo_piece = '';
        $photo_piece_verso = '';

        $cert_file_upload_ok = function ($field) {
            return isset($_FILES[$field]) && (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        };

        if ($niveau === 'vip' || $niveau === 'premium') {
            if ($adresse_exacte === '' || mb_strlen($adresse_exacte) < 10) {
                $errors[] = 'Adresse exacte du local requise (10 caractères minimum).';
            }
            if (!isset($_FILES['photo_local_1']) || (int) ($_FILES['photo_local_1']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'Photo de la façade du local requise.';
            }
            if (!isset($_FILES['photo_local_2']) || (int) ($_FILES['photo_local_2']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'Photo intérieure du local requise.';
            }
        }

        if ($niveau === 'premium') {
            if ($description_activite === '' || mb_strlen($description_activite) < 30) {
                $errors[] = 'Description de l\'activité requise (30 caractères minimum).';
            }
            if ($numero_registre === '' || mb_strlen($numero_registre) < 4) {
                $errors[] = 'Numéro NINEA / registre de commerce requis.';
            }
            if (!isset($_FILES['photo_local_3']) || (int) ($_FILES['photo_local_3']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'Troisième photo du local requise pour Premium.';
            }
            if (!isset($_FILES['photo_local_4']) || (int) ($_FILES['photo_local_4']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'Quatrième photo du local requise pour Premium.';
            }
            if (!isset($_FILES['photo_document']) || (int) ($_FILES['photo_document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'Justificatif officiel requis pour Premium.';
            }
        }

        if (in_array($niveau, ['standard', 'vip', 'premium'], true)) {
            if (!$cert_file_upload_ok('photo_piece_identite')) {
                $errors[] = 'Photo recto de la pièce d\'identité requise.';
            }
            if (!vendeur_certification_piece_identite_verso_column_exists()) {
                $errors[] = 'Enregistrement du verso indisponible pour le moment. Rechargez la page et réessayez.';
            } elseif (!$cert_file_upload_ok('photo_piece_identite_verso')) {
                $errors[] = 'Photo verso de la pièce d\'identité requise.';
            }
        }

        if (empty($errors)) {
            if ($niveau === 'vip' || $niveau === 'premium') {
                $up1 = vendeur_certification_upload_photo($_FILES['photo_local_1'], $admin_id, 'facade');
                if (!$up1['ok']) {
                    $errors[] = $up1['message'];
                } else {
                    $photo1 = $up1['path'];
                }
                $up2 = vendeur_certification_upload_photo($_FILES['photo_local_2'], $admin_id, 'interieur');
                if (!$up2['ok']) {
                    $errors[] = $up2['message'];
                } else {
                    $photo2 = $up2['path'];
                }
            }
            if ($niveau === 'premium' && empty($errors)) {
                $up3 = vendeur_certification_upload_photo($_FILES['photo_local_3'], $admin_id, 'local3');
                if (!$up3['ok']) {
                    $errors[] = $up3['message'];
                } else {
                    $photo3 = $up3['path'];
                }
                $up4 = vendeur_certification_upload_photo($_FILES['photo_local_4'], $admin_id, 'local4');
                if (!$up4['ok']) {
                    $errors[] = $up4['message'];
                } else {
                    $photo4 = $up4['path'];
                }
                $upd = vendeur_certification_upload_photo($_FILES['photo_document'], $admin_id, 'doc');
                if (!$upd['ok']) {
                    $errors[] = $upd['message'];
                } else {
                    $photo_doc = $upd['path'];
                }
            }
            if (in_array($niveau, ['standard', 'vip', 'premium'], true) && empty($errors)) {
                $uppi = vendeur_certification_upload_photo($_FILES['photo_piece_identite'], $admin_id, 'piece_recto');
                if (!$uppi['ok']) {
                    $errors[] = $uppi['message'];
                } else {
                    $photo_piece = $uppi['path'];
                }
                $uppv = vendeur_certification_upload_photo($_FILES['photo_piece_identite_verso'], $admin_id, 'piece_verso');
                if (!$uppv['ok']) {
                    $errors[] = $uppv['message'];
                } else {
                    $photo_piece_verso = $uppv['path'];
                }
            }
        }

        if (empty($errors)) {
            if (admin_has_boutique_region_column()) {
                $region_profil = trim((string) ($admin['boutique_region'] ?? ''));
                if (($region_profil === '' || !senegal_region_is_valid($region_profil)) && $boutique_region !== '') {
                    update_vendeur_boutique_profil($admin_id, $boutique_nom, $boutique_region);
                }
            }
            $result = vendeur_certification_creer_demande($admin_id, [
                'niveau' => $niveau,
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone,
                'boutique_nom' => $boutique_nom,
                'boutique_region' => $boutique_region,
                'adresse_exacte' => $adresse_exacte,
                'description_activite' => $description_activite,
                'numero_registre' => $numero_registre,
                'photo_local_1' => $photo1,
                'photo_local_2' => $photo2,
                'photo_local_3' => $photo3,
                'photo_local_4' => $photo4,
                'photo_document' => $photo_doc,
                'photo_piece_identite' => $photo_piece,
                'photo_piece_identite_verso' => $photo_piece_verso,
                'message_demande' => $message_demande,
            ]);
            if (!empty($result['ok'])) {
                header('Location: certification.php?envoye=1');
                exit;
            }
            $error_message = $result['message'] ?? 'Erreur inconnue.';
        } else {
            $error_message = implode(' ', $errors);
        }
    }
}

$prefill_nom = trim((string) ($admin['nom'] ?? ''));
$prefill_prenom = trim((string) ($admin['prenom'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['nom'])) {
        $prefill_nom = trim((string) $_POST['nom']);
    }
    if (isset($_POST['prenom'])) {
        $prefill_prenom = trim((string) $_POST['prenom']);
    }
}
$prefill_email = trim((string) ($admin['email'] ?? ''));
$prefill_tel = trim((string) ($admin['telephone'] ?? ''));
$prefill_boutique = trim((string) ($admin['boutique_nom'] ?? ''));
$prefill_region = trim((string) ($admin['boutique_region'] ?? ''));
$cert_region_definie = admin_has_boutique_region_column()
    && $prefill_region !== ''
    && senegal_region_is_valid($prefill_region);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['boutique_region']) && !$cert_region_definie) {
    $prefill_region = trim((string) $_POST['boutique_region']);
}
$prefill_adresse = trim((string) ($admin['boutique_adresse'] ?? ''));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande <?php echo htmlspecialchars($niveau_meta['label']); ?> &mdash; Certification</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/vendor-cert-ribbon.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-certification.css<?php echo asset_version_query(); ?>">
</head>
<body class="admin-cert-page admin-cert-demande">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="cert-page cert-page--demande">
        <header class="cert-page-head">
            <a href="certification.php" class="cert-page-head__back" aria-label="Retour au choix du niveau">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
            </a>
            <div class="cert-page-head__text">
                <h1 class="cert-page-head__title">Demande de certification</h1>
            </div>
        </header>

        <header class="cert-hero cert-hero--demande cert-hero--<?php echo htmlspecialchars($niveau, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="cert-hero__top">
                <div class="cert-hero__copy">
                    <p class="cert-hero__eyebrow"><i class="fas fa-certificate" aria-hidden="true"></i> Niveau visé</p>
                    <h2 class="cert-hero__title"><?php echo htmlspecialchars($niveau_meta['label']); ?></h2>
                    <p class="cert-hero__lead"><?php echo htmlspecialchars($niveau_meta['desc']); ?></p>
                </div>
                <div class="cert-hero__badge-wrap" aria-hidden="true">
                    <?php
                    $cert_niveau = $niveau;
                    $cert_size = 'md';
                    require __DIR__ . '/../../includes/partials/vendeur_certification_badge.php';
                    ?>
                </div>
            </div>
        </header>

        <?php if ($error_message !== ''): ?>
            <div class="cert-alert cert-alert--error" role="alert"><i class="fas fa-circle-exclamation"></i><span><?php echo htmlspecialchars($error_message); ?></span></div>
        <?php endif; ?>

        <form method="post" action="" enctype="multipart/form-data" class="cert-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="niveau" value="<?php echo htmlspecialchars($niveau, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="cert-panel">
                <div class="cert-panel__head">
                    <h2>Informations vendeur</h2>
                </div>
                <div class="cert-panel__body">
                    <p class="cert-section-title">Identité</p>
                    <div class="cert-grid-2">
                        <div class="cert-field">
                            <label for="cert_prenom">Prénom <span class="req">*</span></label>
                            <input type="text" id="cert_prenom" name="prenom" value="<?php echo htmlspecialchars($prefill_prenom); ?>" required maxlength="120">
                        </div>
                        <div class="cert-field">
                            <label for="cert_nom">Nom <span class="req">*</span></label>
                            <input type="text" id="cert_nom" name="nom" value="<?php echo htmlspecialchars($prefill_nom); ?>" required maxlength="120">
                        </div>
                    </div>

                    <p class="cert-section-title">Boutique</p>
                    <div class="cert-profile-recap" aria-label="Informations boutique enregistrées">
                        <p class="cert-profile-recap__title"><i class="fas fa-store" aria-hidden="true"></i> Profil boutique</p>
                        <div class="cert-profile-recap__grid">
                            <div class="cert-profile-recap__item">
                                <span class="cert-profile-recap__icon" aria-hidden="true"><i class="fas fa-bag-shopping"></i></span>
                                <div class="cert-profile-recap__content">
                                    <span class="cert-profile-recap__label">Nom commercial</span>
                                    <strong><?php echo htmlspecialchars($prefill_boutique ?: '—'); ?></strong>
                                </div>
                            </div>
                            <div class="cert-profile-recap__item">
                                <span class="cert-profile-recap__icon" aria-hidden="true"><i class="fas fa-phone"></i></span>
                                <div class="cert-profile-recap__content">
                                    <span class="cert-profile-recap__label">Téléphone</span>
                                    <strong dir="ltr"><?php echo htmlspecialchars($prefill_tel ?: '—'); ?></strong>
                                </div>
                            </div>
                            <div class="cert-profile-recap__item">
                                <span class="cert-profile-recap__icon" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                                <div class="cert-profile-recap__content">
                                    <span class="cert-profile-recap__label">Email</span>
                                    <strong class="cert-profile-recap__email"><?php echo htmlspecialchars($prefill_email !== '' ? $prefill_email : 'Non renseigné'); ?></strong>
                                </div>
                            </div>
                            <?php if ($cert_region_definie): ?>
                            <div class="cert-profile-recap__item">
                                <span class="cert-profile-recap__icon" aria-hidden="true"><i class="fas fa-location-dot"></i></span>
                                <div class="cert-profile-recap__content">
                                    <span class="cert-profile-recap__label">Région</span>
                                    <strong><?php echo htmlspecialchars(senegal_region_label($prefill_region)); ?></strong>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (admin_has_boutique_region_column()): ?>
                        <?php if ($cert_region_definie): ?>
                        <input type="hidden" name="boutique_region" value="<?php echo htmlspecialchars($prefill_region, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php else: ?>
                        <div class="cert-field">
                            <label for="cert_region">Région <span class="req">*</span></label>
                            <select id="cert_region" name="boutique_region" required>
                                <?php echo senegal_regions_options_html($prefill_region, true, 'Choisir une région'); ?>
                            </select>
                            <small>Votre région n'est pas encore renseignée sur votre profil — sélectionnez-la pour cette demande.</small>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($niveau === 'standard'): ?>
                    <div class="cert-block">
                        <p class="cert-section-title">Standard — justificatif</p>
                        <?php require __DIR__ . '/inc_cert_piece_identite_uploads.php'; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($niveau === 'vip' || $niveau === 'premium'): ?>
                    <div class="cert-block">
                        <p class="cert-section-title">Local physique — VIP &amp; Premium</p>
                        <div class="cert-field">
                            <label for="cert_adresse">Adresse exacte du local <span class="req">*</span></label>
                            <textarea id="cert_adresse" name="adresse_exacte" maxlength="500" placeholder="Rue, quartier, ville, point de repère…"><?php echo htmlspecialchars($prefill_adresse); ?></textarea>
                            <small>Indiquez l'adresse complète où les clients peuvent vous trouver.</small>
                        </div>
                        <div class="cert-upload-grid">
                            <div class="cert-upload">
                                <label>
                                    <i class="fas fa-camera"></i>
                                    Façade du local <span class="req">*</span>
                                    <input type="file" name="photo_local_1" accept="image/jpeg,image/png,image/webp" required>
                                    <span class="cert-upload__name">JPEG, PNG ou WebP</span>
                                </label>
                            </div>
                            <div class="cert-upload">
                                <label>
                                    <i class="fas fa-image"></i>
                                    Intérieur du local <span class="req">*</span>
                                    <input type="file" name="photo_local_2" accept="image/jpeg,image/png,image/webp" required>
                                    <span class="cert-upload__name">JPEG, PNG ou WebP</span>
                                </label>
                            </div>
                            <?php if ($niveau === 'premium'): ?>
                            <div class="cert-upload">
                                <label>
                                    <i class="fas fa-images"></i>
                                    Vue complémentaire (1) <span class="req">*</span>
                                    <input type="file" name="photo_local_3" accept="image/jpeg,image/png,image/webp" required>
                                    <span class="cert-upload__name">Autre angle du local</span>
                                </label>
                            </div>
                            <div class="cert-upload">
                                <label>
                                    <i class="fas fa-images"></i>
                                    Vue complémentaire (2) <span class="req">*</span>
                                    <input type="file" name="photo_local_4" accept="image/jpeg,image/png,image/webp" required>
                                    <span class="cert-upload__name">Autre angle du local</span>
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($niveau === 'vip'): ?>
                    <div class="cert-block">
                        <p class="cert-section-title">VIP — justificatif</p>
                        <?php require __DIR__ . '/inc_cert_piece_identite_uploads.php'; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($niveau === 'premium'): ?>
                    <div class="cert-block">
                        <p class="cert-section-title">Premium — justificatifs</p>
                        <div class="cert-field">
                            <label for="cert_desc">Description de l'activité <span class="req">*</span></label>
                            <textarea id="cert_desc" name="description_activite" maxlength="800" placeholder="Présentez votre activité, vos produits, votre expérience…"></textarea>
                        </div>
                        <div class="cert-field">
                            <label for="cert_rc">N° NINEA / Registre de commerce <span class="req">*</span></label>
                            <input type="text" id="cert_rc" name="numero_registre" maxlength="80" placeholder="Ex. NINEA ou RC">
                        </div>
                        <div class="cert-upload">
                            <label>
                                <i class="fas fa-file-image"></i>
                                Justificatif officiel <span class="req">*</span>
                                <input type="file" name="photo_document" accept="image/jpeg,image/png,image/webp" required>
                                <span class="cert-upload__name">NINEA, attestation ou document officiel</span>
                            </label>
                        </div>
                        <?php require __DIR__ . '/inc_cert_piece_identite_uploads.php'; ?>
                    </div>
                    <?php endif; ?>

                    <div class="cert-field">
                        <label for="cert_msg">Message pour l'équipe (optionnel)</label>
                        <textarea id="cert_msg" name="message_demande" maxlength="600" placeholder="Informations complémentaires…"></textarea>
                    </div>

                    <label class="cert-check">
                        <input type="checkbox" name="accepte_conditions" value="1" required>
                        <span>Je certifie que les informations fournies sont exactes et j'accepte la vérification par la plateforme.</span>
                    </label>

                    <div class="cert-actions">
                        <button type="submit" name="certification_submit" value="1" class="cert-btn cert-btn--primary">
                            <i class="fas fa-paper-plane"></i> Envoyer
                        </button>
                        <a href="certification.php" class="cert-btn cert-btn--ghost">Annuler</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="/js/cert-upload-preview.js<?php echo asset_version_query(); ?>"></script>
</body>
</html>
