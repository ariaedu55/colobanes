<?php
/**
 * Refuse l'exécution via le navigateur — scripts CLI / maintenance uniquement.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
