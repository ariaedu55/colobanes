<?php
require_once __DIR__ . '/includes/require_admin_web.php';

echo '<h2>Configuration PHP actuelle</h2>';
echo '<pre>';
echo 'post_max_size: ' . ini_get('post_max_size') . "\n";
echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . "\n";
echo 'max_execution_time: ' . ini_get('max_execution_time') . "\n";
echo 'max_input_time: ' . ini_get('max_input_time') . "\n";
echo 'memory_limit: ' . ini_get('memory_limit') . "\n";
echo "\n";
echo 'Fichier php.ini chargé: ' . php_ini_loaded_file() . "\n";
echo 'Fichier php.ini supplémentaire: ' . php_ini_scanned_files() . "\n";
echo '</pre>';

function convertToBytes($val)
{
    $val = trim((string) $val);
    $last = strtolower($val[strlen($val) - 1] ?? '');
    $num = (float) $val;
    switch ($last) {
        case 'g':
            $num *= 1024;
            // no break
        case 'm':
            $num *= 1024;
            // no break
        case 'k':
            $num *= 1024;
    }

    return (int) $num;
}

echo '<h3>Comparaison en octets</h3><pre>';
echo 'post_max_size: ' . convertToBytes(ini_get('post_max_size')) . " bytes\n";
echo 'upload_max_filesize: ' . convertToBytes(ini_get('upload_max_filesize')) . " bytes\n";
echo '</pre>';
