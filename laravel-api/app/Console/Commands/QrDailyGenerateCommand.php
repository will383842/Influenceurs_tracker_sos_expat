<?php

namespace App\Console\Commands;

use App\Jobs\GenerateQrBlogJob;
use App\Models\ContentQuestion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QrDailyGenerateCommand extends Command
{
    protected $signature   = 'qr:daily-generate';
    protected $description = 'Génération Q/R automatique quotidienne selon la programmation configurée';

    private const PROGRESS_KEY = 'qr_blog_generation_progress';
    private const SCHEDULE_KEY = 'qr_schedule';

    public function handle(): int
    {
        $raw    = DB::table('settings')->where('key', self::SCHEDULE_KEY)->value('value');
        $config = $raw ? json_decode($raw, true) : null;

        if (! $config || ! ($config['active'] ?? false)) {
            $this->info('Programmation Q/R inactive — rien à faire.');
            return 0;
        }

        $limit         = (int) ($config['daily_limit'] ?? 20);
        $country       = $config['country'] ?: null;
        $durationType  = $config['duration_type'] ?? 'unlimited';
        $totalGenerated = (int) ($config['total_generated'] ?? 0);

        // ── Vérifier durée : max_days ──────────────────────────
        if ($durationType === 'days' && isset($config['max_days']) && isset($config['start_date'])) {
            $endDate = Carbon::parse($config['start_date'])->addDays((int) $config['max_days']);
            if (now()->isAfter($endDate)) {
                $config['active'] = false;
                $this->saveConfig($config);
                $this->info('Programmation désactivée automatiquement (durée de ' . $config['max_days'] . ' jours atteinte).');
                Log::info('QrDailyGenerate: auto-désactivé, durée max_days atteinte.');
                return 0;
            }
        }

        // ── Vérifier durée : total_goal ────────────────────────
        if ($durationType === 'total' && isset($config['total_goal'])) {
            $remaining = (int) $config['total_goal'] - $totalGenerated;
            if ($remaining <= 0) {
                $config['active'] = false;
                $this->saveConfig($config);
                $this->info('Programmation désactivée automatiquement (objectif de ' . $config['total_goal'] . ' Q/R atteint).');
                Log::info('QrDailyGenerate: auto-désactivé, total_goal atteint.');
                return 0;
            }
            // Limiter le batch du jour au restant
            $limit = min($limit, $remaining);
        }

        // ── Vérifier qu'une génération n'est pas déjà en cours ──
        $current = Cache::get(self::PROGRESS_KEY);
        if ($current && ($current['status'] ?? '') === 'running') {
            $this->warn('Une génération est déjà en cours — skip.');
            return 0;
        }

        // ── Récupérer les IDs ──────────────────────────────────
        $query = ContentQuestion::where('article_status', 'opportunity')->orderByDesc('views');
        if ($country) {
            $query->where(function ($q) use ($country) {
                $q->where('country_slug', $country)->orWhere('country', 'ilike', '%' . $country . '%');
            });
        }
        $ids = $query->limit($limit)->pluck('id')->toArray();

        if (empty($ids)) {
            $this->warn('Aucune question disponible pour la génération quotidienne.');
            return 0;
        }

        // ── Initialiser la progression ─────────────────────────
        Cache::put(self::PROGRESS_KEY, [
            'status'        => 'running',
            'total'         => count($ids),
            'completed'     => 0,
            'skipped'       => 0,
            'errors'        => 0,
            'current_title' => null,
            'started_at'    => now()->toIso8601String(),
            'finished_at'   => null,
            'log'           => [],
            'triggered_by'  => 'scheduler',
        ], now()->addHours(24));

        GenerateQrBlogJob::dispatch($ids)->onQueue('default');

        // ── Mettre à jour config ───────────────────────────────
        if (! isset($config['start_date'])) {
            $config['start_date'] = now()->toDateString();
        }
        $config['last_run_at'] = now()->toIso8601String();
        $this->saveConfig($config);

        $this->info("✓ {$limit} Q/R programmées pour génération (job dispatché).");
        Log::info("QrDailyGenerate: dispatched {$limit} questions.", [
            'duration_type'   => $durationType,
            'total_generated' => $totalGenerated,
            'total_goal'      => $config['total_goal'] ?? null,
        ]);

        return 0;
    }

    private function saveConfig(array $config): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => self::SCHEDULE_KEY],
            ['value' => json_encode($config), 'updated_at' => now()]
        );
    }
}
