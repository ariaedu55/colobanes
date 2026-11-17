<?php
/* Test de l'assistant : php scripts/test_assistant_kb.php (ligne de commande uniquement) */
if (PHP_SAPI !== 'cli') { exit; }
chdir(dirname(__DIR__));
$_SERVER['REQUEST_URI'] = '/';
require 'conn/conn.php';
require 'includes/assistant/assistant_engine.php';
$cases = [
 // découvrir
 ['c\'est quoi colobanes ?', 'presentation'], ['vous êtes qui', 'presentation'], ['comment marche votre site', 'presentation'],
 ['vous êtes dans quels pays ?', 'pays'], ['vous livrez a abidjan ?', 'pays'], ['comment changer de région', 'pays'],
 ['le site existe en anglais ?', 'langue'], ['changer la langue', 'langue'],
 ['comment faire une recherche', 'recherche'], ['quelles sont les catégories ?', 'categories'], ['vous vendez quoi ?', 'categories'],
 ['vous avez des soldes ?', 'promos'], ['j\'ai un code promo', 'promos'], ['les nouveaux produits', 'nouveautes'],
 ['où voir toutes les boutiques', 'boutiques'], ['boutiques près de chez moi', 'boutiques_proches'], ['magasin autour de moi', 'boutiques_proches'],
 ['comment s\'abonner à une boutique ?', 'suivre_boutique'], ['comment choisir la taille', 'fiche_produit'], ['le produit est en rupture de stock', 'stock'],
 ['comment partager un produit', 'partager'], ['les produits que j\'ai regardés', 'historique'],
 // compte
 ['comment créer un compte', 'inscription'], ['je veux m\'inscrire', 'inscription'], ['inscripsion', 'inscription'],
 ['je n\'arrive pas à me connecter', 'connexion'], ['comment me connecter avec google', 'connexion'],
 ['j\'ai oublié mon mot de passe', 'mot_de_passe'], ['mot de pass oublié', 'mot_de_passe'], ['changer mon mdp', 'mot_de_passe'],
 ['comment modifier mon numéro de téléphone', 'profil'], ['comment me déconnecter', 'deconnexion'],
 ['je ne reçois pas les notifications', 'notifications'], ['avez-vous une application ?', 'application'], ['telecharger l\'appli android', 'application'],
 ['activer le mode sombre', 'mode_sombre'], ['où voir les annonces de colobanes', 'annonces_plateforme'],
 // commandes
 ['comment passer une commande ?', 'commander'], ['comment acheter', 'commander'], ['enlever un article du panier', 'panier'],
 ['pourquoi j\'ai deux commandes', 'multi_boutiques'], ['comment payer ?', 'paiement'], ['je peux payer avec wave ?', 'paiement'],
 ['paiement à la livraison possible ?', 'paiement'], ['comment se passe la livraison ?', 'livraison'], ['vous livrez à domicile ?', 'livraison'],
 ['combien coûte la livraison ?', 'frais_livraison'], ['la livraison est gratuite ?', 'frais_livraison'],
 ['combien de temps pour être livré ?', 'delai'], ['quand vais-je recevoir ma commande', 'delai'],
 ['je peux récupérer en boutique ?', 'retrait'], ['comment suivre ma commande ?', 'suivi'], ['que veut dire en attente', 'suivi'],
 ['j\'ai reçu mon colis que faire', 'reception'], ['comment annuler ma commande', 'annulation'], ['je veux annuler', 'annulation'],
 ['je veux être remboursé', 'retour'], ['le produit est abîmé', 'retour'], ['j\'ai reçu le mauvais article', 'retour'], ['il y a une garantie ?', 'retour'],
 ['refaire la même commande', 'recommander'], ['comment laisser un avis', 'avis'], ['noter le vendeur', 'avis'],
 ['ma commande est en retard', 'pas_recu'], ['le livreur n\'est pas venu', 'pas_recu'],
 // échanges
 ['comment contacter un vendeur ?', 'messagerie'], ['le vendeur ne répond pas', 'messagerie'],
 ['pourquoi je ne peux pas envoyer mon numéro', 'message_bloque'], ['je peux négocier le prix ?', 'negocier'], ['comment marchander', 'negocier'],
 ['comment ajouter aux favoris', 'favoris'],
 // dons
 ['c\'est quoi les dons', 'dons'], ['je veux donner des affaires', 'dons'], ['comment publier un don', 'dons_offrir'],
 ['comment demander un objet donné', 'dons_demander'], ['combien de temps dure la réservation du don', 'dons_reservation'],
 ['confirmer que l\'objet a été remis', 'dons_gerer'], ['le donateur me demande de l\'argent', 'dons_securite'],
 // confiance
 ['comment vous contacter', 'contact'], ['service client', 'contact'], ['quelle est votre adresse', 'contact'],
 ['est-ce que le site est fiable ?', 'arnaque'], ['un vendeur me demande de payer à l\'avance', 'arnaque'],
 ['comment signaler un vendeur', 'signaler'], ['que faites-vous de mes données', 'donnees'], ['comment supprimer mon compte', 'suppression_compte'],
 ['où lire les conditions générales', 'cgu'], ['que veut dire boutique certifiée', 'certification'], ['c\'est quoi le badge vip', 'certification'],
 // vendeur
 ['comment devenir vendeur ?', 'vendre'], ['je veux ouvrir une boutique', 'vendre'], ['est-ce payant de vendre ?', 'vendre'],
 ['j\'ai oublié mon code pin', 'vendeur_connexion'], ['pourquoi ma boutique n\'apparaît pas', 'vendeur_activation'],
 ['comment ajouter un produit', 'vendeur_produit'], ['mettre un produit en promotion', 'vendeur_produit'], ['comment mettre à jour mon stock', 'vendeur_stock'],
 ['comment accepter une commande', 'vendeur_commandes'], ['comment mettre une commande en livraison', 'vendeur_commandes'],
 ['un client a proposé un prix', 'vendeur_negociation'], ['comment changer mon logo', 'vendeur_vitrine'], ['comment certifier ma boutique', 'vendeur_certification'],
 ['comment encaisser une vente en boutique', 'vendeur_caisse'], ['où voir mes statistiques', 'vendeur_tableau'],
 // fautes et langage familier
 ['comment on fé pour commandé', 'commander'], ['livrezon gratuite?', 'frais_livraison'], ['rembourcement svp', 'retour'], ['jveux annuler ma cmd', 'annulation'],
 // recherches produit (aucune fiche attendue)
 ['iphone', '#produit'], ['chaussures homme à moins de 20000', '#produit'], ['telephone le moins cher', '#produit'], ['robe', '#produit'], ['casquette', '#produit'], ['montre-moi des chaussures', '#produit'],
 // commandes du client
 ['où est ma commande ?', 'suivi'], ['mes commandes', 'suivi'],
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
