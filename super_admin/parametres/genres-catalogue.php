<?php
/**
 * Genres produits — indépendants des rayons ; les vendeurs les cochent à la publication.
 */
require_once __DIR__ . '/../includes/require_login.php';

if (ob_get_level() === 0) {
    ob_start();
}
require_once dirname(__DIR__, 2) . '/includes/flash_toast.php';

require_once dirname(__DIR__, 2) . '/models/model_genres.php';
require_once dirname(__DIR__, 2) . '/models/model_categories.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_categories.php';

$sa_id = (int) ($_SESSION['super_admin_id'] ?? 0);
$flash_ok = '';
$flash_err = '';
$upload_root = dirname(__DIR__, 2) . '/upload/';

/**
 * @return int[]
 */
function sa_genre_collect_post_cg_ids() {
    $raw = $_POST['g_categorie_generale_ids'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $x) {
        $i = (int) $x;
        if ($i > 0) {
            $out[$i] = true;
        }
    }
    return array_keys($out);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($tok)) {
        $flash_err = 'Jeton de sécurité invalide.';
    } elseif (!genres_table_exists()) {
        $flash_err = 'Table genres absente. Exécutez la migration : php migrations/run_migrate_genres.php';
    } else {
        if (isset($_POST['create_genre'])) {
            $nom = trim((string) ($_POST['g_nom'] ?? ''));
            $d = trim((string) ($_POST['g_description'] ?? ''));
            $so = (int) ($_POST['g_sort'] ?? 0);
            $cg_ids = sa_genre_collect_post_cg_ids();
            $img = null;
            if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? 0) === UPLOAD_ERR_OK) {
                $up = upload_genre_image($_FILES);
                if ($up) {
                    $img = $up;
                } else {
                    $flash_err = 'Image non valide (format JPEG, PNG, GIF, WebP — max. 20 Mo).';
                }
            }
            if ($flash_err === '') {
                if ($nom === '') {
                    $flash_err = 'Le nom du genre est obligatoire.';
                } elseif (!genres_cg_links_table_exists()) {
                    $flash_err = 'Migration requise : php migrations/run_migrate_genres_categories_generales.php';
                } elseif (empty($cg_ids)) {
                    $flash_err = 'Cochez au moins une catégorie principale (rayon) pour ce genre.';
                } else {
                    foreach ($cg_ids as $cid) {
                        if (!get_categorie_generale_by_id($cid)) {
                            $flash_err = 'Une ou plusieurs catégories principales sont invalides.';
                            break;
                        }
                    }
                }
                if ($flash_err === '') {
                    $id = genre_insert_row($nom, $d !== '' ? $d : null, $img, $so);
                    if ($id) {
                        if (!save_genre_categorie_generale_links($id, $cg_ids)) {
                            genre_delete_row($id);
                            $flash_err = 'Erreur lors de l’enregistrement des catégories liées.';
                        } else {
                            super_admin_log_action($sa_id, 'genre_cree', 'genres', $id, $nom);
                            http_redirect_safe('/super_admin/parametres/genres-catalogue.php?ok=1');
                        }
                    } else {
                        $flash_err = 'Impossible d’ajouter le genre (nom déjà utilisé ou erreur).';
                    }
                }
            }
        } elseif (isset($_POST['update_genre'])) {
            $id = (int) ($_POST['g_id'] ?? 0);
            $nom = trim((string) ($_POST['g_nom'] ?? ''));
            $d = trim((string) ($_POST['g_description'] ?? ''));
            $so = (int) ($_POST['g_sort'] ?? 0);
            $cg_ids = sa_genre_collect_post_cg_ids();
            $row = $id > 0 ? get_genre_by_id($id) : false;
            if (!$row) {
                $flash_err = 'Genre introuvable.';
            } else {
                $img = $row['image'] ?? null;
                if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? 0) === UPLOAD_ERR_OK) {
                    $up = upload_genre_image($_FILES);
                    if ($up) {
                        if (!empty($row['image']) && is_file($upload_root . str_replace('\\', '/', (string) $row['image']))) {
                            @unlink($upload_root . str_replace('\\', '/', (string) $row['image']));
                        }
                        $img = $up;
                    } else {
                        $flash_err = 'Image non valide (format JPEG, PNG, GIF, WebP — max. 20 Mo).';
                    }
                }
                if ($flash_err === '') {
                    if ($nom === '') {
                        $flash_err = 'Le nom du genre est obligatoire.';
                    } elseif (!genres_cg_links_table_exists()) {
                        $flash_err = 'Migration requise : php migrations/run_migrate_genres_categories_generales.php';
                    } elseif (empty($cg_ids)) {
                        $flash_err = 'Cochez au moins une catégorie principale (rayon) pour ce genre.';
                    } else {
                        foreach ($cg_ids as $cid) {
                            if (!get_categorie_generale_by_id($cid)) {
                                $flash_err = 'Une ou plusieurs catégories principales sont invalides.';
                                break;
                            }
                        }
                    }
                }
                if ($flash_err === '' && genre_update_row($id, $nom, $d !== '' ? $d : null, $img, $so)) {
                    if (!save_genre_categorie_generale_links($id, $cg_ids)) {
                        $flash_err = 'Le genre a été mis à jour mais l’enregistrement des catégories liées a échoué.';
                    } else {
                        super_admin_log_action($sa_id, 'genre_modifie', 'genres', $id, $nom);
                        http_redirect_safe('/super_admin/parametres/genres-catalogue.php?ok=1');
                    }
                } elseif ($flash_err === '') {
                    $flash_err = 'Modification impossible (nom en doublon ou erreur).';
                }
            }
        } elseif (isset($_POST['delete_genre'])) {
            $id = (int) ($_POST['g_id'] ?? 0);
            $row = $id > 0 ? get_genre_by_id($id) : false;
            if (!$row) {
                $flash_err = 'Genre introuvable.';
            } elseif (genre_delete_row($id)) {
                if (!empty($row['image']) && is_file($upload_root . str_replace('\\', '/', (string) $row['image']))) {
                    @unlink($upload_root . str_replace('\\', '/', (string) $row['image']));
                }
                super_admin_log_action($sa_id, 'genre_supprime', 'genres', $id, (string) ($row['nom'] ?? ''));
                http_redirect_safe('/super_admin/parametres/genres-catalogue.php?ok=1');
            } else {
                $flash_err = 'Suppression impossible : des produits sont encore associés à ce genre.';
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $flash_ok = 'Enregistrement effectué.';
}

$genres_list = genres_list_all();
$edit_g = isset($_GET['edit_genre']) ? (int) $_GET['edit_genre'] : 0;
$row_edit_g = $edit_g > 0 ? get_genre_by_id($edit_g) : false;
$cg_list_all = (function_exists('categories_generales_table_exists') && categories_generales_table_exists())
    ? get_general_categories_ordered() : [];
$links_ok = genres_cg_links_table_exists();
$prefill_cg_ids = ($row_edit_g && $links_ok) ? get_categorie_generale_ids_for_genre((int) $row_edit_g['id']) : [];

$csrf = super_admin_csrf_token();
$table_ok = genres_table_exists();
$genres_total = count($genres_list);

$genre_cards = [];
foreach ($genres_list as $gr) {
    $gid = (int) ($gr['id'] ?? 0);
    if ($gid <= 0) {
        continue;
    }
    $genre_cards[$gid] = [
        'row' => $gr,
        'nb_produits' => count_produits_par_genre_id($gid),
        'rayon_ids' => ($links_ok ? get_categorie_generale_ids_for_genre($gid) : []),
    ];
}

$rayon_groups = [];
foreach ($cg_list_all as $__cg) {
    $__cid = (int) ($__cg['id'] ?? 0);
    if ($__cid <= 0) {
        continue;
    }
    $rayon_groups[$__cid] = [
        'nom' => (string) ($__cg['nom'] ?? ''),
        'sort' => (int) ($__cg['sort_ordre'] ?? 0),
        'genre_ids' => [],
    ];
}

$sans_rayon_ids = [];
foreach ($genre_cards as $gid => $card) {
    $ids = $card['rayon_ids'];
    if (empty($ids)) {
        $sans_rayon_ids[] = $gid;
        continue;
    }
    foreach ($ids as $irc) {
        if (isset($rayon_groups[$irc])) {
            $rayon_groups[$irc]['genre_ids'][$gid] = true;
        }
    }
}

$rayon_groups_sorted = $rayon_groups;
uasort($rayon_groups_sorted, function ($a, $b) {
    $sa = (int) ($a['sort'] ?? 0);
    $sb = (int) ($b['sort'] ?? 0);
    if ($sa !== $sb) {
        return $sa <=> $sb;
    }
    return strcasecmp((string) ($a['nom'] ?? ''), (string) ($b['nom'] ?? ''));
});

$sa_genre_sort_ids = function (array $ids) use ($genre_cards) {
    usort($ids, function ($a, $b) use ($genre_cards) {
        $ra = $genre_cards[$a]['row'] ?? [];
        $rb = $genre_cards[$b]['row'] ?? [];
        $sa = (int) ($ra['sort_ordre'] ?? 0);
        $sb = (int) ($rb['sort_ordre'] ?? 0);
        if ($sa !== $sb) {
            return $sa <=> $sb;
        }
        return strcasecmp((string) ($ra['nom'] ?? ''), (string) ($rb['nom'] ?? ''));
    });
    return $ids;
};

$sans_rayon_ids = $sa_genre_sort_ids($sans_rayon_ids);
$group_by_rayon = $links_ok && !empty($cg_list_all);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Genres catalogue — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-parametres.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page sa-param-hub-page sa-cat-page<?php echo $row_edit_g ? ' sa-cat-modal-open' : ''; ?>">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-param-shell sa-cat-shell">
        <a class="sa-cat-back" href="index.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a>

        <header class="sa-param-hero sa-cat-sub-hero" aria-labelledby="sa-genres-title">
            <div class="sa-param-hero__inner">
                <div class="sa-param-hero__intro">
                    <h1 class="sa-param-hero__title" id="sa-genres-title">Genres</h1>
                    <p class="sa-param-hero__sub">Filtres proposés aux vendeurs selon le rayon du produit</p>
                </div>
                <div class="sa-param-hero__kpis" role="group" aria-label="Statistiques genres">
                    <div class="sa-param-hero-kpi">
                        <span class="sa-param-hero-kpi__label">Genres</span>
                        <span class="sa-param-hero-kpi__value"><?php echo (int) $genres_total; ?></span>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($flash_ok !== ''): ?>
            <div class="sa-cat-alert sa-cat-alert--ok" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($flash_ok, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($flash_err !== ''): ?>
            <div class="sa-cat-alert sa-cat-alert--err" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($flash_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$table_ok): ?>
            <div class="sa-cat-migrate-banner" role="note">
                <strong>Migration requise.</strong> Exécutez <code>php migrations/run_migrate_genres.php</code> pour créer les tables <code>genres</code> et <code>produits_genres</code>, puis rechargez cette page.
            </div>
        <?php endif; ?>
        <?php if ($table_ok && !$links_ok): ?>
            <div class="sa-cat-migrate-banner" role="note">
                <strong>Liaison genres ↔ rayons.</strong> Exécutez <code>php migrations/run_migrate_genres_categories_generales.php</code> pour activer la sélection des catégories principales par genre.
            </div>
        <?php endif; ?>

        <section class="sa-users-panel sa-cat-genres-panel" aria-labelledby="sa-genres-panel-title">
            <div class="sa-users-panel__head sa-cat-rayons-panel__head">
                <div>
                    <h2 id="sa-genres-panel-title">
                        <i class="fas fa-tags" aria-hidden="true"></i>
                        Liste des genres
                    </h2>
                    <p class="sa-users-panel__meta">Regroupés par rayon · ordre, visuel et nombre de produits</p>
                </div>
                <div class="sa-cat-rayons-panel__toolbar">
                    <a class="sa-cat-btn sa-cat-btn--ghost sa-cat-rayons-panel__genres" href="categories-catalogue.php">
                        <i class="fas fa-sitemap" aria-hidden="true"></i>
                        Rayons
                    </a>
                    <button type="button" class="sa-cat-btn sa-cat-btn--primary sa-cat-rayons-panel__add" id="btnOpenModalGenre" data-open-modal="saModalGenre" <?php echo !$table_ok ? 'disabled title="Migration requise"' : ''; ?> <?php echo $row_edit_g ? 'disabled title="Terminez la modification en cours" aria-disabled="true"' : ''; ?>>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        Ajouter un genre
                    </button>
                </div>
            </div>

            <?php if (empty($genres_list)): ?>
                <div class="sa-users-empty sa-cat-rayons-empty">
                    <i class="fas fa-tags" aria-hidden="true"></i>
                    <p><strong>Aucun genre pour l’instant.</strong></p>
                    <p>Ajoutez un genre et associez-le à au moins un rayon.</p>
                </div>
            <?php elseif (!$group_by_rayon): ?>
                <ul class="sa-cat-genre-list sa-cat-genre-list--flat">
                    <?php foreach ($sa_genre_sort_ids(array_keys($genre_cards)) as $gid):
                        include __DIR__ . '/_partials/genre-row.php';
                    endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="sa-genre-rayon-groups">
                    <?php
                    $any_group = false;
                    foreach ($rayon_groups_sorted as $cid => $grp):
                        $gids = $sa_genre_sort_ids(array_keys($grp['genre_ids']));
                        if (empty($gids)) {
                            continue;
                        }
                        $any_group = true;
                        $grp_nom = (string) ($grp['nom'] ?? '');
                    ?>
                    <section class="sa-genre-rayon-group" aria-labelledby="sa-genre-rayon-<?php echo (int) $cid; ?>">
                        <header class="sa-genre-rayon-group__head">
                            <h3 class="sa-genre-rayon-group__title" id="sa-genre-rayon-<?php echo (int) $cid; ?>">
                                <span class="sa-genre-rayon-group__icon" aria-hidden="true"><i class="fas fa-folder"></i></span>
                                <?php echo htmlspecialchars($grp_nom, ENT_QUOTES, 'UTF-8'); ?>
                            </h3>
                            <span class="sa-genre-rayon-group__count"><?php echo count($gids); ?> genre<?php echo count($gids) > 1 ? 's' : ''; ?></span>
                        </header>
                        <ul class="sa-cat-genre-list">
                            <?php foreach ($gids as $gid):
                                include __DIR__ . '/_partials/genre-row.php';
                            endforeach; ?>
                        </ul>
                    </section>
                    <?php endforeach; ?>

                    <?php if (!empty($sans_rayon_ids)): ?>
                    <section class="sa-genre-rayon-group sa-genre-rayon-group--orphan" aria-labelledby="sa-genre-rayon-none">
                        <header class="sa-genre-rayon-group__head">
                            <h3 class="sa-genre-rayon-group__title" id="sa-genre-rayon-none">
                                <span class="sa-genre-rayon-group__icon" aria-hidden="true"><i class="fas fa-unlink"></i></span>
                                Sans rayon
                            </h3>
                            <span class="sa-genre-rayon-group__count"><?php echo count($sans_rayon_ids); ?></span>
                        </header>
                        <ul class="sa-cat-genre-list">
                            <?php foreach ($sans_rayon_ids as $gid):
                                include __DIR__ . '/_partials/genre-row.php';
                            endforeach; ?>
                        </ul>
                    </section>
                    <?php endif; ?>

                    <?php if (!$any_group && empty($sans_rayon_ids)): ?>
                        <div class="sa-users-empty sa-cat-rayons-empty">
                            <p><strong>Aucun genre lié à un rayon.</strong></p>
                            <p>Modifiez un genre pour lui associer des rayons.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="sa-cat-modal sa-cat-modal--genre" id="saModalGenre" role="dialog" aria-modal="true" aria-labelledby="saModalGenreTitle" <?php echo $row_edit_g ? '' : 'hidden'; ?><?php echo $row_edit_g ? ' data-sa-cat-close-reset-href="genres-catalogue.php"' : ''; ?>>
        <div class="sa-cat-modal__backdrop" data-sa-cat-close-modal tabindex="-1" aria-hidden="true"></div>
        <div class="sa-cat-modal__panel">
            <div class="sa-cat-modal__header">
                <div class="sa-cat-modal__header-text">
                    <h2 id="saModalGenreTitle"><?php echo $row_edit_g ? 'Modifier le genre' : 'Nouveau genre'; ?></h2>
                    <?php if ($row_edit_g): ?>
                        <p class="sa-cat-modal__subtitle"><?php echo htmlspecialchars((string) $row_edit_g['nom'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php else: ?>
                        <p class="sa-cat-modal__subtitle">Renseignez le libellé et associez au moins un rayon.</p>
                    <?php endif; ?>
                </div>
                <button type="button" class="sa-cat-modal__close" data-sa-cat-close-modal aria-label="Fermer"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="sa-cat-modal__body sa-cat-modal__body--genre">
                <?php include __DIR__ . '/_partials/genre-form.php'; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
    (function () {
        var OPEN = 'sa-cat-modal-open';
        function openModal(el) {
            if (!el) return;
            el.removeAttribute('hidden');
            document.body.classList.add(OPEN);
        }
        function closeModal(el) {
            if (!el) return;
            var reset = el.getAttribute('data-sa-cat-close-reset-href');
            if (reset) {
                window.location.href = reset;
                return;
            }
            el.setAttribute('hidden', 'hidden');
            document.body.classList.remove(OPEN);
        }
        document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-open-modal');
                openModal(document.getElementById(id));
            });
        });
        document.querySelectorAll('[data-sa-cat-close-modal]').forEach(function (node) {
            node.addEventListener('click', function () {
                var m = node.closest('.sa-cat-modal');
                if (m) closeModal(m);
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var vis = document.querySelector('.sa-cat-modal:not([hidden])');
            if (vis) closeModal(vis);
        });
        document.querySelectorAll('.sa-genre-rayon-pick input[type="checkbox"]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var lab = cb.closest('.sa-genre-rayon-pick');
                if (lab) lab.classList.toggle('is-checked', cb.checked);
            });
        });
        var imgInput = document.querySelector('[data-sa-genre-image-input]');
        var imgPreview = document.getElementById('saGenreImagePreview');
        if (imgInput && imgPreview) {
            imgInput.addEventListener('change', function () {
                var file = imgInput.files && imgInput.files[0];
                if (!file || !file.type.match(/^image\//)) {
                    return;
                }
                var url = URL.createObjectURL(file);
                imgPreview.innerHTML = '<img src="' + url + '" alt="" width="72" height="72" decoding="async">';
            });
        }
    })();
    </script>
</body>

</html>
