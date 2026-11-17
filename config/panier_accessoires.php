<?php
$example = __DIR__ . '/panier_accessoires.example.php';
return is_readable($example) ? require $example : [];
