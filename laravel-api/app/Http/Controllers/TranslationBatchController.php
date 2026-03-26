<?php

namespace App\Http\Controllers;

use App\Models\GeneratedArticle;
use App\Models\QaEntry;
use App\Models\TranslationBatch;
use App\Services\Content\TranslationBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TranslationBatchController extends Controller
{
    public function __construct(
        private TranslationBatchService $batchService,
    ) {}

    /**
     * List translation batches with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'language' => 'nullable|string|max:5',
            'status'   => 'nullable|string|in:running,paused,completed,cancelled',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = TranslationBatch::query();

        if ($request->filled('language')) {
            $query->where('target_language', $request->input('language'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $query->with('creator:id,name')
            ->orderByDesc('created_at');

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Translation status overview per language.
     */
    public function overview(): JsonResponse
    {
        try {
            $languages = ['en', 'de', 'es', 'pt', 'ru', 'zh', 'ar', 'hi'];
            $overview = [];

            $totalFrArticles = GeneratedArticle::where('language', 'fr')
                ->whereNull('parent_article_id')
                ->whereIn('status', ['review', 'published'])
                ->count();

            $totalFrQa = QaEntry::where('language', 'fr')
                ->whereNull('parent_qa_id')
                ->whereIn('status', ['draft', 'review', 'published'])
                ->count();

            foreach ($languages as $lang) {
                $translatedArticles = GeneratedArticle::where('language', $lang)
                    ->whereNotNull('parent_article_id')
                    ->count();

                $translatedQa = QaEntry::where('language', $lang)
                    ->whereNotNull('parent_qa_id')
                    ->count();

                $totalFr = $totalFrArticles + $totalFrQa;
                $totalTranslated = $translatedArticles + $translatedQa;

                $overview[] = [
                    'language' => $lang,
                    'total_fr' => $totalFr,
                    'translated_articles' => $translatedArticles,
                    'translated_qa' => $translatedQa,
                    'translated' => $totalTranslated,
                    'percent' => $totalFr > 0 ? round(($totalTranslated / $totalFr) * 100, 1) : 0,
                ];
            }

            return response()->json($overview);
        } catch (\Throwable $e) {
            Log::error('TranslationBatchController: overview failed', ['message' => $e->getMessage()]);

            return response()->json([
                'message' => 'Overview failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start a new translation batch.
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_language' => 'required|string|in:en,de,es,pt,ru,zh,ar,hi',
            'content_type'    => 'nullable|string|in:article,qa,all',
        ]);

        try {
            $batch = $this->batchService->startBatch(
                $validated['target_language'],
                $validated['content_type'] ?? 'article',
                $request->user()->id
            );

            return response()->json($batch, 202);
        } catch (\Throwable $e) {
            Log::error('TranslationBatchController: start failed', ['message' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to start translation batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Pause a batch.
     */
    public function pause(TranslationBatch $batch): JsonResponse
    {
        try {
            $this->batchService->pauseBatch($batch);

            return response()->json($batch->fresh());
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Pause failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resume a batch.
     */
    public function resume(TranslationBatch $batch): JsonResponse
    {
        try {
            $this->batchService->resumeBatch($batch);

            return response()->json($batch->fresh());
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Resume failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel a batch.
     */
    public function cancel(TranslationBatch $batch): JsonResponse
    {
        try {
            $this->batchService->cancelBatch($batch);

            return response()->json($batch->fresh());
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Cancel failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show batch detail with progress.
     */
    public function show(TranslationBatch $batch): JsonResponse
    {
        $batch->load('creator:id,name');
        $progress = $this->batchService->getProgress($batch);

        return response()->json([
            'batch' => $batch,
            'progress' => $progress,
        ]);
    }
}
