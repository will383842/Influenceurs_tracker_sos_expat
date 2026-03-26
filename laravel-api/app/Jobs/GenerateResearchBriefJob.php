<?php

namespace App\Jobs;

use App\Models\TopicCluster;
use App\Services\Content\ResearchBriefService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateResearchBriefJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;
    public int $maxExceptions = 1;

    public function __construct(
        public int $clusterId,
    ) {
        $this->onQueue('content');
    }

    public function handle(ResearchBriefService $service): void
    {
        $cluster = TopicCluster::findOrFail($this->clusterId);

        Log::info('GenerateResearchBriefJob started', [
            'cluster_id' => $cluster->id,
            'cluster_name' => $cluster->name,
        ]);

        $brief = $service->generateBrief($cluster);

        Log::info('GenerateResearchBriefJob completed', [
            'cluster_id' => $cluster->id,
            'brief_id' => $brief->id,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateResearchBriefJob failed', [
            'cluster_id' => $this->clusterId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
