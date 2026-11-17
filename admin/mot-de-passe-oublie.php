<?php
require_once __DIR__ . '/../includes/app_base.php';
/**
 * Redirection vers le formulaire unifié (compte vendeur / boutique).
 */
header('Location: ' . app_url('/mot-de-passe-oublie.php?type=boutique'), true, 302);
exit;
