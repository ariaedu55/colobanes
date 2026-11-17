    </main>
</div>

<?php include __DIR__ . '/user_rating_popup.php'; ?>

<?php include __DIR__ . '/../../includes/social_floating.php'; ?>
<?php
if (session_status() === PHP_SESSION_ACTIVE
    && !empty($_SESSION['user_id'])
    && empty($_SESSION['admin_id'])
    && empty($enable_firebase_notifications)) {
    $enable_firebase_notifications = true;
    $firebase_notify_type = 'user';
}
if (!empty($enable_firebase_notifications)) {
    include __DIR__ . '/../../includes/firebase_notifications_scripts.php';
}
?>
<?php require_once __DIR__ . '/../../includes/flash_toast.php'; flash_toast_render(); ?>
</body>
</html>

