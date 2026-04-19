<?php

namespace App\Services\Scraping;

use App\Models\ScraperRun;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wrapper qui enregistre chaque exécution de scraper dans la table scraper_runs.
 * Résilient : si la table n'existe pas encore (avant migrate:deploy en prod),
 * le tracking est silencieusement désactivé (catch Throwable).
 *
 * Usage :
 *   $result = app(ScraperRunRecorder::class)->track(
 *       'instagram-scrape-francophones',
 *       'Thaïlande',
 *       true,
 *       fn () => $this->runScraping(...)
 *   );
 */
class ScraperRunRecorder
{
    /**
     * @template T
     * @param callable(ScraperRun|null): T $callback
     * @return T
     */
    public function track(
        string $scraperName,
        ?string $country,
        bool $requiresPerplexity,
        callable $callback
    ) {
        $run = $this->open($scraperName, $country, $requiresPerplexity);

        try {
            $result = $callback($run);
            $this->closeOk($run, $result);
            return $result;
        } catch (Throwable $e) {
            $this->closeError($run, $e->getMessage());
            throw $e;
        }
    }

    public function open(string $scraperName, ?string $country, bool $requiresPerplexity): ?ScraperRun
    {
        try {
            return ScraperRun::create([
                'scraper_name'        => $scraperName,
                'status'              => ScraperRun::STATUS_RUNNING,
                'country'             => $country,
                'requires_perplexity' => $requiresPerplexity,
                'started_at'          => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('ScraperRunRecorder: open failed (table missing?)', [
                'scraper' => $scraperName,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function markSkipped(?ScraperRun $run, string $reason): void
    {
        $run?->markError($reason, ScraperRun::STATUS_SKIPPED_NO_IA);
    }

    public function closeOk(?ScraperRun $run, mixed $result): void
    {
        if (!$run) return;

        $found = 0;
        $new   = 0;
        $meta  = null;

        if (is_array($result)) {
            $found = (int) ($result['found'] ?? 0);
            $new   = (int) ($result['new'] ?? 0);
            $meta  = $result['meta'] ?? null;
        }

        try {
            $run->markOk($found, $new, $meta);
        } catch (Throwable $e) {
            Log::warning('ScraperRunRecorder: closeOk failed', ['error' => $e->getMessage()]);
        }
    }

    public function closeError(?ScraperRun $run, string $message): void
    {
        if (!$run) return;
        try {
            $run->markError($message);
        } catch (Throwable $e) {
            Log::warning('ScraperRunRecorder: closeError failed', ['error' => $e->getMessage()]);
        }
    }
}
