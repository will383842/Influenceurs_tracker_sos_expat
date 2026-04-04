<?php

namespace App\Console\Commands;

use App\Jobs\GenerateQrBlogJob;
use App\Models\ContentQuestion;
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

        $limit    = (int) ($config['daily_limit'] ?? 20);
        $country  = $config['country']  ?: null;
        $category = $config['category'] ?: null;

        // Vérifier qu'une génération n'est pas déjà en cours
        $current = Cache::get(self::PROGRESS_KEY);
        if ($current && ($current['status'] ?? '') === 'running') {
            $this->warn('Une génération est déjà en cours — skip.');
            return 0;
        }

        // Récupérer les IDs
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

        // Initialiser la progression
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

        // Mettre à jour last_run_at dans settings
        $config['last_run_at'] = now()->toIso8601String();
        DB::table('settings')->updateOrInsert(
            ['key' => self::SCHEDULE_KEY],
            ['value' => json_encode($config), 'updated_at' => now()]
        );

        $this->info("✓ {$limit} Q/R programmées pour génération (job dispatché).");
        Log::info("QrDailyGenerate: dispatched {$limit} questions.");

        return 0;
    }
}
