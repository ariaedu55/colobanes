<?php
/**
 * EXEMPLE — copier HORS de la racine web, jamais dans htdocs.
 *
 * Local XAMPP :
 *   /Applications/XAMPP/xamppfiles/secrets/assistant_keys.php
 * Production :
 *   /home/colobanes/secrets/assistant_keys.php
 *
 * Ou définir COLOBANES_ASSISTANT_KEYS=/chemin/absolu/assistant_keys.php
 *
 * Clés gratuites (sans carte bancaire) :
 * - Gemini     : https://aistudio.google.com/apikey
 * - Mistral    : https://console.mistral.ai/api-keys  (offre gratuite « Experiment »)
 * - OpenRouter : https://openrouter.ai/keys  (modèles :free uniquement)
 * - Cloudflare : https://dash.cloudflare.com  → Workers AI (jeton + Account ID)
 */
return [
    'gemini_api_key' => '',
    'gemini_project_id' => '',
    'mistral_api_key' => '',
    'openrouter_api_key' => '',
    'cloudflare_account_id' => '',
    'cloudflare_api_token' => '',
];
