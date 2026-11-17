<?php
/**
 * Description produit — pas de limite applicative (colonne BDD TEXT ~ 65 535 octets).
 */
if (!defined('PRODUIT_DESCRIPTION_MAX_LEN')) {
    define('PRODUIT_DESCRIPTION_MAX_LEN', 0);
}

if (!function_exists('produit_description_db_max_chars')) {
    function produit_description_db_max_chars(): int
    {
        return 65535;
    }
}
