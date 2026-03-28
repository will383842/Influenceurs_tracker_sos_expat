<?php

namespace App\Console\Commands;

use App\Models\ContentCity;
use App\Models\ContentCountry;
use App\Models\ContentSource;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Importe les villes du monde depuis le fichier villes_monde.md
 * Sans doublons — déduplique par nom normalisé.
 */
class ImportWorldCities extends Command
{
    protected $signature   = 'cities:import-world {--dry-run : Afficher sans insérer}';
    protected $description = 'Importe les villes du monde (villes_monde.md) dans content_cities sans doublons';

    // ───────────────────────────────────────────────────────────────
    // Dataset complet — continent => [country_slug => [villes...]]
    // ───────────────────────────────────────────────────────────────
    private array $worldCities = [

        'Afrique' => [
            'afrique-du-sud'                  => ['Johannesburg', 'Le Cap', 'Durban', 'Pretoria', 'Soweto', 'Port Elizabeth'],
            'algerie'                          => ['Alger', 'Oran', 'Constantine', 'Tlemcen', 'Ghardaïa', 'Annaba'],
            'angola'                           => ['Luanda', 'Huambo', 'Lobito', 'Benguela'],
            'botswana'                         => ['Gaborone', 'Francistown', 'Molepolole', 'Maun'],
            'burkina-faso'                     => ['Ouagadougou', 'Bobo-Dioulasso', 'Koudougou'],
            'burundi'                          => ['Bujumbura', 'Gitega', 'Muyinga'],
            'benin'                            => ['Cotonou', 'Porto-Novo', 'Parakou', 'Ouidah'],
            'cap-vert'                         => ['Praia', 'Mindelo', 'Santa Maria', 'Sal'],
            'cameroun'                         => ['Douala', 'Yaoundé', 'Garoua', 'Kribi', 'Bafoussam'],
            'centrafrique'                     => ['Bangui', 'Bimbo', 'Berbérati'],
            'comores'                          => ['Moroni', 'Mutsamudu', 'Fomboni'],
            'congo'                            => ['Brazzaville', 'Pointe-Noire', 'Dolisie'],
            'republique-democratique-du-congo' => ['Kinshasa', 'Lubumbashi', 'Mbuji-Mayi', 'Kisangani', 'Goma'],
            'cote-d-ivoire'                    => ['Abidjan', 'Bouaké', 'Daloa', 'Yamoussoukro', 'Grand-Bassam', 'San Pedro'],
            'djibouti'                         => ['Djibouti', 'Ali Sabieh', 'Tadjoura'],
            'swaziland'                        => ['Mbabane', 'Manzini', 'Lobamba'],
            'gabon'                            => ['Libreville', 'Port-Gentil', 'Franceville', 'Lambaréné'],
            'gambie'                           => ['Banjul', 'Serekunda', 'Brikama'],
            'ghana'                            => ['Accra', 'Kumasi', 'Tamale', 'Cape Coast'],
            'guinee-conakry'                   => ['Conakry', 'Nzérékoré', 'Kindia'],
            'guinee-equatoriale'               => ['Malabo', 'Bata', 'Ebebiyin'],
            'guinee-bissau'                    => ['Bissau', 'Bafatá', 'Gabú'],
            'kenya'                            => ['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Malindi', 'Lamu'],
            'lesotho'                          => ['Maseru', 'Teyateyaneng', 'Mafeteng'],
            'liberia'                          => ['Monrovia', 'Gbarnga', 'Kakata'],
            'libye'                            => ['Tripoli', 'Benghazi', 'Misrata'],
            'madagascar'                       => ['Antananarivo', 'Toamasina', 'Antsirabe', 'Nosy Be', 'Morondava', 'Diego Suarez'],
            'malawi'                           => ['Lilongwe', 'Blantyre', 'Mzuzu'],
            'mali'                             => ['Bamako', 'Sikasso', 'Mopti', 'Tombouctou', 'Djenné'],
            'maroc'                            => ['Casablanca', 'Fès', 'Marrakech', 'Rabat', 'Agadir', 'Tanger', 'Essaouira', 'Meknès', 'Chefchaouen', 'Ouarzazate'],
            'ile-maurice'                      => ['Port-Louis', 'Beau Bassin', 'Vacoas', 'Grand Baie'],
            'mauritanie'                       => ['Nouakchott', 'Nouadhibou', 'Rosso', 'Chinguetti'],
            'mayotte'                          => ['Mamoudzou', 'Koungou', 'Bandraboua'],
            'mozambique'                       => ['Maputo', 'Matola', 'Nampula', 'Beira'],
            'namibie'                          => ['Windhoek', 'Rundu', 'Walvis Bay', 'Swakopmund', 'Sossusvlei'],
            'niger'                            => ['Niamey', 'Zinder', 'Maradi', 'Agadez'],
            'nigeria'                          => ['Lagos', 'Abuja', 'Kano', 'Ibadan', 'Port Harcourt'],
            'ouganda'                          => ['Kampala', 'Gulu', 'Lira', 'Jinja'],
            'la-reunion'                       => ['Saint-Denis', 'Saint-Paul', 'Saint-Pierre'],
            'rwanda'                           => ['Kigali', 'Butare', 'Gitarama'],
            'seychelles'                       => ['Victoria', 'Anse Boileau', 'Beau Vallon', 'Praslin'],
            'sierra-leone'                     => ['Freetown', 'Bo', 'Kenema'],
            'somalie'                          => ['Mogadiscio', 'Hargeisa', 'Berbera'],
            'soudan'                           => ['Khartoum', 'Omdurman', 'Port-Soudan'],
            'sud-soudan'                       => ['Djouba', 'Wau', 'Malakal'],
            'sao-tome-et-principe'             => ['São Tomé', 'Trindade', 'Santana'],
            'senegal'                          => ['Dakar', 'Thiès', 'Kaolack', 'Saint-Louis', 'Ziguinchor'],
            'tanzanie'                         => ['Dar es Salaam', 'Dodoma', 'Zanzibar', 'Arusha', 'Moshi'],
            'tchad'                            => ['N\'Djamena', 'Moundou', 'Sarh'],
            'togo'                             => ['Lomé', 'Sokodé', 'Kara', 'Kpalimé'],
            'tunisie'                          => ['Tunis', 'Sfax', 'Sousse', 'Djerba', 'Hammamet', 'Monastir', 'Carthage', 'Sidi Bou Said'],
            'zambie'                           => ['Lusaka', 'Kitwe', 'Ndola', 'Livingstone'],
            'zimbabwe'                         => ['Harare', 'Bulawayo', 'Chitungwiza', 'Victoria Falls'],
            'egypte'                           => ['Le Caire', 'Alexandrie', 'Gizeh', 'Louxor', 'Assouan', 'Hurghada', 'Charm el-Cheikh', 'Sharm el-Sheikh'],
            'erythree'                         => ['Asmara', 'Keren', 'Massawa'],
            'ethiopie'                         => ['Addis-Abeba', 'Dire Dawa', 'Gondar', 'Lalibela', 'Aksoum'],
        ],

        'Amerique du Nord' => [
            'canada'    => ['Toronto', 'Montréal', 'Vancouver', 'Québec', 'Ottawa', 'Calgary', 'Edmonton', 'Winnipeg', 'Halifax'],
            'etats-unis' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Miami', 'San Francisco', 'Las Vegas', 'Boston', 'Seattle', 'Washington', 'La Nouvelle-Orléans', 'Atlanta', 'Denver', 'Orlando', 'Dallas'],
            'mexique'   => ['Mexico City', 'Guadalajara', 'Monterrey', 'Cancún', 'Oaxaca', 'Puebla', 'Mérida', 'San Cristóbal de las Casas'],
        ],

        'Amerique Centrale' => [
            'antigua-et-barbuda'               => ['Saint John\'s', 'All Saints', 'Liberta'],
            'aruba'                            => ['Oranjestad', 'San Nicolas', 'Noord'],
            'bahamas'                          => ['Nassau', 'Lucaya', 'Freeport'],
            'barbade'                          => ['Bridgetown', 'Speightstown', 'Oistins'],
            'belize'                           => ['Belmopan', 'Belize City', 'San Ignacio', 'Placencia'],
            'bermudes'                         => ['Hamilton', 'Saint George\'s', 'Somerset'],
            'costa-rica'                       => ['San José', 'Cartago', 'Heredia', 'Manuel Antonio', 'Monteverde'],
            'cuba'                             => ['La Havane', 'Santiago de Cuba', 'Camagüey', 'Trinidad', 'Varadero', 'Viñales'],
            'dominique'                        => ['Roseau', 'Portsmouth', 'Marigot'],
            'salvador'                         => ['San Salvador', 'Soyapango', 'Santa Ana', 'El Tunco'],
            'grenade'                          => ['Saint-Georges', 'Gouyave', 'Grenville'],
            'guatemala'                        => ['Guatemala City', 'Mixco', 'Villa Nueva', 'Antigua', 'Tikal'],
            'haiti'                            => ['Port-au-Prince', 'Cap-Haïtien', 'Delmas'],
            'honduras'                         => ['Tegucigalpa', 'San Pedro Sula', 'Choloma', 'Copán', 'Roatán'],
            'iles-caimans'                     => ['George Town', 'West Bay', 'Bodden Town', 'Seven Mile Beach'],
            'jamaique'                         => ['Kingston', 'Montego Bay', 'Portmore', 'Negril', 'Ocho Rios'],
            'martinique'                       => ['Fort-de-France', 'Le Lamentin', 'Le Robert', 'Saint-Pierre'],
            'nicaragua'                        => ['Managua', 'León', 'Masaya', 'Granada'],
            'panama'                           => ['Panama City', 'San Miguelito', 'Tocumen', 'Bocas del Toro', 'Boquete'],
            'porto-rico'                       => ['San Juan', 'Bayamon', 'Carolina', 'Ponce', 'Vieques'],
            'republique-dominicaine'           => ['Saint-Domingue', 'Santiago', 'La Vega', 'Punta Cana'],
            'saint-christophe-et-nieves'       => ['Basseterre', 'Charlestown', 'Sandy Point'],
            'saint-martin'                     => ['Marigot', 'Grand Case', 'Orleans'],
            'saint-vincent-et-les-grenadines'  => ['Kingstown', 'Georgetown', 'Barrouallie'],
            'sainte-lucie'                     => ['Castries', 'Gros Islet', 'Soufrière'],
            'sint-maarten'                     => ['Philipsburg', 'Simpson Bay', 'Cole Bay'],
            'trinite-et-tobago'                => ['Port d\'Espagne', 'Chaguanas', 'San Fernando', 'Tobago'],
        ],

        'Amerique du Sud' => [
            'argentine'  => ['Buenos Aires', 'Córdoba', 'Rosario', 'Mendoza', 'Patagonie', 'Ushuaia', 'Bariloche'],
            'bolivie'    => ['Santa Cruz', 'La Paz', 'Cochabamba', 'Sucre', 'Salar d\'Uyuni', 'Potosí'],
            'bresil'     => ['São Paulo', 'Rio de Janeiro', 'Brasília', 'Manaus', 'Salvador', 'Fortaleza', 'Recife', 'Florianópolis', 'Foz do Iguaçu'],
            'chili'      => ['Santiago', 'Valparaíso', 'Concepción', 'San Pedro de Atacama', 'Torres del Paine', 'Puerto Natales'],
            'colombie'   => ['Bogotá', 'Medellín', 'Cali', 'Cartagena', 'Santa Marta', 'Barranquilla'],
            'equateur'   => ['Guayaquil', 'Quito', 'Cuenca', 'Galápagos'],
            'guyana'     => ['Georgetown', 'Linden', 'New Amsterdam'],
            'paraguay'   => ['Asunción', 'Ciudad del Este', 'San Lorenzo', 'Encarnación'],
            'perou'      => ['Lima', 'Arequipa', 'Trujillo', 'Cuzco', 'Machu Picchu', 'Lac Titicaca', 'Iquitos'],
            'suriname'   => ['Paramaribo', 'Lelydorp', 'Nieuw Nickerie'],
            'uruguay'    => ['Montevideo', 'Salto', 'Paysandú', 'Punta del Este', 'Colonia del Sacramento'],
            'venezuela'  => ['Caracas', 'Maracaibo', 'Maracay', 'Salto Angel', 'Île Margarita'],
        ],

        'Asie' => [
            'afghanistan'        => ['Kaboul', 'Kandahar', 'Herat', 'Bamiyan', 'Jalalabad'],
            'arabie-saoudite'    => ['Riyad', 'Djeddah', 'La Mecque', 'Médine', 'Dammam', 'Taïf'],
            'armenie'            => ['Erevan', 'Gyumri', 'Vanadzor', 'Vagharshapat'],
            'azerbaidjan'        => ['Bakou', 'Ganja', 'Sumqayit', 'Sheki'],
            'bahrein'            => ['Manama', 'Riffa', 'Muharraq'],
            'bangladesh'         => ['Dacca', 'Chittagong', 'Khulna', 'Cox\'s Bazar'],
            'bhoutan'            => ['Thimphou', 'Phuntsholing', 'Paro'],
            'birmanie'           => ['Rangoun', 'Mandalay', 'Naypyidaw', 'Bagan', 'Inle'],
            'brunei'             => ['Bandar Seri Begawan', 'Kuala Belait', 'Seria'],
            'cambodge'           => ['Phnom Penh', 'Siem Reap', 'Battambang', 'Angkor'],
            'chine'              => ['Shanghai', 'Pékin', 'Chongqing', 'Guangzhou', 'Xi\'an', 'Guilin', 'Hangzhou', 'Chengdu', 'Kunming'],
            'coree-du-nord'      => ['Pyongyang', 'Hamhung', 'Chongjin'],
            'coree-du-sud'       => ['Séoul', 'Busan', 'Incheon', 'Jeju', 'Gyeongju', 'Daegu'],
            'georgie'            => ['Tbilissi', 'Koutaïssi', 'Batoumi', 'Mtskheta', 'Kazbegi'],
            'hong-kong'          => ['Hong Kong', 'Kowloon', 'Lantau'],
            'inde'               => ['Mumbai', 'Delhi', 'Bangalore', 'Agra', 'Jaipur', 'Varanasi', 'Goa', 'Chennai', 'Hyderabad', 'Kolkata', 'Pondichéry'],
            'indonesie'          => ['Jakarta', 'Surabaya', 'Bandung', 'Bali', 'Yogyakarta', 'Lombok', 'Komodo', 'Manado'],
            'irak'               => ['Bagdad', 'Bassora', 'Mossoul', 'Erbil', 'Najaf'],
            'iran'               => ['Téhéran', 'Mashhad', 'Ispahan', 'Shiraz', 'Tabriz', 'Persépolis'],
            'israel'             => ['Jérusalem', 'Tel Aviv', 'Haïfa', 'Bethléem', 'Nazareth', 'Eilat'],
            'japon'              => ['Tokyo', 'Yokohama', 'Osaka', 'Kyoto', 'Hiroshima', 'Nara', 'Sapporo', 'Fukuoka', 'Hokkaido'],
            'jordanie'           => ['Amman', 'Zarqa', 'Irbid', 'Pétra', 'Wadi Rum', 'Aqaba'],
            'kazakhstan'         => ['Almaty', 'Astana', 'Shymkent'],
            'kirghizstan'        => ['Bichkek', 'Och', 'Jalal-Abad', 'Issyk-Koul'],
            'koweit'             => ['Koweït City', 'Sabah Al-Salem', 'Hawalli'],
            'laos'               => ['Vientiane', 'Savannakhet', 'Pakse', 'Luang Prabang'],
            'liban'              => ['Beyrouth', 'Tripoli', 'Sidon', 'Byblos', 'Baalbek'],
            'macao'              => ['Macao', 'Taipa', 'Coloane', 'Cotai'],
            'malaisie'           => ['Kuala Lumpur', 'George Town', 'Ipoh', 'Penang', 'Langkawi', 'Bornéo', 'Kota Kinabalu'],
            'maldives'           => ['Malé', 'Addu City', 'Fuvahmulah'],
            'mongolie'           => ['Oulan-Bator', 'Erdenet', 'Darkhan'],
            'nepal'              => ['Katmandou', 'Pokhara', 'Lalitpur', 'Lumbini'],
            'oman'               => ['Mascate', 'Salalah', 'Sohar', 'Nizwa'],
            'ouzbekistan'        => ['Tachkent', 'Samarcande', 'Namangan', 'Boukhara'],
            'pakistan'           => ['Karachi', 'Lahore', 'Faisalabad', 'Islamabad', 'Peshawar'],
            'philippines'        => ['Quezon City', 'Manille', 'Davao', 'Palawan', 'Boracay', 'Cebu', 'Bohol'],
            'qatar'              => ['Doha', 'Al Wakrah', 'Al Khor'],
            'singapour'          => ['Singapour', 'Jurong East', 'Tampines'],
            'sri-lanka'          => ['Colombo', 'Dehiwala', 'Moratuwa', 'Kandy', 'Sigiriya', 'Galle'],
            'syrie'              => ['Damas', 'Alep', 'Homs', 'Palmyre'],
            'tadjikistan'        => ['Douchanbé', 'Khodjent', 'Kulob'],
            'taiwan'             => ['Taipei', 'Kaohsiung', 'Taichung', 'Jiufen', 'Taroko'],
            'thailande'          => ['Bangkok', 'Chiang Mai', 'Phuket', 'Koh Samui', 'Ayutthaya', 'Chiang Rai', 'Hua Hin', 'Koh Phangan', 'Krabi'],
            'timor-oriental'     => ['Dili', 'Dare', 'Maliana'],
            'turkmenistan'       => ['Achgabat', 'Türkmenabat', 'Dasgoguz', 'Merv'],
            'turquie'            => ['Istanbul', 'Ankara', 'Izmir', 'Antalya', 'Bodrum', 'Pamukkale', 'Cappadoce', 'Éphèse', 'Trabzon'],
            'vietnam'            => ['Ho Chi Minh-Ville', 'Hanoi', 'Haiphong', 'Hoi An', 'Ha Long', 'Hue', 'Da Nang', 'Nha Trang'],
            'yemen'              => ['Sanaa', 'Aden', 'Taiz'],
            'emirats-arabes-unis' => ['Dubaï', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras al-Khaimah'],
        ],

        'Europe' => [
            'albanie'            => ['Tirana', 'Durrës', 'Vlorë', 'Berat', 'Gjirokastër'],
            'allemagne'          => ['Berlin', 'Hambourg', 'Munich', 'Cologne', 'Francfort', 'Stuttgart', 'Düsseldorf', 'Heidelberg', 'Dresde', 'Leipzig'],
            'andorre'            => ['Andorre-la-Vieille', 'Escaldes-Engordany', 'Encamp'],
            'autriche'           => ['Vienne', 'Graz', 'Linz', 'Salzbourg', 'Innsbruck'],
            'belgique'           => ['Bruxelles', 'Anvers', 'Gand', 'Bruges', 'Liège', 'Namur'],
            'bielorussie'        => ['Minsk', 'Homiel', 'Vitebsk', 'Brest', 'Grodno'],
            'bosnie-herzegovine' => ['Sarajevo', 'Banja Luka', 'Mostar', 'Tuzla'],
            'bulgarie'           => ['Sofia', 'Plovdiv', 'Varna', 'Nesebar', 'Burgas'],
            'chypre'             => ['Nicosie', 'Limassol', 'Larnaca', 'Paphos'],
            'croatie'            => ['Zagreb', 'Split', 'Rijeka', 'Dubrovnik', 'Plitvice', 'Zadar', 'Hvar'],
            'danemark'           => ['Copenhague', 'Aarhus', 'Odense', 'Helsingør'],
            'espagne'            => ['Madrid', 'Barcelone', 'Valence', 'Séville', 'Grenade', 'Bilbao', 'Tolède', 'Malaga', 'Salamanque', 'Cordoue'],
            'estonie'            => ['Tallinn', 'Tartu', 'Narva'],
            'finlande'           => ['Helsinki', 'Espoo', 'Tampere', 'Rovaniemi', 'Turku'],
            'france'             => ['Paris', 'Marseille', 'Lyon', 'Nice', 'Bordeaux', 'Toulouse', 'Strasbourg', 'Nantes', 'Montpellier', 'Lille', 'Rennes', 'Aix-en-Provence', 'Mont-Saint-Michel'],
            'grece'              => ['Athènes', 'Thessalonique', 'Le Pirée', 'Santorin', 'Mykonos', 'Rhodes', 'Corfou', 'Héraklion'],
            'hongrie'            => ['Budapest', 'Debrecen', 'Miskolc', 'Eger', 'Pécs'],
            'irlande'            => ['Dublin', 'Cork', 'Limerick', 'Galway', 'Killarney', 'Dingle'],
            'islande'            => ['Reykjavik', 'Kópavogur', 'Hafnarfjörður', 'Akureyri'],
            'italie'             => ['Rome', 'Milan', 'Naples', 'Turin', 'Florence', 'Venise', 'Bologne', 'Amalfi', 'Cinque Terre', 'Palerme', 'Sorrente', 'Capri'],
            'kosovo'             => ['Pristina', 'Prizren', 'Peja'],
            'lettonie'           => ['Riga', 'Daugavpils', 'Liepāja', 'Jūrmala'],
            'liechtenstein'      => ['Vaduz', 'Schaan', 'Balzers'],
            'lituanie'           => ['Vilnius', 'Kaunas', 'Klaipėda', 'Trakai'],
            'luxembourg'         => ['Luxembourg', 'Esch-sur-Alzette', 'Differdange', 'Vianden'],
            'macedoine'          => ['Skopje', 'Bitola', 'Kumanovo', 'Ohrid'],
            'malte'              => ['La Valette', 'Birkirkara', 'Mosta', 'Mdina', 'Gozo', 'Sliema'],
            'moldavie'           => ['Chișinău', 'Tiraspol', 'Bălți'],
            'monaco'             => ['Monaco', 'Monte-Carlo', 'La Condamine'],
            'montenegro'         => ['Podgorica', 'Nikšić', 'Bijelo Polje', 'Kotor', 'Budva'],
            'norvege'            => ['Oslo', 'Bergen', 'Trondheim', 'Stavanger', 'Flåm', 'Tromsø'],
            'pays-bas'           => ['Amsterdam', 'Rotterdam', 'La Haye', 'Utrecht', 'Eindhoven', 'Delft'],
            'pologne'            => ['Varsovie', 'Cracovie', 'Łódź', 'Gdansk', 'Wrocław', 'Poznań'],
            'portugal'           => ['Lisbonne', 'Porto', 'Braga', 'Sintra', 'Faro', 'Algarve', 'Madère', 'Açores', 'Évora'],
            'roumanie'           => ['Bucarest', 'Cluj-Napoca', 'Timișoara', 'Brasov', 'Sinaia', 'Sibiu'],
            'angleterre'         => ['Londres', 'Birmingham', 'Manchester', 'Liverpool', 'Leeds', 'Bristol', 'Édimbourg', 'Oxford', 'Cambridge', 'Bath', 'York'],
            'russie'             => ['Moscou', 'Saint-Pétersbourg', 'Novossibirsk', 'Kazan', 'Sotchi', 'Vladivostok', 'Irkoutsk'],
            'serbie'             => ['Belgrade', 'Novi Sad', 'Niš'],
            'slovaquie'          => ['Bratislava', 'Košice', 'Prešov', 'Banská Štiavnica'],
            'slovenie'           => ['Ljubljana', 'Maribor', 'Celje', 'Bled', 'Piran'],
            'suede'              => ['Stockholm', 'Göteborg', 'Malmö', 'Uppsala', 'Abisko'],
            'suisse'             => ['Zurich', 'Genève', 'Berne', 'Bâle', 'Lausanne', 'Interlaken', 'Lucerne', 'Zermatt', 'Saint-Moritz'],
            'republique-tcheque' => ['Prague', 'Brno', 'Ostrava', 'Plzeň', 'Cesky Krumlov'],
            'ukraine'            => ['Kiev', 'Kharkiv', 'Odessa', 'Lviv', 'Dnipro'],
            'vatican'            => ['Vatican'],
        ],

        'Oceanie' => [
            'australie'                  => ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide', 'Gold Coast', 'Cairns', 'Darwin', 'Canberra'],
            'iles-fidji'                 => ['Suva', 'Lautoka', 'Nadi'],
            'iles-cooks'                 => ['Avarua', 'Arorangi', 'Nikao', 'Rarotonga'],
            'kiribati'                   => ['Tarawa', 'Betio', 'Bikenibeu'],
            'micronesie'                 => ['Palikir', 'Weno', 'Kolonia'],
            'nauru'                      => ['Yaren', 'Anabar', 'Anetan'],
            'nouvelle-caledonie'         => ['Nouméa', 'Mont-Dore', 'Dumbéa', 'Bourail'],
            'nouvelle-zelande'           => ['Auckland', 'Wellington', 'Christchurch', 'Queenstown', 'Rotorua', 'Hobbiton', 'Wanaka'],
            'palaos'                     => ['Ngerulmud', 'Koror', 'Kloulklubed'],
            'papouasie-nouvelle-guinee'  => ['Port Moresby', 'Lae', 'Arawa'],
            'polynesie-francaise'        => ['Papeete', 'Punaauia', 'Pirae', 'Bora-Bora', 'Moorea'],
            'samoa'                      => ['Apia', 'Salelologa', 'Asau'],
            'tonga'                      => ['Nuku\'alofa', 'Neiafu', 'Pangai'],
            'tuvalu'                     => ['Funafuti', 'Savave', 'Tanrake'],
            'vanuatu'                    => ['Port-Vila', 'Luganville', 'Norsup'],
            'iles-marshall'              => ['Majuro', 'Ebeye', 'Jaluit'],
            'iles-salomon'               => ['Honiara', 'Gizo', 'Auki'],
        ],
    ];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $source = ContentSource::where('slug', 'expat-com')->first();
        if (!$source) {
            $this->error('Source Expat.com introuvable');
            return 1;
        }

        // Charger tous les pays une seule fois (slug => id)
        $countriesBySlug = ContentCountry::where('source_id', $source->id)
            ->pluck('id', 'slug')
            ->toArray();

        // Charger toutes les villes existantes normalisées par pays
        // Format: [country_id => [normalized_name => true]]
        $existingCities = [];
        ContentCity::where('source_id', $source->id)
            ->get(['country_id', 'name', 'slug'])
            ->each(function ($city) use (&$existingCities) {
                $existingCities[$city->country_id][$this->normalize($city->name)] = true;
                $existingCities[$city->country_id][$this->normalize($city->slug)] = true;
            });

        $inserted   = 0;
        $skipped    = 0;
        $noCountry  = 0;
        $toInsert   = [];

        foreach ($this->worldCities as $continent => $countries) {
            foreach ($countries as $countrySlug => $cities) {
                $countryId = $countriesBySlug[$countrySlug] ?? null;

                if (!$countryId) {
                    $this->line("  <comment>Pays non trouvé: {$countrySlug}</comment>");
                    $noCountry += count($cities);
                    continue;
                }

                foreach ($cities as $cityName) {
                    $normalized = $this->normalize($cityName);

                    if (isset($existingCities[$countryId][$normalized])) {
                        $skipped++;
                        continue;
                    }

                    $slug = Str::slug($cityName) ?: $normalized;

                    // Vérifier unicité du slug dans ce pays
                    $finalSlug = $slug;
                    $i = 2;
                    while (
                        isset($toInsert[$countryId . '-' . $finalSlug]) ||
                        ContentCity::where('source_id', $source->id)
                            ->where('country_id', $countryId)
                            ->where('slug', $finalSlug)
                            ->exists()
                    ) {
                        $finalSlug = $slug . '-' . $i++;
                    }

                    $country = ContentCountry::find($countryId);
                    $guideUrl = rtrim($country->guide_url, '/') . '/' . $finalSlug . '/';

                    $toInsert[$countryId . '-' . $finalSlug] = [
                        'source_id'  => $source->id,
                        'country_id' => $countryId,
                        'name'       => $cityName,
                        'slug'       => $finalSlug,
                        'continent'  => $continent,
                        'guide_url'  => $guideUrl,
                        'articles_count' => 0,
                        'scraped_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $existingCities[$countryId][$normalized] = true;
                    $inserted++;
                }
            }
        }

        $this->info("Bilan:");
        $this->line("  Villes a insérer : {$inserted}");
        $this->line("  Doublons ignorés : {$skipped}");
        $this->line("  Sans pays DB     : {$noCountry}");

        if ($isDryRun) {
            $this->warn('Mode dry-run — rien inséré.');
            if ($inserted > 0) {
                $this->line('Exemples:');
                foreach (array_slice($toInsert, 0, 10) as $row) {
                    $this->line("  [{$row['continent']}] {$row['name']} ({$row['slug']})");
                }
            }
            return 0;
        }

        if (empty($toInsert)) {
            $this->info('Aucune nouvelle ville à insérer.');
            return 0;
        }

        // Insérer par chunks de 100
        $chunks = array_chunk(array_values($toInsert), 100);
        foreach ($chunks as $chunk) {
            ContentCity::insert($chunk);
        }

        $this->info("✓ {$inserted} villes insérées avec succès.");
        return 0;
    }

    /**
     * Normalise un nom de ville pour la comparaison (sans accents, lowercase, sans tirets).
     */
    private function normalize(string $name): string
    {
        $name = mb_strtolower($name);
        // Supprimer accents
        if (function_exists('transliterator_transliterate')) {
            $name = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $name);
        } else {
            $map = [
                'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a',
                'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
                'î'=>'i','ï'=>'i','í'=>'i','ì'=>'i',
                'ô'=>'o','ö'=>'o','ó'=>'o','ò'=>'o','õ'=>'o',
                'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
                'ý'=>'y','ÿ'=>'y',
                'ñ'=>'n','ç'=>'c','ß'=>'ss',
                'ø'=>'o','å'=>'a','æ'=>'ae','œ'=>'oe',
                'ı'=>'i','ğ'=>'g','ş'=>'s','ž'=>'z','č'=>'c','š'=>'s',
                'ā'=>'a','ē'=>'e','ī'=>'i','ō'=>'o','ū'=>'u',
                'ł'=>'l','ń'=>'n','ś'=>'s','ź'=>'z','ż'=>'z',
                'ĺ'=>'l','ľ'=>'l','ŕ'=>'r','ť'=>'t','ď'=>'d',
            ];
            $name = strtr($name, $map);
        }
        // Supprimer tout sauf lettres et chiffres
        $name = preg_replace('/[^a-z0-9]/', '', $name);
        return $name;
    }
}
