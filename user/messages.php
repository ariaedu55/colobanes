<?php
/**
 * Messagerie client — discussions et négociations avec les vendeurs
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();

if (!function_exists('auth_redirect_to_site_home')) {
    require_once __DIR__ . '/../includes/auth_redirect.php';
}
if (!function_exists('app_url')) {
    require_once __DIR__ . '/../includes/app_base.php';
}

require_once __DIR__ . '/../controllers/controller_messages_vendeur.php';
require_once __DIR__ . '/../models/model_messages_vendeur.php';
require_once __DIR__ . '/../includes/image_optimizer.php';

if (isset($_GET['produit_id']) && (int) $_GET['produit_id'] > 0) {
    messages_vendeur_open_product_conversation();
}

if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    $redirect_target = app_url('/user/messages.php');
    header('Location: ' . app_url('/choix-connexion.php?redirect=' . rawurlencode($redirect_target)));
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$conv_id = isset($_GET['conv']) ? (int) $_GET['conv'] : 0;
$show_negocier = isset($_GET['negocier']) && (string) $_GET['negocier'] === '1';
$filtre_inbox = isset($_GET['filtre']) ? trim((string) $_GET['filtre']) : 'tout';
if (!in_array($filtre_inbox, ['tout', 'non_lus', 'negociations', 'achats'], true)) {
    $filtre_inbox = 'tout';
}

$conversations = [];
require_once __DIR__ . '/../includes/messages_inbox_helpers.php';

$conversations_all = [];
$active_conv = null;
$messages = [];
$tables_ok = messages_vendeur_tables_exist();

if ($tables_ok) {
    conversation_vendeur_sync_achats_from_commandes($user_id);
    $conversations_all = conversation_vendeur_list_by_user($user_id, 80);
    $conversations = conversation_vendeur_filter_inbox_rows($conversations_all, $filtre_inbox);

    if ($conv_id > 0 && conversation_vendeur_belongs_to_user($conv_id, $user_id)) {
        $active_conv = conversation_vendeur_get_by_id($conv_id);
        if ($active_conv) {
            $messages = message_vendeur_list_by_conversation($conv_id, 250, 'client');
        }
    }
}

$nb_non_lus = $tables_ok ? conversation_vendeur_count_unread_in_rows($user_id, $conversations_all) : 0;
$nb_negociations = 0;
$nb_achats = 0;
foreach ($conversations_all as $c_row) {
    $st = conversation_vendeur_row_statut($c_row);
    if ($st === 'achat') {
        $nb_achats++;
        continue;
    }
    if ((int) ($c_row['produit_id'] ?? 0) > 0) {
        $nb_negociations++;
    }
}
$messages_page_url = app_url('/user/messages.php');
$messages_form_action = app_url('/user/messages-action.php');
$messages_menu_csrf = messages_vendeur_client_csrf_token();
$has_active_chat = ($active_conv !== null);

if (!function_exists('messages_inbox_filter_href')) {
    /**
     * @param string $filtre
     * @param int $conv
     */
    function messages_inbox_filter_href($filtre, $conv = 0)
    {
        global $messages_page_url;
        $q = ['filtre' => $filtre];
        if ($conv > 0) {
            $q['conv'] = $conv;
        }
        return $messages_page_url . '?' . http_build_query($q);
    }
}

$produit_id_conv = 0;
$produit_url = '';
$prix_conv = 0.0;
$can_negocier = false;

if ($has_active_chat) {
    $produit_id_conv = (int) ($active_conv['produit_id'] ?? 0);
    $produit_url = $produit_id_conv > 0 ? app_url('/produit.php?id=' . $produit_id_conv) : '';
    $prix_conv = conversation_vendeur_produit_prix_affiche([
        'prix' => $active_conv['produit_prix'] ?? 0,
        'prix_promotion' => $active_conv['produit_prix_promotion'] ?? null,
    ]);
    if ($produit_id_conv > 0) {
        require_once __DIR__ . '/../models/model_produits.php';
        $p_row = get_produit_by_id($produit_id_conv);
        $conv_statut = conversation_vendeur_row_statut($active_conv);
        $can_negocier = ($p_row && produit_prix_negociable($p_row) && $conv_statut !== 'achat');
    }
}

