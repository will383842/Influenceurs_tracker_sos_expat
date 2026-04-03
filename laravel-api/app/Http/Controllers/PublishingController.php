<?php

namespace App\Http\Controllers;

use App\Jobs\PublishContentJob;
use App\Models\PublicationQueueItem;
use App\Models\PublicationSchedule;
use App\Models\PublishingEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishingController extends Controller
{
    // ============================================================
    // Endpoints CRUD
    // ============================================================

    public function endpointsIndex(Request $request): JsonResponse
    {
        $endpoints = PublishingEndpoint::query()
            ->withCount('queueItems')
            ->with('schedule')
            ->orderBy('name')
            ->get();

        return response()->json($endpoints);
    }

    public function endpointStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'type'       => 'required|string|in:firestore,wordpress,webhook,blog',
            'config'     => 'required|array',
            'is_active'  => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['is_default'] = $validated['is_default'] ?? false;

        // If setting as default, unset other defaults
        if ($validated['is_default']) {
            PublishingEndpoint::where('is_default', true)->update(['is_default' => false]);
        }

        $endpoint = PublishingEndpoint::create($validated);

        // Create default schedule
        PublicationSchedule::create([
            'endpoint_id'          => $endpoint->id,
            'max_per_day'          => 10,
            'max_per_hour'         => 3,
            'min_interval_minutes' => 15,
            'active_hours_start'   => '08:00',
            'active_hours_end'     => '22:00',
            'active_days'          => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active'            => true,
        ]);

        return response()->json($endpoint->load('schedule'), 201);
    }

    public function endpointUpdate(Request $request, PublishingEndpoint $endpoint): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'nullable|string|max:100',
            'type'       => 'nullable|string|in:firestore,wordpress,webhook,blog',
            'config'     => 'nullable|array',
            'is_active'  => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        // If setting as default, unset other defaults
        if (!empty($validated['is_default']) && $validated['is_default']) {
            PublishingEndpoint::where('id', '!=', $endpoint->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $endpoint->update($validated);

        return response()->json($endpoint->fresh()->load('schedule'));
    }

    public function endpointDestroy(PublishingEndpoint $endpoint): JsonResponse
    {
        // Check for pending queue items
        $pendingCount = $endpoint->queueItems()->where('status', 'pending')->count();

        if ($pendingCount > 0) {
            return response()->json([
                'message' => "Cannot delete endpoint with {$pendingCount} pending items. Cancel them first.",
            ], 422);
        }

        $endpoint->schedule()?->delete();
        $endpoint->delete();

        return response()->json(null, 204);
    }

    // ============================================================
    // Queue Management
    // ============================================================

    public function queue(Request $request): JsonResponse
    {
        $request->validate([
            'status'      => 'nullable|string|in:pending,published,failed,cancelled',
            'endpoint_id' => 'nullable|integer|exists:publishing_endpoints,id',
            'sort_by'     => 'nullable|string|in:created_at,scheduled_at,published_at,attempts',
            'sort_dir'    => 'nullable|string|in:asc,desc',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $query = PublicationQueueItem::with(['publishable', 'endpoint']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('endpoint_id')) {
            $query->where('endpoint_id', $request->input('endpoint_id'));
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    public function executeQueueItem(PublicationQueueItem $item): JsonResponse
    {
        if (in_array($item->status, ['published', 'cancelled'])) {
            return response()->json([
                'message' => "Cannot execute item with status '{$item->status}'",
            ], 422);
        }

        // Reset status and force dispatch
        $item->update(['status' => 'pending']);
        PublishContentJob::dispatch($item->id);

        return response()->json([
            'message' => 'Publication job dispatched',
            'item'    => $item->fresh()->load(['publishable', 'endpoint']),
        ], 202);
    }

    public function cancelQueueItem(PublicationQueueItem $item): JsonResponse
    {
        if ($item->status === 'published') {
            return response()->json(['message' => 'Cannot cancel an already published item'], 422);
        }

        $item->update(['status' => 'cancelled']);

        return response()->json($item->fresh()->load(['publishable', 'endpoint']));
    }

    // ============================================================
    // Schedules
    // ============================================================

    public function getSchedule(PublishingEndpoint $endpoint): JsonResponse
    {
        $schedule = $endpoint->schedule;

        if (!$schedule) {
            // Create default schedule if none exists
            $schedule = PublicationSchedule::create([
                'endpoint_id'          => $endpoint->id,
                'max_per_day'          => 10,
                'max_per_hour'         => 3,
                'min_interval_minutes' => 15,
                'active_hours_start'   => '08:00',
                'active_hours_end'     => '22:00',
                'active_days'          => ['mon', 'tue', 'wed', 'thu', 'fri'],
                'is_active'            => true,
            ]);
        }

        return response()->json($schedule);
    }

    public function updateSchedule(Request $request, PublishingEndpoint $endpoint): JsonResponse
    {
        $validated = $request->validate([
            'max_per_day'           => 'nullable|integer|min:1|max:100',
            'max_per_hour'          => 'nullable|integer|min:1|max:50',
            'min_interval_minutes'  => 'nullable|integer|min:1|max:1440',
            'active_hours_start'    => 'nullable|date_format:H:i',
            'active_hours_end'      => 'nullable|date_format:H:i',
            'active_days'           => 'nullable|array',
            'active_days.*'         => 'string|in:mon,tue,wed,thu,fri,sat,sun',
            'auto_pause_on_errors'  => 'nullable|integer|min:0|max:100',
            'is_active'             => 'nullable|boolean',
        ]);

        $schedule = $endpoint->schedule;

        if ($schedule) {
            $schedule->update($validated);
        } else {
            $validated['endpoint_id'] = $endpoint->id;
            $schedule = PublicationSchedule::create(array_merge([
                'max_per_day'          => 10,
                'max_per_hour'         => 3,
                'min_interval_minutes' => 15,
                'active_hours_start'   => '08:00',
                'active_hours_end'     => '22:00',
                'active_days'          => ['mon', 'tue', 'wed', 'thu', 'fri'],
                'is_active'            => true,
            ], $validated));
        }

        return response()->json($schedule->fresh());
    }
}
