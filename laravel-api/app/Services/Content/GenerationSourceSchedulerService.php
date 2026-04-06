<?php

namespace App\Services\Content;

use App\Jobs\GenerateFromSourceJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Distributes a daily article target across 14 sources by percentage weight.
 *
 * Config stored in generation_source_categories.config_json:
 *   weight_percent  (int 0-100)  — this source's share of the daily total
 *   daily_quota     (int)        — overrides weight if set manually (Command Center manual mode)
 *   is_paused       (bool)       — skip this source entirely
 *
 * Global config stored in admin_config/generation_sources:
 *   total_daily     (int)        — total articles to generate per day (default: 20)
 *   schedule_mode   (string)     — 'percentage' | 'manual'
 */
class GenerationSourceSchedulerService
{
    private const SKIP_SOURCES    = ['annuaires'];
    private const CONFIG_CACHE_KEY = 'gen-source-scheduler-config';
    private const CONFIG_TTL       = 300; // 5 min

    /** Default weight distribution — must sum to 100 */
    private const DEFAULT_WEIGHTS = [
        'fiche-pays'       => 20,
        'fiche-villes'     => 8,
        'qa'               => 15,
        'besoins-reels'    => 8,
        'fiches-pratiques' => 10,
        'comparatifs'      => 4,
        'temoignages'      => 5,
        'chatters'         => 5,
        'bloggeurs'        => 5,
        'admin-groups'     => 5,
        'affiliation'      => 5,
        'annuaires'        => 0,
    ];

    // ─────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────

    /**
     * Run the daily distribution.
     * Called by a scheduled command (cron) or via Command Center "Tout lancer".
     *
     * @param int|null $totalOverride  Override the stored total_daily config
     */
    public function runDaily(?int $totalOverride = null): array
    {
        $globalConfig  = $this->getGlobalConfig();
        $total         = $totalOverride ?? (int) ($globalConfig['total_daily'] ?? 20);
        $scheduleMode  = $globalConfig['schedule_mode'] ?? 'percentage';

        Log::info("GenerationSourceScheduler: runDaily total={$total} mode={$scheduleMode}");

        $sources    = $this->getActiveSources();
        $dispatched = [];
        $skipped    = [];

        if ($scheduleMode === 'manual') {
            // Manual mode: each source uses its own daily_quota from config
            foreach ($sources as $src) {
                $quota = (int) ($src['config']['daily_quota'] ?? 0);
                if ($quota > 0) {
                    GenerateFromSourceJob::dispatch($src['slug'], $quota)->onQueue('content');
                    $dispatched[$src['slug']] = $quota;
                } else {
                    $skipped[] = $src['slug'];
                }
            }
        } else {
            // Percentage mode: distribute total proportionally by weight_percent
            $quotas = $this->distributeByWeight($sources, $total);
            foreach ($quotas as $slug => $quota) {
                if ($quota > 0) {
                    GenerateFromSourceJob::dispatch($slug, $quota)->onQueue('content');
                    $dispatched[$slug] = $quota;
                } else {
                    $skipped[] = $slug;
                }
            }
        }

        $totalDispatched = array_sum($dispatched);
        Log::info("GenerationSourceScheduler: dispatched {$totalDispatched} articles across " . count($dispatched) . " sources");

        Cache::forget('gen-source-command-center');

        return [
            'total_target'    => $total,
            'total_dispatched'=> $totalDispatched,
            'dispatched'      => $dispatched,
            'skipped'         => $skipped,
            'mode'            => $scheduleMode,
        ];
    }

    /**
     * Preview the distribution without dispatching jobs.
     * Used by the Command Center UI to show "X articles today per source".
     */
    public function previewDistribution(?int $totalOverride = null): array
    {
        $globalConfig = $this->getGlobalConfig();
        $total        = $totalOverride ?? (int) ($globalConfig['total_daily'] ?? 20);
        $sources      = $this->getActiveSources();
        $quotas       = $this->distributeByWeight($sources, $total);

        return [
            'total'   => $total,
            'sources' => $quotas,
            'mode'    => $globalConfig['schedule_mode'] ?? 'percentage',
        ];
    }

    /**
     * Update the global daily total + schedule mode.
     */
    public function updateGlobalConfig(int $totalDaily, string $mode = 'percentage'): void
    {
        DB::table('admin_config')
            ->updateOrInsert(
                ['key' => 'generation_sources'],
                [
                    'value'      => json_encode([
                        'total_daily'   => $totalDaily,
                        'schedule_mode' => $mode,
                    ]),
                    'updated_at' => now(),
                ]
            );

        Cache::forget(self::CONFIG_CACHE_KEY);
        Cache::forget('gen-source-command-center');
    }

    /**
     * Update weight_percent for a source.
     */
    public function updateWeight(string $slug, int $weightPercent): void
    {
        $category = DB::table('generation_source_categories')->where('slug', $slug)->first();
        if (!$category) return;

        $config = json_decode($category->config_json ?? '{}', true);
        $config['weight_percent'] = max(0, min(100, $weightPercent));

        DB::table('generation_source_categories')
            ->where('slug', $slug)
            ->update(['config_json' => json_encode($config), 'updated_at' => now()]);

        Cache::forget(self::CONFIG_CACHE_KEY);
        Cache::forget('gen-source-command-center');
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    private function distributeByWeight(array $sources, int $total): array
    {
        $totalWeight = array_sum(array_column($sources, 'weight'));

        if ($totalWeight === 0 || $total === 0) {
            return array_fill_keys(array_column($sources, 'slug'), 0);
        }

        $quotas    = [];
        $remainder = $total;
        $floats    = [];

        // First pass: calculate exact floats
        foreach ($sources as $src) {
            $exact            = ($src['weight'] / $totalWeight) * $total;
            $floats[$src['slug']] = $exact;
            $quotas[$src['slug']] = (int) floor($exact);
            $remainder           -= $quotas[$src['slug']];
        }

        // Second pass: distribute remainder to sources with largest fractional parts
        $fractions = [];
        foreach ($floats as $slug => $exact) {
            $fractions[$slug] = $exact - floor($exact);
        }
        arsort($fractions);

        foreach (array_keys($fractions) as $slug) {
            if ($remainder <= 0) break;
            $quotas[$slug]++;
            $remainder--;
        }

        return $quotas;
    }

    private function getActiveSources(): array
    {
        $rows = DB::table('generation_source_categories')
            ->select('slug', 'config_json')
            ->get();

        $sources = [];
        foreach ($rows as $row) {
            if (in_array($row->slug, self::SKIP_SOURCES, true)) continue;

            $config   = json_decode($row->config_json ?? '{}', true);
            $isPaused = $config['is_paused'] ?? false;
            $weight   = (int) ($config['weight_percent'] ?? self::DEFAULT_WEIGHTS[$row->slug] ?? 0);

            if ($isPaused || $weight === 0) continue;

            $sources[] = [
                'slug'   => $row->slug,
                'weight' => $weight,
                'config' => $config,
            ];
        }

        return $sources;
    }

    private function getGlobalConfig(): array
    {
        return Cache::remember(self::CONFIG_CACHE_KEY, self::CONFIG_TTL, function () {
            $row = DB::table('admin_config')->where('key', 'generation_sources')->first();
            return $row ? json_decode($row->value, true) : [];
        });
    }
}
