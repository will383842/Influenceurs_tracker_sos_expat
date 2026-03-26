<?php

namespace App\Services\Content;

use App\Jobs\ClusterArticlesJob;
use App\Models\ContentArticle;
use App\Models\ContentCountry;
use App\Models\TopicCluster;
use App\Models\TopicClusterArticle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Topic clustering — groups scraped content articles by similarity
 * to prepare them for research briefs and article generation.
 */
class TopicClusteringService
{
    /** Jaccard similarity threshold to consider two titles "similar". */
    private const SIMILARITY_THRESHOLD = 0.3;

    /** Common stopwords to ignore when computing similarity. */
    private const STOPWORDS = [
        // FR
        'le', 'la', 'les', 'de', 'du', 'des', 'un', 'une', 'et', 'en', 'au', 'aux',
        'à', 'pour', 'par', 'sur', 'dans', 'avec', 'est', 'son', 'sa', 'ses',
        'ce', 'cette', 'ces', 'qui', 'que', 'quoi', 'ou', 'où', 'ne', 'pas',
        'plus', 'tout', 'tous', 'très', 'bien', 'aussi', 'comme', 'mais', 'donc',
        // EN
        'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with',
        'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
        'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might',
        'this', 'that', 'these', 'those', 'it', 'its', 'not', 'no', 'but', 'so',
        'how', 'what', 'when', 'where', 'who', 'which', 'all', 'each', 'every',
        'your', 'my', 'our', 'their', 'you', 'we', 'they', 'he', 'she',
    ];

