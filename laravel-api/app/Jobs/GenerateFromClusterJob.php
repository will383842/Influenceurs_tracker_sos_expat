<?php

namespace App\Jobs;

use App\Models\ApiCost;
use App\Models\GeneratedArticle;
use App\Models\TopicCluster;
use App\Services\Content\ArticleGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateFromClusterJob implements ShouldQueue
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

    public function handle(ArticleGenerationService $service): void
    {
        $cluster = TopicCluster::with(['researchBrief', 'sourceArticles'])->findOrFail($this->clusterId);

        Log::info('GenerateFromClusterJob started', [
            'cluster_id' => $cluster->id,
            'cluster_name' => $cluster->name,
            'has_brief' => $cluster->researchBrief !== null,
        ]);

        // Build generation params from cluster + research brief
        $brief = $cluster->researchBrief;
        $suggestedKeywords = $brief->suggested_keywords ?? [];

        $keywords = [];
        if (!empty($suggestedKeywords['primary'])) {
            $keywords[] = $suggestedKeywords['primary'];
        }
        $keywords = array_merge($keywords, $suggestedKeywords['secondary'] ?? []);
        $keywords = array_merge($keywords, array_slice($suggestedKeywords['long_tail'] ?? [], 0, 3));

        $params = [
            'topic' => $cluster->name,
            'language' => $cluster->language ?? 'fr',
            'country' => $cluster->country ?? null,
            'content_type' => 'article',
            'keywords' => array_slice($keywords, 0, 8),
            'cluster_id' => $cluster->id,
            'generate_faq' => true,
            'faq_count' => 10,
            'auto_internal_links' => true,
            'auto_affiliate_links' => true,
            'image_source' => 'unsplash',
        ];

        $article = $service->generate($params);

        // Calculate and persist total generation cost
        $totalCost = ApiCost::where('costable_type', GeneratedArticle::class)
            ->where('costable_id', $article->id)
            ->sum('cost_cents');
        if ($totalCost > 0) {
            $article->update(['generation_cost_cents' => $totalCost]);
        }

        Log::info('GenerateFromClusterJob completed', [
            'cluster_id' => $cluster->id,
            'article_id' => $article->id,
            'title' => $article->title,
            'word_count' => $article->word_count,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateFromClusterJob failed', [
            'cluster_id' => $this->clusterId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
