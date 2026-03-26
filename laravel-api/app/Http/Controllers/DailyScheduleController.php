<?php

namespace App\Http\Controllers;

use App\Jobs\RunDailyContentJob;
use App\Models\DailyContentLog;
use App\Models\DailyContentSchedule;
use App\Services\Content\DailyContentSchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
