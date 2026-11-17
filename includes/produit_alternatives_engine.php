<?php
/**
 * Alternatives disponibles (proche du produit, en stock).
 */

require_once __DIR__ . '/produit_disponibilite.php';
require_once __DIR__ . '/produits_similaires_engine.php';

/**
 * @param array<string, mixed> $produit
 * @return list<array<string, mixed>>
 */
function get_produits_alternatives_disponibles(array $produit, int $generale_id, int $limit = 6): array
{
    $limit = max(1, min(12, $limit));
    $produit_id = (int) ($produit['id'] ?? 0);
    if ($produit_id <= 0) {
        return [];
    }

    $pool = get_produits_similaires_recommandes($produit, $generale_id, $limit * 4, 100);
    $out = [];
    foreach ($pool as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((int) ($row['id'] ?? 0) === $produit_id) {
            continue;
        }
        if (!produit_est_achetable($row)) {
            continue;
        }
        $out[] = $row;
        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}
