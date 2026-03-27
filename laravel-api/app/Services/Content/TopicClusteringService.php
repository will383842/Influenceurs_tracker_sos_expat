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
 *
 * Clustering rules:
 * 1. Articles are ALWAYS scoped to the same country + category (hard constraint)
 * 2. Similarity is computed using weighted Jaccard on titles + content excerpt
 * 3. Domain-specific stopwords prevent false matches on generic expat vocabulary
 * 4. Country names are excluded from similarity tokens to avoid cross-country leaks
 */
class TopicClusteringService
{
    /** Jaccard similarity threshold — raised from 0.3 to 0.5 to avoid false grouping. */
    private const SIMILARITY_THRESHOLD = 0.50;

    /** Bonus weight for content-body overlap (blended with title similarity). */
    private const CONTENT_WEIGHT = 0.3;
    private const TITLE_WEIGHT = 0.7;

    /** Maximum words to sample from article body for similarity. */
    private const CONTENT_SAMPLE_WORDS = 150;

    /** Common stopwords to ignore when computing similarity. */
    private const STOPWORDS = [
        // FR generic
        'le', 'la', 'les', 'de', 'du', 'des', 'un', 'une', 'et', 'en', 'au', 'aux',
        'a', 'pour', 'par', 'sur', 'dans', 'avec', 'est', 'son', 'sa', 'ses',
        'ce', 'cette', 'ces', 'qui', 'que', 'quoi', 'ou', 'ne', 'pas',
        'plus', 'tout', 'tous', 'tres', 'bien', 'aussi', 'comme', 'mais', 'donc',
        'etre', 'avoir', 'faire', 'peut', 'doit', 'faut', 'sont', 'ont', 'vos', 'nos',
        'votre', 'notre', 'leur', 'leurs', 'autre', 'autres', 'meme', 'car', 'dont',
        // EN generic
        'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with',
        'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
        'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might',
        'this', 'that', 'these', 'those', 'it', 'its', 'not', 'no', 'but', 'so',
        'how', 'what', 'when', 'where', 'who', 'which', 'all', 'each', 'every',
        'your', 'my', 'our', 'their', 'you', 'we', 'they', 'he', 'she',
        // Domain-specific: ultra-frequent in expat content, zero discriminating power
        'guide', 'complet', 'complete', 'pratique', 'practical',
        'tout', 'savoir', 'know', 'everything',
        'expatrie', 'expatries', 'expat', 'expats', 'expatriation',
        'etranger', 'etrangers', 'foreigner', 'foreign',
        'francais', 'francaise', 'french',
        'pays', 'country', 'countries',
        'vivre', 'live', 'living', 'installer', 'installation',
        'demarches', 'formalites', 'procedures', 'procedure',
        'conseils', 'tips', 'advice', 'astuces',
        'informations', 'information', 'info', 'infos',
        'article', 'articles', 'page', 'pages',
        'comment', 'pourquoi', 'quand', 'combien',
        'annee', 'year', 'mois', 'month',
        'nouveau', 'nouvelle', 'nouveaux', 'nouvelles', 'new',
        'meilleur', 'meilleure', 'meilleurs', 'meilleures', 'best',
        'important', 'importante', 'importants', 'essentiels', 'essentiel',
    ];

    /**
     * Country names/slugs to strip from tokens — populated dynamically per clustering run.
     * @var string[]
     */
    private array $countryTokens = [];

