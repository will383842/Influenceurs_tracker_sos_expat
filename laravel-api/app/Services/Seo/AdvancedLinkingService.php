<?php

namespace App\Services\Seo;

use App\Models\GeneratedArticle;
use Illuminate\Support\Facades\Log;

/**
 * Advanced internal linking engine — TF-IDF similarity + anchor text diversification.
 * Replaces basic keyword overlap with cosine similarity for relevance scoring.
 */
class AdvancedLinkingService
{
    /** French stopwords to exclude from TF vectors. */
    private const STOPWORDS = [
        'le', 'la', 'les', 'de', 'du', 'des', 'un', 'une', 'et', 'est', 'en',
        'pour', 'avec', 'dans', 'sur', 'par', 'au', 'aux', 'ce', 'cette', 'ces',
        'qui', 'que', 'dont', 'où', 'mais', 'ou', 'ni', 'car', 'donc', 'si',
        'ne', 'pas', 'plus', 'très', 'tout', 'même', 'autre', 'quel', 'aussi',
        'comme', 'son', 'ses', 'sa', 'se', 'leur', 'leurs', 'nous', 'vous',
        'ils', 'elles', 'il', 'elle', 'on', 'être', 'avoir', 'faire', 'dire',
        'aller', 'voir', 'venir', 'pouvoir', 'vouloir', 'devoir', 'falloir',
        'à', 'a', 'ai', 'y', 'me', 'te', 'lui', 'je', 'tu', 'nos', 'vos',
        'mon', 'ton', 'ma', 'ta', 'mes', 'tes', 'ces', 'cet', 'cette',
        'des', 'une', 'bien', 'peu', 'trop', 'assez', 'encore', 'déjà',
        'ici', 'là', 'alors', 'puis', 'après', 'avant', 'depuis', 'pendant',
        'entre', 'vers', 'chez', 'sans', 'sous', 'the', 'and', 'is', 'in',
        'to', 'of', 'for', 'it', 'that', 'this', 'with', 'are', 'was',
        'be', 'as', 'at', 'by', 'an', 'or', 'not', 'but', 'from', 'on',
    ];

    /** Anchor text type weights for random selection. */
    private const ANCHOR_WEIGHTS = [
        'exact'          => 70,
        'partial'        => 15,
        'branded'        => 5,
        'conversational' => 10,
    ];

    /** Maximum links per H2 section for uniform distribution. */
    private const MAX_LINKS_PER_SECTION = 2;

