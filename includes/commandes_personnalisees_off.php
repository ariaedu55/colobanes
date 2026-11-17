<?php
/**
 * Les commandes personnalisées n'existent pas sur la plateforme : les anciennes pages
 * de cette fonctionnalité redirigent (code et table conservés, aucune donnée).
 */
require_once __DIR__ . '/app_base.php';

header('Location: ' . app_url($commandes_personnalisees_off_redirect ?? '/'), true, 302);
exit;
