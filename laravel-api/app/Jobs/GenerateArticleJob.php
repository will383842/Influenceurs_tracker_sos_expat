<?php

namespace App\Jobs;

use App\Services\Content\ArticleGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;
    public int $maxExceptions = 1;

    public function __construct(
        public array $params,
    ) {
        $this->onQueue('content');
    }

    public function handle(ArticleGenerationService $service): void
    {
        Log::info('GenerateArticleJob started', [
            'topic' => $this->params['topic'] ?? null,
            'language' => $this->params['language'] ?? null,
            'content_type' => $this->params['content_type'] ?? 'article',
        ]);

        $article = $service->generate($this->params);

        // Calculate and persist total generation cost
        $totalCost = \App\Models\ApiCost::where('costable_type', \App\Models\GeneratedArticle::class)
            ->where('costable_id', $article->id)
            ->sum('cost_cents');
        if ($totalCost > 0) {
            $article->update(['generation_cost_cents' => $totalCost]);
        }

        Log::info('GenerateArticleJob completed', [
            'id' => $article->id,
            'title' => $article->title,
            'word_count' => $article->word_count,
            'seo_score' => $article->seo_score,
            'cost_cents' => $article->generation_cost_cents,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateArticleJob failed', [
            'params' => $this->params,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
