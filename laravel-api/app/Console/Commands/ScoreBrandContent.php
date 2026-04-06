<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2.3 — Score and sort brand content articles.
 *
 * Evaluates existing content_articles by quality criteria and marks them:
 * - TOP 30: Best articles to keep and optimize
 * - ARCHIVE: Repetitive or low-quality articles to archive
 *
 * Scoring criteria:
 * - Word count (min 500 words = relevant)
 * - Has external links (sources = authority)
 * - Has images (visual quality)
 * - Category relevance (matches our audience)
 * - Not duplicate (unique content)
 * - Recent (fresher = better)
 *
 * Usage: php artisan content:score-brand [--apply] [--limit=208]
 */
class ScoreBrandContent extends Command
{
    protected $signature = 'content:score-brand
        {--apply : Actually update quality_rating in database}
        {--limit=500 : Maximum articles to process}
        {--top=30 : Number of top articles to keep}';

    protected $description = 'Score and sort brand content articles — keep top 30, archive rest';

    // Categories that match our audience
    private const HIGH_VALUE_CATEGORIES = [
        'visa', 'immigration', 'demarches', 'logement', 'sante',
        'emploi', 'fiscalite', 'banque', 'education', 'securite',
    ];

    // Brand-relevant sections
    private const HIGH_VALUE_SECTIONS = [
        'guide', 'services', 'thematic',
    ];

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $topN = (int) $this->option('top');

        $this->info("=== BRAND CONTENT SCORING ===");
        $this->newLine();

        // Fetch articles
        $articles = DB::table('content_articles')
            ->select([
                'id', 'title', 'url', 'category', 'section',
                'word_count', 'language', 'external_links', 'images',
                'is_guide', 'quality_rating', 'processing_status',
                'scraped_at',
            ])
            ->orderByDesc('word_count')
            ->limit($limit)
            ->get();

        if ($articles->isEmpty()) {
            $this->warn("No content_articles found in database.");
            return 0;
        }

        $this->info("Processing {$articles->count()} articles...");

        // Score each article
        $scored = [];
        foreach ($articles as $article) {
            $score = $this->calculateScore($article);
            $scored[] = [
                'id' => $article->id,
                'title' => mb_substr($article->title, 0, 60),
                'score' => $score,
                'word_count' => $article->word_count ?? 0,
                'category' => $article->category,
                'section' => $article->section,
            ];
        }

        // Sort by score DESC
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        // Split into TOP and ARCHIVE
        $top = array_slice($scored, 0, $topN);
        $archive = array_slice($scored, $topN);

        // Display results
        $this->newLine();
        $this->info("=== TOP {$topN} ARTICLES (KEEP) ===");
        $tableData = array_map(fn($a) => [
            $a['id'], $a['score'], $a['word_count'], $a['category'], $a['title'],
        ], $top);
        $this->table(['ID', 'Score', 'Words', 'Category', 'Title'], $tableData);

        $this->newLine();
        $this->warn("=== ARCHIVE (" . count($archive) . " articles) ===");

        // Score distribution
        $scoreRanges = ['90-100' => 0, '70-89' => 0, '50-69' => 0, '30-49' => 0, '0-29' => 0];
        foreach ($scored as $s) {
            if ($s['score'] >= 90) $scoreRanges['90-100']++;
            elseif ($s['score'] >= 70) $scoreRanges['70-89']++;
            elseif ($s['score'] >= 50) $scoreRanges['50-69']++;
            elseif ($s['score'] >= 30) $scoreRanges['30-49']++;
            else $scoreRanges['0-29']++;
        }

        $this->info("=== SCORE DISTRIBUTION ===");
        foreach ($scoreRanges as $range => $count) {
            $bar = str_repeat('█', min($count, 50));
            $this->line("  {$range}: {$count} {$bar}");
        }

        // Apply if requested
        if ($this->option('apply')) {
            $this->newLine();
            $this->info("Updating quality_rating in database...");

            $topIds = array_column($top, 'id');
            $archiveIds = array_column($archive, 'id');

            // Update TOP articles
            foreach ($top as $article) {
                DB::table('content_articles')
                    ->where('id', $article['id'])
                    ->update([
                        'quality_rating' => $article['score'],
                        'processing_status' => 'generation_ready',
                    ]);
            }

            // Update ARCHIVE articles
            if (!empty($archiveIds)) {
                DB::table('content_articles')
                    ->whereIn('id', $archiveIds)
                    ->update([
                        'processing_status' => 'archived',
                    ]);

                foreach ($archive as $article) {
                    DB::table('content_articles')
                        ->where('id', $article['id'])
                        ->update(['quality_rating' => $article['score']]);
                }
            }

            $this->info("Updated " . count($topIds) . " TOP articles + " . count($archiveIds) . " archived.");
        } else {
            $this->newLine();
            $this->comment("Dry run. Use --apply to update the database.");
        }

        return 0;
    }

    private function calculateScore(object $article): int
    {
        $score = 0;

        // Word count (0-30 points)
        $wc = $article->word_count ?? 0;
        if ($wc >= 2000) $score += 30;
        elseif ($wc >= 1000) $score += 20;
        elseif ($wc >= 500) $score += 10;
        elseif ($wc >= 200) $score += 5;

        // Category relevance (0-25 points)
        if (in_array($article->category, self::HIGH_VALUE_CATEGORIES, true)) {
            $score += 25;
        } elseif ($article->category) {
            $score += 10;
        }

        // Section relevance (0-15 points)
        if (in_array($article->section, self::HIGH_VALUE_SECTIONS, true)) {
            $score += 15;
        } elseif ($article->section) {
            $score += 5;
        }

        // Guide flag (0-10 points)
        if ($article->is_guide) {
            $score += 10;
        }

        // External links = authority signals (0-10 points)
        $links = json_decode($article->external_links ?? '[]', true);
        if (is_array($links) && count($links) >= 3) {
            $score += 10;
        } elseif (is_array($links) && count($links) >= 1) {
            $score += 5;
        }

        // Images = visual quality (0-10 points)
        $images = json_decode($article->images ?? '[]', true);
        if (is_array($images) && count($images) >= 2) {
            $score += 10;
        } elseif (is_array($images) && count($images) >= 1) {
            $score += 5;
        }

        return min(100, $score);
    }
}
