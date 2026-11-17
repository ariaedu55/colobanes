/**
 * Profil client — bascule mode clair / sombre (préférence persistée)
 */
(function () {
    var STORAGE_KEY = 'colobanes-ui-theme';

    function isDark() {
        return document.documentElement.classList.contains('prf-theme-dark');
    }

    function updateToggleButton() {
        var btn = document.getElementById('prfThemeToggle');
        if (!btn) {
            return;
        }
        var dark = isDark();
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
    }

    function applyTheme(dark) {
        document.documentElement.classList.toggle('prf-theme-dark', !!dark);
        try {
            localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
        } catch (e) {
            // ignore
        }
        updateToggleButton();
    }

    window.prfApplyTheme = applyTheme;

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('prfThemeToggle');
        if (btn) {
            btn.addEventListener('click', function () {
                applyTheme(!isDark());
            });
        }
        updateToggleButton();
    });
})();
