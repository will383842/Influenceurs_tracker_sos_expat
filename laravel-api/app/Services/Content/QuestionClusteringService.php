<?php

namespace App\Services\Content;

use App\Jobs\ClusterQuestionsJob;
use App\Models\ContentQuestion;
use App\Models\QuestionCluster;
use App\Models\QuestionClusterItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Question clustering — groups scraped forum questions by similarity
 * to prepare them for Q&A generation and article creation.
 */
class QuestionClusteringService
{
    /** Jaccard similarity threshold (lower than articles because question titles are short). */
    private const SIMILARITY_THRESHOLD = 0.25;

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

    /** Category keyword map for auto-detection. */
    private const CATEGORY_KEYWORDS = [
        'visa' => ['visa', 'permis', 'residence', 'sejour', 'immigration', 'passeport'],
        'logement' => ['logement', 'louer', 'location', 'appartement', 'maison', 'immobilier', 'hebergement'],
        'sante' => ['sante', 'assurance', 'hopital', 'medecin', 'vaccination', 'maladie', 'mutuelle'],
        'emploi' => ['emploi', 'travail', 'job', 'carriere', 'entreprise', 'business', 'salaire', 'contrat'],
        'transport' => ['transport', 'conduire', 'voiture', 'permis de conduire', 'metro', 'bus', 'avion'],
        'education' => ['education', 'ecole', 'universite', 'etude', 'scolarite', 'enfant', 'creche'],
        'banque' => ['banque', 'finance', 'compte', 'argent', 'impot', 'fiscal', 'carte bancaire'],
        'culture' => ['culture', 'langue', 'tradition', 'gastronomie', 'loisir', 'integration'],
        'demarches' => ['demarche', 'administration', 'consulat', 'ambassade', 'papier', 'document'],
        'telecom' => ['telephone', 'internet', 'telecom', 'mobile', 'communication', 'forfait'],
        'vie_quotidienne' => ['cout de la vie', 'supermarche', 'quotidien', 'courses', 'prix'],
    ];

    /**
     * Cluster unprocessed questions for a given country slug.
     */
    public function clusterByCountry(string $countrySlug, ?string $category = null): Collection
    {
        try {
            $query = ContentQuestion::query()
                ->where('country_slug', $countrySlug)
                ->where('article_status', 'new')
                ->whereNull('cluster_id');

            if ($category !== null) {
                // Filter by detected category from title keywords
                $query->where(function ($q) use ($category) {
                    $keywords = self::CATEGORY_KEYWORDS[$category] ?? [];
                    foreach ($keywords as $keyword) {
                        $q->orWhere('title', 'ilike', "%{$keyword}%");
                    }
                });
            }

            $questions = $query->get();

            if ($questions->isEmpty()) {
                Log::info('QuestionClustering: no unprocessed questions', [
                    'country_slug' => $countrySlug,
                    'category' => $category,
                ]);

                return collect();
            }

            Log::info('QuestionClustering: clustering started', [
                'country_slug' => $countrySlug,
                'category' => $category,
                'questions_count' => $questions->count(),
            ]);

            // Tokenize all titles upfront
            $tokenized = [];
            foreach ($questions as $question) {
                $tokenized[$question->id] = $this->tokenize($question->title ?? '');
            }

            // Union-Find data structures
            $parent = [];
            $rank = [];
            foreach ($questions as $question) {
                $parent[$question->id] = $question->id;
                $rank[$question->id] = 0;
            }

            // Build similarity groups using union-find
            $questionsList = $questions->values();
            $count = $questionsList->count();

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $idA = $questionsList[$i]->id;
                    $idB = $questionsList[$j]->id;

                    $similarity = $this->calculateJaccard(
                        $tokenized[$idA],
                        $tokenized[$idB]
                    );

                    if ($similarity >= self::SIMILARITY_THRESHOLD) {
                        $this->unionFind($parent, $rank, $idA, $idB);
                    }
                }
            }

            // Group questions by their root parent
            $groups = [];
            foreach ($questions as $question) {
                $root = $this->findRoot($parent, $question->id);
                $groups[$root][] = $question;
            }

            // Create clusters from groups (min 1 question per group)
            $createdClusters = collect();

