<?php

namespace App\Jobs;

use App\Services\Seo\SeoAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeSeoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 3;

    public function __construct(
        public string $modelType,
        public int $modelId,
    ) {
        $this->onQueue('default');
    }

    public function handle(SeoAnalysisService $service): void
    {
        Log::info('AnalyzeSeoJob started', [
            'model_type' => $this->modelType,
            'model_id' => $this->modelId,
        ]);

        $model = $this->modelType::findOrFail($this->modelId);

        $analysis = $service->analyze($model);

        Log::info('SEO analysis completed', [
            'model_type' => $this->modelType,
            'model_id' => $this->modelId,
            'score' => $analysis->overall_score,
            'issues_count' => is_array($analysis->issues) ? count($analysis->issues) : 0,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AnalyzeSeoJob failed', [
            'model_type' => $this->modelType,
            'model_id' => $this->modelId,
            'error' => $e->getMessage(),
        ]);
    }
}
