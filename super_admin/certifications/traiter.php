<?php
/**
 * Traitement d'une demande de certification — Super Admin
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_vendeur_certification.php';
require_once dirname(__DIR__, 2) . '/includes/senegal_regions.php';

$demande_id = (int) ($_GET['id'] ?? $_POST['demande_id'] ?? 0);
$vue_lecture = !empty($_GET['vue']) && $_GET['vue'] === 'lecture';
$show_reject_form = !empty($_GET['rejeter']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refusee');

$d = vendeur_certification_get_demande_by_id($demande_id);
if (!$d) {
    header('Location: index.php?tab=en_cours');
    exit;
}

$st_dem = (string) ($d['statut'] ?? '');
$peut_traiter = ($st_dem === 'en_attente');
if ($vue_lecture) {
    $peut_traiter = false;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $peut_traiter) {
    $action = trim((string) ($_POST['action'] ?? ''));
    $motif = trim((string) ($_POST['motif_refus'] ?? ''));

    if ($action === 'approuvee') {
        if (vendeur_certification_traiter_demande($demande_id, 'approuvee', '', (int) ($_SESSION['super_admin_id'] ?? 0))) {
            $_SESSION['super_admin_flash_ok'] = 'Certification validée avec succès.';
            header('Location: index.php?tab=validees');
            exit;
        }
        $error = 'Impossible de valider cette demande.';
    } elseif ($action === 'refusee') {
        if ($motif === '') {
            $error = 'Indiquez le motif du refus.';
            $show_reject_form = true;
        } elseif (vendeur_certification_traiter_demande($demande_id, 'refusee', $motif, (int) ($_SESSION['super_admin_id'] ?? 0))) {
            $_SESSION['super_admin_flash_ok'] = 'Demande rejetée — le vendeur sera notifié.';
            header('Location: index.php?tab=refusees');
            exit;
        } else {
            $error = 'Impossible de rejeter cette demande.';
        }
    } else {
        $error = 'Action non reconnue.';
    }
}

$tab_retour = match ($st_dem) {
    'approuvee' => 'validees',
    'refusee' => 'refusees',
    default => 'en_cours',
};

$date_demande = !empty($d['date_creation']) ? date('d/m/Y · H:i', strtotime((string) $d['date_creation'])) : '—';
$date_traite = !empty($d['date_traitement']) ? date('d/m/Y · H:i', strtotime((string) $d['date_traitement'])) : '—';
$nv_label = vendeur_certification_niveau_label((string) ($d['niveau'] ?? ''));
$st_label = vendeur_certification_statut_label($st_dem);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars((string) ($d['boutique_nom'] ?? 'Demande'), ENT_QUOTES, 'UTF-8'); ?> — Certification</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/vendor-cert-ribbon.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-certification.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-parametres.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-certifications.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-cert-page sa-cert-page--detail">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-cert-shell sa-cert-shell--detail">
        <nav class="sa-cert-detail-nav" aria-label="Navigation">
            <a href="index.php?tab=<?php echo urlencode($tab_retour); ?>" class="sa-cert-detail-nav__back">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                Retour aux demandes
            </a>
        </nav>

        <header class="sa-param-hero sa-cert-detail-hero sa-cert-detail-hero--<?php echo htmlspecialchars($st_dem, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="sa-cert-traiter-title">
            <div class="sa-param-hero__inner">
                <div class="sa-param-hero__intro">
                    <h1 class="sa-param-hero__title" id="sa-cert-traiter-title"><?php echo htmlspecialchars((string) ($d['boutique_nom'] ?? 'Boutique'), ENT_QUOTES, 'UTF-8'); ?></h1>
                    <div class="sa-cert-detail-hero__tags">
                        <?php
                        $cert_niveau = (string) ($d['niveau'] ?? 'standard');
                        $cert_size = 'sm';
                        require dirname(__DIR__, 2) . '/includes/partials/vendeur_certification_badge.php';
                        ?>
                        <span class="sa-cert-statut sa-cert-statut--hero sa-cert-statut--<?php echo htmlspecialchars($st_dem, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($st_label, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                </div>
                <div class="sa-param-hero__kpis" role="group" aria-label="Dates de la demande">
                    <div class="sa-param-hero-kpi">
                        <span class="sa-param-hero-kpi__label">Niveau</span>
                        <span class="sa-param-hero-kpi__value sa-param-hero-kpi__value--sm"><?php echo htmlspecialchars($nv_label, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="sa-param-hero-kpi">
                        <span class="sa-param-hero-kpi__label">Demandée</span>
                        <span class="sa-param-hero-kpi__value sa-param-hero-kpi__value--sm"><?php echo htmlspecialchars($date_demande, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php if (!empty($d['date_traitement'])): ?>
                    <div class="sa-param-hero-kpi">
                        <span class="sa-param-hero-kpi__label">Traitée</span>
                        <span class="sa-param-hero-kpi__value sa-param-hero-kpi__value--sm"><?php echo htmlspecialchars($date_traite, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <?php if ($error !== ''): ?>
            <div class="sa-cert-alert sa-cert-alert--err" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <?php
        $sa_cert_show_actions = false;
        require __DIR__ . '/includes/demande_card.php';
        ?>

        <?php if ($peut_traiter && !$show_reject_form): ?>
            <section class="sa-users-panel sa-cert-detail-actions" aria-label="Décision">
                <div class="sa-cert-traiter-actions">
                    <form method="post" action="traiter.php?id=<?php echo $demande_id; ?>" class="sa-cert-traiter-actions__form">
                        <?php echo super_admin_csrf_field(); ?>
                        <input type="hidden" name="demande_id" value="<?php echo $demande_id; ?>">
                        <button type="submit" name="action" value="approuvee" class="sa-cert-btn sa-cert-btn--ok sa-cert-btn--lg">
                            <i class="fas fa-check" aria-hidden="true"></i> Valider la certification
                        </button>
                    </form>
                    <a href="traiter.php?id=<?php echo $demande_id; ?>&amp;rejeter=1" class="sa-cert-btn sa-cert-btn--no sa-cert-btn--lg">
                        <i class="fas fa-times" aria-hidden="true"></i> Rejeter la demande
                    </a>
                </div>
            </section>
        <?php elseif ($peut_traiter && $show_reject_form): ?>
            <section class="sa-users-panel sa-cert-detail-actions sa-cert-detail-actions--reject" aria-labelledby="sa-cert-reject-title">
                <div class="sa-users-panel__head">
                    <h2 id="sa-cert-reject-title"><i class="fas fa-circle-xmark" aria-hidden="true"></i> Motif du refus</h2>
                    <p class="sa-users-panel__meta">Ce message sera affiché au vendeur.</p>
                </div>
                <form method="post" action="traiter.php?id=<?php echo $demande_id; ?>" class="sa-cert-reject-form">
                    <?php echo super_admin_csrf_field(); ?>
                    <input type="hidden" name="demande_id" value="<?php echo $demande_id; ?>">
                    <input type="hidden" name="action" value="refusee">
                    <div class="sa-cert-detail-field">
                        <label for="motif_refus">Motif <span class="sa-cert-detail-field__req">*</span></label>
                        <textarea id="motif_refus" name="motif_refus" required maxlength="800" rows="5" placeholder="Détaillez les éléments manquants ou non conformes…"><?php echo htmlspecialchars((string) ($_POST['motif_refus'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="sa-cert-traiter-actions">
                        <button type="submit" class="sa-cert-btn sa-cert-btn--no sa-cert-btn--lg">
                            <i class="fas fa-paper-plane" aria-hidden="true"></i> Confirmer le refus
                        </button>
                        <a href="traiter.php?id=<?php echo $demande_id; ?>" class="sa-cert-btn sa-cert-btn--ghost sa-cert-btn--lg">Annuler</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>

    <div id="saCertLightbox" class="sa-cert-lightbox" hidden role="dialog" aria-modal="true" aria-labelledby="saCertLightboxTitle">
        <button type="button" class="sa-cert-lightbox__backdrop" data-sa-cert-lightbox-close tabindex="-1" aria-label="Fermer"></button>
        <div class="sa-cert-lightbox__panel">
            <button type="button" class="sa-cert-lightbox__close" data-sa-cert-lightbox-close aria-label="Fermer">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <p id="saCertLightboxTitle" class="sa-cert-lightbox__title"></p>
            <div class="sa-cert-lightbox__body">
                <img id="saCertPreviewImg" class="sa-cert-lightbox__img" alt="" hidden>
                <iframe id="saCertPreviewPdf" class="sa-cert-lightbox__pdf" title="Document PDF" hidden></iframe>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
    (function () {
        var root = document.getElementById('saCertDetailViewer');
        var lightbox = document.getElementById('saCertLightbox');
        if (!root || !lightbox) return;
        var previewImg = document.getElementById('saCertPreviewImg');
        var previewPdf = document.getElementById('saCertPreviewPdf');
        var previewTitle = document.getElementById('saCertLightboxTitle');
        var openers = root.querySelectorAll('[data-sa-cert-doc-open]');
        if (!previewImg || !previewTitle || !openers.length) return;

        function closeLightbox() {
            lightbox.hidden = true;
            document.body.classList.remove('sa-cert-lightbox-open');
            previewImg.hidden = true;
            previewImg.removeAttribute('src');
            if (previewPdf) {
                previewPdf.hidden = true;
                previewPdf.removeAttribute('src');
            }
        }

        function openLightbox(btn) {
            var url = btn.getAttribute('data-url');
            var label = btn.getAttribute('data-label') || '';
            var kind = btn.getAttribute('data-kind') || 'image';
            if (!url) return;
            previewTitle.textContent = label;
            if (kind === 'pdf' && previewPdf) {
                previewImg.hidden = true;
                previewImg.removeAttribute('src');
                previewPdf.hidden = false;
                previewPdf.src = url;
                previewPdf.title = label;
            } else {
                if (previewPdf) {
                    previewPdf.hidden = true;
                    previewPdf.removeAttribute('src');
                }
                previewImg.hidden = false;
                previewImg.src = url;
                previewImg.alt = label;
            }
            lightbox.hidden = false;
            document.body.classList.add('sa-cert-lightbox-open');
        }

        openers.forEach(function (btn) {
            btn.addEventListener('click', function () {
                openLightbox(btn);
            });
        });

        lightbox.querySelectorAll('[data-sa-cert-lightbox-close]').forEach(function (el) {
            el.addEventListener('click', closeLightbox);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !lightbox.hidden) {
                closeLightbox();
            }
        });
    })();
    </script>
</body>
</html>