    /**
     * Cluster unprocessed articles for a given country + category.
     * Articles are ONLY grouped within the same country AND category.
     */
    public function clusterByCountryAndCategory(string $country, string $category): Collection
    {
        try {
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

            // Build country tokens to exclude from similarity (avoids matching on country name)
            $this->countryTokens = $this->buildCountryTokens($country);

            Log::info('TopicClustering: clustering started', [
                'country' => $country,
                'category' => $category,
                'articles_count' => $articles->count(),
            ]);

            // Build similarity groups using blended title + content similarity
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

                    $similarity = $this->calculateBlendedSimilarity($articleA, $articleB);

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
                $clusterName = $this->generateClusterName($group, $country, $category);
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

                // Create pivot records — primary article is the longest one
                usort($group, fn ($a, $b) => ($b->word_count ?? 0) - ($a->word_count ?? 0));

                $isPrimary = true;
                foreach ($group as $article) {
                    TopicClusterArticle::create([
                        'cluster_id' => $cluster->id,
                        'source_article_id' => $article->id,
                        'relevance_score' => $isPrimary ? 100 : $this->computeRelevance($group[0], $article),
                        'is_primary' => $isPrimary,
                        'processing_status' => 'pending',
                    ]);
                    $isPrimary = false;

                    $article->update(['processing_status' => 'clustered']);
                }

                $createdClusters->push($cluster);
            }

            Log::info('TopicClustering: clustering complete', [
                'country' => $country,
                'category' => $category,
                'clusters_created' => $createdClusters->count(),
                'articles_clustered' => count($assigned),
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
     * Blended similarity: weighted average of title Jaccard + content Jaccard.
     * This prevents two articles with similar titles but completely different content
     * from being grouped together.
     */
    private function calculateBlendedSimilarity(ContentArticle $a, ContentArticle $b): float
    {
        $titleSim = $this->calculateJaccard(
            $this->tokenize($a->title ?? ''),
            $this->tokenize($b->title ?? '')
        );

        $contentSim = $this->calculateJaccard(
            $this->tokenizeContent($a->content_text ?? ''),
            $this->tokenizeContent($b->content_text ?? '')
        );

        return (self::TITLE_WEIGHT * $titleSim) + (self::CONTENT_WEIGHT * $contentSim);
    }

    /**
     * Jaccard similarity between two token arrays.
     */
    private function calculateJaccard(array $words1, array $words2): float
    {
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
     * Tokenize a title: lowercase, strip accents, remove stopwords + country tokens.
     *
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = $this->stripAccents($text);

        $words = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($words)) {
            return [];
        }

        $words = array_filter($words, function ($word) {
            return mb_strlen($word) > 2
                && !in_array($word, self::STOPWORDS, true)
                && !in_array($word, $this->countryTokens, true);
        });

        return array_values(array_unique($words));
    }

    /**
     * Tokenize article content body: takes first N significant words.
     *
     * @return string[]
     */
    private function tokenizeContent(string $text): array
    {
        if (empty($text)) {
            return [];
        }

        $text = mb_strtolower($text);
        $text = $this->stripAccents($text);
        $text = strip_tags($text);

        $words = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($words)) {
            return [];
        }

        $words = array_filter($words, function ($word) {
            return mb_strlen($word) > 3
                && !in_array($word, self::STOPWORDS, true)
                && !in_array($word, $this->countryTokens, true);
        });

        // Take only first N unique words to keep computation fast
        $unique = array_unique(array_values($words));

        return array_slice($unique, 0, self::CONTENT_SAMPLE_WORDS);
    }

    /**
     * Build tokens from the country name to exclude them from similarity.
     * "Côte d'Ivoire" → ["cote", "ivoire"], "United Kingdom" → ["united", "kingdom"]
     *
     * @return string[]
     */
    private function buildCountryTokens(string $country): array
    {
        $text = mb_strtolower($country);
        $text = $this->stripAccents($text);

        $words = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($words)) {
            return [];
        }

        return array_filter($words, fn ($w) => mb_strlen($w) > 2);
    }

    /**
     * Generate a meaningful cluster name: "{Category} — {top discriminating words} ({country})".
     *
     * @param ContentArticle[] $articles
     */
    private function generateClusterName(array $articles, string $country, string $category): string
    {
        $wordCounts = [];

        foreach ($articles as $article) {
            $words = $this->tokenize($article->title ?? '');
            foreach ($words as $word) {
                $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
            }
        }

        arsort($wordCounts);

        $topWords = array_slice(array_keys($wordCounts), 0, 3);

        if (empty($topWords)) {
            return ucfirst($category) . ' — ' . $country;
        }

        return ucfirst($category) . ' — ' . implode(' ', $topWords) . ' (' . $country . ')';
    }

    /**
     * Detect keywords from article titles + content via simple TF analysis.
     *
     * @param ContentArticle[] $articles
     * @return array<string, int>
     */
    private function detectKeywords(array $articles): array
    {
        $wordCounts = [];

        foreach ($articles as $article) {
            // Title words count double
            $titleWords = $this->tokenize($article->title ?? '');
            foreach ($titleWords as $word) {
                $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 2;
            }

            // Content words count once
            $contentWords = $this->tokenizeContent($article->content_text ?? '');
            foreach ($contentWords as $word) {
                $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
            }
        }

        arsort($wordCounts);

        return array_slice($wordCounts, 0, 15, true);
    }

    /**
     * Compute relevance score of a secondary article relative to the primary.
     */
    private function computeRelevance(ContentArticle $primary, ContentArticle $secondary): int
    {
        $sim = $this->calculateBlendedSimilarity($primary, $secondary);

        return (int) round($sim * 100);
    }

    /**
     * Strip accents from a string (e->e, u->u, etc.).
     */
    private function stripAccents(string $text): string
    {
        $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        if ($transliterator) {
            return $transliterator->transliterate($text) ?: $text;
        }

        return strtr(
            $text,
            'àáâãäåèéêëìíîïòóôõöùúûüýÿñçÀÁÂÃÄÅÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝŸÑÇ',
            'aaaaaaeeeeiiiioooooouuuuyyncAAAAAAEEEEIIIIOOOOOUUUUYYNC'
        );
    }
}
