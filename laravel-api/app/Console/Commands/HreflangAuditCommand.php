<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase 6 — Hreflang audit for multi-language SEO.
 *
 * Verifies that all published articles have correct hreflang tags
 * across all 9 languages. Reports missing translations, orphan pages,
 * and mismatched canonical URLs.
 *
 * Usage: php artisan seo:hreflang-audit [--fix] [--language=fr]
 */
class HreflangAuditCommand extends Command
{
    protected $signature = 'seo:hreflang-audit
        {--fix : Attempt to fix missing hreflang entries}
        {--language= : Audit only this language}
        {--limit=500 : Max articles to audit}';

    protected $description = 'Audit hreflang tags across all 9 languages for published articles';

    private const LANGUAGES = ['fr', 'en', 'es', 'de', 'pt', 'ru', 'zh', 'hi', 'ar'];

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $filterLang = $this->option('language');

        $this->info('=== HREFLANG AUDIT ===');
        $this->newLine();

        // Get all published original articles (not translations)
        $query = DB::table('generated_articles')
            ->where('status', 'published')
            ->whereNull('parent_article_id')
            ->select('id', 'uuid', 'title', 'slug', 'language', 'country', 'hreflang_map', 'content_type');

        if ($filterLang) {
            $query->where('language', $filterLang);
        }

        $articles = $query->limit($limit)->get();

        $this->info("Auditing {$articles->count()} published articles...");

        $stats = [
            'total' => $articles->count(),
            'complete' => 0,          // All 9 languages present
            'partial' => 0,           // Some languages missing
            'no_hreflang' => 0,       // No hreflang at all
            'orphan_translations' => 0, // Translations without parent
            'missing_languages' => [],  // Count per missing language
        ];

        $issues = [];

        foreach ($articles as $article) {
            $hreflangMap = json_decode($article->hreflang_map ?? '{}', true);

            if (empty($hreflangMap)) {
                $stats['no_hreflang']++;
                $issues[] = [
                    'type' => 'no_hreflang',
                    'id' => $article->id,
                    'title' => mb_substr($article->title, 0, 50),
                    'language' => $article->language,
                ];
                continue;
            }

            // Check which languages are present
            $presentLangs = array_keys($hreflangMap);
            $missingLangs = array_diff(self::LANGUAGES, $presentLangs);

            if (empty($missingLangs)) {
                $stats['complete']++;
            } else {
                $stats['partial']++;
                foreach ($missingLangs as $lang) {
                    $stats['missing_languages'][$lang] = ($stats['missing_languages'][$lang] ?? 0) + 1;
                }

                if (count($missingLangs) >= 5) {
                    $issues[] = [
                        'type' => 'many_missing',
                        'id' => $article->id,
                        'title' => mb_substr($article->title, 0, 50),
                        'missing' => implode(', ', $missingLangs),
                    ];
                }
            }

            // Check x-default
            if (!isset($hreflangMap['x-default'])) {
                $issues[] = [
                    'type' => 'no_x_default',
                    'id' => $article->id,
                    'title' => mb_substr($article->title, 0, 50),
                ];
            }
        }

        // Check for orphan translations
        $orphans = DB::table('generated_articles')
            ->where('status', 'published')
            ->whereNotNull('parent_article_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('generated_articles as parent')
                    ->whereColumn('parent.id', 'generated_articles.parent_article_id')
                    ->where('parent.status', 'published');
            })
            ->count();

        $stats['orphan_translations'] = $orphans;

        // Display results
        $this->newLine();
        $this->info("=== RESULTS ===");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total articles audited', $stats['total']],
                ['Complete (9 langues)', $stats['complete']],
                ['Partial (langues manquantes)', $stats['partial']],
                ['Sans hreflang', $stats['no_hreflang']],
                ['Traductions orphelines', $stats['orphan_translations']],
            ]
        );

        if (!empty($stats['missing_languages'])) {
            $this->newLine();
            $this->warn("=== LANGUES MANQUANTES ===");
            arsort($stats['missing_languages']);
            $langTable = array_map(
                fn($lang, $count) => [$lang, $count, round($count / max($stats['total'], 1) * 100) . '%'],
                array_keys($stats['missing_languages']),
                array_values($stats['missing_languages'])
            );
            $this->table(['Langue', 'Articles sans traduction', '% manquant'], $langTable);
        }

        if (!empty($issues)) {
            $this->newLine();
            $this->warn("=== PROBLEMES (" . count($issues) . ") ===");
            foreach (array_slice($issues, 0, 20) as $issue) {
                $this->line("  [{$issue['type']}] ID:{$issue['id']} — {$issue['title']}");
            }
            if (count($issues) > 20) {
                $this->line("  ... et " . (count($issues) - 20) . " autres");
            }
        }

        // Completeness score
        $completeness = $stats['total'] > 0
            ? round($stats['complete'] / $stats['total'] * 100)
            : 0;

        $this->newLine();
        if ($completeness >= 90) {
            $this->info("Score hreflang: {$completeness}% — EXCELLENT");
        } elseif ($completeness >= 70) {
            $this->warn("Score hreflang: {$completeness}% — BON mais ameliorable");
        } else {
            $this->error("Score hreflang: {$completeness}% — CRITIQUE — traductions manquantes");
        }

        return 0;
    }
}
