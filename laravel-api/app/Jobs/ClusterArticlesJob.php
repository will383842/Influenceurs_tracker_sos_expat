<?php

namespace App\Jobs;

use App\Services\Content\TopicClusteringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ClusterArticlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;
    public int $maxExceptions = 1;

    public function __construct(
        public string $country,
        public string $category,
    ) {
        $this->onQueue('content');
    }

    public function handle(TopicClusteringService $service): void
    {
        Log::info('ClusterArticlesJob started', [
            'country' => $this->country,
            'category' => $this->category,
        ]);

        $clusters = $service->clusterByCountryAndCategory($this->country, $this->category);

        Log::info('ClusterArticlesJob completed', [
            'country' => $this->country,
            'category' => $this->category,
            'clusters_created' => $clusters->count(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ClusterArticlesJob failed', [
            'country' => $this->country,
            'category' => $this->category,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
