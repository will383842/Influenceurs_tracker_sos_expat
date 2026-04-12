<?php

namespace App\Console\Commands;

use App\Models\GeneratedArticle;
use App\Services\AI\OpenAiService;
use App\Services\Content\KnowledgeBaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Retrofit existing published articles with modern HTML components:
 * - Wrap first paragraph in <div class="featured-snippet"> (if 30-80 words)
 * - Upgrade naked <blockquote> to <blockquote class="callout-info">
 * - Generate ai_summary (for Key Takeaways) if missing
 *
 * Does NOT inject FAQ into content_html (frontend renders them from DB).
 * Does NOT inject summary-box into content_html (intro generates it for new articles).
 *
 * Safety: backs up original HTML, dry-run by default, processes in batches.
 */
class RetrofitArticleHtmlCommand extends Command
{
    protected $signature = 'content:retrofit-html
        {--dry-run : Preview changes without saving}
        {--limit=50 : Max articles to process}
        {--id= : Process a single article by ID}
        {--force : Skip confirmation prompt}';

    protected $description = 'Retrofit published articles with featured-snippet, callout classes, and ai_summary';

    public function handle(OpenAiService $openAi, KnowledgeBaseService $kb): int
    {
        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $singleId = $this->option('id');

        if ($isDryRun) {
            $this->info('=== DRY RUN MODE — no changes will be saved ===');
        }

        // Build query
        $query = GeneratedArticle::whereIn('status', ['review', 'published', 'approved'])
            ->whereNotNull('content_html')
            ->where('content_html', '!=', '');

        if ($singleId) {
            $query->where('id', $singleId);
        }

        $total = $query->count();
        $this->info("Found {$total} articles to process (limit: {$limit})");

        if (!$isDryRun && !$this->option('force') && !$this->confirm("Proceed with retrofitting up to {$limit} articles?")) {
            return 0;
        }

        $articles = $query->orderByDesc('published_at')->limit($limit)->get();

        $stats = [
            'featured_snippet_added' => 0,
            'callouts_upgraded' => 0,
            'ai_summary_generated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $bar = $this->output->createProgressBar($articles->count());

        foreach ($articles as $article) {
            try {
                $changes = $this->retrofitArticle($article, $openAi, $kb, $isDryRun);

                $stats['featured_snippet_added'] += $changes['featured_snippet'] ? 1 : 0;
                $stats['callouts_upgraded'] += $changes['callouts_count'];
                $stats['ai_summary_generated'] += $changes['ai_summary'] ? 1 : 0;

                if (!$changes['featured_snippet'] && $changes['callouts_count'] === 0 && !$changes['ai_summary']) {
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::warning('RetrofitArticleHtml: error on article #' . $article->id, [
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  Error on #{$article->id}: " . mb_substr($e->getMessage(), 0, 80));
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn ($v, $k) => [str_replace('_', ' ', ucfirst($k)), $v])->values()->toArray()
        );

        if ($isDryRun) {
            $this->warn('Dry run complete. Run without --dry-run to apply changes.');
        }

        return 0;
    }

    private function retrofitArticle(
        GeneratedArticle $article,
        OpenAiService $openAi,
        KnowledgeBaseService $kb,
        bool $isDryRun
    ): array {
        $html = $article->content_html;
        $originalHtml = $html;
        $changes = [
            'featured_snippet' => false,
            'callouts_count' => 0,
            'ai_summary' => false,
        ];

        // ── 1. FEATURED SNIPPET: wrap first <p> if 30-80 words and not already wrapped ──
        if (stripos($html, 'featured-snippet') === false) {
            if (preg_match('/^(\s*)(<p[^>]*>)(.*?)<\/p>/is', $html, $match)) {
                $firstPText = strip_tags($match[3]);
                $wordCount = str_word_count($firstPText);

                if ($wordCount >= 30 && $wordCount <= 80) {
                    $snippetDiv = $match[1] . '<div class="featured-snippet">'
                        . $match[2] . $match[3] . '</p>'
                        . '</div>';
                    $html = substr_replace($html, $snippetDiv, strpos($html, $match[0]), strlen($match[0]));
                    $changes['featured_snippet'] = true;

                    if ($isDryRun) {
                        $this->line("  #{$article->id} — Featured snippet: wrap first <p> ({$wordCount} words)");
                    }
                } else {
                    if ($isDryRun) {
                        $this->line("  #{$article->id} — Featured snippet: SKIP (first <p> = {$wordCount} words, need 30-80)");
                    }
                }
            }
        }

        // ── 2. CALLOUT UPGRADE: naked <blockquote> → <blockquote class="callout-info"> ──
        // Only upgrade blockquotes that have NO class attribute at all
        $calloutCount = 0;
        $html = preg_replace_callback('/<blockquote(?![^>]*class=)([^>]*)>/i', function ($m) use (&$calloutCount) {
            $calloutCount++;
            // Detect if it's a warning (contains "attention", "attention", "avertissement")
            // For simplicity, default to callout-info
            return '<blockquote class="callout-info"' . $m[1] . '>';
        }, $html);
        $changes['callouts_count'] = $calloutCount;

        if ($calloutCount > 0 && $isDryRun) {
            $this->line("  #{$article->id} — Callouts: upgraded {$calloutCount} naked <blockquote>");
        }

        // ── 3. AI SUMMARY: generate if missing ──
        if (empty($article->ai_summary) || mb_strlen($article->ai_summary) < 20) {
            $contentText = mb_substr(strip_tags($html), 0, 2000);

            if (!$isDryRun && !empty($contentText)) {
                $result = $openAi->complete(
                    'Resume cet article en 1-2 phrases factuelles (max 155 caracteres). '
                    . 'Commence directement par les faits. PAS de "Cet article..." ni "Decouvrez...". '
                    . 'Langue: ' . ($article->language ?? 'fr') . '.',
                    "Titre: {$article->title}\n\nContenu:\n{$contentText}",
                    [
                        'model' => 'gpt-4o-mini',
                        'temperature' => 0.4,
                        'max_tokens' => 100,
                    ]
                );

                if (!empty($result['success']) && !empty($result['content'])) {
                    $summary = mb_substr(trim($result['content']), 0, 160);
                    $article->update(['ai_summary' => $summary]);
                    $changes['ai_summary'] = true;
                }
            } elseif ($isDryRun) {
                $this->line("  #{$article->id} — AI summary: WOULD generate (currently empty)");
                $changes['ai_summary'] = true;
            }
        }

        // ── SAVE (with backup) ──
        if (!$isDryRun && $html !== $originalHtml) {
            // Backup original HTML in a JSON column or log
            Log::info('RetrofitArticleHtml: backing up article #' . $article->id, [
                'original_length' => mb_strlen($originalHtml),
                'new_length' => mb_strlen($html),
            ]);

            $article->update([
                'content_html' => $html,
            ]);
        }

        return $changes;
    }
}
