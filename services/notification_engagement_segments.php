<?php
/**
 * Copies de notifications engagement — un même texte par segment (rayon / centre d’intérêt).
 */

require_once __DIR__ . '/../models/model_notification_log.php';
require_once __DIR__ . '/../models/model_client_comportement.php';
require_once __DIR__ . '/../includes/site_brand.php';
require_once __DIR__ . '/../includes/marketplace_helpers.php';

function notification_engagement_brand_label(): string
{
    return defined('SITE_BRAND_NAME') ? (string) SITE_BRAND_NAME : 'COLObanes';
}

function notification_engagement_rayon_nom(int $gid): string
{
    $gid = (int) $gid;
    if ($gid <= 0) {
        return 'nos rayons';
    }
    if (!function_exists('get_categorie_generale_by_id')) {
        require_once __DIR__ . '/../models/model_categories.php';
    }
    $row = get_categorie_generale_by_id($gid);
    $nom = trim((string) ($row['nom'] ?? ''));
    return $nom !== '' ? $nom : 'votre rayon';
}

function notification_engagement_link_rayon(int $gid): string
{
    $gid = (int) $gid;
    if ($gid <= 0) {
        return '/nouveautes.php';
    }
    return nav_categorie_generale_href($gid);
}

/**
 * @return array{title: string, body: string, email_html: string, email_subject: string}
 */
function notification_engagement_copy_nouveau_produit(int $gid, string $nom_produit): array
{
    $brand = notification_engagement_brand_label();
    $nom_produit = trim($nom_produit);
    $rayon = notification_engagement_rayon_nom($gid);

    if ($gid > 0) {
        $title = $brand . ' — Ne ratez pas les nouveautés ' . $rayon;
        if ($nom_produit !== '') {
            $body = '« ' . $nom_produit . ' » est en ligne. Découvrez les dernières arrivées dans votre rayon.';
            $email_html = '<p>Ne ratez pas les nouveautés en <strong>' . htmlspecialchars($rayon) . '</strong> : '
                . '<strong>' . htmlspecialchars($nom_produit) . '</strong> est disponible sur ' . htmlspecialchars($brand) . '.</p>';
        } else {
            $body = 'De nouveaux articles sont en ligne dans votre rayon. Venez les découvrir.';
            $email_html = '<p>De nouveaux produits viennent d’être ajoutés en <strong>' . htmlspecialchars($rayon) . '</strong>.</p>';
        }
    } else {
        $title = $brand . ' — Ne ratez pas les nouveautés';
        if ($nom_produit !== '') {
            $body = '« ' . $nom_produit . ' » est en ligne sur ' . $brand . '.';
            $email_html = '<p><strong>' . htmlspecialchars($nom_produit) . '</strong> vient d’être mis en ligne.</p>';
        } else {
            $body = 'De nouveaux produits vous attendent sur ' . $brand . '.';
            $email_html = '<p>Découvrez les dernières arrivées sur la marketplace.</p>';
        }
    }

    return [
        'title' => $title,
        'body' => $body,
        'email_html' => $email_html,
        'email_subject' => '[COLObanes] ' . $title,
    ];
}

/**
 * @return array{title: string, body: string, email_html: string, email_subject: string}
 */
function notification_engagement_copy_inactif(int $gid): array
{
    $brand = notification_engagement_brand_label();
    $rayon = notification_engagement_rayon_nom($gid);

    if ($gid > 0) {
        $title = $brand . ' — De nouveautés en ' . $rayon . ' vous attendent';
        $body = 'Vous ne nous avez pas vu depuis quelques jours — repassez voir les nouveautés dans votre rayon.';
        $email_html = '<p>De nouveaux articles sont disponibles en <strong>' . htmlspecialchars($rayon) . '</strong>. '
            . 'Revenez sur ' . htmlspecialchars($brand) . ' pour ne rien manquer.</p>';
    } else {
        $title = $brand . ' — De nouveautés vous attendent';
        $body = 'Vous ne nous avez pas vu depuis quelques jours — découvrez les dernières arrivées sur la marketplace.';
        $email_html = '<p>Les vendeurs ont ajouté de nouveaux produits. Repassez sur ' . htmlspecialchars($brand) . ' quand vous voulez.</p>';
    }

    return [
        'title' => $title,
        'body' => $body,
        'email_html' => $email_html,
        'email_subject' => '[COLObanes] ' . $title,
    ];
}

function notification_engagement_primary_gid_for_user(int $user_id): int
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return 0;
    }
    $cfg = notifications_config();
    $min = (float) ($cfg['engagement_interet_score_min'] ?? 0);
    $tops = client_comportement_top_rayons($user_id, null, 1);
    if ($tops === []) {
        return 0;
    }
    $gid = (int) ($tops[0]['categorie_generale_id'] ?? 0);
    $score = (float) ($tops[0]['score'] ?? 0);
    if ($gid <= 0 || $score < $min) {
        return 0;
    }
    return $gid;
}
