<?php

namespace App\Http\Controllers;

use App\Models\ApiCost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CostController extends Controller
{
    /**
     * Budget thresholds (in cents). Override via config/content-engine.php if needed.
     */
    private function getDailyBudget(): int
    {
        return (int) config('services.ai.daily_budget', 5000); // $50
    }

    private function getMonthlyBudget(): int
    {
        return (int) config('services.ai.monthly_budget', 100000); // $1000
    }

    public function overview(): JsonResponse
    {
        $today = (int) ApiCost::where('created_at', '>=', now()->startOfDay())->sum('cost_cents');
        $thisWeek = (int) ApiCost::where('created_at', '>=', now()->startOfWeek())->sum('cost_cents');
        $thisMonth = (int) ApiCost::where('created_at', '>=', now()->startOfMonth())->sum('cost_cents');

        $dailyBudget = $this->getDailyBudget();
        $monthlyBudget = $this->getMonthlyBudget();

        return response()->json([
            'today_cents'        => $today,
            'this_week_cents'    => $thisWeek,
            'this_month_cents'   => $thisMonth,
            'daily_budget_cents' => $dailyBudget,
            'monthly_budget_cents' => $monthlyBudget,
            'is_over_daily'      => $today > $dailyBudget,
            'is_over_monthly'    => $thisMonth > $monthlyBudget,
            'daily_usage_pct'    => $dailyBudget > 0 ? round(($today / $dailyBudget) * 100, 1) : 0,
            'monthly_usage_pct'  => $monthlyBudget > 0 ? round(($thisMonth / $monthlyBudget) * 100, 1) : 0,
        ]);
    }

    public function breakdown(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|string|in:day,week,month',
        ]);

        $period = $request->input('period', 'month');
        $query = ApiCost::query();

        if ($period === 'day') {
            $query->whereDate('created_at', today());
        } elseif ($period === 'week') {
            $query->where('created_at', '>=', now()->startOfWeek());
        } else {
            $query->where('created_at', '>=', now()->startOfMonth());
        }

        // Frontend expects CostBreakdownEntry[]: { service, model, operation, count, total_tokens, total_cost_cents }
        $breakdown = $query
            ->selectRaw('service, model, operation, COUNT(*) as count, SUM(input_tokens + output_tokens) as total_tokens, SUM(cost_cents) as total_cost_cents')
            ->groupBy('service', 'model', 'operation')
            ->orderByDesc('total_cost_cents')
            ->get();

        return response()->json($breakdown);
    }

    public function trends(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'nullable|integer|min:7|max:90',
        ]);

        $days = (int) $request->input('days', 30);

        // Frontend expects CostTrendEntry[]: { date, total_cost_cents, by_service: Record<string, number> }
        $dailyCosts = ApiCost::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, service, SUM(cost_cents) as cost')
            ->groupBy('date', 'service')
            ->orderBy('date')
            ->get();

        $grouped = $dailyCosts->groupBy('date');
        $trends = collect();

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $dayData = $grouped->get($date, collect());
            $byService = [];
            $total = 0;
            foreach ($dayData as $row) {
                $byService[$row->service] = (int) $row->cost;
                $total += (int) $row->cost;
            }
            $trends->push([
                'date'             => $date,
                'total_cost_cents' => $total,
                'by_service'       => (object) $byService,
            ]);
        }

        return response()->json($trends);
    }
}
