<?php

namespace App\Services\Content;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Content Orchestrator — reads config from DB and manages auto-generation.
 *
 * Config is stored in content_orchestrator_config table:
 * - daily_target: how many articles per day
 * - type_distribution: % per content type
 * - auto_pilot: whether to auto-generate
 * - priority_countries: ordered list of priority countries
 */
class ContentOrchestratorService
{
    private const TYPE_LABELS = [
        'qa' => 'Q/R',
        'news' => 'News RSS',
        'article' => 'Articles',
        'guide' => 'Fiches Pays',
        'guide_city' => 'Fiches Villes',
        'comparative' => 'Comparatifs',
        'outreach' => 'Recrutement/Partenariat',
        'testimonial' => 'Temoignages',
    ];

    public function getConfig(): array
    {
        $row = DB::table('content_orchestrator_config')->first();

        if (!$row) {
            return $this->defaultConfig();
        }

        return [
            'id' => $row->id,
            'daily_target' => $row->daily_target,
            'auto_pilot' => (bool) $row->auto_pilot,
            'type_distribution' => json_decode($row->type_distribution, true) ?? [],
            'priority_countries' => json_decode($row->priority_countries, true) ?? [],
            'status' => $row->status,
            'last_run_at' => $row->last_run_at,
            'today_generated' => $row->today_generated,
            'today_cost_cents' => $row->today_cost_cents,
            'type_labels' => self::TYPE_LABELS,
        ];
    }

    public function updateConfig(array $data): array
    {
        $row = DB::table('content_orchestrator_config')->first();

        $update = [];
        if (isset($data['daily_target'])) $update['daily_target'] = max(1, min(1000, (int) $data['daily_target']));
        if (isset($data['auto_pilot'])) $update['auto_pilot'] = (bool) $data['auto_pilot'];
        if (isset($data['type_distribution'])) $update['type_distribution'] = json_encode($data['type_distribution']);
        if (isset($data['priority_countries'])) $update['priority_countries'] = json_encode($data['priority_countries']);
        if (isset($data['status'])) $update['status'] = in_array($data['status'], ['running', 'paused', 'stopped']) ? $data['status'] : 'paused';

        $update['updated_at'] = now();

        if ($row) {
            DB::table('content_orchestrator_config')->where('id', $row->id)->update($update);
        } else {
            $update['created_at'] = now();
            DB::table('content_orchestrator_config')->insert($update);
        }

        return $this->getConfig();
    }

    /**
     * Calculate how many articles of each type to generate today.
     */
    public function getDailyPlan(): array
    {
        $config = $this->getConfig();
        $target = $config['daily_target'];
        $distribution = $config['type_distribution'];
        $remaining = $target - $config['today_generated'];

        if ($remaining <= 0) {
            return ['target' => $target, 'generated' => $config['today_generated'], 'remaining' => 0, 'plan' => []];
        }

        $plan = [];
        foreach ($distribution as $type => $pct) {
            $count = max(0, (int) round($remaining * $pct / 100));
            if ($count > 0) {
                $plan[] = [
                    'type' => $type,
                    'label' => self::TYPE_LABELS[$type] ?? $type,
                    'count' => $count,
                    'pct' => $pct,
                ];
            }
        }

        return [
            'target' => $target,
            'generated' => $config['today_generated'],
            'remaining' => $remaining,
            'plan' => $plan,
            'auto_pilot' => $config['auto_pilot'],
            'status' => $config['status'],
        ];
    }

    /**
     * Record a generation (called by GenerationSchedulerService).
     */
    public function recordGeneration(int $costCents = 0): void
    {
        DB::table('content_orchestrator_config')
            ->limit(1)
            ->update([
                'today_generated' => DB::raw('today_generated + 1'),
                'today_cost_cents' => DB::raw("today_cost_cents + {$costCents}"),
                'last_run_at' => now(),
            ]);
    }

    /**
     * Reset daily counters (called by daily cron at midnight).
     */
    public function resetDaily(): void
    {
        DB::table('content_orchestrator_config')
            ->limit(1)
            ->update([
                'today_generated' => 0,
                'today_cost_cents' => 0,
            ]);
    }

    /**
     * Check if we can generate more today.
     */
    public function canGenerate(): bool
    {
        $config = $this->getConfig();
        return $config['status'] === 'running'
            && $config['auto_pilot']
            && $config['today_generated'] < $config['daily_target'];
    }

    private function defaultConfig(): array
    {
        return [
            'id' => null,
            'daily_target' => 20,
            'auto_pilot' => false,
            'type_distribution' => ['qa' => 25, 'news' => 15, 'article' => 20, 'guide' => 10, 'guide_city' => 10, 'comparative' => 10, 'outreach' => 5, 'testimonial' => 5],
            'priority_countries' => ['FR','US','GB','ES','DE','TH','PT'],
            'status' => 'paused',
            'last_run_at' => null,
            'today_generated' => 0,
            'today_cost_cents' => 0,
            'type_labels' => self::TYPE_LABELS,
        ];
    }
}
