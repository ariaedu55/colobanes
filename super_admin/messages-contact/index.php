<?php
/**
 * Super Admin — messages reçus depuis la page Contact du site.
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/models/model_contact_messages.php';

$vue = ($_GET['vue'] ?? '') === 'traite' ? 'traite' : 'nouveau';

/* CSRF vérifié par require_login */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $mid = (int) ($_POST['id'] ?? 0);
    $nouveau = ($_POST['statut'] ?? '') === 'nouveau' ? 'nouveau' : 'traite';
    if (contact_message_set_statut($mid, $nouveau)) {
        super_admin_log_action((int) $_SESSION['super_admin_id'], 'contact_message_' . $nouveau, 'contact_message', $mid, 'Message de contact #' . $mid);
        $_SESSION['super_admin_flash_ok'] = $nouveau === 'traite' ? 'Message marqué comme traité.' : 'Message remis à traiter.';
    }
    header('Location: ' . app_url('/super_admin/messages-contact/index.php' . ($vue === 'traite' ? '?vue=traite' : '')));
    exit;
}

$msg_ok = $_SESSION['super_admin_flash_ok'] ?? '';
unset($_SESSION['super_admin_flash_ok']);
$rows = contact_messages_list($vue);
$nb_nouveaux = contact_messages_count_new();
$sujets = contact_sujets();
$csrf = super_admin_csrf_field();
$e = function ($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages de contact — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dons.css<?php echo asset_version_query(); ?>">
    <style>
        .sa-cm-msg { margin: 8px 0 0; padding: 10px 12px; border-radius: 10px; background: #f9fafb; font-size: 14px; line-height: 1.55; color: #344054; white-space: pre-line; overflow-wrap: anywhere; }
        .sa-cm-meta a { color: #3564a6; }
    </style>
</head>
<body class="page-users admin-clients-page sa-users-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell">
        <header class="sa-users-hero">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow"><i class="fas fa-envelope-open-text"></i> Service client</p>
                    <h1 class="sa-users-hero__title">Messages de contact</h1>
                    <p class="sa-users-hero__lead">Messages envoyés depuis la page Contact. Répondez par e-mail, puis marquez-les comme traités.</p>
                </div>
                <div class="sa-users-kpis">
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">À traiter</span>
                        <span class="sa-users-kpi__value"><?php echo (int) $nb_nouveaux; ?></span>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($msg_ok !== ''): ?>
            <div class="sa-flash sa-flash--ok"><?php echo $e($msg_ok); ?></div>
        <?php endif; ?>

        <div class="ad-tabs" role="tablist">
            <a href="index.php" class="ad-tab<?php echo $vue === 'nouveau' ? ' is-active' : ''; ?>">À traiter</a>
            <a href="index.php?vue=traite" class="ad-tab<?php echo $vue === 'traite' ? ' is-active' : ''; ?>">Traités</a>
        </div>

        <?php if (!$rows): ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><h3>Aucun message <?php echo $vue === 'nouveau' ? 'à traiter' : 'traité'; ?></h3></div>
        <?php else: ?>
            <div class="ad-list">
                <?php foreach ($rows as $m):
                    $reply = 'mailto:' . rawurlencode($m['email']) . '?subject=' . rawurlencode('Re : ' . ($sujets[$m['sujet']] ?? 'Votre message') . ' — COLObanes'); ?>
                    <article class="ad-row">
                        <div class="ad-row__main">
                            <p class="ad-row__title">
                                <?php echo $e($m['nom']); ?>
                                <span class="ad-pill ad-pill--disponible"><?php echo $e($sujets[$m['sujet']] ?? $m['sujet']); ?></span>
                                <?php if ($m['numero_commande'] !== ''): ?><span class="ad-pill">Commande <?php echo $e($m['numero_commande']); ?></span><?php endif; ?>
                            </p>
                            <p class="ad-row__meta sa-cm-meta">
                                <a href="<?php echo $e($reply); ?>"><?php echo $e($m['email']); ?></a>
                                <?php echo $m['telephone'] !== '' ? ' · ' . $e($m['telephone']) : ''; ?>
                                · <?php echo $e(date('d/m/Y H:i', strtotime($m['created_at']))); ?>
                                <?php echo $m['user_id'] ? ' · client connecté #' . (int) $m['user_id'] : ''; ?>
                            </p>
                            <p class="sa-cm-msg"><?php echo $e($m['message']); ?></p>
                        </div>
                        <div class="ad-row__actions">
                            <a class="ad-btn" href="<?php echo $e($reply); ?>"><i class="fas fa-reply"></i> Répondre</a>
                            <form method="post"><?php echo $csrf; ?><input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>"><input type="hidden" name="statut" value="<?php echo $vue === 'nouveau' ? 'traite' : 'nouveau'; ?>">
                                <button class="ad-btn <?php echo $vue === 'nouveau' ? 'ad-btn--ok' : ''; ?>" type="submit"><?php echo $vue === 'nouveau' ? 'Marquer traité' : 'Remettre à traiter'; ?></button></form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
