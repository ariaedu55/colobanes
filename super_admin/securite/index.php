<?php
/**
 * Super Admin — Sécurité : journal des erreurs, robots bloqués, maintenance 503, clés Cloudflare Turnstile.
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/conn/conn.php';
require_once dirname(__DIR__, 2) . '/includes/protection_site.php';
require_once dirname(__DIR__, 2) . '/includes/journal_erreurs.php';
require_once dirname(__DIR__, 2) . '/includes/assistant/assistant_keys.php';

global $db;
journal_erreurs_table($db);

/* ───── Actions (jeton CSRF vérifié par require_login.php) ───── */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $msg = '';
    if ($action === 'maintenance_on') {
        protection_maintenance_enregistrer(true, (string) ($_POST['message'] ?? ''), (int) ($_POST['minutes'] ?? 30));
        /* Vous gardez l'accès au site pour vérifier vos changements */
        setcookie('colobanes_maint', protection_maintenance_jeton(), [
            'expires' => time() + 43200, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        $msg = 'Maintenance activée : les visiteurs voient la page « Maintenance en cours » (code 503). Vous, vous gardez l’accès.';
    } elseif ($action === 'maintenance_off') {
        protection_maintenance_enregistrer(false);
        $msg = 'Maintenance terminée : le site est de nouveau ouvert à tous.';
    } elseif ($action === 'turnstile') {
        foreach (['turnstile_site_key', 'turnstile_secret_key'] as $nom) {
            $val = trim((string) ($_POST[$nom] ?? ''));
            if (!empty($_POST['retirer'][$nom])) {
                assistant_keys_save($nom, '');
            } elseif ($val !== '' && mb_strlen($val) <= 200 && !preg_match('/\s/', $val)) {
                assistant_keys_save($nom, $val);
            }
        }
        $msg = 'Clés Turnstile enregistrées.';
    } elseif ($action === 'resolu' && (int) ($_POST['id'] ?? 0) > 0) {
        $db->prepare('UPDATE journal_erreurs SET resolu = 1 WHERE id = ?')->execute([(int) $_POST['id']]);
        $msg = 'Erreur marquée comme résolue (elle réapparaîtra si elle se reproduit).';
    } elseif ($action === 'tout_resolu') {
        $db->exec('UPDATE journal_erreurs SET resolu = 1 WHERE resolu = 0');
        $msg = 'Toutes les erreurs sont marquées comme résolues.';
    } elseif ($action === 'purger') {
        $db->exec('DELETE FROM journal_erreurs WHERE resolu = 1');
        $msg = 'Erreurs résolues supprimées du journal.';
    }
    $_SESSION['sa_secu_flash'] = $msg;
    header('Location: index.php' . (!empty($_GET['vue']) ? '?vue=' . rawurlencode((string) $_GET['vue']) : ''));
    exit;
}
$flash = (string) ($_SESSION['sa_secu_flash'] ?? '');
unset($_SESSION['sa_secu_flash']);

/* ───── Données ───── */
$vue = ($_GET['vue'] ?? '') === 'resolues' ? 'resolues' : 'ouvertes';
$st = $db->prepare('SELECT * FROM journal_erreurs WHERE resolu = ? ORDER BY derniere DESC LIMIT 200');
$st->execute([$vue === 'resolues' ? 1 : 0]);
$erreurs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
$nb_ouvertes = (int) $db->query('SELECT COUNT(*) FROM journal_erreurs WHERE resolu = 0')->fetchColumn();
$nb_24h = (int) $db->query('SELECT COALESCE(SUM(occurrences),0) FROM journal_erreurs WHERE derniere >= NOW() - INTERVAL 1 DAY')->fetchColumn();
$stats = protection_stats(7);
$robots = (int) ($stats['robots_honeypot'] ?? 0) + (int) ($stats['robots_trop_rapides'] ?? 0) + (int) ($stats['robots_turnstile'] ?? 0);
$maint = protection_maintenance_etat();
$cles = assistant_keys_load(true);
$ts_actif = protection_turnstile_actif();

/* Fin du journal PHP du serveur (erreurs hors application : démarrage, extensions…) */
$log_brut = [];
$log_f = (string) ini_get('error_log');
if ($log_f === '' || !is_readable($log_f)) {
    $log_f = dirname(__DIR__, 2) . '/error_log';
}
if (is_readable($log_f) && filesize($log_f) > 0) {
    $fp = fopen($log_f, 'r');
    fseek($fp, max(0, filesize($log_f) - 16000));
    $lignes = explode("\n", trim((string) stream_get_contents($fp)));
    fclose($fp);
    $log_brut = array_slice(array_filter($lignes, static fn ($l) => trim($l) !== '' && !str_starts_with(trim($l), '#')), -30);
}

$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$niv_classe = ['fatale' => 'sa-secu-tag--rouge', 'exception' => 'sa-secu-tag--rouge', 'erreur' => 'sa-secu-tag--rouge', 'avertissement' => 'sa-secu-tag--orange'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sécurité — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-ca.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-assistant.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-securite.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-ca-page sa-ai-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-ca-shell">
        <header class="sa-ca-hero">
            <div class="sa-ca-hero__top">
                <div>
                    <h1 class="sa-ca-hero__title">Sécurité</h1>
                    <p class="sa-ca-hero__period">Erreurs, robots, maintenance</p>
                </div>
            </div>
        </header>

        <?php if ($flash !== ''): ?><p class="sa-ai-flash" role="status"><?php echo $e($flash); ?></p><?php endif; ?>

        <div class="sa-ai-providers">
            <article class="sa-ai-card">
                <div class="sa-ai-card__head"><h2 class="sa-ai-card__name">Erreurs à traiter</h2>
                    <span class="sa-ai-pill<?php echo $nb_ouvertes ? ' sa-ai-pill--pause' : ''; ?>"><?php echo $nb_ouvertes ? 'À voir' : 'RAS'; ?></span></div>
                <div class="sa-ai-card__row"><span>Ouvertes</span><strong><?php echo $nb_ouvertes; ?></strong></div>
                <div class="sa-ai-card__row"><span>Survenues sur 24 h</span><strong><?php echo $nb_24h; ?></strong></div>
            </article>
            <article class="sa-ai-card">
                <div class="sa-ai-card__head"><h2 class="sa-ai-card__name">Robots bloqués (7 j)</h2>
                    <span class="sa-ai-pill">Actif</span></div>
                <div class="sa-ai-card__row"><span>Piège invisible</span><strong><?php echo (int) ($stats['robots_honeypot'] ?? 0); ?></strong></div>
                <div class="sa-ai-card__row"><span>Envois trop rapides</span><strong><?php echo (int) ($stats['robots_trop_rapides'] ?? 0); ?></strong></div>
                <div class="sa-ai-card__row"><span>Turnstile</span><strong><?php echo (int) ($stats['robots_turnstile'] ?? 0); ?></strong></div>
            </article>
            <article class="sa-ai-card">
                <div class="sa-ai-card__head"><h2 class="sa-ai-card__name">Trafic excessif (7 j)</h2>
                    <span class="sa-ai-pill">Actif</span></div>
                <div class="sa-ai-card__row"><span>IP ralenties (429)</span><strong><?php echo (int) ($stats['ip_limitees'] ?? 0); ?></strong></div>
                <div class="sa-ai-card__row"><span>Limite</span><strong>240 pages / min</strong></div>
            </article>
            <article class="sa-ai-card">
                <div class="sa-ai-card__head"><h2 class="sa-ai-card__name">Site</h2>
                    <span class="sa-ai-pill<?php echo !empty($maint['actif']) ? ' sa-ai-pill--pause' : ''; ?>"><?php echo !empty($maint['actif']) ? 'Maintenance' : 'En ligne'; ?></span></div>
                <div class="sa-ai-card__row"><span>Turnstile</span><strong><?php echo $ts_actif ? 'Activé' : 'Non configuré'; ?></strong></div>
                <div class="sa-ai-card__row"><span>Robots total</span><strong><?php echo $robots; ?></strong></div>
            </article>
        </div>

        <section class="sa-ai-table-wrap sa-secu-bloc">
            <div class="sa-secu-head">
                <h2>Journal des erreurs</h2>
                <nav class="sa-secu-tabs" aria-label="Filtre">
                    <a href="index.php" class="<?php echo $vue === 'ouvertes' ? 'is-on' : ''; ?>">Ouvertes (<?php echo $nb_ouvertes; ?>)</a>
                    <a href="index.php?vue=resolues" class="<?php echo $vue === 'resolues' ? 'is-on' : ''; ?>">Résolues</a>
                </nav>
                <form method="post" class="sa-secu-actions">
                    <?php echo super_admin_csrf_field(); ?>
                    <?php if ($vue === 'ouvertes' && $erreurs): ?>
                        <button type="submit" name="action" value="tout_resolu" class="sa-secu-btn">Tout marquer résolu</button>
                    <?php elseif ($vue === 'resolues' && $erreurs): ?>
                        <button type="submit" name="action" value="purger" class="sa-secu-btn">Vider les résolues</button>
                    <?php endif; ?>
                </form>
            </div>
            <?php if (!$erreurs): ?>
                <p class="sa-secu-vide"><?php echo $vue === 'ouvertes' ? 'Aucune erreur en attente. Le site fonctionne normalement.' : 'Aucune erreur résolue.'; ?></p>
            <?php else: ?>
                <div class="sa-secu-scroll">
                    <table class="sa-ai-table sa-secu-table">
                        <thead><tr><th>Dernière fois</th><th>Type</th><th>Erreur</th><th>Fois</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($erreurs as $r): ?>
                            <tr>
                                <td class="sa-secu-date"><?php echo $e(date('d/m H:i', strtotime((string) $r['derniere']))); ?></td>
                                <td><span class="sa-secu-tag <?php echo $niv_classe[$r['niveau']] ?? ''; ?>"><?php echo $e($r['niveau']); ?></span></td>
                                <td class="sa-secu-msg">
                                    <strong><?php echo $e($r['message']); ?></strong>
                                    <small><?php echo $e($r['fichier']); ?><?php echo (int) $r['ligne'] ? ' : ligne ' . (int) $r['ligne'] : ''; ?> — <?php echo $e($r['methode'] . ' ' . $r['url']); ?></small>
                                </td>
                                <td><strong><?php echo (int) $r['occurrences']; ?></strong></td>
                                <td>
                                    <?php if (!(int) $r['resolu']): ?>
                                        <form method="post">
                                            <?php echo super_admin_csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                            <button type="submit" name="action" value="resolu" class="sa-secu-btn sa-secu-btn--ok" title="Marquer comme résolue">Résolu</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <div class="sa-secu-grille">
            <section class="sa-ai-keys">
                <h2>Mode maintenance</h2>
                <?php if (!empty($maint['actif'])): ?>
                    <p class="sa-secu-texte">Activée depuis le <?php echo $e(date('d/m à H:i', strtotime((string) ($maint['depuis'] ?? 'now')))); ?>.</p>
                    <form method="post"><?php echo super_admin_csrf_field(); ?>
                        <button type="submit" name="action" value="maintenance_off" class="sa-ai-keys__save">Remettre le site en ligne</button>
                    </form>
                <?php else: ?>
                    <p class="sa-secu-texte">Page d’attente pendant une mise à jour. Vous gardez l’accès.</p>
                    <form method="post" class="sa-ai-keys__form"><?php echo super_admin_csrf_field(); ?>
                        <div class="sa-ai-keys__row"><label for="m_msg">Message</label>
                            <input type="text" id="m_msg" name="message" maxlength="300" placeholder="COLObanes fait peau neuve, retour dans quelques minutes."></div>
                        <div class="sa-ai-keys__row"><label for="m_min">Durée prévue</label>
                            <input type="number" id="m_min" name="minutes" min="5" max="1440" value="30"> <span>min</span></div>
                        <button type="submit" name="action" value="maintenance_on" class="sa-ai-keys__save">Activer la maintenance</button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="sa-ai-keys">
                <h2>Cloudflare Turnstile</h2>
                <p class="sa-secu-texte">Clés : Cloudflare → <strong>Turnstile</strong>.</p>
                <form method="post" class="sa-ai-keys__form" autocomplete="off"><?php echo super_admin_csrf_field(); ?>
                    <?php foreach (['turnstile_site_key' => 'Clé du site', 'turnstile_secret_key' => 'Clé secrète'] as $nom => $lib):
                        $en_place = (string) ($cles[$nom] ?? '') !== ''; ?>
                        <div class="sa-ai-keys__row">
                            <label for="t_<?php echo $nom; ?>"><?php echo $e($lib); ?></label>
                            <input type="password" id="t_<?php echo $nom; ?>" name="<?php echo $nom; ?>" spellcheck="false"
                                placeholder="<?php echo $en_place ? 'Enregistrée — collez une nouvelle clé pour la remplacer' : 'Collez la clé ici'; ?>">
                            <?php if ($en_place): ?><label class="sa-ai-keys__del"><input type="checkbox" name="retirer[<?php echo $nom; ?>]" value="1"> Retirer</label><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" name="action" value="turnstile" class="sa-ai-keys__save">Enregistrer</button>
                </form>
            </section>
        </div>

        <?php if ($log_brut): ?>
            <section class="sa-ai-table-wrap sa-secu-bloc">
                <div class="sa-secu-head"><h2>Journal du serveur (30 dernières lignes)</h2></div>
                <pre class="sa-secu-log"><?php echo $e(implode("\n", $log_brut)); ?></pre>
            </section>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
