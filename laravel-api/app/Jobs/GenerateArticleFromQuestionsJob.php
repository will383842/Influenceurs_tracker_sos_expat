<?php

namespace App\Jobs;

use App\Models\QuestionCluster;
use App\Services\Content\ArticleFromQuestionsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateArticleFromQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;
    public int $maxExceptions = 1;

    public function __construct(
        public int $clusterId,
    ) {
        $this->onQueue('content');
    }

    public function handle(ArticleFromQuestionsService $service): void
    {
        $cluster = QuestionCluster::findOrFail($this->clusterId);

        Log::info('GenerateArticleFromQuestionsJob started', [
            'cluster_id' => $this->clusterId,
        ]);

        $article = $service->generateFromCluster($cluster);

        Log::info('GenerateArticleFromQuestionsJob completed', [
            'cluster_id' => $this->clusterId,
            'article_id' => $article->id,
            'word_count' => $article->word_count,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateArticleFromQuestionsJob failed', [
            'cluster_id' => $this->clusterId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
