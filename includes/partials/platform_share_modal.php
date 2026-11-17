<?php
/**
 * Modal partage unifiée (produits catalogue + boutique vendeur) — ordinateur uniquement.
 * Mobile / tablette : feuille de partage native du téléphone (js/platform-share-modal.js).
 */
if (!function_exists('asset_version_query')) {
    require_once __DIR__ . '/../asset_version.php';
}
?>
<div class="prm-share-modal" id="platformShareModal" role="dialog"
    aria-modal="true" aria-labelledby="platformShareModalTitle" aria-hidden="true" hidden>
    <div class="prm-share-modal__backdrop" data-share-close tabindex="-1"></div>
    <div class="prm-share-modal__panel" role="document">
        <span class="prm-share-modal__grip" aria-hidden="true"></span>
        <div class="prm-share-modal__head">
            <h2 class="prm-share-modal__title" id="platformShareModalTitle">Partager</h2>
            <button type="button" class="prm-share-modal__close" data-share-close aria-label="Fermer">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="prm-share-preview" id="platformShareModalPreview" hidden>
            <img class="prm-share-preview__img" id="platformShareModalImg" alt="" width="56" height="56" decoding="async">
            <div class="prm-share-preview__txt">
                <p class="prm-share-preview__name" id="platformShareModalName"></p>
                <p class="prm-share-preview__price" id="platformShareModalPrice"></p>
            </div>
        </div>

        <div class="prm-share-grid" role="group" aria-label="Partager via">
            <button type="button" class="prm-share-item prm-share-item--wa" data-share-channel="wa">
                <span class="prm-share-item__icon"><i class="fab fa-whatsapp" aria-hidden="true"></i></span>
                <span class="prm-share-item__label">WhatsApp</span>
            </button>
            <button type="button" class="prm-share-item prm-share-item--fb" data-share-channel="fb">
                <span class="prm-share-item__icon"><i class="fab fa-facebook-f" aria-hidden="true"></i></span>
                <span class="prm-share-item__label">Facebook</span>
            </button>
            <button type="button" class="prm-share-item prm-share-item--ig" data-share-channel="ig">
                <span class="prm-share-item__icon"><i class="fab fa-instagram" aria-hidden="true"></i></span>
                <span class="prm-share-item__label">Instagram</span>
            </button>
            <button type="button" class="prm-share-item prm-share-item--tt" data-share-channel="tt">
                <span class="prm-share-item__icon"><i class="fab fa-tiktok" aria-hidden="true"></i></span>
                <span class="prm-share-item__label">TikTok</span>
            </button>
            <button type="button" class="prm-share-item prm-share-item--more" data-share-channel="more" hidden>
                <span class="prm-share-item__icon"><i class="fas fa-ellipsis" aria-hidden="true"></i></span>
                <span class="prm-share-item__label">Plus</span>
            </button>
        </div>

        <div class="prm-share-modal__url-row">
            <i class="fas fa-link prm-share-modal__url-ico" aria-hidden="true"></i>
            <p class="prm-share-modal__url" id="platformShareModalUrl"></p>
            <button type="button" class="prm-share-modal__url-copy" id="platformShareModalUrlCopy"
                aria-label="Copier le lien">
                <i class="fas fa-copy" aria-hidden="true"></i>
                <span class="prm-share-modal__url-copy-label">Copier</span>
            </button>
        </div>
        <p class="prm-share-modal__hint" id="platformShareModalHint" hidden></p>
    </div>
</div>
