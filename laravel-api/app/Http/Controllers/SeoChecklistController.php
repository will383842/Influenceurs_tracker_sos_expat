<?php

namespace App\Http\Controllers;

use App\Models\GeneratedArticle;
use App\Models\SeoChecklist;
use App\Services\Content\SeoChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SeoChecklistController extends Controller
{
    public function __construct(
        private SeoChecklistService $checklistService,
    ) {}

    /**
     * Run the SEO checklist evaluation on an article.
     */
    public function evaluate(GeneratedArticle $article): JsonResponse
    {
        try {
            $checklist = $this->checklistService->evaluate($article);
            $failed = $this->checklistService->getFailedChecks($checklist);

            return response()->json([
                'checklist' => $checklist,
                'failed_checks' => $failed,
                'failed_count' => count($failed),
            ]);
        } catch (\Throwable $e) {
            Log::error('SeoChecklistController: evaluate failed', [
                'article_id' => $article->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'SEO checklist evaluation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get existing checklist for an article.
     */
    public function show(GeneratedArticle $article): JsonResponse
    {
        $checklist = SeoChecklist::where('article_id', $article->id)->first();

        if (!$checklist) {
            return response()->json([
                'message' => 'No SEO checklist found for this article. Run /evaluate first.',
            ], 404);
        }

        return response()->json($checklist);
    }

    /**
     * Get failed checks with suggestions.
     */
    public function failedChecks(GeneratedArticle $article): JsonResponse
    {
        $checklist = SeoChecklist::where('article_id', $article->id)->first();

        if (!$checklist) {
            return response()->json([
                'message' => 'No SEO checklist found. Run /evaluate first.',
            ], 404);
        }

        try {
            $failed = $this->checklistService->getFailedChecks($checklist);

            return response()->json([
                'failed_checks' => $failed,
                'total_failed' => count($failed),
                'overall_score' => $checklist->overall_checklist_score,
            ]);
        } catch (\Throwable $e) {
            Log::error('SeoChecklistController: failedChecks failed', [
                'article_id' => $article->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to get check details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
