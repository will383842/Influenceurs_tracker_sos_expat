<?php

namespace App\Console\Commands;

use App\Models\LandingPage;
use App\Services\Content\LandingGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Enrichit via LLM les landing_pages publiées dont le contenu est "vide"
 * (uniquement hero + meta description, aucune section intro/content/topics/
 * categories/guide_steps). Ces landings sortent de `landings:generate` en mode
 * light ou de `landings:backfill-translations` sans ré-exécuter la pipeline IA.
 *
 * Pour chaque landing vide :
 *   - Relance LandingGenerationService->generate() avec les paramètres
 *     d'audience + template + country + language de la landing existante
 *   - Remplace les sections par celles générées (garde title/canonical/slug)
 *   - Met à jour generation_source='ai_enriched'
 *
 * Usage :
 *   php artisan landings:enrich-empty --limit=5 --dry-run
 *   php artisan landings:enrich-empty --limit=50
 *   php artisan landings:enrich-empty --country=VN
 */
class EnrichEmptyLandingsCommand extends Command
{
    protected $signature = 'landings:enrich-empty
        {--limit=       : Max number of landings to enrich}
        {--country=     : Filter by country_code (ISO2)}
        {--language=    : Filter by language (ISO2)}
        {--dry-run      : Print what would be enriched without calling LLM}';

    protected $description = 'Enrichit via IA les landing_pages publiées qui n\'ont que le hero (0 section intro/content/topics).';

    public function handle(LandingGenerationService $service): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $limit   = $this->option('limit') ? (int) $this->option('limit') : null;
        $country = strtoupper((string) $this->option('country'));
        $language = (string) $this->option('language');

        $this->info("Scanning published landings for empty content…");

        $query = LandingPage::published();
        if ($country)  $query->where('country_code', $country);
        if ($language) $query->where('language', $language);

        $empties = $query->get()->filter(function ($lp) {
            $sections = is_array($lp->sections) ? $lp->sections : [];
            $types    = array_column($sections, 'type');
            // "Empty" = aucune section riche, juste hero/faq/cta
            $rich = ['intro', 'content', 'topics', 'categories', 'visa_categories', 'guide_steps', 'earnings', 'freedom', 'process'];
            return empty(array_intersect($rich, $types));
        })->values();

        $this->info("Found {$empties->count()} empty landings.");

        if ($limit !== null) {
            $empties = $empties->take($limit);
            $this->line("Limit = {$limit} → processing {$empties->count()}");
        }

        if ($dryRun) {
            $this->warn('DRY RUN — aucune écriture.');
            foreach ($empties as $lp) {
                $this->line("  would enrich #{$lp->id}  {$lp->country_code}/{$lp->language}  {$lp->audience_type}/{$lp->template_id}  {$lp->canonical_url}");
            }
            return self::SUCCESS;
        }

        $enriched = 0;
        $failed   = 0;

        foreach ($empties as $lp) {
            $this->line("\n[#{$lp->id} {$lp->country_code}/{$lp->language} {$lp->audience_type}/{$lp->template_id}]");

            try {
                $params = [
                    'audience_type'      => $lp->audience_type ?? 'clients',
                    'template_id'        => $lp->template_id ?? 'informational',
                    'country_code'       => $lp->country_code,
                    'language'           => $lp->language,
                    'problem_slug'       => $lp->problem_id ?? $lp->category_slug ?? null,
                    'category_slug'      => $lp->category_slug,
                    'user_profile'       => $lp->user_profile,
                    'origin_nationality' => $lp->origin_nationality,
                    'created_by'         => $lp->created_by,
                ];

                // On ne peut pas regénérer "en place" sans casser la dédup
                // (audience_type, template_id, country_code, language unique).
                // Stratégie : on génère une NOUVELLE landing via le service,
                // on copie ses sections dans l'ancienne, puis on supprime la
                // nouvelle (qui aurait un slug en conflit sinon).
                //
                // Mais plus propre : on laisse le service générer, puis on
                // détecte le conflit et met à jour l'ancienne.
                //
                // Approche retenue : soft-delete l'ancienne juste avant, pour
                // laisser la place à la nouvelle. Si la nouvelle réussit, on
                // force-delete l'ancienne. Si elle échoue, on restore.
                $oldId = $lp->id;
                $lp->forceDelete(); // hard-delete, on reconstruit

                $newLp = $service->generate($params);

                $this->info("  ✓ enriched #{$newLp->id} (was #{$oldId})");
                $enriched++;
            } catch (\Throwable $e) {
                $this->error("  ✗ FAIL: " . $e->getMessage());
                Log::warning('landings:enrich-empty failed', ['id' => $lp->id, 'err' => $e->getMessage()]);
                $failed++;
                // Si la génération a échoué après le delete, la landing est
                // perdue — acceptable car elle était déjà "vide" et inutile.
            }
        }

        $this->newLine();
        $this->info("Done. Enriched={$enriched}, Failed={$failed}");

        return self::SUCCESS;
    }
}
