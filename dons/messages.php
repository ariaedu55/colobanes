<?php
require_once __DIR__ . '/../includes/dons_bootstrap.php';
$uid = dons_require_login();
$convs = dons_conversations_by_user($uid);

$dons_title = 'Messages — Dons';
$dons_tab = 'messages';
require __DIR__ . '/../includes/partials/dons_head.php';
?>

<div class="dn-pagehead">
    <h1>Messages</h1>
    <p>Vos échanges avec les donateurs et les personnes intéressées.</p>
</div>

<?php if (!$convs): ?>
    <div class="dn-empty">
        <span class="dn-empty__icon"><i class="fa-regular fa-comments" aria-hidden="true"></i></span>
        <h2>Aucune conversation</h2>
        <p>Quand vous demandez un don ou qu’on s’intéresse au vôtre, la conversation apparaît ici.</p>
        <a href="/dons/" class="dn-btn dn-btn--primary">Explorer les dons</a>
    </div>
<?php else: ?>
    <ul class="dn-convs">
        <?php foreach ($convs as $c): ?>
            <li>
                <a href="/dons/conversation.php?id=<?php echo (int) $c['id']; ?>" class="dn-conv<?php echo $c['non_lus'] > 0 ? ' is-unread' : ''; ?>">
                    <img src="<?php echo dons_e(dons_img($c['image'] ?? null, 'sm')); ?>" alt="">
                    <span class="dn-conv__body">
                        <span class="dn-conv__top">
                            <strong><?php echo dons_e(dons_user_prenom($c, 'autre_')); ?></strong>
                            <time><?php echo dons_e(dons_temps_relatif((string) ($c['last_message_at'] ?? $c['created_at']))); ?></time>
                        </span>
                        <span class="dn-conv__don"><?php echo (int) $c['donateur_id'] === $uid ? 'Votre don' : 'Don'; ?> · <?php echo dons_e($c['titre']); ?></span>
                        <span class="dn-conv__last"><?php echo dons_e(mb_strimwidth((string) $c['dernier'], 0, 80, '…', 'UTF-8')); ?></span>
                    </span>
                    <?php if ($c['non_lus'] > 0): ?><b class="dn-conv__badge"><?php echo (int) $c['non_lus']; ?></b><?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require __DIR__ . '/../includes/partials/dons_foot.php'; ?>
