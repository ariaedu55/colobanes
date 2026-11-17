<?php
/**
 * Alias URL courte → tableau de bord admin/vendeur (/admin/dashboard.php)
 */
require_once __DIR__ . '/includes/app_base.php';

header('Location: ' . app_url('/admin/dashboard.php'), true, 302);
exit;
