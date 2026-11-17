<?php
/**
 * Certification vendeur — choix du niveau (Standard / VIP / Premium)
 */
require_once __DIR__ . '/../includes/require_admin_session.php';
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_admin.php';
require_once __DIR__ . '/../../models/model_vendeur_certification.php';

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

$error_message = '';
$success_message = '';
$niveaux = vendeur_certification_niveaux();
$niveau_actif = vendeur_certification_get_niveau_actif($admin_id);
$demande_en_cours = vendeur_certification_get_demande_en_cours($admin_id);
$derniere_demande = vendeur_certification_get_derniere_demande($admin_id);

$cert_tier_features = [
    'standard' => [
        ['ok' => true, 'label' => 'Confiance des acheteurs'],
        ['ok' => true, 'label' => 'Vendre sur la plateforme'],
        ['ok' => true, 'label' => 'Badge certification visible sur votre boutique'],
    ],
    'vip' => [
        ['ok' => true, 'label' => 'Confiance renforcée des acheteurs'],
        ['ok' => true, 'label' => 'Visibilité produits ×5'],
        ['ok' => true, 'label' => 'Participer aux ventes aux enchères'],
        ['ok' => true, 'label' => 'Vendre vos produits'],
    ],
    'premium' => [
        ['ok' => true, 'label' => 'Confiance maximale des acheteurs'],
        ['ok' => true, 'label' => 'Visibilité produits ×10'],
        ['ok' => true, 'label' => 'Publier des status 24 h'],
        ['ok' => true, 'label' => 'Ventes aux enchères'],
        ['ok' => true, 'label' => 'Vendre vos produits'],
    ],
];

