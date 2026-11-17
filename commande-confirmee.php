<?php
/**
 * Confirmation de commande (étape 3 du parcours d'achat).
 * N'affiche que des commandes appartenant au client connecté.
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/includes/app_base.php';
require_once __DIR__ . '/includes/asset_version.php';
require_once __DIR__ . '/includes/site_brand.php';
require_once __DIR__ . '/includes/image_optimizer.php';
require_once __DIR__ . '/conn/conn.php';

$uid = (int) ($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
    require_once __DIR__ . '/includes/auth_redirect.php';
    auth_redirect_to_site_home();
}

$numeros = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($_GET['numeros'] ?? ''))), function ($n) {
    return $n !== '' && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $n);
})));
$numeros = array_slice($numeros, 0, 20);

$commandes = [];
if ($numeros) {
    global $db;
    $ph = implode(',', array_fill(0, count($numeros), '?'));
    $st = $db->prepare("SELECT c.id, c.numero_commande, c.montant_total, c.frais_livraison, c.mode_livraison, c.statut, c.date_commande,
            a.boutique_nom, a.nom AS vendeur_nom,
            (SELECT COALESCE(SUM(cp.quantite), 0) FROM commande_produits cp WHERE cp.commande_id = c.id) AS nb_articles,
            (SELECT p.image_principale FROM commande_produits cp JOIN produits p ON p.id = cp.produit_id WHERE cp.commande_id = c.id LIMIT 1) AS image
        FROM commandes c
        LEFT JOIN admin a ON a.id = c.vendeur_id
        WHERE c.user_id = ? AND c.numero_commande IN ($ph)
        ORDER BY c.id");
    $st->execute(array_merge([$uid], $numeros));
    $commandes = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if (!$commandes) {
    header('Location: ' . app_url('/user/mes-commandes.php'));
    exit;
}

$total = 0.0;
$retrait = false;
foreach ($commandes as $c) {
    $total += (float) $c['montant_total'];
    $retrait = $retrait || ($c['mode_livraison'] ?? '') === 'retrait';
}
$prenom = trim((string) ($_SESSION['user_prenom'] ?? ''));
$e = function ($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
$fcfa = function ($n) {
    return number_format((float) $n, 0, ',', ' ') . ' FCFA';
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Commande confirmée — <?php echo $e(SITE_BRAND_NAME); ?></title>
    <?php require_once __DIR__ . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/checkout-v2.css<?php echo asset_version_query(); ?>">
</head>
<body class="co-body">
    <?php include __DIR__ . '/nav_bar.php'; ?>

    <main class="co-page">
        <div class="co-shell co-shell--narrow">
            <?php $checkout_step = 3; require __DIR__ . '/includes/partials/checkout_steps.php'; ?>

            <section class="co-card co-done">
                <span class="co-done__check" aria-hidden="true"><i class="fas fa-check"></i></span>
                <h1 class="co-done__title"><?php echo $prenom !== '' ? 'Merci ' . $e($prenom) . ', c’est commandé !' : 'Merci, c’est commandé !'; ?></h1>
                <p class="co-done__lead">
                    <?php echo count($commandes) > 1
                        ? 'Vos ' . count($commandes) . ' commandes ont bien été transmises aux boutiques.'
                        : 'Votre commande a bien été transmise à la boutique.'; ?>
                    Vous recevrez une notification à chaque étape.
                </p>

                <ul class="co-orders">
                    <?php foreach ($commandes as $c): ?>
                        <li class="co-order">
                            <img src="<?php echo $e(upload_image_url((string) ($c['image'] ?? ''), 'sm')); ?>" alt="" onerror="this.src='/image/produit1.jpg'">
                            <div class="co-order__body">
                                <p class="co-order__num">Commande <strong>#<?php echo $e($c['numero_commande']); ?></strong></p>
                                <p class="co-order__meta">
                                    <?php echo $e(trim((string) ($c['boutique_nom'] ?: $c['vendeur_nom'])) ?: 'Boutique'); ?>
                                    · <?php echo (int) $c['nb_articles']; ?> article<?php echo $c['nb_articles'] > 1 ? 's' : ''; ?>
                                    · <?php echo ($c['mode_livraison'] ?? '') === 'retrait' ? 'Retrait en boutique' : 'Livraison'; ?>
                                </p>
                            </div>
                            <strong class="co-order__total"><?php echo $e($fcfa($c['montant_total'])); ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($commandes) > 1): ?>
                    <p class="co-done__sum">Total : <strong><?php echo $e($fcfa($total)); ?></strong></p>
                <?php endif; ?>
                <p class="co-done__pay"><i class="fas fa-hand-holding-dollar" aria-hidden="true"></i> Rien à payer maintenant : vous réglez <?php echo $retrait ? 'au retrait ou ' : ''; ?>à la réception.</p>

                <div class="co-done__actions">
                    <a class="co-btn co-btn--primary co-btn--lg" href="<?php echo $e(app_url(count($commandes) === 1 ? '/user/commande-categorie.php?commande_id=' . (int) $commandes[0]['id'] : '/user/mes-commandes.php')); ?>">
                        <i class="fas fa-location-dot" aria-hidden="true"></i> Suivre ma commande
                    </a>
                    <a class="co-btn co-btn--ghost co-btn--lg" href="<?php echo $e(app_url('/index.php')); ?>">Continuer mes achats</a>
                </div>
            </section>

            <section class="co-card co-next">
                <h2 class="co-next__title">Et maintenant ?</h2>
                <ol class="co-next__list">
                    <li><span>1</span><div><strong>La boutique accepte votre commande</strong><small>Elle vérifie le stock et prépare vos articles.</small></div></li>
                    <?php if ($retrait): ?>
                        <li><span>2</span><div><strong>Commande prête au retrait</strong><small>Vous êtes prévenu ; l’adresse et l’itinéraire sont dans le suivi.</small></div></li>
                    <?php else: ?>
                        <li><span>2</span><div><strong>Mise en livraison</strong><small>Gardez votre téléphone joignable : le livreur peut vous appeler.</small></div></li>
                    <?php endif; ?>
                    <li><span>3</span><div><strong>Vous recevez et vous réglez</strong><small>Confirmez la réception dans le suivi, puis notez vos produits.</small></div></li>
                </ol>
                <p class="co-next__help">Une question ? <a href="<?php echo $e(app_url('/user/messages.php')); ?>">Écrivez à la boutique</a> ou <a href="<?php echo $e(app_url('/contact.php?sujet=commande')); ?>">contactez-nous</a>.</p>
            </section>
        </div>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
