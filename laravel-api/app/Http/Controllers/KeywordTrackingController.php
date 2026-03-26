<?php

namespace App\Http\Controllers;

use App\Models\GeneratedArticle;
use App\Models\KeywordTracking;
use App\Services\Content\KeywordTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KeywordTrackingController extends Controller
{
    public function __construct(
        private KeywordTrackingService $keywordService,
    ) {}

    /**
     * List keywords with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type'     => 'nullable|string|in:primary,secondary,long_tail,lsi,trending',
            'language' => 'nullable|string|max:5',
            'country'  => 'nullable|string|max:100',
            'search'   => 'nullable|string|max:200',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = KeywordTracking::query();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }
        if ($request->filled('country')) {
            $query->where('country', $request->input('country'));
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where('keyword', 'ilike', "%{$search}%");
        }

        $query->orderByDesc('articles_using_count');

        $perPage = min((int) $request->input('per_page', 50), 100);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Keyword gaps analysis.
     */
    public function gaps(Request $request): JsonResponse
    {
        $request->validate([
            'country'  => 'required|string|max:100',
            'category' => 'required|string|max:100',
            'language' => 'nullable|string|max:5',
        ]);

        try {
            $gaps = $this->keywordService->findKeywordGaps(
                $request->input('country'),
                $request->input('category'),
                $request->input('language', 'fr')
            );

            return response()->json([
                'gaps' => $gaps,
                'total' => count($gaps),
                'uncovered' => count(array_filter($gaps, fn ($g) => !$g['covered'])),
            ]);
        } catch (\Throwable $e) {
            Log::error('KeywordTrackingController: gaps failed', ['message' => $e->getMessage()]);

            return response()->json([
                'message' => 'Keyword gap analysis failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cannibalization check.
     */
    public function cannibalization(Request $request): JsonResponse
    {
        $request->validate([
            'language' => 'nullable|string|max:5',
        ]);

        try {
            $results = $this->keywordService->checkCannibalization(
                $request->input('language', 'fr')
            );

            return response()->json([
                'cannibalized_keywords' => $results,
                'total' => count($results),
                'high_severity' => count(array_filter($results, fn ($r) => $r['severity'] === 'high')),
            ]);
        } catch (\Throwable $e) {
            Log::error('KeywordTrackingController: cannibalization check failed', ['message' => $e->getMessage()]);

            return response()->json([
                'message' => 'Cannibalization check failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Keywords for a specific article.
     */
    public function articleKeywords(GeneratedArticle $article): JsonResponse
    {
        try {
            $analysis = $this->keywordService->analyzeArticleKeywords($article);

            // Also load tracked keywords via pivot
            $trackedKeywords = $article->load('keywords')->keywords ?? collect();

            return response()->json([
                'analysis' => $analysis,
                'tracked_keywords' => $trackedKeywords,
            ]);
        } catch (\Throwable $e) {
            Log::error('KeywordTrackingController: article keywords failed', [
                'article_id' => $article->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Article keyword analysis failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
