/**
 * Suivi de commande client : fenêtre de notation après livraison.
 * La note est enregistrée pour chaque article de la commande (user/avis-produits-action.php, action « save »).
 */
(function (doc) {
    'use strict';

    var sheet = doc.getElementById('otRateSheet');
    var root = doc.querySelector('.ot');
    if (!sheet || !root) {
        return;
    }

    var MOODS = [
        ['😞', 'Très décevant'],
        ['🙁', 'Décevant'],
        ['😐', 'Correct'],
        ['🙂', 'Bien'],
        ['🤩', 'Excellent !']
    ];
    var commandeId = root.getAttribute('data-commande');
    var products = (sheet.getAttribute('data-products') || '').split(',').filter(Boolean);
    var boutique = sheet.getAttribute('data-boutique') || 'la boutique';
    var stars = sheet.querySelectorAll('.ot-star');
    var submit = sheet.querySelector('[data-ot-submit]');
    var emoji = sheet.querySelector('.ot-mood__emoji');
    var label = sheet.querySelector('.ot-mood__label');
    var note = 0;
    var sending = false;

    function setNote(n, animate) {
        note = n;
        stars.forEach(function (s, i) {
            var on = i < n;
            s.classList.toggle('is-on', on);
            s.setAttribute('aria-checked', i === n - 1 ? 'true' : 'false');
            if (animate && on) {
                s.classList.remove('is-pop');
                void s.offsetWidth;
                s.classList.add('is-pop');
            }
        });
        emoji.textContent = n ? MOODS[n - 1][0] : '';
        label.textContent = n ? MOODS[n - 1][1] : 'Touchez une étoile';
        submit.disabled = n === 0;
    }

    function open() {
        sheet.hidden = false;
        doc.documentElement.style.overflow = 'hidden';
        var first = stars[0];
        if (first) {
            first.focus();
        }
    }

    function close() {
        sheet.hidden = true;
        doc.documentElement.style.overflow = '';
    }

    stars.forEach(function (s) {
        s.addEventListener('click', function () {
            setNote(parseInt(s.getAttribute('data-note'), 10) || 0, true);
        });
    });

    sheet.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
            e.preventDefault();
            setNote(Math.min(5, note + 1), true);
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
            e.preventDefault();
            setNote(Math.max(1, note - 1), true);
        } else if (e.key === 'Escape') {
            close();
        }
    });

    doc.querySelectorAll('[data-ot-rate]').forEach(function (b) {
        b.addEventListener('click', open);
    });
    sheet.querySelectorAll('[data-ot-close]').forEach(function (b) {
        b.addEventListener('click', close);
    });

    submit.addEventListener('click', function () {
        if (!note || sending) {
            return;
        }
        sending = true;
        submit.disabled = true;
        submit.textContent = 'Envoi…';

        var body = new URLSearchParams();
        body.append('action', 'save');
        products.forEach(function (pid) {
            body.append('notes[' + pid + '-' + commandeId + ']', String(note));
        });

        fetch(sheet.getAttribute('data-endpoint'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) {
            return r.json();
        }).then(function (d) {
            if (!d || !d.success) {
                throw new Error((d && d.message) || 'Erreur');
            }
            sheet.querySelector('[data-step="rate"]').hidden = true;
            sheet.querySelector('[data-step="thanks"]').hidden = false;
            sheet.querySelector('.ot-thanks__text').textContent =
                'Votre note de ' + note + ' étoile' + (note > 1 ? 's' : '') + ' a été transmise à ' + boutique + '.';
            doc.querySelectorAll('[data-ot-rate]').forEach(function (b) {
                var done = doc.createElement('span');
                done.className = 'ot-rated';
                done.innerHTML = '<i class="fas fa-circle-check" aria-hidden="true"></i> Noté';
                b.replaceWith(done);
            });
            window.setTimeout(close, 2200);
        }).catch(function (err) {
            sending = false;
            submit.disabled = false;
            submit.textContent = 'Envoyer ma note';
            label.textContent = err.message === 'Erreur' ? 'Envoi impossible, réessayez.' : err.message;
        });
    });

    /* Ouverture automatique après la confirmation de réception */
    if (/[?&]receive_ok=1/.test(window.location.search)) {
        window.setTimeout(open, 900);
    }
})(document);
