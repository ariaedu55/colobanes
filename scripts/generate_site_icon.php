<?php
/**
 * Génère l'icône de site (onglet navigateur, résultats Google, favoris)
 * à partir de image/logo-icone-colobanes.png.
 *
 * Usage : php scripts/generate_site_icon.php
 */
require_once __DIR__ . '/../includes/require_cli_only.php';

$racine = dirname(__DIR__);
$source = $racine . '/image/logo-icone-colobanes.png';
$dossier_sortie = $racine . '/site-icons';

// Google exige une icône carrée, multiple de 48 px ; 16 et 32 servent aux onglets,
// 180 à l'apple-touch-icon (Safari, et candidat favicon côté Google).
$tailles = [16, 32, 48, 96, 144, 180, 192, 256, 512];
$tailles_ico = [16, 32, 48];

// 0 = la goutte remplit tout le carré (icône la plus grande possible sans rognage).
$marge_relative = 0.0;

if (!is_file($source)) {
    fwrite(STDERR, "Source introuvable : $source\n");
    exit(1);
}

$image_source = imagecreatefrompng($source);
if (!$image_source) {
    fwrite(STDERR, "Lecture PNG impossible.\n");
    exit(1);
}

/**
 * Détecte la zone utile (hors marges blanches) pour que l'icône remplisse le carré.
 *
 * @return array{0:int,1:int,2:int,3:int} x, y, largeur, hauteur
 */
function site_icon_zone_utile($image, int $seuil = 244): array
{
    $largeur = imagesx($image);
    $hauteur = imagesy($image);
    $min_x = $largeur;
    $min_y = $hauteur;
    $max_x = -1;
    $max_y = -1;

    for ($y = 0; $y < $hauteur; $y++) {
        for ($x = 0; $x < $largeur; $x++) {
            $couleur = imagecolorat($image, $x, $y);
            $alpha = ($couleur >> 24) & 0x7F;
            if ($alpha > 100) {
                continue;
            }
            $r = ($couleur >> 16) & 0xFF;
            $v = ($couleur >> 8) & 0xFF;
            $b = $couleur & 0xFF;
            if ($r >= $seuil && $v >= $seuil && $b >= $seuil) {
                continue;
            }
            if ($x < $min_x) { $min_x = $x; }
            if ($y < $min_y) { $min_y = $y; }
            if ($x > $max_x) { $max_x = $x; }
            if ($y > $max_y) { $max_y = $y; }
        }
    }

    if ($max_x < 0 || $max_y < 0) {
        return [0, 0, $largeur, $hauteur];
    }

    return [$min_x, $min_y, $max_x - $min_x + 1, $max_y - $min_y + 1];
}

list($zone_x, $zone_y, $zone_w, $zone_h) = site_icon_zone_utile($image_source);
printf(
    "Source %dx%d — zone utile %dx%d à partir de (%d, %d)\n",
    imagesx($image_source),
    imagesy($image_source),
    $zone_w,
    $zone_h,
    $zone_x,
    $zone_y
);

$fichiers_png = [];

foreach ($tailles as $taille) {
    $canvas = imagecreatetruecolor($taille, $taille);
    $blanc = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $taille, $taille, $blanc);

    $marge = (int) round($taille * $marge_relative);
    $zone_dispo = max(1, $taille - ($marge * 2));
    $ratio = min($zone_dispo / $zone_w, $zone_dispo / $zone_h);
    $dest_w = max(1, (int) round($zone_w * $ratio));
    $dest_h = max(1, (int) round($zone_h * $ratio));
    $dest_x = (int) round(($taille - $dest_w) / 2);
    $dest_y = (int) round(($taille - $dest_h) / 2);

    imagecopyresampled(
        $canvas,
        $image_source,
        $dest_x,
        $dest_y,
        $zone_x,
        $zone_y,
        $dest_w,
        $dest_h,
        $zone_w,
        $zone_h
    );

    $chemin = $dossier_sortie . '/site-icon-' . $taille . '.png';
    imagepng($canvas, $chemin, 9);
    $fichiers_png[$taille] = $chemin;
    echo "Écrit : " . basename($chemin) . " ({$taille}x{$taille})\n";
}

/**
 * Écrit un .ico multi-tailles contenant des PNG (supporté par tous les navigateurs actuels).
 */
function site_icon_ecrire_ico(array $pngs, string $destination): bool
{
    $entrees = [];
    foreach ($pngs as $taille => $chemin) {
        $donnees = file_get_contents($chemin);
        if ($donnees === false) {
            return false;
        }
        $entrees[] = ['taille' => (int) $taille, 'donnees' => $donnees];
    }

    $nb = count($entrees);
    if ($nb === 0) {
        return false;
    }

    $offset = 6 + (16 * $nb);
    $repertoire = pack('vvv', 0, 1, $nb);
    $corps = '';

    foreach ($entrees as $entree) {
        $octets = strlen($entree['donnees']);
        $dimension = $entree['taille'] >= 256 ? 0 : $entree['taille'];
        $repertoire .= pack(
            'CCCCvvVV',
            $dimension,
            $dimension,
            0,
            0,
            1,
            32,
            $octets,
            $offset
        );
        $corps .= $entree['donnees'];
        $offset += $octets;
    }

    return file_put_contents($destination, $repertoire . $corps) !== false;
}

$pngs_ico = [];
foreach ($tailles_ico as $taille) {
    if (isset($fichiers_png[$taille])) {
        $pngs_ico[$taille] = $fichiers_png[$taille];
    }
}

$chemin_ico = $racine . '/favicon.ico';
if (site_icon_ecrire_ico($pngs_ico, $chemin_ico)) {
    echo 'Écrit : favicon.ico (' . implode(', ', array_keys($pngs_ico)) . ")\n";
} else {
    fwrite(STDERR, "Échec écriture favicon.ico\n");
    exit(1);
}

echo "Terminé.\n";
