<?php
/**
 * Suivi de commande côté client : carte d'état, étapes datées, articles, vendeur, notation.
 *
 * Variables attendues : $commande, $produits_commande, $commande_id, $can_cancel_suivi,
 *                       $suivi_confirm_success (bool).
 */
if (empty($commande) || !is_array($commande)) {
    return;
}
if (!function_exists('upload_image_url_from_src')) {
    require_once dirname(__DIR__) . '/image_optimizer.php';
}
if (!function_exists('commande_is_retrait')) {
    require_once dirname(__DIR__) . '/commande_mode_helpers.php';
}
if (!function_exists('site_csrf_field')) {
    require_once dirname(__DIR__) . '/site_csrf.php';
}
if (!function_exists('get_admin_by_id')) {
    require_once dirname(__DIR__, 2) . '/models/model_admin.php';
}
if (!function_exists('produits_avis_commande_a_noter')) {
    require_once dirname(__DIR__, 2) . '/models/model_produits_avis.php';
}

$ot_st = (string) ($commande['statut'] ?? 'en_attente');
$ot_retrait = commande_is_retrait($commande);
$ot_num = (string) ($commande['numero_commande'] ?? '');
$ot_cancelled = $ot_st === 'annulee';
$ot_done = in_array($ot_st, ['livree', 'paye'], true);
$ot_rank = [
    'en_attente' => 0, 'confirmee' => 0,
    'prise_en_charge' => 1, 'en_preparation' => 1,
    'livraison_en_cours' => 2, 'expediee' => 2,
    'livree' => 3, 'paye' => 3,
][$ot_st] ?? 0;

$ot_time = function ($raw) {
    $ts = $raw ? strtotime((string) $raw) : false;
    if (!$ts) {
        return '';
    }
    return date('d/m', $ts) === date('d/m') ? 'Aujourd’hui · ' . date('H:i', $ts) : date('d/m · H:i', $ts);
};

/* Vendeur */
$ot_vendeur = !empty($commande['vendeur_id']) ? get_admin_by_id((int) $commande['vendeur_id']) : null;
$ot_boutique = is_array($ot_vendeur) ? trim((string) ($ot_vendeur['boutique_nom'] ?? '')) : '';
if ($ot_boutique === '') {
    $ot_boutique = 'Votre vendeur';
}
$ot_logo = (is_array($ot_vendeur) && !empty($ot_vendeur['boutique_logo'])) ? upload_image_url((string) $ot_vendeur['boutique_logo'], 'sm') : '';
$ot_tel = is_array($ot_vendeur) ? preg_replace('/\D+/', '', (string) ($ot_vendeur['telephone'] ?? '')) : '';
if (strlen($ot_tel) === 9) {
    $ot_tel = '221' . $ot_tel;
}
$ot_first_pid = 0;
foreach ($produits_commande as $p) {
    if (!empty($p['produit_id'])) {
        $ot_first_pid = (int) $p['produit_id'];
        break;
    }
}
$ot_msg_href = $ot_first_pid > 0 ? 'messages.php?produit_id=' . $ot_first_pid : 'messages.php';

/* Étapes */
$ot_steps = [
    ['Commande passée', 'La boutique a bien reçu votre commande', $commande['date_commande'] ?? null],
    ['En préparation', $ot_boutique . ' prépare vos articles', $commande['date_prise_en_charge'] ?? null],
    $ot_retrait
        ? ['Prête au retrait', 'Vous pouvez passer la récupérer en boutique', $commande['date_expedition'] ?? null]
        : ['En livraison', 'Votre colis est en route vers vous', $commande['date_expedition'] ?? null],
    [$ot_retrait ? 'Récupérée' : 'Livrée', $ot_retrait ? 'Commande remise en main propre' : 'Bonne réception !', $commande['date_livraison'] ?? null],
];

