/**
 * Messagerie temps réel — polling fil, inbox et badges
 */
(function () {
    'use strict';

    var cfg = window.MSG_REALTIME || {};
    if (!cfg.enabled) {
        return;
    }

    var apiBase = String(cfg.apiBase || '/api').replace(/\/$/, '');
    var state = {
        convId: parseInt(cfg.convId, 10) || 0,
        lastMessageId: parseInt(cfg.lastMessageId, 10) || 0,
        pollThreadMs: parseInt(cfg.pollThreadMs, 10) || 6000,
        pollInboxMs: parseInt(cfg.pollInboxMs, 10) || 12000,
        pollUnreadMs: parseInt(cfg.pollUnreadMs, 10) || 25000,
        // Onglet / app en arrière-plan : on espace fortement (batterie, data mobile)
        pollThreadHiddenMs: parseInt(cfg.pollThreadHiddenMs, 10) || 30000,
        filtre: String(cfg.filtre || 'tout'),
        role: String(cfg.role || 'client'),
        polling: false,
        stopped: false
    };

    /* App mobile : les push FCM déclenchent msgRealtimePollNow() dès qu'un message
       arrive, le polling ne sert plus que de filet de sécurité → moins de requêtes. */
    if (window.__COLOBANES_NATIVE_APP) {
        if (!cfg.pollThreadMs) { state.pollThreadMs = 9000; }
        if (!cfg.pollInboxMs) { state.pollInboxMs = 30000; }
        if (!cfg.pollUnreadMs) { state.pollUnreadMs = 60000; }
    }

    var threadTimer = null;
    var inboxTimer = null;
    var unreadTimer = null;
    var lastPollAt = 0;
    var markReadTimer = null;
    var markReadInFlight = false;
    var unreadObserver = null;

    function logWarn(err) {
        if (typeof console !== 'undefined' && console.warn) {
            console.warn('[MSG-RT]', err);
        }
    }

    function apiUrl(path, params) {
        var url = apiBase + path;
        var parts = [];
        if (params) {
            Object.keys(params).forEach(function (key) {
                if (params[key] === undefined || params[key] === null) {
                    return;
                }
                parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(params[key])));
            });
        }
        if (parts.length) {
            url += (url.indexOf('?') >= 0 ? '&' : '?') + parts.join('&');
        }
        return url;
    }

    function fetchJson(url) {
        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (res) {
            if (res.status === 401) {
                state.stopped = true;
                clearAllTimers();
                return null;
            }
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.json();
        });
    }

    function clearAllTimers() {
        if (threadTimer) {
            clearTimeout(threadTimer);
            threadTimer = null;
        }
        if (inboxTimer) {
            clearTimeout(inboxTimer);
            inboxTimer = null;
        }
        if (unreadTimer) {
            clearTimeout(unreadTimer);
            unreadTimer = null;
        }
    }

    function scheduleThreadPoll(delay) {
        if (state.stopped || state.convId <= 0) {
            return;
        }
        if (threadTimer) {
            clearTimeout(threadTimer);
        }
        threadTimer = setTimeout(function () {
            pollThread().finally(function () {
                var ms = document.hidden ? state.pollThreadHiddenMs : state.pollThreadMs;
                scheduleThreadPoll(ms);
            });
        }, delay);
    }

    function scheduleInboxPoll(delay) {
        if (state.stopped) {
            return;
        }
        if (inboxTimer) {
            clearTimeout(inboxTimer);
        }
        inboxTimer = setTimeout(function () {
            pollInbox().finally(function () {
                scheduleInboxPoll(document.hidden ? state.pollInboxMs * 5 : state.pollInboxMs);
            });
        }, delay);
    }

    function scheduleUnreadPoll(delay) {
        if (state.stopped) {
            return;
        }
        if (unreadTimer) {
            clearTimeout(unreadTimer);
        }
        unreadTimer = setTimeout(function () {
            pollUnread().finally(function () {
                scheduleUnreadPoll(document.hidden ? state.pollUnreadMs * 4 : state.pollUnreadMs);
            });
        }, delay);
    }

    function threadRoot() {
        return document.querySelector('.msg-chat__thread');
    }

    function scrollAnchor() {
        return document.getElementById('msgThreadScrollEnd');
    }

    function messageExists(msgId) {
        if (!msgId) {
            return false;
        }
        return !!document.querySelector('.msg-chat__thread [data-msg-id="' + msgId + '"]');
    }

    function appendThreadHtml(html) {
        if (!html) {
            return;
        }
        var root = threadRoot();
        var anchor = scrollAnchor();
        if (!root || !anchor) {
            return;
        }

        var wrap = document.createElement('div');
        wrap.innerHTML = html;
        var nodes = Array.prototype.slice.call(wrap.childNodes);
        var pinned = typeof window.msgChatIsPinnedToBottom === 'function'
            ? window.msgChatIsPinnedToBottom()
            : true;

        nodes.forEach(function (node) {
            if (node.nodeType !== 1) {
                return;
            }
            var msgId = node.getAttribute('data-msg-id');
            var metaFor = node.getAttribute('data-msg-meta-for');
            if (metaFor && messageExists(metaFor)) {
                return;
            }
            if (msgId && messageExists(msgId)) {
                return;
            }
            root.insertBefore(node, anchor);
        });

        if (pinned && typeof window.msgChatScrollToBottom === 'function') {
            window.msgChatScrollToBottom(true, false);
        }

        observeUnreadMessages();
        if (pinned) {
            scheduleMarkConversationRead();
        }
    }

    function hasUnreadInThread() {
        return !!document.querySelector('.msg-chat__thread [data-unread="1"]');
    }

    function clearUnreadVisuals() {
        document.querySelectorAll('.msg-chat__thread [data-unread="1"]').forEach(function (el) {
            el.classList.remove('msg-chat__row--unread', 'msg-chat__offer-block--unread', 'msg-chat__notice--unread');
            el.removeAttribute('data-unread');
            var pill = el.querySelector('.msg-chat__unread-pill');
            if (pill) {
                pill.remove();
            }
        });
    }

    function markActiveInboxRead() {
        if (state.convId <= 0) {
            return;
        }
        var item = document.querySelector('.msg-inbox__item[data-conv-id="' + state.convId + '"]');
        if (!item) {
            return;
        }
        item.classList.remove('is-unread');
        var badge = item.querySelector('.msg-inbox__unread-badge');
        if (badge) {
            badge.remove();
        }
    }

    function scheduleMarkConversationRead() {
        if (state.convId <= 0 || !hasUnreadInThread() || markReadInFlight) {
            return;
        }
        if (markReadTimer) {
            clearTimeout(markReadTimer);
        }
        markReadTimer = setTimeout(function () {
            markReadInFlight = true;
            fetchJson(apiUrl('/messages_vendeur_mark_read.php', { conv: state.convId }))
                .then(function (data) {
                    if (!data || !data.ok) {
                        return;
                    }
                    clearUnreadVisuals();
                    markActiveInboxRead();
                    if (typeof data.total_unread === 'number') {
                        syncUnreadBadges(data.total_unread);
                    }
                })
                .catch(logWarn)
                .finally(function () {
                    markReadInFlight = false;
                });
        }, 700);
    }

    function observeUnreadMessages() {
        var scrollEl = document.getElementById('msgThreadScroll');
        if (!scrollEl || typeof IntersectionObserver === 'undefined') {
            return;
        }
        if (unreadObserver) {
            unreadObserver.disconnect();
        }
        unreadObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    scheduleMarkConversationRead();
                }
            });
        }, { root: scrollEl, threshold: 0.45 });

        document.querySelectorAll('.msg-chat__thread [data-unread="1"]').forEach(function (el) {
            unreadObserver.observe(el);
        });
    }

    function syncPresence(presence) {
        if (!presence || !presence.label) {
            return;
        }
        var subtitle = document.querySelector('.msg-chat__header .msg-chat__subtitle');
        if (!subtitle) {
            return;
        }
        subtitle.textContent = presence.label;
        if (presence.online) {
            subtitle.classList.add('msg-chat__subtitle--online');
        } else {
            subtitle.classList.remove('msg-chat__subtitle--online');
        }
    }

    function syncThreadState(threadState) {
        if (!threadState) {
            return;
        }
        var chat = document.querySelector('.msg-chat');
        if (!chat) {
            return;
        }

        if (threadState.has_offre_acceptee_a_payer) {
            chat.classList.add('msg-chat--offer-accepted');
            chat.classList.remove('msg-chat--has-actions');
        } else if (threadState.show_chat_actions) {
            chat.classList.remove('msg-chat--offer-accepted');
            chat.classList.add('msg-chat--has-actions');
        }

        var actions = chat.querySelector('.msg-chat__actions');
        if (actions) {
            actions.style.display = threadState.show_chat_actions ? '' : 'none';
        }

        var offerBtn = document.getElementById('msgOfferOpenBtn');
        if (offerBtn) {
            offerBtn.style.display = threadState.can_negocier ? '' : 'none';
        }
    }

    function setBadgeOnLink(link, badgeId, badgeClass, count) {
        if (!link) {
            return;
        }
        var badge = document.getElementById(badgeId) || link.querySelector('.' + badgeClass.split(' ')[0]);
        count = Math.max(0, parseInt(count, 10) || 0);

        if (count <= 0) {
            if (badge) {
                badge.remove();
            }
            link.classList.remove('has-badge');
            link.setAttribute('aria-label', 'Messages');
            link.setAttribute('title', 'Messages');
            return;
        }

        if (!badge) {
            badge = document.createElement('span');
            badge.className = badgeClass;
            badge.id = badgeId;
            badge.setAttribute('aria-hidden', 'true');
            link.insertBefore(badge, link.firstChild);
        }

        badge.textContent = count > 99 ? '99+' : String(count);
        link.classList.add('has-badge');
        var label = 'Messages — ' + count + ' non lu' + (count > 1 ? 's' : '');
        link.setAttribute('aria-label', label);
        link.setAttribute('title', label);
    }

    function syncUnreadBadges(count) {
        count = Math.max(0, parseInt(count, 10) || 0);

        setBadgeOnLink(
            document.getElementById('navMessagesLink'),
            'navMessagesBadge',
            'nav-messages-badge',
            count
        );
        setBadgeOnLink(
            document.getElementById('shopDockMessagesLink'),
            'shopDockMessagesBadge',
            'shop-mobile-dock__badge shop-mobile-dock__badge--messages',
            count
        );

        var adminBadge = document.querySelector('a[href*="admin/messages.php"] .menu-item__badge');
        if (adminBadge) {
            if (count <= 0) {
                adminBadge.remove();
            } else {
                adminBadge.textContent = count > 99 ? '99+' : String(count);
            }
        }

        var desktopUnread = document.querySelector('.msg-inbox__desktop-unread');
        if (desktopUnread) {
            if (count <= 0) {
                desktopUnread.remove();
            } else {
                desktopUnread.textContent = String(count);
            }
        }

        var filterBadge = document.querySelector('.msg-inbox__filter-badge');
        if (filterBadge) {
            if (count <= 0) {
                filterBadge.remove();
            } else {
                filterBadge.textContent = count > 99 ? '99+' : String(count);
            }
        }
    }

    function updateInboxItem(row) {
        if (!row || !row.id) {
            return;
        }
        var item = document.querySelector('.msg-inbox__item[data-conv-id="' + row.id + '"]');
        if (!item) {
            return;
        }

        if (row.nb_non_lus > 0) {
            item.classList.add('is-unread');
            item.classList.remove('is-read');
        } else {
            item.classList.remove('is-unread');
            item.classList.add('is-read');
            var unreadBadge = item.querySelector('.msg-inbox__unread-badge');
            if (unreadBadge) {
                unreadBadge.remove();
            }
        }

        var title = item.querySelector('.msg-inbox__title-line');
        if (title && row.label) {
            title.textContent = row.label;
        }

        var price = item.querySelector('.msg-inbox__price');
        if (price && typeof row.prix === 'number' && row.prix > 0) {
            price.textContent = Math.round(row.prix).toLocaleString('fr-FR') + ' FCFA';
        }

        var meta = item.querySelector('.msg-inbox__meta');
        if (meta) {
            meta.textContent = row.boutique_nom || row.client_nom || '';
        }
    }

    function pollThread() {
        if (state.stopped || state.convId <= 0 || state.polling) {
            return Promise.resolve();
        }

        // Hors ligne (réseau mobile coupé) : inutile de multiplier les requêtes en échec
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            return Promise.resolve();
        }

        var now = Date.now();
        if (now - lastPollAt < 1500) {
            return Promise.resolve();
        }
        lastPollAt = now;
        state.polling = true;

        return fetchJson(apiUrl('/messages_vendeur_thread.php', {
            conv: state.convId,
            since_id: state.lastMessageId,
            format: 'html'
        }))
            .then(function (data) {
                if (!data || !data.ok) {
                    return;
                }
                if (data.last_id) {
                    state.lastMessageId = parseInt(data.last_id, 10) || state.lastMessageId;
                }
                if (data.html) {
                    appendThreadHtml(data.html);
                } else {
                    observeUnreadMessages();
                }
                syncPresence(data.presence);
                syncThreadState(data.thread_state);
            })
            .catch(logWarn)
            .finally(function () {
                state.polling = false;
            });
    }

    function pollInbox() {
        if (state.stopped) {
            return Promise.resolve();
        }

        return fetchJson(apiUrl('/messages_vendeur_inbox.php', { filtre: state.filtre }))
            .then(function (data) {
                if (!data || !data.ok) {
                    return;
                }
                if (typeof data.total_unread === 'number') {
                    syncUnreadBadges(data.total_unread);
                }
                if (Array.isArray(data.conversations)) {
                    data.conversations.forEach(updateInboxItem);
                }
            })
            .catch(logWarn);
    }

    function pollUnread() {
        if (state.stopped) {
            return Promise.resolve();
        }

        return fetchJson(apiUrl('/messages_vendeur_unread.php'))
            .then(function (data) {
                if (!data || !data.ok) {
                    return;
                }
                syncUnreadBadges(data.count);
            })
            .catch(logWarn);
    }

    function pollNow() {
        var tasks = [pollUnread()];
        if (state.convId > 0) {
            tasks.push(pollThread());
        }
        tasks.push(pollInbox());
        return Promise.all(tasks);
    }

    window.msgRealtimePollNow = pollNow;
    window.msgRealtimeGetLastMessageId = function () {
        return state.lastMessageId;
    };
    /* Après un envoi AJAX : récupère tout de suite le message (ignore l'anti-rafale) */
    window.msgRealtimePollThreadNow = function () {
        lastPollAt = 0;
        return pollThread();
    };
    window.msgRealtimeSetLastMessageId = function (id) {
        var n = parseInt(id, 10);
        if (n > 0) {
            state.lastMessageId = n;
        }
    };

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            pollNow();
        }
    });

    window.addEventListener('online', function () {
        pollNow();
    });

    if (state.convId > 0) {
        scheduleThreadPoll(state.pollThreadMs);
        observeUnreadMessages();
        if (typeof window.msgChatIsPinnedToBottom === 'function' && window.msgChatIsPinnedToBottom()) {
            scheduleMarkConversationRead();
        }
    }
    scheduleInboxPoll(state.pollInboxMs);
    scheduleUnreadPoll(state.pollUnreadMs);
})();
