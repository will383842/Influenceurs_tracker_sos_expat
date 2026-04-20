<?php

namespace Database\Seeders;

use App\Models\RssBlogFeed;
use Illuminate\Database\Seeder;

/**
 * Option D — P9 : Seeder initial de feeds RSS blogs.
 *
 * Stratégie :
 * - Liste curée de ~80 feeds FR expat/voyage/lifestyle
 * - active=true par défaut (le premier run du cron révèlera les URLs mortes
 *   via last_error / absence de last_contacts_found)
 * - updateOrCreate idempotent (rerun safe, pas de duplication)
 * - fetch_about=true par défaut (extraction email depuis homepage 1×/7j)
 * - fetch_pattern_inference=false par défaut (évite faux emails bounce)
 *
 * Pour ajouter de nouveaux feeds : éditer ce fichier OU utiliser
 * l'UI admin /admin/rss-blog-feeds.
 */
class RssBlogFeedSeeder extends Seeder
{
    public function run(): void
    {
        $feeds = $this->feedsData();

        foreach ($feeds as $data) {
            RssBlogFeed::updateOrCreate(
                ['url' => $data['url']],
                array_merge([
                    'language'                => 'fr',
                    'active'                  => true,
                    'fetch_about'             => true,
                    'fetch_pattern_inference' => false,
                    'fetch_interval_hours'    => 6,
                ], $data)
            );
        }

        $this->command->info(sprintf(
            'RssBlogFeedSeeder: %d feeds seeded (total en base : %d).',
            count($feeds),
            RssBlogFeed::count()
        ));
    }

