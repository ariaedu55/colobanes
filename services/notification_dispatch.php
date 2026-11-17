<?php
/**
 * Envoi unifié : push FCM + e-mail si adresse valide.
 */

require_once __DIR__ . '/firebase_push.php';
require_once __DIR__ . '/../models/model_fcm.php';

function notification_email_wrap(string $title, string $body_html, string $cta_url = '', string $cta_label = 'Voir sur COLObanes'): string
{
    $html = '<div style="font-family: \'Poppins\', Arial, sans-serif; max-width: 600px;">';
    $html .= '<h2 style="color: #3564a6;">' . htmlspecialchars($title) . '</h2>';
    $html .= $body_html;
    if ($cta_url !== '') {
        $html .= '<p style="margin-top: 25px;"><a href="' . htmlspecialchars($cta_url) . '" style="display: inline-block; padding: 12px 24px; background: #3564a6; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600;">'
            . htmlspecialchars($cta_label) . '</a></p>';
    }
    $html .= '<hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">';
    $html .= '<p style="font-size: 12px; color: #999;">COLObanes — marketplace Sénégal</p>';
    $html .= '</div>';
    return $html;
}

/**
 * @param array<string, string> $data
 * @param array<string, mixed> $email_meta
 * @return array{push: bool, email: bool}
 */
function notification_dispatch_to_user(
    int $user_id,
    string $title,
    string $body,
    array $data = [],
    string $user_email = '',
    string $email_subject = '',
    string $email_body_html = '',
    string $email_cta_url = '',
    array $email_meta = []
): array {
    $result = ['push' => false, 'email' => false];
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return $result;
    }

    $tokens = get_fcm_tokens_by_user($user_id);
    if (!empty($tokens)) {
        firebase_send_notification($tokens, $title, $body, $data);
        $result['push'] = true;
    }

    if ($user_email === '') {
        require_once __DIR__ . '/../models/model_users.php';
        $u = get_user_by_id($user_id);
        $user_email = trim((string) ($u['email'] ?? ''));
    }
    $user_email = trim($user_email);
    if ($user_email !== '' && filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        require_once __DIR__ . '/email_queue.php';
        $sujet = $email_subject !== '' ? $email_subject : '[COLObanes] ' . $title;
        $inner = $email_body_html !== '' ? $email_body_html : '<p>' . nl2br(htmlspecialchars($body)) . '</p>';
        $link = $email_cta_url;
        if ($link === '' && !empty($data['redirect_url'])) {
            $link = (string) $data['redirect_url'];
        } elseif ($link === '' && !empty($data['link'])) {
            require_once __DIR__ . '/../includes/site_url.php';
            $path = (string) $data['link'];
            $link = strpos($path, 'http') === 0 ? $path : rtrim(get_site_base_url(), '/') . (strpos($path, '/') === 0 ? $path : '/' . $path);
        }
        $html = notification_email_wrap($title, $inner, $link);
        mail_send_async($user_email, $sujet, $html, true, $email_meta);
        $result['email'] = true;
    }

    return $result;
}

/**
 * @param array<string, string> $data
 * @return array{push: bool, email: bool}
 */
function notification_dispatch_to_admin(
    int $admin_id,
    string $title,
    string $body,
    array $data = [],
    string $email_subject = '',
    string $email_body_html = '',
    string $email_cta_url = '',
    array $email_meta = []
): array {
    $result = ['push' => false, 'email' => false];
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) {
        return $result;
    }

    $tokens = get_fcm_tokens_by_admin($admin_id);
    if (!empty($tokens)) {
        firebase_send_notification($tokens, $title, $body, $data);
        $result['push'] = true;
    }

    require_once __DIR__ . '/../models/model_admin.php';
    $admin = get_admin_by_id($admin_id);
    $email = trim((string) ($admin['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        require_once __DIR__ . '/email_queue.php';
        $sujet = $email_subject !== '' ? $email_subject : '[COLObanes] ' . $title;
        $inner = $email_body_html !== '' ? $email_body_html : '<p>' . nl2br(htmlspecialchars($body)) . '</p>';
        $link = $email_cta_url;
        if ($link === '' && !empty($data['link'])) {
            $link = (string) $data['link'];
        }
        $html = notification_email_wrap($title, $inner, $link);
        mail_send_async($email, $sujet, $html, true, $email_meta);
        $result['email'] = true;
    }

    return $result;
}

/**
 * Push FCM uniquement (pas d’e-mail).
 *
 * @param array<string, string> $data
 */
function notification_dispatch_push_only_to_user(int $user_id, string $title, string $body, array $data = []): bool
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    $tokens = get_fcm_tokens_by_user($user_id);
    if ($tokens === []) {
        return false;
    }
    firebase_send_notification($tokens, $title, $body, $data);
    return true;
}

/**
 * Escalade plateforme (tous admins actifs).
 *
 * @return array{push: bool, email: bool}
 */
function notification_dispatch_to_platform_admins(
    string $title,
    string $body,
    array $data = [],
    string $email_subject = '',
    string $email_body_html = '',
    string $email_cta_url = '',
    array $email_meta = []
): array {
    $result = ['push' => false, 'email' => false];

    /* Alerte plateforme : équipe COLObanes uniquement (les jetons « admin » sont ceux des vendeurs) */
    require_once __DIR__ . '/../models/model_admin.php';
    $emails = get_platform_admin_emails();
    if (!empty($emails)) {
        require_once __DIR__ . '/email_queue.php';
        $sujet = $email_subject !== '' ? $email_subject : '[COLObanes] ' . $title;
        $inner = $email_body_html !== '' ? $email_body_html : '<p>' . nl2br(htmlspecialchars($body)) . '</p>';
        $html_base = notification_email_wrap($title, $inner, $email_cta_url);
        foreach ($emails as $email) {
            $email = trim((string) $email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            mail_send_async($email, $sujet, $html_base, true, $email_meta);
            $result['email'] = true;
        }
    }

    return $result;
}
