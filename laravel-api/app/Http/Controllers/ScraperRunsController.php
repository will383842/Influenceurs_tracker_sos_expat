<?php

namespace App\Http\Controllers;

use App\Models\ScraperRotationState;
use App\Models\ScraperRun;
use App\Services\Scraping\AntiBanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScraperRunsController extends Controller
{
    public function runs(Request $request): JsonResponse
    {
        try {
            $query = ScraperRun::query()->orderByDesc('started_at');

            if ($scraper = $request->query('scraper')) {
                $query->where('scraper_name', $scraper);
            }
            if ($status = $request->query('status')) {
                $query->where('status', $status);
            }
            if ($country = $request->query('country')) {
                $query->where('country', $country);
            }

            $limit = min((int) $request->query('limit', 50), 200);

            return response()->json([
                'runs' => $query->limit($limit)->get(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['runs' => [], 'error' => 'scraper_runs table unavailable']);
        }
    }

    public function status(AntiBanService $antiBan): JsonResponse
    {
        try {
            // Dernier run par scraper
            $latest = ScraperRun::query()
                ->selectRaw('MAX(id) AS id, scraper_name')
                ->groupBy('scraper_name')
                ->pluck('id');

            $runs = ScraperRun::whereIn('id', $latest)
                ->orderBy('scraper_name')
                ->get();

            // Stats 24h — COUNT/SUM reviennent en string depuis MySQL, on cast en int
            $since = now()->subDay();
            $stats24h = ScraperRun::where('started_at', '>=', $since)
                ->selectRaw('status, COUNT(*) AS count, COALESCE(SUM(contacts_new), 0) AS contacts_new')
                ->groupBy('status')
                ->get()
                ->map(fn ($row) => [
                    'status'        => $row->status,
                    'count'         => (int) $row->count,
                    'contacts_new'  => (int) $row->contacts_new,
                ]);

            // État rotation
            $rotation = ScraperRotationState::orderBy('scraper_name')->get();

            return response()->json([
                'latest_runs'      => $runs,
                'stats_24h'        => $stats24h,
                'rotation_state'   => $rotation,
                'circuit_breakers' => $antiBan->circuitBreakerDomains(),
                'generated_at'     => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            // Migration pas encore appliquée (table missing) ou autre erreur DB → renvoyer un état vide, pas 500
            return response()->json([
                'latest_runs'      => [],
                'stats_24h'        => [],
                'rotation_state'   => [],
                'circuit_breakers' => $antiBan->circuitBreakerDomains(),
                'generated_at'     => now()->toIso8601String(),
                'error'            => 'scraper_runs table unavailable',
            ]);
        }
    }
}
