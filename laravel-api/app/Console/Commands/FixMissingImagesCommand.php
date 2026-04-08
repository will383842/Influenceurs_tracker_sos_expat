<?php

namespace App\Console\Commands;

use App\Models\GeneratedArticle;
use App\Services\AI\UnsplashService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Batch-fix published articles that have no featured image.
 * Searches Unsplash using the article's keywords or title.
 */
class FixMissingImagesCommand extends Command
{
    protected $signature = 'articles:fix-missing-images
        {--dry-run : Show what would be fixed without changing anything}
        {--limit=0 : Max articles to process (0 = all)}';

    protected $description = 'Search and assign featured images (Unsplash) for published articles missing one';

    public function handle(UnsplashService $unsplash): int
    {
        $dryRun = $this->option('dry-run');
        $limit  = (int) $this->option('limit');

        if (!$unsplash->isConfigured()) {
            $this->error('Unsplash access key is not configured (services.unsplash.access_key).');
            return self::FAILURE;
        }

        $query = GeneratedArticle::where('status', 'published')
            ->whereNull('featured_image_url')
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $articles = $query->get();
        $count    = $articles->count();

        $this->info("Found {$count} published articles without featured_image_url" . ($dryRun ? ' (dry run)' : ''));

        if ($count === 0) {
            return self::SUCCESS;
        }

        $fixed  = 0;
        $failed = 0;

        foreach ($articles as $index => $article) {
            $searchTerm = $article->keywords_primary ?: $article->title;
            $this->line("  [{$index}/{$count}] #{$article->id} [{$article->language}] \"{$searchTerm}\"");

            if ($dryRun) {
                $fixed++;
                continue;
            }

            try {
                $result = $unsplash->search($searchTerm, 1, 'landscape');

                if (!($result['success'] ?? false) || empty($result['images'])) {
                    $error = $result['error'] ?? 'no results';
                    $this->warn("    -> No image found ({$error})");

                    // If rate limit reached, stop entirely
                    if (str_contains($error, 'rate limit')) {
                        $this->error('Rate limit reached — stopping.');
                        break;
                    }

                    $failed++;
                } else {
                    $image = $result['images'][0];

                    // Build keyword-enriched alt text
                    $altText = $article->keywords_primary
                        ? ucfirst($article->keywords_primary) . ' - ' . ($image['alt_text'] ?? $article->title)
                        : ($image['alt_text'] ?? $article->title);
                    $altText = mb_substr($altText, 0, 125);

                    $article->update([
                        'featured_image_url'         => $image['url'],
                        'featured_image_alt'         => $altText,
                        'featured_image_attribution'  => $image['attribution'] ?? null,
                        'featured_image_srcset'       => $image['srcset'] ?? null,
                        'photographer_name'           => $image['photographer_name'] ?? null,
                        'photographer_url'            => $image['photographer_url'] ?? null,
                    ]);

                    $this->info("    -> OK: {$image['photographer_name']} (Unsplash)");
                    $fixed++;
                }
            } catch (\Throwable $e) {
                $this->error("    -> FAILED: {$e->getMessage()}");
                Log::error('FixMissingImages failed', [
                    'article_id' => $article->id,
                    'error'      => $e->getMessage(),
                ]);
                $failed++;
            }

            // Rate limit: 1 second between Unsplash API calls
            if (!$dryRun && $index < $count - 1) {
                sleep(1);
            }
        }

        $this->newLine();
        $this->info("Done: {$fixed} fixed, {$failed} failed" . ($dryRun ? ' (dry run -- no changes made)' : ''));

        return self::SUCCESS;
    }
}
