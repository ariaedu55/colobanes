<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';
require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/includes/site_csrf.php';
require_once __DIR__ . '/includes/app_base.php';
require_once __DIR__ . '/models/model_contact_messages.php';

$base = get_site_base_url();
$seo_title = 'Contact — ' . SITE_BRAND_NAME . ' | Marketplace Sénégal';
$seo_description = 'Contactez ' . SITE_BRAND_NAME . ', marketplace des boutiques du Sénégal. Service client, partenariat vendeurs, questions sur votre commande.';
$seo_keywords = site_brand_seo_keywords_default() . ', contact marketplace, service client Sénégal';
$seo_canonical = $base . '/contact.php';

$social_config = file_exists(__DIR__ . '/config/social.php') ? require __DIR__ . '/config/social.php' : [];
$social_config = is_array($social_config) ? $social_config : [];
$ct_email = 'contact@colobanes.com';
$ct_adresse = 'Rond Point Colobane, Dakar, Sénégal';
$ct_maps = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('Rond Point Colobane, Dakar');

/* Formulaire : prérempli pour un client connecté */
$uid = (int) ($_SESSION['user_id'] ?? 0);
$v = [
    'nom' => trim((string) ($_SESSION['user_prenom'] ?? '') . ' ' . (string) ($_SESSION['user_nom'] ?? '')),
    'email' => (string) ($_SESSION['user_email'] ?? ''),
    'telephone' => '',
    'sujet' => (string) ($_GET['sujet'] ?? ''),
    'numero_commande' => '',
    'message' => '',
];
$ct_errors = [];
$ct_sent = !empty($_SESSION['contact_sent']);
unset($_SESSION['contact_sent']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach (array_keys($v) as $k) {
        $v[$k] = (string) ($_POST[$k] ?? '');
    }
    if (!site_csrf_verify_post()) {
        $ct_errors[] = site_csrf_fail_message();
    } elseif (trim((string) ($_POST['site_web'] ?? '')) !== '') {
        /* Champ piège invisible rempli : robot. On fait comme si tout allait bien. */
        $_SESSION['contact_sent'] = true;
        header('Location: ' . app_url('/contact.php#ctForm'), true, 303);
        exit;
    } else {
        $r = contact_message_save($_POST, $uid);
        if ($r['ok']) {
            $_SESSION['contact_sent'] = true;
            header('Location: ' . app_url('/contact.php#ctForm'), true, 303);
            exit;
        }
        $ct_errors = $r['errors'];
    }
}