if (!empty($_GET['envoye'])) {
    $success_message = 'Votre demande de certification a été transmise avec succès.';
    $demande_en_cours = vendeur_certification_get_demande_en_cours($admin_id);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certification boutique &mdash; Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/vendor-cert-ribbon.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-certification.css<?php echo asset_version_query(); ?>">
</head>
<body class="admin-cert-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="cert-page">
        <header class="vd-page-head">
            <div class="vd-page-head__text">
                <h1 class="vd-page-head__title">Certification</h1>
                <p class="vd-page-head__sub">Badge Standard, VIP ou Premium.</p>
            </div>
        </header>

        <?php if ($success_message !== ''): ?>
            <div class="cert-alert cert-alert--success" role="status"><i class="fas fa-circle-check"></i><span><?php echo htmlspecialchars($success_message); ?></span></div>
        <?php endif; ?>

        <?php if ($demande_en_cours): ?>
            <div class="cert-alert cert-alert--info" role="status">
                <i class="fas fa-info-circle"></i>
                <span>Votre demande <strong><?php echo htmlspecialchars(vendeur_certification_niveau_label($demande_en_cours['niveau'])); ?></strong>
                du <?php echo date('d/m/Y à H:i', strtotime((string) $demande_en_cours['date_creation'])); ?> est en cours d'examen.</span>
            </div>
        <?php elseif ($derniere_demande && ($derniere_demande['statut'] ?? '') === 'refusee'): ?>
            <div class="cert-alert cert-alert--error" role="alert">
                <i class="fas fa-times-circle"></i>
                <span>Dernière demande refusée<?php if (!empty($derniere_demande['motif_refus'])): ?> : <?php echo htmlspecialchars((string) $derniere_demande['motif_refus']); ?><?php endif; ?>.</span>
            </div>
        <?php endif; ?>

        <div class="cert-pricing" role="list" aria-label="Niveaux de certification">
            <?php foreach ($niveaux as $key => $meta):
                $is_active = ($niveau_actif === $key);
                $is_attained = ($niveau_actif !== null && vendeur_certification_niveau_ordre($key) <= vendeur_certification_niveau_ordre($niveau_actif));
                $is_pending = $demande_en_cours && (($demande_en_cours['niveau'] ?? '') === $key);
                $can_request = !$demande_en_cours && vendeur_certification_peut_demander($admin_id, $key)['ok'];
                $is_featured = ($key === 'vip');
                $features = $cert_tier_features[$key] ?? [];

                $col_classes = 'cert-pricing-col cert-pricing-col--' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                if ($is_featured) {
                    $col_classes .= ' cert-pricing-col--featured';
                }
                if ($is_active) {
                    $col_classes .= ' is-active';
                } elseif ($is_pending) {
                    $col_classes .= ' is-pending';
                }
            ?>
            <article class="<?php echo $col_classes; ?>" role="listitem">
                <div class="cert-pricing-col__cap"><?php echo htmlspecialchars(strtoupper($meta['label']), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php $cert_badge_src = vendeur_certification_badge_image_src($key); ?>
                <div class="cert-pricing-col__body"<?php if ($cert_badge_src !== ''): ?> style="--cert-badge-watermark: url('<?php echo htmlspecialchars($cert_badge_src, ENT_QUOTES, 'UTF-8'); ?>');"<?php endif; ?>>
                    <div class="cert-pricing-col__badge-hero">
                        <?php if ($cert_badge_src !== ''): ?>
                        <img src="<?php echo htmlspecialchars($cert_badge_src, ENT_QUOTES, 'UTF-8'); ?>"
                            alt="Certification <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>"
                            class="cert-pricing-col__badge-img"
                            loading="lazy"
                            decoding="async"
                            draggable="false">
                        <?php endif; ?>
                        <span class="cert-pricing-col__ribbon">Gratuit</span>
                    </div>

                    <p class="cert-pricing-col__lead"><?php echo htmlspecialchars($meta['desc'], ENT_QUOTES, 'UTF-8'); ?></p>

                    <ul class="cert-pricing-col__features">
                        <?php foreach ($features as $feat): ?>
                        <li class="<?php echo !empty($feat['ok']) ? 'is-included' : 'is-excluded'; ?>">
                            <span class="cert-pricing-col__feat-icon" aria-hidden="true">
                                <?php if (!empty($feat['ok'])): ?>
                                <i class="fas fa-check"></i>
                                <?php else: ?>
                                <i class="fas fa-xmark"></i>
                                <?php endif; ?>
                            </span>
                            <span class="cert-pricing-col__feat-label"><?php echo htmlspecialchars($feat['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="cert-pricing-col__footer">
                        <?php if ($is_active): ?>
                        <div class="cert-pricing-col__status cert-pricing-col__status--active">
                            <i class="fas fa-circle-check" aria-hidden="true"></i>
                            <span>Certification active</span>
                        </div>
                        <?php elseif ($is_pending): ?>
                        <a href="certification-suivi.php?id=<?php echo (int) ($demande_en_cours['id'] ?? 0); ?>" class="cert-pricing-col__go cert-pricing-col__go--pending" aria-label="Suivre la demande <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="cert-pricing-col__go-inner"><i class="fas fa-hourglass-half"></i></span>
                        </a>
                        <p class="cert-pricing-col__foot-hint">Demande en cours</p>
                        <?php elseif (!$demande_en_cours && $derniere_demande && ($derniere_demande['statut'] ?? '') === 'refusee' && ($derniere_demande['niveau'] ?? '') === $key): ?>
                        <a href="certification-demande.php?niveau=<?php echo urlencode($key); ?>" class="cert-pricing-col__go" aria-label="Demander la certification <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="cert-pricing-col__go-inner">GO</span>
                        </a>
                        <p class="cert-pricing-col__foot-hint">Nouvelle demande</p>
                        <?php elseif ($can_request): ?>
                        <a href="certification-demande.php?niveau=<?php echo urlencode($key); ?>" class="cert-pricing-col__go" aria-label="Demander la certification <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="cert-pricing-col__go-inner">GO</span>
                        </a>
                        <p class="cert-pricing-col__foot-hint">Demander ce niveau</p>
                        <?php elseif ($is_attained): ?>
                        <div class="cert-pricing-col__status cert-pricing-col__status--done">
                            <i class="fas fa-check" aria-hidden="true"></i>
                            <span>Niveau obtenu</span>
                        </div>
                        <?php else: ?>
                        <span class="cert-pricing-col__go cert-pricing-col__go--disabled" aria-disabled="true" aria-label="Certification <?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?> indisponible">
                            <span class="cert-pricing-col__go-inner"><i class="fas fa-lock"></i></span>
                        </span>
                        <p class="cert-pricing-col__foot-hint">Indisponible</p>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
