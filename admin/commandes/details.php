<?php
/**
 * Page de détails d'une commande (Admin)
 * Programmation procédurale uniquement
 */

ob_start();

require_once __DIR__ . '/../includes/require_admin_session.php';

// Récupérer l'ID de la commande (GET ou POST — formulaires de statut)
$commande_id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;

if ($commande_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

// Récupérer la commande et ses produits
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_factures.php';
require_once __DIR__ . '/../../includes/format_commande_options.php';
require_once __DIR__ . '/../../includes/commande_suivi_ui.php';
require_once __DIR__ . '/../../includes/image_optimizer.php';
$commande = get_commande_by_id($commande_id);
$produits = get_produits_by_commande($commande_id);
$produits = is_array($produits) ? $produits : [];
$facture = get_facture_by_commande($commande_id);

if (!$commande) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_route_access.php';
$vf_ck = admin_vendeur_filter_id();
if ($vf_ck !== null && (int) ($commande['vendeur_id'] ?? 0) !== $vf_ck) {
    header('Location: index.php');
    exit;
}

$statut_raw = commande_statut_ui_normalize((string) ($commande['statut'] ?? ''));
$statut_safe_class = htmlspecialchars($statut_raw, ENT_QUOTES, 'UTF-8');
$statut_display = ucfirst(str_replace('_', ' ', $statut_raw));
if ($statut_raw === 'annulee') {
    $statut_display = 'Annulée';
} elseif ($statut_raw === 'paye') {
    $statut_display = 'Payée';
}

// Vérifier si la commande est annulée ou livrée (pas de modification possible)
$is_annulee = $commande['statut'] === 'annulee';
$is_livree = $commande['statut'] === 'livree';
$is_paye = $commande['statut'] === 'paye';
$peut_annuler = !$is_annulee && !$is_livree && !$is_paye;
// Facture : à partir de « En livraison » (ou livrée / payée)
$peut_facture = in_array($statut_raw, ['livraison_en_cours', 'expediee', 'livree', 'paye'], true);

// Traiter les actions de statut (uniquement si la commande n'est pas annulée)
$statut_flash_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_annulee && $commande_id > 0) {
    $statut_mis_a_jour = null;

    $admin_traitant = (int) ($_SESSION['admin_id'] ?? 0);

    if (isset($_POST['annuler_commande']) && $peut_annuler) {
        if (update_commande_statut($commande_id, 'annulee', $admin_traitant)) {
            $statut_mis_a_jour = 'annulee';
        }
    } elseif (isset($_POST['prendre_en_charge'])) {
        if (update_commande_statut($commande_id, 'prise_en_charge', $admin_traitant)) {
            $statut_mis_a_jour = 'prise_en_charge';
        }
    } elseif (isset($_POST['expedier'])) {
        if (update_commande_statut($commande_id, 'livraison_en_cours', $admin_traitant)) {
            $statut_mis_a_jour = 'livraison_en_cours';
        }
    } elseif (isset($_POST['confirmer_livraison'])) {
        if (update_commande_statut($commande_id, 'livree', $admin_traitant)) {
            $statut_mis_a_jour = 'livree';
        }
    }

    if ($statut_mis_a_jour !== null) {
        try {
            require_once __DIR__ . '/../../services/send_commande_notification.php';
            send_commande_status_notification(
                (int) ($commande['user_id'] ?? 0),
                $commande['numero_commande'],
                $statut_mis_a_jour,
                trim($commande['user_email'] ?? '')
            );
        } catch (Throwable $e) {
            error_log('[commandes/details] notification : ' . $e->getMessage());
        }

        $_SESSION['success_message'] = $statut_mis_a_jour === 'annulee'
            ? 'Commande annulée. Le client a été notifié.'
            : 'Statut de la commande mis à jour avec succès. Le client a été notifié.';
        if (!function_exists('app_url')) {
            require_once __DIR__ . '/../../includes/app_base.php';
        }
        header('Location: ' . app_url('/admin/commandes/details.php?id=' . (int) $commande_id));
        exit;
    }

    $statut_flash_err = isset($_POST['annuler_commande'])
        ? 'Impossible d’annuler cette commande.'
        : 'Impossible de mettre à jour le statut. Vérifiez que la commande est dans un état compatible.';
    $err_sql = function_exists('commande_statut_update_last_error') ? commande_statut_update_last_error() : '';
    if ($err_sql !== '') {
        $statut_flash_err .= ' Détail : ' . $err_sql;
    }
    $commande = get_commande_by_id($commande_id);
    if ($commande) {
        $statut_raw = commande_statut_ui_normalize((string) ($commande['statut'] ?? ''));
        $statut_safe_class = htmlspecialchars($statut_raw, ENT_QUOTES, 'UTF-8');
        $statut_display = ucfirst(str_replace('_', ' ', $statut_raw));
        if ($statut_raw === 'annulee') {
            $statut_display = 'Annulée';
        } elseif ($statut_raw === 'paye') {
            $statut_display = 'Payée';
        }
        $is_annulee = $commande['statut'] === 'annulee';
        $is_livree = $commande['statut'] === 'livree';
        $is_paye = $commande['statut'] === 'paye';
        $peut_annuler = !$is_annulee && !$is_livree && !$is_paye;
        $peut_facture = in_array($statut_raw, ['livraison_en_cours', 'expediee', 'livree', 'paye'], true);
    }
}

