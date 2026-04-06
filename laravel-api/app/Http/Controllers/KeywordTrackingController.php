<?php

namespace App\Http\Controllers;

use App\Models\GeneratedArticle;
use App\Models\KeywordTracking;
use App\Services\Content\KeywordTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'type'     => 'nullable|string|max:30',
            'language' => 'nullable|string|max:5',
            'country'  => 'nullable|string|max:100',
            'search'   => 'nullable|string|max:200',
            'per_page' => 'nullable|integer|min:1|max:1000',
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
     * Discover long-tail keywords from recently published articles.
     */
    public function discover(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 20), 100);

        // Pull secondary keywords from recently published articles
        $articles = DB::table('generated_articles')
            ->whereNotNull('keywords_secondary')
            ->latest()
            ->limit(100)
            ->get(['id', 'language', 'country', 'content_type', 'keywords_secondary']);

        $now      = now();
        $inserted = 0;

        foreach ($articles as $article) {
            $rawKws = is_string($article->keywords_secondary)
                ? json_decode($article->keywords_secondary, true)
                : (array) $article->keywords_secondary;

            if (empty($rawKws) || !is_array($rawKws)) continue;

            // Keep keywords with 3+ words (long-tail heuristic)
            $longTail = array_filter($rawKws, fn ($kw) => str_word_count((string) $kw) >= 3);

            foreach (array_values($longTail) as $kw) {
                if (empty(trim($kw))) continue;
                $rows = DB::table('keyword_tracking')->insertOrIgnore([[
                    'keyword'              => mb_strtolower(trim($kw)),
                    'type'                 => 'long_tail',
                    'language'             => $article->language ?? 'fr',
                    'country'              => $article->country ?? null,
                    'category'             => $article->content_type ?? null,
                    'articles_using_count' => 1,
                    'first_used_at'        => $now,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]]);
                $inserted += $rows;
                if ($inserted >= $limit) break 2;
            }
        }

        return response()->json(['inserted' => $inserted]);
    }

    /**
     * Bulk-insert keywords (used by Art Mots Cles page).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'keywords'                  => 'required|array|min:1|max:500',
            'keywords.*.keyword'        => 'required|string|max:300',
            'keywords.*.language'       => 'nullable|string|max:5',
            'keywords.*.category'       => 'nullable|string|max:100',
            'keywords.*.search_intent'  => 'nullable|string|max:30',
            'keywords.*.type'           => 'nullable|string|max:30',
        ]);

        $now      = now();
        $inserted = 0;

        foreach ($request->input('keywords') as $kw) {
            $rows = DB::table('keyword_tracking')->insertOrIgnore([[
                'keyword'        => trim($kw['keyword']),
                'type'           => $kw['type'] ?? 'art_mots_cles',
                'language'       => $kw['language'] ?? 'fr',
                'category'       => $kw['category'] ?? null,
                'search_intent'  => $kw['search_intent'] ?? null,
                'articles_using_count' => 0,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]]);
            $inserted += $rows;
        }

        return response()->json(['inserted' => $inserted, 'total' => count($request->input('keywords'))]);
    }

    /**
     * Delete a keyword by id.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = DB::table('keyword_tracking')->where('id', $id)->delete();

        return response()->json(['deleted' => $deleted > 0]);
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
