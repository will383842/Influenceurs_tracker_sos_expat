<?php

namespace App\Jobs;

use App\Models\QuestionCluster;
use App\Services\Content\QaFromQuestionsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateQaFromClusterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;
    public int $maxExceptions = 1;

    public function __construct(
        public int $clusterId,
        public int $maxQa = 10,
    ) {
        $this->onQueue('content');
    }

    public function handle(QaFromQuestionsService $service): void
    {
        $cluster = QuestionCluster::findOrFail($this->clusterId);

        Log::info('GenerateQaFromClusterJob started', [
            'cluster_id' => $this->clusterId,
            'max_qa' => $this->maxQa,
        ]);

        $entries = $service->generateFromCluster($cluster, $this->maxQa);

        Log::info('GenerateQaFromClusterJob completed', [
            'cluster_id' => $this->clusterId,
            'entries_created' => $entries->count(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateQaFromClusterJob failed', [
            'cluster_id' => $this->clusterId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
