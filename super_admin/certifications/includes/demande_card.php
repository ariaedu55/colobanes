<?php
/**
 * Détail demande certification — Super Admin (traiter.php)
 * Variables : $d (array)
 */
if (empty($d) || !is_array($d)) {
    return;
}
$nv = (string) ($d['niveau'] ?? 'standard');
$st_dem = (string) ($d['statut'] ?? '');
$vendeur_nom = trim(($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? ''));

require_once dirname(__DIR__, 3) . '/includes/certification_media.php';
$photos = certification_demande_pieces_list($d, $nv);

$has_text_blocks = !empty($d['adresse_exacte']) || !empty($d['description_activite']) || !empty($d['message_demande']);
$photos_count = count($photos);
?>
<div class="sa-cert-detail-layout">
    <?php if ($photos_count > 0): ?>
    <section
        class="sa-users-panel sa-cert-detail-block sa-cert-detail-block--wide sa-cert-detail-docs"
        aria-labelledby="sa-cert-docs-title"
        id="saCertDetailViewer">
        <div class="sa-cert-docs-head">
            <div class="sa-cert-docs-head__main">
                <h2 id="sa-cert-docs-title"><i class="fas fa-file-image" aria-hidden="true"></i> Justificatifs</h2>
                <span class="sa-cert-docs-head__count"><?php echo (int) $photos_count; ?> / 10</span>
            </div>
        </div>
        <div class="sa-cert-detail-viewer">
            <ul class="sa-cert-detail-docs-row" aria-label="Liste des justificatifs">
                <?php foreach ($photos as $ph):
                    $kind = (string) ($ph['kind'] ?? 'image');
                    $is_pdf = $kind === 'pdf';
                ?>
                <li class="sa-cert-detail-doc">
                    <button
                        type="button"
                        class="sa-cert-detail-doc__btn<?php echo $is_pdf ? ' sa-cert-detail-doc__btn--pdf' : ''; ?>"
                        data-sa-cert-doc-open
                        data-kind="<?php echo htmlspecialchars($kind, ENT_QUOTES, 'UTF-8'); ?>"
                        data-url="<?php echo htmlspecialchars($ph['url'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-label="<?php echo htmlspecialchars($ph['label'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="sa-cert-detail-doc__frame">
                            <?php if ($is_pdf): ?>
                                <span class="sa-cert-detail-doc__pdf" aria-hidden="true">
                                    <i class="fas fa-file-pdf"></i>
                                    <span>PDF</span>
                                </span>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($ph['url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($ph['label'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async">
                            <?php endif; ?>
                        </span>
                        <span class="sa-cert-detail-doc__label"><?php echo htmlspecialchars($ph['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

    <section class="sa-users-panel sa-cert-detail-block" aria-labelledby="sa-cert-contact-title">
        <div class="sa-users-panel__head">
            <h2 id="sa-cert-contact-title"><i class="fas fa-user" aria-hidden="true"></i> Contact vendeur</h2>
        </div>
        <dl class="sa-cert-detail-facts">
            <div class="sa-cert-detail-facts__row">
                <dt>Nom</dt>
                <dd><?php echo htmlspecialchars($vendeur_nom !== '' ? $vendeur_nom : '—', ENT_QUOTES, 'UTF-8'); ?></dd>
            </div>
            <div class="sa-cert-detail-facts__row">
                <dt>E-mail</dt>
                <dd><?php echo htmlspecialchars((string) ($d['email'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
            </div>
            <div class="sa-cert-detail-facts__row">
                <dt>Téléphone</dt>
                <dd><?php echo htmlspecialchars((string) ($d['telephone'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
            </div>
        </dl>
    </section>

    <section class="sa-users-panel sa-cert-detail-block" aria-labelledby="sa-cert-shop-title">
        <div class="sa-users-panel__head">
            <h2 id="sa-cert-shop-title"><i class="fas fa-store" aria-hidden="true"></i> Boutique</h2>
        </div>
        <dl class="sa-cert-detail-facts">
            <div class="sa-cert-detail-facts__row">
                <dt>Région</dt>
                <dd><?php echo !empty($d['boutique_region']) ? htmlspecialchars(senegal_region_label((string) $d['boutique_region']), ENT_QUOTES, 'UTF-8') : '—'; ?></dd>
            </div>
            <?php if (!empty($d['boutique_slug'])): ?>
            <div class="sa-cert-detail-facts__row">
                <dt>Slug</dt>
                <dd><code class="sa-cert-detail-code"><?php echo htmlspecialchars((string) $d['boutique_slug'], ENT_QUOTES, 'UTF-8'); ?></code></dd>
            </div>
            <?php endif; ?>
            <?php if (!empty($d['numero_registre'])): ?>
            <div class="sa-cert-detail-facts__row">
                <dt><?php echo $nv === 'vip' ? 'RCCM' : 'NINEA / RC'; ?></dt>
                <dd><?php echo htmlspecialchars((string) $d['numero_registre'], ENT_QUOTES, 'UTF-8'); ?></dd>
            </div>
            <?php endif; ?>
            <?php if ($st_dem === 'approuvee' && !empty($d['niveau_actif_admin'])): ?>
            <div class="sa-cert-detail-facts__row sa-cert-detail-facts__row--ok">
                <dt>Badge actif</dt>
                <dd><?php echo htmlspecialchars(vendeur_certification_niveau_label((string) $d['niveau_actif_admin']), ENT_QUOTES, 'UTF-8'); ?></dd>
            </div>
            <?php endif; ?>
        </dl>
    </section>

    <?php if ($has_text_blocks): ?>
    <section class="sa-users-panel sa-cert-detail-block sa-cert-detail-block--wide" aria-labelledby="sa-cert-infos-title">
        <div class="sa-users-panel__head">
            <h2 id="sa-cert-infos-title"><i class="fas fa-align-left" aria-hidden="true"></i> Informations complémentaires</h2>
        </div>
        <div class="sa-cert-detail-texts">
            <?php if (!empty($d['adresse_exacte'])): ?>
            <div class="sa-cert-detail-text">
                <h3>Adresse du local</h3>
                <p><?php echo nl2br(htmlspecialchars((string) $d['adresse_exacte'], ENT_QUOTES, 'UTF-8')); ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($d['description_activite'])): ?>
            <div class="sa-cert-detail-text">
                <h3>Description de l'activité</h3>
                <p><?php echo nl2br(htmlspecialchars((string) $d['description_activite'], ENT_QUOTES, 'UTF-8')); ?></p>
            </div>
            <?php endif; ?>
            <?php if (!empty($d['message_demande'])): ?>
            <div class="sa-cert-detail-text sa-cert-detail-text--muted">
                <h3>Message du vendeur</h3>
                <p><?php echo nl2br(htmlspecialchars((string) $d['message_demande'], ENT_QUOTES, 'UTF-8')); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($st_dem === 'refusee' && !empty($d['motif_refus'])): ?>
    <div class="sa-cert-detail-banner sa-cert-detail-banner--err" role="note">
        <i class="fas fa-circle-xmark" aria-hidden="true"></i>
        <div>
            <strong>Motif du refus</strong>
            <p><?php echo nl2br(htmlspecialchars((string) $d['motif_refus'], ENT_QUOTES, 'UTF-8')); ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>
