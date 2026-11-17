<?php
/**
 * Télécharge TOUTES les images référencées (BDD + sitemap production) depuis colobanes.com/upload/
 *
 * Usage :
 *   php scripts/sync_all_upload_from_production.php
 *   php scripts/sync_all_upload_from_production.php --dry-run
 *   php scripts/sync_all_upload_from_production.php --force   (re-télécharge même si présent)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$dry = in_array('--dry-run', $argv, true);
$force = in_array('--force', $argv, true);
$with_variants = in_array('--with-variants', $argv, true);
$base_url = 'https://colobanes.com/upload/';

require __DIR__ . '/../conn/conn.php';
require __DIR__ . '/../includes/image_optimizer.php';

/** @var PDO $db */

/**
 * @return array<string, true>
 */
function sync_collect_paths_set(): array
{
    global $db;

    $paths = [];

    $add = static function (?string $rel) use (&$paths): void {
        $rel = trim(str_replace('\\', '/', (string) $rel), '/');
        if ($rel === '' || str_contains($rel, '..') || str_contains($rel, '://')) {
            return;
        }
        $paths[$rel] = true;
    };

    $add_json_list = static function (?string $raw) use ($add): void {
        if ($raw === null || $raw === '') {
            return;
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return;
        }
        if ($raw[0] === '[') {
            $arr = json_decode($raw, true);
            if (is_array($arr)) {
                foreach ($arr as $item) {
                    if (is_string($item) || is_numeric($item)) {
                        $add((string) $item);
                    }
                }
            }
            return;
        }
        $add($raw);
    };

    /** @var array<int, array{table:string,column:string,prefix?:string,json?:bool}> $specs */
    $specs = [
        ['table' => 'produits', 'column' => 'image_principale'],
        ['table' => 'produits', 'column' => 'images', 'json' => true],
        ['table' => 'produits', 'column' => 'bloque_image_ref'],
        ['table' => 'categories', 'column' => 'image'],
        ['table' => 'categories_generales', 'column' => 'image'],
        ['table' => 'genres', 'column' => 'image'],
        ['table' => 'produit_variantes', 'column' => 'image'],
        ['table' => 'produits_variantes', 'column' => 'image'],
        ['table' => 'logos', 'column' => 'image'],
        ['table' => 'slider', 'column' => 'image', 'prefix' => 'slider/'],
        ['table' => 'marketplace_hero_affiches', 'column' => 'image', 'prefix' => 'marketplace_hero/'],
        ['table' => 'admin', 'column' => 'boutique_logo'],
        ['table' => 'section4_config', 'column' => 'image_fond', 'prefix' => 'section4/'],
        ['table' => 'trending_config', 'column' => 'image', 'prefix' => 'trending/'],
        ['table' => 'commandes_personnalisees', 'column' => 'image'],
        ['table' => 'commandes_personnalisees', 'column' => 'image_reference'],
        ['table' => 'panier', 'column' => 'variante_image'],
        ['table' => 'stock_articles', 'column' => 'image_principale'],
        ['table' => 'videos', 'column' => 'image_preview', 'prefix' => 'videos/'],
        ['table' => 'vendeur_certification_demandes', 'column' => 'photo_document', 'prefix' => 'certifications/'],
        ['table' => 'vendeur_certification_demandes', 'column' => 'photo_piece_identite', 'prefix' => 'certifications/'],
        ['table' => 'vendeur_certification_demandes', 'column' => 'photo_piece_identite_verso', 'prefix' => 'certifications/'],
        ['table' => 'vendeur_certification_demandes', 'column' => 'photo_local_1', 'prefix' => 'certifications/'],
        ['table' => 'vendeur_certification_demandes', 'column' => 'photo_local_2', 'prefix' => 'certifications/'],
        ['table' => 'vendeur_certification_demandes', 'column' => 'photo_local_3', 'prefix' => 'certifications/'],
        ['table' => 'vendeur_certification_demandes', 'column' => 'photo_local_4', 'prefix' => 'certifications/'],
        ['table' => 'produit_image_moderation', 'column' => 'image_path'],
    ];

    foreach ($specs as $spec) {
        $table = $spec['table'];
        $column = $spec['column'];
        $prefix = $spec['prefix'] ?? '';

        try {
            $chk = $db->query("SHOW TABLES LIKE " . $db->quote($table));
            if (!$chk || !$chk->fetchColumn()) {
                continue;
            }
            $col = $db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $db->quote($column));
            if (!$col || !$col->fetchColumn()) {
                continue;
            }
            $stmt = $db->query("SELECT `{$column}` AS v FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` != ''");
            if (!$stmt) {
                continue;
            }
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $val = (string) ($row['v'] ?? '');
                if (!empty($spec['json'])) {
                    $add_json_list($val);
                    continue;
                }
                $val = trim(str_replace('\\', '/', $val));
                if ($val === '') {
                    continue;
                }
                if ($prefix !== '' && !str_contains($val, '/')) {
                    $add($prefix . $val);
                } else {
                    $add($val);
                }
            }
        } catch (Throwable $e) {
            fwrite(STDERR, "[warn] {$table}.{$column} : " . $e->getMessage() . "\n");
        }
    }

    // Sitemap production (boutiques, branding, etc.)
    $ctx = stream_context_create([
        'http' => ['timeout' => 60, 'follow_location' => 1, 'header' => "User-Agent: COLObanes-Sync/1.0\r\n"],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $sitemap = @file_get_contents('https://colobanes.com/sitemap.xml', false, $ctx);
    if (is_string($sitemap) && $sitemap !== '') {
        if (preg_match_all('#upload/([a-zA-Z0-9_./%-]+)#', $sitemap, $m)) {
            foreach ($m[1] as $p) {
                $add(rawurldecode((string) $p));
            }
        }
    }

    // Scan exhaustif : toute colonne texte contenant un chemin upload/
    try {
        $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $table = (string) $table;
            $cols = $db->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                $type = strtolower((string) ($col['Type'] ?? ''));
                if (!preg_match('/char|text|blob|json/i', $type)) {
                    continue;
                }
                $column = (string) ($col['Field'] ?? '');
                if ($column === '') {
                    continue;
                }
                $stmt = $db->query(
                    'SELECT `' . str_replace('`', '``', $column) . '` AS v FROM `' . str_replace('`', '``', $table) . '` '
                    . 'WHERE `' . str_replace('`', '``', $column) . '` IS NOT NULL AND `' . str_replace('`', '``', $column) . '` != \'\''
                );
                if (!$stmt) {
                    continue;
                }
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $val = (string) ($row['v'] ?? '');
                    if ($val === '') {
                        continue;
                    }
                    if ($val[0] === '[') {
                        $add_json_list($val);
                        continue;
                    }
                    if (preg_match_all('#(?:upload/)?((?:produits|slider|marketplace_hero|boutique_branding|categories(?:_generales)?|certifications|section4|trending|videos|logos)/[a-zA-Z0-9_./%-]+)#', $val, $m)) {
                        foreach ($m[1] as $p) {
                            $add(rawurldecode((string) $p));
                        }
                    }
                }
            }
        }
    } catch (Throwable $e) {
        fwrite(STDERR, '[warn] scan exhaustif BDD : ' . $e->getMessage() . "\n");
    }

    return $paths;
}

