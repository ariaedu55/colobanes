/**
 * Invitation au don — au plus une fois tous les 3 jours par appareil (site et application).
 * S'ouvre après 15 s de navigation ou dès que la personne a fait défiler la moitié de la page,
 * jamais par-dessus une autre fenêtre (invitation à télécharger l'app, assistant, etc.).
 */
(function (win, doc) {
    'use strict';

    var root = doc.getElementById('donsPrompt');
    if (!root) {
        return;
    }
    var KEY = 'colobanes_dons_prompt_at';
    var INTERVAL_MS = 3 * 24 * 60 * 60 * 1000;
    var DELAY_MS = 15000;
    var opened = false;
    var lastFocus = null;

    function lastShown() {
        try {
            return parseInt(win.localStorage.getItem(KEY) || '0', 10) || 0;
        } catch (e) {
            /* Stockage indisponible : cookie de secours */
            var m = doc.cookie.match(/(?:^|;\s*)colobanes_dons_prompt_at=(\d+)/);
            return m ? parseInt(m[1], 10) : 0;
        }
    }

    function remember() {
        var now = String(Date.now());
        try {
            win.localStorage.setItem(KEY, now);
        } catch (e) { /* ignore */ }
        doc.cookie = 'colobanes_dons_prompt_at=' + now + '; max-age=' + (60 * 60 * 24 * 30) + '; path=/; SameSite=Lax';
    }

    /* Une autre fenêtre est déjà ouverte : on attend */
    function busy() {
        var b = doc.body;
        if (b.classList.contains('asp-prompt-open') || b.classList.contains('sa-cat-modal-open')) {
            return true;
        }
        var ca = doc.getElementById('caPanel');
        if (ca && !ca.hidden) {
            return true;
        }
        /* Seules les fenêtres réellement visibles comptent (certains panneaux sont masqués par CSS, sans « hidden ») */
        var dialogs = doc.querySelectorAll('[role="dialog"][aria-modal="true"]:not([hidden]):not(.dp-card), .flash-popup.is-visible');
        for (var i = 0; i < dialogs.length; i++) {
            var r = dialogs[i].getBoundingClientRect();
            var cs = win.getComputedStyle(dialogs[i]);
            if (r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && parseFloat(cs.opacity) > 0.05) {
                return true;
            }
        }
        return false;
    }

    function open() {
        if (opened) {
            return;
        }
        if (busy()) {
            win.setTimeout(open, 4000);
            return;
        }
        opened = true;
        remember();
        lastFocus = doc.activeElement;
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        win.requestAnimationFrame(function () {
            root.classList.add('is-open');
            var cta = root.querySelector('.dp-btn--primary');
            if (cta) {
                cta.focus({ preventScroll: true });
            }
        });
        doc.addEventListener('keydown', onKey);
    }

    function close() {
        root.classList.remove('is-open');
        doc.removeEventListener('keydown', onKey);
        win.setTimeout(function () {
            root.hidden = true;
            root.setAttribute('aria-hidden', 'true');
        }, 280);
        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus({ preventScroll: true });
        }
    }

    function onKey(e) {
        if (e.key === 'Escape') {
            close();
        }
    }

    root.querySelectorAll('[data-dp-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    if (Date.now() - lastShown() < INTERVAL_MS) {
        return;
    }

    var timer = win.setTimeout(open, DELAY_MS);
    function onScroll() {
        var h = doc.documentElement;
        if (h.scrollTop + win.innerHeight >= h.scrollHeight * 0.5) {
            win.removeEventListener('scroll', onScroll);
            win.clearTimeout(timer);
            win.setTimeout(open, 600);
        }
    }
    win.addEventListener('scroll', onScroll, { passive: true });
})(window, document);
