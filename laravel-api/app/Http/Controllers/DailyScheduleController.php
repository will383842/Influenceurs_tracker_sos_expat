<?php

namespace App\Http\Controllers;

use App\Jobs\RunDailyContentJob;
use App\Models\DailyContentLog;
use App\Models\DailyContentSchedule;
use App\Models\GeneratedArticle;
use App\Services\Content\DailyContentSchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DailyScheduleController extends Controller
{
    public function __construct(
        private DailyContentSchedulerService $schedulerService,
    ) {}

    /**
     * Return active schedule + today's log.
     */
    public function getSchedule(): JsonResponse
    {
        $status = $this->schedulerService->getStatus();

        return response()->json($status);
    }

    /**
     * Validate + update the schedule config.
     */
    public function updateSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                    => 'nullable|string|max:100',
            'is_active'               => 'nullable|boolean',
            'pillar_articles_per_day' => 'nullable|integer|min:0|max:20',
            'normal_articles_per_day' => 'nullable|integer|min:0|max:50',
            'qa_per_day'              => 'nullable|integer|min:0|max:100',
            'comparatives_per_day'    => 'nullable|integer|min:0|max:10',
            'publish_per_day'         => 'nullable|integer|min:0|max:50',
            'publish_start_hour'      => 'nullable|integer|min:0|max:23',
            'publish_end_hour'        => 'nullable|integer|min:1|max:24',
            'publish_irregular'       => 'nullable|boolean',
            'target_country'          => 'nullable|string|max:100',
            'target_category'         => 'nullable|string|max:50',
            'min_quality_score'       => 'nullable|integer|min:50|max:100',
        ]);

        $schedule = DailyContentSchedule::active()->where('name', 'default')->first();

        if (!$schedule) {
            $validated['name'] = $validated['name'] ?? 'default';
            $validated['created_by'] = $request->user()->id;
            $schedule = DailyContentSchedule::create($validated);
        } else {
            $schedule->update($validated);
        }

        return response()->json($schedule->fresh()->load('todayLog'));
    }

    /**
     * Return last 30 days of daily logs.
     */
    public function getHistory(Request $request): JsonResponse
    {
        $request->validate([
            'days'     => 'nullable|integer|min:1|max:365',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $days = (int) $request->input('days', 30);
        $perPage = min((int) $request->input('per_page', 30), 100);

        $schedule = DailyContentSchedule::active()->where('name', 'default')->first();

        if (!$schedule) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $logs = DailyContentLog::where('schedule_id', $schedule->id)
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->orderByDesc('date')
            ->paginate($perPage);

        return response()->json($logs);
    }

    /**
     * Dispatch RunDailyContentJob immediately.
     */
    public function runNow(): JsonResponse
    {
        RunDailyContentJob::dispatch();

        return response()->json([
            'message' => 'Daily content generation job dispatched',
        ], 202);
    }

    /**
     * Append titles to the custom_titles array.
     */
    public function addCustomTitles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titles'   => 'required|array|min:1|max:50',
            'titles.*' => 'required|string|max:300',
        ]);

        $schedule = DailyContentSchedule::active()->where('name', 'default')->first();

        if (!$schedule) {
            $schedule = DailyContentSchedule::create([
                'name'       => 'default',
                'is_active'  => true,
                'created_by' => $request->user()->id,
            ]);
        }

        $existing = $schedule->custom_titles ?? [];
        $merged = array_values(array_unique(array_merge($existing, $validated['titles'])));

        $schedule->update(['custom_titles' => $merged]);

        return response()->json([
            'message'       => count($validated['titles']) . ' titles added',
            'total_pending' => count($merged),
            'custom_titles' => $merged,
        ]);
    }

    // ──────────────────────────────────────────────
    // TAXONOMY DISTRIBUTION
    // ──────────────────────────────────────────────

    public function getTaxonomyDistribution(): JsonResponse
    {
        $config = Cache::remember('taxonomy_distribution', 3600, function () {
            $schedule = DailyContentSchedule::active()->where('name', 'default')->first();
            return $schedule?->taxonomy_distribution;
        });

        $total = $config['total_articles_per_day'] ?? 20;
        $distribution = $config['distribution'] ?? [
            ['content_type' => 'article', 'label' => 'Article standard', 'percentage' => 35, 'is_active' => true],
            ['content_type' => 'guide', 'label' => 'Guide (pilier)', 'percentage' => 10, 'is_active' => true],
            ['content_type' => 'qa', 'label' => 'Q&A / FAQ', 'percentage' => 20, 'is_active' => true],
            ['content_type' => 'comparative', 'label' => 'Comparatif', 'percentage' => 10, 'is_active' => true],
            ['content_type' => 'news', 'label' => 'Actualite', 'percentage' => 10, 'is_active' => true],
            ['content_type' => 'tutorial', 'label' => 'Tutoriel', 'percentage' => 5, 'is_active' => true],
            ['content_type' => 'landing', 'label' => 'Landing page', 'percentage' => 5, 'is_active' => true],
            ['content_type' => 'press_release', 'label' => 'Communique presse', 'percentage' => 5, 'is_active' => true],
        ];

        // Calculate counts
        foreach ($distribution as &$item) {
            $item['calculated_count'] = $item['is_active'] ? (int) round($total * $item['percentage'] / 100) : 0;
        }

        return response()->json([
            'total_articles_per_day' => $total,
            'distribution' => $distribution,
        ]);
    }

    public function updateTaxonomyDistribution(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'total_articles_per_day' => 'required|integer|min:1|max:500',
            'distribution' => 'required|array|min:1',
            'distribution.*.content_type' => 'required|string',
            'distribution.*.label' => 'required|string',
            'distribution.*.percentage' => 'required|integer|min:0|max:100',
            'distribution.*.is_active' => 'required|boolean',
        ]);

        // Validate total active percentages = 100
        $totalPct = collect($validated['distribution'])
            ->where('is_active', true)
            ->sum('percentage');

        if ($totalPct !== 100) {
            return response()->json([
                'message' => "Le total des pourcentages actifs doit etre 100% (actuellement {$totalPct}%)",
            ], 422);
        }

        $schedule = DailyContentSchedule::active()->where('name', 'default')->first();
        if (!$schedule) {
            $schedule = DailyContentSchedule::create([
                'name' => 'default',
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);
        }

        $schedule->update([
            'taxonomy_distribution' => $validated,
            // Also sync legacy fields for backward compatibility
            'normal_articles_per_day' => $this->getCountForType($validated, 'article'),
            'pillar_articles_per_day' => $this->getCountForType($validated, 'guide'),
            'qa_per_day' => $this->getCountForType($validated, 'qa'),
            'comparatives_per_day' => $this->getCountForType($validated, 'comparative'),
        ]);

        Cache::forget('taxonomy_distribution');

        return response()->json([
            'message' => 'Distribution sauvegardee',
            'total_articles_per_day' => $validated['total_articles_per_day'],
            'distribution' => $validated['distribution'],
        ]);
    }

    private function getCountForType(array $validated, string $type): int
    {
        $total = $validated['total_articles_per_day'];
        $item = collect($validated['distribution'])->firstWhere('content_type', $type);
        if (!$item || !$item['is_active']) return 0;
        return (int) round($total * $item['percentage'] / 100);
    }

    // ──────────────────────────────────────────────
    // PUBLICATION STATS
    // ──────────────────────────────────────────────

    public function getPublicationStats(): JsonResponse
    {
        $schedule = DailyContentSchedule::active()->where('name', 'default')->first();
        $publishPerDay = $schedule?->publish_per_day ?? 10;

        $unpublished = GeneratedArticle::whereIn('status', ['review', 'draft', 'scheduled'])
            ->whereNull('parent_article_id') // Only originals, not translations
            ->count();

        $byStatus = GeneratedArticle::whereIn('status', ['review', 'draft', 'scheduled', 'generating'])
            ->whereNull('parent_article_id')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byContentType = GeneratedArticle::whereIn('status', ['review', 'draft', 'scheduled'])
            ->whereNull('parent_article_id')
            ->select('content_type', DB::raw('count(*) as count'))
            ->groupBy('content_type')
            ->pluck('count', 'content_type')
            ->toArray();

        $publishedToday = GeneratedArticle::where('status', 'published')
            ->whereDate('published_at', today())
            ->count();

        $publishedThisWeek = GeneratedArticle::where('status', 'published')
            ->where('published_at', '>=', now()->startOfWeek())
            ->count();

        $publishedThisMonth = GeneratedArticle::where('status', 'published')
            ->where('published_at', '>=', now()->startOfMonth())
            ->count();

        $generationToday = GeneratedArticle::whereDate('created_at', today())
            ->whereNull('parent_article_id')
            ->count();

        $totalPublished = GeneratedArticle::where('status', 'published')
            ->whereNull('parent_article_id')
            ->count();

        return response()->json([
            'unpublished_stock' => $unpublished,
            'by_status' => $byStatus,
            'by_content_type' => $byContentType,
            'publish_per_day' => $publishPerDay,
            'days_of_stock' => $publishPerDay > 0 ? (int) ceil($unpublished / $publishPerDay) : 0,
            'published_today' => $publishedToday,
            'published_this_week' => $publishedThisWeek,
            'published_this_month' => $publishedThisMonth,
            'generation_today' => $generationToday,
            'total_published' => $totalPublished,
        ]);
    }

    public function updatePublicationRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publish_per_day' => 'required|integer|min:0|max:200',
            'start_hour' => 'required|integer|min:0|max:23',
            'end_hour' => 'required|integer|min:1|max:24',
            'irregular' => 'required|boolean',
        ]);

        $schedule = DailyContentSchedule::active()->where('name', 'default')->first();
        if ($schedule) {
            $schedule->update([
                'publish_per_day' => $validated['publish_per_day'],
                'publish_start_hour' => $validated['start_hour'],
                'publish_end_hour' => $validated['end_hour'],
                'publish_irregular' => $validated['irregular'],
            ]);
        }

        return response()->json(['message' => 'Rythme de publication mis a jour']);
    }

    // ──────────────────────────────────────────────
    // QUALITY MONITORING
    // ──────────────────────────────────────────────

    public function getQualityMonitoring(Request $request): JsonResponse
    {
        $query = GeneratedArticle::whereNull('parent_article_id');

        $status = $request->input('status');
        if ($status === 'similar') {
            $query->where('quality_score', '<', 85)->where('quality_score', '>=', 60);
        } elseif ($status === 'plagiarized') {
            $query->where('quality_score', '<', 60);
        } elseif ($status === 'low-quality') {
            $query->where('quality_score', '<', 70);
        } else {
            $query->where(function ($q) {
                $q->where('quality_score', '<', 70)
                  ->orWhere('seo_score', '<', 60);
            });
        }

        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }
        if ($request->filled('content_type')) {
            $query->where('content_type', $request->input('content_type'));
        }

        $flagged = $query->orderBy('quality_score', 'asc')->limit(100)->get();

        // Global stats
        $totalArticles = GeneratedArticle::whereNull('parent_article_id')->count();
        $avgQuality = GeneratedArticle::whereNull('parent_article_id')->avg('quality_score') ?? 0;
        $avgSeo = GeneratedArticle::whereNull('parent_article_id')->avg('seo_score') ?? 0;
        $plagiarized = GeneratedArticle::whereNull('parent_article_id')->where('quality_score', '<', 60)->count();
        $similar = GeneratedArticle::whereNull('parent_article_id')->whereBetween('quality_score', [60, 84])->count();
        $original = $totalArticles - $plagiarized - $similar;

        return response()->json([
            'flagged_articles' => $flagged,
            'stats' => [
                'total_checked' => $totalArticles,
                'original' => max(0, $original),
                'similar' => $similar,
                'plagiarized' => $plagiarized,
                'avg_quality_score' => round($avgQuality, 1),
                'avg_seo_score' => round($avgSeo, 1),
            ],
        ]);
    }

    public function rejectArticle(Request $request, int $id): JsonResponse
    {
        $article = GeneratedArticle::findOrFail($id);
        $article->update([
            'status' => 'archived',
        ]);

        return response()->json(['message' => 'Article rejete', 'article' => $article->fresh()]);
    }

    public function approveArticle(int $id): JsonResponse
    {
        $article = GeneratedArticle::findOrFail($id);
        $article->update([
            'status' => 'review',
        ]);

        return response()->json(['message' => 'Article approuve', 'article' => $article->fresh()]);
    }
}