            foreach ($groups as $group) {
                // Find the most viewed question (primary)
                usort($group, function ($a, $b) {
                    return ($b->views ?? 0) - ($a->views ?? 0);
                });

                $primary = $group[0];

                // Compute aggregates
                $totalViews = 0;
                $totalReplies = 0;
                foreach ($group as $q) {
                    $totalViews += $q->views ?? 0;
                    $totalReplies += $q->replies ?? 0;
                }

                // Auto-detect category from all titles in the group
                $allTitles = implode(' ', array_map(fn ($q) => $q->title ?? '', $group));
                $detectedCategory = $category ?? $this->detectCategory($allTitles);

                $clusterName = Str::limit($primary->title, 297);
                $slug = Str::slug($clusterName);

                $cluster = QuestionCluster::create([
                    'name' => $clusterName,
                    'slug' => $slug,
                    'country' => $primary->country ?? '',
                    'country_slug' => $primary->country_slug,
                    'continent' => $primary->continent,
                    'category' => $detectedCategory,
                    'language' => $primary->language ?? 'fr',
                    'total_questions' => count($group),
                    'total_views' => $totalViews,
                    'total_replies' => $totalReplies,
                    'popularity_score' => $totalViews + ($totalReplies * 10),
                    'status' => 'pending',
                ]);

                // Create pivot records
                foreach ($group as $question) {
                    $isPrimary = $question->id === $primary->id;
                    $similarity = $isPrimary
                        ? 1.0
                        : $this->calculateJaccard(
                            $tokenized[$primary->id],
                            $tokenized[$question->id]
                        );

                    QuestionClusterItem::create([
                        'cluster_id' => $cluster->id,
                        'question_id' => $question->id,
                        'is_primary' => $isPrimary,
                        'similarity_score' => round($similarity, 2),
                    ]);

                    // Update content_questions
                    $question->update([
                        'cluster_id' => $cluster->id,
                        'article_status' => 'planned',
                    ]);
                }

                $createdClusters->push($cluster);
            }

            // Sort by popularity_score desc
            $createdClusters = $createdClusters->sortByDesc('popularity_score')->values();

            Log::info('QuestionClustering: clustering complete', [
                'country_slug' => $countrySlug,
                'category' => $category,
                'clusters_created' => $createdClusters->count(),
            ]);

            return $createdClusters;
        } catch (\Throwable $e) {
            Log::error('QuestionClustering: clustering failed', [
                'country_slug' => $countrySlug,
                'category' => $category,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Dispatch clustering jobs for all distinct country_slugs with unprocessed questions.
     */
    public function autoClusterAll(): int
    {
        try {
            $countrySlugs = ContentQuestion::query()
                ->where('article_status', 'new')
                ->whereNull('cluster_id')
                ->distinct()
                ->pluck('country_slug')
                ->filter();

            $dispatched = 0;

            foreach ($countrySlugs as $countrySlug) {
                ClusterQuestionsJob::dispatch($countrySlug, null);
                $dispatched++;
            }

            Log::info('QuestionClustering: auto-cluster dispatched', [
                'jobs_dispatched' => $dispatched,
            ]);

            return $dispatched;
        } catch (\Throwable $e) {
            Log::error('QuestionClustering: auto-cluster failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Detect category from title keywords.
     */
    private function detectCategory(string $title): ?string
    {
        $normalized = $this->stripAccents(mb_strtolower($title));

        $scores = [];

        foreach (self::CATEGORY_KEYWORDS as $cat => $keywords) {
            $count = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    $count++;
                }
            }
            if ($count > 0) {
                $scores[$cat] = $count;
            }
        }

        if (empty($scores)) {
            return null;
        }

        arsort($scores);

        return array_key_first($scores);
    }

    /**
     * Calculate Jaccard similarity between two word sets.
     * Returns 0.0 - 1.0.
     */
    private function calculateJaccard(array $wordsA, array $wordsB): float
    {
        if (empty($wordsA) || empty($wordsB)) {
            return 0.0;
        }

        $intersection = array_intersect($wordsA, $wordsB);
        $union = array_unique(array_merge($wordsA, $wordsB));

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
        $text = mb_strtolower($text);
        $text = $this->stripAccents($text);

        $words = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($words)) {
            return [];
        }

        $words = array_filter($words, function ($word) {
            return mb_strlen($word) > 2 && !in_array($word, self::STOPWORDS, true);
        });

        return array_values(array_unique($words));
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

    /**
     * Union-Find: find root with path compression.
     */
    private function findRoot(array &$parent, int $id): int
    {
        if ($parent[$id] !== $id) {
            $parent[$id] = $this->findRoot($parent, $parent[$id]);
        }

        return $parent[$id];
    }

    /**
     * Union-Find: union two nodes by rank.
     */
    private function unionFind(array &$parent, array &$rank, int $a, int $b): void
    {
        $rootA = $this->findRoot($parent, $a);
        $rootB = $this->findRoot($parent, $b);

        if ($rootA === $rootB) {
            return;
        }

        if ($rank[$rootA] < $rank[$rootB]) {
            $parent[$rootA] = $rootB;
        } elseif ($rank[$rootA] > $rank[$rootB]) {
            $parent[$rootB] = $rootA;
        } else {
            $parent[$rootB] = $rootA;
            $rank[$rootA]++;
        }
    }
}
