<?php
/**
 * Annonces plateforme — envoi et historique (super admin)
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_annonces.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_annonces.php';

$msg_ok = $_SESSION['super_admin_flash_ok'] ?? '';
$msg_err = $_SESSION['super_admin_flash_err'] ?? '';
unset($_SESSION['super_admin_flash_ok'], $_SESSION['super_admin_flash_err']);

$csrf = super_admin_csrf_token();
$form_open = !empty($_GET['envoyer']);
$send_result = ['success' => false, 'message' => ''];

$form_prefill = [
    'titre' => '',
    'message' => '',
    'audience' => 'client',
    'lien_url' => '',
];

$renvoyer_id = isset($_GET['renvoyer']) ? (int) $_GET['renvoyer'] : 0;
if ($renvoyer_id > 0) {
    $src = annonce_get_by_id($renvoyer_id);
    if ($src) {
        $form_prefill = [
            'titre' => (string) ($src['titre'] ?? ''),
            'message' => (string) ($src['message'] ?? ''),
            'audience' => (string) ($src['audience'] ?? 'client'),
            'lien_url' => (string) ($src['lien_url'] ?? ''),
        ];
        $form_open = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_annonce'])) {
    $send_result = process_super_admin_send_annonce();
    if (!empty($send_result['success'])) {
        $_SESSION['super_admin_flash_ok'] = $send_result['message'];
        header('Location: index.php');
        exit;
    }
    $form_open = true;
    $form_prefill = [
        'titre' => isset($_POST['titre']) ? trim((string) $_POST['titre']) : '',
        'message' => isset($_POST['message']) ? trim((string) $_POST['message']) : '',
        'audience' => isset($_POST['audience']) ? trim((string) $_POST['audience']) : 'client',
        'lien_url' => isset($_POST['lien_url']) ? trim((string) $_POST['lien_url']) : '',
    ];
}

$annonces = annonces_list_super_admin(150);
$total = count($annonces);
$audience_pref = $form_prefill['audience'] === 'vendeur' ? 'vendeur' : 'client';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annonces — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-parametres.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-annonces.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-annonces-page<?php echo $form_open ? ' sa-annonces-modal-open' : ''; ?>">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-annonces-shell">
        <header class="sa-param-hero sa-annonces-hero" aria-labelledby="sa-annonces-title">
            <div class="sa-param-hero__inner">
                <div class="sa-param-hero__intro">
                    <h1 class="sa-param-hero__title" id="sa-annonces-title">Annonces</h1>
                    <p class="sa-param-hero__sub">Notifications push aux clients et vendeurs · historique conservé</p>
                </div>
                <div class="sa-param-hero__kpis" role="group" aria-label="Indicateurs annonces">
                    <div class="sa-param-hero-kpi">
                        <span class="sa-param-hero-kpi__label">Envoyées</span>
                        <span class="sa-param-hero-kpi__value"><?php echo (int) $total; ?></span>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($msg_ok !== ''): ?>
            <div class="sa-annonces-alert sa-annonces-alert--ok" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($msg_ok, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($msg_err !== ''): ?>
            <div class="sa-annonces-alert sa-annonces-alert--err" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($msg_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <section class="sa-users-panel sa-annonces-panel" aria-labelledby="sa-annonces-list-title">
            <div class="sa-users-panel__head sa-annonces-panel__head">
                <div>
                    <h2 id="sa-annonces-list-title">
                        <i class="fas fa-clock-rotate-left" aria-hidden="true"></i>
                        Historique
                    </h2>
                    <p class="sa-users-panel__meta">Derniers envois et statistiques push</p>
                </div>
                <button type="button" class="sa-cat-btn sa-cat-btn--primary sa-annonces-panel__send" id="saAnnoncesOpenForm" aria-controls="saAnnoncesFormLayer" aria-expanded="<?php echo $form_open ? 'true' : 'false'; ?>">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                    Envoyer une annonce
                </button>
            </div>

            <?php if (empty($annonces)): ?>
                <div class="sa-users-empty sa-annonces-empty">
                    <i class="fas fa-bullhorn" aria-hidden="true"></i>
                    <p><strong>Aucune annonce pour l’instant.</strong></p>
                    <p>Composez un message et choisissez les destinataires.</p>
                </div>
            <?php else: ?>
                <ul class="sa-annonces-list">
                    <?php foreach ($annonces as $a):
                        $aud = (string) ($a['audience'] ?? 'client');
                        $aid = (int) ($a['id'] ?? 0);
                        $push_ok = (int) ($a['nb_push_envoyes'] ?? 0);
                        $push_target = (int) ($a['nb_destinataires_cibles'] ?? 0);
                        $date_fmt = !empty($a['date_envoi']) ? date('d/m/Y · H:i', strtotime((string) $a['date_envoi'])) : '—';
                    ?>
                    <li class="sa-annonces-row">
                        <div class="sa-annonces-row__grid">
                            <div class="sa-annonces-row__main">
                                <div class="sa-annonces-row__title-line">
                                    <h3 class="sa-annonces-row__title"><?php echo htmlspecialchars((string) ($a['titre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <span class="sa-annonces-audience sa-annonces-audience--<?php echo htmlspecialchars($aud, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-<?php echo $aud === 'vendeur' ? 'store' : 'users'; ?>" aria-hidden="true"></i>
                                        <?php echo htmlspecialchars(annonce_audience_label($aud), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <p class="sa-annonces-row__message"><?php echo htmlspecialchars((string) ($a['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="sa-annonces-row__meta">
                                    <time datetime="<?php echo !empty($a['date_envoi']) ? htmlspecialchars(date('c', strtotime((string) $a['date_envoi'])), ENT_QUOTES, 'UTF-8') : ''; ?>">
                                        <?php echo htmlspecialchars($date_fmt, ENT_QUOTES, 'UTF-8'); ?>
                                    </time>
                                    <span class="sa-annonces-row__push" title="Notifications push">
                                        <i class="fas fa-bell" aria-hidden="true"></i>
                                        <?php echo $push_ok; ?> / <?php echo $push_target; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="sa-annonces-row__actions">
                                <button type="button" class="sa-btn-action sa-btn-action--primary js-annonce-resend"
                                    data-titre="<?php echo htmlspecialchars((string) ($a['titre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-message="<?php echo htmlspecialchars((string) ($a['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-audience="<?php echo htmlspecialchars($aud, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-lien="<?php echo htmlspecialchars((string) ($a['lien_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-redo" aria-hidden="true"></i> Renvoyer
                                </button>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <div class="sa-cat-modal sa-cat-modal--genre sa-annonces-modal" id="saAnnoncesFormLayer" role="dialog" aria-modal="true" aria-labelledby="saAnnoncesFormTitle"<?php echo $form_open ? '' : ' hidden'; ?>>
        <div class="sa-cat-modal__backdrop" data-sa-annonces-close tabindex="-1" aria-hidden="true"></div>
        <div class="sa-cat-modal__panel">
            <div class="sa-cat-modal__header">
                <div class="sa-cat-modal__header-text">
                    <h2 id="saAnnoncesFormTitle">Envoyer une annonce</h2>
                    <p class="sa-cat-modal__subtitle">Message, destinataires et lien optionnel</p>
                </div>
                <button type="button" class="sa-cat-modal__close" data-sa-annonces-close aria-label="Fermer">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="sa-cat-modal__body sa-cat-modal__body--genre">
                <?php if (!empty($send_result['message']) && empty($send_result['success'])): ?>
                <div class="sa-annonces-alert sa-annonces-alert--err" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars($send_result['message'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php endif; ?>

                <form method="post" action="index.php" id="saAnnonceSendForm" class="sa-annonces-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="send_annonce" value="1">

                    <fieldset class="sa-annonces-form__audience">
                        <legend class="sa-annonces-form__legend">Destinataires</legend>
                        <div class="sa-annonces-audience-picks" role="radiogroup" aria-label="Destinataires">
                            <label class="sa-annonces-audience-pick<?php echo $audience_pref === 'client' ? ' is-checked' : ''; ?>">
                                <input type="radio" name="audience" value="client"<?php echo $audience_pref === 'client' ? ' checked' : ''; ?> required>
                                <span class="sa-annonces-audience-pick__card">
                                    <i class="fas fa-users" aria-hidden="true"></i>
                                    <span class="sa-annonces-audience-pick__label">Clients</span>
                                    <span class="sa-annonces-audience-pick__hint">Acheteurs</span>
                                </span>
                            </label>
                            <label class="sa-annonces-audience-pick<?php echo $audience_pref === 'vendeur' ? ' is-checked' : ''; ?>">
                                <input type="radio" name="audience" value="vendeur"<?php echo $audience_pref === 'vendeur' ? ' checked' : ''; ?>>
                                <span class="sa-annonces-audience-pick__card">
                                    <i class="fas fa-store" aria-hidden="true"></i>
                                    <span class="sa-annonces-audience-pick__label">Vendeurs</span>
                                    <span class="sa-annonces-audience-pick__hint">Boutiques</span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="sa-annonces-form__fields">
                        <div class="sa-annonces-field">
                            <label for="annonce_titre">Titre</label>
                            <input type="text" id="annonce_titre" name="titre" required maxlength="200"
                                class="sa-annonces-input sa-annonces-input--title"
                                value="<?php echo htmlspecialchars($form_prefill['titre'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="sa-annonces-field">
                            <label for="annonce_message">Message</label>
                            <textarea id="annonce_message" name="message" required maxlength="5000" class="sa-annonces-input" placeholder="Contenu de l’annonce…"><?php echo htmlspecialchars($form_prefill['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="sa-annonces-field">
                            <label for="annonce_lien">Lien <span class="sa-annonces-field__opt">(optionnel)</span></label>
                            <input type="text" id="annonce_lien" name="lien_url" maxlength="500" class="sa-annonces-input"
                                placeholder="/user/annonces.php ou https://…"
                                value="<?php echo htmlspecialchars($form_prefill['lien_url'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="sa-genre-modal-form__footer sa-annonces-form__footer">
                        <button type="button" class="sa-cat-btn sa-cat-btn--ghost" data-sa-annonces-close>Annuler</button>
                        <button type="submit" class="sa-cat-btn sa-cat-btn--primary">
                            <i class="fas fa-paper-plane" aria-hidden="true"></i> Envoyer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var OPEN = 'sa-annonces-modal-open';
        var layer = document.getElementById('saAnnoncesFormLayer');
        var openBtn = document.getElementById('saAnnoncesOpenForm');
        var titreEl = document.getElementById('annonce_titre');
        var messageEl = document.getElementById('annonce_message');
        var audienceEls = document.querySelectorAll('.sa-annonces-audience-pick input[type="radio"]');
        var lienEl = document.getElementById('annonce_lien');

        function setAudienceChecked() {
            document.querySelectorAll('.sa-annonces-audience-pick').forEach(function (lab) {
                var rb = lab.querySelector('input[type="radio"]');
                lab.classList.toggle('is-checked', rb && rb.checked);
            });
        }

        function fillForm(data) {
            if (titreEl) titreEl.value = data.titre || '';
            if (messageEl) messageEl.value = data.message || '';
            if (lienEl) lienEl.value = data.lien || '';
            var aud = data.audience === 'vendeur' ? 'vendeur' : 'client';
            audienceEls.forEach(function (rb) {
                rb.checked = rb.value === aud;
            });
            setAudienceChecked();
        }

        function openForm(prefill) {
            if (!layer) return;
            if (prefill) fillForm(prefill);
            layer.removeAttribute('hidden');
            document.body.classList.add(OPEN);
            if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
            if (titreEl) titreEl.focus();
        }

        function closeForm() {
            if (!layer) return;
            layer.setAttribute('hidden', 'hidden');
            document.body.classList.remove(OPEN);
            if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
        }

        if (openBtn) openBtn.addEventListener('click', function () { openForm(null); });
        document.querySelectorAll('[data-sa-annonces-close]').forEach(function (node) {
            node.addEventListener('click', closeForm);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && layer && !layer.hasAttribute('hidden')) closeForm();
        });
        audienceEls.forEach(function (rb) {
            rb.addEventListener('change', setAudienceChecked);
        });

        document.querySelectorAll('.js-annonce-resend').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openForm({
                    titre: btn.getAttribute('data-titre') || '',
                    message: btn.getAttribute('data-message') || '',
                    audience: btn.getAttribute('data-audience') || 'client',
                    lien: btn.getAttribute('data-lien') || ''
                });
            });
        });

        <?php if ($form_open): ?>
        openForm({
            titre: <?php echo json_encode($form_prefill['titre'], JSON_UNESCAPED_UNICODE); ?>,
            message: <?php echo json_encode($form_prefill['message'], JSON_UNESCAPED_UNICODE); ?>,
            audience: <?php echo json_encode($form_prefill['audience'], JSON_UNESCAPED_UNICODE); ?>,
            lien: <?php echo json_encode($form_prefill['lien_url'], JSON_UNESCAPED_UNICODE); ?>
        });
        <?php endif; ?>
    })();
    </script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
