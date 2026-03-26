<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use App\Services\Content\QaGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateQaEntriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;
    public int $maxExceptions = 1;

    public function __construct(
        public int $articleId,
        public array $faqIds = [],
    ) {
        $this->onQueue('content');
    }

    public function handle(QaGenerationService $service): void
    {
        $article = GeneratedArticle::findOrFail($this->articleId);

        Log::info('GenerateQaEntriesJob started', [
            'article_id' => $article->id,
            'faq_ids' => $this->faqIds,
        ]);

        $entries = $service->generateFromArticleFaqs($article, $this->faqIds);

        Log::info('GenerateQaEntriesJob completed', [
            'article_id' => $article->id,
            'entries_created' => $entries->count(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateQaEntriesJob failed', [
            'article_id' => $this->articleId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
