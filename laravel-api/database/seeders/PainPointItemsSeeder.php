<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PainPointItemsSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // ── Administratif (8) ──
            ['title' => "J'ai perdu mon passeport a l'etranger",                    'theme' => 'administratif', 'sub_category' => 'documents'],
            ['title' => 'Refus de visa recours possible',                           'theme' => 'administratif', 'sub_category' => 'visa'],
            ['title' => "Renouvellement passeport a l'etranger delais",             'theme' => 'administratif', 'sub_category' => 'documents'],
            ['title' => 'Carte de sejour expiree que faire',                        'theme' => 'administratif', 'sub_category' => 'sejour'],
            ['title' => "Documents voles a l'etranger demarches",                   'theme' => 'administratif', 'sub_category' => 'documents'],
            ['title' => "Permis de conduire non reconnu a l'etranger",              'theme' => 'administratif', 'sub_category' => 'documents'],
            ['title' => 'Inscription consulaire refusee',                           'theme' => 'administratif', 'sub_category' => 'consulat'],
            ['title' => "Perte carte d'identite a l'etranger",                     'theme' => 'administratif', 'sub_category' => 'documents'],

            // ── Juridique (8) ──
            ['title' => 'Divorce expatrie quel pays competent',                     'theme' => 'juridique', 'sub_category' => 'famille'],
            ['title' => 'Garde enfant expatrie conflit juridique',                  'theme' => 'juridique', 'sub_category' => 'famille'],
            ['title' => "Contrat de travail non respecte a l'etranger",             'theme' => 'juridique', 'sub_category' => 'travail'],
            ['title' => 'Probleme succession expatrie',                             'theme' => 'juridique', 'sub_category' => 'patrimoine'],
            ['title' => 'Litige proprietaire locataire expatrie',                   'theme' => 'juridique', 'sub_category' => 'logement'],
            ['title' => "Arrestation a l'etranger que faire",                       'theme' => 'juridique', 'sub_category' => 'penal'],
            ['title' => "Arnaque immobiliere a l'etranger",                         'theme' => 'juridique', 'sub_category' => 'logement'],
            ['title' => 'Discrimination au travail expatrie',                       'theme' => 'juridique', 'sub_category' => 'travail'],

            // ── Medical (8) ──
            ['title' => "Hospitalisation a l'etranger sans assurance",              'theme' => 'medical', 'sub_category' => 'urgence'],
            ['title' => "Enfant malade a l'etranger urgence",                       'theme' => 'medical', 'sub_category' => 'urgence'],
            ['title' => "Accident de voiture a l'etranger sans assurance",          'theme' => 'medical', 'sub_category' => 'accident'],
            ['title' => "Rapatriement medical d'urgence",                           'theme' => 'medical', 'sub_category' => 'urgence'],
            ['title' => 'Maladie tropicale expatrie traitement',                    'theme' => 'medical', 'sub_category' => 'maladie'],
            ['title' => "Deces d'un proche a l'etranger demarches",                 'theme' => 'medical', 'sub_category' => 'deces'],
            ['title' => "Grossesse a l'etranger sans couverture",                   'theme' => 'medical', 'sub_category' => 'maternite'],
            ['title' => "Urgence dentaire a l'etranger",                            'theme' => 'medical', 'sub_category' => 'urgence'],

            // ── Financier (7) ──
            ['title' => 'Employeur expatrie ne paie pas mon salaire',               'theme' => 'financier', 'sub_category' => 'salaire'],
            ['title' => "Compte bancaire bloque a l'etranger",                      'theme' => 'financier', 'sub_category' => 'banque'],
            ['title' => "Arnaque location vacances a l'etranger",                   'theme' => 'financier', 'sub_category' => 'arnaque'],
            ['title' => 'Double imposition expatrie',                               'theme' => 'financier', 'sub_category' => 'fiscalite'],
            ['title' => "Fraude carte bancaire a l'etranger",                       'theme' => 'financier', 'sub_category' => 'arnaque'],
            ['title' => 'Faillite entreprise expatrie',                             'theme' => 'financier', 'sub_category' => 'entreprise'],
            ['title' => 'Pension alimentaire impayee expatrie',                     'theme' => 'financier', 'sub_category' => 'famille'],

            // ── Logement (6) ──
            ['title' => 'Expulsion logement expatrie droits',                       'theme' => 'logement', 'sub_category' => 'expulsion'],
            ['title' => 'Arnaque location appartement etranger',                    'theme' => 'logement', 'sub_category' => 'arnaque'],
            ['title' => 'Logement insalubre expatrie recours',                      'theme' => 'logement', 'sub_category' => 'qualite'],
            ['title' => "Caution non restituee a l'etranger",                       'theme' => 'logement', 'sub_category' => 'caution'],
            ['title' => 'SDF expatrie sans logement',                               'theme' => 'logement', 'sub_category' => 'urgence'],
            ['title' => 'Sous-location illegale expatrie',                          'theme' => 'logement', 'sub_category' => 'legal'],

            // ── Emploi (6) ──
            ['title' => 'Harcelement au travail expatrie',                          'theme' => 'emploi', 'sub_category' => 'harcelement'],
            ['title' => 'Licenciement abusif expatrie',                             'theme' => 'emploi', 'sub_category' => 'licenciement'],
            ['title' => 'Travail au noir expatrie risques',                         'theme' => 'emploi', 'sub_category' => 'illegal'],
            ['title' => 'Patron refuse certificat travail etranger',               'theme' => 'emploi', 'sub_category' => 'documents'],
            ['title' => 'Chomage expatrie retour pays',                             'theme' => 'emploi', 'sub_category' => 'chomage'],
            ['title' => 'Burn-out expatrie isolement',                              'theme' => 'emploi', 'sub_category' => 'sante_mentale'],

            // ── Securite (7) ──
            ['title' => "Vol de bagages a l'aeroport recours",                      'theme' => 'securite', 'sub_category' => 'vol'],
            ['title' => "Agression physique a l'etranger",                          'theme' => 'securite', 'sub_category' => 'agression'],
            ['title' => 'Catastrophe naturelle evacuation expatrie',                'theme' => 'securite', 'sub_category' => 'catastrophe'],
            ['title' => 'Zone de conflit expatrie evacuation',                      'theme' => 'securite', 'sub_category' => 'conflit'],
            ['title' => 'Harcelement de rue expatrie',                              'theme' => 'securite', 'sub_category' => 'harcelement'],
            ['title' => 'Cambriolage logement expatrie',                            'theme' => 'securite', 'sub_category' => 'vol'],
            ['title' => 'Kidnapping menace expatrie',                               'theme' => 'securite', 'sub_category' => 'securite'],
        ];

        $now = now();
        $inserted = 0;

        foreach ($items as $item) {
            DB::table('generation_source_items')->updateOrInsert(
                ['category_slug' => 'pain-point', 'title' => $item['title']],
                [
                    'source_type'       => 'pain_point',
                    'theme'             => $item['theme'],
                    'sub_category'      => $item['sub_category'],
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
            $inserted++;
        }

        $this->command?->info("Seeded {$inserted} pain point items.");
    }
}
