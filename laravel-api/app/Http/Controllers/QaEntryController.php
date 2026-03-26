<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateQaEntriesJob;
use App\Models\GeneratedArticle;
use App\Models\PublicationQueueItem;
use App\Models\QaEntry;
use App\Services\Content\QaGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QaEntryController extends Controller
{
    /**
     * List Q&A entries with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'language'    => 'nullable|string|max:5',
            'country'     => 'nullable|string|max:100',
            'category'    => 'nullable|string|max:100',
            'status'      => 'nullable|string|in:draft,review,published,generating',
            'source_type' => 'nullable|string|in:faq,paa,manual',
            'search'      => 'nullable|string|max:200',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $query = QaEntry::query();

        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }
        if ($request->filled('country')) {
            $query->where('country', $request->input('country'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where('question', 'ilike', "%{$search}%");
        }

        $query->with('creator:id,name')
            ->orderByDesc('created_at');

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Show full Q&A detail.
     */
    public function show(QaEntry $qa): JsonResponse
    {
        $qa->load([
            'parentArticle:id,title,slug,language',
            'cluster:id,name',
            'translations:id,question,language,status,slug,parent_qa_id',
            'creator:id,name',
        ]);

        return response()->json($qa);
    }

    /**
     * Create a Q&A entry manually.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question'             => 'required|string|max:500',
            'answer_short'         => 'nullable|string|max:500',
            'answer_detailed_html' => 'nullable|string',
            'country'              => 'required|string|max:100',
            'category'             => 'required|string|max:100',
            'language'             => 'required|string|in:fr,en,de,es,pt,ru,zh,ar,hi',
            'parent_article_id'    => 'nullable|integer|exists:generated_articles,id',
        ]);

        $slug = Str::slug($validated['question']);

        $qa = QaEntry::create([
            'uuid' => (string) Str::uuid(),
            'question' => $validated['question'],
            'answer_short' => $validated['answer_short'] ?? null,
            'answer_detailed_html' => $validated['answer_detailed_html'] ?? null,
            'country' => $validated['country'],
            'category' => $validated['category'],
            'language' => $validated['language'],
            'slug' => $slug,
            'source_type' => 'manual',
            'status' => 'draft',
            'parent_article_id' => $validated['parent_article_id'] ?? null,
            'word_count' => str_word_count(strip_tags($validated['answer_detailed_html'] ?? '')),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($qa, 201);
    }

    /**
     * Update a Q&A entry.
     */
    public function update(Request $request, QaEntry $qa): JsonResponse
    {
        $validated = $request->validate([
            'question'             => 'nullable|string|max:500',
            'answer_short'         => 'nullable|string|max:500',
            'answer_detailed_html' => 'nullable|string',
            'meta_title'           => 'nullable|string|max:70',
            'meta_description'     => 'nullable|string|max:160',
            'keywords_primary'     => 'nullable|string|max:100',
            'status'               => 'nullable|string|in:draft,review',
        ]);

        $qa->update(array_filter($validated, fn ($v) => $v !== null));

        if (isset($validated['answer_detailed_html'])) {
            $qa->update(['word_count' => str_word_count(strip_tags($validated['answer_detailed_html']))]);
        }

        return response()->json($qa->fresh());
    }

    /**
     * Soft delete a Q&A entry.
     */
    public function destroy(QaEntry $qa): JsonResponse
    {
        $qa->delete();

        return response()->json(['message' => 'Q&A entry deleted']);
    }

    /**
     * Publish a Q&A entry (add to publication queue).
     */
    public function publish(Request $request, QaEntry $qa): JsonResponse
    {
        $request->validate([
            'endpoint_id' => 'nullable|integer|exists:publishing_endpoints,id',
        ]);

        $qa->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Add to publication queue if endpoint specified
        if ($request->filled('endpoint_id')) {
            PublicationQueueItem::create([
                'publishable_type' => QaEntry::class,
                'publishable_id' => $qa->id,
                'endpoint_id' => $request->input('endpoint_id'),
                'status' => 'pending',
                'priority' => 5,
            ]);
        }

        return response()->json($qa->fresh());
    }

    /**
     * Generate Q&A entries from an article's FAQs.
     */
    public function generateFromArticle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_id' => 'required|integer|exists:generated_articles,id',
            'faq_ids'     => 'nullable|array',
            'faq_ids.*'   => 'integer|exists:generated_article_faqs,id',
        ]);

        GenerateQaEntriesJob::dispatch(
            $validated['article_id'],
            $validated['faq_ids'] ?? []
        );

        return response()->json([
            'message' => 'Q&A generation from article FAQs dispatched',
            'article_id' => $validated['article_id'],
        ], 202);
    }

    /**
     * Generate Q&A entries from PAA (People Also Ask).
     */
    public function generateFromPaa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic'    => 'required|string|max:300',
            'country'  => 'required|string|max:100',
            'language' => 'nullable|string|in:fr,en,de,es,pt,ru,zh,ar,hi',
        ]);

        try {
            $service = app(QaGenerationService::class);
            $entries = $service->generateFromPaa(
                $validated['topic'],
                $validated['country'],
                $validated['language'] ?? 'fr'
            );

            return response()->json([
                'message' => 'Q&A entries generated from PAA',
                'entries_created' => $entries->count(),
                'entries' => $entries,
            ]);
        } catch (\Throwable $e) {
            Log::error('QaEntryController: PAA generation failed', ['message' => $e->getMessage()]);

            return response()->json([
                'message' => 'PAA generation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk publish multiple Q&A entries.
     */
    public function bulkPublish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'         => 'required|array|min:1|max:100',
            'ids.*'       => 'integer|exists:qa_entries,id',
            'endpoint_id' => 'nullable|integer|exists:publishing_endpoints,id',
        ]);

        $updated = QaEntry::whereIn('id', $validated['ids'])
            ->whereIn('status', ['draft', 'review'])
            ->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

        // Add to publication queue if endpoint specified
        if ($request->filled('endpoint_id')) {
            foreach ($validated['ids'] as $id) {
                PublicationQueueItem::create([
                    'publishable_type' => QaEntry::class,
                    'publishable_id' => $id,
                    'endpoint_id' => $validated['endpoint_id'],
                    'status' => 'pending',
                    'priority' => 5,
                ]);
            }
        }

        return response()->json([
            'message' => 'Bulk publish completed',
            'updated' => $updated,
        ]);
    }
}
