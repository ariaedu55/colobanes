<?php
/**
 * Font Awesome CDN — chargement non bloquant (icônes).
 */
require_once __DIR__ . '/site_perf_head.php';
site_perf_emit_resource_hints();
site_perf_stylesheet_deferred('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
