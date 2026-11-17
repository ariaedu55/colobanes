(function () {
    'use strict';

    function apiUrl() {
        if (window.MP_FAVORI_API) {
            return String(window.MP_FAVORI_API);
        }
        return '/api/toggle_favori.php';
    }

    function getCsrfToken(form) {
        var input = form ? form.querySelector('input[name="csrf_token"]') : null;
        if (input && input.value) {
            return input.value;
        }
        if (window.__SITE_CSRF__ ) {
            return String(window.__SITE_CSRF__);
        }
        return '';
    }

    function setButtonState(btn, isFav) {
        if (!btn) {
            return;
        }
        btn.classList.toggle('mp-favori-btn--active', !!isFav);
        btn.setAttribute('aria-pressed', isFav ? 'true' : 'false');
        btn.setAttribute('aria-label', isFav ? 'Retirer des favoris' : 'Ajouter aux favoris');
        var icon = btn.querySelector('i');
        if (icon) {
            icon.className = (isFav ? 'fa-solid' : 'fa-regular') + ' fa-heart';
        }
    }

    function updateFavoriIds(produitId, isFav) {
        if (!(window.__MP_FAVORI_IDS__ instanceof Set)) {
            return;
        }
        var id = parseInt(produitId, 10);
        if (id <= 0) {
            return;
        }
        if (isFav) {
            window.__MP_FAVORI_IDS__.add(id);
        } else {
            window.__MP_FAVORI_IDS__.delete(id);
        }
    }

    function removeCardFromFavorisPage(btn) {
        var card = btn.closest('.mp-card');
        if (!card || !document.body.classList.contains('user-page-favoris')) {
            return;
        }
        card.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.96)';
        setTimeout(function () {
            if (card.parentNode) {
                card.parentNode.removeChild(card);
            }
            var grid = document.querySelector('.fav-grid');
            if (grid && grid.querySelectorAll('.mp-card').length === 0) {
                window.location.reload();
            }
        }, 260);
    }

    function showToast(type, message) {
        if (window.FlashToast && typeof window.FlashToast[type] === 'function') {
            window.FlashToast[type](message);
            return;
        }
    }

    function toggleFavori(btn) {
        if (!btn || btn.disabled || btn.dataset.mpFavoriLoading === '1') {
            return;
        }

        var form = btn.closest('.mp-favori-form');
        var produitInput = form ? form.querySelector('input[name="produit_id"]') : null;
        var produitId = produitInput ? parseInt(produitInput.value, 10) : parseInt(btn.getAttribute('data-produit-id') || '0', 10);
        if (produitId <= 0) {
            return;
        }

        var csrf = getCsrfToken(form);
        if (!csrf) {
            showToast('error', 'Session expirée. Rechargez la page.');
            return;
        }

        btn.dataset.mpFavoriLoading = '1';
        btn.disabled = true;

        var fd = new FormData();
        fd.append('produit_id', String(produitId));
        fd.append('csrf_token', csrf);

        fetch(apiUrl(), { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, status: r.status, data: data }; }); })
            .then(function (res) {
                var data = res.data || {};
                if (!res.ok || !data.success) {
                    if (data.auth_required) {
                        var redirect = window.location.pathname + window.location.search;
                        var loginBase = window.MP_FAVORI_LOGIN || '/choix-connexion.php';
                        window.location.href = loginBase + '?redirect=' + encodeURIComponent(redirect);
                        return;
                    }
                    showToast('error', data.message || 'Action impossible.');
                    return;
                }

                setButtonState(btn, !!data.favori);
                updateFavoriIds(produitId, !!data.favori);

                if (data.message) {
                    showToast('success', data.message);
                }

                if (!data.favori) {
                    removeCardFromFavorisPage(btn);
                }
            })
            .catch(function () {
                showToast('error', 'Connexion impossible. Réessayez.');
            })
            .finally(function () {
                btn.disabled = false;
                delete btn.dataset.mpFavoriLoading;
            });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-mp-favori-toggle]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        toggleFavori(btn);
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.classList || !form.classList.contains('mp-favori-form')) {
            return;
        }
        e.preventDefault();
        var btn = form.querySelector('[data-mp-favori-toggle]');
        toggleFavori(btn);
    });
})();
