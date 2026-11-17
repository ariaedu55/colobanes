<?php
require_once __DIR__ . '/../includes/dons_bootstrap.php';
$uid = dons_require_login();

$vue = ($_GET['vue'] ?? '') === 'demandes' ? 'demandes' : 'annonces';
$mes_dons = dons_list_by_user($uid);
$mes_demandes = dons_demandes_by_user($uid);

$dons_title = 'Mes dons';
$dons_tab = 'mes-dons';
require __DIR__ . '/../includes/partials/dons_head.php';

$csrf = site_csrf_field();
$btn_form = function (string $action, array $hidden, string $label, string $class, string $confirm = '') use ($csrf) {
    $h = '<form method="post" action="/dons/action.php"' . ($confirm !== '' ? ' data-confirm="' . dons_e($confirm) . '"' : '') . '>' . $csrf
        . '<input type="hidden" name="action" value="' . dons_e($action) . '">';
    foreach ($hidden as $k => $v) {
        $h .= '<input type="hidden" name="' . dons_e($k) . '" value="' . dons_e($v) . '">';
    }
    return $h . '<button type="submit" class="dn-btn ' . $class . '">' . $label . '</button></form>';
};
?>

<div class="dn-pagehead">
    <h1>Mes dons</h1>
    <div class="dn-seg" role="tablist">
        <a href="/dons/mes-dons.php" class="dn-seg__item<?php echo $vue === 'annonces' ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $vue === 'annonces' ? 'true' : 'false'; ?>">Mes annonces <b><?php echo count($mes_dons); ?></b></a>
        <a href="/dons/mes-dons.php?vue=demandes" class="dn-seg__item<?php echo $vue === 'demandes' ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $vue === 'demandes' ? 'true' : 'false'; ?>">Mes demandes <b><?php echo count($mes_demandes); ?></b></a>
    </div>
</div>

