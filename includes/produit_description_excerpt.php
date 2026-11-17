<?php
/**
 * Extrait court de la description produit (fiche détail).
 */

if (!function_exists('produit_description_max_words')) {
    function produit_description_max_words(): int
    {
        return 32;
    }
}

if (!function_exists('produit_description_plain_text')) {
    function produit_description_plain_text(string $description): string
    {
        $text = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}

if (!function_exists('produit_description_format_html')) {
    function produit_description_format_html(string $description): string
    {
        $decoded = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded !== strip_tags($decoded)) {
            $decoded = strip_tags($decoded);
        }
        return nl2br(htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8'));
    }
}

if (!function_exists('produit_description_prepare_excerpt')) {
    /**
     * @return array{truncated:bool,short:string,full:string}
     */
    function produit_description_prepare_excerpt(string $description): array
    {
        $full = trim($description);
        $plain = produit_description_plain_text($full);
        if ($plain === '') {
            return ['truncated' => false, 'short' => '', 'full' => $full];
        }
        $words = preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY);
        $max = produit_description_max_words();
        if (!is_array($words) || count($words) <= $max) {
            return ['truncated' => false, 'short' => '', 'full' => $full];
        }
        return [
            'truncated' => true,
            'short' => implode(' ', array_slice($words, 0, $max)),
            'full' => $full,
        ];
    }
}
