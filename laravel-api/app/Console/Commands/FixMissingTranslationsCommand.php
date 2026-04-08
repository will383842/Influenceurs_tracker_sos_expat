<?php

namespace App\Console\Commands;

use App\Jobs\GenerateTranslationJob;
use App\Models\GeneratedArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Dispatch translation jobs for published parent articles that have 0 translations.
 */
class FixMissingTranslationsCommand extends Command
{
    protected $signature = 'articles:fix-missing-translations
        {--dry-run : Show what would be dispatched without dispatching}
        {--article-id= : Target a specific article ID}';

    protected $description = 'Dispatch translation jobs for published articles with 0 translations';

    private const TARGET_LANGUAGES = ['en', 'es', 'de', 'pt', 'ru', 'zh', 'ar', 'hi'];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $specificId = $this->option('article-id');

        $query = GeneratedArticle::where('status', 'published')
            ->whereNull('parent_article_id') // Only parent articles (originals)
            ->doesntHave('translations');     // 0 translations

        if ($specificId) {
            $query->where('id', $specificId);
        }

        $articles = $query->get();

        $count = $articles->count();
        $this->info("Found {$count} published parent articles with 0 translations" . ($dryRun ? ' (dry run)' : ''));

        if ($count === 0) {
            if ($specificId) {
                $this->warn("Article #{$specificId} not found, already has translations, or is not a published parent.");
            }
            return self::SUCCESS;
        }

        $dispatched = 0;
        $failed = 0;

        foreach ($articles as $article) {
            $this->line("  #{$article->id} [{$article->language}] {$article->title}");

            $targetLangs = array_filter(
                self::TARGET_LANGUAGES,
                fn (string $lang) => $lang !== $article->language
            );

            foreach ($targetLangs as $lang) {
                if ($dryRun) {
                    $this->line("    -> would dispatch: {$article->language} -> {$lang}");
                    $dispatched++;
                    continue;
                }

                try {
                    GenerateTranslationJob::dispatch($article->id, $lang);
                    $this->info("    -> dispatched: {$article->language} -> {$lang}");
                    $dispatched++;
                } catch (\Throwable $e) {
                    $this->error("    -> FAILED {$lang}: {$e->getMessage()}");
                    Log::error('FixMissingTranslations dispatch failed', [
                        'article_id' => $article->id,
                        'target_language' => $lang,
                        'error' => $e->getMessage(),
                    ]);
                    $failed++;
                }
            }
        }

        $this->newLine();
        $this->info("Done: {$dispatched} translation jobs dispatched, {$failed} failed" . ($dryRun ? ' (dry run -- nothing dispatched)' : ''));

        return self::SUCCESS;
    }
}
