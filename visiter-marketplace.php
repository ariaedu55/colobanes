<?php
require_once __DIR__ . '/includes/app_base.php';
/**
 * Rétrocompatibilité — redirection simple vers l’accueil.
 */
header('Location: ' . app_url('/index.php'), true, 302);
exit;
