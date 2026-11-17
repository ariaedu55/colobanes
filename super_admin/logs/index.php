<?php
/**
 * Journal d'audit (actions super admin)
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';

$logs = super_admin_logs_recent(150);
$nb_logs = count($logs);

/**
 * Nom affiché pour un acteur (prénom + nom).
 */
function sa_log_actor_display_name(array $row) {
    $prenom = trim((string) ($row['super_admin_prenom'] ?? ''));
    $nom = trim((string) ($row['super_admin_nom'] ?? ''));
    $display = trim($prenom . ' ' . $nom);
    if ($display === '') {
        $display = $nom !== '' ? $nom : trim((string) ($row['super_admin_email'] ?? 'Super admin'));
    }
    return $display;
}

/**
 * Libellé lisible pour un code action.
 */
function sa_log_action_label($action) {
    $action = (string) $action;
    static $map = [
        'sous_categorie_modifiee' => 'Sous-catégorie modifiée',
        'sous_categorie_ajoutee' => 'Sous-catégorie ajoutée',
        'sous_categorie_supprimee' => 'Sous-catégorie supprimée',
        'sous_sous_categorie_creee' => 'Sous-sous-catégorie créée',
        'sous_sous_categorie_modifiee' => 'Sous-sous-catégorie modifiée',
        'sous_sous_categorie_supprimee' => 'Sous-sous-catégorie supprimée',
        'categorie_modifiee' => 'Catégorie modifiée',
        'client_activé' => 'Client activé',
        'client_désactivé' => 'Client désactivé',
        'super_admin_active' => 'Compte super admin activé',
        'super_admin_desactive' => 'Compte super admin désactivé',
    ];
    if (isset($map[$action])) {
        return $map[$action];
    }
    $label = str_replace('_', ' ', $action);
    return $label !== '' ? ucfirst($label) : 'Action';
}

/**
 * Libellé court pour le type de cible.
 */
function sa_log_cible_label($type) {
    $type = (string) $type;
    static $map = [
        'categories' => 'Catégorie',
        'category' => 'Catégorie',
        'user' => 'Client',
        'super_admin' => 'Super admin',
        'vendeur' => 'Boutique',
    ];
    return $map[$type] ?? ($type !== '' ? ucfirst($type) : 'Élément');
}

/**
 * Regroupe les logs par jour calendaire + super admin.
 *
 * @return array<int, array{key: string, day: string, day_label: string, actor: string, super_admin_id: int, entries: array}>
 */
function sa_log_group_by_day_actor(array $logs) {
    $groups = [];
    foreach ($logs as $L) {
        $raw = (string) ($L['date_action'] ?? '');
        if ($raw === '') {
            continue;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            continue;
        }
        $day = date('Y-m-d', $ts);
        $sid = (int) ($L['super_admin_id'] ?? 0);
        $key = $day . '-' . $sid;
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'key' => $key,
                'day' => $day,
                'day_label' => date('d/m/Y', $ts),
                'actor' => sa_log_actor_display_name($L),
                'super_admin_id' => $sid,
                'latest_ts' => $ts,
                'entries' => [],
            ];
        }
        if ($ts > $groups[$key]['latest_ts']) {
            $groups[$key]['latest_ts'] = $ts;
        }
        $groups[$key]['entries'][] = $L;
    }
    $out = array_values($groups);
    usort($out, static function ($a, $b) {
        return ($b['latest_ts'] ?? 0) <=> ($a['latest_ts'] ?? 0);
    });
    return $out;
}

