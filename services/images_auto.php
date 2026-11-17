<?php
/**
 * Allègement automatique des images — aucune commande à lancer, fonctionne aussi après une mise à jour du VPS.
 *
 * Pour chaque image de upload/ (produits, logos de boutiques, catégories, dons, bannières) :
 *   - crée les versions légères manquantes : _md.webp (800 px) et _sm.webp (400 px) ;
 *   - pour un ancien JPG / PNG / GIF : crée aussi la version .webp pleine taille (1920 px max).
 * Rien n'est supprimé ni renommé : les chemins en base restent valables, le site sert la version légère.
 * Une version est refaite si l'image d'origine a été remplacée depuis.
 *
 * Lancé par services/taches_auto.php : toutes les 15 min (par lots) et juste après chaque ajout de produit.
 */

require_once dirname(__DIR__) . '/includes/image_optimizer.php';

if (!defined('IMAGES_AUTO_DOSSIERS')) {
    /* Pas « certifications » (pièces d'identité, servies par PHP) ni les codes-barres / QR générés */
    define('IMAGES_AUTO_DOSSIERS', ['produits', 'boutique_branding', 'categories', 'dons', 'marketplace_hero', 'slider']);
}

/** Variantes à produire pour une image : [chemin cible => largeur max]. */
function images_auto_cibles(string $fichier): array
{
    $dir = dirname($fichier);
    $stem = pathinfo($fichier, PATHINFO_FILENAME);
    $ext = strtolower(pathinfo($fichier, PATHINFO_EXTENSION));
    $cibles = [
        $dir . '/' . $stem . '_md.webp' => IMAGE_OPTIMIZER_MD_WIDTH,
        $dir . '/' . $stem . '_sm.webp' => IMAGE_OPTIMIZER_SM_WIDTH,
    ];
    if ($ext !== 'webp') {
        $cibles[$dir . '/' . $stem . '.webp'] = IMAGE_OPTIMIZER_MAX_WIDTH;
    }
    return $cibles;
}

/** Image d'origine (pas une variante _md / _sm) dans un format traitable. */
function images_auto_est_origine(string $fichier): bool
{
    $ext = strtolower(pathinfo($fichier, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return false;
    }
    return !preg_match('/_(md|sm)$/', pathinfo($fichier, PATHINFO_FILENAME));
}

/**
 * Images que PHP ne sait pas lire (animées, abîmées) : mises de côté jusqu'à leur remplacement,
 * pour ne pas les retenter à chaque passage.
 */
function images_auto_illisible(string $fichier, bool $marquer = false): bool
{
    static $liste = null;
    $f = dirname(__DIR__) . '/storage/taches_auto/images_illisibles.json';
    if ($liste === null) {
        $liste = is_file($f) ? (json_decode((string) @file_get_contents($f), true) ?: []) : [];
    }
    $cle = (realpath($fichier) ?: $fichier) . '|' . (int) @filemtime($fichier);
    if ($marquer) {
        $liste[$cle] = date('c');
        @file_put_contents($f, json_encode($liste, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    return isset($liste[$cle]);
}

/** Variantes manquantes ou plus anciennes que l'original. */
function images_auto_a_faire(string $fichier): array
{
    if (images_auto_illisible($fichier)) {
        return [];
    }
    $mtime = (int) @filemtime($fichier);
    $manque = [];
    foreach (images_auto_cibles($fichier) as $cible => $largeur) {
        /* Une version .webp « sœur » d'un JPG/PNG existe déjà (convertie autrefois) : on la garde */
        if (!is_file($cible) || ((int) @filemtime($cible) < $mtime && !str_ends_with($cible, '/' . pathinfo($fichier, PATHINFO_FILENAME) . '.webp'))) {
            $manque[$cible] = $largeur;
        }
    }
    return $manque;
}

/** Crée les versions légères d'une image. Retourne le nombre de fichiers créés. */
function images_auto_traiter_fichier(string $fichier): int
{
    $manque = images_auto_a_faire($fichier);
    if (!$manque) {
        return 0;
    }
    $src = image_optimizer_load($fichier);
    if ($src === false) {
        images_auto_illisible($fichier, true);
        return 0;
    }
    $faits = 0;
    foreach ($manque as $cible => $largeur) {
        $img = image_optimizer_resize($src, (int) $largeur);
        if ($img === false) {
            continue;
        }
        $tmp = $cible . '.' . getmypid() . '.tmp';
        if (image_optimizer_save_webp($img, $tmp) && @filesize($tmp) > 0 && @rename($tmp, $cible)) {
            @chmod($cible, 0644);
            $faits++;
        } else {
            @unlink($tmp);
        }
    }
    return $faits;
}

/**
 * Passe sur les images et allège celles qui en ont besoin, dans la limite d'un budget de temps.
 *
 * @param list<string>|null $fichiers chemins absolus à traiter en priorité (ex. photos du produit qui vient d'être ajouté)
 * @return array{ok:bool, images:int, fichiers_crees:int, restantes:int, message:string}
 */
function images_auto_run(float $budget_secondes = 20.0, ?array $fichiers = null): array
{
    if (!image_optimizer_webp_available()) {
        return ['ok' => true, 'images' => 0, 'fichiers_crees' => 0, 'restantes' => 0,
            'message' => 'ignoré : PHP de ce serveur sans WebP (GD)'];
    }
    @set_time_limit(max(30, (int) $budget_secondes + 20));
    @ini_set('memory_limit', '512M');
    $debut = microtime(true);
    $racine = dirname(__DIR__) . '/upload';
    $images = 0;
    $crees = 0;
    $restantes = 0;

    $liste = [];
    if ($fichiers !== null) {
        foreach ($fichiers as $f) {
            if (is_file($f) && images_auto_est_origine($f)) {
                $liste[] = $f;
            }
        }
    } else {
        foreach (IMAGES_AUTO_DOSSIERS as $dossier) {
            $d = $racine . '/' . $dossier;
            if (!is_dir($d)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile() && images_auto_est_origine($f->getPathname())) {
                    $liste[] = $f->getPathname();
                }
            }
        }
        /* Les plus récentes d'abord : les nouveaux produits sont allégés en premier */
        usort($liste, static fn ($a, $b) => (int) @filemtime($b) <=> (int) @filemtime($a));
    }

    foreach ($liste as $f) {
        if (!images_auto_a_faire($f)) {
            continue;
        }
        if (microtime(true) - $debut > $budget_secondes) {
            $restantes++;
            continue;
        }
        $n = images_auto_traiter_fichier($f);
        if ($n > 0) {
            $images++;
            $crees += $n;
        }
    }

    return [
        'ok' => true,
        'images' => $images,
        'fichiers_crees' => $crees,
        'restantes' => $restantes,
        'message' => $images . ' image(s) allégée(s)' . ($restantes ? ', ' . $restantes . ' au prochain passage' : ''),
    ];
}
