<?php
/**
 * Gestion du module Dons, partagée entre l'administration (/admin/dons/) et le super admin (/super_admin/dons/) :
 * annonces, signalements, catégories, paramètres.
 *
 * Contexte attendu ($ctx) :
 *  - self        : chemin de la page (ex. '/admin/dons/index.php'), pour les redirections
 *  - csrf_field  : champ caché CSRF de l'espace appelant (HTML)
 *  - actor_admin : id admin pour l'historique des dons (0 pour le super admin)
 *  - on_action   : callable(string $action, string $cible_type, int $cible_id, string $details) facultatif (journal d'audit)
 * Le CSRF des POST est vérifié par l'espace appelant avant dons_admin_panel_handle_post().
 */

require_once dirname(__DIR__) . '/models/model_dons.php';
require_once __DIR__ . '/image_optimizer.php';
require_once __DIR__ . '/senegal_regions.php';
require_once __DIR__ . '/app_base.php';

if (!function_exists('dons_admin_panel_tab')) {

    function dons_admin_panel_tab(): string
    {
        $t = (string) ($_GET['tab'] ?? '');
        return in_array($t, ['annonces', 'signalements', 'categories', 'parametres'], true) ? $t : 'annonces';
    }

    /** Traite une action POST puis redirige (PRG). */
    function dons_admin_panel_handle_post(array $ctx): void
    {
        global $db;
        dons_ready();
        $actor = (int) ($ctx['actor_admin'] ?? 0);
        $audit = $ctx['on_action'] ?? null;
        $log = function (string $action, string $type, int $id, string $details) use ($audit) {
            if (is_callable($audit)) {
                $audit($action, $type, $id, $details);
            }
        };
        $a = (string) ($_POST['action'] ?? '');
        $msg = '';
        switch ($a) {
            case 'statut':
                $s = (string) ($_POST['statut'] ?? '');
                $don_id = (int) ($_POST['don_id'] ?? 0);
                $motif = trim((string) ($_POST['motif'] ?? ''));
                if (dons_admin_set_statut($don_id, $s, $actor, $motif)) {
                    $msg = 'Annonce : ' . dons_statut_label($s) . '.';
                    $log('don_' . $s, 'don', $don_id, 'Don #' . $don_id . ' → ' . dons_statut_label($s) . ($motif !== '' ? ' (' . $motif . ')' : ''));
                }
                break;
            case 'suspendre':
            case 'reactiver':
                $u = (int) ($_POST['user_id'] ?? 0);
                if ($u > 0) {
                    $db->prepare('UPDATE users SET statut = :s WHERE id = :id')->execute(['s' => $a === 'suspendre' ? 'inactif' : 'actif', 'id' => $u]);
                    if ($a === 'suspendre') {
                        $ids = $db->prepare("SELECT id FROM dons WHERE user_id = :u AND statut IN ('disponible','reserve','en_moderation')");
                        $ids->execute(['u' => $u]);
                        foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $did) {
                            dons_admin_set_statut((int) $did, 'retire', $actor, 'Compte suspendu par la modération');
                        }
                    }
                    $msg = $a === 'suspendre' ? 'Utilisateur suspendu, ses annonces sont retirées.' : 'Utilisateur réactivé.';
                    $log($a === 'suspendre' ? 'dons_utilisateur_suspendu' : 'dons_utilisateur_reactive', 'utilisateur', $u, 'Module Dons : ' . $msg);
                }
                break;
            case 'signalement_traite':
                $sid = (int) ($_POST['id'] ?? 0);
                $db->prepare("UPDATE dons_signalements SET statut = 'traite' WHERE id = :id")->execute(['id' => $sid]);
                $msg = 'Signalement classé.';
                $log('dons_signalement_classe', 'signalement_don', $sid, 'Signalement #' . $sid . ' classé');
                break;
            case 'categorie_save':
                $nom = trim(mb_substr((string) ($_POST['nom'] ?? ''), 0, 80));
                $icone = preg_match('/^fa-[a-z0-9-]{2,40}$/', (string) ($_POST['icone'] ?? '')) ? (string) $_POST['icone'] : 'fa-box';
                $cid = (int) ($_POST['id'] ?? 0);
                if ($nom !== '') {
                    if ($cid > 0) {
                        $db->prepare('UPDATE dons_categories SET nom = :n, icone = :i, actif = :a WHERE id = :id')
                            ->execute(['n' => $nom, 'i' => $icone, 'a' => !empty($_POST['actif']) ? 1 : 0, 'id' => $cid]);
                    } else {
                        $db->prepare('INSERT INTO dons_categories (nom, icone, ordre) VALUES (:n, :i, 99)')->execute(['n' => $nom, 'i' => $icone]);
                        $cid = (int) $db->lastInsertId();
                    }
                    $msg = 'Catégorie enregistrée.';
                    $log('dons_categorie', 'categorie_don', $cid, 'Catégorie de dons : ' . $nom);
                }
                break;
            case 'parametres':
                dons_param_set('actif', !empty($_POST['actif']) ? '1' : '0');
                dons_param_set('moderation_prealable', !empty($_POST['moderation_prealable']) ? '1' : '0');
                dons_param_set('notifications', !empty($_POST['notifications']) ? '1' : '0');
                dons_param_set('reservation_heures', (string) max(1, min(720, (int) ($_POST['reservation_heures'] ?? 72))));
                dons_param_set('max_annonces_jour', (string) max(1, min(100, (int) ($_POST['max_annonces_jour'] ?? 5))));
                $msg = 'Paramètres enregistrés.';
                $log('dons_parametres', 'module', 0, 'Paramètres du module Dons (actif : ' . (!empty($_POST['actif']) ? 'oui' : 'non') . ')');
                break;
        }
        if ($msg !== '') {
            $_SESSION['dons_admin_flash'] = $msg;
        }
        $back = (string) $ctx['self'] . '?tab=' . dons_admin_panel_tab();
        if (!empty($_GET['statut'])) {
            $back .= '&statut=' . rawurlencode((string) $_GET['statut']);
        }
        header('Location: ' . app_url($back));
        exit;
    }

    /** Affiche le contenu (chiffres, onglets, listes, formulaires). */
    function dons_admin_panel_render(array $ctx): void
    {
        dons_ready();
        dons_expirer_reservations();
        $csrf = (string) ($ctx['csrf_field'] ?? '');
        $tab = dons_admin_panel_tab();
        $e = function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };
        $flash = (string) ($_SESSION['dons_admin_flash'] ?? '');
        unset($_SESSION['dons_admin_flash']);
        $stats = dons_admin_stats();
        $statuts = ['en_moderation', 'disponible', 'reserve', 'donne', 'retire', 'refuse'];
        $f_statut = in_array((string) ($_GET['statut'] ?? ''), $statuts, true) ? (string) $_GET['statut'] : '';
        $f_q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 80));
        $public = function (string $path) use ($e) {
            return $e(app_url($path));
        };
        ?>
        <?php if ($flash !== ''): ?>
            <div class="message success"><i class="fas fa-check-circle"></i> <?php echo $e($flash); ?></div>
        <?php endif; ?>

        <div class="ad-kpis">
            <a href="?tab=annonces&amp;statut=disponible" class="ad-kpi"><span>En ligne</span><strong><?php echo (int) ($stats['disponible'] ?? 0) + (int) ($stats['reserve'] ?? 0); ?></strong></a>
            <a href="?tab=annonces&amp;statut=en_moderation" class="ad-kpi ad-kpi--warn"><span>À valider</span><strong><?php echo (int) ($stats['en_moderation'] ?? 0); ?></strong></a>
            <a href="?tab=annonces&amp;statut=donne" class="ad-kpi"><span>Donnés</span><strong><?php echo (int) ($stats['donne'] ?? 0); ?></strong></a>
            <a href="?tab=signalements" class="ad-kpi ad-kpi--danger"><span>Signalements</span><strong><?php echo (int) ($stats['signalements'] ?? 0); ?></strong></a>
        </div>

        <div class="ad-tabs" role="tablist">
            <?php foreach (['annonces' => 'Annonces', 'signalements' => 'Signalements', 'categories' => 'Catégories', 'parametres' => 'Paramètres'] as $k => $l): ?>
                <a href="?tab=<?php echo $k; ?>" class="ad-tab<?php echo $tab === $k ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $tab === $k ? 'true' : 'false'; ?>"><?php echo $l; ?></a>
            <?php endforeach; ?>
            <?php if (!dons_module_actif()): ?><span class="ad-off"><i class="fas fa-pause-circle"></i> Module en pause</span><?php endif; ?>
        </div>

        <section class="produits-section ad-panel">
        <?php if ($tab === 'annonces'):
            $rows = dons_admin_list(['statut' => $f_statut, 'q' => $f_q], 100); ?>
            <form class="ad-filter" method="get">
                <input type="hidden" name="tab" value="annonces">
                <input type="search" name="q" value="<?php echo $e($f_q); ?>" placeholder="Titre ou nom du donateur">
                <select name="statut" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    <?php foreach ($statuts as $s): ?>
                        <option value="<?php echo $s; ?>"<?php echo $f_statut === $s ? ' selected' : ''; ?>><?php echo $e(dons_statut_label($s)); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary">Filtrer</button>
            </form>
            <?php if (!$rows): ?>
                <div class="empty-state"><i class="fas fa-gift"></i><h3>Aucune annonce</h3></div>
            <?php else: ?>
                <div class="ad-list">
                    <?php foreach ($rows as $r): ?>
                        <article class="ad-row">
                            <img src="<?php echo $e($r['image'] ? upload_image_url($r['image'], 'sm') : upload_image_fallback_url()); ?>" alt="">
                            <div class="ad-row__main">
                                <p class="ad-row__title">
                                    <a href="<?php echo $public('/dons/don.php?id=' . (int) $r['id']); ?>" target="_blank" rel="noopener"><?php echo $e($r['titre']); ?></a>
                                    <span class="ad-pill ad-pill--<?php echo $e($r['statut']); ?>"><?php echo $e(dons_statut_label($r['statut'])); ?></span>
                                    <?php if ($r['nb_signalements'] > 0): ?><span class="ad-pill ad-pill--refuse"><i class="fas fa-flag"></i> <?php echo (int) $r['nb_signalements']; ?></span><?php endif; ?>
                                </p>
                                <p class="ad-row__meta">
                                    <?php echo $e(trim($r['prenom'] . ' ' . $r['nom'])); ?><?php echo $r['user_statut'] !== 'actif' ? ' <b class="ad-susp">suspendu</b>' : ''; ?>
                                    · <?php echo $e($r['categorie_nom'] ?? '—'); ?> · <?php echo $e(senegal_region_label($r['region'])); ?>
                                    · <?php echo (int) $r['quantite_disponible']; ?>/<?php echo (int) $r['quantite_initiale']; ?> · <?php echo (int) $r['nb_demandes']; ?> demande(s)
                                    · <?php echo $e(date('d/m/Y', strtotime($r['created_at']))); ?>
                                </p>
                            </div>
                            <div class="ad-row__actions">
                                <?php if (in_array($r['statut'], ['en_moderation', 'retire', 'refuse'], true)): ?>
                                    <form method="post"><?php echo $csrf; ?><input type="hidden" name="action" value="statut"><input type="hidden" name="statut" value="disponible"><input type="hidden" name="don_id" value="<?php echo (int) $r['id']; ?>">
                                        <button class="ad-btn ad-btn--ok" type="submit"><?php echo $r['statut'] === 'en_moderation' ? 'Valider' : 'Rétablir'; ?></button></form>
                                <?php endif; ?>
                                <?php if (in_array($r['statut'], ['en_moderation', 'disponible', 'reserve'], true)): ?>
                                    <form method="post" class="ad-motif-form" data-label="<?php echo $r['statut'] === 'en_moderation' ? 'Motif du refus' : 'Motif du masquage'; ?>">
                                        <?php echo $csrf; ?><input type="hidden" name="action" value="statut">
                                        <input type="hidden" name="statut" value="<?php echo $r['statut'] === 'en_moderation' ? 'refuse' : 'retire'; ?>">
                                        <input type="hidden" name="don_id" value="<?php echo (int) $r['id']; ?>"><input type="hidden" name="motif" value="">
                                        <button class="ad-btn ad-btn--danger" type="submit"><?php echo $r['statut'] === 'en_moderation' ? 'Refuser' : 'Masquer'; ?></button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" onsubmit="return confirm('<?php echo $r['user_statut'] === 'actif' ? 'Suspendre ce compte ? Ses annonces seront retirées.' : 'Réactiver ce compte ?'; ?>');">
                                    <?php echo $csrf; ?><input type="hidden" name="action" value="<?php echo $r['user_statut'] === 'actif' ? 'suspendre' : 'reactiver'; ?>"><input type="hidden" name="user_id" value="<?php echo (int) $r['user_id']; ?>">
                                    <button class="ad-btn" type="submit"><?php echo $r['user_statut'] === 'actif' ? 'Suspendre l’utilisateur' : 'Réactiver l’utilisateur'; ?></button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($tab === 'signalements'):
            $vue = ($_GET['vue'] ?? '') === 'traite' ? 'traite' : 'nouveau';
            $rows = dons_admin_signalements($vue);
            $motifs = dons_motifs_signalement(); ?>
            <div class="ad-filter">
                <a href="?tab=signalements" class="ad-tab<?php echo $vue === 'nouveau' ? ' is-active' : ''; ?>">À traiter</a>
                <a href="?tab=signalements&amp;vue=traite" class="ad-tab<?php echo $vue === 'traite' ? ' is-active' : ''; ?>">Traités</a>
            </div>
            <?php if (!$rows): ?>
                <div class="empty-state"><i class="fas fa-flag"></i><h3>Aucun signalement <?php echo $vue === 'nouveau' ? 'à traiter' : 'traité'; ?></h3></div>
            <?php else: ?>
                <div class="ad-list">
                    <?php foreach ($rows as $s): ?>
                        <article class="ad-row ad-row--report">
                            <div class="ad-row__main">
                                <p class="ad-row__title">
                                    <span class="ad-pill ad-pill--refuse"><?php echo $e($motifs[$s['motif']] ?? $s['motif']); ?></span>
                                    <a href="<?php echo $public('/dons/don.php?id=' . (int) $s['don_id']); ?>" target="_blank" rel="noopener"><?php echo $e($s['titre']); ?></a>
                                    <span class="ad-pill ad-pill--<?php echo $e($s['don_statut']); ?>"><?php echo $e(dons_statut_label($s['don_statut'])); ?></span>
                                </p>
                                <p class="ad-row__meta">Signalé par <?php echo $e(trim($s['prenom'] . ' ' . $s['nom'])); ?> le <?php echo $e(date('d/m/Y H:i', strtotime($s['created_at']))); ?>
                                    <?php echo $s['conversation_id'] ? ' · depuis une conversation' : ''; ?> · Donateur : <?php echo $e(trim($s['donateur_prenom'] . ' ' . $s['donateur_nom'])); ?><?php echo $s['donateur_statut'] !== 'actif' ? ' <b class="ad-susp">suspendu</b>' : ''; ?></p>
                                <?php if (trim((string) $s['details']) !== ''): ?><p class="ad-row__quote"><?php echo nl2br($e($s['details'])); ?></p><?php endif; ?>
                            </div>
                            <?php if ($vue === 'nouveau'): ?>
                                <div class="ad-row__actions">
                                    <?php if (in_array($s['don_statut'], ['disponible', 'reserve', 'en_moderation'], true)): ?>
                                        <form method="post"><?php echo $csrf; ?><input type="hidden" name="action" value="statut"><input type="hidden" name="statut" value="retire"><input type="hidden" name="don_id" value="<?php echo (int) $s['don_id']; ?>"><input type="hidden" name="motif" value="Annonce retirée suite à un signalement">
                                            <button class="ad-btn ad-btn--danger" type="submit">Masquer l’annonce</button></form>
                                    <?php endif; ?>
                                    <?php if ($s['donateur_statut'] === 'actif'): ?>
                                        <form method="post" onsubmit="return confirm('Suspendre le donateur ? Ses annonces seront retirées.');"><?php echo $csrf; ?><input type="hidden" name="action" value="suspendre"><input type="hidden" name="user_id" value="<?php echo (int) $s['donateur_id']; ?>">
                                            <button class="ad-btn" type="submit">Suspendre le donateur</button></form>
                                    <?php endif; ?>
                                    <form method="post"><?php echo $csrf; ?><input type="hidden" name="action" value="signalement_traite"><input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                                        <button class="ad-btn ad-btn--ok" type="submit">Classer</button></form>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($tab === 'categories'): ?>
            <div class="ad-list">
                <?php foreach (dons_categories(false) as $c): ?>
                    <form method="post" class="ad-row ad-cat">
                        <?php echo $csrf; ?>
                        <input type="hidden" name="action" value="categorie_save"><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                        <span class="ad-cat__icon"><i class="fas <?php echo $e($c['icone']); ?>"></i></span>
                        <input type="text" name="nom" value="<?php echo $e($c['nom']); ?>" maxlength="80" required aria-label="Nom">
                        <input type="text" name="icone" value="<?php echo $e($c['icone']); ?>" maxlength="42" aria-label="Icône Font Awesome" class="ad-cat__ic">
                        <label class="ad-check"><input type="checkbox" name="actif" value="1"<?php echo $c['actif'] ? ' checked' : ''; ?>> Active</label>
                        <button class="ad-btn" type="submit">Enregistrer</button>
                    </form>
                <?php endforeach; ?>
                <form method="post" class="ad-row ad-cat ad-cat--new">
                    <?php echo $csrf; ?>
                    <input type="hidden" name="action" value="categorie_save">
                    <span class="ad-cat__icon"><i class="fas fa-plus"></i></span>
                    <input type="text" name="nom" maxlength="80" required placeholder="Nouvelle catégorie" aria-label="Nom">
                    <input type="text" name="icone" maxlength="42" placeholder="fa-box" aria-label="Icône Font Awesome" class="ad-cat__ic">
                    <button class="ad-btn ad-btn--ok" type="submit">Ajouter</button>
                </form>
            </div>
            <p class="ad-help">Désactiver une catégorie la retire des choix de publication et des filtres, sans toucher aux annonces existantes. Icônes : noms Font Awesome (ex. fa-couch).</p>

        <?php else: ?>
            <form method="post" class="ad-settings">
                <?php echo $csrf; ?>
                <input type="hidden" name="action" value="parametres">
                <label class="ad-setting">
                    <span><strong>Module actif</strong><small>Désactivé : plus de nouvelles annonces ni de demandes. Les données et conversations sont conservées.</small></span>
                    <input type="checkbox" name="actif" value="1"<?php echo dons_param('actif') === '1' ? ' checked' : ''; ?>>
                </label>
                <label class="ad-setting">
                    <span><strong>Validation avant publication</strong><small>Les nouvelles annonces attendent votre validation (onglet Annonces, filtre « En modération »).</small></span>
                    <input type="checkbox" name="moderation_prealable" value="1"<?php echo dons_param('moderation_prealable') === '1' ? ' checked' : ''; ?>>
                </label>
                <label class="ad-setting">
                    <span><strong>Notifications</strong><small>Prévenir donateurs et demandeurs (demandes, réservations, messages).</small></span>
                    <input type="checkbox" name="notifications" value="1"<?php echo dons_param('notifications') === '1' ? ' checked' : ''; ?>>
                </label>
                <label class="ad-setting">
                    <span><strong>Durée d’une réservation</strong><small>En heures. Passé ce délai sans remise, l’objet redevient disponible.</small></span>
                    <input type="number" name="reservation_heures" min="1" max="720" value="<?php echo (int) dons_param('reservation_heures'); ?>">
                </label>
                <label class="ad-setting">
                    <span><strong>Annonces par jour et par utilisateur</strong><small>Limite anti-spam sur 24 heures glissantes.</small></span>
                    <input type="number" name="max_annonces_jour" min="1" max="100" value="<?php echo (int) dons_param('max_annonces_jour'); ?>">
                </label>
                <button type="submit" class="btn-primary">Enregistrer les paramètres</button>
            </form>
        <?php endif; ?>
        </section>

        <script>
        document.querySelectorAll('.ad-motif-form').forEach(function (f) {
            f.addEventListener('submit', function (ev) {
                var m = window.prompt(f.getAttribute('data-label') + ' (visible par le donateur) :', '');
                if (m === null) { ev.preventDefault(); return; }
                f.querySelector('input[name="motif"]').value = m;
            });
        });
        </script>
        <?php
    }
}
