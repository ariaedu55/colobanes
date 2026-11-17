/**
 * Panier — « Complétez votre achat » : ajout d'un accessoire sans quitter la page.
 * Le formulaire classique (add-to-panier.php) reste le secours sans JavaScript.
 * Après l'ajout, le panier, les totaux et les badges sont remplacés par la version à jour du serveur.
 */
(function (win, doc) {
    'use strict';

    var busy = false;

    function toast(message, isError) {
        var el = doc.createElement('div');
        el.className = 'pacc-toast' + (isError ? ' pacc-toast--error' : '');
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        var icon = doc.createElement('i');
        icon.className = 'fas ' + (isError ? 'fa-circle-exclamation' : 'fa-check');
        icon.setAttribute('aria-hidden', 'true');
        var text = doc.createElement('span');
        text.textContent = message;
        el.appendChild(icon);
        el.appendChild(text);
        doc.body.appendChild(el);
        win.requestAnimationFrame(function () {
            el.classList.add('is-visible');
        });
        win.setTimeout(function () {
            el.classList.remove('is-visible');
            win.setTimeout(function () {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 300);
        }, 2200);
    }

    /* Remplace un élément de la page par son équivalent dans la page fraîche */
    function swap(fresh, selector) {
        var current = doc.querySelector(selector);
        var next = fresh.querySelector(selector);
        if (current && next) {
            current.replaceWith(doc.importNode(next, true));
        }
    }

    function cartHasProduct(fresh, pid) {
        return !!fresh.querySelector('.co-item a[href$="produit.php?id=' + pid + '"]');
    }

    function onSubmit(e) {
        var form = e.target.closest ? e.target.closest('form[data-pacc-add]') : null;
        if (!form || !win.fetch || !win.DOMParser) {
            return;
        }
        e.preventDefault();
        if (busy) {
            return;
        }
        busy = true;

        var pid = String((form.querySelector('input[name="produit_id"]') || {}).value || '');
        var btn = form.querySelector('.pacc-card__btn');
        var card = form.closest('.pacc-card');
        if (btn) {
            btn.disabled = true;
            btn.classList.add('is-loading');
        }

        win.fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            redirect: 'follow'
        }).then(function (res) {
            return res.text();
        }).then(function (html) {
            var fresh = new DOMParser().parseFromString(html, 'text/html');
            if (!cartHasProduct(fresh, pid)) {
                throw new Error('not-added');
            }
            if (btn) {
                btn.classList.remove('is-loading');
                btn.classList.add('is-done');
            }
            if (card) {
                card.classList.add('is-added');
            }
            /* Laisse voir la coche, puis met le panier et les suggestions à jour */
            win.setTimeout(function () {
                swap(fresh, '.co-layout');
                swap(fresh, '.co-mobilebar');
                swap(fresh, '.nav-panier-link');
                swap(fresh, '.shop-mobile-dock__fab');
                var currentAcc = doc.getElementById('panierAccessoires');
                var nextAcc = fresh.getElementById('panierAccessoires');
                if (currentAcc && !nextAcc) {
                    currentAcc.remove();
                }
                if (typeof win.panierBindControls === 'function') {
                    win.panierBindControls(doc.querySelector('.co-layout') || doc);
                }
                bindArrows();
                busy = false;
            }, 450);
            toast('Ajouté au panier');
        }).catch(function () {
            busy = false;
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('is-loading');
            }
            toast('Impossible d’ajouter ce produit. Réessayez.', true);
        });
    }

    /* Flèches (ordinateur) : défilement d'une carte à la fois */
    function bindArrows() {
        var section = doc.getElementById('panierAccessoires');
        if (!section || section.dataset.paccBound === '1') {
            return;
        }
        section.dataset.paccBound = '1';
        var track = section.querySelector('.pacc__track');
        section.querySelectorAll('[data-pacc-scroll]').forEach(function (arrow) {
            arrow.addEventListener('click', function () {
                if (!track) {
                    return;
                }
                var card = track.querySelector('.pacc-card');
                var step = card ? card.getBoundingClientRect().width + 12 : 200;
                track.scrollBy({ left: step * Number(arrow.getAttribute('data-pacc-scroll')), behavior: 'smooth' });
            });
        });
    }

    /* Panier : l'IA prépare les compléments des produits inconnus des règles, puis le bloc est remplacé */
    function loadIaSuggestions() {
        var slot = doc.getElementById('panierAccessoiresIa');
        if (!slot || !win.fetch) {
            return;
        }
        win.fetch(slot.getAttribute('data-api'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'panier' })
        }).then(function (r) {
            return r.ok ? r.json() : null;
        }).then(function (d) {
            if (!d || !d.success || !d.html) {
                return;
            }
            var tpl = doc.createElement('template');
            tpl.innerHTML = d.html;
            var fresh = tpl.content.firstElementChild;
            var current = doc.getElementById('panierAccessoires');
            if (!fresh) {
                return;
            }
            if (current) {
                current.replaceWith(fresh);
                slot.remove();
            } else {
                slot.replaceWith(fresh);
            }
            bindArrows();
        }).catch(function () { /* le bloc existant reste affiché */ });
    }

    function init() {
        bindArrows();
        loadIaSuggestions();
    }

    doc.addEventListener('submit', onSubmit);
    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
