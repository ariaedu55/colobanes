/**
 * Module Dons : filtres auto, galerie, aperçu des photos, confirmations, messagerie en direct.
 */
(function () {
    'use strict';

    /* Filtres : envoi automatique au changement */
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () {
            if (el.form) el.form.submit();
        });
    });

    /* Confirmations des actions sensibles */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
        });
    });

    /* Galerie du détail */
    var main = document.getElementById('dnMainImg');
    document.querySelectorAll('.dn-thumb').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!main) return;
            main.src = btn.getAttribute('data-src');
            document.querySelectorAll('.dn-thumb').forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
        });
    });

    /* Publication : photos cumulées avec aperçu (6 max) */
    var input = document.getElementById('dnPhotoInput');
    var addTile = document.getElementById('dnPhotoAdd');
    if (input && addTile && window.DataTransfer) {
        var store = new DataTransfer();
        var maxPhotos = 6;
        var existingCount = function () {
            return document.querySelectorAll('.dn-photo--existing input:not(:checked)').length;
        };
        var render = function () {
            document.querySelectorAll('.dn-photo--new').forEach(function (n) { n.remove(); });
            Array.prototype.forEach.call(store.files, function (file, idx) {
                var tile = document.createElement('div');
                tile.className = 'dn-photo dn-photo--new';
                var img = document.createElement('img');
                img.alt = '';
                img.src = URL.createObjectURL(file);
                var rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'dn-photo__remove';
                rm.setAttribute('aria-label', 'Retirer cette photo');
                rm.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
                rm.addEventListener('click', function () {
                    var next = new DataTransfer();
                    Array.prototype.forEach.call(store.files, function (f, i) { if (i !== idx) next.items.add(f); });
                    store = next;
                    input.files = store.files;
                    render();
                });
                tile.appendChild(img);
                tile.appendChild(rm);
                addTile.parentNode.insertBefore(tile, addTile);
            });
            addTile.hidden = existingCount() + store.files.length >= maxPhotos;
        };
        input.addEventListener('change', function () {
            Array.prototype.forEach.call(input.files, function (f) {
                if (existingCount() + store.files.length < maxPhotos && /^image\//.test(f.type)) store.items.add(f);
            });
            input.files = store.files;
            render();
        });
        document.querySelectorAll('.dn-photo--existing input').forEach(function (cb) {
            cb.addEventListener('change', render);
        });
    }

    /* Conversation : envoi sans rechargement + nouveaux messages toutes les 5 s */
    var chat = document.querySelector('.dn-chat[data-conversation]');
    if (!chat) return;
    var convId = chat.getAttribute('data-conversation');
    var lastId = parseInt(chat.getAttribute('data-last'), 10) || 0;
    var thread = document.getElementById('dnThread');
    var form = document.getElementById('dnCompose');
    var area = document.getElementById('dnMsg');
    var errorBox = document.getElementById('dnChatError');
    /* getAttribute : le champ caché name="action" masque la propriété form.action */
    var endpoint = form.getAttribute('action');
    var initialStatut = null;

    var scrollDown = function () { thread.scrollTop = thread.scrollHeight; };
    scrollDown();

    var append = function (m) {
        if (thread.querySelector('[data-id="' + m.id + '"]')) return;
        var row = document.createElement('div');
        row.className = 'dn-msg' + (m.systeme ? ' dn-msg--sys' : '') + (m.mine ? ' dn-msg--mine' : '');
        row.setAttribute('data-id', m.id);
        row.innerHTML = '<div class="dn-msg__bubble">' + m.html + '</div><time>' + m.heure + '</time>';
        thread.appendChild(row);
        lastId = Math.max(lastId, m.id);
    };

    var poll = function () {
        fetch(endpoint + '?action=poll&conversation=' + encodeURIComponent(convId) + '&after=' + lastId, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.ok ? r.json() : null; }).then(function (data) {
            if (!data || !data.ok) return;
            if (initialStatut === null) initialStatut = data.statut;
            if (data.statut !== initialStatut) { window.location.reload(); return; }
            if (data.messages.length) {
                var nearBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 120;
                data.messages.forEach(append);
                if (nearBottom) scrollDown();
            }
        }).catch(function () {});
    };
    poll();
    setInterval(function () { if (!document.hidden) poll(); }, 5000);

    var grow = function () {
        area.style.height = 'auto';
        area.style.height = Math.min(area.scrollHeight, 140) + 'px';
    };
    area.addEventListener('input', grow);
    area.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && window.matchMedia('(min-width: 768px)').matches) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = area.value.trim();
        if (!text) return;
        var btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        errorBox.hidden = true;
        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data && data.ok) {
                area.value = '';
                grow();
                poll();
                setTimeout(scrollDown, 300);
            } else {
                errorBox.textContent = (data && data.message) || 'Envoi impossible.';
                errorBox.hidden = false;
            }
        }).catch(function () {
            errorBox.textContent = 'Connexion perdue, réessayez.';
            errorBox.hidden = false;
        }).then(function () { btn.disabled = false; });
    });
})();
