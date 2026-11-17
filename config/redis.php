<?php
/**
 * Redis — prêt côté serveur (DB 2). Sessions/cache app désactivés tant que non validés.
 */
return [
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 2,
    'password' => null,
    'timeout' => 1.5,
    'read_write_timeout' => 1.5,
    'prefix' => 'colobanes:',
    'sessions_enabled' => false,
    'cache_enabled' => false,
    'cache_ttl' => 120,
];
