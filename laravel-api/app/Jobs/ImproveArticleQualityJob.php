<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use App\Services\Quality\AutoQualityImproverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImproveArticleQualityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(
        public int $articleId,
        public int $targetScore = 85,
        public int $maxPasses = 3,
    ) {
        $this->onQueue('content');
    }

    public function handle(AutoQualityImproverService $service): void
    {
        Log::info('ImproveArticleQualityJob started', [
            'article_id'   => $this->articleId,
            'target_score' => $this->targetScore,
            'max_passes'   => $this->maxPasses,
        ]);

        $article = GeneratedArticle::findOrFail($this->articleId);

        $result = $service->improve($article, $this->targetScore, $this->maxPasses);

        Log::info('ImproveArticleQualityJob complete', [
            'article_id'    => $this->articleId,
            'initial_score' => $result['initial_score'],
            'final_score'   => $result['final_score'],
            'passes'        => $result['passes'],
            'improvements'  => count($result['improvements']),
        ]);
    }
}