    /**
     * Suggest internal links for an article using TF-IDF similarity.
     *
     * @return array<array{target_id: int, target_title: string, target_url: string, anchor_text: string, anchor_type: string, relevance_score: float, best_position: string, best_position_index: int}>
     */
    public function suggestLinks(GeneratedArticle $article, int $maxLinks = 7): array
    {
        try {
            $html = $article->content_html ?? '';

            if (empty($html)) {
                return [];
            }

            $text = $this->extractText($html);
            $sourceTf = $this->calculateTfIdf($text);

            if (empty($sourceTf)) {
                return [];
            }

            // Find candidates: same language, published, not self, not translations
            $candidates = GeneratedArticle::published()
                ->where('language', $article->language)
                ->where('id', '!=', $article->id)
                ->whereNull('parent_article_id')
                ->select(['id', 'title', 'slug', 'content_html', 'language', 'country', 'content_type', 'keywords_primary', 'pillar_article_id'])
                ->get();

            if ($candidates->isEmpty()) {
                return [];
            }

            // Score each candidate
            $scored = [];

            foreach ($candidates as $candidate) {
                $candidateText = $this->extractText($candidate->content_html ?? '');
                $candidateTf = $this->calculateTfIdf($candidateText);

                if (empty($candidateTf)) {
                    continue;
                }

                $similarity = $this->calculateSimilarity($sourceTf, $candidateTf);

                // Bonus: same country (+0.15), same category (+0.10), same cluster (+0.10)
                if (!empty($article->country) && $article->country === $candidate->country) {
                    $similarity += 0.15;
                }
                if (!empty($article->content_type) && $article->content_type === $candidate->content_type) {
                    $similarity += 0.10;
                }
                if (!empty($article->pillar_article_id) && $article->pillar_article_id === $candidate->pillar_article_id) {
                    $similarity += 0.10;
                }

                $scored[] = [
                    'candidate'       => $candidate,
                    'relevance_score' => min(1.0, $similarity),
                ];
            }

            // Sort by relevance
            usort($scored, fn (array $a, array $b) => $b['relevance_score'] <=> $a['relevance_score']);

            // Take top N
            $scored = array_slice($scored, 0, $maxLinks);

            // Generate suggestions with diversified anchors and best positions
            $suggestions = [];
            $usedAnchors = [];
            $sectionLinkCounts = [];

            foreach ($scored as $item) {
                $candidate = $item['candidate'];

                // Generate diversified anchor text
                $anchorType = $this->pickAnchorType();
                $anchorText = $this->generateAnchorText($candidate, $anchorType, $usedAnchors);
                $usedAnchors[] = mb_strtolower($anchorText);

                // Find best position
                $position = $this->findBestPosition($html, $candidate);
                $sectionIdx = $position['section_index'] ?? 0;

                // Enforce max links per section
                $sectionLinkCounts[$sectionIdx] = ($sectionLinkCounts[$sectionIdx] ?? 0) + 1;
                if ($sectionLinkCounts[$sectionIdx] > self::MAX_LINKS_PER_SECTION) {
                    // Try next section
                    $position = $this->findAlternatePosition($html, $sectionLinkCounts);
                }

                $suggestions[] = [
                    'target_id'          => $candidate->id,
                    'target_title'       => $candidate->title,
                    'target_url'         => $candidate->url ?? "/{$candidate->language}/blog/{$candidate->slug}",
                    'anchor_text'        => $anchorText,
                    'anchor_type'        => $anchorType,
                    'relevance_score'    => round($item['relevance_score'], 3),
                    'best_position'      => $position['sentence'],
                    'best_position_index' => $position['index'],
                ];
            }

            Log::info('Advanced link suggestions generated', [
                'article_id'       => $article->id,
                'suggestions_count' => count($suggestions),
            ]);

            return $suggestions;
        } catch (\Throwable $e) {
            Log::error('Advanced link suggestion failed', [
                'article_id' => $article->id ?? null,
                'message'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Inject link suggestions into article HTML.
     */
    public function injectLinks(GeneratedArticle $article, array $suggestions): string
    {
        $html = $article->content_html ?? '';

        if (empty($html) || empty($suggestions)) {
            return $html;
        }

        foreach ($suggestions as $suggestion) {
            $url = $suggestion['target_url'];
            $anchor = $suggestion['anchor_text'];
            $sentence = $suggestion['best_position'];

            $link = "<a href=\"{$url}\" title=\"{$anchor}\">{$anchor}</a>";

            // Try to find the anchor text in the sentence within the HTML
            if (mb_stripos($html, $anchor) !== false) {
                // Replace first occurrence (case-preserving)
                $pos = mb_stripos($html, $anchor);
                $original = mb_substr($html, $pos, mb_strlen($anchor));

                // Only replace if not already inside a link
                $before = mb_substr($html, max(0, $pos - 20), 20);
                if (mb_strpos($before, '<a ') === false && mb_strpos($before, 'href') === false) {
                    $html = mb_substr($html, 0, $pos) . "<a href=\"{$url}\">{$original}</a>" . mb_substr($html, $pos + mb_strlen($anchor));
                }
            } else {
                // Append link at end of the best-matched paragraph
                $paragraphIdx = $suggestion['best_position_index'];
                $html = $this->appendLinkToParagraph($html, $paragraphIdx, $link);
            }
        }

        $article->update(['content_html' => $html]);

        return $html;
    }

    // ============================================================
    // TF-IDF engine
    // ============================================================

    /**
     * Calculate TF (term frequency) vector for a text.
     *
     * @return array<string, float> word => tf_score
     */
    private function calculateTfIdf(string $text): array
    {
        $text = mb_strtolower($text);

        // Tokenize
        $words = preg_split('/[\s\p{P}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove stopwords and short words
        $stopwordsFlipped = array_flip(self::STOPWORDS);
        $filtered = [];

        foreach ($words as $word) {
            if (mb_strlen($word) < 3) {
                continue;
            }
            if (isset($stopwordsFlipped[$word])) {
                continue;
            }
            $filtered[] = $word;
        }

        $totalWords = count($filtered);

        if ($totalWords === 0) {
            return [];
        }

        // Count frequencies
        $frequencies = array_count_values($filtered);

        // Calculate TF
        $tf = [];
        foreach ($frequencies as $word => $count) {
            $tf[$word] = $count / $totalWords;
        }

        return $tf;
    }

    /**
     * Calculate cosine similarity between two TF vectors.
     */
    private function calculateSimilarity(array $tfA, array $tfB): float
    {
        // Get all terms from both vectors
        $allTerms = array_unique(array_merge(array_keys($tfA), array_keys($tfB)));

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        foreach ($allTerms as $term) {
            $a = $tfA[$term] ?? 0.0;
            $b = $tfB[$term] ?? 0.0;

            $dotProduct += $a * $b;
            $magnitudeA += $a * $a;
            $magnitudeB += $b * $b;
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    // ============================================================
    // Anchor text generation
    // ============================================================

    /**
     * Pick an anchor type based on weighted random selection.
     */
    private function pickAnchorType(): string
    {
        $total = array_sum(self::ANCHOR_WEIGHTS);
        $rand = mt_rand(1, $total);

        $cumulative = 0;
        foreach (self::ANCHOR_WEIGHTS as $type => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $type;
            }
        }

        return 'exact';
    }

    /**
     * Generate diversified anchor text for a target article.
     */
    private function generateAnchorText(GeneratedArticle $target, string $type, array $usedAnchors): string
    {
        $keyword = $target->keywords_primary ?? '';
        $title = $target->title ?? '';

        $anchor = match ($type) {
            'exact' => !empty($keyword) ? $keyword : $this->cleanTitle($title),
            'partial' => $this->generatePartialAnchor($title, $keyword),
            'branded' => $this->generateBrandedAnchor($title),
            'conversational' => $this->generateConversationalAnchor(),
            default => $this->cleanTitle($title),
        };

        // Ensure no duplicates
        $attempts = 0;
        while (in_array(mb_strtolower($anchor), $usedAnchors) && $attempts < 5) {
            $anchor = $this->generatePartialAnchor($title, $keyword);
            $attempts++;
        }

        return $anchor;
    }

    /**
     * Clean title for use as anchor: remove site name, trailing separators.
     */
    private function cleanTitle(string $title): string
    {
        // Remove common separators and site name patterns
        $title = preg_replace('/\s*[\|—–-]\s*SOS.*/i', '', $title);
        $title = preg_replace('/\s*[\|—–-]\s*$/', '', $title);

        return trim($title);
    }

    /**
     * Generate a partial anchor from title or keyword.
     */
    private function generatePartialAnchor(string $title, string $keyword): string
    {
        if (!empty($keyword)) {
            // Take first 3-5 words of the keyword/title
            $words = preg_split('/\s+/', $keyword, -1, PREG_SPLIT_NO_EMPTY);
            $take = min(count($words), mt_rand(3, 5));

            return implode(' ', array_slice($words, 0, $take));
        }

        $words = preg_split('/\s+/', $title, -1, PREG_SPLIT_NO_EMPTY);
        $take = min(count($words), mt_rand(3, 5));

        return implode(' ', array_slice($words, 0, $take));
    }

    /**
     * Generate a branded anchor text.
     */
    private function generateBrandedAnchor(string $title): string
    {
        $templates = [
            'notre guide sur %s',
            'notre article sur %s',
            'le guide SOS-Expat sur %s',
        ];

        // Extract a short topic from the title (first 4 words max)
        $words = preg_split('/\s+/', $title, -1, PREG_SPLIT_NO_EMPTY);
        $topic = implode(' ', array_slice($words, 0, min(4, count($words))));
        $topic = mb_strtolower($topic);

        $template = $templates[array_rand($templates)];

        return sprintf($template, $topic);
    }

    /**
     * Generate a conversational anchor text.
     */
    private function generateConversationalAnchor(): string
    {
        $options = [
            'en savoir plus sur ce sujet',
            'consultez notre guide complet',
            'découvrez tous les détails',
            'lisez notre article dédié',
            'tout savoir dans notre guide',
        ];

        return $options[array_rand($options)];
    }

    // ============================================================
    // Position finding
    // ============================================================

    /**
     * Find the best paragraph to place a link based on TF similarity with the target.
     *
     * @return array{sentence: string, index: int, section_index: int}
     */
    private function findBestPosition(string $html, GeneratedArticle $target): array
    {
        // Split HTML into paragraphs
        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $paragraphs, PREG_SET_ORDER);

        if (empty($paragraphs)) {
            return ['sentence' => '', 'index' => 0, 'section_index' => 0];
        }

        $targetText = $this->extractText($target->content_html ?? $target->title ?? '');
        $targetTf = $this->calculateTfIdf($targetText);

        if (empty($targetTf)) {
            // Fallback: middle paragraph
            $midIdx = (int) floor(count($paragraphs) / 2);

            return [
                'sentence'      => strip_tags($paragraphs[$midIdx][1] ?? ''),
                'index'         => $midIdx,
                'section_index' => $this->getSectionIndex($html, $midIdx),
            ];
        }

        $bestScore = -1;
        $bestIdx = 0;

        foreach ($paragraphs as $idx => $paragraph) {
            $paraText = strip_tags($paragraph[1] ?? '');
            $paraTf = $this->calculateTfIdf($paraText);

            if (empty($paraTf)) {
                continue;
            }

            $score = $this->calculateSimilarity($paraTf, $targetTf);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIdx = $idx;
            }
        }

        return [
            'sentence'      => strip_tags($paragraphs[$bestIdx][1] ?? ''),
            'index'         => $bestIdx,
            'section_index' => $this->getSectionIndex($html, $bestIdx),
        ];
    }

    /**
     * Find an alternate position when a section already has max links.
     */
    private function findAlternatePosition(string $html, array $sectionLinkCounts): array
    {
        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $paragraphs, PREG_SET_ORDER);

        if (empty($paragraphs)) {
            return ['sentence' => '', 'index' => 0, 'section_index' => 0];
        }

        // Find a section with fewer links
        foreach ($paragraphs as $idx => $paragraph) {
            $sectionIdx = $this->getSectionIndex($html, $idx);

            if (($sectionLinkCounts[$sectionIdx] ?? 0) < self::MAX_LINKS_PER_SECTION) {
                return [
                    'sentence'      => strip_tags($paragraph[1] ?? ''),
                    'index'         => $idx,
                    'section_index' => $sectionIdx,
                ];
            }
        }

        // Fallback: middle of article
        $midIdx = (int) floor(count($paragraphs) / 2);

        return [
            'sentence'      => strip_tags($paragraphs[$midIdx][1] ?? ''),
            'index'         => $midIdx,
            'section_index' => $this->getSectionIndex($html, $midIdx),
        ];
    }

    /**
     * Determine which H2 section a paragraph index belongs to.
     */
    private function getSectionIndex(string $html, int $paragraphIndex): int
    {
        // Count H2 tags before the nth paragraph
        $sectionIndex = 0;
        $paraCount = 0;

        $parts = preg_split('/(<h2[^>]*>.*?<\/h2>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $part) {
            if (preg_match('/<h2[^>]*>/i', $part)) {
                $sectionIndex++;
                continue;
            }

            // Count paragraphs in this part
            $paraInPart = preg_match_all('/<p[^>]*>/i', $part);
            if ($paraCount + $paraInPart > $paragraphIndex) {
                return $sectionIndex;
            }
            $paraCount += $paraInPart;
        }

        return $sectionIndex;
    }

    /**
     * Append a link to a specific paragraph by index.
     */
    private function appendLinkToParagraph(string $html, int $paragraphIndex, string $link): string
    {
        $count = 0;

        return preg_replace_callback('/<\/p>/i', function (array $match) use (&$count, $paragraphIndex, $link) {
            if ($count === $paragraphIndex) {
                $count++;
                return " {$link}</p>";
            }
            $count++;
            return $match[0];
        }, $html);
    }

    /**
     * Extract plain text from HTML.
     */
    private function extractText(string $html): string
    {
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<\/(p|div|h[1-6]|li|tr|td|th|br|hr)[^>]*>/i', ' ', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/\s+/', ' ', trim($text));
    }
}
