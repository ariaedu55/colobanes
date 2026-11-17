<?php
/**
 * En-tête layout espace client (sans sidebar — navigation via profil + dock boutique)
 * Programmation procédurale uniquement
 */

$user_nav_commandes_actives = 0;
$user_nav_annonces_non_lues = 0;
$user_nav_messages_non_lus = 0;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../../models/model_commandes.php';
    $user_nav_commandes_actives = count_commandes_actives_by_user((int) $_SESSION['user_id']);
    if (file_exists(__DIR__ . '/../../models/model_annonces.php')) {
        require_once __DIR__ . '/../../models/model_annonces.php';
        if (function_exists('annonces_table_exists') && annonces_table_exists()) {
            $user_nav_annonces_non_lues = annonce_count_unread_client((int) $_SESSION['user_id']);
        }
    }
    if (file_exists(__DIR__ . '/../../models/model_messages_vendeur.php')) {
        require_once __DIR__ . '/../../models/model_messages_vendeur.php';
        if (function_exists('messages_vendeur_tables_exist') && messages_vendeur_tables_exist()) {
            $user_nav_messages_non_lus = message_vendeur_count_unread_for_client((int) $_SESSION['user_id']);
        }
    }
}
?>
<div class="user-container user-container--no-sidebar">
    <main class="user-content user-content--full" id="userContent">
