<?php

namespace App\Services\Scraping;

use App\Models\ScraperRotationState;
use Illuminate\Support\Carbon;

/**
 * Rotation pays pour les scrapers cron.
 *
 *  - Chaque scraper a une queue FIFO de pays.
 *  - On ne retraite jamais un pays vu dans les dernières 24h.
 *  - Quand la queue est vide, on recharge depuis la liste complète.
 *
 * Usage (depuis un Job wrapper) :
 *   $country = $rotation->nextCountry('scrape-lawyers', $allCountries);
 *   if ($country === null) return; // tous les pays faits dans les 24h
 *   ... scrape $country ...
 *   $rotation->markDone('scrape-lawyers', $country);
 */
class ScraperRotationService
{
    private const COOLDOWN_HOURS = 24;

    /**
     * Retourne le prochain pays à traiter, ou null si tout a été vu récemment.
     *
     * @param array<int, string> $allCountries Liste source (ordre préservé)
     */
    public function nextCountry(string $scraperName, array $allCountries): ?string
    {
        if (empty($allCountries)) {
            return null;
        }

        $state = ScraperRotationState::firstOrNew(['scraper_name' => $scraperName]);

        $queue  = (array) ($state->country_queue ?? []);
        $recent = (array) ($state->recent_countries ?? []);

        // Nettoyer les entrées recent expirées (>24h)
        $cutoff = now()->subHours(self::COOLDOWN_HOURS)->timestamp;
        $recent = array_filter($recent, fn ($entry) => ($entry['ts'] ?? 0) >= $cutoff);

        // Recharger la queue si vide
        if (empty($queue)) {
            $recentNames = array_column($recent, 'country');
            $queue = array_values(array_diff($allCountries, $recentNames));

            // Tous les pays vus dans les 24h → rien à faire
            if (empty($queue)) {
                $state->recent_countries = array_values($recent);
                $state->save();
                return null;
            }
        }

        $next = array_shift($queue);

        $state->country_queue   = array_values($queue);
        $state->recent_countries = array_values($recent);
        $state->last_country    = $next;
        $state->last_ran_at     = now();
        $state->save();

        return $next;
    }

    public function markDone(string $scraperName, string $country): void
    {
        $state = ScraperRotationState::firstOrNew(['scraper_name' => $scraperName]);
        $recent = (array) ($state->recent_countries ?? []);
        $recent[] = ['country' => $country, 'ts' => now()->timestamp];

        // Purger les entrées > 48h pour garder la table légère
        $cutoff = now()->subHours(48)->timestamp;
        $recent = array_values(array_filter($recent, fn ($entry) => ($entry['ts'] ?? 0) >= $cutoff));

        $state->recent_countries = $recent;
        $state->save();
    }

    public function reset(string $scraperName): void
    {
        ScraperRotationState::where('scraper_name', $scraperName)->delete();
    }
}
