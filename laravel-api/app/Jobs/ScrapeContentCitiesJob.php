<?php

namespace App\Jobs;

use App\Models\ContentCity;
use App\Models\ContentCountry;
use App\Models\ContentSource;
use App\Services\ContentScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrateur : découverte de toutes les villes pour tous les pays d'une source,
 * puis dispatch d'un ScrapeContentCityJob par ville.
 */
class ScrapeContentCitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 28800; // 8h — 223 pays × ~10s/page villes
    public int $tries   = 1;

    public function __construct(
        private int $sourceId,
        private ?int $countryId = null, // Si null → tous les pays
    ) {
        $this->onQueue('content-scraper');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('scrape-cities-' . $this->sourceId))
                ->releaseAfter(28800)
                ->expireAfter(28800),
        ];
    }

    public function handle(ContentScraperService $scraper): void
    {
        $source = ContentSource::find($this->sourceId);
        if (!$source) return;

        Log::info('ScrapeContentCitiesJob: starting', [
            'source'     => $source->slug,
            'country_id' => $this->countryId ?? 'all',
        ]);

        // Charger les pays à traiter
        $query = ContentCountry::where('source_id', $source->id);
        if ($this->countryId) {
            $query->where('id', $this->countryId);
        }
        $countries = $query->orderBy('name')->get();

        $totalCities     = 0;
        $newCities       = 0;
        $dispatchedJobs  = 0;

        foreach ($countries as $country) {
            try {
                $scraper->rateLimitSleep();

                $cityList = $scraper->scrapeCityList($country);

                if (empty($cityList)) {
                    Log::info('ScrapeContentCitiesJob: no cities found', ['country' => $country->slug]);
                    continue;
                }

                foreach ($cityList as $cityData) {
                    // Créer ou retrouver la ville
                    $city = ContentCity::firstOrCreate(
                        [
                            'source_id'  => $source->id,
                            'country_id' => $country->id,
                            'slug'       => $cityData['slug'],
                        ],
                        [
                            'name'      => $cityData['name'],
                            'continent' => $cityData['continent'] ?? $country->continent,
                            'guide_url' => $cityData['guide_url'],
                        ]
                    );

                    $totalCities++;
                    if ($city->wasRecentlyCreated) {
                        $newCities++;
                    }

                    // Dispatcher le job de scraping uniquement si pas encore scraped
                    if (!$city->scraped_at) {
                        ScrapeContentCityJob::dispatch($city->id)->onQueue('content-scraper');
                        $dispatchedJobs++;
                    }
                }

            } catch (\Throwable $e) {
                Log::warning('ScrapeContentCitiesJob: country failed', [
                    'country' => $country->slug,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // Mettre à jour les stats source
        $source->update([
            'total_countries' => $source->countries()->count(),
        ]);

        Log::info('ScrapeContentCitiesJob: discovery completed', [
            'source'         => $source->slug,
            'total_cities'   => $totalCities,
            'new_cities'     => $newCities,
            'dispatched_jobs' => $dispatchedJobs,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ScrapeContentCitiesJob: job failed permanently', [
            'sourceId' => $this->sourceId,
            'error'    => $e->getMessage(),
        ]);
    }
}
