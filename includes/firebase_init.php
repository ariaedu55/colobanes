<?php
/**
 * Configuration Firebase - Source unique pour toutes les pages
 * En cas d'erreur "API key not valid", voir FIX_API_KEY_NOTIFICATIONS.md
 */
$config_path = __DIR__ . '/../config/firebase_config.php';

if (!is_file($config_path)) {
    echo '<script>window.FIREBASE_CONFIG = null; window.FIREBASE_VAPID_KEY = "";</script>';
    return;
}

$firebase_config = require $config_path;

if (!function_exists('app_url')) {
    require_once __DIR__ . '/app_base.php';
}

$fcm_sw_path = app_url('/firebase-messaging-sw.js');
?>
<script>
    window.FIREBASE_CONFIG = <?php echo json_encode([
        'apiKey' => $firebase_config['apiKey'] ?? '',
        'authDomain' => $firebase_config['authDomain'] ?? '',
        'projectId' => $firebase_config['projectId'] ?? '',
        'storageBucket' => $firebase_config['storageBucket'] ?? '',
        'messagingSenderId' => $firebase_config['messagingSenderId'] ?? '',
        'appId' => $firebase_config['appId'] ?? '',
        'measurementId' => $firebase_config['measurementId'] ?? null,
    ]); ?>;
    window.FIREBASE_VAPID_KEY = <?php echo json_encode(trim($firebase_config['vapidKey'] ?? '')); ?>;
    window.FCM_SW_PATH = <?php echo json_encode($fcm_sw_path); ?>;
</script>
