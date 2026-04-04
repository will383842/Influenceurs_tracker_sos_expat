<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FetchRssFeedsJob;
use App\Models\RssFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RssFeedController extends Controller
{
    // ─────────────────────────────────────────
    // FEEDS
    // ─────────────────────────────────────────

    public function index(): JsonResponse
    {
        $feeds = RssFeed::withCount([
            'items as items_pending_count'    => fn($q) => $q->where('status', 'pending'),
            'items as items_published_count'  => fn($q) => $q->where('status', 'published'),
            'items as items_irrelevant_count' => fn($q) => $q->where('status', 'irrelevant'),
            'items as items_failed_count'     => fn($q) => $q->where('status', 'failed'),
            'items as items_skipped_count'    => fn($q) => $q->where('status', 'skipped'),
            'items as items_total_count',
        ])->orderBy('name')->get();

        return response()->json(['data' => $feeds]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255',
            'url'                   => 'required|url|max:500|unique:rss_feeds,url',
            'language'              => 'required|string|max:5',
            'country'               => 'nullable|string|max:5',
            'category'              => 'nullable|string|max:100',
            'active'                => 'boolean',
            'fetch_interval_hours'  => 'integer|min:1|max:168',
            'relevance_threshold'   => 'integer|min:0|max:100',
            'notes'                 => 'nullable|string',
        ]);

        $feed = RssFeed::create($validated);

        return response()->json(['data' => $feed], 201);
    }

    public function show(RssFeed $feed): JsonResponse
    {
        $items = $feed->items()
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        return response()->json(['data' => $feed, 'items' => $items]);
    }

    public function update(Request $request, RssFeed $feed): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => 'sometimes|string|max:255',
            'url'                   => "sometimes|url|max:500|unique:rss_feeds,url,{$feed->id}",
            'language'              => 'sometimes|string|max:5',
            'country'               => 'nullable|string|max:5',
            'category'              => 'nullable|string|max:100',
            'active'                => 'boolean',
            'fetch_interval_hours'  => 'integer|min:1|max:168',
            'relevance_threshold'   => 'integer|min:0|max:100',
            'notes'                 => 'nullable|string',
        ]);

        $feed->update($validated);

        return response()->json(['data' => $feed]);
    }

    public function destroy(RssFeed $feed): JsonResponse
    {
        $feed->delete();

        return response()->json(['message' => 'Feed supprimé']);
    }

    public function fetchNow(RssFeed $feed): JsonResponse
    {
        FetchRssFeedsJob::dispatch($feed->id);

        return response()->json([
            'message' => "Fetch lancé pour le feed \"{$feed->name}\"",
            'feed_id' => $feed->id,
        ]);
    }

    // ─────────────────────────────────────────
    // SETTINGS QUOTA
    // ─────────────────────────────────────────

    public function getSettings(): JsonResponse
    {
        $raw   = DB::table('settings')->where('key', 'news_daily_quota')->value('value');
        $quota = $raw ? json_decode($raw, true) : [
            'quota'           => 15,
            'generated_today' => 0,
            'last_reset_date' => now()->toDateString(),
        ];

        return response()->json(['data' => $quota]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quota' => 'required|integer|min:1|max:200',
        ]);

        $raw   = DB::table('settings')->where('key', 'news_daily_quota')->value('value');
        $quota = $raw ? json_decode($raw, true) : [];

        $quota['quota'] = $validated['quota'];

        DB::table('settings')->updateOrInsert(
            ['key' => 'news_daily_quota'],
            ['value' => json_encode($quota), 'updated_at' => now()]
        );

        return response()->json(['data' => $quota]);
    }
}
