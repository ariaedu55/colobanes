<?php
/**
 * Pages d'erreur du site (403, 404, 500, 503) — appelées par Apache via ErrorDocument (.htaccess).
 * Même habillage COLObanes que les erreurs PHP ; une 404 propose une recherche de produit.
 */
require_once __DIR__ . '/includes/app_base.php';
require_once __DIR__ . '/includes/erreurs_gracieuses.php';

$code = (int) ($_GET['code'] ?? ($_SERVER['REDIRECT_STATUS'] ?? 404));
$pages = [
    400 => ['Requête invalide', 'Cette adresse n’est pas valide. Revenez à l’accueil pour continuer vos achats.'],
    403 => ['Accès non autorisé', 'Cette page n’est pas accessible. Si vous pensez que c’est une erreur, revenez à l’accueil.'],
    404 => ['Page introuvable', 'Cette page n’existe pas ou a été déplacée. Cherchez un produit ou revenez à l’accueil.'],
    500 => ['Petit souci technique', 'Une erreur est survenue de notre côté. Réessayez dans un instant.'],
    503 => ['Maintenance en cours', 'COLObanes revient dans quelques minutes, merci de votre patience.'],
];
if (!isset($pages[$code])) {
    $code = 404;
}
if ($code === 503 && !headers_sent()) {
    header('Retry-After: 1800');
}
erreurs_gracieuses_repondre($code, $pages[$code][0], $pages[$code][1]);
