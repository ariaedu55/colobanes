<?php
/**
 * Partage produit (WhatsApp, réseaux, lien) — frontend catalogue.
 */

if (!function_exists('product_share_abs_url')) {
    function product_share_abs_url(int $produit_id): string
    {
        if ($produit_id <= 0) {
            return '';
        }
        if (!function_exists('get_site_base_url')) {
            require_once __DIR__ . '/site_url.php';
        }
        return get_site_base_url() . '/produit.php?id=' . $produit_id;
    }
}

if (!function_exists('product_share_price_label')) {
    function product_share_price_label(array $produit): string
    {
        $prix_base = (float) ($produit['prix'] ?? 0);
        $prix_promo = isset($produit['prix_promotion']) ? (float) $produit['prix_promotion'] : 0.0;
        $prix = ($prix_promo > 0 && $prix_promo < $prix_base) ? $prix_promo : $prix_base;
        return number_format($prix, 0, ',', ' ') . ' FCFA';
    }
}

if (!function_exists('product_share_text_short')) {
    function product_share_text_short(array $produit): string
    {
        if (!defined('SITE_BRAND_NAME')) {
            require_once __DIR__ . '/site_brand.php';
        }
        $nom = trim((string) ($produit['nom'] ?? 'Produit'));
        $prix = product_share_price_label($produit);
        return 'Regarde ce que j\'ai trouvé sur ' . SITE_BRAND_NAME . ' : ' . $nom . ' à ' . $prix;
    }
}

if (!function_exists('product_share_accroche')) {
    /** Description de l'aperçu du lien partagé (sous le titre, dans WhatsApp / Facebook). */
    function product_share_accroche(array $produit, string $boutique = ''): string
    {
        if (!defined('SITE_BRAND_NAME')) {
            require_once __DIR__ . '/site_brand.php';
        }
        $prix_base = (float) ($produit['prix'] ?? 0);
        $promo = (float) ($produit['prix_promotion'] ?? 0);
        $txt = 'Regarde ce que j\'ai trouvé sur ' . SITE_BRAND_NAME . ' !';
        if ($promo > 0 && $promo < $prix_base) {
            $txt .= ' -' . (int) round(($prix_base - $promo) / $prix_base * 100) . ' % en ce moment.';
        }
        if ($boutique !== '') {
            $txt .= ' Vendu par ' . $boutique . '.';
        }
        return $txt . ' Commande en quelques clics sur la marketplace du Sénégal.';
    }
}

if (!function_exists('product_share_message')) {
    function product_share_message(array $produit): string
    {
        if (!defined('SITE_BRAND_NAME')) {
            require_once __DIR__ . '/site_brand.php';
        }
        $pid = (int) ($produit['id'] ?? 0);
        $nom = trim((string) ($produit['nom'] ?? 'Produit'));
        $url = product_share_abs_url($pid);
        $prix = product_share_price_label($produit);
        return 'Regarde ce que j\'ai trouvé sur ' . SITE_BRAND_NAME . " !\n" . $nom . ' à ' . $prix . "\n" . $url;
    }
}

if (!function_exists('product_share_whatsapp_url')) {
    function product_share_whatsapp_url(array $produit): string
    {
        return 'https://wa.me/?text=' . rawurlencode(product_share_message($produit));
    }
}

if (!function_exists('product_share_facebook_url')) {
    function product_share_facebook_url(array $produit): string
    {
        $url = product_share_abs_url((int) ($produit['id'] ?? 0));
        return 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url);
    }
}

if (!function_exists('product_share_twitter_url')) {
    function product_share_twitter_url(array $produit): string
    {
        if (!defined('SITE_BRAND_NAME')) {
            require_once __DIR__ . '/site_brand.php';
        }
        $url = product_share_abs_url((int) ($produit['id'] ?? 0));
        $nom = trim((string) ($produit['nom'] ?? 'Produit'));
        $text = 'Regarde ce que j\'ai trouvé sur ' . SITE_BRAND_NAME . ' : ' . $nom;
        return 'https://twitter.com/intent/tweet?text=' . rawurlencode($text) . '&url=' . rawurlencode($url);
    }
}
