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

    /**
     * Check if an article on this topic already exists.
     * Returns the existing article if found, null if safe to create.
     */
    public function findDuplicateArticle(string $title, string $country, string $language): ?GeneratedArticle
    {
        // 1. Exact slug match
        $slug = Str::slug($title);
        $existing = GeneratedArticle::where('language', $language)
            ->where('slug', 'ilike', $slug . '%')
            ->whereNull('parent_article_id') // originals only
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
