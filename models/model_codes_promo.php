<?php
/**
 * Codes promo — validation et application au panier
 */
require_once __DIR__ . '/../conn/conn.php';

function codes_promo_table_exists()
{
    global $db;
    if (!$db) {
        return false;
    }
    try {
        $db->query('SELECT 1 FROM codes_promo LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @return array<string, mixed>|null
 */
function codes_promo_find_by_code($code)
{
    global $db;
    if (!codes_promo_table_exists()) {
        return null;
    }
    $code = strtoupper(trim((string) $code));
    if ($code === '') {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM codes_promo WHERE UPPER(code) = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * @return array{ok:bool, message:string, promo?:array}
 */
function codes_promo_validate_for_panier($code, $sous_total)
{
    $sous_total = max(0, (float) $sous_total);
    $row = codes_promo_find_by_code($code);
    if (!$row) {
        return ['ok' => false, 'message' => 'Code promo invalide.'];
    }
    if (($row['actif'] ?? '') !== 'actif') {
        return ['ok' => false, 'message' => 'Ce code promo n\'est plus actif.'];
    }

    $now = time();
    if (!empty($row['date_debut']) && strtotime((string) $row['date_debut']) > $now) {
        return ['ok' => false, 'message' => 'Ce code promo n\'est pas encore valide.'];
    }
    if (!empty($row['date_fin']) && strtotime((string) $row['date_fin']) < $now) {
        return ['ok' => false, 'message' => 'Ce code promo a expiré.'];
    }

    $max = isset($row['utilisations_max']) ? (int) $row['utilisations_max'] : 0;
    $count = (int) ($row['utilisations_count'] ?? 0);
    if ($max > 0 && $count >= $max) {
        return ['ok' => false, 'message' => 'Ce code promo a atteint sa limite d\'utilisation.'];
    }

    $min = isset($row['montant_minimum']) ? (float) $row['montant_minimum'] : 0;
    if ($min > 0 && $sous_total < $min) {
        return [
            'ok' => false,
            'message' => 'Montant minimum : ' . number_format($min, 0, ',', ' ') . ' FCFA.',
        ];
    }

    $type = (string) ($row['type_remise'] ?? 'pourcentage');
    $valeur = (float) ($row['valeur'] ?? 0);

    if ($type === 'livraison_gratuite') {
        return [
            'ok' => true,
            'message' => 'Code appliqué.',
            'promo' => [
                'id' => (int) $row['id'],
                'code' => strtoupper(trim((string) $row['code'])),
                'libelle' => trim((string) ($row['libelle'] ?? '')),
                'type_remise' => 'livraison_gratuite',
                'valeur' => $valeur,
                'remise_montant' => 0.0,
                'livraison_gratuite' => true,
            ],
        ];
    }

    if ($valeur <= 0) {
        return ['ok' => false, 'message' => 'Code promo invalide.'];
    }

    $remise = 0.0;
    if ($type === 'montant_fixe') {
        $remise = min($sous_total, $valeur);
    } else {
        $remise = round($sous_total * ($valeur / 100), 0);
    }
    if ($remise <= 0) {
        return ['ok' => false, 'message' => 'Ce code ne s\'applique pas à votre panier.'];
    }

    return [
        'ok' => true,
        'message' => 'Code appliqué.',
        'promo' => [
            'id' => (int) $row['id'],
            'code' => strtoupper(trim((string) $row['code'])),
            'libelle' => trim((string) ($row['libelle'] ?? '')),
            'type_remise' => $type,
            'valeur' => $valeur,
            'remise_montant' => $remise,
        ],
    ];
}

/**
 * @param array<string, mixed> $promo
 */
function codes_promo_calculate_remise(array $promo, $sous_total)
{
    $sous_total = max(0, (float) $sous_total);
    $type = (string) ($promo['type_remise'] ?? 'pourcentage');
    $valeur = (float) ($promo['valeur'] ?? 0);
    if ($valeur <= 0) {
        return 0.0;
    }
    if ($type === 'montant_fixe') {
        return min($sous_total, $valeur);
    }
    return round($sous_total * ($valeur / 100), 0);
}
