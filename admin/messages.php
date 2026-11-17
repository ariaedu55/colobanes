<?php
/**
 * Messagerie vendeur — réponses aux clients et négociations
 */
require_once __DIR__ . '/includes/require_admin_session.php';
require_once __DIR__ . '/includes/require_access.php';

require_once __DIR__ . '/../models/model_messages_vendeur.php';
require_once __DIR__ . '/../models/model_prix_negociations.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/app_base.php';
require_once __DIR__ . '/../includes/messages_inbox_helpers.php';

$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
$admin_role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? 'admin');

/* Vendeur : sa messagerie boutique. Admin / plateforme : accès support (toutes conversations). */
if (!in_array($admin_role, ['vendeur', 'admin', 'plateforme'], true)) {
    header('Location: ' . app_url('/admin/dashboard.php'));
    exit;
}

$conv_id = isset($_GET['conv']) ? (int) $_GET['conv'] : 0;
$filtre_inbox = isset($_GET['filtre']) ? trim((string) $_GET['filtre']) : 'tout';
if (!in_array($filtre_inbox, ['tout', 'non_lus', 'negociations'], true)) {
    $filtre_inbox = 'tout';
}

$conversations = [];
$conversations_all = [];
$active_conv = null;
$messages = [];
$pending_negociations = [];
$tables_ok = messages_vendeur_tables_exist();
$is_support_inbox = in_array($admin_role, ['admin', 'plateforme'], true);

if ($tables_ok) {
    $conversations_all = $is_support_inbox
        ? conversation_vendeur_list_all(120)
        : conversation_vendeur_list_by_admin($admin_id, 80);
    $conversations = $conversations_all;

    if ($filtre_inbox === 'non_lus') {
        $conversations = array_values(array_filter($conversations_all, function ($c) {
            return (int) ($c['nb_non_lus'] ?? 0) > 0;
        }));
    } elseif ($filtre_inbox === 'negociations') {
        $conversations = array_values(array_filter($conversations_all, function ($c) {
            return (int) ($c['produit_id'] ?? 0) > 0;
        }));
    }

    $can_open = $conv_id > 0 && (
        $is_support_inbox
            ? (bool) conversation_vendeur_get_by_id($conv_id)
            : conversation_vendeur_belongs_to_admin($conv_id, $admin_id)
    );
    if ($can_open) {
        $active_conv = conversation_vendeur_get_by_id($conv_id);
        if ($active_conv) {
            $messages = message_vendeur_list_by_conversation($conv_id, 250, 'vendeur');
            if (function_exists('prix_negociation_list_pending_for_conversation')) {
                $pending_negociations = prix_negociation_list_pending_for_conversation($active_conv);
            }
        }
    }
}

$nb_non_lus = 0;
if ($tables_ok) {
    if ($is_support_inbox) {
        foreach ($conversations_all as $c_row) {
            $nb_non_lus += (int) ($c_row['nb_non_lus'] ?? 0);
        }
    } else {
        $nb_non_lus = message_vendeur_count_unread_for_vendeur($admin_id);
    }
}
$messages_page_url = app_url('/admin/messages.php');
$messages_form_action = app_url('/admin/messages-action.php');
$has_active_chat = ($active_conv !== null);

/* Stories : jamais ouvertes par le vendeur — uniquement ici, pas dans la liste */
$conversations_stories = [];
$conversations_list = [];
foreach ($conversations as $c_row) {
    $jamais_ouverte = ((int) ($c_row['ouverte_vendeur'] ?? 0) === 0);
    if ($jamais_ouverte) {
        $conversations_stories[] = $c_row;
    } else {
        $conversations_list[] = $c_row;
    }
}
/* Si filtre vide mais stories présentes, on affiche quand même les stories */
if (empty($conversations) && !empty($conversations_all) && $filtre_inbox !== 'tout') {
    foreach ($conversations_all as $c_row) {
        if ((int) ($c_row['ouverte_vendeur'] ?? 0) !== 0) {
            continue;
        }
        if ($filtre_inbox === 'non_lus' && (int) ($c_row['nb_non_lus'] ?? 0) <= 0) {
            continue;
        }
        if ($filtre_inbox === 'negociations' && (int) ($c_row['produit_id'] ?? 0) <= 0) {
            continue;
        }
        $conversations_stories[] = $c_row;
    }
}

