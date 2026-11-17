<?php
/**
 * Version des assets pour cache busting
 * Force les navigateurs à recharger les CSS/JS modifiés
 *
 * @return string Timestamp du fichier CSS le plus récent, ou version config
 */
function get_asset_version() {
    static $version = null;
    if ($version !== null) {
        return $version;
    }
    $config_file = __DIR__ . '/../config/assets.php';
    $config_version = 0;
    if (file_exists($config_file)) {
        $config = require $config_file;
        $config_version = (int) ($config['version'] ?? 0);
    }
    /* Automatique : date du fichier CSS/JS modifié en dernier (recalculée au plus toutes les 60 s).
       Un fichier modifié est donc rechargé par les navigateurs sans toucher à config/assets.php. */
    $version = (string) max($config_version, asset_version_latest_mtime(), 1);
    return $version;
}

function asset_version_latest_mtime(): int
{
    $cache = __DIR__ . '/../storage/taches_auto/asset_mtime';
    if (is_file($cache) && time() - (int) @filemtime($cache) < 60) {
        return (int) @file_get_contents($cache);
    }
    $max = 0;
    foreach (['css', 'js'] as $dir) {
        foreach (glob(__DIR__ . '/../' . $dir . '/*.{css,js}', GLOB_BRACE) ?: [] as $f) {
            $max = max($max, (int) @filemtime($f));
        }
    }
    if (is_dir(dirname($cache)) || @mkdir(dirname($cache), 0775, true)) {
        @file_put_contents($cache, (string) $max, LOCK_EX);
    }
    return $max;
}

/**
 * Retourne la chaîne ?v=xxx pour cache busting
 * @return string Ex: ?v=1234567890 ou ''
 */
function asset_version_query() {
    $v = get_asset_version();
    return $v ? '?v=' . $v : '';
}