/* Carte d'état */
if ($ot_cancelled) {
    $ot_tone = 'cancel';
    $ot_pill = 'Annulée';
    $ot_label = 'Statut';
    $ot_title = 'Commande annulée';
    $ot_sub = $ot_time($commande['date_annulation'] ?? null) !== '' ? 'Le ' . $ot_time($commande['date_annulation']) : 'Cette commande ne sera pas livrée';
    $ot_icon = 'fa-xmark';
    $ot_progress = 0;
} elseif ($ot_done) {
    $ot_tone = 'done';
    $ot_pill = 'Terminée';
    $ot_label = $ot_retrait ? 'Récupérée' : 'Livrée';
    $ot_title = 'Bonne réception !';
    $ot_sub = $ot_time($commande['date_livraison'] ?? null) !== '' ? ($ot_retrait ? 'Récupérée le ' : 'Livrée le ') . $ot_time($commande['date_livraison']) : 'Commande terminée';
    $ot_icon = 'fa-check';
    $ot_progress = 100;
} else {
    $ot_tone = 'live';
    $ot_pill = 'En direct';
    $ot_label = 'Étape ' . ($ot_rank + 1) . ' sur 4';
    $ot_title = [
        0 => 'Commande reçue',
        1 => 'En préparation',
        2 => $ot_retrait ? 'Prête au retrait' : 'En route',
    ][$ot_rank];
    $ot_sub = $ot_steps[$ot_rank][1];
    $ot_icon = [0 => 'fa-receipt', 1 => 'fa-box-open', 2 => $ot_retrait ? 'fa-store' : 'fa-truck-fast'][$ot_rank];
    $ot_progress = [0 => 12, 1 => 45, 2 => 78][$ot_rank];
}

/* Articles */
$ot_count = 0;
foreach ($produits_commande as $p) {
    $ot_count += max(1, (int) ($p['quantite'] ?? 1));
}
$ot_total = (float) ($commande['montant_total'] ?? 0);

