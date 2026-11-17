<?php
/**
 * Recherche assistée (barre « Que recherchez-vous ? »).
 *
 * N'intervient que si la recherche classique ne trouve rien : l'IA traduit la phrase du client
 * en noms de produits du catalogue (« cadeau pour ma maman » → parfum, sac à main, montre…),
 * puis chaque nom est cherché dans le vrai catalogue. Réponse de l'IA gardée 30 jours.
 * Sans IA disponible, la phrase est découpée localement (mots utiles, synonymes, prix) : ça marche toujours.
 */

require_once __DIR__ . '/assistant_ia_json.php';
require_once dirname(__DIR__, 2) . '/models/model_produits.php';

function assistant_recherche_ia_consigne(): string
{
    return "Tu aides la barre de recherche de COLObanes, un marché en ligne au Sénégal (mode, beauté, high-tech, maison, "
        . "électroménager, alimentation, pâtisserie, bébé, auto-moto, services…). On te donne ce qu'un client a tapé "
        . "(français, wolof, fautes possibles). Les produits du catalogue ont des noms courts comme « Robe wax », "
        . "« Casque Bluetooth », « Parfum », « Sac à main », « Chaussures homme ».\n"
        . "Traduis la demande en recherches simples. Si c'est un besoin (cadeau, mariage, rentrée, bébé, Tabaski…), "
        . "propose les produits qui y répondent. N'invente pas de marque.\n"
        . "Réponds UNIQUEMENT avec ce JSON :\n"
        . "{\"mots_cles\": [2 à 6 noms de produits de 1 à 3 mots, au singulier, du plus pertinent au moins pertinent], "
        . "\"prix_min\": montant écrit par le client ou null, \"prix_max\": montant écrit par le client ou null, "
        . "\"tri\": \"prix_asc\" si le client veut pas cher, sinon \"date\"}";
}

/**
 * @return array{produits: list<array>, mots_cles: list<string>, prix_min: ?float, prix_max: ?float, tri: string}|null
 */
function assistant_recherche_ia(string $recherche, ?float $prix_min = null, ?float $prix_max = null, ?int $categorie_id = null, int $limit = 20): ?array
{
    $q = trim(preg_replace('/\s+/u', ' ', $recherche));
    if (mb_strlen($q, 'UTF-8') < 2 || mb_strlen($q, 'UTF-8') > 120) {
        return null;
    }

    $data = assistant_ia_json(
        assistant_recherche_ia_consigne(),
        'Recherche du client : ' . $q,
        'recherche_' . md5(assistant_norm($q))
    );
    $mots = is_array($data) ? assistant_ia_termes($data['mots_cles'] ?? []) : [];
    $source = 'ia';
    $local = assistant_parse_search($q);

    /* Sans IA (quota, panne, pas de clé) : compréhension locale — mots utiles de la phrase et synonymes */
    if (!$mots) {
        $source = 'locale';
        $data = [];
        $base = array_values(array_filter(explode(' ', (string) $local['query']), static fn ($w) => mb_strlen($w, 'UTF-8') >= 3));
        if (count($base) > 1) {
            $mots[] = implode(' ', array_slice($base, 0, 3));
        }
        foreach ($base as $w) {
            $mots[] = $w;
            foreach (assistant_synonyms($w) as $syn) {
                $mots[] = $syn;
            }
        }
        $mots = assistant_ia_termes($mots, 8);
        if (!$mots) {
            return null;
        }
    }

    /* Les filtres choisis par le client priment ; sinon on reprend ce que la phrase dit du prix */
    /* Un prix de l'IA n'est retenu que si le client a écrit un montant (« pas cher » trie, il ne plafonne pas) */
    $montant_ecrit = (bool) preg_match('/\d/', $q);
    $pmin = $prix_min ?? $local['prix_min'] ?? ($montant_ecrit && is_numeric($data['prix_min'] ?? null) ? (float) $data['prix_min'] : null);
    $pmax = $prix_max ?? $local['prix_max'] ?? ($montant_ecrit && is_numeric($data['prix_max'] ?? null) ? (float) $data['prix_max'] : null);
    $tri = $local['tri'] !== 'date' ? $local['tri'] : (($data['tri'] ?? '') === 'prix_asc' ? 'prix_asc' : 'date');

    /* Une liste par mot-clé : d'abord les produits dont le NOM contient le mot */
    $listes = [];
    $mots_utiles = [];
    foreach ($mots as $mot) {
        /* On cherche le premier mot (« chaussures »), puis on garde les noms qui ont tous les mots
           (« Chaussures pour hommes ») ; sinon le premier mot seul (« robe de soirée » → « Robe ») */
        $premier = (string) strtok($mot, ' ');
        $rows = search_produits_with_filters($premier, $pmin, $pmax, $categorie_id, $tri, 0, 30);
        if ($premier !== $mot) {
            $rows = array_merge(search_produits_with_filters($mot, $pmin, $pmax, $categorie_id, $tri, 0, 15), $rows);
        }
        foreach (array_unique([$mot, $premier]) as $essai) {
            if (mb_strlen($essai, 'UTF-8') < 3 || in_array(assistant_norm($essai), array_map('assistant_norm', $mots_utiles), true)) {
                continue;
            }
            $par_nom = [];
            foreach ($rows as $r) {
                if (assistant_ia_nom_contient((string) ($r['nom'] ?? ''), $essai)) {
                    $par_nom[(int) $r['id']] = $r;
                }
            }
            if ($par_nom) {
                $listes[] = array_slice(array_values($par_nom), 0, 15);
                $mots_utiles[] = $essai;
                break;
            }
        }
    }
    if (!$listes) {
        return null;
    }
    /* Sans IA : si la phrase entière (« chaussure homme ») suffit, on n'élargit pas aux mots seuls (« chaussure ») */
    if ($source === 'locale' && count($listes) > 1 && count($listes[0]) >= 4 && str_contains($mots_utiles[0], ' ')) {
        $listes = [$listes[0]];
        $mots_utiles = [$mots_utiles[0]];
    }

    /* Mélange équilibré : 1er de chaque mot-clé, puis 2e… (un cadeau = parfum, sac, montre, pas 20 parfums) */
    $produits = [];
    $vus = [];
    for ($i = 0; count($produits) < $limit; $i++) {
        $ajout = false;
        foreach ($listes as $liste) {
            if (!isset($liste[$i])) {
                continue;
            }
            $ajout = true;
            $id = (int) ($liste[$i]['id'] ?? 0);
            if ($id > 0 && !isset($vus[$id])) {
                $vus[$id] = true;
                $produits[] = $liste[$i];
                if (count($produits) >= $limit) {
                    break;
                }
            }
        }
        if (!$ajout) {
            break;
        }
    }
    if ($tri === 'prix_asc') {
        usort($produits, static fn ($a, $b) => assistant_ia_prix($a) <=> assistant_ia_prix($b));
    }

    return ['produits' => $produits, 'mots_cles' => $mots_utiles, 'prix_min' => $pmin, 'prix_max' => $pmax, 'tri' => $tri, 'source' => $source];
}

/** Prix payé (promotion comprise). */
function assistant_ia_prix(array $p): float
{
    $prix = (float) ($p['prix'] ?? 0);
    $promo = (float) ($p['prix_promotion'] ?? 0);
    return ($promo > 0 && $promo < $prix) ? $promo : $prix;
}
