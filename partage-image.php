<?php
/**
 * Image d'aperçu d'un produit partagé (lue par WhatsApp, Facebook, etc.) — JPEG 1200 × 630.
 * GET ?id=<produit>&v=<version> ; l'image est fabriquée une fois puis servie depuis le cache.
 */
declare(strict_types=1);

require_once __DIR__ . '/conn/conn.php';
require_once __DIR__ . '/includes/partage_image.php';

$id = (int) ($_GET['id'] ?? 0);
$produit = null;
$boutique = '';
if ($id > 0) {
    try {
        $st = $db->prepare("
            SELECT p.id, p.nom, p.prix, p.prix_promotion, p.image_principale,
                   COALESCE(NULLIF(TRIM(a.boutique_nom), ''), TRIM(CONCAT(a.prenom, ' ', a.nom))) AS boutique
            FROM produits p
            LEFT JOIN admin a ON a.id = p.admin_id
            WHERE p.id = ? AND p.statut IN ('actif', 'rupture_stock')
            LIMIT 1
        ");
        $st->execute([$id]);
        $produit = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        $boutique = trim((string) ($produit['boutique'] ?? ''));
    } catch (PDOException $e) {
        error_log('[partage-image] ' . $e->getMessage());
    }
}

/* Produit introuvable : logo de la plateforme */
if (!$produit) {
    header('Location: ' . (function_exists('app_url') ? app_url('/image/logo_market.png') : '/image/logo_market.png'), true, 302);
    exit;
}

$version = partage_image_version($produit, $boutique);
$fichier = __DIR__ . '/storage/partage/produit-' . $id . '-' . $version . '.jpg';
if (!is_file($fichier)) {
    /* Anciennes versions de ce produit : supprimées */
    foreach (glob(__DIR__ . '/storage/partage/produit-' . $id . '-*.jpg') ?: [] as $ancien) {
        @unlink($ancien);
    }
    $fichier = partage_image_generer($produit, $boutique, $fichier);
}

if ($fichier === '' || !is_file($fichier)) {
    /* Serveur sans GD : photo d'origine */
    $img = trim(str_replace('\\', '/', (string) ($produit['image_principale'] ?? '')), '/');
    require_once __DIR__ . '/includes/site_url.php';
    header('Location: ' . get_site_base_url() . ($img !== '' ? '/upload/' . $img : '/image/logo_market.png'), true, 302);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($fichier));
header('Cache-Control: public, max-age=604800');
header('X-Content-Type-Options: nosniff');
readfile($fichier);
