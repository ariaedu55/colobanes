<?php
/* Assistant — échanges avec les vendeurs : messagerie, négociation, favoris. */
return [
    [
        'id' => 'messagerie', 'title' => 'Contacter un vendeur', 'audience' => 'client',
        'keywords' => ['contacter le vendeur', 'contacter un vendeur', 'ecrire au vendeur', 'messagerie', 'discuter avec', 'parler au vendeur', 'question au vendeur', 'appeler le vendeur'],
        'questions' => ['comment contacter un vendeur', 'je veux parler a la boutique', 'ou sont mes messages', 'comment ecrire a une boutique', 'le vendeur ne repond pas'],
        'answer' => "Depuis une fiche produit, une vitrine ou une commande, touchez **Message** pour écrire au vendeur ; pendant une commande, le bouton **Appeler** est aussi disponible.\nVos conversations sont dans la **messagerie** (icône bulle), avec une notification à chaque réponse. Sans réponse, réessayez plus tard ou contactez le support.",
        'links' => [['Messagerie', '/user/messages.php']],
    ],
    [
        'id' => 'message_bloque', 'title' => 'Message bloqué (numéro ou e-mail)',
        'keywords' => ['message bloque', 'numero bloque', 'impossible d envoyer mon numero', 'pourquoi mon message ne part pas', 'donner mon numero', 'partager mon numero'],
        'questions' => ['pourquoi je ne peux pas envoyer mon numero', 'mon message a ete bloque', 'le site cache mon numero de telephone', 'je veux donner mon whatsapp au vendeur'],
        'answer' => "Pour votre sécurité, la messagerie **bloque les numéros de téléphone, e-mails et insultes** : les échanges restent sur COLObanes, ce qui vous protège des arnaques. Convenez du rendez-vous ou de la livraison directement dans la conversation.",
        'links' => [['Messagerie', '/user/messages.php']],
    ],
    [
        'id' => 'negocier', 'title' => 'Négocier un prix', 'audience' => 'client',
        'keywords' => ['negocier', 'negociation', 'marchander', 'faire une offre', 'proposer un prix', 'baisser le prix', 'discuter le prix', 'contre proposition'],
        'questions' => ['est ce que je peux negocier le prix', 'comment faire une proposition de prix', 'le vendeur peut il baisser', 'j ai fait une offre que se passe t il'],
        'answer' => "Sur les produits qui le permettent, touchez **Négocier le prix** sur la fiche et proposez votre montant. Le vendeur peut **accepter**, **refuser** ou faire une **contre-proposition** ; vous êtes notifié dans la messagerie.\nSi l’offre est acceptée, vous commandez **au prix convenu**.",
        'links' => [['Messagerie', '/user/messages.php']],
    ],
    [
        'id' => 'favoris', 'title' => 'Favoris', 'audience' => 'client',
        'keywords' => ['favori', 'favoris', 'coeur', 'liste d envies', 'wishlist', 'sauvegarder un produit', 'enregistrer un produit'],
        'questions' => ['comment ajouter aux favoris', 'ou sont mes favoris', 'garder un produit pour plus tard', 'retirer un favori'],
        'answer' => "Touchez le **cœur** sur un produit pour l’ajouter à vos favoris (il faut être connecté). Retrouvez-les dans **Mon compte → Favoris** ; touchez à nouveau le cœur pour le retirer.",
        'links' => [['Mes favoris', '/user/favoris.php']],
    ],
];
