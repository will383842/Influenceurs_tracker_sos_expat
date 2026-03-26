<?php

namespace App\Jobs;

use App\Services\Content\AutoContentPipelineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunFullAutoPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400; // 4 hours
    public int $tries = 1;
    public int $maxExceptions = 1;

    public function __construct(
        public array $options = [],
    ) {
        $this->onQueue('content');
    }

    public function handle(AutoContentPipelineService $service): void
    {
        Log::info('RunFullAutoPipelineJob: started', ['options' => $this->options]);

        $summary = $service->run($this->options);

        Log::info('RunFullAutoPipelineJob: completed', $summary);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RunFullAutoPipelineJob: failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
