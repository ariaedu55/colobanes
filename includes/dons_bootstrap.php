<?php
/**
 * Chargement commun des pages du module Dons (/dons/*).
 */
require_once __DIR__ . '/session_user.php';
session_start_persistent();

require_once __DIR__ . '/../models/model_dons.php';
require_once __DIR__ . '/site_csrf.php';
require_once __DIR__ . '/image_optimizer.php';
require_once __DIR__ . '/senegal_regions.php';
require_once __DIR__ . '/asset_version.php';
require_once __DIR__ . '/site_brand.php';
require_once __DIR__ . '/auth_redirect.php';
require_once __DIR__ . '/app_base.php';

dons_ready();

$dons_uid = (int) ($_SESSION['user_id'] ?? 0);

if (!function_exists('dons_require_login')) {
    function dons_require_login(): int
    {
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) {
            header('Location: ' . auth_choix_connexion_url((string) ($_SERVER['REQUEST_URI'] ?? app_url('/dons/'))));
            exit;
        }
        return $uid;
    }

    /** Redirection interne au module (chemin /dons/…, préfixé du base path). */
    function dons_redirect(string $path): void
    {
        header('Location: ' . app_url($path));
        exit;
    }

    function dons_flash(string $type, string $message): void
    {
        $_SESSION['dons_flash'] = ['type' => $type, 'message' => $message];
    }

    function dons_flash_take(): ?array
    {
        $f = $_SESSION['dons_flash'] ?? null;
        unset($_SESSION['dons_flash']);
        return is_array($f) ? $f : null;
    }

    function dons_e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    function dons_img(?string $chemin, string $variant = 'md'): string
    {
        return $chemin ? upload_image_url((string) $chemin, $variant) : upload_image_fallback_url();
    }

    function dons_lieu(array $d): string
    {
        $r = senegal_region_label((string) ($d['region'] ?? ''));
        $z = trim((string) ($d['zone'] ?? ''));
        return $z !== '' ? $z . ', ' . $r : $r;
    }

    /** Pastille de statut public (« Demande reçue » si des demandes attendent). */
    function dons_statut_pill(array $d): string
    {
        $s = (string) $d['statut'];
        $label = dons_statut_label($s);
        $mod = $s;
        if ($s === 'disponible' && (int) ($d['nb_demandes'] ?? 0) > 0) {
            $label = 'Demande reçue';
            $mod = 'demande';
        }
        return '<span class="dn-pill dn-pill--' . dons_e($mod) . '">' . dons_e($label) . '</span>';
    }
}
