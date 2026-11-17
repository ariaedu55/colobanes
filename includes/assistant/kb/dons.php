<?php
/* Assistant — espace Dons : offrir, demander, réserver, remettre, signaler. */
return [
    [
        'id' => 'dons', 'title' => 'L’espace Dons',
        'keywords' => ['dons', 'don', 'donner', 'gratuit', 'gratuitement', 'objets gratuits', 'espace solidaire', 'donation', 'offrir un objet'],
        'questions' => ['c est quoi les dons', 'comment marche l espace dons', 'je veux donner des affaires', 'recuperer des objets gratuits', 'il y a des choses gratuites'],
        'answer' => "L’espace **Dons** permet d’**offrir** ou de **récupérer gratuitement** des objets entre membres : vêtements, meubles, livres, électronique…\nC’est **toujours 0 FCFA**, sans frais ni commission. Les échanges se font par la messagerie privée de chaque don, puis l’objet est remis en main propre.",
        'links' => [['Explorer les dons', '/dons/'], ['Offrir un objet', '/dons/offrir.php']],
    ],
    [
        'id' => 'dons_offrir', 'title' => 'Offrir un objet',
        'keywords' => ['offrir un objet', 'publier un don', 'donner un objet', 'mettre en don', 'annonce de don', 'faire un don'],
        'questions' => ['comment publier un don', 'je veux donner un meuble', 'comment offrir mes vetements', 'mettre une annonce de don'],
        'answer' => "Touchez **Offrir un objet** dans l’espace Dons (il faut être connecté), puis :\n1. Ajoutez jusqu’à **6 photos**.\n2. Indiquez titre, catégorie, état (neuf à « à réparer »), quantité et description.\n3. Précisez la **région** et un quartier approximatif, et les modalités de récupération.\nN’écrivez ni téléphone ni adresse exacte : les intéressés vous contactent par la messagerie. Limite : {{dons_limite}} annonces par jour.",
        'links' => [['Offrir un objet', '/dons/offrir.php']],
    ],
    [
        'id' => 'dons_demander', 'title' => 'Demander un don',
        'keywords' => ['je suis interesse', 'demander un don', 'recuperer un don', 'avoir un objet gratuit', 'interesse par un don'],
        'questions' => ['comment demander un objet donne', 'je veux un don comment faire', 'comment avoir un objet de l espace dons', 'j ai clique sur je suis interesse'],
        'answer' => "Sur la fiche d’un don, touchez **Je suis intéressé** (avec un message facultatif). Une conversation privée s’ouvre avec le donateur, qui choisit à qui donner.\nSuivez vos demandes dans **Dons → Mes dons → Mes demandes**.",
        'links' => [['Explorer les dons', '/dons/'], ['Mes demandes', '/dons/mes-dons.php?vue=demandes']],
    ],
    [
        'id' => 'dons_reservation', 'title' => 'Réservation d’un don',
        'keywords' => ['reservation du don', 'duree de la reservation', 'reservation', 'reserve pour moi', 'reservation don', 'don reserve', 'reservation expiree', 'liberer la reservation'],
        'questions' => ['que veut dire reserve pour vous', 'combien de temps dure la reservation', 'ma reservation a expire', 'le donateur m a reserve l objet'],
        'answer' => "Quand le donateur accepte votre demande, l’objet vous est **réservé pendant {{dons_reservation}} heures** : convenez du lieu et de l’heure dans la conversation. Passé ce délai sans remise, la réservation est libérée et l’objet redevient disponible.",
        'links' => [['Mes demandes', '/dons/mes-dons.php?vue=demandes']],
    ],
    [
        'id' => 'dons_gerer', 'title' => 'Gérer mes dons (donateur)',
        'keywords' => ['retirer mon annonce', 'retirer mon don', 'supprimer mon don', 'modifier mon annonce', 'mes dons', 'gerer mes dons', 'objet remis', 'retirer mon don', 'modifier mon don', 'accepter une demande'],
        'questions' => ['comment choisir a qui donner', 'confirmer que l objet a ete remis', 'retirer mon annonce de don', 'modifier mon annonce de don'],
        'answer' => "Dans **Dons → Mes dons → Mes annonces**, chaque don liste les demandes reçues :\n• **Réserver** pour la personne choisie (ou Refuser) ;\n• après la rencontre, **Remis** : la quantité baisse et, à zéro, le don passe en **Donné** ;\n• **Modifier** ou **Retirer** l’annonce à tout moment.",
        'links' => [['Mes dons', '/dons/mes-dons.php']],
    ],
    [
        'id' => 'dons_securite', 'title' => 'Dons : argent et sécurité',
        'keywords' => ['don payant', 'demande d argent', 'on me demande de payer un don', 'signaler un don', 'arnaque don'],
        'questions' => ['le donateur me demande de l argent', 'un don peut il etre payant', 'signaler une annonce de don', 'comment rencontrer un donateur en securite'],
        'answer' => "Un don est **toujours gratuit** : si quelqu’un vous demande de l’argent, des frais de transport à l’avance ou vos codes, **refusez** et touchez **Signaler** sur l’annonce ou la conversation.\nRencontrez-vous dans un **lieu public** et ne partagez pas votre adresse exacte.",
        'links' => [['Explorer les dons', '/dons/']],
    ],
];
