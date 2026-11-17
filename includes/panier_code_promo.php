<?php
/**
 * Code promo en session pour le panier
 */

require_once __DIR__ . '/../models/model_codes_promo.php';

function panier_code_promo_session_get()
{
    if (empty($_SESSION['panier_code_promo']) || !is_array($_SESSION['panier_code_promo'])) {
        return null;
    }
    return $_SESSION['panier_code_promo'];
}

/**
 * @param array<string, mixed> $promo
 */
function panier_code_promo_session_set(array $promo)
{
    $_SESSION['panier_code_promo'] = $promo;
}

function panier_code_promo_session_clear()
{
    unset($_SESSION['panier_code_promo']);
}

/**
 * Revalide le code en session (dates, actif, montant panier).
 *
 * @return array{ok:bool, message:string, promo?:array, remise:float}
 */
function panier_code_promo_resolve($sous_total)
{
    $stored = panier_code_promo_session_get();
    if (!$stored || empty($stored['code'])) {
        return ['ok' => true, 'message' => '', 'remise' => 0.0];
    }

    $check = codes_promo_validate_for_panier((string) $stored['code'], $sous_total);
    if (!$check['ok']) {
        panier_code_promo_session_clear();
        return ['ok' => false, 'message' => $check['message'], 'remise' => 0.0];
    }

    $promo = $check['promo'];
    panier_code_promo_session_set($promo);

    return [
        'ok' => true,
        'message' => '',
        'promo' => $promo,
        'remise' => (float) ($promo['remise_montant'] ?? 0),
    ];
}
