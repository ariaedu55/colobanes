(function () {
    'use strict';

    var snackHost = null;

    function showPanierAddedSnack() {
        var host = document.createElement('div');
        host.className = 'panier-added-snack-host';
        host.setAttribute('role', 'status');
        host.setAttribute('aria-live', 'polite');

        var snack = document.createElement('div');
        snack.className = 'panier-added-snack';
        snack.innerHTML =
            '<span class="panier-added-snack__icon" aria-hidden="true"><i class="fas fa-check"></i></span>' +
            '<span>Ajouté au panier</span>';

        host.appendChild(snack);
        document.body.appendChild(host);
        snackHost = host;

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                snack.classList.add('is-visible');
            });
        });

        window.setTimeout(function () {
            snack.classList.remove('is-visible');
            snack.classList.add('is-leaving');
        }, 2000);

        window.setTimeout(function () {
            if (host.parentNode) {
                host.parentNode.removeChild(host);
            }
        }, 2350);
    }

    /* Panneau « Ajouté au panier » + produits complémentaires choisis par l'IA */
    function showSuggestions(html) {
        if (snackHost && snackHost.parentNode) {
            snackHost.parentNode.removeChild(snackHost);
        }
        var host = document.createElement('div');
        host.className = 'sap-host';
        host.innerHTML = '<div class="sap-backdrop" data-sap-close></div>' + html;
        document.body.appendChild(host);
        document.body.classList.add('sap-ouvert');
        var before = document.activeElement;

        function close() {
            host.classList.remove('is-open');
            document.body.classList.remove('sap-ouvert');
            document.removeEventListener('keydown', onKey);
            window.setTimeout(function () {
                if (host.parentNode) {
                    host.parentNode.removeChild(host);
                }
            }, 260);
            if (before && before.focus) {
                before.focus();
            }
        }
        function onKey(e) {
            if (e.key === 'Escape') {
                close();
            }
        }
        host.addEventListener('click', function (e) {
            if (e.target.closest('[data-sap-close]')) {
                close();
            }
        });
        document.addEventListener('keydown', onKey);

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                host.classList.add('is-open');
                /* Le focus va au panneau (lecteurs d'écran, clavier) sans entourer la croix d'un anneau */
                var sheet = host.querySelector('.sap');
                if (sheet) {
                    sheet.setAttribute('tabindex', '-1');
                    sheet.focus({ preventScroll: true });
                }
            });
        });
    }

    function loadSuggestions() {
        var ajout = window.__PANIER_AJOUT__;
        if (!ajout || !ajout.produitId || !window.fetch) {
            return;
        }
        window.fetch(ajout.api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'produit', produit_id: ajout.produitId })
        }).then(function (r) {
            return r.ok ? r.json() : null;
        }).then(function (d) {
            if (d && d.success && d.html) {
                showSuggestions(d.html);
            }
        }).catch(function () { /* pas de suggestion : le snackbar suffit */ });
    }

    function start() {
        showPanierAddedSnack();
        loadSuggestions();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
