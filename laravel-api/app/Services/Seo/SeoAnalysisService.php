<?php

namespace App\Services\Seo;

use App\Models\SeoAnalysis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Core native SEO analysis engine.
 * Scores content across 10 dimensions for a total of /100.
 */
class SeoAnalysisService
{
    /**
     * Run full SEO analysis on any content model.
     * The model should have: title/meta_title, meta_description, content_html, json_ld, hreflang_map.
     */
    public function analyze(Model $content): SeoAnalysis
    {
        try {
            $metaTitle = $content->meta_title ?? $content->title ?? '';
            $metaDescription = $content->meta_description ?? '';
            $contentHtml = $content->content_html ?? '';
            $primaryKeyword = $content->keywords_primary ?? '';
            $secondaryKeywords = $content->keywords_secondary ?? [];
            $jsonLd = $content->json_ld ?? null;
            $hreflangMap = $content->hreflang_map ?? null;
            $language = $content->language ?? 'fr';
            $canonicalUrl = $content->canonical_url ?? null;
            $status = $content->status ?? 'draft';
            $featuredImage = $content->featured_image_url ?? null;
            if (!$featuredImage && method_exists($content, 'images')) {
                $featuredImage = $content->images()->orderBy('sort_order')->value('url');
            }

            // Run all scoring methods
            $titleResult = $this->analyzeTitleTag($metaTitle, $primaryKeyword);
            $metaDescResult = $this->analyzeMetaDescription($metaDescription, $primaryKeyword);
            $headingsResult = $this->analyzeHeadings($contentHtml);
            $contentResult = $this->analyzeContent($contentHtml, $primaryKeyword, $secondaryKeywords);
            $imagesResult = $this->analyzeImages($contentHtml, $featuredImage);
            $internalLinksResult = $this->analyzeInternalLinks($content);
            $externalLinksResult = $this->analyzeExternalLinks($content);
            $structuredDataResult = $this->analyzeStructuredData($jsonLd);
            $hreflangResult = $this->analyzeHreflang($hreflangMap, $language);
            $technicalResult = $this->analyzeTechnical($canonicalUrl, $status);

            $overallScore = $titleResult['score']
                + $metaDescResult['score']
                + $headingsResult['score']
                + $contentResult['score']
                + $imagesResult['score']
                + $internalLinksResult['score']
                + $externalLinksResult['score']
                + $structuredDataResult['score']
                + $hreflangResult['score']
                + $technicalResult['score'];

            $allIssues = array_merge(
                array_map(fn ($i) => ['category' => 'title', 'issue' => $i], $titleResult['issues']),
                array_map(fn ($i) => ['category' => 'meta_description', 'issue' => $i], $metaDescResult['issues']),
                array_map(fn ($i) => ['category' => 'headings', 'issue' => $i], $headingsResult['issues']),
                array_map(fn ($i) => ['category' => 'content', 'issue' => $i], $contentResult['issues']),
                array_map(fn ($i) => ['category' => 'images', 'issue' => $i], $imagesResult['issues']),
                array_map(fn ($i) => ['category' => 'internal_links', 'issue' => $i], $internalLinksResult['issues']),
                array_map(fn ($i) => ['category' => 'external_links', 'issue' => $i], $externalLinksResult['issues']),
                array_map(fn ($i) => ['category' => 'structured_data', 'issue' => $i], $structuredDataResult['issues']),
                array_map(fn ($i) => ['category' => 'hreflang', 'issue' => $i], $hreflangResult['issues']),
                array_map(fn ($i) => ['category' => 'technical', 'issue' => $i], $technicalResult['issues']),
            );

            // Create or update SeoAnalysis record
            $seoAnalysis = SeoAnalysis::updateOrCreate(
                [
                    'analyzable_type' => get_class($content),
                    'analyzable_id' => $content->id,
                ],
                [
                    'overall_score' => $overallScore,
                    'title_score' => $titleResult['score'],
                    'meta_description_score' => $metaDescResult['score'],
                    'headings_score' => $headingsResult['score'],
                    'content_score' => $contentResult['score'],
                    'images_score' => $imagesResult['score'],
                    'internal_links_score' => $internalLinksResult['score'],
                    'external_links_score' => $externalLinksResult['score'],
                    'structured_data_score' => $structuredDataResult['score'],
                    'hreflang_score' => $hreflangResult['score'],
                    'technical_score' => $technicalResult['score'],
                    'issues' => $allIssues,
                    'analyzed_at' => now(),
                ]
            );

            // Update the content model's seo_score if it has the attribute
            if (in_array('seo_score', $content->getFillable())) {
                $content->update(['seo_score' => $overallScore]);
            }

            Log::info('SEO analysis complete', [
                'model' => get_class($content),
                'id' => $content->id,
                'overall_score' => $overallScore,
                'issues_count' => count($allIssues),
            ]);

            return $seoAnalysis;
        } catch (\Throwable $e) {
            Log::error('SEO analysis failed', [
                'model' => get_class($content),
                'id' => $content->id ?? null,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Analyze title tag quality. Score /10.
     */
    private function analyzeTitleTag(string $metaTitle, string $primaryKeyword): array
    {
        $score = 10;
        $issues = [];

        $length = mb_strlen($metaTitle);

        // Check length (50-60 ideal)
        if ($length === 0) {
            $score -= 10;
            $issues[] = 'Meta title is empty';
            return ['score' => max(0, $score), 'issues' => $issues];
        }

        if ($length < 30) {
            $score -= 3;
            $issues[] = "Meta title too short ({$length} chars, aim for 50-60)";
        } elseif ($length < 50) {
            $score -= 1;
            $issues[] = "Meta title slightly short ({$length} chars, aim for 50-60)";
        } elseif ($length > 60) {
            $score -= 2;
            $issues[] = "Meta title too long ({$length} chars, will be truncated in SERP)";
        }

        // Check contains primary keyword
        if (!empty($primaryKeyword) && mb_stripos($metaTitle, $primaryKeyword) === false) {
            $score -= 3;
            $issues[] = 'Meta title does not contain primary keyword';
        }

        // Check keyword near start (first 50% of title)
        if (!empty($primaryKeyword)) {
            $keywordPos = mb_stripos($metaTitle, $primaryKeyword);
            if ($keywordPos !== false && $keywordPos > $length * 0.5) {
                $score -= 1;
                $issues[] = 'Primary keyword should be closer to the start of the title';
            }
        }

        // Check for pipe/dash overuse
        $separatorCount = substr_count($metaTitle, '|') + substr_count($metaTitle, ' - ');
        if ($separatorCount > 2) {
            $score -= 1;
            $issues[] = 'Title uses too many separators (|/-)';
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Analyze meta description. Score /10.
     */
    private function analyzeMetaDescription(string $description, string $primaryKeyword): array
    {
        $score = 10;
        $issues = [];

        $length = mb_strlen($description);

        if ($length === 0) {
            $score -= 10;
            $issues[] = 'Meta description is empty';
            return ['score' => max(0, $score), 'issues' => $issues];
        }

        // Check length (140-160 ideal)
        if ($length < 70) {
            $score -= 3;
            $issues[] = "Meta description too short ({$length} chars, aim for 140-160)";
        } elseif ($length < 140) {
            $score -= 1;
            $issues[] = "Meta description slightly short ({$length} chars, aim for 140-160)";
        } elseif ($length > 160) {
            $score -= 2;
            $issues[] = "Meta description too long ({$length} chars, will be truncated)";
        }

        // Check contains primary keyword
        if (!empty($primaryKeyword) && mb_stripos($description, $primaryKeyword) === false) {
            $score -= 3;
            $issues[] = 'Meta description does not contain primary keyword';
        }

        // Check for CTA words (French and English)
        $ctaWords = ['découvrez', 'guide', 'tout savoir', 'apprenez', 'comparez', 'trouvez',
            'discover', 'learn', 'find', 'compare', 'explore', 'get started'];
        $hasCta = false;
        foreach ($ctaWords as $cta) {
            if (mb_stripos($description, $cta) !== false) {
                $hasCta = true;
                break;
            }
        }
        if (!$hasCta) {
            $score -= 2;
            $issues[] = 'Meta description lacks a call-to-action word';
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Analyze heading structure. Score /10.
     */
    private function analyzeHeadings(string $html): array
    {
        $score = 10;
        $issues = [];

        if (empty($html)) {
            return ['score' => 0, 'issues' => ['No content HTML to analyze']];
        }

        $headings = $this->findHeadings($html);

        $h1Count = count(array_filter($headings, fn ($h) => $h['level'] === 1));
        $h2Count = count(array_filter($headings, fn ($h) => $h['level'] === 2));
        $h3Count = count(array_filter($headings, fn ($h) => $h['level'] === 3));

        // Check exactly 1 h1
        if ($h1Count === 0) {
            $score -= 3;
            $issues[] = 'No H1 tag found';
        } elseif ($h1Count > 1) {
            $score -= 2;
            $issues[] = "Multiple H1 tags found ({$h1Count}), should be exactly 1";
        }

        // Check 4-12 h2s (multi-prompt pipeline targets 8-12 sections)
        if ($h2Count === 0) {
            $score -= 3;
            $issues[] = 'No H2 tags found — content needs section headings';
        } elseif ($h2Count < 4) {
            $score -= 2;
            $issues[] = "Only {$h2Count} H2 tags (aim for 6-12 sections)";
        } elseif ($h2Count > 15) {
            $score -= 1;
            $issues[] = "Many H2 tags ({$h2Count}), consider consolidating some sections";
        }

        // Check proper hierarchy (no h3 without preceding h2)
        $lastH2Seen = false;
        foreach ($headings as $heading) {
            if ($heading['level'] === 2) {
                $lastH2Seen = true;
            }
            if ($heading['level'] === 3 && !$lastH2Seen) {
                $score -= 1;
                $issues[] = 'H3 tag appears before any H2 — broken heading hierarchy';
                break;
            }
        }

        // Check no heading level skips (e.g., h1 -> h3 without h2)
        $levels = array_column($headings, 'level');
        for ($i = 1; $i < count($levels); $i++) {
            if ($levels[$i] > $levels[$i - 1] + 1) {
                $score -= 1;
                $issues[] = "Heading level skip detected (H{$levels[$i - 1]} -> H{$levels[$i]})";
                break;
            }
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Analyze content quality. Score /20.
     */
    private function analyzeContent(string $html, string $primaryKeyword, array $secondaryKeywords): array
    {
        $score = 20;
        $issues = [];

        if (empty($html)) {
            return ['score' => 0, 'issues' => ['No content to analyze']];
        }

        $text = $this->extractTextFromHtml($html);
        $wordCount = $this->countWords($text);

        // Check word count — generous ranges for multi-prompt pipeline (targets 2000-7000 by type)
        if ($wordCount < 500) {
            $score -= 6;
            $issues[] = "Content very short ({$wordCount} words, aim for 1500+)";
        } elseif ($wordCount < 1000) {
            $score -= 3;
            $issues[] = "Content short ({$wordCount} words, aim for 1500+)";
        } elseif ($wordCount < 1500) {
            $score -= 1;
            $issues[] = "Content slightly short ({$wordCount} words)";
        }
        // No penalty for long content — pillar articles target 4000-7000 words

        // Check primary keyword density (1-2%)
        if (!empty($primaryKeyword)) {
            $primaryDensity = $this->calculateKeywordDensity($text, $primaryKeyword);

            if ($primaryDensity < 0.5) {
                $score -= 3;
                $issues[] = "Primary keyword density too low ({$primaryDensity}%, aim for 1-2%)";
            } elseif ($primaryDensity < 1.0) {
                $score -= 1;
                $issues[] = "Primary keyword density slightly low ({$primaryDensity}%, aim for 1-2%)";
            } elseif ($primaryDensity > 3.0) {
                $score -= 3;
                $issues[] = "Primary keyword density too high ({$primaryDensity}%), possible keyword stuffing";
            } elseif ($primaryDensity > 2.0) {
                $score -= 1;
                $issues[] = "Primary keyword density slightly high ({$primaryDensity}%)";
            }
        }

        // Check secondary keyword usage (0.5-1%)
        if (!empty($secondaryKeywords)) {
            $missingSecondary = 0;
            foreach ($secondaryKeywords as $keyword) {
                $density = $this->calculateKeywordDensity($text, $keyword);
                if ($density < 0.1) {
                    $missingSecondary++;
                }
            }
            if ($missingSecondary > 0) {
                $ratio = $missingSecondary . '/' . count($secondaryKeywords);
                $score -= min(3, $missingSecondary);
                $issues[] = "{$ratio} secondary keywords are not used in the content";
            }
        }

        // Check first paragraph contains keyword
        if (!empty($primaryKeyword)) {
            $firstParagraph = '';
            if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $html, $match)) {
                $firstParagraph = strip_tags($match[1]);
            }
            if (!empty($firstParagraph) && mb_stripos($firstParagraph, $primaryKeyword) === false) {
                $score -= 2;
                $issues[] = 'Primary keyword not found in the first paragraph';
            }
        }

        // Check uses <strong> tags
        if (stripos($html, '<strong') === false && stripos($html, '<b>') === false) {
            $score -= 1;
            $issues[] = 'No bold/strong text found — use to emphasize key terms';
        }

        // Check has lists or tables
        $hasLists = stripos($html, '<ul') !== false || stripos($html, '<ol') !== false;
        $hasTables = stripos($html, '<table') !== false;
        if (!$hasLists && !$hasTables) {
            $score -= 1;
            $issues[] = 'No lists or tables found — structured content improves readability';
        }

        // Readability check — French scores ~15pts lower than English on Flesch-Kincaid,
        // so thresholds are adjusted downward (30→20, 50→35)
        $readability = $this->calculateReadability($text);
        if ($readability < 20) {
            $score -= 2;
            $issues[] = "Readability score very low ({$readability}) — content may be too complex";
        } elseif ($readability < 35) {
            $score -= 1;
            $issues[] = "Readability score low ({$readability}) — consider simplifying sentences";
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Analyze images. Score /10.
     */
    private function analyzeImages(string $html, ?string $featuredImage): array
    {
        $score = 10;
        $issues = [];

        // Check featured image
        if (empty($featuredImage)) {
            $score -= 3;
            $issues[] = 'No featured image set';
        }

        if (empty($html)) {
            return ['score' => max(0, $score), 'issues' => $issues];
        }

        // Parse img tags
        preg_match_all('/<img\s[^>]*>/i', $html, $imgMatches);
        $imgCount = count($imgMatches[0]);

        if ($imgCount === 0 && empty($featuredImage)) {
            $score -= 3;
            $issues[] = 'No images found in content';
            return ['score' => max(0, $score), 'issues' => $issues];
        }

        // Check alt text on all images
        $missingAlt = 0;
        $missingDimensions = 0;
        $missingLazy = 0;

        foreach ($imgMatches[0] as $imgTag) {
            // Check alt attribute
            if (!preg_match('/\balt\s*=\s*"[^"]+"/i', $imgTag) && !preg_match("/\balt\s*=\s*'[^']+'/i", $imgTag)) {
                $missingAlt++;
            }

            // Check width/height attributes
            if (!preg_match('/\bwidth\s*=/i', $imgTag) || !preg_match('/\bheight\s*=/i', $imgTag)) {
                $missingDimensions++;
            }

            // Check lazy loading
            if (!preg_match('/\bloading\s*=\s*["\']lazy["\']/i', $imgTag)) {
                $missingLazy++;
            }
        }

        if ($missingAlt > 0) {
            $score -= min(3, $missingAlt);
            $issues[] = "{$missingAlt} image(s) missing alt text";
        }

        if ($missingDimensions > 0) {
            $score -= min(2, $missingDimensions);
            $issues[] = "{$missingDimensions} image(s) missing width/height attributes";
        }

        if ($missingLazy > 0 && $imgCount > 1) {
            $score -= 1;
            $issues[] = "{$missingLazy} image(s) without lazy loading";
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Analyze internal links. Score /10.
     */
    private function analyzeInternalLinks(Model $content): array
    {
        $score = 10;
        $issues = [];

        try {
            $linkCount = 0;

            // Check if model has internalLinksOut relationship
            if (method_exists($content, 'internalLinksOut')) {
                $linkCount = $content->internalLinksOut()->count();
            }

            if ($linkCount === 0) {
                $score -= 6;
                $issues[] = 'No internal links found';
            } elseif ($linkCount < 3) {
                $score -= 3;
                $issues[] = "Only {$linkCount} internal links (aim for 3-8)";
            } elseif ($linkCount > 10) {
                $score -= 2;
                $issues[] = "Too many internal links ({$linkCount}), may dilute link equity";
            }

            // Check anchor text variety
            if (method_exists($content, 'internalLinksOut') && $linkCount >= 2) {
                $anchors = $content->internalLinksOut()->pluck('anchor_text')->toArray();
                $uniqueAnchors = array_unique(array_map('mb_strtolower', $anchors));
                if (count($uniqueAnchors) < count($anchors) * 0.5) {
                    $score -= 2;
                    $issues[] = 'Internal link anchor texts are too repetitive';
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Internal links analysis skipped', ['message' => $e->getMessage()]);
            $score = 5; // Neutral score if cannot analyze
            $issues[] = 'Internal links could not be analyzed';
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Analyze external links. Score /5.
     */
    private function analyzeExternalLinks(Model $content): array
    {
        $score = 5;
        $issues = [];

        try {
            $linkCount = 0;

            if (method_exists($content, 'externalLinks')) {
                $linkCount = $content->externalLinks()->count();
            }

            if ($linkCount === 0) {
                $score -= 3;
                $issues[] = 'No external links — citing sources improves credibility';
            } elseif ($linkCount < 2) {
                $score -= 1;
                $issues[] = "Only {$linkCount} external link (aim for 2-5)";
            } elseif ($linkCount > 8) {
                $score -= 1;
                $issues[] = "Too many external links ({$linkCount})";
            }

            // Check for nofollow overuse
            if (method_exists($content, 'externalLinks') && $linkCount >= 2) {
                $nofollowCount = $content->externalLinks()->where('is_nofollow', true)->count();
                if ($nofollowCount === $linkCount) {
                    $score -= 1;
                    $issues[] = 'All external links are nofollow — consider some dofollow to authoritative sources';
                }
            }
        } catch (\Throwable $e) {
            Log::debug('External links analysis skipped', ['message' => $e->getMessage()]);
            $score = 3;
            $issues[] = 'External links could not be analyzed';
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Analyze structured data (JSON-LD). Score /10.
     */
    private function analyzeStructuredData(?array $jsonLd): array
    {
        $score = 10;
        $issues = [];

        if (empty($jsonLd)) {
            $score -= 10;
            $issues[] = 'No JSON-LD structured data found';
            return ['score' => max(0, $score), 'issues' => $issues];
        }

        // Check for @type
        $types = [];
        if (isset($jsonLd['@graph'])) {
            foreach ($jsonLd['@graph'] as $item) {
                $types[] = $item['@type'] ?? 'unknown';
            }
        } else {
            $types[] = $jsonLd['@type'] ?? 'unknown';
        }

        if (in_array('unknown', $types) || empty($types)) {
            $score -= 3;
            $issues[] = 'JSON-LD missing @type property';
        }

        // Check required fields per type
        $items = isset($jsonLd['@graph']) ? $jsonLd['@graph'] : [$jsonLd];
        foreach ($items as $item) {
            $type = $item['@type'] ?? '';

            if ($type === 'Article' || $type === 'BlogPosting' || $type === 'NewsArticle') {
                $requiredFields = ['headline', 'datePublished', 'author', 'image'];
                foreach ($requiredFields as $field) {
                    if (empty($item[$field])) {
                        $score -= 1;
                        $issues[] = "Article schema missing required field: {$field}";
                    }
                }
            }

            if ($type === 'FAQPage') {
                if (empty($item['mainEntity']) || !is_array($item['mainEntity'])) {
                    $score -= 2;
                    $issues[] = 'FAQPage schema missing mainEntity array';
                } else {
                    foreach ($item['mainEntity'] as $faq) {
                        if (empty($faq['@type']) || $faq['@type'] !== 'Question') {
                            $score -= 1;
                            $issues[] = 'FAQ item missing @type: Question';
                            break;
                        }
                        if (empty($faq['acceptedAnswer'])) {
                            $score -= 1;
                            $issues[] = 'FAQ question missing acceptedAnswer';
                            break;
                        }
                    }
                }
            }

            if ($type === 'BreadcrumbList') {
                if (empty($item['itemListElement']) || !is_array($item['itemListElement'])) {
                    $score -= 1;
                    $issues[] = 'BreadcrumbList schema missing itemListElement';
                }
            }
        }

        // Check @context present
        $context = $jsonLd['@context'] ?? ($items[0]['@context'] ?? '');
        if (empty($context)) {
            $score -= 1;
            $issues[] = 'JSON-LD missing @context property';
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Analyze hreflang tags. Score /10.
     */
    private function analyzeHreflang(?array $hreflangMap, string $language): array
    {
        $score = 10;
        $issues = [];

        if (empty($hreflangMap)) {
            $score -= 10;
            $issues[] = 'No hreflang map defined';
            return ['score' => max(0, $score), 'issues' => $issues];
        }

        // Check has entries
        if (count($hreflangMap) < 2) {
            $score -= 3;
            $issues[] = 'Hreflang map has less than 2 languages — needs at least 2 for multilingual SEO';
        }

        // Check has x-default
        if (!isset($hreflangMap['x-default'])) {
            $score -= 3;
            $issues[] = 'Hreflang map missing x-default entry';
        }

        // Check has self-referencing
        if (!isset($hreflangMap[$language])) {
            $score -= 2;
            $issues[] = "Hreflang map missing self-referencing entry for current language ({$language})";
        }

        // Check valid language codes
        $validCodes = ['fr', 'en', 'de', 'es', 'it', 'pt', 'nl', 'pl', 'ro', 'ar', 'tr', 'ru', 'zh', 'ja', 'ko', 'x-default'];
        foreach (array_keys($hreflangMap) as $code) {
            if (!in_array($code, $validCodes) && !preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $code)) {
                $score -= 1;
                $issues[] = "Invalid language code in hreflang: {$code}";
                break;
            }
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    /**
     * Analyze technical SEO factors. Score /5.
     */
    private function analyzeTechnical(?string $canonicalUrl, string $status): array
    {
        $score = 5;
        $issues = [];

        // Check has canonical URL
        if (empty($canonicalUrl)) {
            $score -= 2;
            $issues[] = 'No canonical URL set';
        } else {
            // Check canonical is absolute URL
            if (!filter_var($canonicalUrl, FILTER_VALIDATE_URL)) {
                $score -= 1;
                $issues[] = 'Canonical URL is not an absolute URL';
            }
        }

        // Check status
        if (!in_array($status, ['published', 'scheduled', 'review', 'draft'])) {
            $score -= 1;
            $issues[] = "Unknown content status: {$status}";
        }

        return ['score' => max(0, $score), 'issues' => $issues];
    }

    // ============================================================
    // Helper Methods
    // ============================================================

    /**
     * Calculate readability score (Flesch-Kincaid adapted for French).
     * Returns a score from 0-100 (higher = easier to read).
     */
    public function calculateReadability(string $text): float
    {
        if (empty($text)) {
            return 0;
        }

        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentenceCount = max(1, count($sentences));

        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = max(1, count($words));

        // Count syllables (simplified for French: count vowel groups)
        $syllableCount = 0;
        foreach ($words as $word) {
            $syllableCount += max(1, preg_match_all('/[aeiouyàâäéèêëïîôùûü]+/iu', mb_strtolower($word)));
        }

        // Flesch Reading Ease adapted for French (Kandel & Moles formula)
        $avgSentenceLength = $wordCount / $sentenceCount;
        $avgSyllablesPerWord = $syllableCount / $wordCount;

        $score = 207 - (1.015 * $avgSentenceLength) - (73.6 * $avgSyllablesPerWord);

        return round(max(0, min(100, $score)), 1);
    }

    /**
     * Count words in text (strip HTML first).
     */
    public function countWords(string $text): int
    {
        $text = $this->extractTextFromHtml($text);
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return count($words);
    }

    /**
     * Calculate keyword density as a percentage.
     */
    public function calculateKeywordDensity(string $text, string $keyword): float
    {
        if (empty($text) || empty($keyword)) {
            return 0.0;
        }

        $text = mb_strtolower($this->extractTextFromHtml($text));
        $keyword = mb_strtolower($keyword);

        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $totalWords = count($words);

        if ($totalWords === 0) {
            return 0.0;
        }

        // Count keyword occurrences (phrase-level)
        $keywordWords = preg_split('/\s+/', trim($keyword));
        $keywordLength = count($keywordWords);
        $occurrences = 0;

        if ($keywordLength === 1) {
            $occurrences = substr_count($text, $keyword);
        } else {
            // Multi-word keyword: count phrase occurrences
            $occurrences = substr_count($text, $keyword);
        }

        // Density = (occurrences * keyword word count) / total words * 100
        $density = ($occurrences * $keywordLength) / $totalWords * 100;

        return round($density, 2);
    }

    /**
     * Strip all HTML tags from text.
     */
    public function extractTextFromHtml(string $html): string
    {
        // Remove script and style contents
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Convert block elements to spaces
        $html = preg_replace('/<\/(p|div|h[1-6]|li|tr|td|th|br|hr)[^>]*>/i', ' ', $html);

        // Strip all remaining tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text;
    }

    /**
     * Find all headings in HTML.
     *
     * @return array<array{level: int, text: string}>
     */
    public function findHeadings(string $html): array
    {
        $headings = [];

        if (preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $headings[] = [
                    'level' => (int) $match[1],
                    'text' => strip_tags($match[2]),
                ];
            }
        }

        return $headings;
    }
}
