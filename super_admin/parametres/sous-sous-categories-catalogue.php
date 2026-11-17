<?php
/**
 * Sous-sous-catégories plateforme — rattachées à une sous-catégorie.
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

function sa_ssc_redirect_list_ok($anchor_id = '') {
    $anchor_id = trim((string) $anchor_id);
    $url = '/super_admin/parametres/sous-sous-categories-catalogue.php?ok=1';
    if ($anchor_id !== '' && preg_match('/^sa-ssc-parent-[0-9]+$/i', $anchor_id)) {
        $url .= '#' . $anchor_id;
    }
    http_redirect_safe($url);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tok = $_POST['csrf_token'] ?? '';
    if (!super_admin_csrf_valid($tok)) {
        $flash_err = 'Jeton de sécurité invalide.';
    } elseif (!categories_table_has_column('parent_id') || !categories_has_categorie_generale_id_column()) {
        $flash_err = 'Colonne parent_id requise. Exécutez la migration hiérarchie catégories.';
    } else {
        if (isset($_POST['auto_setup_ssc'])) {
            $auto = plateforme_ssc_auto_setup_vetements();
            if ($auto['created'] > 0) {
                super_admin_log_action($sa_id, 'sous_sous_categorie_auto', 'categories', 0, implode(' ; ', $auto['items']));
                flash_toast_push('success', $auto['created'] . ' grille(s) de tailles créée(s) pour les vêtements et chaussures.');
            } else {
                flash_toast_push('info', 'Tout est déjà configuré : aucune grille à ajouter.');
            }
            http_redirect_safe('/super_admin/parametres/sous-sous-categories-catalogue.php');
        } elseif (isset($_POST['create_ssc'])) {
            $d = trim((string) ($_POST['ssc_description'] ?? ''));
            /* Une même grille peut être ajoutée à plusieurs sous-catégories d'un coup */
            $pids = [];
            foreach ((array) ($_POST['ssc_parent_ids'] ?? []) as $raw_pid) {
                $raw_pid = (int) $raw_pid;
                if ($raw_pid > 0) {
                    $pids[$raw_pid] = true;
                }
            }
            $pids = array_keys($pids);
            $tranche_age = function_exists('categorie_tranche_age_from_post')
                ? categorie_tranche_age_from_post($_POST) : null;
            $nom = function_exists('categorie_ssc_nom_from_tranche_type') && $tranche_age !== null
                ? categorie_ssc_nom_from_tranche_type($tranche_age) : '';
            $tailles_json = function_exists('categorie_tailles_build_from_post')
                ? categorie_tailles_build_from_post($_POST, 'ssc') : null;
            if ($tranche_age === null || $nom === '') {
                $flash_err = 'Sélectionnez un type de tailles.';
            } elseif ($tailles_json === null) {
                $flash_err = 'Cochez au moins une taille.';
            } elseif ($pids === []) {
                $flash_err = 'Cochez au moins une sous-catégorie.';
            } else {
                $ok_n = 0;
                $deja = [];
                $last_pid = 0;
                foreach ($pids as $pid) {
                    $parent = get_categorie_by_id($pid);
                    if (!$parent || !categorie_est_sous_categorie_plateforme_racine($parent)) {
                        continue;
                    }
                    if (!plateforme_sous_sous_categorie_nom_disponible($nom, $pid, 0)) {
                        $deja[] = (string) ($parent['nom'] ?? '');
                        continue;
                    }
                    $new_id = plateforme_create_sous_sous_categorie($nom, $d !== '' ? $d : null, null, $pid, $tailles_json, $tranche_age);
                    if ($new_id) {
                        $ok_n++;
                        $last_pid = $pid;
                        super_admin_log_action($sa_id, 'sous_sous_categorie_creee', 'categories', (int) $new_id, $nom);
                    }
                }
                if ($ok_n > 0) {
                    $msg = $ok_n . ' grille(s) « ' . $nom . ' » ajoutée(s).';
                    if ($deja) {
                        $msg .= ' Déjà présente pour : ' . implode(', ', $deja) . '.';
                    }
                    flash_toast_push('success', $msg);
                    http_redirect_safe('/super_admin/parametres/sous-sous-categories-catalogue.php'
                        . ($ok_n === 1 ? '#sa-ssc-parent-' . $last_pid : ''));
                }
                $flash_err = $deja
                    ? 'Cette grille existe déjà pour : ' . implode(', ', $deja) . '.'
                    : 'Impossible d’enregistrer.';
            }
        } elseif (isset($_POST['update_ssc'])) {
            $id = (int) ($_POST['ssc_id'] ?? 0);
            $d = trim((string) ($_POST['ssc_description'] ?? ''));
            $pid = (int) ($_POST['ssc_parent_id'] ?? 0);
            $row = $id > 0 ? get_categorie_by_id($id) : false;
            $parent = $pid > 0 ? get_categorie_by_id($pid) : false;
            $tranche_age_update = function_exists('categorie_tranche_age_from_post')
                ? categorie_tranche_age_from_post($_POST) : null;
            $nom = function_exists('categorie_ssc_nom_from_tranche_type') && $tranche_age_update !== null
                ? categorie_ssc_nom_from_tranche_type($tranche_age_update) : '';
            if (!$row || !categorie_est_sous_sous_categorie_plateforme($row)) {
                $flash_err = 'Sous-sous-catégorie introuvable.';
            } elseif ($tranche_age_update === null || $nom === '') {
                $flash_err = 'Sélectionnez un type de tailles.';
            } elseif (!$parent || !categorie_est_sous_categorie_plateforme_racine($parent)) {
                $flash_err = 'Sous-catégorie parente invalide.';
            } else {
                $img = (string) ($row['image'] ?? '');
                $tailles_for_update = null;
                $tranche_for_update = null;
                if (function_exists('categories_table_has_column') && categories_table_has_column('tailles')
                    && function_exists('categorie_tailles_build_from_post')) {
                    $tailles_for_update = categorie_tailles_build_from_post($_POST, 'ssc');
                    if ($tailles_for_update === null) {
                        $tailles_for_update = '';
                    }
                }
                if (function_exists('categories_table_has_column') && categories_table_has_column('tranche_age')) {
                    $tranche_for_update = function_exists('categorie_tranche_age_from_post')
                        ? categorie_tranche_age_from_post($_POST) : null;
                    if ($tranche_for_update === null) {
                        $tranche_for_update = '';
                    }
                }
                if ($flash_err === '' && plateforme_update_sous_sous_categorie($id, $nom, $d !== '' ? $d : null, $img !== '' ? $img : null, $pid, $tailles_for_update, $tranche_for_update)) {
                    super_admin_log_action($sa_id, 'sous_sous_categorie_modifiee', 'categories', $id, $nom);
                    sa_ssc_redirect_list_ok('sa-ssc-parent-' . $pid);
                } elseif ($flash_err === '') {
                    $flash_err = 'Impossible de mettre à jour.';
                }
            }
        } elseif (isset($_POST['delete_ssc'])) {
            $id = (int) ($_POST['ssc_id'] ?? 0);
            $anchor = trim((string) ($_POST['ssc_return_anchor'] ?? ''));
            $row = $id > 0 ? get_categorie_by_id($id) : false;
            if (!$row || !categorie_est_sous_sous_categorie_plateforme($row)) {
                $flash_err = 'Sous-sous-catégorie introuvable.';
            } elseif (function_exists('count_produits_referencing_sous_categorie') && count_produits_referencing_sous_categorie($id) > 0) {
                $flash_err = 'Impossible de supprimer : des produits utilisent encore cette rubrique.';
            } elseif (!plateforme_delete_sous_sous_categorie($id)) {
                $flash_err = 'Suppression impossible.';
            } else {
                if (produits_sous_categories_table_exists()) {
                    global $db;
                    try {
                        $db->prepare('DELETE FROM `produits_sous_categories` WHERE `categorie_id` = :c')->execute(['c' => $id]);
                    } catch (PDOException $e) {
                    }
                }
                super_admin_log_action($sa_id, 'sous_sous_categorie_supprimee', 'categories', $id, (string) ($row['nom'] ?? ''));
                sa_ssc_redirect_list_ok($anchor);
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $flash_ok = 'Enregistrement effectué.';
}

$ssc_list = get_plateforme_sous_sous_categories_for_form();
$parents_sc = get_plateforme_sous_categories_for_form();
$edit_ssc = isset($_GET['edit_ssc']) ? (int) $_GET['edit_ssc'] : 0;
$row_edit = $edit_ssc > 0 ? get_categorie_by_id($edit_ssc) : false;
if ($row_edit && !categorie_est_sous_sous_categorie_plateforme($row_edit)) {
    $row_edit = false;
    $edit_ssc = 0;
}
$edit_parent = 0;
if ($row_edit) {
    $edit_parent = (int) ($row_edit['parent_id'] ?? 0);
}
if ($edit_parent <= 0 && isset($_GET['parent'])) {
    $edit_parent = (int) $_GET['parent'];
}
$csrf = super_admin_csrf_token();
$table_ok = categories_table_has_column('parent_id') && !empty($parents_sc);
$ssc_modal_close_href = 'sous-sous-categories-catalogue.php';
if ($row_edit && $edit_parent > 0) {
    $ssc_modal_close_href .= '#sa-ssc-parent-' . $edit_parent;
}
$ssc_total = count($ssc_list);

$ssc_by_parent = [];
foreach ($parents_sc as $p) {
    $pid = (int) ($p['id'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    $ssc_by_parent[$pid] = [
        'nom' => (string) ($p['nom'] ?? ''),
        'parent' => $p,
        'items' => [],
    ];
}
foreach ($ssc_list as $ssc) {
    $pid = (int) ($ssc['parent_id'] ?? 0);
    if ($pid > 0 && isset($ssc_by_parent[$pid])) {
        $ssc_by_parent[$pid]['items'][] = $ssc;
    }
}
?>
<?php
/* Rayon de chaque sous-catégorie + grilles rattachées hors vêtements / chaussures (à vérifier) */
$ssc_rayon_by_parent = [];
$ssc_parents_by_rayon = [];
foreach ($parents_sc as $p) {
    $pid = (int) ($p['id'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    $rayon = trim((string) ($p['generale_nom'] ?? '')) ?: 'Autres';
    $ssc_rayon_by_parent[$pid] = $rayon;
    $ssc_parents_by_rayon[$rayon][] = $p;
}
$ssc_auto_todo = 0;
foreach ($parents_sc as $p) {
    foreach (plateforme_ssc_types_auto_pour_sous_categorie($p['nom'] ?? '', $p['generale_nom'] ?? '') as $type) {
        if (plateforme_sous_sous_categorie_nom_disponible(categorie_ssc_nom_from_tranche_type($type), (int) $p['id'], 0)) {
            $ssc_auto_todo++;
        }
    }
}
$ssc_parents_avec = 0;
foreach ($ssc_by_parent as $grp) {
    if (!empty($grp['items'])) {
        $ssc_parents_avec++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tailles (sous-sous-catégories) — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-parametres.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-ssc.css?v=<?php echo (int) @filemtime(dirname(__DIR__, 2) . '/css/super-admin-ssc.css'); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-param-hub-page sa-ssc-v2<?php echo $row_edit ? ' sa-cat-modal-open' : ''; ?>">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell ssc-shell">
        <a class="ssc-back" href="index.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Paramètres</a>

        <header class="ssc-hero">
            <div class="ssc-hero__text">
                <h1>Tailles par sous-catégorie</h1>
                <p>Une grille de tailles rend la taille <strong>obligatoire</strong> pour les vendeurs dans cette sous-catégorie.</p>
            </div>
            <dl class="ssc-hero__kpis">
                <div><dt>Grilles</dt><dd><?php echo (int) $ssc_total; ?></dd></div>
                <div><dt>Sous-catégories équipées</dt><dd><?php echo (int) $ssc_parents_avec; ?></dd></div>
            </dl>
        </header>

        <?php if ($flash_ok !== ''): ?>
            <div class="ssc-alert ssc-alert--ok" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($flash_ok, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($flash_err !== ''): ?>
            <div class="ssc-alert ssc-alert--err" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($flash_err, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!categories_table_has_column('parent_id')): ?>
            <div class="ssc-alert ssc-alert--err" role="note"><strong>Migration requise.</strong> Activez la colonne <code>categories.parent_id</code>.</div>
        <?php elseif (empty($parents_sc)): ?>
            <div class="ssc-alert ssc-alert--info" role="note">Créez d’abord des sous-catégories dans <a href="sous-categories-catalogue.php">Sous-catégories</a>.</div>
        <?php endif; ?>

        <?php if ($table_ok && $ssc_auto_todo > 0): ?>
        <form method="post" action="sous-sous-categories-catalogue.php" class="ssc-auto">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="ssc-auto__icon" aria-hidden="true"><i class="fas fa-wand-magic-sparkles"></i></span>
            <p class="ssc-auto__text">
                <strong><?php echo (int) $ssc_auto_todo; ?> grille(s) manquante(s)</strong> pour les vêtements et chaussures
                <span>(T-shirts → XS…3XL, Chaussures → 35…46, Enfants → 2A…16A…)</span>
            </p>
            <button type="submit" name="auto_setup_ssc" value="1" class="ssc-btn ssc-btn--primary">
                <i class="fas fa-bolt" aria-hidden="true"></i> Configurer automatiquement
            </button>
        </form>
        <?php endif; ?>

        <section class="ssc-panel" aria-label="Grilles de tailles">
            <div class="ssc-toolbar">
                <label class="ssc-search">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="sscListSearch" placeholder="Rechercher une sous-catégorie…" autocomplete="off">
                </label>
                <button type="button" class="ssc-btn ssc-btn--primary" id="btnOpenModalSsc" data-open-modal="saModalSsc" <?php echo !$table_ok || $row_edit ? 'disabled' : ''; ?>>
                    <i class="fas fa-plus" aria-hidden="true"></i> <span>Ajouter une grille</span>
                </button>
            </div>

            <?php if (empty($ssc_list)): ?>
                <div class="ssc-empty">
                    <i class="fas fa-ruler" aria-hidden="true"></i>
                    <p><strong>Aucune grille de tailles.</strong></p>
                    <p>Utilisez « Configurer automatiquement » ou « Ajouter une grille ».</p>
                </div>
            <?php else: ?>
                <div class="ssc-grid" id="sscGrid">
                    <?php foreach ($ssc_by_parent as $pid => $grp):
                        if (empty($grp['items'])) {
                            continue;
                        }
                        $rayon = $ssc_rayon_by_parent[$pid] ?? '';
                        $hors_mode = plateforme_ssc_types_auto_pour_sous_categorie('vêtement', $rayon) === [];
                    ?>
                    <article class="ssc-card<?php echo $hors_mode ? ' ssc-card--warn' : ''; ?>" id="sa-ssc-parent-<?php echo (int) $pid; ?>"
                        data-ssc-search="<?php echo htmlspecialchars(mb_strtolower($grp['nom'] . ' ' . $rayon, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>">
                        <header class="ssc-card__head">
                            <div>
                                <h2 class="ssc-card__title"><?php echo htmlspecialchars((string) $grp['nom'], ENT_QUOTES, 'UTF-8'); ?></h2>
                                <p class="ssc-card__rayon"><?php echo htmlspecialchars($rayon, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <?php if ($hors_mode): ?>
                                <span class="ssc-card__warn" title="Ce rayon n’est pas un rayon vêtements / chaussures : les vendeurs devront quand même choisir une taille.">
                                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Hors vêtements
                                </span>
                            <?php endif; ?>
                        </header>
                        <ul class="ssc-card__list">
                            <?php foreach ($grp['items'] as $ssc):
                                $ssid = (int) $ssc['id'];
                                $sizes = categorie_tailles_selected_values($ssc);
                                if (!$sizes && !empty($ssc['tranche_age'])) {
                                    $sizes = categorie_tailles_flat_presets_for_tranche((string) $ssc['tranche_age']);
                                }
                                $type = (string) ($ssc['tranche_age'] ?? '');
                                $icon = strpos($type, 'chaussure') === 0 ? 'fa-shoe-prints' : 'fa-shirt';
                            ?>
                            <li class="ssc-row">
                                <span class="ssc-row__icon" aria-hidden="true"><i class="fas <?php echo $icon; ?>"></i></span>
                                <div class="ssc-row__body">
                                    <p class="ssc-row__name"><?php echo htmlspecialchars((string) $ssc['nom'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="ssc-row__sizes">
                                        <?php foreach (array_slice($sizes, 0, 8) as $sz): ?><span><?php echo htmlspecialchars($sz, ENT_QUOTES, 'UTF-8'); ?></span><?php endforeach; ?>
                                        <?php if (count($sizes) > 8): ?><span class="ssc-row__more">+<?php echo count($sizes) - 8; ?></span><?php endif; ?>
                                    </p>
                                </div>
                                <div class="ssc-row__actions">
                                    <a class="ssc-icon-btn" href="sous-sous-categories-catalogue.php?edit_ssc=<?php echo $ssid; ?>#sa-ssc-parent-<?php echo (int) $pid; ?>" aria-label="Modifier <?php echo htmlspecialchars((string) $ssc['nom'], ENT_QUOTES, 'UTF-8'); ?>" title="Modifier"><i class="fas fa-pen" aria-hidden="true"></i></a>
                                    <form method="post" action="sous-sous-categories-catalogue.php"
                                        data-ssc-confirm="Supprimer la grille « <?php echo htmlspecialchars((string) $ssc['nom'], ENT_QUOTES, 'UTF-8'); ?> » de « <?php echo htmlspecialchars((string) $grp['nom'], ENT_QUOTES, 'UTF-8'); ?> » ?">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="ssc_id" value="<?php echo $ssid; ?>">
                                        <input type="hidden" name="ssc_return_anchor" value="sa-ssc-parent-<?php echo (int) $pid; ?>">
                                        <button type="submit" name="delete_ssc" value="1" class="ssc-icon-btn ssc-icon-btn--danger" aria-label="Supprimer" title="Supprimer"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>
                                    </form>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                    <?php endforeach; ?>
                </div>
                <p class="ssc-noresult" id="sscNoResult" hidden>Aucune sous-catégorie ne correspond.</p>
            <?php endif; ?>
        </section>
    </div>

    <div class="sa-cat-modal ssc-modal" id="saModalSsc" role="dialog" aria-modal="true" aria-labelledby="saModalSscTitle" <?php echo $row_edit ? '' : 'hidden'; ?><?php echo $row_edit ? ' data-sa-cat-close-reset-href="' . htmlspecialchars($ssc_modal_close_href, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
        <div class="sa-cat-modal__backdrop" data-sa-cat-close-modal tabindex="-1" aria-hidden="true"></div>
        <div class="sa-cat-modal__panel ssc-modal__panel">
            <div class="ssc-modal__header">
                <h2 id="saModalSscTitle"><?php echo $row_edit ? 'Modifier la grille' : 'Nouvelle grille de tailles'; ?></h2>
                <button type="button" class="ssc-icon-btn" data-sa-cat-close-modal aria-label="Fermer"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <?php include __DIR__ . '/_partials/ssc-form.php'; ?>
        </div>
    </div>
    <?php /* Confirmation intégrée à la page : window.confirm() est bloqué par certains navigateurs intégrés (il renvoie « non » sans s'afficher) */ ?>
    <div class="ssc-confirm" id="sscConfirm" role="alertdialog" aria-modal="true" aria-labelledby="sscConfirmText" hidden>
        <div class="ssc-confirm__backdrop" data-ssc-confirm-cancel></div>
        <div class="ssc-confirm__box">
            <span class="ssc-confirm__icon" aria-hidden="true"><i class="fas fa-trash-alt"></i></span>
            <p class="ssc-confirm__text" id="sscConfirmText"></p>
            <p class="ssc-confirm__hint">Les vendeurs ne verront plus ces tailles pour cette sous-catégorie.</p>
            <div class="ssc-confirm__actions">
                <button type="button" class="ssc-btn ssc-btn--ghost" data-ssc-confirm-cancel>Annuler</button>
                <button type="button" class="ssc-btn ssc-btn--danger" id="sscConfirmOk">Supprimer</button>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
    (function () {
        var OPEN = 'sa-cat-modal-open';

        /* Suppression : confirmation dans la page, puis envoi du formulaire */
        (function initDeleteConfirm() {
            var box = document.getElementById('sscConfirm');
            var text = document.getElementById('sscConfirmText');
            var ok = document.getElementById('sscConfirmOk');
            if (!box || !text || !ok) return;
            var pending = null;
            function close() {
                box.hidden = true;
                pending = null;
            }
            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (!form.matches || !form.matches('form[data-ssc-confirm]') || form.dataset.sscConfirmed === '1') return;
                e.preventDefault();
                pending = form;
                text.textContent = form.getAttribute('data-ssc-confirm') || 'Supprimer ?';
                box.hidden = false;
                ok.focus();
            });
            ok.addEventListener('click', function () {
                if (!pending) return;
                var form = pending;
                form.dataset.sscConfirmed = '1';
                ok.disabled = true;
                /* requestSubmit garde le bouton cliqué (name="delete_ssc") dans les données envoyées */
                var btn = form.querySelector('button[name="delete_ssc"]');
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(btn || undefined);
                } else {
                    var flag = document.createElement('input');
                    flag.type = 'hidden';
                    flag.name = 'delete_ssc';
                    flag.value = '1';
                    form.appendChild(flag);
                    form.submit();
                }
            });
            box.querySelectorAll('[data-ssc-confirm-cancel]').forEach(function (el) {
                el.addEventListener('click', close);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !box.hidden) close();
            });
        })();
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

        function norm(s) {
            return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        }

        /* Recherche dans la liste des grilles */
        var listSearch = document.getElementById('sscListSearch');
        if (listSearch) {
            listSearch.addEventListener('input', function () {
                var q = norm(listSearch.value.trim());
                var shown = 0;
                document.querySelectorAll('#sscGrid .ssc-card').forEach(function (card) {
                    var ok = q === '' || norm(card.getAttribute('data-ssc-search')).indexOf(q) !== -1;
                    card.hidden = !ok;
                    if (ok) shown++;
                });
                var nr = document.getElementById('sscNoResult');
                if (nr) nr.hidden = shown > 0;
            });
        }

        /* Modal : recherche + compteur des sous-catégories cochées */
        var pickSearch = document.getElementById('sscParentSearch');
        var pickList = document.getElementById('sscParentList');
        var pickCount = document.getElementById('sscParentCount');
        function refreshPickCount() {
            if (!pickList || !pickCount) return;
            var n = pickList.querySelectorAll('input:checked').length;
            pickCount.textContent = n > 0 ? n + ' sélectionnée' + (n > 1 ? 's' : '') : '';
        }
        if (pickList) {
            pickList.addEventListener('change', function (e) {
                var lab = e.target.closest('.ssc-pick');
                if (e.target.type === 'radio') {
                    pickList.querySelectorAll('.ssc-pick').forEach(function (l) { l.classList.remove('is-checked'); });
                }
                if (lab) lab.classList.toggle('is-checked', e.target.checked);
                refreshPickCount();
            });
            refreshPickCount();
        }
        if (pickSearch && pickList) {
            pickSearch.addEventListener('input', function () {
                var q = norm(pickSearch.value.trim());
                pickList.querySelectorAll('.ssc-pick-group').forEach(function (grp) {
                    var any = false;
                    grp.querySelectorAll('.ssc-pick').forEach(function (lab) {
                        var ok = q === '' || norm(lab.getAttribute('data-search')).indexOf(q) !== -1;
                        lab.hidden = !ok;
                        if (ok) any = true;
                    });
                    grp.hidden = !any;
                });
            });
        }

        /* Grille de tailles selon le type choisi */
        (function initSscTaillesByTranche() {
            var mount = document.getElementById('sscTaillesGroups');
            var trancheSelect = document.querySelector('[data-ssc-tranche-select]');
            if (!mount || !trancheSelect) return;
            var presets = {};
            try {
                presets = JSON.parse(mount.getAttribute('data-presets') || '{}');
            } catch (e) {
                presets = {};
            }
            function escapeHtml(str) {
                return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
            }
            function render(tranche) {
                var groups = presets[String(tranche || '').toLowerCase()];
                if (!groups || !groups.length) {
                    mount.innerHTML = '<p class="ssc-sizes__empty">Choisissez d’abord le type.</p>';
                    return;
                }
                var html = '';
                groups.forEach(function (group) {
                    (group.sizes || []).forEach(function (sz) {
                        var u = String(sz).toUpperCase();
                        if (!u) return;
                        /* Nouvelle grille : toutes les tailles cochées par défaut */
                        html += '<label class="sa-sc-taille-chip is-on"><input type="checkbox" name="ssc_tailles_preset[]" value="' + escapeHtml(u) + '" checked><span>' + escapeHtml(u) + '</span></label>';
                    });
                });
                mount.innerHTML = html;
            }
            trancheSelect.addEventListener('change', function () { render(trancheSelect.value); });
            mount.addEventListener('change', function (e) {
                var lab = e.target.closest('.sa-sc-taille-chip');
                if (lab) lab.classList.toggle('is-on', e.target.checked);
            });
        })();

        var hash = window.location.hash ? window.location.hash.replace(/^#/, '') : '';
        if (hash && document.getElementById(hash)) {
            var el = document.getElementById(hash);
            el.classList.add('is-flash');
            window.scrollTo(0, Math.max(0, el.getBoundingClientRect().top + window.pageYOffset - 90));
        }
    })();
    </script>
</body>
</html>
