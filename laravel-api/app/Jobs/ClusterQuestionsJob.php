<?php

namespace App\Jobs;

use App\Services\Content\QuestionClusteringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ClusterQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;
    public int $maxExceptions = 1;

    public function __construct(
        public string $countrySlug,
        public ?string $category = null,
    ) {
        $this->onQueue('content');
    }

    public function handle(QuestionClusteringService $service): void
    {
        Log::info('ClusterQuestionsJob started', [
            'country_slug' => $this->countrySlug,
            'category' => $this->category,
        ]);

        $clusters = $service->clusterByCountry($this->countrySlug, $this->category);

        Log::info('ClusterQuestionsJob completed', [
            'country_slug' => $this->countrySlug,
            'category' => $this->category,
            'clusters_created' => $clusters->count(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ClusterQuestionsJob failed', [
            'country_slug' => $this->countrySlug,
            'category' => $this->category,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
