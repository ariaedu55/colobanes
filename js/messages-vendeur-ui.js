/**
 * UI messagerie client — compose, scroll, modal offre
 */
(function () {
    var modal = document.getElementById('msgOfferModal');
    var openBtn = document.getElementById('msgOfferOpenBtn');

    function openModal() {
        if (!modal) {
            return;
        }
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('msg-offer-modal-open');
        var input = modal.querySelector('#msgPrixPropose');
        if (input) {
            setTimeout(function () {
                input.focus();
            }, 80);
        }
    }

    function closeModal() {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('msg-offer-modal-open');
        if (openBtn) {
            openBtn.focus();
        }
    }

    window.msgOfferModalOpen = openModal;
    window.msgOfferModalClose = closeModal;

    if (openBtn) {
        openBtn.addEventListener('click', openModal);
    }

    if (modal) {
        modal.querySelectorAll('[data-msg-offer-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });
    }

    var scrollEl = document.getElementById('msgThreadScroll');
    var scrollAnchor = document.getElementById('msgThreadScrollEnd');
    var composeForm = document.getElementById('msgChatComposeForm');
    var composeInput = document.getElementById('msgContenu');
    var sendBtn = document.getElementById('msgChatSendBtn');
    var chatFooter = document.querySelector('.msg-chat__footer');
    var chatRoot = document.querySelector('.msg-chat');
    var userPinnedToBottom = false;
    var bottomThreshold = 96;

    function threadOverflows() {
        if (!scrollEl) {
            return false;
        }
        return scrollEl.scrollHeight > scrollEl.clientHeight + 4;
    }

    function syncMessagesLayoutVars() {
        if (!document.body.classList.contains('site-page-messages--chat')) {
            return;
        }

        var nav = document.querySelector('.nav-planete-gateau');
        var chatHeader = document.querySelector('.msg-chat__header');

        if (nav) {
            var navH = Math.ceil(nav.getBoundingClientRect().height);
            if (navH > 0) {
                document.documentElement.style.setProperty('--msg-site-nav-h', navH + 'px');
            }
        }

        if (chatHeader && chatRoot) {
            var headerH = Math.ceil(chatHeader.getBoundingClientRect().height);
            if (headerH > 0) {
                document.documentElement.style.setProperty('--msg-chat-header-h', headerH + 'px');
                chatRoot.style.setProperty('--msg-chat-header-h', headerH + 'px');
            }
        }
    }

    function isNearBottom() {
        if (!scrollEl) {
            return true;
        }
        return (scrollEl.scrollHeight - scrollEl.scrollTop - scrollEl.clientHeight) <= bottomThreshold;
    }

    function scrollThreadToTop() {
        if (!scrollEl) {
            return;
        }
        if (typeof scrollEl.scrollTo === 'function') {
            scrollEl.scrollTo({ top: 0, behavior: 'auto' });
        } else {
            scrollEl.scrollTop = 0;
        }
    }

    function applyScrollPosition(smooth) {
        if (!scrollEl) {
            return;
        }

        if (!threadOverflows()) {
            scrollThreadToTop();
            return;
        }

        if (scrollAnchor && typeof scrollAnchor.scrollIntoView === 'function') {
            scrollAnchor.scrollIntoView({ block: 'end', behavior: smooth ? 'smooth' : 'auto' });
            return;
        }

        var top = scrollEl.scrollHeight;
        if (smooth && typeof scrollEl.scrollTo === 'function') {
            scrollEl.scrollTo({ top: top, behavior: 'smooth' });
        } else {
            scrollEl.scrollTop = top;
        }
    }

    function scrollThreadToBottom(smooth, force) {
        if (!scrollEl) {
            return;
        }
        if (!threadOverflows()) {
            scrollThreadToTop();
            return;
        }
        if (!force && !userPinnedToBottom) {
            return;
        }
        applyScrollPosition(!!smooth);
    }

    function ensureThreadAtTop() {
        if (!scrollEl) {
            return;
        }
        scrollThreadToTop();
        requestAnimationFrame(function () {
            scrollThreadToTop();
        });
    }

    function ensureThreadAtBottom(force) {
        if (!scrollEl) {
            return;
        }
        if (!threadOverflows()) {
            ensureThreadAtTop();
            return;
        }
        var mustForce = force !== false;

        applyScrollPosition(false);
        requestAnimationFrame(function () {
            applyScrollPosition(false);
            requestAnimationFrame(function () {
                applyScrollPosition(false);
            });
        });

        setTimeout(function () {
            scrollThreadToBottom(false, mustForce);
        }, 60);

        setTimeout(function () {
            scrollThreadToBottom(false, mustForce);
        }, 220);

        setTimeout(function () {
            scrollThreadToBottom(false, mustForce);
        }, 500);
    }

    window.msgChatScrollToBottom = scrollThreadToBottom;
    window.msgChatScrollToTop = scrollThreadToTop;
    window.msgChatEnsureAtBottom = ensureThreadAtBottom;
    window.msgChatEnsureAtTop = ensureThreadAtTop;
    window.msgChatIsPinnedToBottom = function () {
        return userPinnedToBottom;
    };

    function updateFooterHeightVar() {
        if (!chatFooter || !chatRoot) {
            return;
        }
        var h = Math.ceil(chatFooter.getBoundingClientRect().height);
        if (h > 0) {
            chatRoot.style.setProperty('--msg-chat-footer-h', h + 'px');
        }
    }

    function resizeComposeInput() {
        if (!composeInput) {
            return;
        }
        composeInput.style.height = 'auto';
        var next = Math.min(120, Math.max(46, composeInput.scrollHeight));
        composeInput.style.height = next + 'px';
        updateFooterHeightVar();
        if (userPinnedToBottom) {
            scrollThreadToBottom(false, true);
        }
    }

    function composeHasText() {
        return composeInput && composeInput.value.trim() !== '';
    }

    function syncSendButton() {
        if (!sendBtn) {
            return;
        }
        var ok = composeHasText();
        sendBtn.disabled = !ok;
        sendBtn.classList.toggle('is-ready', ok);
    }

    if (scrollEl) {
        scrollEl.addEventListener('scroll', function () {
            userPinnedToBottom = isNearBottom();
        }, { passive: true });

        syncMessagesLayoutVars();
        ensureThreadAtTop();

        window.addEventListener('load', function () {
            syncMessagesLayoutVars();
            ensureThreadAtTop();
        });

        if (typeof MutationObserver !== 'undefined') {
            var threadRoot = scrollEl.querySelector('.msg-chat__thread') || scrollEl;
            var mo = new MutationObserver(function () {
                if (userPinnedToBottom) {
                    scrollThreadToBottom(false, true);
                }
            });
            mo.observe(threadRoot, { childList: true, subtree: true });
        }

        scrollEl.querySelectorAll('img').forEach(function (img) {
            if (img.complete) {
                return;
            }
            img.addEventListener('load', function () {
                if (userPinnedToBottom) {
                    scrollThreadToBottom(false, true);
                }
            }, { once: true });
        });
    }

    if (composeInput && composeForm && sendBtn) {
        composeInput.addEventListener('input', function () {
            resizeComposeInput();
            syncSendButton();
        });

        composeInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (composeHasText() && !sendBtn.disabled) {
                    composeForm.requestSubmit();
                }
            }
        });

        var composeSending = false;

        function endSending() {
            composeSending = false;
            composeForm.classList.remove('is-sending');
            sendBtn.classList.remove('is-sending');
            sendBtn.removeAttribute('aria-busy');
            syncSendButton();
        }

        composeForm.addEventListener('submit', function (e) {
            var text = composeInput.value.trim();
            if (text === '') {
                e.preventDefault();
                syncSendButton();
                composeInput.focus();
                return;
            }
            userPinnedToBottom = true;
            composeInput.value = text;
            composeForm.classList.add('is-sending');
            sendBtn.disabled = true;
            sendBtn.classList.add('is-sending');
            sendBtn.setAttribute('aria-busy', 'true');

            /* Envoi sans recharger la page (mobile : clavier qui reste ouvert, pas de
               page blanche). Repli sur l'envoi classique si le temps réel est absent. */
            if (typeof window.fetch !== 'function' || typeof window.FormData !== 'function' ||
                typeof window.msgRealtimePollThreadNow !== 'function') {
                return;
            }
            e.preventDefault();
            if (composeSending) {
                return;
            }
            composeSending = true;
            var beforeId = window.msgRealtimeGetLastMessageId();
            var body = new FormData(composeForm);

            fetch(composeForm.action, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                redirect: 'follow'
            }).then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                composeInput.value = '';
                resizeComposeInput();
                return window.msgRealtimePollThreadNow().then(function () {
                    endSending();
                    /* Message refusé par le serveur (CSRF, blocage…) : on affiche
                       la page de retour qui porte le message d'erreur */
                    if (window.msgRealtimeGetLastMessageId() === beforeId && res.url) {
                        window.location.href = res.url;
                    }
                });
            }).catch(function () {
                endSending();
                composeInput.value = text;
                resizeComposeInput();
                syncSendButton();
                HTMLFormElement.prototype.submit.call(composeForm);
            });
        });

        resizeComposeInput();
        syncSendButton();

        /* Sur téléphone, ne pas ouvrir le clavier d'office : il masquerait la conversation */
        var isTouchDevice = window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches;
        if (!isTouchDevice) {
            setTimeout(function () {
                composeInput.focus();
            }, 120);
        }
    }

    if (chatFooter && typeof ResizeObserver !== 'undefined') {
        var ro = new ResizeObserver(function () {
            syncMessagesLayoutVars();
            updateFooterHeightVar();
            if (userPinnedToBottom) {
                scrollThreadToBottom(false, true);
            }
        });
        ro.observe(chatFooter);
        var navEl = document.querySelector('.nav-planete-gateau');
        if (navEl) {
            ro.observe(navEl);
        }
        var chatHeaderEl = document.querySelector('.msg-chat__header');
        if (chatHeaderEl) {
            ro.observe(chatHeaderEl);
        }
    } else {
        syncMessagesLayoutVars();
        updateFooterHeightVar();
    }

    window.addEventListener('resize', function () {
        syncMessagesLayoutVars();
        updateFooterHeightVar();
        if (userPinnedToBottom) {
            scrollThreadToBottom(false, true);
        }
    });

    var menuWrap = document.querySelector('[data-msg-chat-menu]');
    if (menuWrap) {
        var menuBtn = menuWrap.querySelector('#msgChatMenuBtn');
        var menuDropdown = menuWrap.querySelector('.msg-chat__dropdown');

        function closeChatMenu() {
            if (!menuBtn || !menuDropdown) {
                return;
            }
            menuDropdown.hidden = true;
            menuBtn.setAttribute('aria-expanded', 'false');
        }

        function openChatMenu() {
            if (!menuBtn || !menuDropdown) {
                return;
            }
            menuDropdown.hidden = false;
            menuBtn.setAttribute('aria-expanded', 'true');
        }

        if (menuBtn && menuDropdown) {
            menuBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (menuDropdown.hidden) {
                    openChatMenu();
                } else {
                    closeChatMenu();
                }
            });

            document.addEventListener('click', function (e) {
                if (!menuWrap.contains(e.target)) {
                    closeChatMenu();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeChatMenu();
                }
            });
        }
    }
})();
