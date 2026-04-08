<?php

namespace App\Services\Content;

use App\Models\GeneratedArticle;
use App\Models\QaEntry;
use App\Services\Quality\PlagiarismService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeduplicationService
{
    public function __construct(
        private PlagiarismService $plagiarism,
    ) {}

    // Similarity thresholds
    // > 0.75  = certain duplicate (same content_type + same source) → block
    // > 0.60  = probable duplicate (different sources, same content_type) → block
    // 0.40-0.60 = complementary (flag for review but allow)
    // < 0.40  = different topics → always allow
    private const THRESHOLD_EXACT_DUPLICATE   = 0.75;
    private const THRESHOLD_CROSS_SOURCE      = 0.60;
    private const THRESHOLD_COMPLEMENTARY     = 0.40;

    /**
     * Check if an article on this topic already exists (within same source/content_type).
     * Returns the existing article if found, null if safe to create.
     */
    public function findDuplicateArticle(string $title, string $country, string $language): ?GeneratedArticle
    {
        // 1. Exact slug match (exclude articles still generating — they are the current article)
        $slug = Str::slug($title);
        $existing = GeneratedArticle::where('language', $language)
            ->where('slug', 'ilike', $slug . '%')
            ->whereNull('parent_article_id') // originals only
            ->where('status', '!=', 'generating')
            ->first();
        if ($existing) {
            Log::info('DeduplicationService: slug match found', ['title' => $title, 'existing_id' => $existing->id]);
            return $existing;
        }

        // 2. Title similarity (Jaccard on words)
        $titleWords = $this->normalizeWords($title);
        $candidates = GeneratedArticle::where('language', $language)
            ->where('country', $country)
            ->whereNull('parent_article_id')
            ->whereIn('status', ['draft', 'review', 'published'])
            ->select('id', 'title', 'slug')
            ->get();

        foreach ($candidates as $candidate) {
            $candidateWords = $this->normalizeWords($candidate->title);
            $similarity = $this->jaccardSimilarity($titleWords, $candidateWords);
            if ($similarity > 0.5) {
                Log::info('DeduplicationService: title similarity match', [
                    'title' => $title,
                    'existing' => $candidate->title,
                    'similarity' => $similarity,
                ]);
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Cross-source duplicate detection.
     *
     * Two articles are duplicates when:
     *   - Same content_type AND similarity > THRESHOLD_EXACT_DUPLICATE (0.75)
     *   - Different content_type but similarity > THRESHOLD_CROSS_SOURCE (0.60) → still block
     *     (e.g. guide "Vivre au Portugal" vs guide "Vivre au Portugal" from different source)
     *
     * Two articles are COMPLEMENTARY (allowed) when:
     *   - Different content_type AND similarity < THRESHOLD_CROSS_SOURCE
     *     (e.g. guide "Vivre au Portugal" + qa "Comment obtenir un visa Portugal")
     *
     * Returns:
     *   null                       → safe to generate
     *   ['status' => 'duplicate']  → block generation
     *   ['status' => 'flag']       → allow but flag for review
     */
    public function checkCrossSourceDuplicate(
        string $title,
        string $country,
        string $language,
        string $contentType,
        ?string $sourceSlug = null,
    ): ?array {
        $titleWords = $this->normalizeWords($title);

        $candidates = GeneratedArticle::where('language', $language)
            ->where('country', $country)
            ->whereNull('parent_article_id')
            ->whereIn('status', ['draft', 'review', 'published'])
            ->when($sourceSlug, fn ($q) => $q->where('source_slug', '!=', $sourceSlug))
            ->select('id', 'title', 'slug', 'content_type', 'source_slug')
            ->get();

        $highestSimilarity = 0;
        $closestMatch = null;

        foreach ($candidates as $candidate) {
            $candidateWords = $this->normalizeWords($candidate->title);
            $similarity = $this->jaccardSimilarity($titleWords, $candidateWords);

            if ($similarity > $highestSimilarity) {
                $highestSimilarity = $similarity;
                $closestMatch = $candidate;
            }
        }

        if (!$closestMatch || $highestSimilarity < self::THRESHOLD_COMPLEMENTARY) {
            return null; // safe
        }

        $sameContentType = $closestMatch->content_type === $contentType;

        // Same content_type + high similarity = definite duplicate
        if ($sameContentType && $highestSimilarity >= self::THRESHOLD_EXACT_DUPLICATE) {
            Log::warning('DeduplicationService: cross-source duplicate blocked', [
                'new_title'    => $title,
                'existing'     => $closestMatch->title,
                'similarity'   => round($highestSimilarity, 2),
                'source_slug'  => $sourceSlug,
                'existing_source' => $closestMatch->source_slug,
            ]);
            return [
                'status'      => 'duplicate',
                'existing_id' => $closestMatch->id,
                'similarity'  => $highestSimilarity,
                'reason'      => "Same content_type '{$contentType}', similarity " . round($highestSimilarity * 100) . '%',
            ];
        }

        // Different content_type but high cross-source similarity = still block
        if (!$sameContentType && $highestSimilarity >= self::THRESHOLD_CROSS_SOURCE) {
            Log::warning('DeduplicationService: cross-source near-duplicate blocked', [
                'new_title'    => $title,
                'existing'     => $closestMatch->title,
                'similarity'   => round($highestSimilarity, 2),
                'new_type'     => $contentType,
                'existing_type' => $closestMatch->content_type,
            ]);
            return [
                'status'      => 'duplicate',
                'existing_id' => $closestMatch->id,
                'similarity'  => $highestSimilarity,
                'reason'      => "Cross-source near-duplicate: '{$contentType}' vs '{$closestMatch->content_type}', similarity " . round($highestSimilarity * 100) . '%',
            ];
        }

        // Complementary: different content_types, moderate similarity → allow but flag
        if ($highestSimilarity >= self::THRESHOLD_COMPLEMENTARY) {
            Log::info('DeduplicationService: complementary articles (allowed)', [
                'new_title'    => $title,
                'existing'     => $closestMatch->title,
                'similarity'   => round($highestSimilarity, 2),
                'new_type'     => $contentType,
                'existing_type' => $closestMatch->content_type,
            ]);
            return [
                'status'      => 'flag',
                'existing_id' => $closestMatch->id,
                'similarity'  => $highestSimilarity,
                'reason'      => "Complementary: '{$contentType}' vs '{$closestMatch->content_type}' — different angles on similar topic",
            ];
        }

        return null;
    }

    /**
     * Check if a Q&A on this question already exists.
     */
    public function findDuplicateQa(string $question, string $language): ?QaEntry
    {
        // 1. Exact slug match
        $slug = Str::slug($question);
        $existing = QaEntry::where('language', $language)
            ->where('slug', 'ilike', $slug . '%')
            ->whereNull('parent_qa_id')
            ->first();
        if ($existing) return $existing;

        // 2. Title similarity
        $questionWords = $this->normalizeWords($question);
        $candidates = QaEntry::where('language', $language)
            ->whereNull('parent_qa_id')
            ->whereIn('status', ['draft', 'review', 'published'])
            ->select('id', 'question', 'slug')
            ->get();

        foreach ($candidates as $candidate) {
            $candidateWords = $this->normalizeWords($candidate->question);
            $similarity = $this->jaccardSimilarity($questionWords, $candidateWords);
            if ($similarity > 0.5) return $candidate;
        }

        return null;
    }

    /**
     * Run plagiarism check on generated content.
     * Returns true if content is original enough.
     */
    public function checkContentOriginality(GeneratedArticle $article): array
    {
        return $this->plagiarism->check($article);
    }

    private function normalizeWords(string $text): array
    {
        $text = mb_strtolower($text);
        $text = str_replace(['à', 'â', 'ä'], 'a', $text);
        $text = str_replace(['é', 'è', 'ê', 'ë'], 'e', $text);
        $text = str_replace(['î', 'ï'], 'i', $text);
        $text = str_replace(['ô', 'ö'], 'o', $text);
        $text = str_replace(['ù', 'û', 'ü'], 'u', $text);
        $text = str_replace(['ç'], 'c', $text);
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        $words = preg_split('/\s+/', trim($text));
        $stopwords = ['le', 'la', 'les', 'de', 'du', 'des', 'un', 'une', 'et', 'en', 'pour', 'dans', 'sur', 'par', 'au', 'aux', 'a', 'ce', 'que', 'qui', 'est', 'avec', 'son', 'sa', 'ses', 'se', 'ne', 'pas', 'the', 'a', 'an', 'in', 'on', 'of', 'for', 'to', 'and', 'is', 'it'];
        return array_values(array_diff($words, $stopwords));
    }

    private function jaccardSimilarity(array $a, array $b): float
    {
        if (empty($a) && empty($b)) return 0;
        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));
        return $union > 0 ? $intersection / $union : 0;
    }
}
