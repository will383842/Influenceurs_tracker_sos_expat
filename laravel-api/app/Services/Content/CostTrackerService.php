<?php

namespace App\Services\Content;

use App\Models\ApiCost;
use Illuminate\Support\Facades\Log;

/**
 * AI cost tracking and budget management.
 * Tracks spending across all AI services (OpenAI, Perplexity, etc.).
 */
class CostTrackerService
{
    /**
     * Get total cost for today in cents.
     */
    public function getTodayCost(): int
    {
        try {
            return (int) ApiCost::whereDate('created_at', now()->toDateString())->sum('cost_cents');
        } catch (\Throwable $e) {
            Log::error('Cost tracker: getTodayCost failed', ['message' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get total cost for the current month in cents.
     */
    public function getMonthlyCost(): int
    {
        try {
            return (int) ApiCost::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->sum('cost_cents');
        } catch (\Throwable $e) {
            Log::error('Cost tracker: getMonthlyCost failed', ['message' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Get daily budget in cents.
     */
    public function getDailyBudget(): int
    {
        return (int) config('services.ai.daily_budget', 5000);
    }

    /**
     * Get monthly budget in cents.
     */
    public function getMonthlyBudget(): int
    {
        return (int) config('services.ai.monthly_budget', 100000);
    }

    /**
     * Check if today's spending exceeds daily budget.
     */
    public function isOverDailyBudget(): bool
    {
        return $this->getTodayCost() >= $this->getDailyBudget();
    }

    /**
     * Check if this month's spending exceeds monthly budget.
     */
    public function isOverMonthlyBudget(): bool
    {
        return $this->getMonthlyCost() >= $this->getMonthlyBudget();
    }

    /**
     * Check if API calls should be blocked due to budget.
     */
    public function shouldBlock(): bool
    {
        $blockOnExceeded = (bool) config('services.ai.block_on_exceeded', false);

        if (!$blockOnExceeded) {
            return false;
        }

        return $this->isOverDailyBudget() || $this->isOverMonthlyBudget();
    }

    /**
     * Get cost breakdown by service, model, and operation.
     *
     * @return array<array{service: string, model: string, operation: string, count: int, total_tokens: int, total_cost_cents: int}>
     */
    public function getCostBreakdown(string $period = 'month'): array
    {
        try {
            $query = ApiCost::query();

            if ($period === 'today') {
                $query->whereDate('created_at', now()->toDateString());
            } elseif ($period === 'week') {
                $query->where('created_at', '>=', now()->startOfWeek());
            } elseif ($period === 'month') {
                $query->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month);
            } elseif ($period === 'year') {
                $query->whereYear('created_at', now()->year);
            }

            $results = $query
                ->selectRaw('service, model, operation, COUNT(*) as count, SUM(input_tokens + output_tokens) as total_tokens, SUM(cost_cents) as total_cost_cents')
                ->groupBy('service', 'model', 'operation')
                ->orderByDesc('total_cost_cents')
                ->get();

            return $results->map(fn ($row) => [
                'service' => $row->service,
                'model' => $row->model,
                'operation' => $row->operation,
                'count' => (int) $row->count,
                'total_tokens' => (int) $row->total_tokens,
                'total_cost_cents' => (int) $row->total_cost_cents,
            ])->toArray();
        } catch (\Throwable $e) {
            Log::error('Cost tracker: getCostBreakdown failed', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get daily cost trends for the last N days.
     *
     * @return array<array{date: string, total_cost_cents: int, by_service: array}>
     */
    public function getCostTrends(int $days = 30): array
    {
        try {
            $startDate = now()->subDays($days)->startOfDay();

            // Get daily totals
            $dailyTotals = ApiCost::where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, service, SUM(cost_cents) as total_cost_cents')
                ->groupBy('date', 'service')
                ->orderBy('date')
                ->get();

            // Group by date
            $trends = [];
            $grouped = $dailyTotals->groupBy('date');

            foreach ($grouped as $date => $rows) {
                $byService = [];
                $dayTotal = 0;

                foreach ($rows as $row) {
                    $cost = (int) $row->total_cost_cents;
                    $byService[$row->service] = $cost;
                    $dayTotal += $cost;
                }

                $trends[] = [
                    'date' => $date,
                    'total_cost_cents' => $dayTotal,
                    'by_service' => $byService,
                ];
            }

            // Fill in missing days with zero values
            $allDates = [];
            for ($i = $days; $i >= 0; $i--) {
                $allDates[] = now()->subDays($i)->toDateString();
            }

            $trendsByDate = collect($trends)->keyBy('date');
            $filledTrends = [];

            foreach ($allDates as $date) {
                if ($trendsByDate->has($date)) {
                    $filledTrends[] = $trendsByDate->get($date);
                } else {
                    $filledTrends[] = [
                        'date' => $date,
                        'total_cost_cents' => 0,
                        'by_service' => [],
                    ];
                }
            }

            return $filledTrends;
        } catch (\Throwable $e) {
            Log::error('Cost tracker: getCostTrends failed', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Track an API cost record.
     */
    public function track(
        string $service,
        string $model,
        string $operation,
        int $inputTokens,
        int $outputTokens,
        int $costCents,
        ?string $costableType = null,
        ?int $costableId = null,
        ?int $createdBy = null
    ): ApiCost {
        try {
            $cost = ApiCost::create([
                'service' => $service,
                'model' => $model,
                'operation' => $operation,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_cents' => $costCents,
                'costable_type' => $costableType,
                'costable_id' => $costableId,
                'created_by' => $createdBy,
            ]);

            Log::debug('API cost tracked', [
                'service' => $service,
                'model' => $model,
                'cost_cents' => $costCents,
            ]);

            return $cost;
        } catch (\Throwable $e) {
            Log::error('Cost tracking failed', [
                'service' => $service,
                'model' => $model,
                'message' => $e->getMessage(),
            ]);

            // Return an unsaved model so callers don't break
            return new ApiCost([
                'service' => $service,
                'model' => $model,
                'operation' => $operation,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_cents' => $costCents,
            ]);
        }
    }
}