/**
 * @param array<string, true> $paths
 * @return array<string, true>
 */
function sync_expand_variants(array $paths, bool $include_variants): array
{
    if (!$include_variants) {
        return $paths;
    }

    $out = $paths;
    foreach (array_keys($paths) as $rel) {
        if (preg_match('/_(md|sm)\.webp$/i', $rel)) {
            continue;
        }
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (!in_array($ext, ['webp', 'jpg', 'jpeg', 'png', 'gif'], true)) {
            continue;
        }
        $md = image_optimizer_variant_relative_path($rel, 'md');
        $sm = image_optimizer_variant_relative_path($rel, 'sm');
        if ($md !== '') {
            $out[$md] = true;
        }
        if ($sm !== '') {
            $out[$sm] = true;
        }
    }
    return $out;
}

function sync_is_variant_path(string $rel): bool
{
    return (bool) preg_match('/_(md|sm)\.webp$/i', $rel);
}

function sync_download_file(string $rel, string $upload_root, bool $dry, bool $force): string
{
    $dest = $upload_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!$force && is_file($dest) && filesize($dest) > 100) {
        return 'skip';
    }

    $dir = dirname($dest);
    if (!is_dir($dir) && !$dry) {
        mkdir($dir, 0755, true);
    }

    $url = 'https://colobanes.com/upload/' . str_replace(' ', '%20', $rel);
    if ($dry) {
        echo "[dry-run] GET {$url}\n";
        return 'dry';
    }

    $ctx = stream_context_create([
        'http' => ['timeout' => 45, 'follow_location' => 1, 'header' => "User-Agent: COLObanes-Sync/1.0\r\n"],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 80) {
        return 'fail';
    }

    if (file_put_contents($dest, $data) === false) {
        return 'fail';
    }

    return 'ok';
}

