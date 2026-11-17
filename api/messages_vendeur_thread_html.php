<?php
/**
 * Alias — fil HTML fragment (délègue à messages_vendeur_thread.php?format=html)
 */
$_GET['format'] = 'html';
require __DIR__ . '/messages_vendeur_thread.php';