    /**
     * Cluster unprocessed articles for a given country + category.
     */
    public function clusterByCountryAndCategory(string $country, string $category): Collection
    {
        try {
            // Fetch unprocessed content articles matching country + category
            $articles = ContentArticle::query()
                ->whereHas('country', function ($q) use ($country) {
                    $q->where('name', $country)->orWhere('slug', $country);
                })
                ->where('category', $category)
                ->where(function ($q) {
                    $q->where('processing_status', 'unprocessed')
                      ->orWhereNull('processing_status');
                })
                ->get();

            if ($articles->isEmpty()) {
                Log::info('TopicClustering: no unprocessed articles', [
                    'country' => $country,
                    'category' => $category,
                ]);

                return collect();
            }

            Log::info('TopicClustering: clustering started', [
                'country' => $country,
                'category' => $category,
                'articles_count' => $articles->count(),
            ]);

            // Build similarity groups
            $assigned = [];
            $groups = [];

            foreach ($articles as $i => $articleA) {
                if (in_array($articleA->id, $assigned, true)) {
                    continue;
                }

                $group = [$articleA];
                $assigned[] = $articleA->id;

                foreach ($articles as $j => $articleB) {
                    if ($i === $j || in_array($articleB->id, $assigned, true)) {
                        continue;
                    }

                    $similarity = $this->calculateSimilarity(
                        $articleA->title ?? '',
                        $articleB->title ?? ''
                    );

                    if ($similarity >= self::SIMILARITY_THRESHOLD) {
                        $group[] = $articleB;
                        $assigned[] = $articleB->id;
                    }
                }

                $groups[] = $group;
            }

            // Create clusters from groups
            $createdClusters = collect();

            foreach ($groups as $group) {
                $clusterName = $this->generateClusterName($group);
                $keywords = $this->detectKeywords($group);

                $cluster = TopicCluster::create([
                    'name' => Str::limit($clusterName, 200),
                    'slug' => Str::slug($clusterName),
                    'country' => $country,
                    'category' => $category,
                    'language' => $group[0]->language ?? 'fr',
                    'source_articles_count' => count($group),
                    'status' => 'pending',
                    'keywords_detected' => $keywords,
                ]);

                // Create pivot records
                $isPrimary = true;
                foreach ($group as $article) {
                    TopicClusterArticle::create([
                        'cluster_id' => $cluster->id,
                        'source_article_id' => $article->id,
                        'relevance_score' => $isPrimary ? 100 : 70,
                        'is_primary' => $isPrimary,
                        'processing_status' => 'pending',
                    ]);
                    $isPrimary = false;

                    // Mark article as clustered
                    $article->update(['processing_status' => 'clustered']);
                }

                $createdClusters->push($cluster);
            }

            Log::info('TopicClustering: clustering complete', [
                'country' => $country,
                'category' => $category,
                'clusters_created' => $createdClusters->count(),
            ]);

            return $createdClusters;
        } catch (\Throwable $e) {
            Log::error('TopicClustering: clustering failed', [
                'country' => $country,
                'category' => $category,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Dispatch clustering jobs for all distinct country+category combos with unprocessed articles.
     */
    public function autoClusterAll(): int
    {
        try {
            $combos = ContentArticle::query()
                ->where(function ($q) {
                    $q->where('processing_status', 'unprocessed')
                      ->orWhereNull('processing_status');
                })
                ->join('content_countries', 'content_articles.country_id', '=', 'content_countries.id')
                ->select('content_countries.name as country_name', 'content_articles.category')
                ->distinct()
                ->get();

            $dispatched = 0;

            foreach ($combos as $combo) {
                if (empty($combo->country_name) || empty($combo->category)) {
                    continue;
                }

                ClusterArticlesJob::dispatch($combo->country_name, $combo->category);
                $dispatched++;
            }

            Log::info('TopicClustering: auto-cluster dispatched', [
                'jobs_dispatched' => $dispatched,
            ]);

            return $dispatched;
        } catch (\Throwable $e) {
            Log::error('TopicClustering: auto-cluster failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate Jaccard similarity between two titles.
     * Returns 0.0 - 1.0.
     */
    private function calculateSimilarity(string $title1, string $title2): float
    {
        $words1 = $this->tokenize($title1);
        $words2 = $this->tokenize($title2);

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        if (empty($union)) {
            return 0.0;
        }

        return count($intersection) / count($union);
    }

    /**
     * Tokenize a title: lowercase, strip accents, remove stopwords.
     *
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        // Lowercase
        $text = mb_strtolower($text);

        // Strip accents
        $text = $this->stripAccents($text);

        // Split on non-alpha characters
        $words = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($words)) {
            return [];
        }

        // Remove stopwords and short words
        $words = array_filter($words, function ($word) {
            return mb_strlen($word) > 2 && !in_array($word, self::STOPWORDS, true);
        });

        return array_values(array_unique($words));
    }

    /**
     * Generate a cluster name from the most common title words.
     *
     * @param ContentArticle[] $articles
     */
    private function generateClusterName(array $articles): string
    {
        $wordCounts = [];

        foreach ($articles as $article) {
            $words = $this->tokenize($article->title ?? '');
            foreach ($words as $word) {
                $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
            }
        }

        // Sort by frequency descending
        arsort($wordCounts);

        // Take top 4 words
        $topWords = array_slice(array_keys($wordCounts), 0, 4);

        if (empty($topWords)) {
            return 'Cluster ' . now()->format('Y-m-d H:i');
        }

        return ucfirst(implode(' ', $topWords));
    }

    /**
     * Detect keywords from article titles via simple TF analysis.
     *
     * @param ContentArticle[] $articles
     * @return array<string, int>
     */
    private function detectKeywords(array $articles): array
    {
        $wordCounts = [];

        foreach ($articles as $article) {
            $words = $this->tokenize($article->title ?? '');
            foreach ($words as $word) {
                $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
            }
        }

        arsort($wordCounts);

        // Return top 10 keywords with their counts
        return array_slice($wordCounts, 0, 10, true);
    }

    /**
     * Strip accents from a string (é→e, ü→u, etc.).
     */
    private function stripAccents(string $text): string
    {
        $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        if ($transliterator) {
            return $transliterator->transliterate($text) ?: $text;
        }

        // Fallback if intl extension not available
        return strtr(
            $text,
            'àáâãäåèéêëìíîïòóôõöùúûüýÿñçÀÁÂÃÄÅÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝŸÑÇ',
            'aaaaaaeeeeiiiioooooouuuuyyncAAAAAAEEEEIIIIOOOOOUUUUYYNC'
        );
    }
}
