<?php
/**
 * Configuration notifications planifiées.
 */
$example = __DIR__ . '/notifications.example.php';
return file_exists($example) ? require $example : [];
