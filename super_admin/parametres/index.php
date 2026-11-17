<?php
/**
 * Hub Paramètres — Super administrateur
 */
require_once __DIR__ . '/../includes/require_login.php';

$param_sections = [
    [
        'id' => 'catalogue',
        'title' => 'Catalogue',
        'icon' => 'fa-box-open',
        'items' => [
            [
                'href' => 'categories-catalogue.php',
                'icon' => 'fa-sitemap',
                'title' => 'Rayons (menu)',
                'desc' => 'Catégories principales affichées dans le menu',
            ],
            [
                'href' => 'genres-catalogue.php',
                'icon' => 'fa-tags',
                'title' => 'Genres produits',
                'desc' => 'Filtres et genres associés aux rayons',
            ],
            [
                'href' => 'sous-categories-catalogue.php',
                'icon' => 'fa-diagram-project',
                'title' => 'Sous-catégories',
                'desc' => 'Arborescence détaillée du catalogue',
            ],
            [
                'href' => 'sous-sous-categories-catalogue.php',
                'icon' => 'fa-folder-tree',
                'title' => 'Sous-sous-catégories',
                'desc' => 'Niveau détaillé sous chaque sous-catégorie',
            ],
        ],
    ],
    [
        'id' => 'vendeurs',
        'title' => 'Vendeurs',
        'icon' => 'fa-store',
        'items' => [
            [
                'href' => 'boutique-types.php',
                'icon' => 'fa-layer-group',
                'title' => 'Types de boutique',
                'desc' => 'Profils et types proposés aux vendeurs',
            ],
            [
                'href' => 'rappels-vendeur.php',
                'icon' => 'fa-bell',
                'title' => 'Rappels vendeur',
                'desc' => 'Notifications et relances côté vendeur',
            ],
        ],
    ],
    [
        'id' => 'plateforme',
        'title' => 'Plateforme',
        'icon' => 'fa-sliders',
        'items' => [
            [
                'href' => 'app-mobile.php',
                'icon' => 'fa-mobile-screen',
                'title' => 'Mise à jour app',
                'desc' => 'Version minimale et message de mise à jour',
            ],
            [
                'href' => 'hero-affiches.php',
                'icon' => 'fa-panorama',
                'title' => 'Hero & bannières',
                'desc' => 'Visuels d’accueil et bannières promotionnelles',
                'accent' => true,
            ],
        ],
    ],
];

$param_total_modules = 0;
foreach ($param_sections as $sec) {
    $param_total_modules += count($sec['items']);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-parametres.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page sa-param-hub-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-param-shell">
        <header class="sa-param-hero sa-param-hero--hub" aria-labelledby="sa-param-title">
            <div class="sa-param-hero__inner">
                <div class="sa-param-hero__intro">
                    <h1 class="sa-param-hero__title" id="sa-param-title">Paramètres</h1>
                    <p class="sa-param-hero__sub">Catalogue, vendeurs et options de la plateforme</p>
                </div>
                <div class="sa-param-hero__kpis" role="group" aria-label="Vue d’ensemble">
                    <div class="sa-param-hero-kpi">
                        <span class="sa-param-hero-kpi__label">Modules</span>
                        <span class="sa-param-hero-kpi__value"><?php echo (int) $param_total_modules; ?></span>
                    </div>
                    <div class="sa-param-hero-kpi">
                        <span class="sa-param-hero-kpi__label">Sections</span>
                        <span class="sa-param-hero-kpi__value"><?php echo count($param_sections); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <section class="sa-users-panel sa-param-hub-panel" aria-label="Accès aux réglages">
            <div class="sa-param-hub-grid" role="navigation" aria-label="Sections paramètres">
                <?php foreach ($param_sections as $section): ?>
                    <div class="sa-param-hub-col" id="sa-param-col-<?php echo htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="sa-param-hub-col__head">
                            <span class="sa-param-hub-col__icon" aria-hidden="true">
                                <i class="fas <?php echo htmlspecialchars($section['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                            </span>
                            <h2 class="sa-param-hub-col__title"><?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <ul class="sa-param-hub-cards">
                            <?php foreach ($section['items'] as $item): ?>
                                <li>
                                    <a class="sa-param-hub-card<?php echo !empty($item['accent']) ? ' sa-param-hub-card--accent' : ''; ?>"
                                        href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="sa-param-hub-card__icon" aria-hidden="true">
                                            <i class="fas <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                        </span>
                                        <span class="sa-param-hub-card__body">
                                            <span class="sa-param-hub-card__label"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="sa-param-hub-card__desc"><?php echo htmlspecialchars($item['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </span>
                                        <span class="sa-param-hub-card__go" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>
