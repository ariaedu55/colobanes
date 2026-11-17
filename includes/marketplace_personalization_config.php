<?php

function marketplace_personalization_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $path = dirname(__DIR__) . '/config/personalization.php';
    if (!is_readable($path)) {
        $path = dirname(__DIR__) . '/config/personalization.example.php';
    }
    $cfg = is_readable($path) ? (require $path) : [];
    if (!is_array($cfg)) {
        $cfg = [];
    }
    return $cfg;
}