if ($has_active_chat && $conv_id > 0) {
    $mark_admin_id = $is_support_inbox
        ? (int) ($active_conv['admin_id'] ?? 0)
        : $admin_id;
    if ($mark_admin_id > 0) {
        message_vendeur_mark_read_by_vendeur($conv_id, $mark_admin_id);
    }
}
if (!function_exists('messages_admin_inbox_filter_href')) {
    function messages_admin_inbox_filter_href($filtre, $conv = 0)
    {
        global $messages_page_url;
        $q = ['filtre' => $filtre];
        if ($conv > 0) {
            $q['conv'] = $conv;
        }
        return $messages_page_url . '?' . http_build_query($q);
    }
}

$page_title = 'Messages clients';

$last_message_id = 0;
if ($has_active_chat && !empty($messages)) {
    $last_msg_row = end($messages);
    if (is_array($last_msg_row)) {
        $last_message_id = (int) ($last_msg_row['id'] ?? 0);
    }
    reset($messages);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?> — Admin</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/variables.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">    <?php require_once dirname(__DIR__, 1) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/style.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/admin-dashboard.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/messages-vendeur.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/prix-negociation.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php /* Habillage « messagerie pro » commun client / vendeur */ ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/messages-pro.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<?php /* Messagerie unique : même gabarit que user/messages.php (onglet « Ventes »), sans la mise en page admin */ ?>
<body class="admin-page site-page-messages msg-pro msg-role-vendeur<?php echo $has_active_chat ? ' site-page-messages--chat' : ''; ?>">

    <?php
    $nav_force_marketplace_chrome = true;
    include __DIR__ . '/../nav_bar.php';
    ?>

    <?php include __DIR__ . '/includes/nav.php'; ?>

    <div class="msg-app msg-app--admin">
        <?php if (!$tables_ok): ?>
        <div class="msg-empty">
            <div class="msg-empty__icon"><i class="fas fa-tools" aria-hidden="true"></i></div>
            <h2>Messagerie indisponible</h2>
            <p>Exécutez la migration <code>php migrations/run_add_messages_vendeur.php</code>.</p>
        </div>
        <?php else: ?>
        <div class="msg-app__layout<?php echo $has_active_chat ? ' msg-app__layout--chat-open' : ''; ?>">

            <aside class="msg-inbox<?php echo ($has_active_chat && $conv_id > 0) ? ' msg-inbox--hidden-mobile' : ''; ?>" aria-label="Conversations clients">
                <div class="msg-inbox__desktop-head">
                    <div class="msg-inbox__desktop-head-row">
                        <h1 class="msg-inbox__desktop-title">Messages</h1>
                        <?php if ($nb_non_lus > 0): ?>
                        <span class="msg-inbox__desktop-unread" aria-label="<?php echo (int) $nb_non_lus; ?> non lu(s)"><?php echo (int) $nb_non_lus; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php $msg_role = 'vendeur'; include __DIR__ . '/../includes/partials/messages_role_switch.php'; ?>
                <?php if (!empty($conversations_all)): ?>
                <div class="msg-inbox__search">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="msgInboxSearch" placeholder="Rechercher un client ou un produit" aria-label="Rechercher une conversation" autocomplete="off">
                </div>
                <?php endif; ?>
                <?php if (!empty($conversations_stories)): ?>
                <div class="msg-inbox__stories" aria-label="Nouvelles propositions">
                    <div class="msg-inbox__stories-track">
                        <?php foreach ($conversations_stories as $story):
                            $story_id = (int) ($story['id'] ?? 0);
                            $story_img = trim((string) ($story['produit_image'] ?? ''));
                            $story_thumb = $story_img !== '' ? upload_image_url($story_img, 'sm') : '';
                            $story_nom = trim((string) ($story['produit_nom'] ?? ''));
                            if ($story_nom === '') {
                                $story_nom = trim((string) ($story['client_prenom'] ?? '') . ' ' . (string) ($story['client_nom'] ?? ''));
                            }
                            if ($story_nom === '') {
                                $story_nom = 'Nouvelle discussion';
                            }
                            $story_href = messages_admin_inbox_filter_href($filtre_inbox, $story_id);
                            $story_initial = mb_strtoupper(mb_substr($story_nom, 0, 1));
                        ?>
                        <a href="<?php echo htmlspecialchars($story_href, ENT_QUOTES, 'UTF-8'); ?>"
                            class="msg-inbox__story<?php echo ($conv_id === $story_id) ? ' is-active' : ''; ?>"
                            aria-label="<?php echo htmlspecialchars($story_nom, ENT_QUOTES, 'UTF-8'); ?>"
                            title="<?php echo htmlspecialchars($story_nom, ENT_QUOTES, 'UTF-8'); ?>"
                            data-conv-id="<?php echo $story_id; ?>">
                            <span class="msg-inbox__story-avatar">
                                <?php if ($story_thumb !== ''): ?>
                                <img src="<?php echo htmlspecialchars($story_thumb, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="64" height="64" loading="lazy">
                                <?php else: ?>
                                <span class="msg-inbox__story-fallback"><?php echo htmlspecialchars($story_initial, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <span class="msg-inbox__story-dot" aria-hidden="true"></span>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="msg-inbox__toolbar">
                    <div class="msg-inbox__filters" role="tablist" aria-label="Filtrer les conversations">
                        <a href="<?php echo htmlspecialchars(messages_admin_inbox_filter_href('tout'), ENT_QUOTES, 'UTF-8'); ?>"
                            class="msg-inbox__filter<?php echo $filtre_inbox === 'tout' ? ' is-active' : ''; ?>">Tout</a>
                        <a href="<?php echo htmlspecialchars(messages_admin_inbox_filter_href('non_lus'), ENT_QUOTES, 'UTF-8'); ?>"
                            class="msg-inbox__filter<?php echo $filtre_inbox === 'non_lus' ? ' is-active' : ''; ?>">
                            Non lus<?php echo $nb_non_lus > 0 ? ' (' . (int) $nb_non_lus . ')' : ''; ?>
                        </a>
                        <a href="<?php echo htmlspecialchars(messages_admin_inbox_filter_href('negociations'), ENT_QUOTES, 'UTF-8'); ?>"
                            class="msg-inbox__filter<?php echo $filtre_inbox === 'negociations' ? ' is-active' : ''; ?>">Négociations</a>
                    </div>
                </div>

                <?php if (empty($conversations_all)): ?>
                <div class="msg-inbox__empty">
                    <div class="msg-inbox__empty-icon"><i class="fas fa-comments" aria-hidden="true"></i></div>
                    <h2>Aucun message</h2>
                    <p>Les clients qui négocient vos produits apparaîtront ici.</p>
                </div>
                <?php elseif (empty($conversations_list) && empty($conversations_stories)): ?>
                <div class="msg-inbox__empty msg-inbox__empty--filter">
                    <p>Aucune conversation dans ce filtre.</p>
                    <a href="<?php echo htmlspecialchars(messages_admin_inbox_filter_href('tout'), ENT_QUOTES, 'UTF-8'); ?>" class="msg-inbox__cta msg-inbox__cta--ghost">Voir tout</a>
                </div>
                <?php elseif (empty($conversations_list)): ?>
                <div class="msg-inbox__empty msg-inbox__empty--filter">
                    <p>Ouvrez une proposition ci-dessus pour commencer.</p>
                </div>
                <?php else: ?>
                <div class="msg-inbox__list" role="list">
                    <?php foreach ($conversations_list as $c):
                        $cid = (int) ($c['id'] ?? 0);
                        $is_active = ($conv_id === $cid);
                        $client_nom = trim((string) ($c['client_prenom'] ?? '') . ' ' . (string) ($c['client_nom'] ?? ''));
                        $produit_nom = trim((string) ($c['produit_nom'] ?? ''));
                        $label = $produit_nom !== '' ? $produit_nom : 'Produit';
                        $meta_line = $client_nom !== '' ? $client_nom : 'Client';
                        $prix_inbox = conversation_vendeur_produit_prix_affiche([
                            'prix' => $c['produit_prix'] ?? 0,
                            'prix_promotion' => $c['produit_prix_promotion'] ?? null,
                        ]);
                        $img = trim((string) ($c['produit_image'] ?? ''));
                        $thumb = $img !== '' ? upload_image_url($img, 'sm') : '';
                        $non_lus = (int) ($c['nb_non_lus'] ?? 0);
                        $conv_href = messages_admin_inbox_filter_href($filtre_inbox, $cid);
                    ?>
                    <a href="<?php echo htmlspecialchars($conv_href, ENT_QUOTES, 'UTF-8'); ?>"
                        class="msg-inbox__item<?php echo $is_active ? ' is-active' : ''; ?><?php echo $non_lus > 0 ? ' is-unread' : ' is-read'; ?>"
                        role="listitem" data-conv-id="<?php echo $cid; ?>">
                        <div class="msg-inbox__thumb">
                            <?php if ($thumb !== ''): ?>
                            <img src="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="72" height="72" loading="lazy">
                            <?php else: ?>
                            <span class="msg-inbox__thumb-fallback" aria-hidden="true"><i class="fas fa-user"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="msg-inbox__content">
                            <div class="msg-inbox__top">
                                <h2 class="msg-inbox__title-line"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></h2>
                                <?php $time_label = messages_inbox_time_label($c['dernier_message_date'] ?? ($c['dernier_message_at'] ?? '')); ?>
                                <?php if ($time_label !== ''): ?>
                                <time class="msg-inbox__time"><?php echo htmlspecialchars($time_label, ENT_QUOTES, 'UTF-8'); ?></time>
                                <?php endif; ?>
                            </div>
                            <?php $preview = messages_inbox_preview($c['dernier_message'] ?? ''); ?>
                            <?php if ($preview !== ''): ?>
                            <p class="msg-inbox__preview"><?php echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <p class="msg-inbox__sub">
                                <span class="msg-inbox__meta"><?php echo htmlspecialchars($meta_line, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($prix_inbox > 0): ?>
                                <span class="msg-inbox__price"><?php echo number_format($prix_inbox, 0, ',', ' '); ?> FCFA</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($non_lus > 0): ?>
                        <span class="msg-inbox__unread-badge" aria-label="<?php echo (int) $non_lus; ?> non lu(s)"><?php echo $non_lus > 99 ? '99+' : (int) $non_lus; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </aside>

            <div class="msg-pane">
                <?php if (!$has_active_chat): ?>
                <div class="msg-pane__empty">
                    <div class="msg-pane__empty-icon"><i class="fas fa-comment-dots" aria-hidden="true"></i></div>
                    <h2>Messagerie vendeur</h2>
                    <p>Choisissez une conversation.</p>
                </div>
                <?php else: ?>
                <?php include __DIR__ . '/../includes/partials/messages_vendeur_thread_admin.php'; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php
    ob_start();
    ?>
    <script src="<?php echo htmlspecialchars(app_url('/js/messages-vendeur-ui.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(app_url('/js/prix-negociation-modal.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script>
    (function () {
        var input = document.getElementById('msgInboxSearch');
        if (!input) return;
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            document.querySelectorAll('.msg-inbox__item').forEach(function (item) {
                item.hidden = q !== '' && item.textContent.toLowerCase().indexOf(q) === -1;
            });
        });
    })();
    </script>
    <?php if ($tables_ok): ?>
    <script>
    window.MSG_REALTIME = {
        enabled: true,
        role: 'vendeur',
        apiBase: <?php echo json_encode(app_url('/api'), JSON_UNESCAPED_UNICODE); ?>,
        convId: <?php echo (int) $conv_id; ?>,
        lastMessageId: <?php echo (int) $last_message_id; ?>,
        filtre: <?php echo json_encode($filtre_inbox, JSON_UNESCAPED_UNICODE); ?>,
        pollThreadMs: 6000,
        pollInboxMs: 12000,
        pollUnreadMs: 25000,
        pollThreadHiddenMs: 8000
    };
    </script>
    <script src="<?php echo htmlspecialchars(app_url('/js/messages-vendeur-realtime.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endif; ?>
    <script>
    (function () {
        if (typeof window.msgChatEnsureAtTop === 'function') {
            window.msgChatEnsureAtTop();
        } else if (typeof window.msgChatScrollToTop === 'function') {
            window.msgChatScrollToTop();
        }
    })();
    </script>
    <?php
    $admin_page_scripts_before_close = ob_get_clean();
    include __DIR__ . '/includes/footer.php';
    ?>
