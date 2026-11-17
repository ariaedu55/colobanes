<?php
/**
 * Profils accessoires panier — copier vers panier_accessoires.php si besoin de surcharge.
 */
return [
    'max_produits' => 10,
    'pool_limit' => 50,
    'max_chips' => 3,
    'max_bundle_slots' => 5,
    'section_title' => 'Complétez votre achat',
    'profiles' => [
        [
            'id' => 'telephonie',
            'bundle_example' => 'téléphone + coque + chargeur',
            'priority' => 100,
            'rayon_nom_contains' => ['téléphonie', 'telephonie', 'smartphone'],
            'keywords' => [
                'iphone', 'samsung', 'galaxy', 'smartphone', 'android', 'redmi', 'xiaomi', 'huawei',
                'tecno', 'infinix', 'itel', 'oppo', 'pixel', 'nokia',
            ],
            'accessories' => [
                ['label' => 'Coque', 'termes' => ['coque', 'étui', 'etui', 'protection', 'verre trempé']],
                ['label' => 'Chargeur', 'termes' => ['chargeur', 'câble', 'cable', 'usb', 'adaptateur', 'powerbank', 'power bank', 'batterie externe']],
                ['label' => 'Audio', 'termes' => ['écouteur', 'ecouteur', 'airpods', 'casque', 'micro', 'enceinte']],
                ['label' => 'Montre', 'termes' => ['montre', 'smartwatch', 'bracelet connecté']],
                ['label' => 'Pratique', 'termes' => ['airtag', 'tracker', 'support', 'ring light', 'right light', 'trépied', 'perche', 'carte mémoire']],
            ],
            'rayon_cible_nom' => 'technolog',
            'candidate_generale_contains' => [
                'téléphonie', 'telephonie', 'électronique', 'electronique', 'technolog',
                'informatique', 'high-tech', 'hightech', 'accessoire',
            ],
            /* Sous-catégories acceptées comme accessoires (sinon le nom doit contenir un terme ci-dessus) */
            'candidate_categorie_contains' => ['accessoire', 'objets connect', 'téléphone', 'telephone'],
            /* Un accessoire coûte au plus 40 % du téléphone (évite de proposer un autre téléphone) */
            'candidate_max_price_ratio' => 0.4,
            /* Compatibilité : pas d'accessoire Android pour un iPhone, et inversement */
            'ecosystems' => [
                ['match' => ['iphone', 'apple'], 'exclude' => ['android', 'samsung']],
                ['match' => ['samsung', 'galaxy', 'xiaomi', 'redmi', 'huawei', 'tecno', 'infinix', 'itel', 'oppo', 'pixel', 'android'], 'exclude' => ['iphone', 'apple', 'airpods', 'lightning']],
            ],
            'candidate_exclude_nom' => [
                'casquette', 'bonnet', 'crochet', 'tricot', 'mode', 'robe', 'jupe',
                'pantalon', 'chaussure', 'sandale', 'bijou', 'collier', 'sac à main',
            ],
        ],
        [
            'id' => 'informatique',
            'priority' => 90,
            'bundle_example' => 'ordinateur + souris + sacoche',
            'rayon_nom_contains' => ['informatique', 'ordinateur', 'électronique'],
            'keywords' => ['macbook', 'laptop', 'ordinateur', 'pc portable', 'imac', 'tablette', 'ipad'],
            'accessories' => [
                ['label' => 'Souris', 'termes' => ['souris']],
                ['label' => 'Sacoche', 'termes' => ['sacoche', 'sac ordinateur', 'housse ordinateur']],
                ['label' => 'Clavier', 'termes' => ['clavier']],
                ['label' => 'Câbles et hubs', 'termes' => ['hdmi', 'hub', 'adaptateur', 'câble']],
            ],
            'rayon_cible_nom' => 'technolog',
            'candidate_generale_contains' => [
                'informatique', 'électronique', 'electronique', 'high-tech', 'hightech', 'technolog',
            ],
            'candidate_categorie_contains' => ['accessoire', 'réseau', 'reseau', 'objets connect'],
            'candidate_max_price_ratio' => 0.4,
            'ecosystems' => [
                ['match' => ['macbook', 'imac', 'ipad', 'apple'], 'exclude' => ['android']],
            ],
            'candidate_exclude_nom' => ['casquette', 'crochet', 'mode', 'robe', 'chaussure'],
        ],
        [
            'id' => 'bebe',
            'priority' => 85,
            'bundle_example' => 'puériculture + couches + soins bébé',
            'rayon_nom_contains' => ['bébé', 'bebe', 'puériculture', 'puericulture'],
            'keywords' => ['couche', 'couches', 'biberon', 'layette', 'poussette', 'bébé', 'bebe'],
            'accessories' => [
                ['label' => 'Couches et change', 'termes' => ['couche', 'lingette', 'liniment', 'crème']],
                ['label' => 'Soins bébé', 'termes' => ['biberon', 'tétine', 'lait', 'shampooing bébé']],
            ],
            'rayon_cible_nom' => 'Bébé',
        ],
        [
            'id' => 'auto',
            'priority' => 70,
            'rayon_nom_contains' => ['auto', 'moto'],
            'keywords' => ['voiture', 'pneu', 'batterie auto', 'dashcam'],
            'accessories' => [
                ['label' => 'Accessoires auto', 'termes' => ['chargeur voiture', 'support', 'housses', 'nettoyant']],
            ],
            'rayon_cible_nom' => 'Auto',
        ],
        [
            'id' => 'meuble',
            'priority' => 75,
            'rayon_nom_contains' => ['maison', 'meuble', 'décoration'],
            'keywords' => ['canapé', 'canape', 'lit', 'armoire', 'chaise', 'meuble', 'réfrigérateur', 'frigo', 'climatiseur'],
            'candidate_generale_contains' => ['maison', 'meuble', 'décoration', 'decoration', 'électroménager', 'electromenager'],
            'accessories' => [
                ['label' => 'Entretien et fixation', 'termes' => ['vis', 'cheville', 'colle', 'nettoyant']],
            ],
            'montage' => true,
        ],
        [
            'id' => 'electromenager',
            'priority' => 72,
            'rayon_nom_contains' => ['électroménager', 'electromenager'],
            'keywords' => ['machine à laver', 'cuisinière', 'micro-ondes', 'aspirateur'],
            'accessories' => [
                ['label' => 'Accessoires électroménager', 'termes' => ['filtre', 'rallonge', 'multiprise']],
            ],
            'montage' => true,
        ],

        /* ───── Règles de base (fallback) : utilisées tant que l'IA n'a pas préparé mieux pour ce produit,
           ou quand elle est indisponible. Le nom du complément doit contenir un des termes. ───── */
        [
            'id' => 'mode_femme',
            'fallback' => true,
            'priority' => 30,
            'bundle_example' => 'tenue + sac + chaussures + bijoux',
            'keywords' => ['robe', 'jupe', 'boubou', 'abaya', 'crop top', 'blouse', 'combinaison', 'pagne', 'bazin', 'taille basse', 'ensemble femme', 'tunique', 'legging'],
            'accessories' => [
                ['label' => 'Sac', 'termes' => ['sac', 'sacoche', 'pochette']],
                ['label' => 'Chaussures', 'termes' => ['chaussure', 'sandale', 'escarpin', 'talon', 'mule']],
                ['label' => 'Bijoux', 'termes' => ['collier', 'bracelet', 'boucle', 'bague', 'montre']],
                ['label' => 'Foulard', 'termes' => ['foulard', 'voile', 'turban', 'bonnet']],
            ],
            'candidate_nom_termes_only' => true,
            'candidate_exclude_nom' => ['homme'],
        ],
        [
            'id' => 'mode_homme',
            'fallback' => true,
            'priority' => 29,
            'bundle_example' => 'tenue + chaussures + ceinture',
            'keywords' => ['chemise', 'pantalon', 'costume', 'jalaba', 'djellaba', 'kaftan', 'polo', 't-shirt', 'tee-shirt', 'jean', 'ensemble homme', 'short', 'cardigan', 'veste'],
            'accessories' => [
                ['label' => 'Chaussures', 'termes' => ['chaussure', 'mocassin', 'basket', 'sandale']],
                ['label' => 'Ceinture', 'termes' => ['ceinture']],
                ['label' => 'Montre', 'termes' => ['montre']],
                ['label' => 'Casquette', 'termes' => ['casquette', 'bonnet', 'chapeau']],
            ],
            'candidate_nom_termes_only' => true,
            'candidate_exclude_nom' => ['femme'],
        ],
        [
            'id' => 'chaussures',
            'fallback' => true,
            'priority' => 28,
            'bundle_example' => 'chaussures + chaussettes + entretien',
            'keywords' => ['chaussure', 'chaussures', 'basket', 'baskets', 'sneakers', 'mocassin', 'mocassins', 'sandale', 'escarpin', 'talons', 'babouche'],
            'accessories' => [
                ['label' => 'Chaussettes', 'termes' => ['chaussette']],
                ['label' => 'Entretien', 'termes' => ['cirage', 'brosse', 'semelle']],
                ['label' => 'Sac', 'termes' => ['sac', 'sacoche']],
                ['label' => 'Ceinture', 'termes' => ['ceinture']],
            ],
            'candidate_nom_termes_only' => true,
        ],
        [
            'id' => 'beaute',
            'fallback' => true,
            'priority' => 27,
            'bundle_example' => 'parfum + soin du corps + maquillage',
            'keywords' => ['parfum', 'eau de toilette', 'crème', 'creme', 'lotion', 'lait corps', 'sérum', 'serum', 'maquillage', 'rouge à lèvres', 'gloss', 'fond de teint', 'savon', 'gommage'],
            'accessories' => [
                ['label' => 'Soin du corps', 'termes' => ['lait', 'lotion', 'gel douche', 'savon', 'gommage']],
                ['label' => 'Déodorant', 'termes' => ['déodorant', 'deodorant', 'brume']],
                ['label' => 'Maquillage', 'termes' => ['gloss', 'mascara', 'fond de teint', 'rouge à lèvres', 'lip']],
                ['label' => 'Accessoires', 'termes' => ['pinceau', 'éponge', 'miroir', 'trousse']],
            ],
            'candidate_nom_termes_only' => true,
        ],
        [
            'id' => 'cheveux',
            'fallback' => true,
            'priority' => 31,
            'bundle_example' => 'perruque + colle + bonnet',
            'keywords' => ['perruque', 'mèche', 'meche', 'tissage', 'lace', 'extension', 'closure', 'frontal'],
            'accessories' => [
                ['label' => 'Fixation', 'termes' => ['colle', 'gel', 'élastique']],
                ['label' => 'Bonnet', 'termes' => ['bonnet', 'filet', 'wig cap']],
                ['label' => 'Soin cheveux', 'termes' => ['huile', 'shampooing', 'après-shampooing', 'spray']],
                ['label' => 'Peigne', 'termes' => ['peigne', 'brosse']],
            ],
            'candidate_nom_termes_only' => true,
        ],
        [
            'id' => 'patisserie',
            'fallback' => true,
            'priority' => 32,
            'bundle_example' => 'moule + douilles + colorants + décoration',
            'keywords' => ['moule', 'douille', 'colorant', 'cake topper', 'topper', 'spatule', 'pâtisserie', 'patisserie', 'gâteau', 'gateau', 'cupcake', 'fondant'],
            'accessories' => [
                ['label' => 'Douilles', 'termes' => ['douille', 'poche']],
                ['label' => 'Colorants', 'termes' => ['colorant']],
                ['label' => 'Décoration', 'termes' => ['topper', 'papillon', 'paillette', 'perle', 'décoration']],
                ['label' => 'Ustensiles', 'termes' => ['spatule', 'fouet', 'thermomètre', 'grille', 'scraper']],
                ['label' => 'Boîtes', 'termes' => ['boite', 'boîte', 'emballage']],
            ],
            'candidate_nom_termes_only' => true,
        ],
        [
            'id' => 'cuisine',
            'fallback' => true,
            'priority' => 26,
            'bundle_example' => 'ustensile + rangement + linge de cuisine',
            'keywords' => ['marmite', 'casserole', 'poêle', 'poele', 'plat four', 'plat', 'assiette', 'verre', 'thermos', 'mixeur', 'blender', 'bouilloire'],
            'accessories' => [
                ['label' => 'Ustensiles', 'termes' => ['spatule', 'louche', 'couteau', 'cuillère', 'fouet']],
                ['label' => 'Rangement', 'termes' => ['boîte', 'boite', 'bocal', 'rangement']],
                ['label' => 'Linge de cuisine', 'termes' => ['torchon', 'gant', 'manique', 'nappe', 'tablier']],
            ],
            'candidate_nom_termes_only' => true,
        ],
        [
            'id' => 'alimentation',
            'fallback' => true,
            'priority' => 25,
            'bundle_example' => 'épicerie + boissons',
            'keywords' => ['riz', 'huile', 'farine', 'sucre', 'céréale', 'cereale', 'jus', 'bissap', 'gingembre', 'thé', 'café', 'cafe', 'lait', 'miel', 'arachide'],
            'accessories' => [
                ['label' => 'Épicerie', 'termes' => ['sucre', 'lait', 'thé', 'café', 'miel']],
                ['label' => 'Boissons', 'termes' => ['jus', 'bissap', 'gingembre', 'eau']],
                ['label' => 'Snacks', 'termes' => ['biscuit', 'céréale', 'arachide', 'gâteau']],
            ],
            'candidate_nom_termes_only' => true,
        ],
    ],
    /* Univers de la personne : après les compléments directs, d'autres produits pour elle / pour lui.
       indices = mots qui révèlent le public ; termes = ce qu'on propose ; exclure = noms à écarter. */
    'univers' => [
        'femme' => [
            'label' => 'Pour elle',
            'indices' => ['femme', 'femmes', 'dame', 'dames', 'fille', 'lady', 'woman', 'women', 'robe', 'jupe', 'escarpin',
                'talons', 'sac à main', 'maquillage', 'rouge à lèvres', 'gloss', 'perruque', 'mèche', 'lingerie',
                'soutien-gorge', 'abaya', 'legging', 'crop top', 'boucles d\'oreilles', 'vernis'],
            'termes' => ['parfum', 'maquillage', 'gloss', 'rouge à lèvres', 'lip', 'crème', 'lait corps', 'sérum', 'vernis',
                'perruque', 'mèche', 'collier', 'bracelet', 'boucle', 'bijou', 'sac à main', 'pochette', 'robe',
                'chaussures femme', 'sandale'],
            'exclure' => ['homme', 'hommes', 'garçon', 'barbe', 'moule', 'douille', 'pâtisserie'],
            /* Suggestions universelles : seulement les mots de genre (un moule reste proposable) */
            'exclure_genre' => ['homme', 'hommes', 'garçon', 'barbe'],
        ],
        'homme' => [
            'label' => 'Pour lui',
            'indices' => ['homme', 'hommes', 'monsieur', 'men', 'costume', 'cravate', 'barbe', 'boxer', 'caleçon', 'jalaba'],
            'termes' => ['parfum', 'montre', 'ceinture', 'chemise', 'polo', 'chaussures homme', 'mocassin', 'basket',
                'casquette', 'portefeuille', 'tondeuse', 'rasoir', 'sacoche'],
            'exclure' => ['femme', 'femmes', 'fille', 'dame', 'robe', 'jupe'],
            'exclure_genre' => ['femme', 'femmes', 'fille', 'dame', 'robe', 'jupe'],
        ],
    ],
    /* Suggestions universelles (sans IA) : pour tout produit, catégories qui le complètent.
       si = motifs du nom de la catégorie du produit au panier (à défaut, de son rayon) — minuscules, sans accents ;
       proposer = motifs des catégories à proposer, de la plus pertinente à la moins pertinente.
       Ensuite viennent les autres catégories du même rayon, la même boutique, puis les produits populaires. */
    /* Articles principaux (motifs de nom de catégorie) : jamais proposés en complément d'un autre achat */
    'jamais_en_complement' => ['moto', 'scooter', 'voiture'],
    'complementarite' => [
        ['si' => ['chaussure'], 'proposer' => ['sac', 'bijou', 'accessoires (', 'soins du corps', 'parfum', 'sous-vetement']],
        ['si' => ['jupe', 'chemise', 'pantalon', 'pantanon', 'jean', 'tshirt', 't-shirt', 'tenue', 'vetements de sport', 'lingerie', 'sous-vetement', 'robe', 'vetement'],
            'proposer' => ['chaussure', 'sac', 'accessoires (', 'bijou', 'parfum', 'soins du corps']],
        ['si' => ['sac', 'bagage'], 'proposer' => ['chaussure', 'bijou', 'accessoires (', 'parfum', 'maquillage']],
        ['si' => ['bijou'], 'proposer' => ['accessoires (', 'sac', 'parfum', 'maquillage', 'chaussure']],
        ['si' => ['accessoires (montres', 'montre', 'ceinture', 'lunette'], 'proposer' => ['bijou', 'sac', 'parfum', 'chaussure', 'objets connectes']],
        ['si' => ['parfum'], 'proposer' => ['soins du corps', 'hygiene', 'maquillage', 'bijou', 'soins du visage', 'accessoires (']],
        ['si' => ['maquillage'], 'proposer' => ['soins du visage', 'parfum', 'cheveux', 'soins du corps', 'bijou']],
        ['si' => ['soins du visage', 'soins du corps', 'hygiene'], 'proposer' => ['parfum', 'maquillage', 'soins du', 'hygiene', 'produits naturels', 'cheveux']],
        ['si' => ['cheveux', 'coiffure'], 'proposer' => ['soins du', 'hygiene', 'maquillage', 'accessoires (', 'bijou']],
        ['si' => ['sante', 'bien-etre', 'produits naturels', 'bio'], 'proposer' => ['produits naturels', 'fruits secs', 'cereales', 'produits locaux', 'soins du', 'hygiene']],
        ['si' => ['telephone', 'tablette'], 'proposer' => ['accessoires electroniques', 'objets connectes', 'stockage', 'tv, audio', 'audio']],
        ['si' => ['accessoires electroniques'], 'proposer' => ['objets connectes', 'stockage', 'tv, audio', 'reseaux', 'telephone']],
        ['si' => ['objets connectes'], 'proposer' => ['accessoires electroniques', 'stockage', 'telephone', 'vetements de sport']],
        ['si' => ['tv, audio', 'audio', 'video'], 'proposer' => ['accessoires electroniques', 'reseaux', 'stockage', 'objets connectes']],
        ['si' => ['reseaux', 'internet'], 'proposer' => ['accessoires electroniques', 'stockage', 'objets connectes']],
        ['si' => ['appareils photo', 'camera'], 'proposer' => ['stockage', 'accessoires electroniques', 'reseaux', 'objets connectes']],
        ['si' => ['stockage'], 'proposer' => ['accessoires electroniques', 'reseaux', 'telephone']],
        ['si' => ['moto', 'scooter', 'voiture', 'automobile'], 'proposer' => ['accessoires electroniques', 'objets connectes', 'quincaillerie', 'vetements de sport']],
        ['si' => ['meuble', 'mobilier'], 'proposer' => ['art mural', 'objets design', 'art personnalise', 'quincaillerie']],
        ['si' => ['art', 'decoration', 'objets design'], 'proposer' => ['meuble', 'art', 'objets design']],
        ['si' => ['quincaillerie', 'bricolage', 'jardin'], 'proposer' => ['meuble', 'electromenager', 'autres']],
        ['si' => ['sport', 'loisir'], 'proposer' => ['vetements de sport', 'chaussure', 'objets connectes', 'cereales', 'fruits secs']],
        ['si' => ['puericulture', 'bebe', 'enfant'], 'proposer' => ['hygiene', 'soins du corps', 'sport', 'produits naturels']],
        ['si' => ['cuisine electrique', 'refrigeration', 'electromenager'], 'proposer' => ['outils patisserie', 'moules', 'matériels cake', 'materiels cake', 'boites', 'produits comestibles', 'autres']],
        ['si' => ['moule', 'outils patisserie', 'cake', 'boites a gateau', 'topper', 'patisserie'],
            'proposer' => ['outils patisserie', 'moules', 'materiels cake', 'produits comestibles', 'cake topper', 'boites', 'cuisine electrique']],
        ['si' => ['produits comestibles'], 'proposer' => ['moules', 'outils patisserie', 'materiels cake', 'boites', 'cake topper']],
        ['si' => ['cereales', 'grains', 'fruits secs', 'produits locaux', 'nutrition'],
            'proposer' => ['produits locaux', 'fruits secs', 'cereales', 'produits naturels', 'produits comestibles', 'sante']],
    ],
    'service_livraison' => [
        'title' => 'Livraison',
        'text' => 'Choisissez votre adresse et les frais à l’étape suivante.',
        'icon' => 'fa-truck',
    ],
    'service_montage' => [
        'title' => 'Montage à domicile',
        'text' => 'Besoin d’aide pour installer ? Parcourez les services disponibles.',
        'icon' => 'fa-screwdriver-wrench',
        'search_url' => '/produits.php?recherche=montage',
    ],
];
