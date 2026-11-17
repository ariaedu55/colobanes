<?php
/**
 * En-tête HTML des pages Dons. Variables : $dons_title, $dons_description, $dons_tab (explorer|mes-dons|messages|offrir).
 */
$dons_tab = $dons_tab ?? 'explorer';
$dons_non_lus = $dons_uid > 0 ? dons_count_non_lus($dons_uid) : 0;
$seo_title = ($dons_title ?? 'Dons') . ' — ' . SITE_BRAND_NAME;
$seo_description = $dons_description ?? 'Donnez et récupérez gratuitement des objets près de chez vous sur ' . SITE_BRAND_NAME . '.';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../pwa_meta.php'; ?>
    <?php include __DIR__ . '/../seo_meta.php'; ?>
    <?php require_once __DIR__ . '/../ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/dons.css<?php echo asset_version_query(); ?>">
</head>
<body class="dn-body">
<?php include __DIR__ . '/../../nav_bar.php'; ?>
<main class="dn-page">
    <div class="dn-shell">
        <div class="dn-tabs" role="navigation" aria-label="Espace Dons">
            <div class="dn-tabs__list">
                <a href="/dons/" class="dn-tab<?php echo $dons_tab === 'explorer' ? ' is-active' : ''; ?>"<?php echo $dons_tab === 'explorer' ? ' aria-current="page"' : ''; ?>>
                    <i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorer</span>
                </a>
                <a href="/dons/mes-dons.php" class="dn-tab<?php echo $dons_tab === 'mes-dons' ? ' is-active' : ''; ?>"<?php echo $dons_tab === 'mes-dons' ? ' aria-current="page"' : ''; ?>>
                    <i class="fa-solid fa-box-open" aria-hidden="true"></i><span>Mes dons</span>
                </a>
                <a href="/dons/messages.php" class="dn-tab<?php echo $dons_tab === 'messages' ? ' is-active' : ''; ?>"<?php echo $dons_tab === 'messages' ? ' aria-current="page"' : ''; ?>>
                    <i class="fa-solid fa-comments" aria-hidden="true"></i><span>Messages</span>
                    <?php if ($dons_non_lus > 0): ?><b class="dn-tab__badge"><?php echo $dons_non_lus > 99 ? '99+' : $dons_non_lus; ?></b><?php endif; ?>
                </a>
            </div>
            <?php if ($dons_tab !== 'offrir'): ?>
            <a href="/dons/offrir.php" class="dn-btn dn-btn--primary dn-tabs__cta">
                <i class="fa-solid fa-plus" aria-hidden="true"></i><span>Offrir un objet</span>
            </a>
            <?php endif; ?>
        </div>
        <?php if (!dons_module_actif()): ?>
            <div class="dn-alert dn-alert--info"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                Le module Dons est en pause : les annonces restent consultables, mais les nouvelles publications et demandes sont suspendues.</div>
        <?php endif; ?>
        <?php if ($dons_flash_msg = dons_flash_take()): ?>
            <div class="dn-alert dn-alert--<?php echo dons_e($dons_flash_msg['type']); ?>" role="status">
                <i class="fa-solid <?php echo $dons_flash_msg['type'] === 'ok' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>" aria-hidden="true"></i>
                <?php echo dons_e($dons_flash_msg['message']); ?>
            </div>
        <?php endif; ?>
