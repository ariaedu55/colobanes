<?php
require_once __DIR__ . '/includes/app_base.php';
/**
 * Alias / redirection vers les conditions générales d'utilisation (URL courte ou ancienne).
 */
header('Location: ' . app_url('/conditions-utilisation.php'), true, 301);
exit;
