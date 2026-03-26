<?php

namespace App\Http\Controllers;

use App\Jobs\RunFullAutoPipelineJob;
use App\Models\ContentArticle;
use App\Models\ContentQuestion;
use App\Models\GeneratedArticle;
use App\Models\GenerationLog;
use App\Models\GenerationPreset;
use App\Models\PromptTemplate;
use App\Models\QuestionCluster;
use App\Models\TopicCluster;
use App\Services\AI\OpenAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GenerationController extends Controller
{
    // ============================================================
    // Statistics
    // ============================================================

    public function stats(): JsonResponse
    {
        $totalAllTime = GeneratedArticle::count();
        $totalThisMonth = GeneratedArticle::where('created_at', '>=', now()->startOfMonth())->count();
        $totalThisWeek = GeneratedArticle::where('created_at', '>=', now()->startOfWeek())->count();

        // Average generation time from logs
        $avgGenerationMs = (int) GenerationLog::where('phase', 'complete')
            ->where('status', 'success')
            ->avg('duration_ms');

        // Average quality score
        $avgQualityScore = (int) GeneratedArticle::whereNotNull('quality_score')->avg('quality_score');

        // Average SEO score
        $avgSeoScore = (int) GeneratedArticle::whereNotNull('seo_score')->avg('seo_score');

        // Articles by status
        $byStatus = GeneratedArticle::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Articles by language
        $byLanguage = GeneratedArticle::selectRaw('language, COUNT(*) as count')
            ->groupBy('language')
            ->orderByDesc('count')
            ->pluck('count', 'language');

        // Average cost
        $avgCostCents = (int) GeneratedArticle::whereNotNull('generation_cost_cents')
            ->where('generation_cost_cents', '>', 0)
            ->avg('generation_cost_cents');

        // Total cost this month
        $totalCostThisMonth = (int) GeneratedArticle::whereNotNull('generation_cost_cents')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('generation_cost_cents');

        return response()->json([
            'total_all_time'         => $totalAllTime,
            'total_this_month'       => $totalThisMonth,
            'total_this_week'        => $totalThisWeek,
            'avg_generation_ms'      => $avgGenerationMs,
            'avg_quality_score'      => $avgQualityScore,
            'avg_seo_score'          => $avgSeoScore,
            'avg_cost_cents'         => $avgCostCents,
            'total_cost_this_month'  => $totalCostThisMonth,
            'by_status'              => $byStatus,
            'by_language'            => $byLanguage,
        ]);
    }

    // ============================================================
    // History (Generation Logs)
    // ============================================================

    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'phase'     => 'nullable|string|max:50',
            'status'    => 'nullable|string|in:success,error,pending',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
            'per_page'  => 'nullable|integer|min:1|max:100',
        ]);

        $query = GenerationLog::with('loggable');

        if ($request->filled('phase')) {
            $query->where('phase', $request->input('phase'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date('date_to')->endOfDay());
        }

        $query->orderByDesc('created_at');

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    // ============================================================
    // Presets CRUD
    // ============================================================

    public function presetsIndex(): JsonResponse
    {
        $presets = GenerationPreset::withCount('articles')
            ->with('createdBy:id,name')
            ->orderBy('name')
            ->get();

        return response()->json($presets);
    }

    public function presetStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100',
            'description'  => 'nullable|string|max:500',
            'content_type' => 'nullable|string|in:article,guide,news,tutorial,comparative',
            'config'       => 'required|array',
            'is_default'   => 'nullable|boolean',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['is_default'] = $validated['is_default'] ?? false;

        // Unset other defaults if this is set as default
        if ($validated['is_default']) {
            GenerationPreset::where('is_default', true)->update(['is_default' => false]);
        }

        $preset = GenerationPreset::create($validated);

        return response()->json($preset->load('createdBy:id,name'), 201);
    }

    public function presetUpdate(Request $request, GenerationPreset $preset): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'nullable|string|max:100',
            'description'  => 'nullable|string|max:500',
            'content_type' => 'nullable|string|in:article,guide,news,tutorial,comparative',
            'config'       => 'nullable|array',
            'is_default'   => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            GenerationPreset::where('id', '!=', $preset->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $preset->update($validated);

        return response()->json($preset->fresh()->load('createdBy:id,name'));
    }

    public function presetDelete(GenerationPreset $preset): JsonResponse
    {
        // Check if any articles use this preset
        $usageCount = $preset->articles()->count();

        if ($usageCount > 0) {
            return response()->json([
                'message' => "Cannot delete preset — used by {$usageCount} articles. Reassign them first.",
            ], 422);
        }

        $preset->delete();

        return response()->json(null, 204);
    }

    // ============================================================
    // Prompt Templates CRUD
    // ============================================================

    public function promptsIndex(): JsonResponse
    {
        $prompts = PromptTemplate::with('createdBy:id,name')
            ->orderBy('content_type')
            ->orderBy('phase')
            ->orderByDesc('version')
            ->get();

        return response()->json($prompts);
    }

    public function promptStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:100',
            'description'           => 'nullable|string|max:500',
            'content_type'          => 'required|string|in:article,guide,news,tutorial,comparative,landing,press',
            'phase'                 => 'required|string|max:50',
            'system_message'        => 'required|string',
            'user_message_template' => 'required|string',
            'model'                 => 'nullable|string|max:50',
            'temperature'           => 'nullable|numeric|min:0|max:2',
            'max_tokens'            => 'nullable|integer|min:100|max:128000',
            'is_active'             => 'nullable|boolean',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['is_active'] = $validated['is_active'] ?? true;

        // Auto-increment version for same content_type + phase
        $latestVersion = PromptTemplate::where('content_type', $validated['content_type'])
            ->where('phase', $validated['phase'])
            ->max('version') ?? 0;

        $validated['version'] = $latestVersion + 1;

        $prompt = PromptTemplate::create($validated);

        return response()->json($prompt->load('createdBy:id,name'), 201);
    }

    public function promptUpdate(Request $request, PromptTemplate $prompt): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => 'nullable|string|max:100',
            'description'           => 'nullable|string|max:500',
            'system_message'        => 'nullable|string',
            'user_message_template' => 'nullable|string',
            'model'                 => 'nullable|string|max:50',
            'temperature'           => 'nullable|numeric|min:0|max:2',
            'max_tokens'            => 'nullable|integer|min:100|max:128000',
            'is_active'             => 'nullable|boolean',
        ]);

        $prompt->update($validated);

        return response()->json($prompt->fresh()->load('createdBy:id,name'));
    }

    public function promptDelete(PromptTemplate $prompt): JsonResponse
    {
        $prompt->delete();

        return response()->json(null, 204);
    }

    public function testPrompt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'system_message'        => 'required|string',
            'user_message_template' => 'required|string',
            'variables'             => 'nullable|array',
            'model'                 => 'nullable|string|max:50',
            'temperature'           => 'nullable|numeric|min:0|max:2',
            'max_tokens'            => 'nullable|integer|min:100|max:4000',
        ]);

        // Replace variables in user message
        $userMessage = $validated['user_message_template'];
        $variables = $validated['variables'] ?? [];

        foreach ($variables as $key => $value) {
            $userMessage = str_replace("{{{$key}}}", $value, $userMessage);
        }

        try {
            /** @var OpenAiService $aiService */
            $aiService = app(OpenAiService::class);

            $result = $aiService->complete(
                $validated['system_message'],
                $userMessage,
                [
                    'model' => $validated['model'] ?? 'gpt-4o-mini',
                    'temperature' => $validated['temperature'] ?? 0.7,
                    'max_tokens' => $validated['max_tokens'] ?? 2000,
                ]
            );

            return response()->json([
                'success'        => true,
                'response'       => $result['content'] ?? '',
                'tokens_input'   => $result['tokens_input'] ?? 0,
                'tokens_output'  => $result['tokens_output'] ?? 0,
                'model'          => $result['model'] ?? $validated['model'] ?? 'gpt-4o-mini',
                'rendered_prompt' => $userMessage,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // Auto Pipeline
    // ============================================================

    public function runAutoPipeline(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            'max_articles' => 'nullable|integer|min:1|max:500',
            'min_quality_score' => 'nullable|integer|min:50|max:100',
            'include_qa' => 'nullable|boolean',
            'articles_from_questions' => 'nullable|boolean',
        ]);

        RunFullAutoPipelineJob::dispatch($validated);

        return response()->json([
            'message' => 'Pipeline automatique lancé',
            'options' => $validated,
        ], 202);
    }

    public function pipelineStatus(): JsonResponse
    {
        $unprocessedArticles = ContentArticle::whereIn('processing_status', ['unprocessed', null])->count();
        $unprocessedQuestions = ContentQuestion::where('article_status', 'new')->count();
        $pendingClusters = TopicCluster::whereIn('status', ['pending', 'ready'])->count();
        $pendingQClusters = QuestionCluster::whereIn('status', ['pending', 'ready'])->count();
        $generatingCount = GeneratedArticle::where('status', 'generating')->count();
        $generatedToday = GeneratedArticle::whereDate('created_at', today())->count();
        $totalGenerated = GeneratedArticle::whereNull('parent_article_id')->count();
        $avgQuality = GeneratedArticle::whereNull('parent_article_id')->avg('quality_score') ?? 0;
        $avgSeo = GeneratedArticle::whereNull('parent_article_id')->avg('seo_score') ?? 0;

        return response()->json([
            'unprocessed_articles' => $unprocessedArticles,
            'unprocessed_questions' => $unprocessedQuestions,
            'pending_clusters' => $pendingClusters,
            'pending_question_clusters' => $pendingQClusters,
            'currently_generating' => $generatingCount,
            'generated_today' => $generatedToday,
            'total_generated' => $totalGenerated,
            'avg_quality_score' => round($avgQuality, 1),
            'avg_seo_score' => round($avgSeo, 1),
            'pipeline_ready' => $unprocessedArticles > 0 || $unprocessedQuestions > 0,
        ]);
    }
}
