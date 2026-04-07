<?php

namespace Database\Seeders;

use App\Helpers\FrenchPreposition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Peuple generation_source_items pour TOUS les types ContentGenerator vides.
 * Les articles sont générés en FR, la Phase 15 du pipeline traduit en 8 langues.
 */
class ContentGeneratorSourcesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $totalInserted = 0;

        // ── 1. FICHES VILLES — copier content_cities vers generation_source_items ──
        $cities = DB::table('content_cities as cc')
            ->join('content_countries as co', 'cc.country_id', '=', 'co.id')
            ->select('cc.name as city', 'co.name as country', 'co.slug as country_slug')
            ->orderBy('co.name')
            ->orderBy('cc.name')
            ->get();

        $villesCount = 0;
        foreach ($cities as $city) {
            $title = "Vivre a {$city->city} en tant qu'expatrie : guide complet";
            DB::table('generation_source_items')->updateOrInsert(
                ['category_slug' => 'villes', 'title' => $title],
                [
                    'source_type'       => 'guide_city',
                    'country'           => $city->country,
                    'country_slug'      => $city->country_slug,
                    'theme'             => 'guide',
                    'sub_category'      => $city->city,
                    'language'          => 'fr',
                    'processing_status' => 'ready',
                    'quality_score'     => 80,
                    'is_cleaned'        => true,
                    'input_quality'     => 'title_only',
                    'used_count'        => 0,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]
            );
            $villesCount++;
        }
        $totalInserted += $villesCount;
        $this->command?->info("Villes: {$villesCount} items (from content_cities)");

        // ── 2. TYPES OUTREACH (chatters, influenceurs, admin-groupes, avocats, expats-aidants) ──
        // Titres = vraies requêtes Google (intention de recherche), PAS des annonces SOS-Expat
        $outreachTypes = [
            'chatters' => [
                'source_type' => 'outreach',
                'template'    => 'Gagner de l\'argent en ligne {prep_pays} : missions reseaux sociaux pour expatries',
                'theme'       => 'recrutement',
            ],
            'bloggeurs' => [
                'source_type' => 'outreach',
                'template'    => 'Monetiser son blog expatriation {prep_pays} : programmes d\'affiliation',
                'theme'       => 'recrutement',
            ],
            'admin-groups' => [
                'source_type' => 'outreach',
                'template'    => 'Groupes Facebook expatries {prep_pays} : creer et gerer une communaute',
                'theme'       => 'recrutement',
            ],
            'avocats' => [
                'source_type' => 'outreach',
                'template'    => 'Avocat francophone {prep_pays} : trouver des clients expatries en ligne',
                'theme'       => 'recrutement',
            ],
            'expats-aidants' => [
                'source_type' => 'outreach',
                'template'    => 'Revenu complementaire {prep_pays} : aider les expatries a distance',
                'theme'       => 'recrutement',
            ],
        ];

        $countries = DB::table('content_countries')
            ->select('name', 'slug')
            ->orderBy('name')
            ->get();

        // Supprimer les anciens titres outreach (mauvais format)
        DB::table('generation_source_items')
            ->whereIn('category_slug', array_keys($outreachTypes))
            ->delete();

        foreach ($outreachTypes as $slug => $config) {
            $count = 0;
            foreach ($countries as $country) {
                $title = FrenchPreposition::replace($config['template'], $country->name);
                DB::table('generation_source_items')->updateOrInsert(
                    ['category_slug' => $slug, 'title' => $title],
                    [
                        'source_type'       => $config['source_type'],
                        'country'           => $country->name,
                        'country_slug'      => $country->slug,
                        'theme'             => $config['theme'],
                        'language'          => 'fr',
                        'processing_status' => 'ready',
                        'quality_score'     => 75,
                        'is_cleaned'        => true,
                        'input_quality'     => 'title_only',
                        'used_count'        => 0,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]
                );
                $count++;
            }
            $totalInserted += $count;
            $this->command?->info("{$slug}: {$count} items");
        }

        // ── 3. TUTORIELS — démarches × pays ──
        $demarches = [
            ['demarche' => 'obtenir un visa de travail',     'theme' => 'visa'],
            ['demarche' => 'obtenir un visa etudiant',       'theme' => 'visa'],
            ['demarche' => 'renouveler son titre de sejour', 'theme' => 'visa'],
            ['demarche' => 'ouvrir un compte bancaire',      'theme' => 'banque'],
            ['demarche' => 's\'inscrire a la securite sociale', 'theme' => 'sante'],
            ['demarche' => 'trouver un logement',            'theme' => 'logement'],
            ['demarche' => 'immatriculer une voiture',       'theme' => 'transport'],
            ['demarche' => 'creer une entreprise',           'theme' => 'emploi'],
            ['demarche' => 'inscrire ses enfants a l\'ecole', 'theme' => 'education'],
            ['demarche' => 'obtenir un permis de conduire',  'theme' => 'transport'],
            ['demarche' => 'faire sa declaration d\'impots',  'theme' => 'fiscalite'],
            ['demarche' => 'souscrire une assurance sante',  'theme' => 'sante'],
        ];

        // Supprimer les anciens tutoriels (mauvaises prépositions)
        DB::table('generation_source_items')->where('category_slug', 'tutoriels')->delete();

        $tutCount = 0;
        foreach ($demarches as $d) {
            foreach ($countries as $country) {
                $prep = FrenchPreposition::prep($country->name);
                $title = "Comment {$d['demarche']} {$prep} : guide complet etape par etape";
                DB::table('generation_source_items')->updateOrInsert(
                    ['category_slug' => 'tutoriels', 'title' => $title],
                    [
                        'source_type'       => 'tutorial',
                        'country'           => $country->name,
                        'country_slug'      => $country->slug,
                        'theme'             => $d['theme'],
                        'sub_category'      => $d['demarche'],
                        'language'          => 'fr',
                        'processing_status' => 'ready',
                        'quality_score'     => 80,
                        'is_cleaned'        => true,
                        'input_quality'     => 'title_only',
                        'used_count'        => 0,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]
                );
                $tutCount++;
            }
        }
        $totalInserted += $tutCount;
        $this->command?->info("tutoriels: {$tutCount} items (12 demarches x 223 pays)");

        // ── 4. TÉMOIGNAGES — compléter avec les pays manquants ──
        // Supprimer les anciens témoignages (mauvaises prépositions)
        DB::table('generation_source_items')->where('category_slug', 'temoignages')->delete();

        $temCount = 0;
        foreach ($countries as $country) {
            $prep = FrenchPreposition::prep($country->name);
            $title = "Temoignage expatrie {$prep} : mon experience et mes conseils";
            DB::table('generation_source_items')->updateOrInsert(
                ['category_slug' => 'temoignages', 'title' => $title],
                [
                    'source_type'       => 'testimonial',
                    'country'           => $country->name,
                    'country_slug'      => $country->slug,
                    'theme'             => 'general',
                    'language'          => 'fr',
                    'processing_status' => 'ready',
                    'quality_score'     => 75,
                    'is_cleaned'        => true,
                    'input_quality'     => 'title_only',
                    'used_count'        => 0,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]
            );
            $temCount++;
        }
        $totalInserted += $temCount;
        $this->command?->info("temoignages: {$temCount} items supplementaires");

        // ── 5. PAIN POINTS — expansion par pays ──
        $painPointTitles = [
            "J'ai perdu mon passeport {prep_pays}",
            "Arnaque location vacances {prep_pays}",
            "Accident de voiture {prep_pays} sans assurance",
            "Hospitalisation {prep_pays} sans assurance",
            "Divorce expatrie {prep_pays} quel recours",
            "Licenciement abusif {prep_pays} expatrie",
            "Agression physique {prep_pays} que faire",
            "Compte bancaire bloque {prep_pays}",
            "Refus de visa {prep_pays} recours",
            "Harcelement au travail {prep_pays} expatrie",
        ];

        // Supprimer anciens pain-points par pays (mauvaises prépositions)
        DB::table('generation_source_items')
            ->where('category_slug', 'pain-point')
            ->whereNotNull('country_slug')
            ->where('country_slug', '!=', '')
            ->delete();

        $ppCount = 0;
        foreach ($painPointTitles as $template) {
            foreach ($countries as $country) {
                $title = FrenchPreposition::replace($template, $country->name);
                DB::table('generation_source_items')->updateOrInsert(
                    ['category_slug' => 'pain-point', 'title' => $title],
                    [
                        'source_type'       => 'pain_point',
                        'country'           => $country->name,
                        'country_slug'      => $country->slug,
                        'theme'             => 'urgence',
                        'language'          => 'fr',
                        'processing_status' => 'ready',
                        'quality_score'     => 85,
                        'is_cleaned'        => true,
                        'input_quality'     => 'title_only',
                        'used_count'        => 0,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]
                );
                $ppCount++;
            }
        }
        $totalInserted += $ppCount;
        $this->command?->info("pain-point: {$ppCount} items par pays supplementaires");

        $this->command?->info("\nTotal: {$totalInserted} items inseres/mis a jour.");
    }
}
