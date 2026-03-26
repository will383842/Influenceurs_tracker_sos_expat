<?php

namespace App\Http\Controllers;

use App\Jobs\ClusterQuestionsJob;
use App\Jobs\GenerateArticleFromQuestionsJob;
use App\Jobs\GenerateQaFromClusterJob;
use App\Models\QuestionCluster;
use App\Services\Content\QuestionClusteringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuestionClusterController extends Controller
{
    public function __construct(
        private QuestionClusteringService $clusteringService,
    ) {}

    /**
     * List clusters with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'country'         => 'nullable|string|max:100',
            'category'        => 'nullable|string|max:50',
            'status'          => 'nullable|string|in:pending,ready,generating_qa,generating_article,completed,skipped',
            'min_popularity'  => 'nullable|integer|min:0',
            'search'          => 'nullable|string|max:200',
            'per_page'        => 'nullable|integer|min:1|max:100',
        ]);

        $query = QuestionCluster::query();

        if ($request->filled('country')) {
            $query->where('country', $request->input('country'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('min_popularity')) {
            $query->where('popularity_score', '>=', (int) $request->input('min_popularity'));
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where('name', 'ilike', "%{$search}%");
        }

        $query->with('creator:id,name')
            ->orderByDesc('popularity_score');

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Show cluster detail with questions loaded.
     */
    public function show(QuestionCluster $cluster): JsonResponse
    {
        $cluster->load([
            'items' => function ($q) {
                $q->with('question:id,title,url,views,replies,language,article_status,country')
                    ->orderByDesc('is_primary');
            },
            'generatedArticle:id,title,slug,status,word_count,seo_score',
            'qaEntries:id,question,slug,status,word_count',
            'creator:id,name',
        ]);

        // Sort questions by popularity within the loaded items
        if ($cluster->relationLoaded('items')) {
            $cluster->setRelation(
                'items',
                $cluster->items->sortByDesc(function ($item) {
                    $q = $item->question;
                    return $q ? (($q->views ?? 0) + ($q->replies ?? 0) * 10) : 0;
                })->values()
            );
        }

        return response()->json($cluster);
    }

    /**
     * Aggregate stats for question clusters.
     */
    public function stats(): JsonResponse
    {
        $totalClusters = QuestionCluster::count();

        $byCountry = QuestionCluster::select('country', DB::raw('count(*) as total'))
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $byStatus = QuestionCluster::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        $byCategory = QuestionCluster::select('category', DB::raw('count(*) as total'))
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $topByPopularity = QuestionCluster::select('id', 'name', 'country', 'category', 'popularity_score', 'total_questions', 'status')
            ->orderByDesc('popularity_score')
            ->limit(10)
            ->get();

        return response()->json([
            'total_clusters' => $totalClusters,
            'by_country' => $byCountry,
            'by_status' => $byStatus,
            'by_category' => $byCategory,
            'top_by_popularity' => $topByPopularity,
        ]);
    }

    /**
     * Trigger auto-clustering.
     */
    public function autoCluster(Request $request): JsonResponse
    {
        $request->validate([
            'country_slug' => 'nullable|string|max:100',
            'category'     => 'nullable|string|max:50',
        ]);

        try {
            if ($request->filled('country_slug')) {
                ClusterQuestionsJob::dispatch(
                    $request->input('country_slug'),
                    $request->input('category')
                );

                return response()->json([
                    'message' => 'Clustering job dispatched',
                    'country_slug' => $request->input('country_slug'),
                    'category' => $request->input('category'),
                ], 202);
            }

            $dispatched = $this->clusteringService->autoClusterAll();

            return response()->json([
                'message' => 'Auto-clustering jobs dispatched',
                'jobs_dispatched' => $dispatched,
            ], 202);
        } catch (\Throwable $e) {
            Log::error('QuestionClusterController: auto-cluster failed', ['message' => $e->getMessage()]);

            return response()->json([
                'message' => 'Auto-clustering failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate Q&A entries from cluster questions.
     */
    public function generateQa(QuestionCluster $cluster): JsonResponse
    {
        if (in_array($cluster->status, ['generating_qa', 'generating_article'])) {
            return response()->json([
                'message' => 'Cluster is already being processed',
            ], 422);
        }

        GenerateQaFromClusterJob::dispatch($cluster->id);

        return response()->json([
            'message' => 'Q&A generation dispatched',
            'cluster_id' => $cluster->id,
        ], 202);
    }

    /**
     * Generate article from cluster questions.
     */
    public function generateArticle(QuestionCluster $cluster): JsonResponse
    {
        if (in_array($cluster->status, ['generating_qa', 'generating_article'])) {
            return response()->json([
                'message' => 'Cluster is already being processed',
            ], 422);
        }

        GenerateArticleFromQuestionsJob::dispatch($cluster->id);

        return response()->json([
            'message' => 'Article generation dispatched',
            'cluster_id' => $cluster->id,
        ], 202);
    }

    /**
     * Generate both Q&A + article from cluster.
     */
    public function generateBoth(QuestionCluster $cluster): JsonResponse
    {
        if (in_array($cluster->status, ['generating_qa', 'generating_article'])) {
            return response()->json([
                'message' => 'Cluster is already being processed',
            ], 422);
        }

        GenerateQaFromClusterJob::dispatch($cluster->id);
        GenerateArticleFromQuestionsJob::dispatch($cluster->id);

        return response()->json([
            'message' => 'Q&A + Article generation dispatched',
            'cluster_id' => $cluster->id,
        ], 202);
    }

    /**
     * Skip a cluster.
     */
    public function skip(QuestionCluster $cluster): JsonResponse
    {
        $cluster->update(['status' => 'skipped']);

        return response()->json($cluster);
    }

    /**
     * Delete/skip a cluster and unlink its questions.
     */
    public function destroy(QuestionCluster $cluster): JsonResponse
    {
        // Unlink questions
        $cluster->questions()->update([
            'cluster_id' => null,
            'article_status' => 'new',
        ]);

        $cluster->update(['status' => 'skipped']);

        return response()->json([
            'message' => 'Cluster skipped and questions unlinked',
            'cluster_id' => $cluster->id,
        ]);
    }
}
