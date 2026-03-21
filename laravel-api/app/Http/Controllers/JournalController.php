<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalController extends Controller
{
    /**
     * List journal entries (manual + auto activity logs).
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with(['user:id,name', 'influenceur:id,name'])
            ->orderByDesc('created_at');

        // Researcher scoping
        if ($request->user()->isResearcher()) {
            $query->where('user_id', $request->user()->id);
        }

        // Filters
        if ($request->manual_only) {
            $query->where('is_manual', true);
        }
        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        }
        if ($request->contact_type) {
            $query->where('contact_type', $request->contact_type);
        }
        if ($request->user_id && $request->user()->isAdmin()) {
            $query->where('user_id', $request->user_id);
        }

        $perPage = min((int) ($request->per_page ?? 50), 200);

        return response()->json(
            $query->paginate($perPage)
        );
    }

    /**
     * Add a manual journal entry (from Mission Control's journal feature).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'note'         => 'required|string|max:2000',
            'contact_type' => 'nullable|in:' . implode(',', ContactType::values()),
        ]);

        $log = ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'        => 'manual_log',
            'is_manual'     => true,
            'manual_note'   => $data['note'],
            'contact_type'  => $data['contact_type'] ?? null,
            'details'       => [
                'time' => now()->format('H:i'),
            ],
        ]);

        return response()->json($log->load('user:id,name'), 201);
    }

    /**
     * Today's summary: actions count, by type, journal entries.
     */
    public function today(Request $request)
    {
        $userId = $request->user()->isResearcher()
            ? $request->user()->id
            : ($request->user_id ?? null);

        $query = ActivityLog::whereDate('created_at', now()->toDateString());
        if ($userId) $query->where('user_id', $userId);

        $entries = $query->orderByDesc('created_at')
            ->with('user:id,name')
            ->get();

        $manualEntries = $entries->where('is_manual', true);
        $autoEntries = $entries->where('is_manual', false);

        // Count by action type
        $byAction = $autoEntries->groupBy('action')
            ->map(fn($group) => $group->count());

        // Count by contact type
        $byContactType = $entries->whereNotNull('contact_type')
            ->groupBy('contact_type')
            ->map(fn($group) => $group->count());

        return response()->json([
            'total_actions'   => $entries->count(),
            'manual_entries'  => $manualEntries->values(),
            'by_action'       => $byAction,
            'by_contact_type' => $byContactType,
        ]);
    }

    /**
     * Weekly summary with daily breakdown.
     */
    public function weekly(Request $request)
    {
        $userId = $request->user()->isResearcher()
            ? $request->user()->id
            : ($request->user_id ?? null);

        $query = ActivityLog::where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->select(
                DB::raw("DATE(created_at) as date"),
                DB::raw('count(*) as total'),
                DB::raw("sum(case when is_manual then 1 else 0 end) as manual_count"),
            )
            ->groupBy('date')
            ->orderBy('date');

        if ($userId) $query->where('user_id', $userId);

        return response()->json($query->get());
    }
}
