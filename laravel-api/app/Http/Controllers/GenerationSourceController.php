<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerationSourceController extends Controller
{
    /**
     * List all source categories with counts (brut vs nettoyé).
     */
    public function categories(): JsonResponse
    {
        $data = Cache::remember('gen-source-categories', 300, function () {
            return DB::table('generation_source_categories as gsc')
                ->leftJoin('generation_source_items as gsi', 'gsi.category_slug', '=', 'gsc.slug')
                ->selectRaw("
                    gsc.id, gsc.slug, gsc.name, gsc.description, gsc.icon, gsc.sort_order,
                    COUNT(gsi.id) as total_items,
                    COUNT(gsi.id) FILTER (WHERE gsi.is_cleaned = true) as cleaned_items,
                    COUNT(gsi.id) FILTER (WHERE gsi.is_cleaned = false) as raw_items,
                    COUNT(gsi.id) FILTER (WHERE gsi.processing_status = 'ready') as ready_items,
                    COUNT(DISTINCT gsi.country_slug) FILTER (WHERE gsi.country_slug IS NOT NULL) as countries,
                    COUNT(DISTINCT gsi.theme) FILTER (WHERE gsi.theme IS NOT NULL) as themes,
                    COUNT(DISTINCT gsi.sub_category) FILTER (WHERE gsi.sub_category IS NOT NULL) as sub_categories
                ")
                ->groupBy('gsc.id', 'gsc.slug', 'gsc.name', 'gsc.description', 'gsc.icon', 'gsc.sort_order')
                ->orderBy('gsc.sort_order')
                ->get();
        });

        return response()->json($data);
    }

    /**
     * Single item detail with full content from source table.
     */
    public function itemDetail(int $id): JsonResponse
    {
        $item = DB::table('generation_source_items')->where('id', $id)->first();
        if (!$item) return response()->json(['error' => 'Not found'], 404);

        $sourceData = null;
        $pillarSources = null;

        if ($item->source_type === 'pillar' && $item->data_json) {
            // Pillar article: load all referenced source articles
            $data = json_decode($item->data_json, true);
            $articleIds = $data['article_ids'] ?? [];
            if (!empty($articleIds)) {
                $pillarSources = DB::table('content_articles as ca')
                    ->leftJoin('content_sources as cs', 'ca.source_id', '=', 'cs.id')
                    ->whereIn('ca.id', $articleIds)
                    ->select('ca.id', 'ca.title', 'ca.url', 'ca.content_text', 'ca.word_count', 'ca.category', 'ca.section', 'ca.language', 'ca.meta_description', 'cs.name as source_name')
                    ->orderByDesc('ca.word_count')
                    ->get();
            }
            // Also load top Q&A questions for this country/theme
            $qaQuestions = DB::table('content_questions')
                ->where('country_slug', $data['country_slug'] ?? '')
                ->where('views', '>=', 50)
                ->orderByDesc('views')
                ->limit(20)
                ->select('id', 'title', 'url', 'views', 'replies', 'country')
                ->get();
        } elseif ($item->source_id) {
            if ($item->source_type === 'article') {
                $sourceData = DB::table('content_articles as ca')
                    ->leftJoin('content_sources as cs', 'ca.source_id', '=', 'cs.id')
                    ->where('ca.id', $item->source_id)
                    ->select('ca.id', 'ca.title', 'ca.url', 'ca.content_text', 'ca.word_count', 'ca.category', 'ca.section', 'ca.language', 'ca.meta_title', 'ca.meta_description', 'ca.scraped_at', 'cs.name as source_name', 'cs.base_url as source_url')
                    ->first();
            } elseif ($item->source_type === 'question') {
                $sourceData = DB::table('content_questions')
                    ->where('id', $item->source_id)
                    ->select('id', 'title', 'url', 'country', 'city', 'replies', 'views', 'is_sticky', 'is_closed', 'last_post_date', 'last_post_author', 'language', 'article_status')
                    ->first();
            }
        }

        return response()->json([
            'item'           => $item,
            'source'         => $sourceData,
            'pillar_sources' => $pillarSources,
            'qa_questions'   => $qaQuestions ?? null,
        ]);
    }

    /**
     * Items in a category, with filters for sub_category, country, theme, status.
     */
    public function categoryItems(string $categorySlug, Request $request): JsonResponse
    {
        $query = DB::table('generation_source_items')
            ->where('category_slug', $categorySlug)
            ->select('id', 'source_type', 'source_id', 'title', 'country', 'country_slug', 'theme', 'sub_category', 'language', 'word_count', 'quality_score', 'is_cleaned', 'processing_status', 'used_count', 'input_quality', 'data_json');

        // Filter: brut vs nettoyé
        if ($request->filled('cleaned')) {
            $query->where('is_cleaned', $request->boolean('cleaned'));
        }

        if ($request->filled('status')) {
            $query->where('processing_status', $request->input('status'));
        }

        if ($request->filled('sub_category')) {
            $query->where('sub_category', $request->input('sub_category'));
        }

        if ($request->filled('country_slug')) {
            $query->where('country_slug', $request->input('country_slug'));
        }

        if ($request->filled('theme')) {
            $query->where('theme', $request->input('theme'));
        }

        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where('title', 'ilike', '%' . $search . '%');
        }

        $sortBy = $request->input('sort', 'quality_score');
        $sortDir = $request->input('dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $items = $query->paginate($request->input('per_page', 50));

        // Sub-categories breakdown for sidebar
        $subCategories = DB::table('generation_source_items')
            ->where('category_slug', $categorySlug)
            ->selectRaw('sub_category, COUNT(*) as count, COUNT(*) FILTER (WHERE is_cleaned) as cleaned, COUNT(*) FILTER (WHERE NOT is_cleaned) as raw')
            ->whereNotNull('sub_category')
            ->groupBy('sub_category')
            ->orderByDesc('count')
            ->get();

        // Countries in this category
        $countries = DB::table('generation_source_items')
            ->where('category_slug', $categorySlug)
            ->whereNotNull('country_slug')
            ->selectRaw('country, country_slug, COUNT(*) as count')
            ->groupBy('country', 'country_slug')
            ->orderByDesc('count')
            ->limit(50)
            ->get();

        // Themes in this category
        $themes = DB::table('generation_source_items')
            ->where('category_slug', $categorySlug)
            ->whereNotNull('theme')
            ->selectRaw('theme, COUNT(*) as count')
            ->groupBy('theme')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'items'          => $items,
            'sub_categories' => $subCategories,
            'countries'      => $countries,
            'themes'         => $themes,
        ]);
    }

    /**
     * Command Center: aggregated data for all 14 sources with pipeline status.
     * Response shape matches ContentCommandCenter.tsx interfaces exactly:
     *   sources[]  → PipelineSourceStatus  (quota_daily, pipeline_status, generated_today…)
     *   pipeline   → PipelineGlobal        (is_running, currently_generating…)
     */
    public function commandCenter(): JsonResponse
    {
        $data = Cache::remember('gen-source-command-center', 30, function () {
            $today     = now()->toDateString();
            $weekStart = now()->subDays(6)->toDateString();

            // Per-source configs
            $categories = DB::table('generation_source_categories')
                ->select('slug', 'config_json')
                ->get()
                ->keyBy('slug');

            // Articles generated per source: today + this week + last activity
            $articleStats = DB::table('generated_articles')
                ->whereDate('created_at', '>=', $weekStart)
                ->selectRaw("
                    source_slug,
                    COUNT(*) FILTER (WHERE DATE(created_at) = ?)         as generated_today,
                    COUNT(*)                                              as generated_week,
                    MAX(created_at)                                       as last_generated_at,
                    COUNT(*) FILTER (WHERE status = 'processing')        as current_running
                ", [$today])
                ->groupBy('source_slug')
                ->get()
                ->keyBy('source_slug');

            // Build sources array in format expected by PipelineSourceStatus
            $sources = $categories->map(function ($cat) use ($articleStats) {
                $config  = json_decode($cat->config_json ?? '{}', true);
                $art     = $articleStats->get($cat->slug);
                $isPaused = $config['is_paused'] ?? false;
                $quota    = (int) ($config['daily_quota'] ?? 0);
                $running  = (int) ($art->current_running ?? 0);

                if ($isPaused || $quota === 0) {
                    $status = 'paused';
                } elseif ($running > 0) {
                    $status = 'generating';
                } elseif ($art && (int) $art->generated_today > 0) {
                    $status = 'active';
                } else {
                    $status = 'idle';
                }

                return [
                    'slug'             => $cat->slug,
                    'pipeline_status'  => $status,
                    'generated_today'  => (int) ($art->generated_today ?? 0),
                    'generated_week'   => (int) ($art->generated_week ?? 0),
                    'quota_daily'      => $quota,
                    'last_generated_at'=> $art->last_generated_at ?? null,
                    'current_running'  => $running,
                    'is_paused'        => $isPaused,
                    'is_visible'       => $config['is_visible'] ?? true,
                ];
            })->values();

            // Global pipeline object (PipelineGlobal)
            $globalRaw = DB::table('generated_articles')
                ->selectRaw("
                    COUNT(*) FILTER (WHERE DATE(created_at) = ?)  as generated_today,
                    COUNT(*) FILTER (WHERE status = 'processing') as currently_generating,
                    COUNT(*) FILTER (WHERE status = 'error')      as errors_count,
                    MAX(created_at)                               as last_activity
                ", [$today])
                ->first();

            $activeSources = $categories->filter(function ($cat) {
                $cfg = json_decode($cat->config_json ?? '{}', true);
                return !($cfg['is_paused'] ?? false) && (int) ($cfg['daily_quota'] ?? 0) > 0;
            })->count();

            $queueSize = DB::table('generation_source_items')
                ->where('processing_status', 'ready')
                ->count();

            $pipeline = [
                'is_running'            => (int) ($globalRaw->currently_generating ?? 0) > 0,
                'currently_generating'  => (int) ($globalRaw->currently_generating ?? 0),
                'queue_size'            => (int) $queueSize,
                'last_activity'         => $globalRaw->last_activity ?? null,
                'errors_count'          => (int) ($globalRaw->errors_count ?? 0),
                'active_sources'        => $activeSources,
                'generated_today_total' => (int) ($globalRaw->generated_today ?? 0),
            ];

            return [
                'sources'  => $sources,
                'pipeline' => $pipeline,
            ];
        });

        return response()->json($data);
    }

    /**
     * Trigger generation for a specific source slug.
     */
    public function trigger(string $slug): JsonResponse
    {
        $category = DB::table('generation_source_categories')->where('slug', $slug)->first();
        if (!$category) {
            return response()->json(['error' => "Source '{$slug}' introuvable."], 404);
        }

        $config = json_decode($category->config_json ?? '{}', true);
        if ($config['is_paused'] ?? false) {
            return response()->json(['error' => "Source '{$slug}' est en pause."], 409);
        }

        \App\Jobs\GenerateFromSourceJob::dispatch($slug, (int) ($config['daily_quota'] ?? 3));

        Cache::forget('gen-source-command-center');

        return response()->json([
            'ok'      => true,
            'message' => "Génération lancée pour '{$slug}'.",
            'slug'    => $slug,
        ]);
    }

    /**
     * Pause or resume a source (toggle is_paused in config_json).
     */
    public function pause(string $slug, Request $request): JsonResponse
    {
        $category = DB::table('generation_source_categories')->where('slug', $slug)->first();
        if (!$category) {
            return response()->json(['error' => "Source '{$slug}' introuvable."], 404);
        }

        $config = json_decode($category->config_json ?? '{}', true);
        $newPaused = $request->boolean('paused', !($config['is_paused'] ?? false));
        $config['is_paused'] = $newPaused;

        DB::table('generation_source_categories')
            ->where('slug', $slug)
            ->update(['config_json' => json_encode($config), 'updated_at' => now()]);

        Cache::forget('gen-source-command-center');
        Cache::forget('gen-source-categories');

        return response()->json([
            'ok'        => true,
            'slug'      => $slug,
            'is_paused' => $newPaused,
            'message'   => $newPaused ? "Source '{$slug}' mise en pause." : "Source '{$slug}' relancée.",
        ]);
    }

    /**
     * Toggle visibility of a source (is_visible in config_json).
     */
    public function visibility(string $slug, Request $request): JsonResponse
    {
        $request->validate(['visible' => 'required|boolean']);

        $category = DB::table('generation_source_categories')->where('slug', $slug)->first();
        if (!$category) {
            return response()->json(['error' => "Source '{$slug}' introuvable."], 404);
        }

        $config = json_decode($category->config_json ?? '{}', true);
        $config['is_visible'] = $request->boolean('visible');

        DB::table('generation_source_categories')
            ->where('slug', $slug)
            ->update(['config_json' => json_encode($config), 'updated_at' => now()]);

        Cache::forget('gen-source-command-center');
        Cache::forget('gen-source-categories');

        return response()->json([
            'ok'         => true,
            'slug'       => $slug,
            'is_visible' => $config['is_visible'],
        ]);
    }

    /**
     * Update daily_quota for a source.
     */
    public function quota(string $slug, Request $request): JsonResponse
    {
        // Accept both 'quota' and 'daily' (frontend sends 'daily')
        $request->validate(['quota' => 'nullable|integer|min:0|max:100', 'daily' => 'nullable|integer|min:0|max:100']);
        $value = $request->filled('daily') ? $request->input('daily') : $request->input('quota');
        if ($value === null) return response()->json(['error' => 'Missing quota or daily field'], 422);

        $category = DB::table('generation_source_categories')->where('slug', $slug)->first();
        if (!$category) {
            return response()->json(['error' => "Source '{$slug}' introuvable."], 404);
        }

        $config = json_decode($category->config_json ?? '{}', true);
        $config['daily_quota'] = (int) $value;

        DB::table('generation_source_categories')
            ->where('slug', $slug)
            ->update(['config_json' => json_encode($config), 'updated_at' => now()]);

        Cache::forget('gen-source-command-center');

        return response()->json([
            'ok'          => true,
            'slug'        => $slug,
            'daily_quota' => $config['daily_quota'],
        ]);
    }

    /**
     * Trigger generation for ALL sources using the percentage scheduler.
     * Respects total_daily config + weight_percent per source.
     */
    public function triggerAll(Request $request): JsonResponse
    {
        $scheduler = app(\App\Services\Content\GenerationSourceSchedulerService::class);
        $total     = $request->filled('total') ? (int) $request->input('total') : null;
        $result    = $scheduler->runDaily($total);

        $launched = array_keys($result['dispatched']);
        $skipped  = $result['skipped'];

        return response()->json([
            'ok'              => true,
            'launched'        => $launched,
            'skipped'         => $skipped,
            'total_target'    => $result['total_target'],
            'total_dispatched'=> $result['total_dispatched'],
            'dispatched'      => $result['dispatched'],
            'message'         => $result['total_dispatched'] . ' articles répartis sur ' . count($launched) . ' sources.',
        ]);
    }

    /**
     * Get + update global scheduler config (total_daily, schedule_mode).
     */
    public function schedulerConfig(Request $request): JsonResponse
    {
        $scheduler = app(\App\Services\Content\GenerationSourceSchedulerService::class);

        if ($request->isMethod('POST')) {
            $request->validate([
                'total_daily'   => 'required|integer|min:1|max:500',
                'schedule_mode' => 'nullable|in:percentage,manual',
            ]);
            $scheduler->updateGlobalConfig(
                (int) $request->input('total_daily'),
                $request->input('schedule_mode', 'percentage')
            );
        }

        // Always return current preview distribution
        $preview = $scheduler->previewDistribution();
        return response()->json(['ok' => true, 'preview' => $preview]);
    }

    /**
     * Update weight_percent for a source (percentage mode).
     */
    public function weight(string $slug, Request $request): JsonResponse
    {
        $request->validate(['weight' => 'required|integer|min:0|max:100']);

        $category = DB::table('generation_source_categories')->where('slug', $slug)->first();
        if (!$category) {
            return response()->json(['error' => "Source '{$slug}' introuvable."], 404);
        }

        $scheduler = app(\App\Services\Content\GenerationSourceSchedulerService::class);
        $scheduler->updateWeight($slug, (int) $request->input('weight'));

        return response()->json([
            'ok'     => true,
            'slug'   => $slug,
            'weight' => (int) $request->input('weight'),
        ]);
    }

    /**
     * Global stats: brut vs nettoyé across all categories.
     */
    public function stats(): JsonResponse
    {
        $data = Cache::remember('gen-source-stats', 300, function () {
            $overall = DB::table('generation_source_items')
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE is_cleaned) as cleaned,
                    COUNT(*) FILTER (WHERE NOT is_cleaned) as raw,
                    COUNT(*) FILTER (WHERE processing_status = 'ready') as ready,
                    COUNT(*) FILTER (WHERE processing_status = 'used') as used,
                    COUNT(DISTINCT country_slug) FILTER (WHERE country_slug IS NOT NULL) as countries,
                    COUNT(DISTINCT theme) FILTER (WHERE theme IS NOT NULL) as themes
                ")
                ->first();

            $byStatus = DB::table('generation_source_items')
                ->selectRaw('processing_status, COUNT(*) as count')
                ->groupBy('processing_status')
                ->get();

            $bySourceType = DB::table('generation_source_items')
                ->selectRaw('source_type, COUNT(*) as count')
                ->groupBy('source_type')
                ->get();

            return [
                'overall'        => $overall,
                'by_status'      => $byStatus,
                'by_source_type' => $bySourceType,
            ];
        });

        return response()->json($data);
    }
}
