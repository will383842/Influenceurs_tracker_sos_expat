<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeSeoJob;
use App\Jobs\GenerateComparativeJob;
use App\Jobs\PublishContentJob;
use App\Models\Comparative;
use App\Models\PublicationQueueItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComparativeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => 'nullable|string|in:draft,review,published,generating,failed',
            'language' => 'nullable|string|max:5',
            'country'  => 'nullable|string|max:100',
            'search'   => 'nullable|string|max:200',
            'sort_by'  => 'nullable|string|in:created_at,updated_at,published_at,seo_score,quality_score,title',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Comparative::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }
        if ($request->filled('country')) {
            $query->where('country', $request->input('country'));
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('keyword_primary', 'ilike', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $query->with(['seoAnalysis', 'creator:id,name']);

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show(Comparative $comparative): JsonResponse
    {
        $comparative->load([
            'seoAnalysis',
            'translations:id,title,language,status,slug,parent_id',
            'generationLogs',
            'creator:id,name',
        ]);

        return response()->json($comparative);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'nullable|string|max:500',
            'entities'     => 'required|array|min:2|max:5',
            'entities.*'   => 'string|max:200',
            'language'     => 'required|string|in:fr,en,de,es,pt,ru,zh,ar,hi',
            'country'      => 'nullable|string|max:100',
            'keywords'     => 'nullable|array',
            'keywords.*'   => 'string|max:100',
            'tone'         => 'nullable|string|in:professional,casual,expert,friendly',
            'instructions' => 'nullable|string|max:2000',
        ]);

        $validated['created_by'] = $request->user()->id;

        GenerateComparativeJob::dispatch($validated);

        return response()->json([
            'message' => 'Comparative generation started',
            'params'  => $validated,
        ], 202);
    }

    public function update(Request $request, Comparative $comparative): JsonResponse
    {
        if (!in_array($comparative->status, ['draft', 'review'])) {
            return response()->json([
                'message' => 'Only draft or review comparatives can be edited',
            ], 422);
        }

        $validated = $request->validate([
            'title'            => 'nullable|string|max:300',
            'content_html'     => 'nullable|string',
            'excerpt'          => 'nullable|string|max:500',
            'meta_title'       => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'keyword_primary'  => 'nullable|string|max:100',
            'entities'         => 'nullable|array|min:2|max:5',
            'comparison_data'  => 'nullable|array',
            'status'           => 'nullable|string|in:draft,review',
        ]);

        $contentChanged = isset($validated['content_html']) && $validated['content_html'] !== $comparative->content_html;

        $comparative->update($validated);

        if ($contentChanged) {
            AnalyzeSeoJob::dispatch(Comparative::class, $comparative->id);
        }

        return response()->json($comparative->fresh()->load(['seoAnalysis', 'creator:id,name']));
    }

    public function destroy(Comparative $comparative): JsonResponse
    {
        $comparative->delete();

        return response()->json(null, 204);
    }

    public function publish(Request $request, Comparative $comparative): JsonResponse
    {
        $validated = $request->validate([
            'endpoint_id'  => 'required|integer|exists:publishing_endpoints,id',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $queueItem = PublicationQueueItem::create([
            'publishable_type' => Comparative::class,
            'publishable_id'   => $comparative->id,
            'endpoint_id'      => $validated['endpoint_id'],
            'status'           => 'pending',
            'priority'         => $request->input('priority', 'default'),
            'scheduled_at'     => $validated['scheduled_at'] ?? null,
            'max_attempts'     => 5,
        ]);

        if (empty($validated['scheduled_at'])) {
            PublishContentJob::dispatch($queueItem->id);
        }

        return response()->json([
            'message'    => 'Comparative queued for publishing',
            'queue_item' => $queueItem->load('endpoint'),
        ], 202);
    }
}
