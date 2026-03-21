<?php

namespace App\Http\Controllers;

use App\Models\ContentMetric;
use Illuminate\Http\Request;

class ContentMetricController extends Controller
{
    /**
     * Get metrics for a date range (default: last 30 days).
     */
    public function index(Request $request)
    {
        $days = min((int) ($request->days ?? 30), 365);
        $metrics = ContentMetric::latest($days);

        // Compute summary
        $latest = $metrics->last();
        $first = $metrics->first();

        return response()->json([
            'metrics' => $metrics,
            'summary' => $latest ? [
                'landing_pages'   => $latest->landing_pages,
                'articles'        => $latest->articles,
                'indexed_pages'   => $latest->indexed_pages,
                'top10_positions' => $latest->top10_positions,
                'position_zero'   => $latest->position_zero,
                'ai_cited'        => $latest->ai_cited,
                'daily_visits'    => $latest->daily_visits,
                'calls_generated' => $latest->calls_generated,
                'revenue_cents'   => $latest->revenue_cents,
            ] : null,
            'trends' => $first && $latest ? [
                'visits_growth'  => $first->daily_visits > 0
                    ? round(($latest->daily_visits - $first->daily_visits) / $first->daily_visits * 100, 1)
                    : 0,
                'calls_growth'   => $first->calls_generated > 0
                    ? round(($latest->calls_generated - $first->calls_generated) / $first->calls_generated * 100, 1)
                    : 0,
                'revenue_growth' => $first->revenue_cents > 0
                    ? round(($latest->revenue_cents - $first->revenue_cents) / $first->revenue_cents * 100, 1)
                    : 0,
            ] : null,
        ]);
    }

    /**
     * Get or create today's metrics.
     */
    public function today()
    {
        return response()->json(ContentMetric::today());
    }

    /**
     * Update today's metrics (partial update).
     */
    public function updateToday(Request $request)
    {
        $data = $request->validate([
            'landing_pages'   => 'sometimes|integer|min:0',
            'articles'        => 'sometimes|integer|min:0',
            'indexed_pages'   => 'sometimes|integer|min:0',
            'top10_positions' => 'sometimes|integer|min:0',
            'position_zero'   => 'sometimes|integer|min:0',
            'ai_cited'        => 'sometimes|integer|min:0',
            'daily_visits'    => 'sometimes|integer|min:0',
            'calls_generated' => 'sometimes|integer|min:0',
            'revenue_cents'   => 'sometimes|integer|min:0',
        ]);

        $metric = ContentMetric::today();
        $metric->update($data);

        return response()->json($metric);
    }

    /**
     * Bulk update or create metrics for a specific date (admin).
     */
    public function upsert(Request $request)
    {
        $data = $request->validate([
            'date'            => 'required|date',
            'landing_pages'   => 'sometimes|integer|min:0',
            'articles'        => 'sometimes|integer|min:0',
            'indexed_pages'   => 'sometimes|integer|min:0',
            'top10_positions' => 'sometimes|integer|min:0',
            'position_zero'   => 'sometimes|integer|min:0',
            'ai_cited'        => 'sometimes|integer|min:0',
            'daily_visits'    => 'sometimes|integer|min:0',
            'calls_generated' => 'sometimes|integer|min:0',
            'revenue_cents'   => 'sometimes|integer|min:0',
        ]);

        $date = $data['date'];
        unset($data['date']);

        $metric = ContentMetric::updateOrCreate(
            ['date' => $date],
            $data
        );

        return response()->json($metric);
    }
}
