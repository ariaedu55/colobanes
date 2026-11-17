/**
 * Invitation téléchargement app native — mobile web uniquement.
 * Tous les navigateurs mobiles (Safari, Chrome, Edge, Firefox, Samsung Internet…) :
 * une fois par visite (sessionStorage), bouton App Store sur iOS, Google Play sur Android.
 */
(function (win, doc) {
    'use strict';

    var STORAGE_KEY = 'colobanes_asp_seen_session';
    var root = null;
    var cfg = { android: '', ios: '', delayMs: 1600 };

    function readConfig() {
        var el = doc.getElementById('aspPromptData');
        if (!el) {
            return;
        }
        try {
            var data = JSON.parse(el.textContent || '{}');
            if (data && typeof data === 'object') {
                cfg.android = String(data.android || cfg.android);
                cfg.ios = String(data.ios || cfg.ios);
                cfg.delayMs = parseInt(data.delayMs, 10) || 1600;
            }
        } catch (e) { /* ignore */ }
    }

    function isNativeApp() {
        try {
            if (doc.documentElement.classList.contains('is-native-app')) {
                return true;
            }
            if (win.__COLOBANES_NATIVE_APP === true) {
                return true;
            }
            return String(navigator.userAgent || '').indexOf('ColobanesApp') !== -1;
        } catch (e) {
            return false;
        }
    }

    function detectPlatform() {
        var ua = String(navigator.userAgent || '');
        var uaData = navigator.userAgentData || null;
        var platform = '';
        if (uaData && Array.isArray(uaData.brands)) {
            platform = String(uaData.platform || '').toLowerCase();
        }

        var isAndroid = /android/i.test(ua) || platform === 'android';
        var isIOS = /iphone|ipod|ipad/i.test(ua)
            || platform === 'ios'
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        var isSafari = false;
        if (isIOS) {
            var isCriOS = /CriOS/i.test(ua);
            var isFxiOS = /FxiOS/i.test(ua);
            var isEdgiOS = /EdgiOS/i.test(ua);
            var isOPiOS = /OPiOS|OPT\//i.test(ua);
            isSafari = /Safari/i.test(ua) && !isCriOS && !isFxiOS && !isEdgiOS && !isOPiOS;
        }

        return {
            android: isAndroid,
            ios: isIOS,
            safariIOS: isIOS && isSafari,
            mobile: isAndroid || isIOS
        };
    }

    /* Une fois par visite : réaffichée à la prochaine ouverture du navigateur / du site. */
    function isDismissed() {
        try {
            return win.sessionStorage.getItem(STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function markSeen() {
        try {
            win.sessionStorage.setItem(STORAGE_KEY, '1');
        } catch (e) { /* ignore */ }
    }

    function dismiss() {
        markSeen();
        close();
    }

    function open() {
        if (!root) {
            return;
        }
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        // force reflow for animation
        void root.offsetWidth;
        root.classList.add('is-open');
        doc.body.classList.add('asp-prompt-open');
    }

    function close() {
        if (!root) {
            return;
        }
        root.classList.remove('is-open');
        doc.body.classList.remove('asp-prompt-open');
        win.setTimeout(function () {
            if (!root.classList.contains('is-open')) {
                root.hidden = true;
                root.setAttribute('aria-hidden', 'true');
            }
        }, 280);
    }

    function setupCta(platform) {
        if (!root) {
            return;
        }
        /* Un seul bouton visible : App Store sur iPhone / iPad, Google Play sur Android. */
        root.setAttribute('data-platform', platform.android ? 'android' : 'ios');
        var iosLink = root.querySelector('.asp-prompt__store--ios');
        var androidLink = root.querySelector('.asp-prompt__store--android');
        if (iosLink && cfg.ios) {
            iosLink.href = cfg.ios;
        }
        if (androidLink && cfg.android) {
            androidLink.href = cfg.android;
        }
    }

    function shouldShow(platform) {
        if (isNativeApp()) {
            return false;
        }
        if (!platform.mobile) {
            return false;
        }
        if (isDismissed()) {
            return false;
        }
        if (platform.android || platform.ios) {
            return true;
        }
        return false;
    }

    function bind() {
        root = doc.getElementById('aspPrompt');
        if (!root) {
            return;
        }

        readConfig();
        var platform = detectPlatform();
        if (!shouldShow(platform)) {
            return;
        }

        setupCta(platform);

        var later = doc.getElementById('aspPromptLater');
        var closeBtn = doc.getElementById('aspPromptClose');
        var backdrop = doc.getElementById('aspPromptBackdrop');
        [later, closeBtn].forEach(function (btn) {
            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    dismiss();
                });
            }
        });
        if (backdrop) {
            backdrop.addEventListener('click', function () {
                dismiss();
            });
        }
        root.querySelectorAll('.js-asp-store').forEach(function (link) {
            link.addEventListener('click', function () {
                markSeen();
                close();
            });
        });
        doc.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && root && !root.hidden) {
                dismiss();
            }
        });

        win.setTimeout(function () {
            if (isDismissed()) {
                return;
            }
            markSeen();
            open();
        }, Math.max(400, cfg.delayMs));
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})(window, document);