$has_offre_acceptee_a_payer = false;
$pending_counters = [];
$neg_orderable = null;
if ($has_active_chat && $produit_id_conv > 0) {
    if (!function_exists('prix_negociation_find_orderable_for_conversation')) {
        require_once __DIR__ . '/../models/model_prix_negociations.php';
    }
    if (function_exists('prix_negociation_list_counters_for_conversation')) {
        $pending_counters = prix_negociation_list_counters_for_conversation($active_conv);
    }
    if (function_exists('prix_negociation_find_orderable_for_conversation')) {
        $neg_orderable = prix_negociation_find_orderable_for_conversation($active_conv);
        $has_offre_acceptee_a_payer = ($neg_orderable !== null && empty($pending_counters));
    }
    if ($has_offre_acceptee_a_payer || !empty($pending_counters)) {
        $can_negocier = false;
    }
}
$conv_statut = $has_active_chat ? conversation_vendeur_row_statut($active_conv) : 'active';

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
    <title>Messages — COLObanes</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 1) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/variables.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/style.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/messages-vendeur.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php /* Habillage « messagerie pro » commun client / vendeur */ ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/css/messages-pro.css') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="site-page-messages msg-pro<?php echo $has_active_chat ? ' site-page-messages--chat' : ''; ?>">

    <?php include __DIR__ . '/../nav_bar.php'; ?>

    <div class="msg-app">
        <?php if (!$tables_ok): ?>
        <div class="msg-empty">
            <div class="msg-empty__icon"><i class="fas fa-tools" aria-hidden="true"></i></div>
            <h2>Messagerie en cours d'installation</h2>
            <p>La messagerie n'est pas encore disponible. Réessayez plus tard.</p>
        </div>
        <?php else: ?>
        <div class="msg-app__layout<?php echo $has_active_chat ? ' msg-app__layout--chat-open' : ''; ?>">

            <aside class="msg-inbox<?php echo ($has_active_chat && $conv_id > 0) ? ' msg-inbox--hidden-mobile' : ''; ?>" aria-label="Vos conversations">
                <div class="msg-inbox__desktop-head">
                    <div class="msg-inbox__desktop-head-row">
                        <h1 class="msg-inbox__desktop-title">Messages</h1>
                        <?php if ($nb_non_lus > 0): ?>
                        <span class="msg-inbox__desktop-unread" aria-label="<?php echo (int) $nb_non_lus; ?> message<?php echo $nb_non_lus > 1 ? 's' : ''; ?> non lu<?php echo $nb_non_lus > 1 ? 's' : ''; ?>"><?php echo (int) $nb_non_lus; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php $msg_role = 'client'; include __DIR__ . '/../includes/partials/messages_role_switch.php'; ?>
                <?php if (!empty($conversations_all)): ?>
                <div class="msg-inbox__search">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="msgInboxSearch" placeholder="Rechercher une conversation" aria-label="Rechercher une conversation" autocomplete="off">
                </div>
                <?php endif; ?>
                <div class="msg-inbox__toolbar">
                    <div class="msg-inbox__filters" role="tablist" aria-label="Filtrer les conversations">
                        <a href="<?php echo htmlspecialchars(messages_inbox_filter_href('tout'), ENT_QUOTES, 'UTF-8'); ?>"
                            class="msg-inbox__filter<?php echo $filtre_inbox === 'tout' ? ' is-active' : ''; ?>"
                            role="tab" aria-selected="<?php echo $filtre_inbox === 'tout' ? 'true' : 'false'; ?>">
                            <?php if ($filtre_inbox === 'tout'): ?><i class="fas fa-check" aria-hidden="true"></i><?php endif; ?>
                            Tout
                        </a>
                        <a href="<?php echo htmlspecialchars(messages_inbox_filter_href('non_lus'), ENT_QUOTES, 'UTF-8'); ?>"
                            class="msg-inbox__filter<?php echo $filtre_inbox === 'non_lus' ? ' is-active' : ''; ?>"
                            role="tab" aria-selected="<?php echo $filtre_inbox === 'non_lus' ? 'true' : 'false'; ?>">
                            <?php if ($filtre_inbox === 'non_lus'): ?><i class="fas fa-check" aria-hidden="true"></i><?php endif; ?>
                            Non lus
                            <?php if ($nb_non_lus > 0): ?>
                            <span class="msg-inbox__filter-badge"><?php echo $nb_non_lus > 99 ? '99+' : (int) $nb_non_lus; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo htmlspecialchars(messages_inbox_filter_href('negociations'), ENT_QUOTES, 'UTF-8'); ?>"
                            class="msg-inbox__filter<?php echo $filtre_inbox === 'negociations' ? ' is-active' : ''; ?>"
                            role="tab" aria-selected="<?php echo $filtre_inbox === 'negociations' ? 'true' : 'false'; ?>">
                            <?php if ($filtre_inbox === 'negociations'): ?><i class="fas fa-check" aria-hidden="true"></i><?php endif; ?>
                            Négociations
                        </a>
                        <a href="<?php echo htmlspecialchars(messages_inbox_filter_href('achats'), ENT_QUOTES, 'UTF-8'); ?>"
                            class="msg-inbox__filter<?php echo $filtre_inbox === 'achats' ? ' is-active' : ''; ?>"
                            role="tab" aria-selected="<?php echo $filtre_inbox === 'achats' ? 'true' : 'false'; ?>">
                            <?php if ($filtre_inbox === 'achats'): ?><i class="fas fa-check" aria-hidden="true"></i><?php endif; ?>
                            Achats<?php echo $nb_achats > 0 ? ' (' . (int) $nb_achats . ')' : ''; ?>
                        </a>
                        <?php
                        require_once __DIR__ . '/../models/model_dons.php';
                        $nb_dons_non_lus = dons_count_non_lus($user_id);
                        ?>
                        <a href="/dons/messages.php" class="msg-inbox__filter">
                            Dons
                            <?php if ($nb_dons_non_lus > 0): ?>
                            <span class="msg-inbox__filter-badge"><?php echo $nb_dons_non_lus > 99 ? '99+' : (int) $nb_dons_non_lus; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>

                <?php if (empty($conversations_all)): ?>
                <div class="msg-inbox__empty">
                    <div class="msg-inbox__empty-icon"><i class="fas fa-comments" aria-hidden="true"></i></div>
                    <h2>Aucune discussion</h2>
                    <p>Depuis un produit, cliquez sur <strong>Négocier le prix</strong> pour contacter le vendeur. Vos échanges apparaîtront ici.</p>
                    <a href="<?php echo htmlspecialchars(app_url('/produits.php'), ENT_QUOTES, 'UTF-8'); ?>" class="msg-inbox__cta">Parcourir les produits</a>
                </div>
                <?php elseif (empty($conversations)): ?>
                <div class="msg-inbox__empty msg-inbox__empty--filter">
                    <p>Aucune conversation dans ce filtre.</p>
                    <a href="<?php echo htmlspecialchars(messages_inbox_filter_href('tout'), ENT_QUOTES, 'UTF-8'); ?>" class="msg-inbox__cta msg-inbox__cta--ghost">Voir tout</a>
                </div>
                <?php else: ?>
                <div class="msg-inbox__list" role="list">
                    <?php foreach ($conversations as $c):
                        $cid = (int) ($c['id'] ?? 0);
                        $is_active = ($conv_id === $cid);
                        $boutique = trim((string) ($c['vendeur_boutique_nom'] ?? 'Boutique'));
                        $produit_nom = trim((string) ($c['produit_nom'] ?? ''));
                        $label = $produit_nom !== '' ? $produit_nom : 'Produit';
                        $prix_inbox = conversation_vendeur_produit_prix_affiche([
                            'prix' => $c['produit_prix'] ?? 0,
                            'prix_promotion' => $c['produit_prix_promotion'] ?? null,
                        ]);
                        $img = trim((string) ($c['produit_image'] ?? ''));
                        $thumb = $img !== '' ? upload_image_url($img, 'sm') : '';
                        $non_lus = (int) ($c['nb_non_lus'] ?? 0);
                        $is_achat = conversation_vendeur_row_statut($c) === 'achat';
                        $conv_href = $messages_page_url . '?conv=' . $cid;
                        if ($filtre_inbox !== 'tout') {
                            $conv_href .= '&filtre=' . rawurlencode($filtre_inbox);
                        }
                    ?>
                    <a href="<?php echo htmlspecialchars($conv_href, ENT_QUOTES, 'UTF-8'); ?>"
                        class="msg-inbox__item<?php echo $is_active ? ' is-active' : ''; ?><?php echo $non_lus > 0 ? ' is-unread' : ' is-read'; ?><?php echo $is_achat ? ' is-achat' : ''; ?>"
                        role="listitem" data-conv-id="<?php echo $cid; ?>">
                        <div class="msg-inbox__thumb">
                            <?php if ($thumb !== ''): ?>
                            <img src="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="72" height="72" loading="lazy">
                            <?php else: ?>
                            <span class="msg-inbox__thumb-fallback" aria-hidden="true"><i class="fas fa-store"></i></span>
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
                                <span class="msg-inbox__meta"><?php echo htmlspecialchars($boutique, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($prix_inbox > 0): ?>
                                <span class="msg-inbox__price"><?php echo number_format($prix_inbox, 0, ',', ' '); ?> FCFA</span>
                                <?php endif; ?>
                                <?php if ($is_achat): ?>
                                <span class="msg-inbox__tag">Acheté</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($non_lus > 0): ?>
                        <span class="msg-inbox__unread-badge" aria-label="<?php echo (int) $non_lus; ?> non lu<?php echo $non_lus > 1 ? 's' : ''; ?>"><?php echo $non_lus > 99 ? '99+' : (int) $non_lus; ?></span>
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
                    <h2>Vos discussions</h2>
                    <p>Sélectionnez une conversation pour négocier un prix ou échanger avec un vendeur.</p>
                </div>
                <?php else:
                    $messages_list_url = messages_inbox_filter_href($filtre_inbox);
                    if (!isset($conv_statut) && $active_conv) {
                        $conv_statut = conversation_vendeur_row_statut($active_conv);
                    }
                    $messages_compose_redirect = $messages_page_url . '?conv=' . (int) $conv_id;
                    if ($filtre_inbox !== 'tout') {
                        $messages_compose_redirect .= '&filtre=' . rawurlencode($filtre_inbox);
                    }
                ?>
                <?php include __DIR__ . '/../includes/partials/messages_vendeur_thread_client.php'; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php require_once __DIR__ . '/../includes/flash_toast.php'; flash_toast_render(); ?>

    <?php
    $enable_firebase_notifications = true;
    $firebase_notify_type = 'user';
    include __DIR__ . '/../includes/firebase_notifications_scripts.php';
    ?>
    <script src="<?php echo htmlspecialchars(app_url('/js/messages-vendeur-ui.js') . asset_version_query(), ENT_QUOTES, 'UTF-8'); ?>"></script>
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
        role: 'client',
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
        <?php if ($show_negocier && $can_negocier): ?>
        var offerModal = document.getElementById('msgOfferModal');
        if (offerModal && typeof window.msgOfferModalOpen === 'function') {
            window.msgOfferModalOpen();
        }
        <?php endif; ?>
    })();
    </script>
</body>
</html>
