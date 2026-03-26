<?php

namespace App\Jobs;

use App\Services\Content\ComparativeGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateComparativeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    public function __construct(
        public array $params,
    ) {
        $this->onQueue('content');
    }

    public function handle(ComparativeGenerationService $service): void
    {
        Log::info('GenerateComparativeJob started', [
            'title' => $this->params['title'] ?? null,
            'entities' => $this->params['entities'] ?? [],
            'language' => $this->params['language'] ?? null,
        ]);

        $comparative = $service->generate($this->params);

        // Calculate and persist total generation cost
        $totalCost = \App\Models\ApiCost::where('costable_type', \App\Models\Comparative::class)
            ->where('costable_id', $comparative->id)
            ->sum('cost_cents');
        if ($totalCost > 0) {
            $comparative->update(['generation_cost_cents' => $totalCost]);
        }

        Log::info('GenerateComparativeJob completed', [
            'id' => $comparative->id,
            'title' => $comparative->title,
            'seo_score' => $comparative->seo_score,
            'cost_cents' => $comparative->generation_cost_cents,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateComparativeJob failed', [
            'params' => $this->params,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
