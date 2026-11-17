    </main>
</div>

<script>
    (function() {
        function closeAdminSidebar() {
            var sidebar = document.getElementById('adminSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
        window.addEventListener('resize', function() {
            if (window.innerWidth > 600) {
                closeAdminSidebar();
            }
        });
    })();
</script>
<?php
if (!empty($__vendeur_cert_niveau_hero) || !empty($__vendeur_cert_notif)) {
    require_once __DIR__ . '/../../includes/asset_version.php';
    if (!empty($__vendeur_cert_niveau_hero)) {
        echo '<link rel="stylesheet" href="/css/vendor-cert-ribbon.css' . asset_version_query() . '">' . "\n";
    }
    echo '<link rel="stylesheet" href="/css/vendeur-cert-notif.css' . asset_version_query() . '">' . "\n";
}
if (!empty($__vendeur_cert_notif) && is_array($__vendeur_cert_notif)) {
    require __DIR__ . '/../../includes/partials/vendeur_certification_notif_modal.php';
}
require __DIR__ . '/vendeur_login_modals.php';
?>
<?php
if (!isset($is_vendeur_menu) && isset($_SESSION['admin_role']) && function_exists('admin_normalize_role_for_route')) {
    $is_vendeur_menu = admin_normalize_role_for_route($_SESSION['admin_role']) === 'vendeur';
}
if (!empty($is_vendeur_menu)) {
    $enable_firebase_notifications = true;
    $firebase_notify_type = 'admin';
}
if (!empty($enable_firebase_notifications) && ($firebase_notify_type ?? '') === 'admin') {
    $__vendor_notify_page = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
    if ($__vendor_notify_page === 'dashboard.php') {
        require_once __DIR__ . '/../../includes/asset_version.php';
        echo '<link rel="stylesheet" href="/css/vendeur-notify-prompt.css' . asset_version_query() . '">' . "\n";
        require __DIR__ . '/../../includes/partials/vendeur_notify_prompt.php';
    }
}
include __DIR__ . '/../../includes/firebase_notifications_scripts.php';
?>
<?php require_once __DIR__ . '/../../includes/flash_toast.php'; flash_toast_render(); ?>
<?php
if (!function_exists('admin_csrf_emit_form_inject_script')) {
    require_once __DIR__ . '/../../includes/admin_csrf.php';
}
admin_csrf_emit_form_inject_script();

/* Dock vendeur (#adminVendeurBottomDock dans nav.php) — pas le dock client marketplace */
if (!empty($admin_page_scripts_before_close)) {
    echo $admin_page_scripts_before_close;
}
?>
</body>
</html>

