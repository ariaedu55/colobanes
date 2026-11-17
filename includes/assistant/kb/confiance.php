<?php
/* Assistant — confiance, sécurité, données personnelles, règles et support. */
return [
    [
        'id' => 'contact', 'title' => 'Contacter COLObanes',
        'keywords' => ['vous contacter', 'vous joindre', 'votre adresse', 'ou etes vous situe', 'ou vous trouvez vous', 'votre email', 'votre numero', 'contacter colobanes', 'service client', 'support', 'joindre', 'parler a quelqu un', 'adresse de colobanes', 'email de colobanes', 'numero de colobanes', 'reclamation'],
        'questions' => ['comment vous contacter', 'je veux parler au service client', 'ou se trouve colobanes', 'quelle est votre adresse', 'j ai une reclamation'],
        'answer' => "L’équipe COLObanes est joignable par e-mail à **contact@colobanes.com** ou via le formulaire de contact. Adresse : **Rond Point Colobane, Dakar, Sénégal**.\nPour une commande, indiquez toujours son **numéro** : nous traitons plus vite.",
        'links' => [['Nous contacter', '/contact.php']],
    ],
    [
        'id' => 'arnaque', 'title' => 'Éviter les arnaques',
        'keywords' => ['arnaque', 'arnaquer', 'escroquerie', 'escroc', 'fraude', 'faux vendeur', 'payer a l avance', 'avance', 'securise', 'fiable', 'confiance'],
        'questions' => ['est ce que le site est fiable', 'comment eviter les arnaques', 'un vendeur me demande de payer a l avance', 'je pense que c est une arnaque', 'puis je faire confiance aux vendeurs'],
        'answer' => "Quelques règles simples :\n• Commandez et discutez **sur COLObanes** : la messagerie bloque numéros et e-mails pour vous protéger.\n• Vous réglez **à la réception** ou au retrait : ne payez **jamais à l’avance** à quelqu’un qui vous contacte hors plateforme.\n• Préférez les **boutiques certifiées** et lisez les avis.\n• Un doute ? **Signalez** la boutique ou écrivez au support.",
        'links' => [['Nous contacter', '/contact.php']],
    ],
    [
        'id' => 'signaler', 'title' => 'Signaler un problème',
        'keywords' => ['signaler', 'signalement', 'abus', 'litige', 'probleme avec un vendeur', 'vendeur malhonnete', 'contenu inapproprie', 'faux produit'],
        'questions' => ['comment signaler un vendeur', 'signaler un produit suspect', 'j ai un litige avec une boutique', 'denoncer une annonce'],
        'answer' => "Écrivez au support à **contact@colobanes.com** en précisant le **numéro de commande**, le nom de la boutique et, si possible, des **captures d’écran**. L’équipe vérifie et peut suspendre une boutique ou un compte qui ne respecte pas les règles.\nDans l’espace Dons, utilisez directement le bouton **Signaler**.",
        'links' => [['Nous contacter', '/contact.php']],
    ],
    [
        'id' => 'donnees', 'title' => 'Données personnelles',
        'keywords' => ['donnees personnelles', 'vie privee', 'confidentialite', 'rgpd', 'mes donnees', 'que faites vous de mes donnees', 'cookies'],
        'questions' => ['que faites vous de mes donnees', 'mes informations sont elles protegees', 'qui voit mon numero', 'politique de confidentialite'],
        'answer' => "Vos données servent uniquement à votre compte et à vos commandes (livraison, suivi, contact du vendeur). Elles ne sont pas affichées publiquement et ne sont pas revendues. Ne communiquez jamais votre **mot de passe**, même à un vendeur.",
        'links' => [['Confidentialité', '/politique-confidentialite.php']],
    ],
    [
        'id' => 'suppression_compte', 'title' => 'Supprimer mon compte',
        'keywords' => ['supprimer mon compte', 'suppression de compte', 'fermer mon compte', 'effacer mon compte', 'desinscrire', 'clôturer mon compte'],
        'questions' => ['comment supprimer mon compte', 'je veux fermer mon compte', 'effacer mes donnees', 'se desinscrire du site'],
        'answer' => "La suppression se demande **par e-mail** à **contact@colobanes.com**, depuis l’adresse liée au compte, avec l’objet « **Demande de suppression de compte** », vos nom et prénom et vos identifiants (e-mail ou téléphone).\nUne preuve d’identité peut être demandée. Sans réponse sous **30 jours**, vous pouvez relancer.",
        'links' => [['Politique de suppression', '/politique-suppression-compte.php']],
    ],
    [
        'id' => 'cgu', 'title' => 'Conditions d’utilisation',
        'keywords' => ['conditions d utilisation', 'cgu', 'cgv', 'conditions generales', 'reglement', 'regles du site', 'mentions legales'],
        'questions' => ['ou lire les conditions generales', 'quelles sont les regles du site', 'les conditions de vente'],
        'answer' => "Les **conditions d’utilisation** détaillent la commande, le paiement, la livraison, l’annulation, les retours et vos droits. La **politique de confidentialité** explique l’usage de vos données.",
        'links' => [['Conditions d’utilisation', '/conditions-utilisation.php'], ['Confidentialité', '/politique-confidentialite.php']],
    ],
    [
        'id' => 'certification', 'title' => 'Boutiques certifiées',
        'keywords' => ['certifie', 'certifiee', 'certification', 'badge', 'verifie', 'vip', 'premium', 'standard', 'boutique certifiee'],
        'questions' => ['que veut dire boutique certifiee', 'c est quoi le badge vip', 'comment savoir si une boutique est serieuse', 'les niveaux de certification'],
        'answer' => "Une boutique **certifiée** a été vérifiée par COLObanes ; son badge apparaît sur la vitrine et les fiches produits. Les niveaux :\n{{certifs}}",
        'links' => [['Les boutiques', '/boutiques.php']],
    ],
];