<?php if ($vue === 'annonces'): ?>
    <?php if (!$mes_dons): ?>
        <div class="dn-empty">
            <span class="dn-empty__icon"><i class="fa-solid fa-gift" aria-hidden="true"></i></span>
            <h2>Vous n’avez encore rien offert</h2>
            <p>Un meuble, des livres, des vêtements ? Publiez-les en deux minutes.</p>
        </div>
    <?php else: ?>
        <div class="dn-manage">
            <?php foreach ($mes_dons as $d):
                $ouvert = in_array($d['statut'], ['disponible', 'reserve'], true);
                $actives = array_filter($d['demandes'], fn ($x) => in_array($x['statut'], ['en_attente', 'acceptee'], true));
                $d['nb_demandes'] = count(array_filter($d['demandes'], fn ($x) => $x['statut'] === 'en_attente'));
            ?>
                <article class="dn-mcard" id="don-<?php echo (int) $d['id']; ?>">
                    <div class="dn-mcard__head">
                        <img src="<?php echo dons_e(dons_img($d['image'] ?? null, 'sm')); ?>" alt="">
                        <div class="dn-mcard__info">
                            <?php echo dons_statut_pill($d); ?>
                            <h2><a href="/dons/don.php?id=<?php echo (int) $d['id']; ?>"><?php echo dons_e($d['titre']); ?></a></h2>
                            <p><?php echo (int) $d['quantite_disponible']; ?> / <?php echo (int) $d['quantite_initiale']; ?> disponible<?php echo $d['quantite_disponible'] > 1 ? 's' : ''; ?> · <?php echo dons_e(dons_lieu($d)); ?> · <?php echo dons_e(dons_temps_relatif($d['created_at'])); ?></p>
                            <?php if (!empty($d['motif_moderation']) && in_array($d['statut'], ['refuse', 'retire'], true)): ?>
                                <p class="dn-mcard__motif"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> <?php echo dons_e($d['motif_moderation']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!in_array($d['statut'], ['donne', 'retire'], true)): ?>
                            <div class="dn-mcard__tools">
                                <a href="/dons/offrir.php?id=<?php echo (int) $d['id']; ?>" class="dn-iconbtn" title="Modifier" aria-label="Modifier"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>
                                <?php echo $btn_form('retirer', ['don_id' => $d['id']], '<i class="fa-solid fa-eye-slash" aria-hidden="true"></i><span class="dn-sr">Retirer</span>', 'dn-iconbtn', 'Retirer cette annonce ? Les demandes en cours seront annulées.'); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($d['demandes']): ?>
                        <ul class="dn-reqs">
                            <?php foreach ($d['demandes'] as $q): ?>
                                <li class="dn-req dn-req--<?php echo dons_e($q['statut']); ?>">
                                    <span class="dn-avatar dn-avatar--sm" aria-hidden="true"><?php echo dons_e(mb_strtoupper(mb_substr(dons_user_prenom($q), 0, 1, 'UTF-8'), 'UTF-8')); ?></span>
                                    <div class="dn-req__who">
                                        <strong><?php echo dons_e(dons_user_prenom($q)); ?></strong>
                                        <small>
                                            <?php echo dons_e(dons_demande_label($q['statut'] === 'acceptee' ? 'acceptee' : $q['statut'])); ?>
                                            <?php if ($q['statut'] === 'acceptee' && $q['expires_at']): ?> · jusqu’au <?php echo dons_e(date('d/m H\hi', strtotime((string) $q['expires_at']))); ?><?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="dn-req__actions">
                                        <?php if ($q['conversation_id']): ?>
                                            <a href="/dons/conversation.php?id=<?php echo (int) $q['conversation_id']; ?>" class="dn-iconbtn" title="Conversation" aria-label="Ouvrir la conversation">
                                                <i class="fa-solid fa-comment" aria-hidden="true"></i><?php if ($q['non_lus'] > 0): ?><b class="dn-dot"></b><?php endif; ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($ouvert && $q['statut'] === 'en_attente'): ?>
                                            <?php echo $btn_form('refuser', ['demande_id' => $q['id']], 'Refuser', 'dn-btn--ghost dn-btn--sm'); ?>
                                            <?php echo $btn_form('accepter', ['demande_id' => $q['id']], 'Réserver', 'dn-btn--primary dn-btn--sm'); ?>
                                        <?php elseif ($ouvert && $q['statut'] === 'acceptee'): ?>
                                            <?php echo $btn_form('refuser', ['demande_id' => $q['id']], 'Libérer', 'dn-btn--ghost dn-btn--sm', 'Libérer la réservation ?'); ?>
                                            <?php echo $btn_form('donner', ['demande_id' => $q['id']], '<i class="fa-solid fa-check" aria-hidden="true"></i> Remis', 'dn-btn--primary dn-btn--sm', 'Confirmer que l’objet a été remis ?'); ?>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif ($ouvert): ?>
                        <p class="dn-mcard__empty">Pas encore de demande. Les personnes intéressées apparaîtront ici.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <?php if (!$mes_demandes): ?>
        <div class="dn-empty">
            <span class="dn-empty__icon"><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i></span>
            <h2>Aucune demande</h2>
            <p>Parcourez les dons et cliquez sur « Je suis intéressé ».</p>
            <a href="/dons/" class="dn-btn dn-btn--primary">Explorer les dons</a>
        </div>
    <?php else: ?>
        <ul class="dn-convs">
            <?php foreach ($mes_demandes as $q): ?>
                <li>
                    <div class="dn-conv dn-conv--static">
                        <img src="<?php echo dons_e(dons_img($q['image'] ?? null, 'sm')); ?>" alt="">
                        <span class="dn-conv__body">
                            <span class="dn-conv__top">
                                <strong><a href="/dons/don.php?id=<?php echo (int) $q['don_id']; ?>"><?php echo dons_e($q['titre']); ?></a></strong>
                                <span class="dn-pill dn-pill--req-<?php echo dons_e($q['statut']); ?>"><?php echo dons_e(dons_demande_label($q['statut'])); ?></span>
                            </span>
                            <span class="dn-conv__last"><?php echo dons_e(dons_lieu($q)); ?> · <?php echo dons_e(dons_temps_relatif($q['created_at'])); ?></span>
                        </span>
                        <span class="dn-req__actions">
                            <?php if ($q['conversation_id']): ?>
                                <a href="/dons/conversation.php?id=<?php echo (int) $q['conversation_id']; ?>" class="dn-iconbtn" aria-label="Ouvrir la conversation">
                                    <i class="fa-solid fa-comment" aria-hidden="true"></i><?php if ($q['non_lus'] > 0): ?><b class="dn-dot"></b><?php endif; ?>
                                </a>
                            <?php endif; ?>
                            <?php if (in_array($q['statut'], ['en_attente', 'acceptee'], true)): ?>
                                <?php echo $btn_form('annuler', ['don_id' => $q['don_id'], 'retour' => '/dons/mes-dons.php?vue=demandes'], 'Annuler', 'dn-btn--ghost dn-btn--sm', 'Annuler votre demande ?'); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/partials/dons_foot.php'; ?>
