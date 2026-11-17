    </main>
</div>

<script>
    (function() {
        function closeSuperAdminSidebar() {
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
                closeSuperAdminSidebar();
            }
        });
    })();
</script>
<?php require_once __DIR__ . '/../../includes/flash_toast.php'; flash_toast_render(); ?>
<?php
if (!function_exists('super_admin_csrf_emit_form_inject_script')) {
    require_once __DIR__ . '/../../includes/super_admin_csrf.php';
}
super_admin_csrf_emit_form_inject_script();
?>
</body>
</html>
