/**
 * Thème global COLObanes — clair / sombre (localStorage + compte)
 */
(function () {
    var STORAGE_KEY = 'colobanes-ui-theme';
    var ROOT_CLASS = 'theme-dark';
    var API_URL = (typeof window.COLOBANES_THEME_API === 'string' && window.COLOBANES_THEME_API)
        ? window.COLOBANES_THEME_API
        : '/api/user_ui_theme.php';

    function isDark() {
        return document.documentElement.classList.contains(ROOT_CLASS);
    }

    function updateMetaThemeColor() {
        var meta = document.querySelector('meta[name="theme-color"]');
        if (!meta) {
            return;
        }
        meta.setAttribute('content', isDark() ? '#0e1218' : '#3564a6');
    }

    function updateToggleButtons() {
        var dark = isDark();
        document.querySelectorAll('[data-theme-toggle], #prfThemeToggle').forEach(function (btn) {
            btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
            btn.setAttribute(
                'aria-label',
                dark ? 'Activer le mode clair' : 'Activer le mode sombre'
            );
            btn.title = dark ? 'Mode clair' : 'Mode sombre';
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
            }
        });
    }

    function syncThemeToAccount(theme) {
        if (typeof FormData === 'undefined' || typeof fetch !== 'function') {
            return;
        }
        var body = new FormData();
        body.append('theme', theme);
        fetch(API_URL, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).catch(function () {
            // ignore
        });
    }

    function applyTheme(dark, persist) {
        var theme = dark ? 'dark' : 'light';
        document.documentElement.classList.toggle(ROOT_CLASS, dark);
        document.documentElement.classList.remove('prf-theme-dark');
        document.documentElement.setAttribute('data-theme', theme);

        if (persist !== false) {
            try {
                localStorage.setItem(STORAGE_KEY, theme);
            } catch (e) {
                // ignore
            }
            syncThemeToAccount(theme);
        }

        updateToggleButtons();
        updateMetaThemeColor();
    }

    function toggleTheme() {
        applyTheme(!isDark(), true);
    }

    window.colobanesApplyTheme = applyTheme;
    window.colobanesToggleTheme = toggleTheme;
    window.colobanesIsDarkTheme = isDark;
    window.prfApplyTheme = applyTheme;

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-theme-toggle], #prfThemeToggle').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                toggleTheme();
            });
        });
        updateToggleButtons();
        updateMetaThemeColor();
    });
})();