/* Notation (après livraison) */
$ot_can_rate = $ot_done && !empty($_SESSION['user_id']) && produits_avis_commande_a_noter((int) $_SESSION['user_id'], (int) $commande_id);
$ot_rated = $ot_done && !$ot_can_rate;
$ot_ring = 2 * M_PI * 34;
?>
<div class="ot" data-commande="<?php echo (int) $commande_id; ?>">

    <header class="ot-top">
        <a href="mes-commandes.php" class="ot-top__btn" aria-label="Retour à mes commandes"><i class="fas fa-chevron-left" aria-hidden="true"></i></a>
        <div class="ot-top__text">
            <h1 class="ot-top__title">Commande #<?php echo htmlspecialchars($ot_num, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="ot-top__sub"><?php echo htmlspecialchars($ot_boutique, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <a href="<?php echo htmlspecialchars($ot_msg_href, ENT_QUOTES, 'UTF-8'); ?>" class="ot-top__btn" aria-label="Écrire à la boutique"><i class="fas fa-headset" aria-hidden="true"></i></a>
    </header>

    <div class="ot-layout">
    <div class="ot-col">

    <section class="ot-hero ot-hero--<?php echo $ot_tone; ?>" aria-live="polite">
        <div class="ot-hero__text">
            <span class="ot-hero__pill"><span aria-hidden="true"></span><?php echo htmlspecialchars($ot_pill, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="ot-hero__label"><?php echo htmlspecialchars($ot_label, ENT_QUOTES, 'UTF-8'); ?></span>
            <strong class="ot-hero__title"><?php echo htmlspecialchars($ot_title, ENT_QUOTES, 'UTF-8'); ?></strong>
            <span class="ot-hero__bar" aria-hidden="true"><span style="width: <?php echo (int) $ot_progress; ?>%"></span></span>
            <span class="ot-hero__sub"><?php echo htmlspecialchars($ot_sub, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <span class="ot-hero__ring" aria-hidden="true">
            <svg viewBox="0 0 80 80" width="80" height="80">
                <circle cx="40" cy="40" r="34" class="ot-hero__ring-bg"/>
                <circle cx="40" cy="40" r="34" class="ot-hero__ring-fg"
                    stroke-dasharray="<?php echo round($ot_ring, 2); ?>"
                    stroke-dashoffset="<?php echo round($ot_ring * (1 - ($ot_cancelled ? 1 : $ot_progress) / 100), 2); ?>"/>
            </svg>
            <span class="ot-hero__ring-icon"><i class="fas <?php echo $ot_icon; ?>"></i></span>
        </span>
    </section>

    <?php if (!$ot_cancelled): ?>
    <section class="ot-card" aria-labelledby="otProgressTitle">
        <h2 class="ot-card__title" id="otProgressTitle">Progression</h2>
        <ol class="ot-steps">
            <?php foreach ($ot_steps as $i => $step):
                $state = ($ot_done || $i < $ot_rank) ? 'done' : ($i === $ot_rank ? 'now' : 'todo');
                $when = $state === 'todo' ? '' : $ot_time($step[2]);
            ?>
            <li class="ot-step ot-step--<?php echo $state; ?>"<?php echo $state === 'now' ? ' aria-current="step"' : ''; ?>>
                <span class="ot-step__dot" aria-hidden="true"><?php if ($state === 'done'): ?><i class="fas fa-check"></i><?php endif; ?></span>
                <span class="ot-step__text">
                    <strong><?php echo htmlspecialchars($step[0], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?php echo htmlspecialchars($step[1], ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
                <span class="ot-step__meta">
                    <?php if ($state === 'done'): ?>
                        <span class="ot-step__pill">Fait</span>
                    <?php elseif ($state === 'now'): ?>
                        <span class="ot-step__pill ot-step__pill--now"><?php echo $i === 0 ? 'En attente' : 'En cours'; ?></span>
                    <?php endif; ?>
                    <?php if ($when !== ''): ?><span class="ot-step__time"><?php echo htmlspecialchars($when, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <?php endif; ?>

    </div>
    <div class="ot-col">

    <?php if (!empty($produits_commande)): ?>
    <button type="button" class="ot-card ot-order" id="ccOpenProducts" aria-haspopup="dialog">
        <span class="ot-order__head">
            <span class="ot-card__title">Votre commande</span>
            <span class="ot-order__meta"><?php echo $ot_count; ?> article<?php echo $ot_count > 1 ? 's' : ''; ?> · <strong><?php echo number_format($ot_total, 0, ',', ' '); ?> FCFA</strong></span>
        </span>
        <span class="ot-order__thumbs">
            <?php foreach (array_slice($produits_commande, 0, 4) as $p):
                $img = !empty($p['image_afficher']) ? $p['image_afficher'] : ($p['image_principale'] ?? '');
            ?>
                <img src="<?php echo htmlspecialchars(upload_image_url_from_src((string) $img, 'sm'), ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" onerror="this.src='/image/produit1.jpg'">
            <?php endforeach; ?>
            <?php if (count($produits_commande) > 4): ?>
                <span class="ot-order__more">+<?php echo count($produits_commande) - 4; ?></span>
            <?php endif; ?>
            <i class="fas fa-chevron-right ot-order__chev" aria-hidden="true"></i>
        </span>
    </button>
    <?php endif; ?>

    <section class="ot-card ot-seller">
        <span class="ot-seller__logo" aria-hidden="true">
            <?php if ($ot_logo !== ''): ?>
                <img src="<?php echo htmlspecialchars($ot_logo, ENT_QUOTES, 'UTF-8'); ?>" alt="" onerror="this.remove()">
            <?php endif; ?>
            <span><?php echo htmlspecialchars(mb_strtoupper(mb_substr($ot_boutique, 0, 1, 'UTF-8'), 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if (!$ot_done && !$ot_cancelled): ?><i class="ot-seller__online"></i><?php endif; ?>
        </span>
        <span class="ot-seller__text">
            <strong><?php echo htmlspecialchars($ot_boutique, ENT_QUOTES, 'UTF-8'); ?> · Votre vendeur</strong>
            <span>
                <?php if ($ot_cancelled): ?>Commande annulée
                <?php elseif ($ot_done): ?>Merci d’avoir commandé chez <?php echo htmlspecialchars($ot_boutique, ENT_QUOTES, 'UTF-8'); ?>
                <?php elseif ($ot_rank === 2 && !$ot_retrait): ?>Votre colis est en route
                <?php else: ?>Une question ? Écrivez-lui<?php endif; ?>
            </span>
        </span>
        <span class="ot-seller__actions">
            <?php if ($ot_can_rate): ?>
                <button type="button" class="ot-rate-btn" data-ot-rate><i class="fas fa-star" aria-hidden="true"></i> Noter</button>
            <?php elseif ($ot_rated): ?>
                <span class="ot-rated"><i class="fas fa-circle-check" aria-hidden="true"></i> Noté</span>
            <?php else: ?>
                <a class="ot-icon-btn" href="<?php echo htmlspecialchars($ot_msg_href, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Écrire au vendeur"><i class="far fa-message"></i></a>
                <?php if ($ot_tel !== '' && !$ot_cancelled): ?>
                <a class="ot-icon-btn ot-icon-btn--dark" href="tel:+<?php echo htmlspecialchars($ot_tel, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Appeler le vendeur"><i class="fas fa-phone"></i></a>
                <?php endif; ?>
            <?php endif; ?>
        </span>
    </section>

    <?php if ($ot_st === 'livraison_en_cours'): ?>
    <form method="post" action="commande-categorie.php?commande_id=<?php echo (int) $commande_id; ?>" class="ot-confirm">
        <input type="hidden" name="commande_id" value="<?php echo (int) $commande_id; ?>">
        <?php echo site_csrf_field(); ?>
        <button type="submit" name="confirmer_livraison" value="1" class="ot-confirm__btn">
            <i class="fas fa-hand-holding-heart" aria-hidden="true"></i> J’ai reçu ma commande
        </button>
        <p class="ot-confirm__hint">À confirmer seulement quand le colis est entre vos mains.</p>
    </form>
    <?php endif; ?>

    <?php if (!empty($can_cancel_suivi)): ?>
    <form method="post" action="" class="uc-cancel-form ot-cancel">
        <input type="hidden" name="commande_id" value="<?php echo (int) $commande_id; ?>">
        <?php echo site_csrf_field(); ?>
        <button type="button" class="ot-cancel__btn uc-btn-open-cancel">Annuler la commande</button>
    </form>
    <?php endif; ?>

    </div>
    </div>

    <?php if ($ot_can_rate): ?>
    <div class="ot-sheet" id="otRateSheet" hidden role="dialog" aria-modal="true" aria-labelledby="otRateTitle"
        data-endpoint="avis-produits-action.php"
        data-products="<?php echo htmlspecialchars(implode(',', array_unique(array_filter(array_map(function ($p) { return (int) ($p['produit_id'] ?? 0); }, $produits_commande)))), ENT_QUOTES, 'UTF-8'); ?>"
        data-boutique="<?php echo htmlspecialchars($ot_boutique, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="ot-sheet__backdrop" data-ot-close></div>
        <div class="ot-sheet__panel">
            <span class="ot-sheet__handle" aria-hidden="true"></span>
            <div class="ot-sheet__step" data-step="rate">
                <span class="ot-seller__logo ot-seller__logo--lg" aria-hidden="true">
                    <?php if ($ot_logo !== ''): ?><img src="<?php echo htmlspecialchars($ot_logo, ENT_QUOTES, 'UTF-8'); ?>" alt="" onerror="this.remove()"><?php endif; ?>
                    <span><?php echo htmlspecialchars(mb_strtoupper(mb_substr($ot_boutique, 0, 1, 'UTF-8'), 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
                <strong class="ot-sheet__name"><?php echo htmlspecialchars($ot_boutique, ENT_QUOTES, 'UTF-8'); ?></strong>
                <span class="ot-sheet__sub"><?php echo htmlspecialchars($ot_sub, ENT_QUOTES, 'UTF-8'); ?></span>
                <h2 class="ot-sheet__q" id="otRateTitle">Comment s’est passée votre commande ?</h2>
                <div class="ot-stars" role="radiogroup" aria-label="Note sur 5">
                    <?php for ($n = 1; $n <= 5; $n++): ?>
                        <button type="button" class="ot-star" role="radio" aria-checked="false" data-note="<?php echo $n; ?>" aria-label="<?php echo $n; ?> étoile<?php echo $n > 1 ? 's' : ''; ?>"><i class="fas fa-star"></i></button>
                    <?php endfor; ?>
                </div>
                <div class="ot-mood" aria-live="polite"><span class="ot-mood__emoji"></span><span class="ot-mood__label">Touchez une étoile</span></div>
                <button type="button" class="ot-submit" data-ot-submit disabled>Envoyer ma note</button>
                <button type="button" class="ot-later" data-ot-close>Plus tard</button>
            </div>
            <div class="ot-sheet__step" data-step="thanks" hidden>
                <span class="ot-thanks__check" aria-hidden="true"><i class="fas fa-check"></i></span>
                <strong class="ot-sheet__name">Merci pour votre avis !</strong>
                <span class="ot-sheet__sub ot-thanks__text"></span>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
