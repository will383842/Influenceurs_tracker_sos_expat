<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessContentCampaignJob;
use App\Models\ContentCampaignItem;
use App\Models\ContentGenerationCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ContentCampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'        => 'nullable|string|in:draft,running,paused,completed,cancelled,failed',
            'campaign_type' => 'nullable|string|in:country_coverage,thematic,pillar_cluster,comparative_series,custom',
            'per_page'      => 'nullable|integer|min:1|max:100',
        ]);

        $query = ContentGenerationCampaign::withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('campaign_type')) {
            $query->where('campaign_type', $request->input('campaign_type'));
        }

        $query->with('creator:id,name')
              ->orderByDesc('created_at');

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show(ContentGenerationCampaign $campaign): JsonResponse
    {
        $campaign->load(['items.itemable', 'creator:id,name']);
        $campaign->loadCount('items');

        return response()->json($campaign);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                      => 'required|string|max:300',
            'description'               => 'nullable|string|max:2000',
            'campaign_type'             => 'required|string|in:country_coverage,thematic,pillar_cluster,comparative_series,custom',
            'config'                    => 'required|array',
            'config.country'            => 'nullable|string|max:100',
            'config.themes'             => 'nullable|array',
            'config.themes.*'           => 'string|max:200',
            'config.languages'          => 'nullable|array',
            'config.languages.*'        => 'string|in:fr,en,de,es,pt,ru,zh,ar,hi',
            'config.articles_per_day'   => 'nullable|integer|min:1|max:50',
            'config.preset_id'          => 'nullable|integer|exists:generation_presets,id',
            'config.content_type'       => 'nullable|string|in:article,guide,comparative,tutorial',
            'config.tone'               => 'nullable|string|in:professional,casual,expert,friendly',
            'config.length'             => 'nullable|string|in:short,medium,long',
            'config.generate_faq'       => 'nullable|boolean',
            'config.image_source'       => 'nullable|string|in:unsplash,dalle,none',
            'config.auto_internal_links'  => 'nullable|boolean',
            'config.auto_affiliate_links' => 'nullable|boolean',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'draft';
        $validated['total_items'] = 0;
        $validated['completed_items'] = 0;
        $validated['failed_items'] = 0;

        $campaign = ContentGenerationCampaign::create($validated);

        return response()->json($campaign->load('creator:id,name'), 201);
    }

    public function update(Request $request, ContentGenerationCampaign $campaign): JsonResponse
    {
        if (!in_array($campaign->status, ['draft', 'paused'])) {
            return response()->json([
                'message' => 'Only draft or paused campaigns can be edited',
            ], 422);
        }

        $validated = $request->validate([
            'name'        => 'nullable|string|max:300',
            'description' => 'nullable|string|max:2000',
            'config'      => 'nullable|array',
        ]);

        $campaign->update($validated);

        return response()->json($campaign->fresh()->load('creator:id,name'));
    }

    public function destroy(ContentGenerationCampaign $campaign): JsonResponse
    {
        if ($campaign->status === 'running') {
            return response()->json([
                'message' => 'Cannot delete a running campaign. Pause or cancel it first.',
            ], 422);
        }

        $campaign->items()->delete();
        $campaign->delete();

        return response()->json(null, 204);
    }

    // ============================================================
    // Campaign Lifecycle
    // ============================================================

    public function start(ContentGenerationCampaign $campaign): JsonResponse
    {
        if (!in_array($campaign->status, ['draft'])) {
            return response()->json([
                'message' => 'Only draft campaigns can be started',
            ], 422);
        }

        $config = $campaign->config ?? [];
        $themes = $config['themes'] ?? $config['topics'] ?? $config['titles'] ?? [];
        $languages = $config['languages'] ?? ['fr'];
        $country = $config['country'] ?? null;

        // Generate campaign items based on type and config
        $items = [];
        $sortOrder = 0;
        $articlesPerDay = $config['articles_per_day'] ?? 5;
        $intervalMinutes = max(1, (int) round((24 * 60) / $articlesPerDay));

        switch ($campaign->campaign_type) {
            case 'country_coverage':
            case 'thematic':
                // One item per theme x language
                foreach ($themes as $theme) {
                    foreach ($languages as $language) {
                        $items[] = [
                            'campaign_id'     => $campaign->id,
                            'title_hint'      => $theme,
                            'config_override' => [
                                'topic'    => $theme,
                                'language' => $language,
                                'country'  => $country,
                            ],
                            'status'       => 'pending',
                            'sort_order'   => $sortOrder,
                            'scheduled_at' => now()->addMinutes($sortOrder * $intervalMinutes),
                        ];
                        $sortOrder++;
                    }
                }
                break;

            case 'pillar_cluster':
                // First item is pillar, rest are cluster articles
                if (!empty($themes)) {
                    // Pillar article
                    $items[] = [
                        'campaign_id'     => $campaign->id,
                        'title_hint'      => $themes[0] . ' (Pillar)',
                        'config_override' => [
                            'topic'        => $themes[0],
                            'language'     => $languages[0] ?? 'fr',
                            'country'      => $country,
                            'content_type' => 'guide',
                            'length'       => 'long',
                        ],
                        'status'       => 'pending',
                        'sort_order'   => $sortOrder++,
                        'scheduled_at' => now(),
                    ];

                    // Cluster articles
                    foreach (array_slice($themes, 1) as $theme) {
                        foreach ($languages as $language) {
                            $items[] = [
                                'campaign_id'     => $campaign->id,
                                'title_hint'      => $theme,
                                'config_override' => [
                                    'topic'    => $theme,
                                    'language' => $language,
                                    'country'  => $country,
                                ],
                                'status'       => 'pending',
                                'sort_order'   => $sortOrder,
                                'scheduled_at' => now()->addMinutes($sortOrder * $intervalMinutes),
                            ];
                            $sortOrder++;
                        }
                    }
                }
                break;

            case 'comparative_series':
                // Each theme becomes a comparative
                foreach ($themes as $theme) {
                    foreach ($languages as $language) {
                        $items[] = [
                            'campaign_id'     => $campaign->id,
                            'title_hint'      => $theme,
                            'config_override' => [
                                'topic'        => $theme,
                                'language'     => $language,
                                'country'      => $country,
                                'content_type' => 'comparative',
                            ],
                            'status'       => 'pending',
                            'sort_order'   => $sortOrder,
                            'scheduled_at' => now()->addMinutes($sortOrder * $intervalMinutes),
                        ];
                        $sortOrder++;
                    }
                }
                break;

            case 'custom':
            default:
                // Custom: one item per theme, single language
                foreach ($themes as $theme) {
                    $items[] = [
                        'campaign_id'     => $campaign->id,
                        'title_hint'      => $theme,
                        'config_override' => [
                            'topic'    => $theme,
                            'language' => $languages[0] ?? 'fr',
                            'country'  => $country,
                        ],
                        'status'       => 'pending',
                        'sort_order'   => $sortOrder,
                        'scheduled_at' => now()->addMinutes($sortOrder * $intervalMinutes),
                    ];
                    $sortOrder++;
                }
                break;
        }

        // Bulk create items
        foreach ($items as $itemData) {
            ContentCampaignItem::create($itemData);
        }

        $campaign->update([
            'status'      => 'running',
            'started_at'  => now(),
            'total_items' => count($items),
        ]);

        // Dispatch the first processing job
        ProcessContentCampaignJob::dispatch($campaign->id);

        return response()->json($campaign->fresh()->load(['items', 'creator:id,name']));
    }

    public function pause(ContentGenerationCampaign $campaign): JsonResponse
    {
        if ($campaign->status !== 'running') {
            return response()->json(['message' => 'Only running campaigns can be paused'], 422);
        }

        $campaign->update(['status' => 'paused']);

        return response()->json($campaign->fresh());
    }

    public function resume(ContentGenerationCampaign $campaign): JsonResponse
    {
        if ($campaign->status !== 'paused') {
            return response()->json(['message' => 'Only paused campaigns can be resumed'], 422);
        }

        $campaign->update(['status' => 'running']);

        // Re-dispatch processing
        ProcessContentCampaignJob::dispatch($campaign->id);

        return response()->json($campaign->fresh());
    }

    public function cancel(ContentGenerationCampaign $campaign): JsonResponse
    {
        if (!in_array($campaign->status, ['running', 'paused'])) {
            return response()->json(['message' => 'Only running or paused campaigns can be cancelled'], 422);
        }

        // Cancel all pending items
        $campaign->items()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $campaign->update([
            'status'         => 'cancelled',
            'completed_at'   => now(),
            'completed_items' => $campaign->items()->where('status', 'completed')->count(),
            'failed_items'   => $campaign->items()->where('status', 'failed')->count(),
        ]);

        return response()->json($campaign->fresh());
    }

    public function items(ContentGenerationCampaign $campaign): JsonResponse
    {
        $items = $campaign->items()
            ->with('itemable')
            ->orderBy('sort_order')
            ->get();

        return response()->json($items);
    }
}
