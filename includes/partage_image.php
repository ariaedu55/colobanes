<?php
/**
 * Image d'aperçu des liens partagés (WhatsApp, Facebook, Instagram, TikTok…) — 1200 × 630.
 * Photo du produit à gauche ; logo COLObanes, nom, prix, réduction et boutique à droite.
 * Générée une seule fois par version du produit puis servie depuis storage/partage/.
 */

if (!function_exists('partage_image_version')) {
    /** Change dès que le nom, le prix, la promo, la photo ou la boutique changent → nouvelle image. */
    function partage_image_version(array $p, string $boutique = ''): string
    {
        return substr(sha1(implode('|', [
            (string) ($p['nom'] ?? ''), (string) ($p['prix'] ?? ''), (string) ($p['prix_promotion'] ?? ''),
            (string) ($p['image_principale'] ?? ''), $boutique, 'v2',
        ])), 0, 12);
    }

    function partage_image_url(array $p, string $boutique = ''): string
    {
        if (!function_exists('get_site_base_url')) {
            require_once __DIR__ . '/site_url.php';
        }
        return get_site_base_url() . '/partage-image.php?id=' . (int) ($p['id'] ?? 0) . '&v=' . partage_image_version($p, $boutique);
    }

    /** Ouvre une photo (webp, jpg, png) ; null si le serveur ne sait pas la lire. */
    function partage_image_ouvrir(string $fichier)
    {
        if ($fichier === '' || !is_file($fichier)) {
            return null;
        }
        $info = @getimagesize($fichier);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            return @imagecreatefromwebp($fichier) ?: null;
        }
        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
            return @imagecreatefromjpeg($fichier) ?: null;
        }
        if ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
            return @imagecreatefrompng($fichier) ?: null;
        }
        return null;
    }

    /** Coupe un texte en lignes qui tiennent dans $largeur (points ajoutés si trop long). */
    function partage_image_lignes(string $texte, string $police, float $taille, int $largeur, int $max_lignes): array
    {
        $mots = preg_split('/\s+/u', trim($texte)) ?: [];
        $lignes = [];
        $ligne = '';
        foreach ($mots as $mot) {
            $essai = $ligne === '' ? $mot : $ligne . ' ' . $mot;
            $b = imagettfbbox($taille, 0, $police, $essai);
            if ($b[2] - $b[0] <= $largeur || $ligne === '') {
                $ligne = $essai;
            } else {
                $lignes[] = $ligne;
                $ligne = $mot;
            }
        }
        if ($ligne !== '') {
            $lignes[] = $ligne;
        }
        if (count($lignes) > $max_lignes) {
            $lignes = array_slice($lignes, 0, $max_lignes);
            $last = $lignes[$max_lignes - 1];
            while ($last !== '' && (imagettfbbox($taille, 0, $police, $last . '…')[2] > $largeur)) {
                $last = mb_substr($last, 0, -1);
            }
            $lignes[$max_lignes - 1] = rtrim($last) . '…';
        }
        return $lignes;
    }

    function partage_image_texte($img, float $taille, int $x, int $y, int $couleur, string $police, string $texte): int
    {
        imagettftext($img, $taille, 0, $x, $y, $couleur, $police, $texte);
        $b = imagettfbbox($taille, 0, $police, $texte);
        return $x + ($b[2] - $b[0]);
    }

    function partage_image_rect_arrondi($img, int $x1, int $y1, int $x2, int $y2, int $r, int $couleur): void
    {
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $couleur);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $couleur);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $couleur);
        }
    }

    /**
     * Fabrique l'image JPEG et renvoie son chemin (ou '' si GD est absent).
     */
    function partage_image_generer(array $p, string $boutique, string $dest): string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) {
            return '';
        }
        $root = dirname(__DIR__);
        $fBold = $root . '/fonts/poppins/ttf/Poppins-Bold.ttf';
        $fSemi = $root . '/fonts/poppins/ttf/Poppins-SemiBold.ttf';
        $fMed = $root . '/fonts/poppins/ttf/Poppins-Medium.ttf';
        if (!is_file($fBold) || !is_file($fSemi) || !is_file($fMed)) {
            return '';
        }

        $W = 1200;
        $H = 630;
        $img = imagecreatetruecolor($W, $H);
        imagealphablending($img, true);
        $blanc = imagecolorallocate($img, 255, 255, 255);
        $fond = imagecolorallocate($img, 244, 246, 250);
        $bleu = imagecolorallocate($img, 53, 100, 166);
        $orange = imagecolorallocate($img, 255, 107, 53);
        $noir = imagecolorallocate($img, 16, 24, 40);
        $gris = imagecolorallocate($img, 102, 112, 133);
        $grisClair = imagecolorallocate($img, 152, 162, 179);
        imagefilledrectangle($img, 0, 0, $W, $H, $blanc);

        /* Photo du produit, entière, centrée sur fond clair */
        $zone = 630;
        imagefilledrectangle($img, 0, 0, $zone, $H, $fond);
        $photo = null;
        $chemin = trim(str_replace('\\', '/', (string) ($p['image_principale'] ?? '')), '/');
        if ($chemin !== '') {
            $photo = partage_image_ouvrir($root . '/upload/' . $chemin);
        }
        if ($photo) {
            $pw = imagesx($photo);
            $ph = imagesy($photo);
            $marge = 36;
            $echelle = min(($zone - 2 * $marge) / $pw, ($H - 2 * $marge) / $ph);
            $dw = (int) round($pw * $echelle);
            $dh = (int) round($ph * $echelle);
            imagecopyresampled($img, $photo, (int) (($zone - $dw) / 2), (int) (($H - $dh) / 2), 0, 0, $dw, $dh, $pw, $ph);
        } else {
            /* Pas de photo lisible : grand logo sur fond blanc (le logo a un fond blanc), séparé par un filet */
            imagefilledrectangle($img, 0, 0, $zone, $H, $blanc);
            imagefilledrectangle($img, $zone, 40, $zone + 1, $H - 48, imagecolorallocate($img, 234, 236, 240));
            $logo = partage_image_ouvrir($root . '/image/logo_market.png');
            if ($logo) {
                $lw = 440;
                $lh = (int) round(imagesy($logo) * $lw / imagesx($logo));
                imagecopyresampled($img, $logo, (int) (($zone - $lw) / 2), (int) (($H - $lh) / 2), 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
            }
        }

        /* Colonne d'informations */
        $x = 682;
        $largeur = $W - $x - 56;
        $logo = partage_image_ouvrir($root . '/image/logo_market.png');
        if ($logo) {
            $lw = 230;
            $lh = (int) round(imagesy($logo) * $lw / imagesx($logo));
            imagecopyresampled($img, $logo, $x, 52, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
        }

        $y = 196;
        foreach (partage_image_lignes((string) ($p['nom'] ?? 'Produit'), $fBold, 34, $largeur, 3) as $l) {
            imagettftext($img, 34, 0, $x, $y, $noir, $fBold, $l);
            $y += 50;
        }

        /* Prix (promo) + ancien prix barré + badge réduction */
        $prix = (float) ($p['prix'] ?? 0);
        $promo = (float) ($p['prix_promotion'] ?? 0);
        $en_promo = $promo > 0 && $promo < $prix;
        $affiche = $en_promo ? $promo : $prix;
        $y += 34;
        if ($affiche > 0) {
            $fin = partage_image_texte($img, 46, $x, $y, $orange, $fBold, number_format($affiche, 0, ',', ' ') . ' FCFA');
            if ($en_promo) {
                $pct = (int) round(($prix - $promo) / $prix * 100);
                $badge = '-' . $pct . ' %';
                $bb = imagettfbbox(20, 0, $fBold, $badge);
                $bw = $bb[2] - $bb[0] + 28;
                $bx = $fin + 18;
                if ($bx + $bw <= $W - 40) {
                    partage_image_rect_arrondi($img, $bx, $y - 38, $bx + $bw, $y - 2, 16, $orange);
                    imagettftext($img, 20, 0, $bx + 14, $y - 11, $blanc, $fBold, $badge);
                }
                $y += 46;
                $ancien = number_format($prix, 0, ',', ' ') . ' FCFA';
                $fin2 = partage_image_texte($img, 24, $x, $y, $grisClair, $fMed, $ancien);
                imagesetthickness($img, 2);
                imageline($img, $x, $y - 9, $fin2, $y - 9, $grisClair);
            }
        }

        /* Boutique */
        if ($boutique !== '') {
            $texte = 'Vendu par ' . $boutique;
            $lignes = partage_image_lignes($texte, $fSemi, 22, $largeur, 1);
            imagettftext($img, 22, 0, $x, 506, $gris, $fSemi, $lignes[0] ?? $texte);
        }

        /* Bouton d'appel à l'action */
        partage_image_rect_arrondi($img, $x, 534, $x + 300, 590, 28, $bleu);
        $cta = 'Voir le produit';
        $cb = imagettfbbox(22, 0, $fBold, $cta);
        imagettftext($img, 22, 0, $x + (int) ((300 - ($cb[2] - $cb[0])) / 2), 571, $blanc, $fBold, $cta);

        /* Liseré aux couleurs de la marque */
        imagefilledrectangle($img, 0, $H - 8, (int) ($W * 0.6), $H, $bleu);
        imagefilledrectangle($img, (int) ($W * 0.6), $H - 8, $W, $H, $orange);

        if (!is_dir(dirname($dest))) {
            @mkdir(dirname($dest), 0775, true);
        }
        $tmp = $dest . '.' . getmypid() . '.tmp';
        $ok = imagejpeg($img, $tmp, 86);
        if (!$ok || !@rename($tmp, $dest)) {
            @unlink($tmp);
            return '';
        }
        return $dest;
    }
}
