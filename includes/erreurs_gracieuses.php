<?php
/**
 * Filet de sécurité : une erreur fatale ou une exception non attrapée n'affiche plus une page blanche.
 * - visiteur : page COLObanes « Petit souci technique » (code 500, rien de technique n'est montré) ;
 * - appel API / application (JSON) : {"success": false, "message": "..."} ;
 * - l'erreur complète reste écrite dans le journal PHP pour être corrigée.
 * Chargé par includes/session_user.php (présent sur toutes les pages).
 */

if (defined('ERREURS_GRACIEUSES_LOADED') || PHP_SAPI === 'cli') {
    return;
}
define('ERREURS_GRACIEUSES_LOADED', true);

/* Journal centralisé (Super Admin → Sécurité) */
require_once __DIR__ . '/journal_erreurs.php';

/** Requête qui attend du JSON (API, application mobile, appel AJAX). */
function erreurs_gracieuses_veut_json(): bool
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    return strpos($uri, '/api/') !== false
        || stripos($accept, 'application/json') !== false
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

/** Réponse propre (seulement si rien n'a encore été envoyé au navigateur). */
function erreurs_gracieuses_repondre(int $code = 500, string $titre = 'Petit souci technique', string $texte = 'Une erreur est survenue de notre côté. Réessayez dans un instant.'): void
{
    if (headers_sent()) {
        return;
    }
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    http_response_code($code);
    header('Cache-Control: no-store');
    if (erreurs_gracieuses_veut_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'ok' => false, 'message' => $texte], JSON_UNESCAPED_UNICODE);
        return;
    }
    $base = function_exists('get_app_base_path') ? (string) get_app_base_path() : '';
    $t = htmlspecialchars($titre, ENT_QUOTES, 'UTF-8');
    $m = htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
    $home = htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . $t . ' — COLObanes</title><meta name="robots" content="noindex">'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;box-sizing:border-box;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f4f6fa;color:#101828}'
        . '.c{max-width:420px;text-align:center;background:#fff;border:1px solid #e4e7ec;border-radius:20px;padding:32px 24px}'
        . '.l{font-size:26px;font-weight:800;color:#3564a6;margin:0 0 18px}.l span{color:#ff6b35}'
        . 'h1{font-size:20px;margin:0 0 8px}p{margin:0 0 22px;color:#475467;line-height:1.5}'
        . '.b{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}'
        . 'a,button{font:inherit;font-weight:700;font-size:14.5px;padding:12px 18px;border-radius:12px;text-decoration:none;cursor:pointer}'
        . 'button{border:0;background:#ff6b35;color:#fff}a{border:1px solid #d0d5dd;color:#101828;background:#fff}'
        . '.s{display:flex;gap:8px;margin:0 0 14px}.s input{flex:1;min-width:0;font:inherit;font-size:15px;padding:12px 14px;border:1px solid #d0d5dd;border-radius:12px}'
        . '.s button{padding:12px 14px}</style></head>'
        . '<body><main class="c"><p class="l">COLO<span>banes</span></p><h1>' . $t . '</h1><p>' . $m . '</p>'
        . ($code === 404
            ? '<form class="s" action="' . htmlspecialchars($base . '/produits.php', ENT_QUOTES, 'UTF-8') . '" method="get" role="search">'
              . '<input type="search" name="recherche" placeholder="Rechercher un produit" aria-label="Rechercher un produit">'
              . '<button type="submit">Rechercher</button></form>'
              . '<div class="b"><a href="' . $home . '">Retour à l’accueil</a></div>'
            : '<div class="b"><button type="button" onclick="location.reload()">Réessayer</button><a href="' . $home . '">Retour à l’accueil</a></div>')
        . '</main></body></html>';
}

/* Exception non attrapée (un gestionnaire déjà installé par la page reste prioritaire) */
$erreurs_gracieuses_prev = set_exception_handler(null);
if ($erreurs_gracieuses_prev !== null) {
    set_exception_handler($erreurs_gracieuses_prev);
} else {
    set_exception_handler(static function (Throwable $e): void {
        error_log('[COLObanes] Exception non attrapée : ' . get_class($e) . ' — ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if (function_exists('journal_erreurs_noter')) {
            journal_erreurs_noter('exception', get_class($e) . ' : ' . $e->getMessage(), $e->getFile(), $e->getLine());
        }
        erreurs_gracieuses_repondre();
    });
}
unset($erreurs_gracieuses_prev);

/* Erreur fatale (mémoire, fichier manquant, fonction inconnue…) */
register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e === null || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    erreurs_gracieuses_repondre();
});
