<?php
/**
 * Action POST — ajouter / retirer un produit des favoris
 */
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/controllers/controller_favoris.php';

process_toggle_favori();