$detail_form_action = 'details.php?id=' . (int) $commande_id;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Commande #<?php echo htmlspecialchars($commande['numero_commande'] ?? ''); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-suivi-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-commandes-details.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="contents-container cmd-detail-page od-page">
        <?php
        if (!function_exists('commande_date_evenement_affiche')) {
            require_once __DIR__ . '/../../includes/commande_card_helpers.php';
        }
        require_once __DIR__ . '/../../includes/commande_mode_helpers.php';
        require_once __DIR__ . '/../../includes/geo_location_service.php';

        $od_retrait = commande_is_retrait($commande);
        $od_num = (string) ($commande['numero_commande'] ?? '');
        $od_client = commande_client_display_name($commande);
        $od_initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($od_client !== '' ? $od_client : 'C', 0, 1, 'UTF-8'), 'UTF-8') : 'C';
        $od_tel = commande_card_telephone($commande, 'vendor');
        $od_tel_digits = preg_replace('/\D+/', '', (string) $od_tel);
        /* Numéro international pour tel: / WhatsApp (numéros locaux sénégalais à 9 chiffres) */
        $od_tel_intl = strlen($od_tel_digits) === 9 ? '221' . $od_tel_digits : ltrim($od_tel_digits, '0');
        $od_addr = trim((string) ($commande['adresse_livraison'] ?? ''));
        $od_notes = trim((string) ($commande['notes'] ?? ''));
        $od_fmt_date = function ($raw) {
            $ts = $raw ? strtotime((string) $raw) : false;
            return $ts ? date('d/m/Y à H:i', $ts) : '';
        };
        $od_date_recue = $od_fmt_date($commande['date_commande'] ?? '');
        $od_date_livree = $od_fmt_date($commande['date_livraison'] ?? '');
        $od_date_annulee = $od_fmt_date($commande['date_annulation'] ?? '');

        /* Étapes : 0 reçue, 1 préparation, 2 en livraison / prête, 3 livrée / retirée */
        $od_order = [
            'en_attente' => 0, 'confirmee' => 0,
            'prise_en_charge' => 1, 'en_preparation' => 1,
            'livraison_en_cours' => 2, 'expediee' => 2,
            'livree' => 3, 'paye' => 3,
        ];
        $od_idx = $od_order[$statut_raw] ?? 0;
        $od_done_all = $is_livree || $is_paye;
        $od_steps = [
            ['Reçue', 'fa-receipt', $od_date_recue],
            ['En préparation', 'fa-box-open', ''],
            [$od_retrait ? 'Prête au retrait' : 'En livraison', $od_retrait ? 'fa-store' : 'fa-truck-fast', ''],
            [$od_retrait ? 'Retirée' : 'Livrée', 'fa-house-circle-check', $od_date_livree],
        ];

        /* Une seule action proposée à chaque étape */
        $od_next = null;
        if (!$is_annulee && !$od_done_all) {
            if ($od_idx === 0) {
                $od_next = [
                    'name' => 'prendre_en_charge',
                    'title' => 'Nouvelle commande à accepter',
                    'text' => 'Vérifiez les articles puis acceptez.',
                    'btn' => 'Accepter la commande',
                    'icon' => 'fa-check',
                ];
            } elseif ($od_idx === 1) {
                $od_next = $od_retrait
                    ? [
                        'name' => 'expedier',
                        'title' => 'Préparez la commande',
                        'text' => 'Quand la commande est emballée et prête en boutique, prévenez le client qu’il peut venir la récupérer.',
                        'btn' => 'Commande prête au retrait',
                        'icon' => 'fa-store',
                    ]
                    : [
                        'name' => 'expedier',
                        'title' => 'Préparez puis expédiez',
                        'text' => 'Emballez la commande et confiez-la au livreur. Le client sera prévenu qu’elle est en route.',
                        'btn' => 'Mettre en livraison',
                        'icon' => 'fa-truck-fast',
                    ];
            } else {
                $od_next = [
                    'name' => 'confirmer_livraison',
                    'title' => $od_retrait ? 'En attente du client' : 'Commande en route',
                    'text' => $od_retrait
                        ? 'Quand le client a récupéré sa commande en boutique, confirmez le retrait.'
                        : 'Quand le client a bien reçu son colis, confirmez la livraison pour clôturer la commande.',
                    'btn' => $od_retrait ? 'Confirmer le retrait' : 'Confirmer la livraison',
                    'icon' => 'fa-circle-check',
                ];
            }
        }

        $od_status_label = [
            0 => 'Nouvelle',
            1 => 'En préparation',
            2 => $od_retrait ? 'Prête au retrait' : 'En livraison',
            3 => $od_retrait ? 'Retirée' : 'Livrée',
        ][$od_idx];
        if ($is_paye) {
            $od_status_label = 'Payée';
        }
        if ($is_annulee) {
            $od_status_label = 'Annulée';
        }
        $od_status_tone = $is_annulee ? 'danger' : ($od_done_all ? 'success' : ($od_idx === 0 ? 'warn' : 'info'));
        ?>

        <header class="vd-page-head od-head">
            <div class="vd-page-head__text">
                <a href="index.php" class="vd-page-head__back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Commandes</a>
                <h1 class="vd-page-head__title" id="cmd-detail-title">
                    Commande <span class="od-head__num">#<?php echo htmlspecialchars($od_num, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="od-pill od-pill--<?php echo $od_status_tone; ?>"><?php echo htmlspecialchars($od_status_label, ENT_QUOTES, 'UTF-8'); ?></span>
                </h1>
                <p class="vd-page-head__sub">
                    <?php echo htmlspecialchars(commande_date_evenement_affiche($commande), ENT_QUOTES, 'UTF-8'); ?>
                    · <?php echo $od_retrait ? 'Retrait en boutique' : 'Livraison à domicile'; ?>
                    · <strong><?php echo number_format((float) ($commande['montant_total'] ?? 0), 0, ',', ' '); ?> FCFA</strong>
                </p>
            </div>
            <?php if ($peut_facture || $peut_annuler): ?>
            <div class="vd-page-head__actions od-head__actions">
                <?php if ($peut_facture): ?>
                    <?php if ($facture): ?>
                    <a href="facture.php?id=<?php echo (int) $facture['id']; ?>"
                        class="vd-btn vd-btn--ghost" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-file-invoice" aria-hidden="true"></i><span>Facture</span>
                    </a>
                    <?php else: ?>
                    <form method="post" action="generer_facture.php" style="display:inline;margin:0;">
                        <?php echo admin_csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $commande_id; ?>">
                        <button type="submit" class="vd-btn vd-btn--ghost">
                            <i class="fas fa-file-invoice" aria-hidden="true"></i><span>Facture</span>
                        </button>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($peut_annuler): ?>
                <form method="post" action="<?php echo htmlspecialchars($detail_form_action, ENT_QUOTES, 'UTF-8'); ?>" class="od-cancel-form" id="cmdCancelForm">
                    <input type="hidden" name="id" value="<?php echo (int) $commande_id; ?>">
                    <input type="hidden" name="annuler_commande" value="1">
                    <button type="button" class="vd-btn vd-btn--ghost od-btn-danger" id="cmdCancelOpen" aria-haspopup="dialog" aria-controls="cmdCancelModal">
                        <i class="fas fa-ban" aria-hidden="true"></i><span>Annuler</span>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </header>

    <?php if ($statut_flash_err !== ''): ?>
        <div class="od-alert" role="alert">
            <i class="fas fa-circle-exclamation" aria-hidden="true"></i> <?php echo htmlspecialchars($statut_flash_err, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php /* success / error affichés via flash toast — voir footer.php */ ?>

    <div class="od-layout">
        <div class="od-main">

            <section class="od-card od-flow<?php echo $is_annulee ? ' od-flow--cancelled' : ''; ?>" aria-labelledby="odFlowTitle">
                <h2 class="od-card__title" id="odFlowTitle">Suivi de la commande</h2>

                <?php if ($is_annulee): ?>
                    <div class="od-final od-final--danger">
                        <span class="od-final__icon"><i class="fas fa-ban" aria-hidden="true"></i></span>
                        <div>
                            <strong>Commande annulée</strong>
                            <p><?php echo $od_date_annulee !== '' ? 'Annulée le ' . htmlspecialchars($od_date_annulee, ENT_QUOTES, 'UTF-8') . '. ' : ''; ?>Le client a été prévenu.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <ol class="od-steps" aria-label="Étapes de la commande">
                        <?php foreach ($od_steps as $si => $step):
                            $s_state = ($od_done_all || $si < $od_idx) ? 'done' : ($si === $od_idx ? 'current' : 'todo');
                        ?>
                        <li class="od-step od-step--<?php echo $s_state; ?>"<?php echo $s_state === 'current' ? ' aria-current="step"' : ''; ?>>
                            <span class="od-step__dot" aria-hidden="true">
                                <i class="fas <?php echo $s_state === 'done' ? 'fa-check' : $step[1]; ?>"></i>
                            </span>
                            <span class="od-step__label"><?php echo htmlspecialchars($step[0], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($step[2] !== '' && $s_state === 'done'): ?>
                                <span class="od-step__date"><?php echo htmlspecialchars($step[2], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ol>

                    <?php if ($od_next !== null): ?>
                        <div class="od-next">
                            <div class="od-next__text">
                                <span class="od-next__eyebrow">Prochaine étape</span>
                                <strong class="od-next__title"><?php echo htmlspecialchars($od_next['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <p class="od-next__desc"><?php echo htmlspecialchars($od_next['text'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <form method="POST" action="<?php echo htmlspecialchars($detail_form_action, ENT_QUOTES, 'UTF-8'); ?>" class="od-next__form">
                                <input type="hidden" name="id" value="<?php echo (int) $commande_id; ?>">
                                <button type="submit" name="<?php echo htmlspecialchars($od_next['name'], ENT_QUOTES, 'UTF-8'); ?>" value="1" class="od-next__btn">
                                    <i class="fas <?php echo htmlspecialchars($od_next['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars($od_next['btn'], ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                                <span class="od-next__hint"><i class="fas fa-bell" aria-hidden="true"></i> Le client est prévenu automatiquement</span>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="od-final od-final--success">
                            <span class="od-final__icon"><i class="fas fa-circle-check" aria-hidden="true"></i></span>
                            <div>
                                <strong><?php echo $is_paye ? 'Commande payée et terminée' : ($od_retrait ? 'Commande retirée par le client' : 'Commande livrée au client'); ?></strong>
                                <p><?php echo $od_date_livree !== '' ? 'Le ' . htmlspecialchars($od_date_livree, ENT_QUOTES, 'UTF-8') . '. ' : ''; ?>Plus aucune action n’est nécessaire.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

    <section class="od-card cmd-products-section" aria-labelledby="cmd-products-heading">
        <div class="cmd-products-section__head">
            <div class="cmd-products-section__head-left">
                <div class="cmd-products-section__icon" aria-hidden="true"><i class="fas fa-receipt"></i></div>
                <div>
                    <h2 id="cmd-products-heading">Lignes de commande</h2>
                    <p class="cmd-products-section__sub"><?php echo count($produits); ?> article<?php echo count($produits) > 1 ? 's' : ''; ?></p>
                </div>
            </div>
        </div>

        <div class="cmd-products-list">
            <?php if (empty($produits)): ?>
            <div class="cmd-products-empty">
                <i class="fas fa-box-open" aria-hidden="true"></i>
                <p>Aucune ligne produit enregistrée pour cette commande.</p>
            </div>
            <?php else: ?>
            <?php foreach ($produits as $idx => $produit): ?>
                <?php
                $img_src = !empty($produit['image_afficher']) ? $produit['image_afficher'] : ($produit['image_principale'] ?? '');
                $nom_affichage = !empty($produit['variante_nom']) ? $produit['produit_nom'] . ' → ' . $produit['variante_nom'] : ($produit['produit_nom'] ?? '');
                ?>
                <div class="cpd-row">
                    <div class="cpd-row__num" aria-hidden="true"><?php echo $idx + 1; ?></div>
                    <div class="cpd-row__img-wrap">
                        <img src="<?php echo htmlspecialchars(upload_image_url($img_src ?? '', 'sm')); ?>"
                            alt="<?php echo htmlspecialchars($nom_affichage ?? ''); ?>"
                            class="cpd-row__img"
                            onerror="this.src='/image/produit1.jpg'">
                    </div>
                    <div class="cpd-row__body">
                        <p class="cpd-row__name"><?php echo htmlspecialchars($nom_affichage ?? ''); ?></p>
                        <div class="cpd-row__chips">
                            <span class="cpd-chip cpd-chip--qty">
                                <i class="fas fa-cubes"></i> <?php echo (int)$produit['quantite']; ?> pièce<?php echo $produit['quantite'] > 1 ? 's' : ''; ?>
                            </span>
                            <span class="cpd-chip cpd-chip--unit">
                                <?php echo number_format((float)$produit['prix_unitaire'], 0, ',', ' '); ?> FCFA / u
                            </span>
                            <?php if (!empty($produit['couleur'])): ?>
                            <?php
                            $hex = trim($produit['couleur']);
                            $is_hex = preg_match('/^#[0-9A-Fa-f]{6}$/', $hex);
                            $nom_couleur = format_couleur_commande($hex);
                            ?>
                            <span class="cpd-chip cpd-chip--color">
                                <?php if ($is_hex): ?>
                                <span class="cpd-chip__swatch" style="background:<?php echo htmlspecialchars($hex); ?>;"></span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($nom_couleur); ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($produit['variante_nom'])): ?>
                            <span class="cpd-chip cpd-chip--variant"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($produit['variante_nom']); ?></span>
                            <?php endif; ?>
                            <?php
                            $poids_raw = $produit['poids'] ?? '';
                            $taille_raw = $produit['taille'] ?? '';
                            $surcout_p = isset($produit['surcout_poids']) ? (float)$produit['surcout_poids'] : 0;
                            $surcout_t = isset($produit['surcout_taille']) ? (float)$produit['surcout_taille'] : 0;
                            $poids_lignes = parse_poids_taille_commande($poids_raw, $surcout_p);
                            $taille_lignes = parse_poids_taille_commande($taille_raw, $surcout_t);
                            foreach ($poids_lignes as $opt): ?>
                            <span class="cpd-chip cpd-chip--poids"><i class="fas fa-weight-hanging"></i> <?php echo htmlspecialchars($opt['v'] ?? ''); ?><?php if (($opt['s'] ?? 0) > 0) echo ' (+' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)'; ?></span>
                            <?php endforeach; ?>
                            <?php foreach ($taille_lignes as $opt): ?>
                            <span class="cpd-chip cpd-chip--taille"><i class="fas fa-ruler"></i> <?php echo htmlspecialchars($opt['v'] ?? ''); ?><?php if (($opt['s'] ?? 0) > 0) echo ' (+' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)'; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="cpd-row__total">
                        <span><?php echo number_format((float)$produit['prix_total'], 0, ',', ' '); ?></span>
                        <small>FCFA</small>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="cpd-totals">
                <?php
                $sous_total = array_sum(array_column($produits, 'prix_total'));
                $frais = isset($commande['frais_livraison']) ? (float) $commande['frais_livraison'] : 0;
                ?>
                <?php if ($frais > 0): ?>
                <div class="cpd-totals__line">
                    <span><i class="fas fa-box-open"></i> Sous-total produits</span>
                    <strong><?php echo number_format($sous_total, 0, ',', ' '); ?> FCFA</strong>
                </div>
                <div class="cpd-totals__line">
                    <span><i class="fas fa-truck"></i> Frais de livraison</span>
                    <strong><?php echo number_format($frais, 0, ',', ' '); ?> FCFA</strong>
                </div>
                <div class="cpd-totals__divider"></div>
                <?php endif; ?>
                <div class="cpd-totals__grand">
                    <span>Total commande</span>
                    <div class="cpd-totals__grand-value">
                        <?php echo number_format((float)$commande['montant_total'], 0, ',', ' '); ?>
                        <small>FCFA</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

        </div><!-- /.od-main -->

        <aside class="od-aside" aria-label="Client">
            <section class="od-card od-client" aria-labelledby="odClientTitle">
                <h2 class="od-card__title" id="odClientTitle">Client</h2>
                <div class="od-client__id">
                    <span class="od-client__avatar" aria-hidden="true"><?php echo htmlspecialchars($od_initial, ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="od-client__who">
                        <strong><?php echo htmlspecialchars($od_client !== '' ? $od_client : 'Client', ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php if ($od_tel !== ''): ?><span><?php echo htmlspecialchars($od_tel, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                    </div>
                </div>
                <?php if ($od_tel_digits !== ''): ?>
                <div class="od-client__actions">
                    <a class="od-client__btn" href="tel:+<?php echo htmlspecialchars($od_tel_intl, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-phone" aria-hidden="true"></i> Appeler
                    </a>
                    <a class="od-client__btn od-client__btn--wa" href="https://wa.me/<?php echo htmlspecialchars($od_tel_intl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i> WhatsApp
                    </a>
                </div>
                <?php endif; ?>

                <div class="od-client__block">
                    <span class="od-client__label"><?php echo $od_retrait ? 'Mode de remise' : 'Adresse de livraison'; ?></span>
                    <?php if ($od_retrait): ?>
                        <p class="od-client__text"><i class="fas fa-store" aria-hidden="true"></i> Le client vient récupérer sa commande en boutique.</p>
                    <?php elseif ($od_addr !== ''): ?>
                        <p class="od-client__text"><i class="fas fa-location-dot" aria-hidden="true"></i> <?php echo nl2br(htmlspecialchars($od_addr, ENT_QUOTES, 'UTF-8')); ?></p>
                    <?php else: ?>
                        <p class="od-client__text od-client__text--muted">Adresse non renseignée</p>
                    <?php endif; ?>
                </div>

                <?php
                if (!$od_retrait):
                    $geo_cmd_lat = geo_parse_coord($commande['delivery_latitude'] ?? null);
                    $geo_cmd_lng = geo_parse_coord($commande['delivery_longitude'] ?? null);
                    if (geo_coords_valid($geo_cmd_lat, $geo_cmd_lng)):
                        $geo_distance_txt = '';
                        if (!empty($commande['vendeur_id']) && geo_boutiques_ready()) {
                            try {
                                $stmt_geo_b = $db->prepare("SELECT boutique_latitude, boutique_longitude FROM admin WHERE id = :id");
                                $stmt_geo_b->execute(['id' => (int) $commande['vendeur_id']]);
                                $geo_b = $stmt_geo_b->fetch(PDO::FETCH_ASSOC);
                                $geo_b_lat = $geo_b ? geo_parse_coord($geo_b['boutique_latitude']) : null;
                                $geo_b_lng = $geo_b ? geo_parse_coord($geo_b['boutique_longitude']) : null;
                                if (geo_coords_valid($geo_b_lat, $geo_b_lng)) {
                                    $geo_distance_txt = geo_format_distance(geo_distance_km($geo_b_lat, $geo_b_lng, $geo_cmd_lat, $geo_cmd_lng));
                                }
                            } catch (PDOException $e) { /* distance facultative */ }
                        }
                ?>
                <div class="od-client__block od-client__geo">
                    <span class="od-client__label">Position GPS du client</span>
                    <?php if ($geo_distance_txt !== ''): ?>
                        <p class="od-client__text"><i class="fas fa-route" aria-hidden="true"></i> À <?php echo htmlspecialchars($geo_distance_txt, ENT_QUOTES, 'UTF-8'); ?> de votre boutique</p>
                    <?php endif; ?>
                    <?php
                    $geo_nav_lat = $geo_cmd_lat;
                    $geo_nav_lng = $geo_cmd_lng;
                    $geo_nav_label = 'Commande #' . $od_num;
                    include __DIR__ . '/../../includes/partials/geo_nav_apps_buttons.php';
                    ?>
                </div>
                <?php
                    endif;
                endif;
                ?>
            </section>

            <?php if ($od_notes !== ''): ?>
            <section class="od-card od-notes" aria-labelledby="odNotesTitle">
                <h2 class="od-card__title" id="odNotesTitle"><i class="fas fa-comment-dots" aria-hidden="true"></i> Note du client</h2>
                <p><?php echo nl2br(htmlspecialchars($od_notes, ENT_QUOTES, 'UTF-8')); ?></p>
            </section>
            <?php endif; ?>
        </aside>
    </div><!-- /.od-layout -->

    </div>

    <?php if ($peut_annuler): ?>
    <div class="cmd-cancel-modal" id="cmdCancelModal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="cmdCancelModalTitle">
        <div class="cmd-cancel-modal__backdrop" data-cmd-cancel-close tabindex="-1"></div>
        <div class="cmd-cancel-modal__sheet">
            <h2 class="cmd-cancel-modal__title" id="cmdCancelModalTitle">Annuler la commande ?</h2>
            <p class="cmd-cancel-modal__text">Cette action est définitive. Le client sera notifié de l’annulation.</p>
            <div class="cmd-cancel-modal__actions">
                <button type="button" class="cmd-cancel-modal__btn cmd-cancel-modal__btn--no" data-cmd-cancel-close>Non</button>
                <button type="button" class="cmd-cancel-modal__btn cmd-cancel-modal__btn--yes" id="cmdCancelConfirm">Oui</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var modal = document.getElementById('cmdCancelModal');
        var openBtn = document.getElementById('cmdCancelOpen');
        var form = document.getElementById('cmdCancelForm');
        var yesBtn = document.getElementById('cmdCancelConfirm');
        if (!modal || !openBtn || !form || !yesBtn) return;

        function openModal() {
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
        }
        function closeModal() {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
        }

        openBtn.addEventListener('click', openModal);
        yesBtn.addEventListener('click', function () { form.submit(); });
        modal.addEventListener('click', function (e) {
            if (e.target.closest('[data-cmd-cancel-close]')) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) closeModal();
        });
    })();
    </script>
    <?php endif; ?>

    <?php require __DIR__ . '/../../includes/partials/platform_share_modal.php'; ?>
    <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-nav-apps.js<?php echo asset_version_query(); ?>"></script>

    <?php include '../includes/footer.php'; ?>