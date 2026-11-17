<?php
/**
 * Jeu de test : 2 images + 2 PDF sur une demande de certification (5 emplacements max).
 *
 * Usage :
 *   php scripts/seed_certification_test_pieces.php
 *   php scripts/seed_certification_test_pieces.php --id=1
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/conn/conn.php';
require_once $root . '/models/model_vendeur_certification.php';

if (empty($db) || !($db instanceof PDO)) {
    fwrite(STDERR, "Connexion BDD indisponible.\n");
    exit(1);
}

$demande_id = 1;
foreach ($argv as $arg) {
    if (preg_match('/^--id=(\d+)$/', (string) $arg, $m)) {
        $demande_id = (int) $m[1];
    }
}

$d = vendeur_certification_get_demande_by_id($demande_id);
if (!$d) {
    fwrite(STDERR, "Demande #{$demande_id} introuvable.\n");
    exit(1);
}

$admin_id = (int) ($d['admin_id'] ?? 0);
if ($admin_id <= 0) {
    fwrite(STDERR, "admin_id invalide pour la demande #{$demande_id}.\n");
    exit(1);
}

$dir = $root . '/upload/certifications/' . $admin_id;
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
    fwrite(STDERR, "Impossible de créer {$dir}\n");
    exit(1);
}

function seed_write_test_jpeg(string $dest): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        fwrite(STDERR, "Extension GD requise pour générer les images de test.\n");
        return false;
    }
    $im = imagecreatetruecolor(320, 240);
    if ($im === false) {
        return false;
    }
    $bg = imagecolorallocate($im, 53, 100, 166);
    $fg = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, 319, 239, $bg);
    imagestring($im, 5, 80, 110, 'Test image', $fg);
    return imagejpeg($im, $dest, 88);
}

function seed_write_test_pdf(string $dest, string $title): bool
{
    $title = preg_replace('/[^\x20-\x7E]/', '', $title) ?: 'Test PDF';
    $content = "BT /F1 18 Tf 48 720 Td ({$title}) Tj ET";
    $len = strlen($content);
    $pdf = "%PDF-1.4\n"
        . "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        . "3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Contents 4 0 R/Resources<</Font<</F1<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>>>>>>>endobj\n"
        . "4 0 obj<</Length {$len}>>stream\n{$content}\nendstream\nendobj\n"
        . "xref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000261 00000 n \n"
        . "trailer<</Size 5/Root 1 0 R>>\nstartxref\n360\n%%EOF";
    return file_put_contents($dest, $pdf) !== false;
}

$files = [
    'photo_local_1' => ['name' => 'test_facade_' . bin2hex(random_bytes(4)) . '.jpg', 'writer' => 'jpeg', 'label' => 'Façade'],
    'photo_local_2' => ['name' => 'test_interieur_' . bin2hex(random_bytes(4)) . '.jpg', 'writer' => 'jpeg', 'label' => 'Intérieur'],
    'photo_document' => ['name' => 'test_document_' . bin2hex(random_bytes(4)) . '.pdf', 'writer' => 'pdf', 'label' => 'Document PDF'],
    'photo_piece_identite' => ['name' => 'test_piece_' . bin2hex(random_bytes(4)) . '.pdf', 'writer' => 'pdf', 'label' => 'Pièce PDF'],
];

$paths = [];
foreach ($files as $col => $meta) {
    $full = $dir . '/' . $meta['name'];
    $ok = $meta['writer'] === 'jpeg'
        ? seed_write_test_jpeg($full)
        : seed_write_test_pdf($full, $meta['label']);
    if (!$ok) {
        fwrite(STDERR, "Échec création fichier {$full}\n");
        exit(1);
    }
    $paths[$col] = 'certifications/' . $admin_id . '/' . $meta['name'];
}

$st = $db->prepare('
    UPDATE vendeur_certification_demandes
    SET photo_local_1 = :p1,
        photo_local_2 = :p2,
        photo_local_3 = NULL,
        photo_document = :pdoc,
        photo_piece_identite = :ppiece
    WHERE id = :id
');
$st->execute([
    'p1' => $paths['photo_local_1'],
    'p2' => $paths['photo_local_2'],
    'pdoc' => $paths['photo_document'],
    'ppiece' => $paths['photo_piece_identite'],
    'id' => $demande_id,
]);

echo "OK — demande #{$demande_id} (boutique: " . ($d['boutique_nom'] ?? '') . ")\n";
echo "  2 images : photo_local_1, photo_local_2\n";
echo "  2 PDF    : photo_document, photo_piece_identite\n";
echo "  (emplacement photo_local_3 libre — 4 pièces sur 5)\n";
echo "Ouvrez : super_admin/certifications/traiter.php?id={$demande_id}&vue=lecture\n";
