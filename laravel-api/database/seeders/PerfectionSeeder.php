<?php

namespace Database\Seeders;

use App\Helpers\FrenchPreposition;
use App\Models\ContentTemplateItem;
use App\Models\CountryGeo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Corrige et perfectionne TOUS les titres de contenus :
 * 1. Prépositions FR dans content_template_items (en/au/aux/à)
 * 2. Supprime les tutoriels qui font doublon avec les templates pays
 * 3. Diversifie les témoignages (4 angles par pays)
 * 4. Diversifie les outreach (3 variantes par type × pays)
 */
class PerfectionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ── 1. CORRIGER LES PRÉPOSITIONS DANS CONTENT_TEMPLATE_ITEMS ──
        $this->command?->info("1. Correction prepositions content_template_items...");

        $countries = CountryGeo::all()->keyBy('country_code');
        $fixed = 0;

        // Traiter par batch pour la performance
        ContentTemplateItem::whereNotNull('variable_values')
            ->chunkById(500, function ($items) use ($countries, &$fixed) {
                foreach ($items as $item) {
                    $vals = $item->variable_values;
                    $paysName = $vals['pays'] ?? null;
                    $paysCode = $vals['pays_code'] ?? null;

                    if (!$paysName) continue;

                    $prep = FrenchPreposition::prep($paysName);
                    $currentTitle = $item->expanded_title;

                    // Vérifier si le titre contient déjà la bonne préposition
                    if (str_contains($currentTitle, $prep)) continue;

                    // Remplacer le nom du pays brut par la version avec préposition
                    // Ex: "visa de travail France" → "visa de travail en France"
                    $newTitle = str_replace($paysName, $prep, $currentTitle);

                    // Si pas de changement (pays pas trouvé dans le titre), essayer autrement
                    if ($newTitle === $currentTitle) {
                        // Le pays peut être à la fin sans espace avant
                        $newTitle = preg_replace(
                            '/\b' . preg_quote($paysName, '/') . '\b/',
                            $prep,
                            $currentTitle
                        );
                    }

                    if ($newTitle !== $currentTitle) {
                        $item->update(['expanded_title' => $newTitle]);
                        $fixed++;
                    }
                }
            });

        $this->command?->info("  {$fixed} titres corriges");

        // ── 2. SUPPRIMER LES TUTORIELS REDONDANTS AVEC LES TEMPLATES PAYS ──
        $this->command?->info("2. Suppression tutoriels redondants...");

        // Les templates pays couvrent déjà : visa travail, visa étudiant, permis séjour,
        // compte bancaire, logement, entreprise, permis conduire, immatriculation,
        // assurance santé, impôts, sécurité sociale
        $redundantDemarches = [
            'obtenir un visa de travail',     // → Pays - Visa & Immigration - Visa travail
            'obtenir un visa etudiant',       // → Pays - Visa & Immigration - Visa etudiant
            'renouveler son titre de sejour', // → Pays - Visa & Immigration - Permis de sejour
            'ouvrir un compte bancaire',      // → Pays - Finance - Compte bancaire
            'trouver un logement',            // → Pays - Logement - Trouver un logement
            'creer une entreprise',           // → Pays - Entrepreneuriat - Creer entreprise
            'obtenir un permis de conduire',  // → Pays - Transport - Permis de conduire
            'immatriculer une voiture',       // → Pays - Transport - Immatriculation
            'souscrire une assurance sante',  // → Pays - Sante - Assurance sante
        ];

        // Garder les tutoriels UNIQUES (pas couverts par templates pays)
        // - faire sa declaration d'impots → Pays a "impots expatrie" mais angle différent (pas étape par étape)
        // - inscrire ses enfants a l'ecole → Pays a "ecoles internationales" mais angle différent
        // - s'inscrire a la securite sociale → pas dans templates pays

        $deleted = DB::table('generation_source_items')
            ->where('category_slug', 'tutoriels')
            ->whereIn('sub_category', $redundantDemarches)
            ->delete();

        $this->command?->info("  {$deleted} tutoriels redondants supprimes");
        $remaining = DB::table('generation_source_items')->where('category_slug', 'tutoriels')->count();
        $this->command?->info("  {$remaining} tutoriels uniques conserves");

        // ── 3. DIVERSIFIER LES TÉMOIGNAGES (4 angles par pays) ──
        $this->command?->info("3. Diversification temoignages...");

        // Supprimer les anciens (1 seul angle)
        DB::table('generation_source_items')->where('category_slug', 'temoignages')->delete();

        $temoignageAngles = [
            ['template' => 'Mon installation {prep_pays} : recit d\'un expatrie', 'theme' => 'installation'],
            ['template' => 'Travailler {prep_pays} en tant qu\'expatrie : mon parcours', 'theme' => 'emploi'],
            ['template' => 'Vivre en famille {prep_pays} : notre experience d\'expatriation', 'theme' => 'famille'],
            ['template' => 'Retraite {prep_pays} : pourquoi j\'ai choisi ce pays', 'theme' => 'retraite'],
        ];

        $allCountries = DB::table('content_countries')->select('name', 'slug')->orderBy('name')->get();
        $temCount = 0;

        foreach ($temoignageAngles as $angle) {
            foreach ($allCountries as $country) {
                $title = FrenchPreposition::replace($angle['template'], $country->name);
                DB::table('generation_source_items')->insert([
                    'category_slug'     => 'temoignages',
                    'source_type'       => 'testimonial',
                    'title'             => $title,
                    'country'           => $country->name,
                    'country_slug'      => $country->slug,
                    'theme'             => $angle['theme'],
                    'language'          => 'fr',
                    'processing_status' => 'ready',
                    'quality_score'     => 75,
                    'is_cleaned'        => true,
                    'input_quality'     => 'title_only',
                    'used_count'        => 0,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
                $temCount++;
            }
        }
        $this->command?->info("  {$temCount} temoignages diversifies (4 angles x pays)");

        // ── 4. DIVERSIFIER LES OUTREACH (3 variantes par type) ──
        $this->command?->info("4. Diversification outreach...");

        $outreachVariantes = [
            'chatters' => [
                'Gagner de l\'argent en ligne {prep_pays} : missions reseaux sociaux pour expatries',
                'Travail flexible {prep_pays} : devenir ambassadeur digital pour expatries',
                'Completer ses revenus {prep_pays} : partager des bons plans expatriation',
            ],
            'bloggeurs' => [
                'Monetiser son blog expatriation {prep_pays} : programmes d\'affiliation',
                'Blogueur voyage {prep_pays} : gagner des commissions avec l\'expatriation',
                'Creer du contenu expatriation {prep_pays} : opportunites de monetisation',
            ],
            'admin-groups' => [
                'Groupes Facebook expatries {prep_pays} : creer et gerer une communaute',
                'Communaute expatries {prep_pays} : lancer un groupe d\'entraide en ligne',
                'Reseaux sociaux expatries {prep_pays} : federer et monetiser une communaute',
            ],
            'avocats' => [
                'Avocat francophone {prep_pays} : trouver des clients expatries en ligne',
                'Clientele expatriee {prep_pays} : developper son cabinet a l\'international',
                'Consultation juridique en ligne {prep_pays} : opportunites pour avocats',
            ],
            'expats-aidants' => [
                'Revenu complementaire {prep_pays} : aider les expatries a distance',
                'Conseiller expatries {prep_pays} : partager son experience contre remuneration',
                'Assistance expatries {prep_pays} : devenir guide local pour nouveaux arrivants',
            ],
        ];

        // Supprimer les anciens outreach (1 seule variante)
        DB::table('generation_source_items')
            ->whereIn('category_slug', array_keys($outreachVariantes))
            ->delete();

        $outCount = 0;
        foreach ($outreachVariantes as $slug => $variantes) {
            foreach ($variantes as $template) {
                foreach ($allCountries as $country) {
                    $title = FrenchPreposition::replace($template, $country->name);
                    DB::table('generation_source_items')->insert([
                        'category_slug'     => $slug,
                        'source_type'       => 'outreach',
                        'title'             => $title,
                        'country'           => $country->name,
                        'country_slug'      => $country->slug,
                        'theme'             => 'recrutement',
                        'language'          => 'fr',
                        'processing_status' => 'ready',
                        'quality_score'     => 75,
                        'is_cleaned'        => true,
                        'input_quality'     => 'title_only',
                        'used_count'        => 0,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]);
                    $outCount++;
                }
            }
        }
        $this->command?->info("  {$outCount} outreach diversifies (3 variantes x 5 types x pays)");

        // ── RÉCAP ──
        $totalSource = DB::table('generation_source_items')->count();
        $totalTemplate = DB::table('content_template_items')->count();
        $this->command?->info("\n=== RECAP ===");
        $this->command?->info("generation_source_items: {$totalSource}");
        $this->command?->info("content_template_items: {$totalTemplate}");
        $this->command?->info("Total: " . ($totalSource + $totalTemplate));
    }
}
