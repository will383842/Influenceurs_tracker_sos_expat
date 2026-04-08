<?php

namespace App\Console\Commands;

use App\Models\GeneratedArticle;
use App\Services\Seo\SeoAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Recalculate SEO scores for published articles that have seo_score = 0.
 */
class FixSeoScoresCommand extends Command
{
    protected $signature = 'articles:fix-seo-scores
        {--dry-run : Show what would be fixed without changing anything}
        {--all-missing : Also fix articles with quality_score=0 or readability_score=NULL}';

    protected $description = 'Recalculate SEO/quality/readability scores for published articles with seo_score = 0 (or quality_score = 0 / readability_score NULL with --all-missing)';

    public function handle(SeoAnalysisService $seoService): int
    {
        $dryRun = $this->option('dry-run');
        $allMissing = $this->option('all-missing');

        $query = GeneratedArticle::where('status', 'published');

        if ($allMissing) {
            $query->where(function ($q) {
                $q->where('seo_score', 0)
                  ->orWhere('quality_score', 0)
                  ->orWhereNull('readability_score');
            });
        } else {
            $query->where('seo_score', 0);
        }

        $articles = $query->get();

        $count = $articles->count();
        $label = $allMissing
            ? 'seo_score=0 OR quality_score=0 OR readability_score=NULL'
            : 'seo_score = 0';
        $this->info("Found {$count} published articles with {$label}" . ($dryRun ? ' (dry run)' : ''));

        if ($count === 0) {
            return self::SUCCESS;
        }

        $fixed = 0;
        $failed = 0;

        foreach ($articles as $article) {
            $this->line("  #{$article->id} [{$article->language}] {$article->title}");

            if ($dryRun) {
                $fixed++;
                continue;
            }

            try {
                $seoAnalysis = $seoService->analyze($article);

                $overallScore = $seoAnalysis->overall_score;

                // Also update quality_score and readability_score on the article if fillable
                $updates = ['seo_score' => $overallScore];

                $readability = $seoService->calculateReadability(
                    $seoService->extractTextFromHtml($article->content_html ?? '')
                );
                if (in_array('readability_score', $article->getFillable())) {
                    $updates['readability_score'] = $readability;
                }
                if (in_array('quality_score', $article->getFillable())) {
                    $updates['quality_score'] = $overallScore;
                }

                $article->update($updates);

                $this->info("    -> seo_score={$overallScore}, readability={$readability}, issues=" . count($seoAnalysis->issues ?? []));
                $fixed++;
            } catch (\Throwable $e) {
                $this->error("    -> FAILED: {$e->getMessage()}");
                Log::error('FixSeoScores failed', [
                    'article_id' => $article->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done: {$fixed} fixed, {$failed} failed" . ($dryRun ? ' (dry run -- no changes made)' : ''));

        return self::SUCCESS;
    }
}
