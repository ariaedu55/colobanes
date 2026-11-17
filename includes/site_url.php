<?php
/**
 * Retourne l'URL de base du site pour les liens (emails, notifications, etc.)
 * Priorité : config/site.php > config/email.php > déduction depuis $_SERVER
 * 
 * @return string URL sans slash final (ex: https://colobanes.com)
 */
function normalize_colobanes_site_base_url($url) {
    $url = rtrim(trim((string) $url), '/');
    if ($url === '') {
        return '';
    }
    return preg_replace('#^https?://www\.colobanes\.com#i', 'https://colobanes.com', $url);
}

function get_site_base_url() {
    if (!function_exists('get_app_base_path')) {
        require_once __DIR__ . '/app_base.php';
    }

    $site_url = '';

    if (file_exists(__DIR__ . '/../config/site.php')) {
        $config = require __DIR__ . '/../config/site.php';
        $site_url = $config['site_url'] ?? '';
    }

    if (empty($site_url) && file_exists(__DIR__ . '/../config/email.php')) {
        $config = require __DIR__ . '/../config/email.php';
        $site_url = $config['site_url'] ?? '';
    }

    if (!empty($site_url)) {
        return normalize_colobanes_site_base_url($site_url);
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if ($host === 'www.colobanes.com') {
        $host = 'colobanes.com';
    }
    /* L'en-tête Host est contrôlé par le client : sans site_url configuré, n'accepter que les hôtes connus
       (sinon un lien de réinitialisation de mot de passe pourrait pointer vers un domaine tiers). */
    $host_name = preg_replace('/:\d+$/', '', $host);
    $host_ok = in_array($host_name, ['colobanes.com', 'localhost', '127.0.0.1', '[::1]'], true)
        || preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.(localhost|test)$/', $host_name);
    if (!$host_ok) {
        error_log('[site_url] Hôte non reconnu ignoré : ' . substr($host, 0, 100) . ' — définissez site_url dans config/site.php');
        $protocol = 'https';
        $host = 'colobanes.com';
    }
    $url = normalize_colobanes_site_base_url($protocol . '://' . $host);
    $base_path = get_app_base_path();
    if ($base_path !== '') {
        $url .= $base_path;
    }

    return $url;
}