$log_groups = sa_log_group_by_day_actor($logs);
$nb_groups = count($log_groups);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal d'audit — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-logs.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page sa-log-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell">
        <header class="sa-users-hero sa-log-hero" aria-labelledby="sa-log-title">
            <div class="sa-users-hero__inner">
                <div>
                    <h1 class="sa-users-hero__title" id="sa-log-title">Journal d'audit</h1>
                </div>
            </div>
        </header>

        <section class="sa-users-panel sa-log-panel" aria-labelledby="sa-log-panel-title">
            <div class="sa-users-panel__head">
                <h2 id="sa-log-panel-title"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Activité</h2>
                <p class="sa-users-panel__meta">
                    <?php if ($nb_logs === 0): ?>
                        Aucune entrée enregistrée pour l’instant.
                    <?php else: ?>
                        <strong><?php echo (int) $nb_groups; ?></strong> journée<?php echo $nb_groups > 1 ? 's' : ''; ?> ·
                        <strong><?php echo (int) $nb_logs; ?></strong> action<?php echo $nb_logs > 1 ? 's' : ''; ?>
                    <?php endif; ?>
                </p>
            </div>

            <?php if (empty($log_groups)): ?>
                <div class="sa-users-empty">
                    <i class="fas fa-clipboard" aria-hidden="true"></i>
                    <p><strong>Aucune action enregistrée.</strong></p>
                    <p>Les opérations sur les boutiques et les clients apparaîtront ici automatiquement.</p>
                </div>
            <?php else: ?>
                <ul class="sa-log-groups">
                    <?php foreach ($log_groups as $gi => $g):
                        $gid = 'sa-log-group-' . $gi;
                        $nb_entries = count($g['entries']);
                        $preview_action = '';
                        if (!empty($g['entries'][0]['action'])) {
                            $preview_action = (string) $g['entries'][0]['action'];
                        }
                    ?>
                    <li class="sa-log-group">
                        <div class="sa-log-group__summary">
                            <span class="sa-log-group__date">
                                <i class="fas fa-calendar-day" aria-hidden="true"></i>
                                <?php echo htmlspecialchars($g['day_label'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <button type="button" class="sa-log-group__toggle"
                                data-sa-log-toggle
                                aria-expanded="false"
                                aria-controls="<?php echo htmlspecialchars($gid, ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="sa-log-group__actor"><?php echo htmlspecialchars($g['actor'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="sa-log-group__meta">
                                    <?php echo (int) $nb_entries; ?> action<?php echo $nb_entries > 1 ? 's' : ''; ?>
                                    <?php if ($preview_action !== ''): ?>
                                        · <span class="sa-log-group__preview"><?php echo htmlspecialchars($preview_action, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </span>
                                <i class="fas fa-chevron-down sa-log-group__chevron" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="sa-log-group__detail" id="<?php echo htmlspecialchars($gid, ENT_QUOTES, 'UTF-8'); ?>" hidden>
                            <div class="sa-log-events-panel">
                                <div class="sa-log-events-head" aria-hidden="true">
                                    <span>Heure</span>
                                    <span>Événement</span>
                                </div>
                                <ul class="sa-log-events">
                                    <?php foreach ($g['entries'] as $L):
                                        $raw = (string) ($L['date_action'] ?? '');
                                        $time_fmt = $raw !== '' ? date('H:i', strtotime($raw)) : '—';
                                        $action_code = (string) ($L['action'] ?? '');
                                        $action_label = sa_log_action_label($action_code);
                                        $cible_type = (string) ($L['cible_type'] ?? '');
                                        $cible_label = sa_log_cible_label($cible_type);
                                        $details = trim((string) ($L['details'] ?? ''));
                                        $title = $details !== '' ? $details : $action_label;
                                    ?>
                                    <li class="sa-log-event">
                                        <time class="sa-log-event__time" datetime="<?php echo $raw !== '' ? htmlspecialchars(date('c', strtotime($raw)), ENT_QUOTES, 'UTF-8') : ''; ?>">
                                            <?php echo htmlspecialchars($time_fmt, ENT_QUOTES, 'UTF-8'); ?>
                                        </time>
                                        <div class="sa-log-event__content">
                                            <p class="sa-log-event__title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></p>
                                            <div class="sa-log-event__meta">
                                                <span class="sa-log-event__pill"><?php echo htmlspecialchars($action_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php if ($cible_type !== '' || !empty($L['cible_id'])): ?>
                                                    <span class="sa-log-event__cible">
                                                        <?php echo htmlspecialchars($cible_label, ENT_QUOTES, 'UTF-8'); ?>
                                                        <?php if (!empty($L['cible_id'])): ?>
                                                            <span class="sa-log-event__id">#<?php echo (int) $L['cible_id']; ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <script>
    (function () {
        document.querySelectorAll('[data-sa-log-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('aria-controls');
                var panel = id ? document.getElementById(id) : null;
                if (!panel) return;
                var open = panel.hidden;
                panel.hidden = !open;
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                btn.classList.toggle('is-open', open);
            });
        });
    })();
    </script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