$upload_root = realpath(__DIR__ . '/../upload');
if ($upload_root === false) {
    $upload_root = __DIR__ . '/../upload';
    if (!is_dir($upload_root) && !$dry) {
        mkdir($upload_root, 0755, true);
    }
    $upload_root = realpath($upload_root) ?: __DIR__ . '/../upload';
}

echo "=== Sync upload/ depuis colobanes.com ===\n";
echo "Base locale : {$upload_root}\n";
if ($dry) {
    echo "Mode : dry-run\n";
}
if ($force) {
    echo "Mode : force (écrase les fichiers existants)\n";
}
if ($with_variants) {
    echo "Mode : with-variants (tente aussi _md/_sm depuis la prod)\n";
} else {
    echo "Mode : originaux uniquement (variantes _md/_sm générées localement après sync)\n";
}

$paths = sync_expand_variants(sync_collect_paths_set(), $with_variants);
ksort($paths, SORT_STRING);

echo 'Chemins à synchroniser : ' . count($paths) . "\n\n";

$stats = ['ok' => 0, 'skip' => 0, 'fail' => 0, 'dry' => 0];
$failed_list = [];

foreach (array_keys($paths) as $rel) {
    if (!$with_variants && sync_is_variant_path($rel)) {
        continue;
    }
    $result = sync_download_file($rel, $upload_root, $dry, $force);
    $stats[$result] = ($stats[$result] ?? 0) + 1;
    if ($result === 'ok') {
        echo "OK   {$rel}\n";
    } elseif ($result === 'fail') {
        $failed_list[] = $rel;
        echo "FAIL {$rel}\n";
    }
}

echo "\n--- Résumé téléchargement ---\n";
echo "Téléchargés : {$stats['ok']}\n";
echo "Ignorés (déjà présents) : {$stats['skip']}\n";
echo "Absents sur la production (404) : {$stats['fail']}\n";
if ($dry) {
    echo "Dry-run : {$stats['dry']}\n";
}

if (!$dry && $stats['fail'] > 0) {
    echo "\nFichiers introuvables sur colobanes.com (références BDD orphelines) :\n";
    $show = array_slice($failed_list, 0, 60);
    foreach ($show as $f) {
        echo "  - {$f}\n";
    }
    if (count($failed_list) > 60) {
        echo '  … et ' . (count($failed_list) - 60) . " autres\n";
    }
}

if (!$dry && ($stats['ok'] > 0 || $stats['skip'] > 0)) {
    echo "\nGénération locale des variantes WebP (_md / _sm)…\n";
    $optimize_cmd = PHP_BINARY . ' -d error_reporting=' . (E_ALL & ~E_DEPRECATED) . ' '
        . escapeshellarg(__DIR__ . '/optimize_existing_images.php') . ' ';
    foreach (['produits', 'categories', 'categories_generales', 'slider', 'marketplace_hero', 'boutique_branding', 'section4', 'certifications', 'trending', 'videos', 'logos'] as $subdir) {
        $abs = $upload_root . DIRECTORY_SEPARATOR . $subdir;
        if (!is_dir($abs)) {
            continue;
        }
        passthru($optimize_cmd . escapeshellarg($subdir));
    }
}

$total_files = 0;
if (!$dry && is_dir($upload_root)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile()) {
            $total_files++;
        }
    }
    echo "\nTotal fichiers dans upload/ : {$total_files}\n";
}

exit(0);
