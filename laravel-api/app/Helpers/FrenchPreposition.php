<?php

namespace App\Helpers;

/**
 * Gère les prépositions françaises pour les noms de pays.
 * Règles : en (féminin/voyelle), au (masculin/consonne), aux (pluriel), à (ville/micro-état).
 */
class FrenchPreposition
{
    // Pays masculins commençant par consonne → "au"
    private const AU = [
        'Bahreïn','Bangladesh','Belize','Bhoutan','Botswana','Bresil','Brunei',
        'Burkina Faso','Burundi','Cambodge','Cameroun','Canada','Cap-Vert',
        'Centrafrique','Chili','Congo','Costa Rica','Danemark','El Salvador',
        'Gabon','Ghana','Guatemala','Guyana','Honduras','Japon','Kenya',
        'Kirghizistan','Kosovo','Koweit','Laos','Lesotho','Liban','Liberia',
        'Liechtenstein','Luxembourg','Malawi','Mali','Maroc','Mexique',
        'Montenegro','Mozambique','Myanmar','Nepal','Nicaragua','Niger',
        'Nigeria','Oman','Ouganda','Pakistan','Panama','Paraguay','Perou',
        'Portugal','Qatar','Rwanda','Royaume-Uni','Salvador','Senegal',
        'Soudan','Sri Lanka','Suriname','Swaziland','Tadjikistan','Tchad',
        'Timor-Leste','Togo','Turkmenistan','Venezuela','Vietnam','Yemen',
        'Zimbabwe',
        // Variantes orthographiques
        'Brésil','Népal','Sénégal','Pérou','Yémen','Québec',
    ];

    // Pays pluriels → "aux"
    private const AUX = [
        'Bahamas','Barbades','Bermudes','Comores','Emirats Arabes Unis',
        'Emirats arabes unis','États-Unis','Etats-Unis','Fidji','Iles Caïmans',
        'Iles Cook','Iles Féroé','Iles Mariannes du Nord','Iles Marshall',
        'Iles Salomon','Iles Turques et Caïques','Iles Vierges',
        'Iles vierges britanniques','Maldives','Palaos','Pays-Bas',
        'Philippines','Samoa','Seychelles','Tonga','Tuvalu',
    ];

    // Villes et micro-états → "à"
    private const A = [
        'Andorre','Aruba','Bahreïn','Brunei','Chypre','Cuba','Curaçao',
        'Djibouti','Dominique','Gibraltar','Grenade','Guam','Guernesey',
        'Hong Kong','Hong-Kong','Jersey','Macao','Malte','Maurice',
        'Mayotte','Monaco','Nauru','Oman','Saint-Marin','Singapour',
        'Taïwan','Taiwan','Trinite et Tobago','Trinité-et-Tobago',
    ];

    /**
     * Retourne "en France", "au Portugal", "aux États-Unis", "à Singapour".
     */
    public static function prep(string $pays): string
    {
        $pays = trim($pays);

        // Check "aux" first (pluriels)
        foreach (self::AUX as $p) {
            if (strcasecmp($pays, $p) === 0) return "aux {$pays}";
        }

        // Check "à" (villes/micro-états)
        foreach (self::A as $p) {
            if (strcasecmp($pays, $p) === 0) return "a {$pays}";
        }

        // Check "au" (masculins/consonne)
        foreach (self::AU as $p) {
            if (strcasecmp($pays, $p) === 0) return "au {$pays}";
        }

        // Default: "en" (féminin ou commençant par voyelle)
        return "en {$pays}";
    }

    /**
     * Remplace {prep_pays} ou {en_pays} dans un template par la bonne préposition.
     * Remplace aussi {pays} simple par le nom du pays sans préposition.
     */
    public static function replace(string $template, string $pays): string
    {
        $prepPays = self::prep($pays);
        $result = str_replace(
            ['{prep_pays}', '{en_pays}', '{pays}'],
            [$prepPays, $prepPays, $pays],
            $template
        );
        return $result;
    }
}
