<?php
/**
 * Sous-catégories plateforme — liées à un rayon (categories_generales), visibles des vendeurs.
 */
require_once __DIR__ . '/../includes/require_login.php';

if (ob_get_level() === 0) {
    ob_start();
}
require_once dirname(__DIR__, 2) . '/includes/flash_toast.php';

require_once dirname(__DIR__, 2) . '/models/model_categories.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/models/model_produits_sous_categories.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_categories.php';

$sa_id = (int) ($_SESSION['super_admin_id'] ?? 0);
$flash_ok = '';
$flash_err = '';
$upload_root = dirname(__DIR__, 2) . '/upload/';

/**
 * Redirection liste avec ancre rayon (conserve la position dans la page).
 */
function sa_sc_redirect_list_ok($anchor_id = '') {
    $anchor_id = trim((string) $anchor_id);
    $url = '/super_admin/parametres/sous-categories-catalogue.php?ok=1';
    if ($anchor_id !== '' && preg_match('/^sa-sc-rayon-[a-z0-9-]+$/i', $anchor_id)) {
        $url .= '#' . $anchor_id;
    }
    http_redirect_safe($url);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($tok)) {
        $flash_err = 'Jeton de sécurité invalide.';
    } elseif (!function_exists('categories_generales_table_exists') || !categories_generales_table_exists()
        || !categories_has_categorie_generale_id_column()) {
        $flash_err = 'Structure catalogue incomplète. Exécutez les migrations catégories / rayons.';
    } else {
        if (isset($_POST['create_sc'])) {
            $nom = trim((string) ($_POST['sc_nom'] ?? ''));
            $d = trim((string) ($_POST['sc_description'] ?? ''));
            $gid = (int) ($_POST['sc_categorie_generale_id'] ?? 0);
            if ($nom === '') {
                $flash_err = 'Le nom est obligatoire.';
            } elseif ($gid <= 0 || !get_categorie_generale_by_id($gid)) {
                $flash_err = 'Sélectionnez un rayon valide.';
            } else {
                $img = null;
                if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? 0) === UPLOAD_ERR_OK) {
                    $up = upload_categorie_image($_FILES);
                    if ($up) {
                        $img = $up;
                    } else {
                        $flash_err = 'Image non valide (JPEG, PNG, GIF, WebP).';
                    }
                }
                if ($flash_err === '') {
                    $tailles_json = function_exists('categorie_tailles_build_from_post')
                        ? categorie_tailles_build_from_post($_POST) : null;
                    $new_id = plateforme_create_sous_categorie($nom, $d !== '' ? $d : null, $img, $gid, 0, $tailles_json);
                    if ($new_id) {
                        if (function_exists('plateforme_ensure_liaison_rows_for_legacy_categories')) {
                            plateforme_ensure_liaison_rows_for_legacy_categories();
                        }
                        super_admin_log_action($sa_id, 'sous_categorie_creee', 'categories', (int) $new_id, $nom);
                        sa_sc_redirect_list_ok($gid > 0 ? 'sa-sc-rayon-' . $gid : '');
                    }
                    $flash_err = 'Impossible d’enregistrer (nom déjà utilisé dans ce rayon ou erreur).';
                }
            }
        } elseif (isset($_POST['update_sc'])) {
            $id = (int) ($_POST['sc_id'] ?? 0);
            $nom = trim((string) ($_POST['sc_nom'] ?? ''));
            $d = trim((string) ($_POST['sc_description'] ?? ''));
            $gid = (int) ($_POST['sc_categorie_generale_id'] ?? 0);
            $row = $id > 0 ? get_categorie_by_id($id) : false;
            if (!$row) {
                $flash_err = 'Sous-catégorie introuvable.';
            } elseif (!categorie_est_sous_categorie_plateforme_racine($row)) {
                $flash_err = 'Cette fiche n’est pas une sous-catégorie plateforme.';
            } elseif ($nom === '') {
                $flash_err = 'Le nom est obligatoire.';
            } elseif ($gid <= 0 || !get_categorie_generale_by_id($gid)) {
                $flash_err = 'Sélectionnez un rayon valide.';
            } else {
                $img = (string) ($row['image'] ?? '');
                if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? 0) === UPLOAD_ERR_OK) {
                    $up = upload_categorie_image($_FILES);
                    if ($up) {
                        if ($img !== '' && is_file($upload_root . str_replace('\\', '/', $img))) {
                            @unlink($upload_root . str_replace('\\', '/', $img));
                        }
                        $img = $up;
                    } else {
                        $flash_err = 'Image non valide.';
                    }
                }
                $tailles_for_update = null;
                if ($flash_err === '' && function_exists('categories_table_has_column') && categories_table_has_column('tailles')
                    && function_exists('categorie_tailles_build_from_post')) {
                    $tailles_for_update = categorie_tailles_build_from_post($_POST);
                    if ($tailles_for_update === null) {
                        $tailles_for_update = '';
                    }
                }
                if ($flash_err === '' && plateforme_update_sous_categorie($id, $nom, $d !== '' ? $d : null, $img !== '' ? $img : null, $gid, null, $tailles_for_update)) {
                    if (function_exists('plateforme_ensure_liaison_rows_for_legacy_categories')) {
                        plateforme_ensure_liaison_rows_for_legacy_categories();
                    }
                    super_admin_log_action($sa_id, 'sous_categorie_modifiee', 'categories', $id, $nom);
                    sa_sc_redirect_list_ok($gid > 0 ? 'sa-sc-rayon-' . $gid : '');
                } elseif ($flash_err === '') {
                    $flash_err = 'Modification impossible (nom déjà utilisé dans ce rayon ou erreur).';
                }
            }
        } elseif (isset($_POST['delete_sc'])) {
            $id = (int) ($_POST['sc_id'] ?? 0);
            $row = $id > 0 ? get_categorie_by_id($id) : false;
            if (!$row) {
                $flash_err = 'Sous-catégorie introuvable.';
            } elseif (!categorie_est_sous_categorie_plateforme_racine($row)) {
                $flash_err = 'Cette fiche n’est pas une sous-catégorie plateforme.';
            } elseif (function_exists('plateforme_sous_categorie_count_enfants') && plateforme_sous_categorie_count_enfants($id) > 0) {
                $flash_err = 'Impossible de supprimer : des sous-sous-catégories sont rattachées.';
            } elseif (function_exists('count_produits_referencing_sous_categorie') && count_produits_referencing_sous_categorie($id) > 0) {
                $flash_err = 'Impossible de supprimer : des produits utilisent encore cette sous-catégorie.';
            } else {
                if (produits_sous_categories_table_exists()) {
                    global $db;
                    try {
                        $db->prepare('DELETE FROM `produits_sous_categories` WHERE `categorie_id` = :c')->execute(['c' => $id]);
                    } catch (PDOException $e) {
                    }
                }
                if (delete_categorie($id)) {
                    $img = (string) ($row['image'] ?? '');
                    if ($img !== '' && is_file($upload_root . str_replace('\\', '/', $img))) {
                        @unlink($upload_root . str_replace('\\', '/', $img));
                    }
                    super_admin_log_action($sa_id, 'sous_categorie_supprimee', 'categories', $id, (string) ($row['nom'] ?? ''));
                    $ret_anchor = trim((string) ($_POST['sc_return_anchor'] ?? ''));
                    sa_sc_redirect_list_ok($ret_anchor);
                }
                $flash_err = 'Suppression impossible.';
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $flash_ok = 'Enregistrement effectué.';
}

$sc_list = get_plateforme_sous_categories_for_form();
$edit_sc = isset($_GET['edit_sc']) ? (int) $_GET['edit_sc'] : 0;
$row_edit = $edit_sc > 0 ? get_categorie_by_id($edit_sc) : false;
if ($row_edit && !categorie_est_sous_categorie_plateforme_racine($row_edit)) {
    $row_edit = false;
    $edit_sc = 0;
}
$rayons = (function_exists('categories_generales_table_exists') && categories_generales_table_exists())
    ? get_general_categories_ordered() : [];
$edit_rayon = 0;
if ($row_edit) {
    $edit_rayon = (int) ($row_edit['categorie_generale_id'] ?? 0);
    if ($edit_rayon <= 0) {
        $rids = plateforme_get_rayons_ids_for_categorie((int) $row_edit['id']);
        $edit_rayon = (int) ($rids[0] ?? 0);
    }
}
$csrf = super_admin_csrf_token();
$table_ok = !empty($rayons) && categories_has_categorie_generale_id_column();
$sc_modal_close_href = 'sous-categories-catalogue.php';
if ($row_edit && $edit_rayon > 0) {
    $sc_modal_close_href .= '#sa-sc-rayon-' . $edit_rayon;
} elseif (isset($_GET['rayon'])) {
    $rayon_get = (int) $_GET['rayon'];
    if ($rayon_get > 0) {
        $sc_modal_close_href .= '#sa-sc-rayon-' . $rayon_get;
    }
}
$sc_total = count($sc_list);

$sc_by_rayon = [];
foreach ($rayons as $r) {
    $rid = (int) ($r['id'] ?? 0);
    if ($rid <= 0) {
        continue;
    }
    $sc_by_rayon[$rid] = [
        'nom' => (string) ($r['nom'] ?? ''),
        'sort' => (int) ($r['sort_ordre'] ?? 0),
        'items' => [],
    ];
}
$sc_orphans = [];

foreach ($sc_list as $sc) {
    $scid = (int) ($sc['id'] ?? 0);
    if ($scid <= 0) {
        continue;
    }
    $rayon_ids = [];
    $gid = (int) ($sc['categorie_generale_id'] ?? 0);
    if ($gid > 0) {
        $rayon_ids[$gid] = true;
    }
    if (function_exists('plateforme_get_rayons_ids_for_categorie')) {
        foreach (plateforme_get_rayons_ids_for_categorie($scid) as $rid_raw) {
            $rid_raw = (int) $rid_raw;
            if ($rid_raw > 0) {
                $rayon_ids[$rid_raw] = true;
            }
        }
    }
    if (empty($rayon_ids)) {
        $sc_orphans[] = $sc;
        continue;
    }
    foreach (array_keys($rayon_ids) as $rid) {
        if (isset($sc_by_rayon[$rid])) {
            $sc_by_rayon[$rid]['items'][$scid] = $sc;
        }
    }
}

uasort($sc_by_rayon, function ($a, $b) {
    $sa = (int) ($a['sort'] ?? 0);
    $sb = (int) ($b['sort'] ?? 0);
    if ($sa !== $sb) {
        return $sa <=> $sb;
    }
    return strcasecmp((string) ($a['nom'] ?? ''), (string) ($b['nom'] ?? ''));
});

foreach ($sc_by_rayon as &$sc_grp) {
    $items = array_values($sc_grp['items']);
    usort($items, function ($a, $b) {
        return strcasecmp((string) ($a['nom'] ?? ''), (string) ($b['nom'] ?? ''));
    });
    $sc_grp['items'] = $items;
}
unset($sc_grp);

usort($sc_orphans, function ($a, $b) {
    return strcasecmp((string) ($a['nom'] ?? ''), (string) ($b['nom'] ?? ''));
});
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sous-catégories catalogue — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-parametres.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-param-hub-page sa-cat-page sa-sc-page<?php echo $row_edit ? ' sa-cat-modal-open' : ''; ?>">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-param-shell sa-cat-shell">
        <a class="sa-cat-back" href="index.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a>

        <header class="sa-param-hero sa-cat-sub-hero" aria-labelledby="sa-sc-title">
            <div class="sa-param-hero__inner">
                <div class="sa-param-hero__intro">
                    <h1 class="sa-param-hero__title" id="sa-sc-title">Sous-catégories</h1>
                    <p class="sa-param-hero__sub">Rubriques liées à un rayon · choix multiples pour les vendeurs</p>
                </div>
                <div class="sa-param-hero__kpis" role="group" aria-label="Statistiques sous-catégories">
                    <div class="sa-param-hero-kpi">
                        <span class="sa-param-hero-kpi__label">Rubriques</span>
                        <span class="sa-param-hero-kpi__value"><?php echo (int) $sc_total; ?></span>
                    </div>
                    <div class="sa-param-hero-kpi">
                        <span class="sa-param-hero-kpi__label">Rayons</span>
                        <span class="sa-param-hero-kpi__value"><?php echo count($rayons); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($flash_ok !== ''): ?>
            <div class="sa-cat-alert sa-cat-alert--ok" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($flash_ok, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($flash_err !== ''): ?>
            <div class="sa-cat-alert sa-cat-alert--err" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($flash_err, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!$table_ok): ?>
            <div class="sa-cat-migrate-banner" role="note"><strong>Configuration requise.</strong> Vérifiez les tables <code>categories_generales</code> et la colonne <code>categories.categorie_generale_id</code>.</div>
        <?php endif; ?>

        <section class="sa-users-panel sa-sc-panel" aria-labelledby="sa-sc-panel-title">
            <div class="sa-users-panel__head sa-cat-rayons-panel__head sa-sc-panel__sticky-head">
                <div>
                    <h2 id="sa-sc-panel-title">
                        <i class="fas fa-diagram-project" aria-hidden="true"></i>
                        Par rayon
                    </h2>
                    <p class="sa-users-panel__meta">Chaque bloc regroupe les sous-catégories du même rayon</p>
                </div>
                <div class="sa-cat-rayons-panel__toolbar">
                    <a class="sa-cat-btn sa-cat-btn--ghost sa-cat-rayons-panel__genres" href="categories-catalogue.php">
                        <i class="fas fa-sitemap" aria-hidden="true"></i>
                        Rayons
                    </a>
                    <a class="sa-cat-btn sa-cat-btn--ghost" href="sous-sous-categories-catalogue.php">
                        <i class="fas fa-folder-tree" aria-hidden="true"></i>
                        Sous-sous-catégories
                    </a>
                    <button type="button" class="sa-cat-btn sa-cat-btn--primary sa-cat-rayons-panel__add" id="btnOpenModalSc" data-open-modal="saModalSc" <?php echo !$table_ok || $row_edit ? 'disabled' : ''; ?>>
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        Ajouter
                    </button>
                </div>
            </div>

            <?php if (empty($sc_list)): ?>
                <div class="sa-users-empty sa-cat-rayons-empty">
                    <i class="fas fa-layer-group" aria-hidden="true"></i>
                    <p><strong>Aucune sous-catégorie.</strong></p>
                    <p>Créez la première rubrique ou exécutez la migration si des données existent déjà.</p>
                </div>
            <?php else: ?>
                <div class="sa-sc-rayon-stack">
                    <?php
                    $block_i = 0;
                    foreach ($sc_by_rayon as $rid => $grp):
                        if (empty($grp['items'])) {
                            continue;
                        }
                        $block_i++;
                        $grp_nom = (string) ($grp['nom'] ?? '');
                        $nb = count($grp['items']);
                    ?>
                    <section class="sa-sc-rayon-block sa-sc-rayon-block--tone-<?php echo (int) (($block_i - 1) % 4) + 1; ?>" aria-labelledby="sa-sc-rayon-<?php echo (int) $rid; ?>">
                        <header class="sa-sc-rayon-block__head">
                            <div class="sa-sc-rayon-block__title-wrap">
                                <span class="sa-sc-rayon-block__badge" aria-hidden="true"><?php echo (int) $nb; ?></span>
                                <h3 class="sa-sc-rayon-block__title" id="sa-sc-rayon-<?php echo (int) $rid; ?>"><?php echo htmlspecialchars($grp_nom, ENT_QUOTES, 'UTF-8'); ?></h3>
                            </div>
                            <span class="sa-sc-rayon-block__meta"><?php echo (int) $nb; ?> sous-catégorie<?php echo $nb > 1 ? 's' : ''; ?></span>
                        </header>
                        <div class="sa-sc-tiles">
                            <?php foreach ($grp['items'] as $sc):
                                $sc_return_anchor = 'sa-sc-rayon-' . (int) $rid;
                                include __DIR__ . '/_partials/sous-cat-tile.php';
                            endforeach; ?>
                        </div>
                    </section>
                    <?php endforeach; ?>

                    <?php if (!empty($sc_orphans)): ?>
                    <section class="sa-sc-rayon-block sa-sc-rayon-block--orphan" aria-labelledby="sa-sc-rayon-orphan">
                        <header class="sa-sc-rayon-block__head">
                            <div class="sa-sc-rayon-block__title-wrap">
                                <span class="sa-sc-rayon-block__badge" aria-hidden="true">!</span>
                                <h3 class="sa-sc-rayon-block__title" id="sa-sc-rayon-orphan">Sans rayon</h3>
                            </div>
                            <span class="sa-sc-rayon-block__meta">À rattacher</span>
                        </header>
                        <div class="sa-sc-tiles">
                            <?php foreach ($sc_orphans as $sc):
                                $sc_return_anchor = 'sa-sc-rayon-orphan';
                                include __DIR__ . '/_partials/sous-cat-tile.php';
                            endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="sa-cat-modal sa-cat-modal--genre" id="saModalSc" role="dialog" aria-modal="true" aria-labelledby="saModalScTitle" <?php echo $row_edit ? '' : 'hidden'; ?><?php echo $row_edit ? ' data-sa-cat-close-reset-href="' . htmlspecialchars($sc_modal_close_href, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
        <div class="sa-cat-modal__backdrop" data-sa-cat-close-modal tabindex="-1" aria-hidden="true"></div>
        <div class="sa-cat-modal__panel">
            <div class="sa-cat-modal__header">
                <div class="sa-cat-modal__header-text">
                    <h2 id="saModalScTitle"><?php echo $row_edit ? 'Modifier la sous-catégorie' : 'Nouvelle sous-catégorie'; ?></h2>
                    <?php if ($row_edit): ?>
                        <p class="sa-cat-modal__subtitle"><?php echo htmlspecialchars((string) $row_edit['nom'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php else: ?>
                        <p class="sa-cat-modal__subtitle">Nom, visuel et rayon parent</p>
                    <?php endif; ?>
                </div>
                <button type="button" class="sa-cat-modal__close" data-sa-cat-close-modal aria-label="Fermer"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="sa-cat-modal__body sa-cat-modal__body--genre">
                <?php include __DIR__ . '/_partials/sous-cat-form.php'; ?>
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
                openModal(document.getElementById(btn.getAttribute('data-open-modal')));
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
        document.querySelectorAll('.sa-sc-rayon-select input[type="radio"]').forEach(function (rb) {
            rb.addEventListener('change', function () {
                document.querySelectorAll('.sa-sc-rayon-select').forEach(function (lab) {
                    lab.classList.remove('is-checked');
                });
                var lab = rb.closest('.sa-sc-rayon-select');
                if (lab && rb.checked) lab.classList.add('is-checked');
            });
        });
        var imgInput = document.querySelector('[data-sa-sc-image-input]');
        var imgPreview = document.getElementById('saScImagePreview');
        if (imgInput && imgPreview) {
            imgInput.addEventListener('change', function () {
                var file = imgInput.files && imgInput.files[0];
                if (!file || !file.type.match(/^image\//)) return;
                imgPreview.innerHTML = '<img src="' + URL.createObjectURL(file) + '" alt="" width="174" height="174" decoding="async">';
            });
        }

        function saScStickyHeadOffset() {
            var head = document.querySelector('.sa-sc-panel__sticky-head');
            return head ? head.offsetHeight + 10 : 0;
        }

        function saScScrollToAnchor(anchorId, instant) {
            if (!anchorId) return;
            var el = document.getElementById(anchorId);
            if (!el) return;
            var y = el.getBoundingClientRect().top + window.pageYOffset - saScStickyHeadOffset();
            window.scrollTo({ top: Math.max(0, y), behavior: instant ? 'auto' : 'smooth' });
        }

        function saScResolveListAnchor() {
            var hash = window.location.hash ? window.location.hash.replace(/^#/, '') : '';
            if (hash && document.getElementById(hash)) return hash;
            var m = window.location.search.match(/[?&]rayon=(\d+)/);
            if (m) {
                var fromQuery = 'sa-sc-rayon-' + m[1];
                if (document.getElementById(fromQuery)) return fromQuery;
            }
            return '';
        }

        function saScRestoreListPosition() {
            var anchor = saScResolveListAnchor();
            if (anchor) {
                saScScrollToAnchor(anchor, true);
                requestAnimationFrame(function () { saScScrollToAnchor(anchor, true); });
                setTimeout(function () { saScScrollToAnchor(anchor, true); }, 120);
            }
        }

        document.querySelectorAll('.sa-sc-tile__action[href*="edit_sc="]').forEach(function (link) {
            link.addEventListener('click', function () {
                var block = link.closest('.sa-sc-rayon-block');
                if (!block) return;
                var labelled = block.getAttribute('aria-labelledby');
                if (labelled) {
                    try { sessionStorage.setItem('sa_sc_list_anchor', labelled); } catch (e) {}
                }
            });
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', saScRestoreListPosition);
        } else {
            saScRestoreListPosition();
        }
        window.addEventListener('load', saScRestoreListPosition);
    })();
    </script>
</body>
</html>
