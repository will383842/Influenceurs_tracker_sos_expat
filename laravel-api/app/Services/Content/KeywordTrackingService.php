<?php

namespace App\Services\Content;

use App\Models\ArticleKeyword;
use App\Models\GeneratedArticle;
use App\Models\KeywordTracking;
use App\Services\PerplexitySearchService;
use Illuminate\Support\Facades\Log;

/**
 * Keyword tracking — tracks keyword usage across articles,
 * detects gaps, and identifies cannibalization risks.
 */
class KeywordTrackingService
{
    public function __construct(
        private PerplexitySearchService $perplexity,
    ) {}

    /**
     * Track keywords for a given article.
     *
     * @param array<array{keyword: string, type: string, usage_type: string, density_percent: float, position_context: string}> $keywords
     */
    public function trackKeywordsForArticle(GeneratedArticle $article, array $keywords): void
    {
        try {
            foreach ($keywords as $kw) {
                $keyword = $kw['keyword'] ?? '';
                if (empty(trim($keyword))) {
                    continue;
                }

                // Find or create keyword tracking record
                $tracking = KeywordTracking::firstOrCreate(
                    [
                        'keyword' => mb_strtolower(trim($keyword)),
                        'language' => $article->language ?? 'fr',
                    ],
                    [
                        'type' => $kw['type'] ?? 'secondary',
                        'country' => $article->country,
                        'category' => $article->content_type ?? 'article',
                        'articles_using_count' => 0,
                        'first_used_at' => now(),
                    ]
                );

                // Create or update pivot
                $existingPivot = ArticleKeyword::where('article_id', $article->id)
                    ->where('keyword_id', $tracking->id)
                    ->first();

                if ($existingPivot) {
                    $existingPivot->update([
                        'usage_type' => $kw['usage_type'] ?? 'content',
                        'density_percent' => $kw['density_percent'] ?? 0,
                        'position_context' => $kw['position_context'] ?? null,
                    ]);
                } else {
                    ArticleKeyword::create([
                        'article_id' => $article->id,
                        'keyword_id' => $tracking->id,
                        'usage_type' => $kw['usage_type'] ?? 'content',
                        'density_percent' => $kw['density_percent'] ?? 0,
                        'occurrences' => $kw['occurrences'] ?? 1,
                        'position_context' => $kw['position_context'] ?? null,
                    ]);

                    // Increment articles_using_count
                    $tracking->increment('articles_using_count');
                }
            }

            Log::info('KeywordTracking: keywords tracked', [
                'article_id' => $article->id,
                'keywords_count' => count($keywords),
            ]);
        } catch (\Throwable $e) {
            Log::error('KeywordTracking: tracking failed', [
                'article_id' => $article->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Analyze keywords present in an article's content.
     *
     * @return array<array{keyword: string, location: string, density: float, occurrences: int}>
     */
    public function analyzeArticleKeywords(GeneratedArticle $article): array
    {
        try {
            $html = $article->content_html ?? '';
            $analysis = [];

            // Extract primary keyword
            $primary = $article->keywords_primary ?? '';
            $secondary = $article->keywords_secondary ?? [];

            $allKeywords = array_filter(array_merge([$primary], $secondary));
            $contentText = mb_strtolower(strip_tags($html));
            $totalWords = str_word_count($contentText);

            if ($totalWords === 0) {
                return [];
            }

            // Extract text from specific locations
            $h1Text = $this->extractTagContent($html, 'h1');
            $h2Text = $this->extractTagContent($html, 'h2');
            $metaTitle = mb_strtolower($article->meta_title ?? '');
            $metaDesc = mb_strtolower($article->meta_description ?? '');

            // Get first paragraph text
            preg_match('/<p[^>]*>(.*?)<\/p>/is', $html, $firstParagraph);
            $firstParagraphText = mb_strtolower(strip_tags($firstParagraph[1] ?? ''));

            foreach ($allKeywords as $keyword) {
                if (empty(trim($keyword))) {
                    continue;
                }

                $kwLower = mb_strtolower($keyword);
                $occurrences = mb_substr_count($contentText, $kwLower);
                $density = ($totalWords > 0) ? round(($occurrences / $totalWords) * 100, 2) : 0;

                $locations = [];
                if (mb_strpos($h1Text, $kwLower) !== false) {
                    $locations[] = 'h1';
                }
                if (mb_strpos($h2Text, $kwLower) !== false) {
                    $locations[] = 'h2';
                }
                if (mb_strpos($metaTitle, $kwLower) !== false) {
                    $locations[] = 'meta_title';
                }
                if (mb_strpos($metaDesc, $kwLower) !== false) {
                    $locations[] = 'meta_description';
                }
                if (mb_strpos($firstParagraphText, $kwLower) !== false) {
                    $locations[] = 'first_paragraph';
                }

                $analysis[] = [
                    'keyword' => $keyword,
                    'location' => implode(', ', $locations),
                    'density' => $density,
                    'occurrences' => $occurrences,
                ];
            }

            return $analysis;
        } catch (\Throwable $e) {
            Log::error('KeywordTracking: analysis failed', [
                'article_id' => $article->id,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Find keyword gaps for a given country + category + language.
     *
     * @return array<array{keyword: string, type: string, covered: bool, suggested_priority: string}>
     */
    public function findKeywordGaps(string $country, string $category, string $language): array
    {
        try {
            // Get keywords currently used in published articles
            $usedKeywords = KeywordTracking::where('country', $country)
                ->where('language', $language)
                ->pluck('keyword')
                ->map(fn ($k) => mb_strtolower($k))
                ->toArray();

            // Hardcoded essential keyword categories for expat content
            $essentialKeywords = $this->getEssentialKeywords($category, $language);

            $gaps = [];

            foreach ($essentialKeywords as $kw) {
                $isUsed = in_array(mb_strtolower($kw['keyword']), $usedKeywords, true);
                $gaps[] = [
                    'keyword' => $kw['keyword'],
                    'type' => $kw['type'],
                    'covered' => $isUsed,
                    'suggested_priority' => $isUsed ? 'low' : ($kw['priority'] ?? 'medium'),
                ];
            }

            // Also check with Perplexity for trending keywords
            if ($this->perplexity->isConfigured()) {
                $query = "Most searched keywords for {$category} by expatriates in {$country}. "
                    . "Language: {$language}. List the top 10 keywords.";

                $result = $this->perplexity->search($query, $language);

                if ($result['success'] && !empty($result['text'])) {
                    $lines = explode("\n", $result['text']);
                    foreach ($lines as $line) {
                        $line = trim($line, " \t\n\r\0\x0B-.*1234567890)");
                        if (mb_strlen($line) > 3 && mb_strlen($line) < 100) {
                            $lineLower = mb_strtolower($line);
                            if (!in_array($lineLower, $usedKeywords, true)) {
                                $gaps[] = [
                                    'keyword' => $line,
                                    'type' => 'trending',
                                    'covered' => false,
                                    'suggested_priority' => 'high',
                                ];
                            }
                        }
                    }
                }
            }

            return $gaps;
        } catch (\Throwable $e) {
            Log::error('KeywordTracking: gap analysis failed', [
                'country' => $country,
                'category' => $category,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check for keyword cannibalization (same primary keyword in 2+ articles).
     *
     * @return array<array{keyword: string, articles: array, severity: string}>
     */
    public function checkCannibalization(string $language): array
    {
        try {
            // Find keywords used as 'primary' in 2+ different articles
            $cannibalized = ArticleKeyword::where('usage_type', 'primary')
                ->join('keyword_tracking', 'article_keywords.keyword_id', '=', 'keyword_tracking.id')
                ->where('keyword_tracking.language', $language)
                ->select('keyword_tracking.id', 'keyword_tracking.keyword')
                ->groupBy('keyword_tracking.id', 'keyword_tracking.keyword')
                ->havingRaw('COUNT(DISTINCT article_keywords.article_id) >= 2')
                ->get();

            $results = [];

            foreach ($cannibalized as $kw) {
                $articleIds = ArticleKeyword::where('keyword_id', $kw->id)
                    ->where('usage_type', 'primary')
                    ->pluck('article_id');

                $articles = GeneratedArticle::whereIn('id', $articleIds)
                    ->select('id', 'title', 'slug', 'status', 'seo_score')
                    ->get()
                    ->toArray();

                $severity = count($articles) > 3 ? 'high' : (count($articles) > 2 ? 'medium' : 'low');

                $results[] = [
                    'keyword' => $kw->keyword,
                    'articles' => $articles,
                    'severity' => $severity,
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            Log::error('KeywordTracking: cannibalization check failed', [
                'language' => $language,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get essential keywords for a category (hardcoded baseline).
     *
     * @return array<array{keyword: string, type: string, priority: string}>
     */
    private function getEssentialKeywords(string $category, string $language): array
    {
        $keywords = [
            'article' => [
                ['keyword' => 'expatriation', 'type' => 'primary', 'priority' => 'high'],
                ['keyword' => 'visa', 'type' => 'primary', 'priority' => 'high'],
                ['keyword' => 'permis de travail', 'type' => 'primary', 'priority' => 'high'],
                ['keyword' => 'déménagement international', 'type' => 'secondary', 'priority' => 'medium'],
                ['keyword' => 'assurance expatrié', 'type' => 'secondary', 'priority' => 'medium'],
                ['keyword' => 'fiscalité expatrié', 'type' => 'secondary', 'priority' => 'medium'],
                ['keyword' => 'logement expatrié', 'type' => 'secondary', 'priority' => 'medium'],
                ['keyword' => 'coût de la vie', 'type' => 'long_tail', 'priority' => 'medium'],
                ['keyword' => 'scolarité enfants expatriés', 'type' => 'long_tail', 'priority' => 'low'],
                ['keyword' => 'banque pour expatrié', 'type' => 'long_tail', 'priority' => 'low'],
            ],
            'guide' => [
                ['keyword' => 'guide expatriation', 'type' => 'primary', 'priority' => 'high'],
                ['keyword' => 'formalités administratives', 'type' => 'primary', 'priority' => 'high'],
                ['keyword' => 'checklist départ', 'type' => 'secondary', 'priority' => 'medium'],
                ['keyword' => 'inscription consulaire', 'type' => 'secondary', 'priority' => 'medium'],
                ['keyword' => 'protection sociale', 'type' => 'secondary', 'priority' => 'medium'],
            ],
        ];

        if ($language === 'en') {
            $keywords = [
                'article' => [
                    ['keyword' => 'expatriation', 'type' => 'primary', 'priority' => 'high'],
                    ['keyword' => 'visa', 'type' => 'primary', 'priority' => 'high'],
                    ['keyword' => 'work permit', 'type' => 'primary', 'priority' => 'high'],
                    ['keyword' => 'international relocation', 'type' => 'secondary', 'priority' => 'medium'],
                    ['keyword' => 'expat insurance', 'type' => 'secondary', 'priority' => 'medium'],
                    ['keyword' => 'expat tax', 'type' => 'secondary', 'priority' => 'medium'],
                    ['keyword' => 'cost of living', 'type' => 'long_tail', 'priority' => 'medium'],
                ],
                'guide' => [
                    ['keyword' => 'expat guide', 'type' => 'primary', 'priority' => 'high'],
                    ['keyword' => 'administrative procedures', 'type' => 'primary', 'priority' => 'high'],
                    ['keyword' => 'departure checklist', 'type' => 'secondary', 'priority' => 'medium'],
                ],
            ];
        }

        return $keywords[$category] ?? $keywords['article'] ?? [];
    }

    /**
     * Extract text from a specific HTML tag.
     */
    private function extractTagContent(string $html, string $tag): string
    {
        preg_match_all("/<{$tag}[^>]*>(.*?)<\/{$tag}>/is", $html, $matches);

        $texts = array_map(fn ($m) => mb_strtolower(strip_tags($m)), $matches[1] ?? []);

        return implode(' ', $texts);
    }
}
