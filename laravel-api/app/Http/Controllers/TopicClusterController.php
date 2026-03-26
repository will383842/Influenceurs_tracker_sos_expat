<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateFromClusterJob;
use App\Jobs\GenerateQaEntriesJob;
use App\Jobs\GenerateResearchBriefJob;
use App\Models\TopicCluster;
use App\Services\Content\TopicClusteringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TopicClusterController extends Controller
{
    public function __construct(
        private TopicClusteringService $clusteringService,
    ) {}

    /**
     * List clusters with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'country'  => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'status'   => 'nullable|string|in:pending,ready,generated,archived',
            'search'   => 'nullable|string|max:200',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = TopicCluster::query();

        if ($request->filled('country')) {
            $query->where('country', $request->input('country'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where('name', 'ilike', "%{$search}%");
        }

        $query->withCount('clusterArticles')
            ->with('creator:id,name')
            ->orderByDesc('created_at');

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Show cluster detail with source articles + research brief.
     */
    public function show(TopicCluster $cluster): JsonResponse
    {
        $cluster->load([
            'sourceArticles:id,title,url,category,word_count,language',
            'clusterArticles',
            'researchBrief',
            'generatedArticle:id,title,slug,status,word_count,seo_score',
            'creator:id,name',
        ]);

        return response()->json($cluster);
    }

    /**
     * Trigger auto-clustering.
     */
    public function autoCluster(Request $request): JsonResponse
    {
        $request->validate([
            'country'  => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
        ]);

        try {
            if ($request->filled('country') && $request->filled('category')) {
                $clusters = $this->clusteringService->clusterByCountryAndCategory(
                    $request->input('country'),
                    $request->input('category')
                );

                return response()->json([
                    'message' => 'Clustering completed',
                    'clusters_created' => $clusters->count(),
                ]);
            }

            $dispatched = $this->clusteringService->autoClusterAll();

            return response()->json([
                'message' => 'Auto-clustering jobs dispatched',
                'jobs_dispatched' => $dispatched,
            ], 202);
        } catch (\Throwable $e) {
            Log::error('TopicClusterController: auto-cluster failed', ['message' => $e->getMessage()]);

            return response()->json([
                'message' => 'Auto-clustering failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger research brief generation for a cluster.
     */
    public function generateBrief(TopicCluster $cluster): JsonResponse
    {
        if ($cluster->status === 'generated') {
            return response()->json([
                'message' => 'Cluster already has a generated article',
            ], 422);
        }

        GenerateResearchBriefJob::dispatch($cluster->id);

        return response()->json([
            'message' => 'Research brief generation dispatched',
            'cluster_id' => $cluster->id,
        ], 202);
    }

    /**
     * Trigger article generation from cluster.
     */
    public function generateArticle(TopicCluster $cluster): JsonResponse
    {
        if ($cluster->status !== 'ready') {
            return response()->json([
                'message' => 'Cluster must have status "ready" (research brief completed)',
            ], 422);
        }

        GenerateFromClusterJob::dispatch($cluster->id);

        return response()->json([
            'message' => 'Article generation from cluster dispatched',
            'cluster_id' => $cluster->id,
        ], 202);
    }

    /**
     * Trigger Q&A generation from cluster's generated article FAQs.
     */
    public function generateQa(TopicCluster $cluster): JsonResponse
    {
        if (!$cluster->generated_article_id) {
            return response()->json([
                'message' => 'Cluster does not have a generated article yet',
            ], 422);
        }

        GenerateQaEntriesJob::dispatch($cluster->generated_article_id);

        return response()->json([
            'message' => 'Q&A generation dispatched',
            'cluster_id' => $cluster->id,
            'article_id' => $cluster->generated_article_id,
        ], 202);
    }

    /**
     * Archive a cluster (soft action).
     */
    public function destroy(TopicCluster $cluster): JsonResponse
    {
        $cluster->update(['status' => 'archived']);

        return response()->json([
            'message' => 'Cluster archived',
            'cluster_id' => $cluster->id,
        ]);
    }
}
