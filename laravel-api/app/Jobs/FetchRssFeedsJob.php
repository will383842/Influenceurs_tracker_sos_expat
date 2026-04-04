<?php

namespace App\Jobs;

use App\Models\RssFeed;
use App\Models\RssFeedItem;
use App\Services\News\RelevanceFilterService;
use App\Services\News\RssFetcherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchRssFeedsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 1;

    public function __construct(private readonly ?int $feedId = null) {}

    public function handle(RssFetcherService $fetcher, RelevanceFilterService $filter): void
    {
        $query = RssFeed::active();

        if ($this->feedId !== null) {
            $query->where('id', $this->feedId);
        }

        $feeds = $query->get();

        foreach ($feeds as $feed) {
            // Vérifier si le feed doit être fetchés maintenant
            if (
                $this->feedId === null
                && $feed->last_fetched_at !== null
                && $feed->last_fetched_at->gt(now()->subHours($feed->fetch_interval_hours))
            ) {
                continue;
            }

            Log::info("FetchRssFeedsJob: fetch feed #{$feed->id} ({$feed->name})");

            try {
                $newCount = $fetcher->fetchFeed($feed);

                Log::info("FetchRssFeedsJob: {$newCount} nouveaux items pour feed #{$feed->id}");

                if ($newCount > 0) {
                    // Évaluer la pertinence des nouveaux items (relevance_score = null)
                    // with('feed') évite N+1 dans RelevanceFilterService::evaluate()
                    $newItems = RssFeedItem::with('feed')
                        ->where('feed_id', $feed->id)
                        ->where('status', 'pending')
                        ->whereNull('relevance_score')
                        ->latest()
                        ->limit($newCount + 5) // légère marge
                        ->get();

                    foreach ($newItems as $item) {
                        try {
                            $filter->evaluate($item);
                        } catch (\Throwable $e) {
                            Log::warning("FetchRssFeedsJob: erreur évaluation item #{$item->id}", ['error' => $e->getMessage()]);
                        }
                    }
                }

            } catch (\Throwable $e) {
                Log::error("FetchRssFeedsJob: erreur feed #{$feed->id}", ['error' => $e->getMessage()]);
            }
        }

        Log::info('FetchRssFeedsJob: terminé', ['feeds_processed' => $feeds->count()]);
    }
}
