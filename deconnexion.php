<?php
/**
 * Alias URL courte → déconnexion client (/user/deconnexion.php)
 */
require_once __DIR__ . '/includes/app_base.php';

header('Location: ' . app_url('/user/deconnexion.php'), true, 302);
exit;
