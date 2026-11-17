<?php
/**
 * Sert une image de certification uniquement aux sessions autorisées.
 * Invisible / inaccessible via /upload/certifications/…
 */
require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../includes/certification_media.php';

$raw = isset($_GET['p']) ? (string) $_GET['p'] : '';
$path = certification_media_normalize_path($raw);
if ($path === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Introuvable');
}

if (!certification_media_can_access($path)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit('Accès refusé');
}

$file = certification_media_absolute_file($path);
if ($file === false) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Introuvable');
}

$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detected = finfo_file($finfo, $file);
        finfo_close($finfo);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
}
$allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Type non autorisé');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($file));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Disposition: inline; filename="' . basename($file) . '"');

readfile($file);
exit;
