<?php

namespace App\Jobs;

use App\Models\AiResearchSession;
use App\Services\PerplexitySearchService;
use App\Services\Scraping\ScraperRotationService;
use App\Services\Scraping\ScraperRunRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Option D — P4 : Wrapper qui lance RunAiResearchJob avec rotation pays
 * pour un contact_type donné (blog, podcast_radio, influenceur).
 *
 * Flow :
 *   1. Selectionne 1 pays via ScraperRotationService (cooldown 24h)
 *   2. Si Perplexity non configure → skipped_no_ia (graceful)
 *   3. Cree AiResearchSession
 *   4. Dispatch async RunAiResearchJob (qui fait Perplexity + import)
 *   5. Marque pays done dans rotation
 *   6. Retour immediat (async : on ne tracke pas les resultats finaux ici,
 *      le ScraperRun est ferme avec meta session_id pour tracage)
 *
 * Utilise par 3 boutons /admin/scraper :
 *   - bloggers-ai    → contact_type=blog
 *   - podcasters-ai  → contact_type=podcast_radio
 *   - influencers-ai → contact_type=influenceur
 */
class DispatchAiResearchByTypeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    /** Top 30 pays FR expat pour rotation (francophonie + forte population FR) */
    private const COUNTRIES_FR_EXPAT = [
        'France', 'Canada', 'Belgique', 'Suisse', 'Luxembourg',
        'États-Unis', 'Royaume-Uni', 'Allemagne', 'Espagne', 'Italie',
        'Portugal', 'Maroc', 'Tunisie', 'Algérie', 'Sénégal',
        'Côte d\'Ivoire', 'Cameroun', 'Madagascar', 'Thaïlande', 'Vietnam',
        'Japon', 'Chine', 'Corée du Sud', 'Australie', 'Nouvelle-Zélande',
        'Brésil', 'Argentine', 'Mexique', 'Émirats arabes unis', 'Qatar',
    ];

    public function __construct(private readonly string $contactType)
    {
        $this->onQueue('scraper');
    }

    public function handle(
        ScraperRotationService $rotation,
        ScraperRunRecorder $recorder,
        PerplexitySearchService $perplexity
    ): void {
        $scraperName = "ai-{$this->contactType}"; // ai-blog, ai-podcast_radio, ai-influenceur

        $country = $rotation->nextCountry($scraperName, self::COUNTRIES_FR_EXPAT);

        $recorder->track($scraperName, $country, true, function ($run) use ($country, $perplexity, $rotation, $scraperName) {
            // Graceful skip si Perplexity KO
            if (!$perplexity->isConfigured()) {
                if ($run) {
                    $recorderInstance = app(ScraperRunRecorder::class);
                    $recorderInstance->markSkipped($run, 'PERPLEXITY_API_KEY manquante');
                }
                Log::info('DispatchAiResearchByTypeJob: skipped (Perplexity KO)', [
                    'contact_type' => $this->contactType,
                ]);
                return ['found' => 0, 'new' => 0, 'meta' => ['skipped' => 'perplexity_not_configured']];
            }

            // Skip si tous les pays ont déjà été traités dans les dernières 24h
            if ($country === null) {
                Log::info('DispatchAiResearchByTypeJob: all countries done in last 24h', [
                    'contact_type' => $this->contactType,
                ]);
                return ['found' => 0, 'new' => 0, 'meta' => ['skipped' => 'all_countries_cooldown']];
            }

            // Créer session + dispatch async RunAiResearchJob
            $session = AiResearchSession::create([
                'user_id'      => 1, // system user (admin)
                'contact_type' => $this->contactType,
                'country'      => $country,
                'language'     => 'fr',
                'status'       => 'pending',
            ]);

            RunAiResearchJob::dispatch($session->id, null, false);
            $rotation->markDone($scraperName, $country);

            Log::info('DispatchAiResearchByTypeJob: session dispatched', [
                'contact_type' => $this->contactType,
                'country'      => $country,
                'session_id'   => $session->id,
            ]);

            // Retour immédiat : résultats finaux captures par RunAiResearchJob lui-même
            // (il met à jour session.contacts_found/contacts_imported).
            return [
                'found' => 0,
                'new'   => 0,
                'meta'  => [
                    'session_id' => $session->id,
                    'country'    => $country,
                    'async'      => true,
                ],
            ];
        });
    }
}
