/**
 * Assistant COLObanes — interface de discussion.
 * La conversation est gardée pendant la navigation (sessionStorage) et envoyée à api/assistant.php.
 */
(function (win, doc) {
    'use strict';

    var STORE_KEY = 'colobanes_assistant_v1';
    var panel = doc.getElementById('caPanel');
    var launcher = doc.getElementById('caLauncher');
    if (!panel || !launcher) {
        return;
    }

    var body = doc.getElementById('caBody');
    var form = doc.getElementById('caForm');
    var input = doc.getElementById('caText');
    var send = doc.getElementById('caSend');
    var endpoint = panel.getAttribute('data-endpoint');
    var botName = panel.getAttribute('data-bot') || 'Kennassy';
    /* Photo de l'assistante (vide = pas de photo à côté des messages) */
    var botAvatar = panel.getAttribute('data-avatar') || '';
    function avatarHtml() {
        return botAvatar ? '<img class="ca-msg__avatar" src="' + esc(botAvatar) + '" alt="" width="30" height="30">' : '';
    }
    var userName = panel.getAttribute('data-user') || '';

    var state = { open: false, messages: [], context: null };
    var busy = false;

    /* ---------- Stockage ---------- */
    function load() {
        try {
            var raw = win.sessionStorage.getItem(STORE_KEY);
            if (raw) {
                var s = JSON.parse(raw);
                if (s && Array.isArray(s.messages)) {
                    state.messages = s.messages.slice(-40);
                    state.context = s.context || null;
                    state.open = !!s.open;
                }
            }
        } catch (e) { /* stockage indisponible */ }
    }

    function save() {
        try {
            win.sessionStorage.setItem(STORE_KEY, JSON.stringify({
                messages: state.messages.slice(-40),
                context: state.context,
                open: state.open
            }));
        } catch (e) { /* stockage indisponible */ }
    }

    /* ---------- Rendu ---------- */
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /* Liens internes uniquement : chemin relatif du site ou URL https de la même origine */
    function isSiteUrl(u) {
        u = String(u || '');
        if (/[\\\x00-\x1F\x7F]/.test(u)) { return false; }
        if (/^\/(?!\/)/.test(u)) { return true; }
        var origin = (win.location && win.location.origin) || '';
        return origin.indexOf('https://') === 0 && (u === origin || u.indexOf(origin + '/') === 0);
    }

    function safeUrl(u) {
        return isSiteUrl(u) ? String(u) : '#';
    }

    /* Texte du bot : **gras**, [lien](url), listes « • » / « 1. », retours à la ligne */
    function formatText(text) {
        var html = esc(text);
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, label, url) {
            var raw = url.replace(/&amp;/g, '&');
            /* Lien externe : rendu en texte simple, non cliquable */
            if (!isSiteUrl(raw)) { return label; }
            return '<a href="' + esc(raw) + '">' + label + '</a>';
        });
        return html.replace(/\n/g, '<br>');
    }

    function price(n) {
        return Math.round(Number(n) || 0).toLocaleString('fr-FR').replace(/ | /g, ' ') + ' FCFA';
    }

    function scrollDown() {
        win.requestAnimationFrame(function () {
            body.scrollTop = body.scrollHeight;
        });
    }

    function renderExtras(m) {
        var out = '';
        if (m.products && m.products.length) {
            out += '<div class="ca-cards" role="list">';
            m.products.forEach(function (p) {
                out += '<a class="ca-card" role="listitem" href="' + esc(safeUrl(p.url)) + '">'
                    + '<span class="ca-card__img"><img src="' + esc(p.image) + '" alt="" loading="lazy" onerror="this.style.visibility=\'hidden\'">'
                    + (p.ancien_prix ? '<span class="ca-card__badge">Promo</span>' : '')
                    + (p.en_stock === false ? '<span class="ca-card__badge ca-card__badge--out">Rupture</span>' : '')
                    + '</span><span class="ca-card__body">'
                    + '<span class="ca-card__name">' + esc(p.nom) + '</span>'
                    + '<span class="ca-card__price">' + esc(price(p.prix))
                    + (p.ancien_prix ? '<span class="ca-card__old">' + esc(price(p.ancien_prix)) + '</span>' : '') + '</span>'
                    + (p.boutique ? '<span class="ca-card__shop">' + esc(p.boutique) + '</span>' : '')
                    + '</span></a>';
            });
            out += '</div>';
        }
        if (m.shops && m.shops.length) {
            out += '<div class="ca-list">';
            m.shops.forEach(function (s) {
                var initial = esc(String(s.nom || 'B').charAt(0).toUpperCase());
                out += '<a class="ca-row" href="' + esc(safeUrl(s.url)) + '">'
                    + '<span class="ca-row__logo">' + (s.logo ? '<img src="' + esc(s.logo) + '" alt="" onerror="this.remove()">' : '') + (s.logo ? '' : initial) + '</span>'
                    + '<span class="ca-row__text"><span class="ca-row__title">' + esc(s.nom) + '</span>'
                    + '<span class="ca-row__sub">' + esc(s.produits) + ' produit' + (s.produits > 1 ? 's' : '') + (s.region ? ' · ' + esc(s.region) : '') + '</span></span>'
                    + '</a>';
            });
            out += '</div>';
        }
        if (m.orders && m.orders.length) {
            out += '<div class="ca-list">';
            m.orders.forEach(function (o) {
                out += '<a class="ca-row" href="' + esc(safeUrl(o.url)) + '">'
                    + '<span class="ca-row__text"><span class="ca-row__title">#' + esc(o.numero) + '</span>'
                    + '<span class="ca-row__sub">' + esc(o.date) + ' · ' + esc(price(o.montant)) + '</span></span>'
                    + '<span class="ca-row__pill">' + esc(o.statut) + '</span></a>';
            });
            out += '</div>';
        }
        if (m.links && m.links.length) {
            out += '<div class="ca-chips">';
            m.links.forEach(function (l) {
                out += '<a class="ca-chip ca-chip--link" href="' + esc(safeUrl(l.url)) + '">' + esc(l.label) + '</a>';
            });
            out += '</div>';
        }
        return out;
    }

    function renderSuggestions(list) {
        if (!list || !list.length) {
            return '';
        }
        return '<div class="ca-chips ca-chips--suggest">' + list.map(function (s) {
            return '<button type="button" class="ca-chip" data-suggest="' + esc(s) + '">' + esc(s) + '</button>';
        }).join('') + '</div>';
    }

    function render() {
        var html = '';
        state.messages.forEach(function (m, i) {
            if (m.role === 'user') {
                html += '<div class="ca-msg ca-msg--user"><div class="ca-bubble">' + esc(m.text) + '</div></div>';
            } else {
                html += '<div class="ca-msg ca-msg--bot">' + avatarHtml() + '<div class="ca-bubble">' + formatText(m.text) + '</div></div>' + renderExtras(m);
                if (i === state.messages.length - 1) {
                    html += renderSuggestions(m.suggestions);
                }
            }
        });
        if (busy) {
            html += '<div class="ca-msg ca-msg--bot">' + avatarHtml() + '<div class="ca-bubble"><span class="ca-typing" aria-label="' + esc(botName) + ' écrit"><span></span><span></span><span></span></span></div></div>';
        }
        body.innerHTML = html;
        scrollDown();
    }

    function welcome() {
        state.messages = [{
            role: 'bot',
            text: 'Bonjour' + (userName ? ' ' + userName : '') + ' 👋 Comment puis-je vous aider ?',
            suggestions: ['Voir les promos', 'Suivre ma commande', 'Livraison']
        }];
        state.context = null;
    }

    /* ---------- Envoi ---------- */
    function ask(text) {
        text = String(text || '').trim();
        if (!text || busy) {
            return;
        }
        var history = state.messages.slice(-10).map(function (m) {
            return { role: m.role, text: m.text };
        });
        state.messages.push({ role: 'user', text: text });
        busy = true;
        input.value = '';
        autosize();
        render();
        save();

        var payload = JSON.stringify({ message: text, history: history, context: state.context });
        /* Une coupure passagère (serveur ou réseau) est retentée automatiquement une fois */
        function ask(retry) {
            return fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: payload
            }).then(function (r) {
                return r.json().catch(function () {
                    return { success: false, retryable: true };
                }).then(function (d) {
                    if (r.status >= 500 && d) {
                        d.retryable = true;
                    }
                    return d;
                });
            }).catch(function () {
                return { success: false, retryable: true, offline: true };
            }).then(function (d) {
                if (retry && d && !d.success && d.retryable) {
                    return new Promise(function (ok) { win.setTimeout(ok, 1500); }).then(function () { return ask(false); });
                }
                return d;
            });
        }

        ask(true).then(function (d) {
            if (d && d.success) {
                if (d.context) {
                    state.context = d.context;
                }
                state.messages.push({
                    role: 'bot',
                    text: d.reply || '…',
                    products: d.products || [],
                    shops: d.shops || [],
                    orders: d.orders || [],
                    links: d.links || [],
                    suggestions: d.suggestions || []
                });
            } else if (d && d.offline) {
                state.messages.push({
                    role: 'bot',
                    text: 'La connexion semble coupée. Vérifiez votre réseau, puis renvoyez votre message.',
                    suggestions: [text]
                });
            } else {
                state.messages.push({
                    role: 'bot',
                    text: (d && d.message && !d.retryable) ? d.message : 'Un instant, je n\'ai pas pu terminer. Touchez votre question ci-dessous pour la renvoyer.',
                    suggestions: [text]
                });
            }
        }).then(function () {
            busy = false;
            render();
            save();
            updateSend();
        });
    }

    /* ---------- Ouverture / fermeture ---------- */
    function open() {
        state.open = true;
        panel.hidden = false;
        launcher.classList.add('is-hidden');
        launcher.setAttribute('aria-expanded', 'true');
        doc.body.classList.add('ca-open');
        if (!state.messages.length) {
            welcome();
        }
        render();
        save();
        if (win.matchMedia('(min-width: 641px)').matches) {
            input.focus();
        }
    }

    function close() {
        state.open = false;
        panel.hidden = true;
        launcher.classList.remove('is-hidden');
        launcher.setAttribute('aria-expanded', 'false');
        doc.body.classList.remove('ca-open');
        save();
        launcher.focus();
    }

    function autosize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    }

    function updateSend() {
        send.disabled = busy || input.value.trim() === '';
    }

    launcher.addEventListener('click', open);

    /* Téléphone : le bouton se range sur le côté quand il recouvre un bouton d'action important
       (Ajouter au panier, Commander, Confirmer…), puis revient. */
    (function avoidImportantButtons() {
        var SELECTOR = '#btn-add-panier, .cmd-submit, .co-mobilebar .co-btn, .co-summary .co-btn--primary, [data-ca-avoid]';
        var ticking = false;
        function overlaps(a, b) {
            return !(a.right < b.left || a.left > b.right || a.bottom < b.top || a.top > b.bottom);
        }
        function check() {
            ticking = false;
            if (state.open || !win.matchMedia('(max-width: 1024px)').matches) {
                launcher.classList.remove('ca-launcher--tucked');
                return;
            }
            launcher.classList.remove('ca-launcher--tucked');
            var lr = launcher.getBoundingClientRect();
            var hit = false;
            doc.querySelectorAll(SELECTOR).forEach(function (el) {
                if (hit || !el.offsetParent) return;
                if (overlaps(lr, el.getBoundingClientRect())) hit = true;
            });
            launcher.classList.toggle('ca-launcher--tucked', hit);
        }
        function schedule() {
            if (!ticking) {
                ticking = true;
                win.requestAnimationFrame(check);
            }
        }
        win.addEventListener('scroll', schedule, { passive: true });
        win.addEventListener('resize', schedule);
        win.setTimeout(check, 400);
    })();
    doc.getElementById('caClose').addEventListener('click', close);
    doc.getElementById('caReset').addEventListener('click', function () {
        welcome();
        render();
        save();
        input.focus();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        ask(input.value);
    });

    input.addEventListener('input', function () {
        autosize();
        updateSend();
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            ask(input.value);
        }
    });

    body.addEventListener('click', function (e) {
        var chip = e.target.closest('[data-suggest]');
        if (chip) {
            ask(chip.getAttribute('data-suggest'));
        }
    });

    doc.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && state.open) {
            close();
        }
    });

    /* Ouverture depuis n'importe quel lien : <a href="#assistant"> ou data-open-assistant */
    doc.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-open-assistant], a[href="#assistant"]');
        if (trigger) {
            e.preventDefault();
            open();
            /* data-assistant-question : la question est posée tout de suite (ex. recherche sans résultat) */
            var question = trigger.getAttribute('data-assistant-question');
            if (question) {
                ask(question);
            }
        }
    });
    win.openColoAssistant = open;

    load();
    /* Sur ordinateur, la fenêtre reste ouverte d'une page à l'autre ; sur téléphone (plein écran), on la referme */
    if (state.open && win.matchMedia('(min-width: 641px)').matches) {
        open();
    } else {
        state.open = false;
    }
})(window, document);
