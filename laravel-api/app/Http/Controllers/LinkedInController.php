<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateLinkedInPostJob;
use App\Models\GeneratedArticle;
use App\Models\LinkedInPost;
use App\Models\QaEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LinkedInController extends Controller
{
    // ── Stats ──────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $postsThisWeek = LinkedInPost::whereBetween('scheduled_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->orWhereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();
        $scheduled     = LinkedInPost::where('status', 'scheduled')->count();
        $published     = LinkedInPost::where('status', 'published')->count();
        $totalReach    = LinkedInPost::where('status', 'published')->sum('reach');
        $avgEngagement = LinkedInPost::where('status', 'published')->avg('engagement_rate') ?? 0;

        $topDay = LinkedInPost::where('status', 'published')
            ->selectRaw('day_type, AVG(engagement_rate) as avg_eng')
            ->groupBy('day_type')
            ->orderByDesc('avg_eng')
            ->value('day_type');

        $usedArticleIds = LinkedInPost::whereIn('status', ['draft', 'scheduled', 'published', 'generating'])
            ->where('source_type', 'article')
            ->whereNotNull('source_id')
            ->pluck('source_id');

        $usedFaqIds = LinkedInPost::whereIn('status', ['draft', 'scheduled', 'published', 'generating'])
            ->where('source_type', 'faq')
            ->whereNotNull('source_id')
            ->pluck('source_id');

        $availableArticles = GeneratedArticle::published()
            ->whereNotIn('id', $usedArticleIds)
            ->count();

        $availableFaqs = QaEntry::published()
            ->whereNotIn('id', $usedFaqIds)
            ->count();

        return response()->json([
            'posts_this_week'      => $postsThisWeek,
            'posts_scheduled'      => $scheduled,
            'posts_published'      => $published,
            'total_reach'          => (int) $totalReach,
            'avg_engagement_rate'  => round($avgEngagement, 2),
            'top_performing_day'   => $topDay ?? 'monday',
            'available_articles'   => $availableArticles,
            'available_faqs'       => $availableFaqs,
        ]);
    }

    // ── Queue (paginated) ──────────────────────────────────────────────

    public function queue(Request $request): JsonResponse
    {
        $query = LinkedInPost::latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = min((int) ($request->per_page ?? 25), 50);

        return response()->json($query->paginate($perPage));
    }

    // ── Auto-select best unpublished source ───────────────────────────

    public function autoSelect(Request $request): JsonResponse
    {
        $request->validate([
            'source_type' => 'required|in:article,faq,tip',
            'lang'        => 'required|in:fr,en,both',
        ]);

        $lang       = $request->lang === 'both' ? 'fr' : $request->lang;
        $sourceType = $request->source_type;

        if ($sourceType === 'article') {
            $usedIds = LinkedInPost::whereIn('status', ['draft', 'scheduled', 'published', 'generating'])
                ->where('source_type', 'article')
                ->whereNotNull('source_id')
                ->pluck('source_id');

            $source = GeneratedArticle::published()
                ->where('language', $lang)
                ->whereNotIn('id', $usedIds)
                ->orderByDesc('editorial_score')
                ->first(['id', 'title', 'language', 'country', 'editorial_score', 'quality_score', 'keywords_primary']);

            $availableCount = GeneratedArticle::published()
                ->where('language', $lang)
                ->whereNotIn('id', $usedIds)
                ->count();

            return response()->json([
                'found'           => $source !== null,
                'source_type'     => 'article',
                'source_id'       => $source?->id,
                'title'           => $source?->title,
                'country'         => $source?->country,
                'editorial_score' => $source?->editorial_score,
                'quality_score'   => $source?->quality_score,
                'available_count' => $availableCount,
            ]);
        }

        if ($sourceType === 'faq') {
            $usedIds = LinkedInPost::whereIn('status', ['draft', 'scheduled', 'published', 'generating'])
                ->where('source_type', 'faq')
                ->whereNotNull('source_id')
                ->pluck('source_id');

            $source = QaEntry::published()
                ->where('language', $lang)
                ->whereNotIn('id', $usedIds)
                ->orderByDesc('seo_score')
                ->first(['id', 'question', 'language', 'country', 'seo_score', 'keywords_primary']);

            $availableCount = QaEntry::published()
                ->where('language', $lang)
                ->whereNotIn('id', $usedIds)
                ->count();

            return response()->json([
                'found'           => $source !== null,
                'source_type'     => 'faq',
                'source_id'       => $source?->id,
                'title'           => $source?->question,
                'country'         => $source?->country,
                'seo_score'       => $source?->seo_score,
                'available_count' => $availableCount,
            ]);
        }

        // tip — free generation, no source needed
        return response()->json([
            'found'           => true,
            'source_type'     => 'tip',
            'source_id'       => null,
            'title'           => 'Génération libre (conseil / tip)',
            'available_count' => 999,
        ]);
    }

    // ── Generate (async) ───────────────────────────────────────────────

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'source_type' => 'required|in:article,faq,tip,news,case_study',
            'source_id'   => 'nullable|integer',
            'day_type'    => 'required|in:monday,tuesday,wednesday,thursday,friday',
            'lang'        => 'required|in:fr,en,both',
            'account'     => 'required|in:page,personal,both',
        ]);

        $sourceTitle = $this->resolveSourceTitle($request->source_type, $request->source_id);

        $post = LinkedInPost::create([
            'source_type'  => $request->source_type,
            'source_id'    => $request->source_id,
            'source_title' => $sourceTitle,
            'day_type'     => $request->day_type,
            'lang'         => $request->lang,
            'account'      => $request->account,
            'hook'         => '',
            'body'         => '',
            'hashtags'     => [],
            'status'       => 'generating',
            'phase'        => (int) ($request->phase ?? 1),
        ]);

        GenerateLinkedInPostJob::dispatch($post->id);

        return response()->json($post, 202);
    }

    // ── Update ─────────────────────────────────────────────────────────

    public function update(Request $request, LinkedInPost $post): JsonResponse
    {
        $post->update($request->only(['hook', 'body', 'hashtags', 'lang', 'account', 'day_type', 'status']));
        return response()->json($post);
    }

    // ── Schedule ───────────────────────────────────────────────────────

    public function schedule(Request $request, LinkedInPost $post): JsonResponse
    {
        $request->validate(['scheduled_at' => 'required|date|after:now']);
        $post->update(['status' => 'scheduled', 'scheduled_at' => $request->scheduled_at]);
        return response()->json($post);
    }

    // ── Publish (manual / OAuth future) ───────────────────────────────

    public function publish(LinkedInPost $post): JsonResponse
    {
        // TODO: integrate LinkedIn API v2 when OAuth tokens are configured.
        $post->update([
            'status'       => 'published',
            'published_at' => now(),
        ]);

        return response()->json($post);
    }

    // ── Delete ─────────────────────────────────────────────────────────

    public function destroy(LinkedInPost $post): JsonResponse
    {
        $post->delete();
        return response()->json(['message' => 'Post supprimé']);
    }

    // ── Private helpers ────────────────────────────────────────────────

    private function resolveSourceTitle(string $type, ?int $id): ?string
    {
        if (!$id) {
            return null;
        }

        try {
            return match ($type) {
                'article' => GeneratedArticle::find($id)?->title,
                'faq'     => QaEntry::find($id)?->question,
                default   => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }
}