    /** @return array<int,array<string,mixed>> */
    private function feedsData(): array
    {
        return [
            // ── EXPAT FRANCOPHONE : Le Petit Journal (30+ éditions pays) ──
            ['name' => 'Le Petit Journal - Actualité',      'url' => 'https://lepetitjournal.com/rss.xml',                 'base_url' => 'https://lepetitjournal.com',                  'category' => 'expat',        'country' => null],
            ['name' => 'Le Petit Journal - Paris',           'url' => 'https://lepetitjournal.com/paris/feed',              'base_url' => 'https://lepetitjournal.com/paris',            'category' => 'expat',        'country' => 'France'],
            ['name' => 'Le Petit Journal - New York',        'url' => 'https://lepetitjournal.com/new-york/feed',           'base_url' => 'https://lepetitjournal.com/new-york',         'category' => 'expat',        'country' => 'États-Unis'],
            ['name' => 'Le Petit Journal - Londres',         'url' => 'https://lepetitjournal.com/londres/feed',            'base_url' => 'https://lepetitjournal.com/londres',          'category' => 'expat',        'country' => 'Royaume-Uni'],
            ['name' => 'Le Petit Journal - Bruxelles',       'url' => 'https://lepetitjournal.com/bruxelles/feed',          'base_url' => 'https://lepetitjournal.com/bruxelles',        'category' => 'expat',        'country' => 'Belgique'],
            ['name' => 'Le Petit Journal - Berlin',          'url' => 'https://lepetitjournal.com/berlin/feed',             'base_url' => 'https://lepetitjournal.com/berlin',           'category' => 'expat',        'country' => 'Allemagne'],
            ['name' => 'Le Petit Journal - Madrid',          'url' => 'https://lepetitjournal.com/madrid/feed',             'base_url' => 'https://lepetitjournal.com/madrid',           'category' => 'expat',        'country' => 'Espagne'],
            ['name' => 'Le Petit Journal - Barcelone',       'url' => 'https://lepetitjournal.com/barcelone/feed',          'base_url' => 'https://lepetitjournal.com/barcelone',        'category' => 'expat',        'country' => 'Espagne'],
            ['name' => 'Le Petit Journal - Lisbonne',        'url' => 'https://lepetitjournal.com/lisbonne/feed',           'base_url' => 'https://lepetitjournal.com/lisbonne',         'category' => 'expat',        'country' => 'Portugal'],
            ['name' => 'Le Petit Journal - Rome',            'url' => 'https://lepetitjournal.com/rome/feed',               'base_url' => 'https://lepetitjournal.com/rome',             'category' => 'expat',        'country' => 'Italie'],
            ['name' => 'Le Petit Journal - Dubaï',           'url' => 'https://lepetitjournal.com/dubai/feed',              'base_url' => 'https://lepetitjournal.com/dubai',            'category' => 'expat',        'country' => 'Émirats arabes unis'],
            ['name' => 'Le Petit Journal - Singapour',       'url' => 'https://lepetitjournal.com/singapour/feed',          'base_url' => 'https://lepetitjournal.com/singapour',        'category' => 'expat',        'country' => 'Singapour'],
            ['name' => 'Le Petit Journal - Bangkok',         'url' => 'https://lepetitjournal.com/bangkok/feed',            'base_url' => 'https://lepetitjournal.com/bangkok',          'category' => 'expat',        'country' => 'Thaïlande'],
            ['name' => 'Le Petit Journal - Tokyo',           'url' => 'https://lepetitjournal.com/tokyo/feed',              'base_url' => 'https://lepetitjournal.com/tokyo',            'category' => 'expat',        'country' => 'Japon'],
            ['name' => 'Le Petit Journal - Shanghai',        'url' => 'https://lepetitjournal.com/shanghai/feed',           'base_url' => 'https://lepetitjournal.com/shanghai',         'category' => 'expat',        'country' => 'Chine'],
            ['name' => 'Le Petit Journal - Hong Kong',       'url' => 'https://lepetitjournal.com/hong-kong/feed',          'base_url' => 'https://lepetitjournal.com/hong-kong',        'category' => 'expat',        'country' => 'Hong Kong'],
            ['name' => 'Le Petit Journal - Sydney',          'url' => 'https://lepetitjournal.com/sydney/feed',             'base_url' => 'https://lepetitjournal.com/sydney',           'category' => 'expat',        'country' => 'Australie'],
            ['name' => 'Le Petit Journal - Montréal',        'url' => 'https://lepetitjournal.com/montreal/feed',           'base_url' => 'https://lepetitjournal.com/montreal',         'category' => 'expat',        'country' => 'Canada'],
            ['name' => 'Le Petit Journal - Ho Chi Minh',     'url' => 'https://lepetitjournal.com/hochiminhville/feed',     'base_url' => 'https://lepetitjournal.com/hochiminhville',   'category' => 'expat',        'country' => 'Vietnam'],
            ['name' => 'Le Petit Journal - Lausanne',        'url' => 'https://lepetitjournal.com/lausanne/feed',           'base_url' => 'https://lepetitjournal.com/lausanne',         'category' => 'expat',        'country' => 'Suisse'],

            // ── EXPAT FEMMES / LIFESTYLE ──
            ['name' => 'Expatclic',                          'url' => 'https://www.expatclic.com/feed/',                    'base_url' => 'https://www.expatclic.com',                   'category' => 'expat_femmes', 'country' => null],
            ['name' => 'FemmExpat',                          'url' => 'https://www.femmexpat.com/feed/',                    'base_url' => 'https://www.femmexpat.com',                   'category' => 'expat_femmes', 'country' => null],
            ['name' => 'Femmes Expat',                       'url' => 'https://femmes-expat.com/feed/',                     'base_url' => 'https://femmes-expat.com',                    'category' => 'expat_femmes', 'country' => null],

            // ── FRENCH MORNING (USA éditions) ──
            ['name' => 'French Morning New York',            'url' => 'https://frenchmorning.com/feed',                     'base_url' => 'https://frenchmorning.com',                   'category' => 'expat',        'country' => 'États-Unis'],
            ['name' => 'French Morning Miami',               'url' => 'https://frenchmorningmiami.com/feed',                'base_url' => 'https://frenchmorningmiami.com',              'category' => 'expat',        'country' => 'États-Unis'],
            ['name' => 'French Morning Los Angeles',         'url' => 'https://frenchmorninglosangeles.com/feed',           'base_url' => 'https://frenchmorninglosangeles.com',         'category' => 'expat',        'country' => 'États-Unis'],
            ['name' => 'French Morning Londres',             'url' => 'https://frenchmorninglondon.com/feed',               'base_url' => 'https://frenchmorninglondon.com',             'category' => 'expat',        'country' => 'Royaume-Uni'],

            // ── FRENCH DISTRICT / ANNUAIRES ──
            ['name' => 'French District',                    'url' => 'https://frenchdistrict.com/feed',                    'base_url' => 'https://frenchdistrict.com',                  'category' => 'expat',        'country' => 'États-Unis'],
            ['name' => 'Français à l\'étranger',             'url' => 'https://www.francaisaletranger.fr/feed/',            'base_url' => 'https://www.francaisaletranger.fr',           'category' => 'expat',        'country' => null],
            ['name' => 'Les Français',                        'url' => 'https://lesfrancais.press/feed/',                    'base_url' => 'https://lesfrancais.press',                   'category' => 'expat',        'country' => null],

            // ── EXPAT GÉNÉRIQUE ──
            ['name' => 'Monde des Expats',                   'url' => 'https://mondedesexpats.com/feed/',                   'base_url' => 'https://mondedesexpats.com',                  'category' => 'expat',        'country' => null],
            ['name' => 'Expat.com Blog',                     'url' => 'https://www.expat.com/fr/expat-mag/rss',             'base_url' => 'https://www.expat.com',                       'category' => 'expat',        'country' => null],
            ['name' => 'Expat Finder',                        'url' => 'https://www.expatfinder.com/blog/feed/',             'base_url' => 'https://www.expatfinder.com',                 'category' => 'expat',        'country' => null],
            ['name' => 'Expats.cz',                           'url' => 'https://www.expats.cz/rss',                           'base_url' => 'https://www.expats.cz',                       'category' => 'expat',        'language' => 'en', 'country' => 'République tchèque'],
            ['name' => 'Expatblog',                           'url' => 'https://expatblog.com/feed/',                         'base_url' => 'https://expatblog.com',                       'category' => 'expat',        'country' => null],

            // ── VOYAGE / NOMADES ──
            ['name' => 'Routard',                             'url' => 'https://www.routard.com/rss/actualite_voyageur.xml', 'base_url' => 'https://www.routard.com',                    'category' => 'voyage',       'country' => null],
            ['name' => 'Petit Futé',                          'url' => 'https://www.petitfute.com/rss/magazine.xml',         'base_url' => 'https://www.petitfute.com',                  'category' => 'voyage',       'country' => null],
            ['name' => 'Voyageurs du Monde',                  'url' => 'https://www.voyageursdumonde.fr/rss',                'base_url' => 'https://www.voyageursdumonde.fr',            'category' => 'voyage',       'country' => null],
            ['name' => 'Géo',                                 'url' => 'https://www.geo.fr/rss',                             'base_url' => 'https://www.geo.fr',                         'category' => 'voyage',       'country' => null],
            ['name' => 'Lonely Planet FR',                    'url' => 'https://www.lonelyplanet.fr/feed',                   'base_url' => 'https://www.lonelyplanet.fr',                'category' => 'voyage',       'country' => null],
            ['name' => 'Evaneos Blog',                        'url' => 'https://www.evaneos.fr/blog/feed/',                  'base_url' => 'https://www.evaneos.fr',                     'category' => 'voyage',       'country' => null],
            ['name' => 'Nomadslim',                           'url' => 'https://nomadslim.com/feed/',                        'base_url' => 'https://nomadslim.com',                      'category' => 'voyage',       'country' => null],
            ['name' => 'Petit Voyageur',                      'url' => 'https://petitvoyageur.fr/feed/',                     'base_url' => 'https://petitvoyageur.fr',                   'category' => 'voyage',       'country' => null],
            ['name' => 'Voyages Voyages',                     'url' => 'https://voyagesvoyages.com/feed/',                   'base_url' => 'https://voyagesvoyages.com',                 'category' => 'voyage',       'country' => null],

            // ── LIFESTYLE / CULTURE ──
            ['name' => 'Daily Nord',                          'url' => 'https://dailynord.fr/feed/',                         'base_url' => 'https://dailynord.fr',                       'category' => 'regional',     'country' => 'France'],
            ['name' => 'Le Figaro - Voyage',                  'url' => 'https://www.lefigaro.fr/rss/figaro_voyages.xml',     'base_url' => 'https://www.lefigaro.fr',                    'category' => 'voyage',       'country' => 'France'],
            ['name' => 'Le Monde - Voyage',                   'url' => 'https://www.lemonde.fr/voyage/rss_full.xml',         'base_url' => 'https://www.lemonde.fr',                     'category' => 'voyage',       'country' => 'France'],
            ['name' => 'Télérama - Voyage',                   'url' => 'https://www.telerama.fr/rss/voyage.xml',             'base_url' => 'https://www.telerama.fr',                    'category' => 'voyage',       'country' => 'France'],

            // ── BUSINESS / TECH FR ÉTRANGER ──
            ['name' => 'Frenchies Abroad',                    'url' => 'https://frenchiesabroad.com/feed/',                  'base_url' => 'https://frenchiesabroad.com',                'category' => 'business',     'country' => null],
            ['name' => 'Bpifrance Blog',                      'url' => 'https://bpifrance-creation.fr/feed',                 'base_url' => 'https://bpifrance-creation.fr',              'category' => 'business',     'country' => 'France'],
            ['name' => 'Maddyness',                            'url' => 'https://www.maddyness.com/feed/',                    'base_url' => 'https://www.maddyness.com',                  'category' => 'tech',         'country' => 'France'],
            ['name' => 'FrenchWeb',                           'url' => 'https://www.frenchweb.fr/feed',                      'base_url' => 'https://www.frenchweb.fr',                   'category' => 'tech',         'country' => 'France'],
            ['name' => 'Journal du Net',                      'url' => 'https://www.journaldunet.com/rss/',                  'base_url' => 'https://www.journaldunet.com',               'category' => 'tech',         'country' => 'France'],

            // ── EXPATS PAR PAYS / BLOGS INDIVIDUELS ──
            ['name' => 'Yummy Planet',                        'url' => 'https://yummyplanet.fr/feed/',                       'base_url' => 'https://yummyplanet.fr',                     'category' => 'voyage',       'country' => null],
            ['name' => 'Road Addict',                         'url' => 'https://www.road-addict.fr/feed/',                   'base_url' => 'https://www.road-addict.fr',                 'category' => 'voyage',       'country' => null],
            ['name' => 'Novo Monde',                          'url' => 'https://www.novo-monde.com/feed/',                   'base_url' => 'https://www.novo-monde.com',                 'category' => 'voyage',       'country' => null],
            ['name' => 'Bons Plans Voyage',                   'url' => 'https://bons-plans-voyage-new-york.com/feed/',       'base_url' => 'https://bons-plans-voyage-new-york.com',     'category' => 'voyage',       'country' => 'États-Unis'],
            ['name' => 'Carnets de Weekends',                 'url' => 'https://www.carnetsdeweekends.fr/feed/',             'base_url' => 'https://www.carnetsdeweekends.fr',           'category' => 'voyage',       'country' => null],
            ['name' => 'Chicks en Road',                      'url' => 'https://www.chicksenroad.com/feed/',                 'base_url' => 'https://www.chicksenroad.com',               'category' => 'voyage',       'country' => null],
            ['name' => 'Detour Local',                        'url' => 'https://detourlocal.com/feed/',                      'base_url' => 'https://detourlocal.com',                    'category' => 'voyage',       'country' => null],
            ['name' => 'Voyage Avec Ninie',                   'url' => 'https://voyageavecninie.fr/feed/',                   'base_url' => 'https://voyageavecninie.fr',                 'category' => 'voyage',       'country' => null],
            ['name' => 'Voyage en Famille',                   'url' => 'https://voyagefamille.com/feed/',                    'base_url' => 'https://voyagefamille.com',                  'category' => 'voyage_famille', 'country' => null],
            ['name' => 'Vie Nomade',                          'url' => 'https://vie-nomade.com/feed/',                       'base_url' => 'https://vie-nomade.com',                     'category' => 'nomade',       'country' => null],

            // ── PODCASTS ── (itunes:author dans le RSS)
            ['name' => 'Choses à Savoir - Voyage',            'url' => 'https://www.chosesasavoir.com/feed/voyage',          'base_url' => 'https://www.chosesasavoir.com',              'category' => 'podcast',      'country' => 'France'],
            ['name' => 'Les Baladeurs (Podcasts d\'Aventures)','url' => 'https://feeds.acast.com/public/shows/les-baladeurs', 'base_url' => 'https://lesbaladeurs.com',                    'category' => 'podcast',      'country' => null],
            ['name' => 'Expatriés - Le Podcast',              'url' => 'https://anchor.fm/s/4f6d9f30/podcast/rss',           'base_url' => null,                                          'category' => 'podcast',      'country' => null],

            // ── NOMADES / DIGITAL ──
            ['name' => 'Nomadic Matt (EN)',                   'url' => 'https://www.nomadicmatt.com/feed/',                  'base_url' => 'https://www.nomadicmatt.com',                 'category' => 'nomade',       'language' => 'en', 'country' => null],
            ['name' => 'Remote.co Blog',                       'url' => 'https://remote.co/blog/feed/',                        'base_url' => 'https://remote.co',                           'category' => 'nomade',       'language' => 'en', 'country' => null],
            ['name' => 'Nomad List Blog',                      'url' => 'https://nomadlist.com/rss',                           'base_url' => 'https://nomadlist.com',                       'category' => 'nomade',       'language' => 'en', 'country' => null],

            // ── ANNUAIRES / COLLECTIFS ──
            ['name' => 'Globe Reporters',                     'url' => 'https://www.globe-reporters.org/spip.php?page=backend','base_url' => 'https://www.globe-reporters.org',             'category' => 'journalisme',  'country' => null],
            ['name' => 'Expat Communications',                 'url' => 'https://expat-communication.com/feed/',               'base_url' => 'https://expat-communication.com',             'category' => 'expat',        'country' => null],
            ['name' => 'Les Frenchies d\'Allemagne',          'url' => 'https://lesfrenchies.de/feed/',                       'base_url' => 'https://lesfrenchies.de',                     'category' => 'expat',        'country' => 'Allemagne'],

            // ── BLOGS TECH / STARTUP INTERNATIONAUX FR ──
            ['name' => 'Blog du Modérateur',                   'url' => 'https://www.blogdumoderateur.com/feed/',             'base_url' => 'https://www.blogdumoderateur.com',           'category' => 'tech',         'country' => 'France'],
            ['name' => 'Presse-Citron',                        'url' => 'https://www.presse-citron.net/feed/',                'base_url' => 'https://www.presse-citron.net',              'category' => 'tech',         'country' => 'France'],

            // ── FOOD / CULTURE FR ──
            ['name' => 'Papilles et Pupilles',                 'url' => 'https://www.papillesetpupilles.fr/feed',             'base_url' => 'https://www.papillesetpupilles.fr',          'category' => 'food',         'country' => 'France'],
            ['name' => 'Chef Simon',                            'url' => 'https://chefsimon.com/feed/',                        'base_url' => 'https://chefsimon.com',                      'category' => 'food',         'country' => 'France'],

            // ── MAGAZINES EXPATRIATION ──
            ['name' => 'S\'expatrier.com',                     'url' => 'https://www.s-expatrier.com/feed/',                  'base_url' => 'https://www.s-expatrier.com',                'category' => 'expat',        'country' => null],
            ['name' => 'Je Pars à l\'étranger',               'url' => 'https://jepars-a-letranger.fr/feed/',                'base_url' => 'https://jepars-a-letranger.fr',               'category' => 'expat',        'country' => null],
        ];
    }
}
