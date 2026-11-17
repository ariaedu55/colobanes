<?php
/**
 * Assistant COLObanes — réglages publics (sans secrets).
 * Les clés API sont hors web root : voir config/assistant_keys.example.php
 */
return [
    'enabled' => true,
    'bot_name' => 'Kennassy',
    /* auto = Gemini, puis Mistral, puis OpenRouter, puis Cloudflare (selon les clés présentes) */
    'provider' => 'auto',
    /* Modèles gratuits de chaque IA, dans l'ordre : quota atteint → modèle suivant, puis IA suivante */
    'gemini_models' => ['gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-flash-lite-latest', 'gemini-3.5-flash'],
    /* Mistral gratuit : limite par modèle (2 req/min, voxtral 60 req/min) — on les enchaîne tous */
    'mistral_models' => ['ministral-14b-latest', 'voxtral-small-latest', 'ministral-8b-latest', 'codestral-latest', 'ministral-3b-latest'],
    'openrouter_models' => ['nvidia/nemotron-3.5-lightning:free', 'google/gemma-4-31b-it:free', 'google/gemma-4-26b-a4b-it:free', 'nvidia/nemotron-3-ultra-550b-a55b:free'],
    'cloudflare_models' => ['@cf/meta/llama-4-scout-17b-16e-instruct', '@cf/meta/llama-3.3-70b-instruct-fp8-fast', '@cf/mistralai/mistral-small-3.1-24b-instruct'],
    'rate_limit_per_minute' => 20,
];