/* Questions fréquentes : mêmes réponses que l'assistant */
$ct_faq = [];
if (is_file(__DIR__ . '/includes/assistant/assistant_engine.php')) {
    require_once __DIR__ . '/includes/assistant/assistant_engine.php';
    $by_id = [];
    foreach (assistant_knowledge() as $k) {
        $by_id[$k['id']] = $k;
    }
    foreach (['paiement', 'livraison', 'suivi', 'annulation', 'retour', 'vendre'] as $fid) {
        if (isset($by_id[$fid])) {
            $ct_faq[] = $by_id[$fid];
        }
    }
}
$ct_md = function (string $txt): string {
    $h = htmlspecialchars($txt, ENT_QUOTES, 'UTF-8');
    $h = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $h);
    return nl2br($h);
};
$e = function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$reseaux = [
    'facebook' => ['Facebook', 'fab fa-facebook-f'],
    'instagram' => ['Instagram', 'fab fa-instagram'],
    'tiktok' => ['TikTok', 'fab fa-tiktok'],
    'linkedin' => ['LinkedIn', 'fab fa-linkedin-in'],
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <?php require_once __DIR__ . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/contact-v2.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include 'nav_bar.php'; ?>

    <main class="ct-page">
        <div class="ct-shell">

            <header class="ct-hero">
                <div class="ct-hero__text">
                    <p class="ct-hero__eyebrow"><i class="fas fa-headset" aria-hidden="true"></i> Service client</p>
                    <h1 class="ct-hero__title">Comment pouvons-nous vous aider ?</h1>
                    <p class="ct-hero__sub">Nous vous répondons rapidement.</p>
                </div>
                <div class="ct-hero__actions">
                    <a class="ct-quick" href="<?php echo $e(app_url('/user/mes-commandes.php')); ?>">
                        <span class="ct-quick__icon ct-quick__icon--blue"><i class="fas fa-box" aria-hidden="true"></i></span>
                        <span><strong>Suivre ma commande</strong><small>Étapes et livraison</small></span>
                    </a>
                    <a class="ct-quick" href="<?php echo $e(app_url('/user/messages.php')); ?>">
                        <span class="ct-quick__icon ct-quick__icon--green"><i class="fas fa-comments" aria-hidden="true"></i></span>
                        <span><strong>Écrire à un vendeur</strong><small>Ma messagerie</small></span>
                    </a>
                    <button type="button" class="ct-quick" data-open-assistant>
                        <span class="ct-quick__icon ct-quick__icon--orange"><i class="fas fa-robot" aria-hidden="true"></i></span>
                        <span><strong>Demander à Colo</strong><small>Réponse immédiate, 24 h/24</small></span>
                    </button>
                </div>
            </header>

            <div class="ct-layout">
                <section class="ct-card ct-form-card" id="ctForm" aria-labelledby="ctFormTitle">
                    <h2 class="ct-card__title" id="ctFormTitle">Envoyez-nous un message</h2>

                    <?php if ($ct_sent): ?>
                        <div class="ct-success" role="status">
                            <span class="ct-success__icon"><i class="fas fa-check" aria-hidden="true"></i></span>
                            <div>
                                <strong>Message bien reçu, merci !</strong>
                                <p>Nous vous répondrons par e-mail dès que possible.</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($ct_errors): ?>
                        <div class="ct-alert" role="alert">
                            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                            <ul><?php foreach ($ct_errors as $err): ?><li><?php echo $e($err); ?></li><?php endforeach; ?></ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo $e(app_url('/contact.php')); ?>
<?php echo function_exists('protection_turnstile_widget') ? protection_turnstile_widget() : ''; ?>#ctForm" class="ct-form" novalidate>
                        <?php echo site_csrf_field(); ?>
                        <div class="ct-hp" aria-hidden="true">
                            <label>Site web <input type="text" name="site_web" tabindex="-1" autocomplete="off"></label>
                        </div>
                        <div class="ct-row">
                            <label class="ct-field">
                                <span>Nom complet</span>
                                <input type="text" name="nom" maxlength="120" required autocomplete="name" value="<?php echo $e($v['nom']); ?>">
                            </label>
                            <label class="ct-field">
                                <span>Adresse e-mail</span>
                                <input type="email" name="email" maxlength="190" required autocomplete="email" value="<?php echo $e($v['email']); ?>">
                            </label>
                        </div>
                        <div class="ct-row">
                            <label class="ct-field">
                                <span>Sujet</span>
                                <select name="sujet" required id="ctSujet">
                                    <option value="">Choisir…</option>
                                    <?php foreach (contact_sujets() as $k => $l): ?>
                                        <option value="<?php echo $e($k); ?>"<?php echo $v['sujet'] === $k ? ' selected' : ''; ?>><?php echo $e($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="ct-field">
                                <span>Téléphone <em>(facultatif)</em></span>
                                <input type="tel" name="telephone" maxlength="40" autocomplete="tel" value="<?php echo $e($v['telephone']); ?>">
                            </label>
                        </div>
                        <label class="ct-field" id="ctCmdField"<?php echo in_array($v['sujet'], ['commande', 'livraison'], true) ? '' : ' hidden'; ?>>
                            <span>Numéro de commande <em>(facultatif)</em></span>
                            <input type="text" name="numero_commande" maxlength="40" value="<?php echo $e($v['numero_commande']); ?>" placeholder="Ex. CMD-2026-00123">
                        </label>
                        <label class="ct-field">
                            <span>Votre message</span>
                            <textarea name="message" rows="6" maxlength="3000" required placeholder="Décrivez votre demande : plus elle est précise, plus la réponse sera rapide."><?php echo $e($v['message']); ?></textarea>
                        </label>
                        <div class="ct-form__foot">
                            <p class="ct-form__privacy"><i class="fas fa-lock" aria-hidden="true"></i> Vos informations servent uniquement à vous répondre.</p>
                            <button type="submit" class="ct-btn ct-btn--primary">Envoyer le message <i class="fas fa-paper-plane" aria-hidden="true"></i></button>
                        </div>
                    </form>
                </section>

                <aside class="ct-side">
                    <div class="ct-card ct-channels">
                        <h2 class="ct-card__title">Nos coordonnées</h2>
                        <a class="ct-channel" href="mailto:<?php echo $e($ct_email); ?>">
                            <span class="ct-channel__icon"><i class="fas fa-envelope" aria-hidden="true"></i></span>
                            <span><small>E-mail</small><strong><?php echo $e($ct_email); ?></strong></span>
                        </a>
                        <a class="ct-channel" href="<?php echo $e($ct_maps); ?>" target="_blank" rel="noopener">
                            <span class="ct-channel__icon"><i class="fas fa-location-dot" aria-hidden="true"></i></span>
                            <span><small>Adresse</small><strong><?php echo $e($ct_adresse); ?></strong></span>
                            <i class="fas fa-arrow-up-right-from-square ct-channel__ext" aria-hidden="true"></i>
                        </a>
                        <div class="ct-social">
                            <small>Suivez-nous</small>
                            <div class="ct-social__list">
                                <?php foreach ($reseaux as $rk => [$rlabel, $ricon]):
                                    $rurl = trim((string) ($social_config[$rk] ?? ''));
                                    if ($rurl === '') {
                                        continue;
                                    } ?>
                                    <a class="ct-social__item ct-social__item--<?php echo $e($rk); ?>" href="<?php echo $e($rurl); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo $e($rlabel); ?>" title="<?php echo $e($rlabel); ?>"><i class="<?php echo $e($ricon); ?>" aria-hidden="true"></i></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="ct-card ct-seller">
                        <span class="ct-seller__icon"><i class="fas fa-store" aria-hidden="true"></i></span>
                        <h2>Vous êtes commerçant ?</h2>
                        <p>Ouvrez votre boutique gratuitement et vendez aux clients près de chez vous.</p>
                        <a class="ct-btn ct-btn--light" href="<?php echo $e(app_url('/admin/inscription-vendeur.php')); ?>">Ouvrir ma boutique</a>
                    </div>
                </aside>
            </div>

            <?php if ($ct_faq): ?>
            <section class="ct-faq" aria-labelledby="ctFaqTitle">
                <h2 class="ct-section-title" id="ctFaqTitle">Questions fréquentes</h2>
                <div class="ct-faq__list">
                    <?php foreach ($ct_faq as $i => $q): ?>
                        <details class="ct-faq__item"<?php echo $i === 0 ? ' open' : ''; ?>>
                            <summary><?php echo $e($q['title']); ?><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                            <div class="ct-faq__answer"><?php echo $ct_md(assistant_kb_render($q['answer'])); ?></div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <section class="ct-card ct-map" aria-labelledby="ctMapTitle">
                <div class="ct-map__head">
                    <h2 class="ct-card__title" id="ctMapTitle">Nous trouver</h2>
                    <a class="ct-btn ct-btn--ghost" href="<?php echo $e($ct_maps); ?>" target="_blank" rel="noopener"><i class="fas fa-route" aria-hidden="true"></i> Itinéraire</a>
                </div>
                <iframe src="https://www.google.com/maps?q=<?php echo rawurlencode('Rond Point Colobane, Dakar'); ?>&amp;output=embed"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Localisation COLObanes — Rond Point Colobane, Dakar"></iframe>
            </section>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
    (function () {
        /* Numéro de commande : seulement pour les sujets commande / livraison */
        var sujet = document.getElementById('ctSujet');
        var cmd = document.getElementById('ctCmdField');
        if (sujet && cmd) {
            sujet.addEventListener('change', function () {
                cmd.hidden = !(sujet.value === 'commande' || sujet.value === 'livraison');
            });
        }
        /* « Demander à Colo » ouvre l'assistant du site */
        document.querySelectorAll('[data-open-assistant]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var launcher = document.getElementById('caLauncher');
                if (launcher) { launcher.click(); }
            });
        });
    })();
    </script>
</body>

</html>
