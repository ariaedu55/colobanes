<?php
/* Test de l'assistant : php scripts/test_assistant_kb_inedit.php (ligne de commande uniquement) */
if (PHP_SAPI !== 'cli') { exit; }
chdir(dirname(__DIR__));
$_SERVER['REQUEST_URI'] = '/';
require 'conn/conn.php';
require 'includes/assistant/assistant_engine.php';
$cases = [
 ['bonsoir, vous faites quoi exactement ?', 'presentation'], ['le site est disponible au gabon ?', 'pays'],
 ['traduire la page en espagnol', 'langue'], ['comment trier par prix', 'recherche'], ['quels rayons avez-vous', 'categories'],
 ['il y a des réductions en ce moment ?', 'promos'], ['quoi de neuf aujourd\'hui', 'nouveautes'], ['magasins à proximité', 'boutiques_proches'],
 ['arrêter de suivre une boutique', 'suivre_boutique'], ['quelle pointure choisir', 'fiche_produit'], ['il en reste combien en stock', 'stock'],
 ['envoyer un article à un ami sur whatsapp', 'partager'], ['ouvrir un compte client', 'inscription'], ['connexion impossible', 'connexion'],
 ['réinitialiser mon mot de passe', 'mot_de_passe'], ['mettre à jour mon email', 'profil'], ['sortir de mon compte', 'deconnexion'],
 ['désactiver les notifications', 'notifications'], ['l\'appli existe sur iphone ?', 'application'], ['mettre le thème sombre', 'mode_sombre'],
 ['étapes pour acheter un article', 'commander'], ['vider mon panier', 'panier'], ['je commande chez deux vendeurs différents', 'multi_boutiques'],
 ['on paie en ligne ou à la livraison ?', 'paiement'], ['orange money accepté ?', 'paiement'], ['le livreur va m\'appeler ?', 'livraison'],
 ['frais de livraison pour dakar', 'frais_livraison'], ['livré le jour même ?', 'delai'], ['je préfère venir chercher moi-même', 'retrait'],
 ['ma commande est confirmée ça veut dire quoi', 'suivi'], ['colis reçu comment le signaler', 'reception'], ['puis-je annuler après validation', 'annulation'],
 ['le téléphone ne marche pas, je veux un échange', 'retour'], ['commander à nouveau le même produit', 'recommander'], ['donner des étoiles à un produit', 'avis'],
 ['toujours pas reçu ma livraison', 'pas_recu'], ['écrire à la boutique', 'messagerie'], ['mon message a été bloqué', 'message_bloque'],
 ['faire une offre au vendeur', 'negocier'], ['garder un produit pour plus tard', 'favoris'],
 ['objets gratuits', 'dons'], ['comment offrir mes vêtements', 'dons_offrir'], ['je suis intéressé par un don', 'dons_demander'],
 ['ma réservation a expiré', 'dons_reservation'], ['retirer mon annonce de don', 'dons_gerer'], ['joindre le support', 'contact'],
 ['c\'est une arnaque ?', 'arnaque'], ['j\'ai un litige avec une boutique', 'signaler'], ['mes infos sont protégées ?', 'donnees'],
 ['fermer mon compte définitivement', 'suppression_compte'], ['règles du site', 'cgu'], ['que signifie le badge premium', 'certification'],
 ['ouvrir ma boutique en ligne', 'vendre'], ['connexion à mon espace vendeur', 'vendeur_connexion'], ['les clients ne voient pas ma boutique', 'vendeur_activation'],
 ['publier un article à vendre', 'vendeur_produit'], ['ajouter des quantités au stock', 'vendeur_stock'], ['j\'ai reçu une nouvelle commande que faire', 'vendeur_commandes'],
 ['modifier les couleurs de ma vitrine', 'vendeur_vitrine'], ['imprimer un ticket de caisse', 'vendeur_caisse'], ['mon chiffre d\'affaires', 'vendeur_tableau'],
];
$ok = 0; $fails = [];
foreach ($cases as [$q, $want]) {
    $r = assistant_local_reply($q, []);
    if ($want === '#produit') { $got = !empty($r['products']) ? '#produit' : ($r['kb_id'] ?? (empty($r['shops']) ? 'rien' : 'boutiques')); }
    elseif ($want === '#commandes') { $got = (strpos($r['reply'], 'commandes') !== false && empty($r['kb_id'])) ? '#commandes' : ($r['kb_id'] ?? 'rien'); }
    else { $got = $r['kb_id'] ?? (!empty($r['products']) ? '#produit' : (!empty($r['shops']) ? 'boutiques' : 'rien')); }
    if ($got === $want) { $ok++; } else { $sc = assistant_kb_scored($q, 2); $fails[] = sprintf("%-50s attendu %-20s obtenu %-18s [%s]", $q, $want, $got, implode(' | ', array_map(fn($x)=>$x['entry']['id'].':'.$x['score'], $sc))); }
}
printf("%d / %d (%.1f %%)\n", $ok, count($cases), 100 * $ok / count($cases));
echo implode("\n", $fails), "\n";
