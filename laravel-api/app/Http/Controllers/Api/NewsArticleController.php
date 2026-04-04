<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateNewsArticleJob;
use App\Models\RssFeed;
use App\Models\RssFeedItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewsArticleController extends Controller
{
    // ─────────────────────────────────────────
    // ITEMS
    // ─────────────────────────────────────────

    public function items(Request $request): JsonResponse
    {
        $query = RssFeedItem::with('feed:id,name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('feed_id')) {
            $query->where('feed_id', $request->integer('feed_id'));
        }

        if ($request->filled('relevance_score_min')) {
            $query->where('relevance_score', '>=', $request->integer('relevance_score_min'));
        }

        if ($request->filled('date_from')) {
            $query->where('published_at', '>=', $request->date_from);
        }

        $items = $query->orderByDesc('published_at')->paginate(50);

        return response()->json($items);
    }

    public function generateItem(RssFeedItem $item): JsonResponse
    {
        if (! in_array($item->status, ['pending', 'failed', 'skipped'], true)) {
            return response()->json([
                'message' => "Item status={$item->status}, seuls pending/failed/skipped peuvent être régénérés",
            ], 422);
        }

        // Remettre en pending pour permettre la génération
        $item->update(['status' => 'pending', 'error_message' => null]);

        GenerateNewsArticleJob::dispatch($item->id);

        return response()->json(['message' => "Génération lancée pour l'item #{$item->id}"]);
    }

    public function generateBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit'         => 'integer|min:1|max:20',
            'feed_id'       => 'nullable|integer|exists:rss_feeds,id',
            'min_relevance' => 'integer|min:0|max:100',
        ]);

        $limit       = $validated['limit'] ?? 10;
        $minRelevance = $validated['min_relevance'] ?? 0;

        // Vérifier quota restant
        $remaining = $this->getRemainingQuota();

        if ($remaining <= 0) {
            return response()->json([
                'message'         => 'Quota journalier atteint',
                'dispatched'      => 0,
                'remaining_quota' => 0,
            ]);
        }

        $limit = min($limit, $remaining);

        $query = RssFeedItem::where('status', 'pending')
            ->whereNotNull('relevance_score')
            ->orderByDesc('relevance_score')
            ->orderByDesc('published_at');

        if (! empty($validated['feed_id'])) {
            $query->where('feed_id', $validated['feed_id']);
        }

        if ($minRelevance > 0) {
            $query->where('relevance_score', '>=', $minRelevance);
        }

        $items = $query->limit($limit)->get();

        $dispatched = 0;
        foreach ($items as $item) {
            GenerateNewsArticleJob::dispatch($item->id);
            $dispatched++;
        }

        return response()->json([
            'message'         => "{$dispatched} jobs de génération dispatchés",
            'dispatched'      => $dispatched,
            'remaining_quota' => $remaining - $dispatched,
        ]);
    }

    public function skipItem(RssFeedItem $item): JsonResponse
    {
        $item->update(['status' => 'skipped']);

        return response()->json(['message' => "Item #{$item->id} marqué comme ignoré"]);
    }

    // ─────────────────────────────────────────
    // STATS
    // ─────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $byStatus = RssFeedItem::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $activeFeeds = RssFeed::active()->count();

        // Quota journalier
        $raw   = DB::table('settings')->where('key', 'news_daily_quota')->value('value');
        $quota = $raw ? json_decode($raw, true) : ['quota' => 15, 'generated_today' => 0];
        $today = now()->toDateString();

        if (($quota['last_reset_date'] ?? '') !== $today) {
            $quota['generated_today'] = 0;
        }

        return response()->json([
            'items_by_status' => $byStatus,
            'active_feeds'    => $activeFeeds,
            'quota'           => [
                'daily_limit'     => $quota['quota'] ?? 15,
                'generated_today' => $quota['generated_today'] ?? 0,
                'remaining'       => max(0, ($quota['quota'] ?? 15) - ($quota['generated_today'] ?? 0)),
            ],
        ]);
    }

    public function progress(): JsonResponse
    {
        $progress = Cache::get('news_generation_progress', []);

        return response()->json(['data' => $progress]);
    }

    // ─────────────────────────────────────────
    // UNPUBLISH
    // ─────────────────────────────────────────

    /**
     * Dépublier un article news — retire de sos-expat.com immédiatement.
     * Appelle le Blog via webhook pour passer l'article en 'draft',
     * puis marque l'item local comme 'skipped'.
     */
    public function unpublishItem(RssFeedItem $item): JsonResponse
    {
        if ($item->status !== 'published') {
            return response()->json(['error' => 'Seuls les articles publiés peuvent être dépubliés.'], 422);
        }

        if (! $item->blog_article_uuid) {
            return response()->json(['error' => 'UUID Blog manquant pour cet article.'], 422);
        }

        $blogUrl = rtrim(config('services.blog.url', ''), '/');
        $blogKey = config('services.blog.api_key', '');

        if (! $blogUrl || ! $blogKey) {
            return response()->json(['error' => 'Configuration Blog manquante (BLOG_API_URL / BLOG_API_KEY).'], 500);
        }

        try {
            $response = Http::withToken($blogKey)->timeout(20)
                ->post("{$blogUrl}/api/v1/webhook/unpublish-news/{$item->blog_article_uuid}");

            if (! $response->successful()) {
                Log::warning('NewsArticleController: Blog unpublish failed', [
                    'status'  => $response->status(),
                    'uuid'    => $item->blog_article_uuid,
                    'item_id' => $item->id,
                ]);
                return response()->json([
                    'error' => 'Le Blog a retourné une erreur : ' . $response->status(),
                ], 502);
            }
        } catch (\Throwable $e) {
            Log::error('NewsArticleController: Blog unpublish exception', [
                'error'   => $e->getMessage(),
                'item_id' => $item->id,
            ]);
            return response()->json(['error' => 'Impossible de joindre le Blog.'], 503);
        }

        $item->update([
            'status'        => 'skipped',
            'error_message' => 'Dépublié manuellement le ' . now()->toDateTimeString(),
        ]);

        return response()->json([
            'message' => 'Article dépublié avec succès.',
            'item_id' => $item->id,
        ]);
    }

    // ─────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────

    private function getRemainingQuota(): int
    {
        try {
            $raw   = DB::table('settings')->where('key', 'news_daily_quota')->value('value');
            $quota = $raw ? json_decode($raw, true) : [];

            $quotaLimit     = (int) ($quota['quota'] ?? 15);
            $generatedToday = (int) ($quota['generated_today'] ?? 0);
            $today          = now()->toDateString();

            if (($quota['last_reset_date'] ?? '') !== $today) {
                $generatedToday = 0;
            }

            return max(0, $quotaLimit - $generatedToday);

        } catch (\Throwable) {
            return 15;
        }
    }
}
