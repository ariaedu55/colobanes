<?php
/**
 * Helpers types de boutique — options pour les formulaires vendeur.
 */

if (!function_exists('boutique_types_table_exists')) {
    require_once dirname(__DIR__) . '/models/model_boutique_types.php';
}

/**
 * Au moins un type actif est configuré (champ obligatoire à l'inscription).
 */
function boutique_types_inscription_required()
{
    if (!boutique_types_table_exists()) {
        return false;
    }
    return count(boutique_types_list_active()) > 0;
}

/**
 * HTML <option> pour un select type de boutique.
 */
function boutique_types_options_html($selected_id = 0, $with_empty = true, $empty_label = 'Sélectionnez un type de boutique')
{
    $selected_id = (int) $selected_id;
    $types = boutique_types_list_active();
    $html = '';
    if ($with_empty) {
        $html .= '<option value="">' . htmlspecialchars($empty_label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    foreach ($types as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $nom = trim((string) ($row['nom'] ?? ''));
        if ($nom === '') {
            continue;
        }
        $sel = $id === $selected_id ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $sel . '>'
            . htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

/**
 * Valide un type pour l'inscription vendeur.
 */
function boutique_type_validate_inscription($type_id)
{
    if (!boutique_types_inscription_required()) {
        return ['ok' => true, 'id' => null];
    }
    $type_id = (int) $type_id;
    if ($type_id <= 0) {
        return ['ok' => false, 'message' => 'Veuillez sélectionner le type de votre boutique.'];
    }
    if (!boutique_type_is_valid_active($type_id)) {
        return ['ok' => false, 'message' => 'Le type de boutique sélectionné n\'est pas valide.'];
    }
    return ['ok' => true, 'id' => $type_id];
}

/**
 * Valeur spéciale : pas de plafond de distance (toutes les boutiques géolocalisées).
 */
function boutique_types_distance_unlimited_km()
{
    return 999;
}

function boutique_types_distance_is_unlimited($km)
{
    return (int) $km === boutique_types_distance_unlimited_km();
}

/**
 * Convertit la valeur filtre (km) en rayon SQL (0 = sans limite).
 */
function boutique_types_distance_to_rayon_km($km)
{
    $km = (int) $km;
    if ($km <= 0 || boutique_types_distance_is_unlimited($km)) {
        return 0.0;
    }
    return (float) $km;
}

/**
 * Limites d'affichage carte (nombre de boutiques).
 *
 * @return array<int, string>
 */
function boutique_types_map_limit_options()
{
    return [
        20 => '20',
        50 => '50',
        200 => 'Total',
    ];
}

function boutique_types_map_limit_default()
{
    return 200;
}

function boutique_types_map_limit_is_valid($limit)
{
    $limit = (int) $limit;
    return array_key_exists($limit, boutique_types_map_limit_options());
}

function boutique_types_map_limit_normalize($limit)
{
    $limit = (int) $limit;
    if (!boutique_types_map_limit_is_valid($limit)) {
        return boutique_types_map_limit_default();
    }
    return $limit;
}

/**
 * Pastilles HTML — nombre de boutiques affichées sur la carte.
 */
function boutique_types_map_limit_chips_html($selected_limit = 200, $input_id = 'mpBtMapFilterLimit')
{
    $selected_limit = boutique_types_map_limit_normalize($selected_limit);
    $html = '<div class="mp-bt-dist-chips" role="group" aria-label="Nombre de boutiques">';
    $html .= '<input type="hidden" id="' . htmlspecialchars($input_id, ENT_QUOTES, 'UTF-8') . '" value="' . (int) $selected_limit . '">';
    foreach (boutique_types_map_limit_options() as $limit => $label) {
        $active = ((int) $limit === $selected_limit) ? ' is-active' : '';
        $pressed = ((int) $limit === $selected_limit) ? 'true' : 'false';
        $html .= '<button type="button" class="mp-bt-dist-chips__btn' . $active . '"'
            . ' data-limit="' . (int) $limit . '"'
            . ' aria-pressed="' . $pressed . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</button>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Rayons de distance autorisés (km) pour le filtre catalogue.
 *
 * @return array<int, string>
 */
function boutique_types_distance_options()
{
    return [
        5 => '5 km',
        15 => '15 km',
        50 => '50 km',
        999 => 'Total',
    ];
}

function boutique_types_distance_is_valid($km)
{
    $km = (int) $km;
    return array_key_exists($km, boutique_types_distance_options());
}

/**
 * Valeur filtre par défaut (proximité Colobane / Dakar).
 */
function boutique_types_distance_default_km()
{
    return 15;
}

function boutique_types_distance_options_html($selected_km = 0, $allow_empty = false)
{
    $selected_km = (int) $selected_km;
    if (!$allow_empty && !boutique_types_distance_is_valid($selected_km)) {
        $selected_km = boutique_types_distance_default_km();
    }
    $html = '';
    if ($allow_empty) {
        $sel0 = ($selected_km === 0 || !boutique_types_distance_is_valid($selected_km)) ? ' selected' : '';
        $html .= '<option value="0"' . $sel0 . '>Peu importe</option>';
    }
    foreach (boutique_types_distance_options() as $km => $label) {
        $sel = ((int) $km === $selected_km) ? ' selected' : '';
        $html .= '<option value="' . (int) $km . '"' . $sel . '>'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

/**
 * Pastilles HTML pour le filtre distance (catalogue).
 */
function boutique_types_distance_chips_html($selected_km = 0, $input_id = 'mpBtMapFilterDist')
{
    $selected_km = (int) $selected_km;
    if (!boutique_types_distance_is_valid($selected_km)) {
        $selected_km = boutique_types_distance_default_km();
    }
    $html = '<div class="mp-bt-dist-chips" role="group" aria-label="Filtrer par distance">';
    $html .= '<input type="hidden" id="' . htmlspecialchars($input_id, ENT_QUOTES, 'UTF-8') . '" name="dist" value="' . (int) $selected_km . '">';
    foreach (boutique_types_distance_options() as $km => $label) {
        $active = ((int) $km === $selected_km) ? ' is-active' : '';
        $pressed = ((int) $km === $selected_km) ? 'true' : 'false';
        $html .= '<button type="button" class="mp-bt-dist-chips__btn' . $active . '"'
            . ' data-dist="' . (int) $km . '"'
            . ' aria-pressed="' . $pressed . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</button>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * HTML options filtre type (inclut « Tous les types »).
 */
function boutique_types_filter_options_html($selected_id = 0)
{
    return boutique_types_options_html((int) $selected_id, true, 'Tous les types');
}

/**
 * @return array<int, array{id:int,nom:string}>
 */
function boutique_types_filter_json_list()
{
    $out = [];
    foreach (boutique_types_list_active() as $row) {
        $id = (int) ($row['id'] ?? 0);
        $nom = trim((string) ($row['nom'] ?? ''));
        if ($id > 0 && $nom !== '') {
            $out[] = ['id' => $id, 'nom' => $nom];
        }
    }
    return $out;
}
