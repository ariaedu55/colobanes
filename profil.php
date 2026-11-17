<?php
/**
 * Alias URL courte → profil client (/user/profil.php)
 */
require_once __DIR__ . '/includes/app_base.php';

header('Location: ' . app_url('/user/profil.php'), true, 302);
exit;
