<?php

namespace App\Http\Controllers;

use App\Jobs\PublishContentJob;
use App\Models\PressDossier;
use App\Models\PressDossierItem;
use App\Models\PressRelease;
use App\Models\PublicationQueueItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PressController extends Controller
{
    // ============================================================
    // Press Releases
    // ============================================================

    public function releaseIndex(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => 'nullable|string|in:draft,review,published,generating,failed',
            'language' => 'nullable|string|max:5',
            'search'   => 'nullable|string|max:200',
            'sort_by'  => 'nullable|string|in:created_at,updated_at,published_at,seo_score,title',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = PressRelease::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where('title', 'ilike', "%{$search}%");
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $query->with(['seoAnalysis', 'createdBy:id,name']);

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    public function releaseShow(PressRelease $release): JsonResponse
    {
        $release->load([
            'seoAnalysis',
            'translations:id,title,language,status,slug,parent_id',
            'generationLogs',
            'createdBy:id,name',
        ]);

        return response()->json($release);
    }

    public function releaseStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:300',
            'language'         => 'required|string|in:fr,en,de,es,pt,ru,zh,ar,hi',
            'country'          => 'nullable|string|max:100',
            'content_html'     => 'nullable|string',
            'excerpt'          => 'nullable|string|max:500',
            'meta_title'       => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'keyword_primary'  => 'nullable|string|max:100',
            'tone'             => 'nullable|string|in:professional,casual,expert,friendly',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'draft';
        $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(6);

        $release = PressRelease::create($validated);

        return response()->json($release->load('createdBy:id,name'), 201);
    }

    public function releaseUpdate(Request $request, PressRelease $release): JsonResponse
    {
        if (!in_array($release->status, ['draft', 'review'])) {
            return response()->json([
                'message' => 'Only draft or review press releases can be edited',
            ], 422);
        }

        $validated = $request->validate([
            'title'            => 'nullable|string|max:300',
            'content_html'     => 'nullable|string',
            'excerpt'          => 'nullable|string|max:500',
            'meta_title'       => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'keyword_primary'  => 'nullable|string|max:100',
            'status'           => 'nullable|string|in:draft,review',
        ]);

        $release->update($validated);

        return response()->json($release->fresh()->load(['seoAnalysis', 'createdBy:id,name']));
    }

    public function releaseDestroy(PressRelease $release): JsonResponse
    {
        $release->delete();

        return response()->json(null, 204);
    }

    public function releasePublish(Request $request, PressRelease $release): JsonResponse
    {
        $validated = $request->validate([
            'endpoint_id'  => 'required|integer|exists:publishing_endpoints,id',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $queueItem = PublicationQueueItem::create([
            'publishable_type' => PressRelease::class,
            'publishable_id'   => $release->id,
            'endpoint_id'      => $validated['endpoint_id'],
            'status'           => 'pending',
            'priority'         => 0,
            'scheduled_at'     => $validated['scheduled_at'] ?? null,
            'max_attempts'     => 5,
        ]);

        if (empty($validated['scheduled_at'])) {
            PublishContentJob::dispatch($queueItem->id);
        }

        return response()->json([
            'message'    => 'Press release queued for publishing',
            'queue_item' => $queueItem->load('endpoint'),
        ], 202);
    }

    public function releaseExportPdf(PressRelease $release)
    {
        $pdf = Pdf::loadView('exports.press-release', [
            'release' => $release,
        ]);

        $filename = Str::slug($release->title) . '.pdf';

        return $pdf->download($filename);
    }

    public function releaseExportWord(PressRelease $release)
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();

        $section = $phpWord->addSection();
        $section->addTitle($release->title, 1);

        if ($release->excerpt) {
            $section->addText($release->excerpt, ['italic' => true, 'size' => 12]);
            $section->addTextBreak(1);
        }

        // Convert HTML to plain text for Word
        $plainContent = strip_tags($release->content_html ?? '');
        $paragraphs = preg_split('/\n{2,}/', $plainContent);

        foreach ($paragraphs as $paragraph) {
            $trimmed = trim($paragraph);
            if (!empty($trimmed)) {
                $section->addText($trimmed, ['size' => 11], ['spaceAfter' => 120]);
            }
        }

        $section->addTextBreak(2);
        $section->addText(
            'Published: ' . ($release->published_at?->format('Y-m-d') ?? 'Draft'),
            ['italic' => true, 'size' => 9, 'color' => '888888']
        );

        $filename = Str::slug($release->title) . '.docx';
        $tempPath = storage_path('app/temp/' . $filename);

        // Ensure temp directory exists
        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempPath);

        return response()->download($tempPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    // ============================================================
    // Press Dossiers
    // ============================================================

    public function dossierIndex(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => 'nullable|string|in:draft,published',
            'language' => 'nullable|string|max:5',
            'search'   => 'nullable|string|max:200',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = PressDossier::withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where('name', 'ilike', "%{$search}%");
        }

        $query->with('createdBy:id,name')
              ->orderByDesc('created_at');

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    public function dossierShow(PressDossier $dossier): JsonResponse
    {
        $dossier->load([
            'items.itemable',
            'createdBy:id,name',
        ]);

        return response()->json($dossier);
    }

    public function dossierStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:300',
            'language'        => 'required|string|in:fr,en,de,es,pt,ru,zh,ar,hi',
            'description'     => 'nullable|string|max:2000',
            'cover_image_url' => 'nullable|url|max:500',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'draft';
        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(6);

        $dossier = PressDossier::create($validated);

        return response()->json($dossier->load('createdBy:id,name'), 201);
    }

    public function dossierUpdate(Request $request, PressDossier $dossier): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'nullable|string|max:300',
            'description'     => 'nullable|string|max:2000',
            'cover_image_url' => 'nullable|url|max:500',
            'status'          => 'nullable|string|in:draft,published',
        ]);

        $dossier->update($validated);

        return response()->json($dossier->fresh()->load(['items.itemable', 'createdBy:id,name']));
    }

    public function dossierDestroy(PressDossier $dossier): JsonResponse
    {
        $dossier->items()->delete();
        $dossier->delete();

        return response()->json(null, 204);
    }

    public function dossierAddItem(Request $request, PressDossier $dossier): JsonResponse
    {
        $validated = $request->validate([
            'itemable_type' => 'required|string|in:GeneratedArticle,PressRelease,Comparative',
            'itemable_id'   => 'required|integer',
        ]);

        // Map short type name to full class
        $typeMap = [
            'GeneratedArticle' => \App\Models\GeneratedArticle::class,
            'PressRelease'     => \App\Models\PressRelease::class,
            'Comparative'      => \App\Models\Comparative::class,
        ];

        $fullType = $typeMap[$validated['itemable_type']];

        // Verify item exists
        $fullType::findOrFail($validated['itemable_id']);

        // Check if already in dossier
        $exists = $dossier->items()
            ->where('itemable_type', $fullType)
            ->where('itemable_id', $validated['itemable_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Item already in dossier'], 422);
        }

        $maxOrder = $dossier->items()->max('sort_order') ?? -1;

        $item = PressDossierItem::create([
            'dossier_id'    => $dossier->id,
            'itemable_type' => $fullType,
            'itemable_id'   => $validated['itemable_id'],
            'sort_order'    => $maxOrder + 1,
        ]);

        return response()->json($item->load('itemable'), 201);
    }

    public function dossierRemoveItem(PressDossier $dossier, PressDossierItem $item): JsonResponse
    {
        if ($item->dossier_id !== $dossier->id) {
            return response()->json(['message' => 'Item does not belong to this dossier'], 422);
        }

        $item->delete();

        return response()->json(null, 204);
    }

    public function dossierReorderItems(Request $request, PressDossier $dossier): JsonResponse
    {
        $validated = $request->validate([
            'item_ids'   => 'required|array',
            'item_ids.*' => 'integer|exists:press_dossier_items,id',
        ]);

        foreach ($validated['item_ids'] as $index => $itemId) {
            PressDossierItem::where('id', $itemId)
                ->where('dossier_id', $dossier->id)
                ->update(['sort_order' => $index]);
        }

        return response()->json($dossier->fresh()->load('items.itemable'));
    }

    public function dossierExportPdf(PressDossier $dossier)
    {
        $dossier->load('items.itemable');

        $pdf = Pdf::loadView('exports.press-dossier', [
            'dossier' => $dossier,
        ]);

        $filename = Str::slug($dossier->name) . '-dossier.pdf';

        return $pdf->download($filename);
    }
}
