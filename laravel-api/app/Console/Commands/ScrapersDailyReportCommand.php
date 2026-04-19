<?php

namespace App\Console\Commands;

use App\Models\ScraperRun;
use App\Services\Scraping\AntiBanService;
use App\Services\Social\TelegramAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScrapersDailyReportCommand extends Command
{
    protected $signature = 'scrapers:daily-report {--silent : N\'envoie pas sur Telegram, affiche seulement}';

    protected $description = 'Agrège les runs scrapers des dernières 24h et envoie un rapport Telegram';

    public function handle(AntiBanService $antiBan): int
    {
        $since = now()->subDay();

        $runs = ScraperRun::where('started_at', '>=', $since)->get();

        if ($runs->isEmpty()) {
            $this->info('Aucun run scraper dans les dernières 24h.');
            return 0;
        }

        $byStatus = $runs->groupBy('status');
        $ok           = $byStatus->get(ScraperRun::STATUS_OK, collect());
        $skipped      = $byStatus->get(ScraperRun::STATUS_SKIPPED_NO_IA, collect());
        $rateLimited  = $byStatus->get(ScraperRun::STATUS_RATE_LIMITED, collect());
        $circuitBroken= $byStatus->get(ScraperRun::STATUS_CIRCUIT_BROKEN, collect());
        $errors       = $byStatus->get(ScraperRun::STATUS_ERROR, collect());

        $contactsNew   = (int) $runs->sum('contacts_new');
        $contactsFound = (int) $runs->sum('contacts_found');

        $lines = [];
        $lines[] = '📊 <b>Rapport scrapers 24h</b>';
        $lines[] = "✅ {$ok->count()} OK ({$contactsNew} nouveaux contacts, {$contactsFound} trouvés)";

        if ($skipped->count() > 0) {
            $scrapers = $skipped->pluck('scraper_name')->unique()->implode(', ');
            $lines[] = "⏸️ {$skipped->count()} skipped (Perplexity) — {$scrapers}";
        }
        if ($rateLimited->count() > 0) {
            $lines[] = "🐢 {$rateLimited->count()} rate-limited";
        }
        if ($circuitBroken->count() > 0) {
            $lines[] = "🛑 {$circuitBroken->count()} circuit-broken";
        }
        if ($errors->count() > 0) {
            $lines[] = "❌ {$errors->count()} errors";
        }

        $circuitDomains = $antiBan->circuitBreakerDomains();
        if (!empty($circuitDomains)) {
            $names = array_slice(array_keys($circuitDomains), 0, 5);
            $lines[] = '⚠️ Domaines en pause : ' . implode(', ', $names);
        }

        $topScrapers = $runs->groupBy('scraper_name')
            ->map(fn ($c) => (int) $c->sum('contacts_new'))
            ->sortDesc()
            ->take(5);

        if ($topScrapers->filter()->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '🏆 Top scrapers (nouveaux contacts) :';
            foreach ($topScrapers as $name => $count) {
                if ($count > 0) {
                    $lines[] = "  • {$name} : {$count}";
                }
            }
        }

        $message = implode("\n", $lines);
        $this->line(strip_tags($message));

        if (!$this->option('silent')) {
            try {
                app(TelegramAlertService::class)->sendMessage($message);
                $this->info('Rapport envoyé sur Telegram.');
            } catch (\Throwable $e) {
                $this->error('Échec envoi Telegram : ' . $e->getMessage());
            }
        }

        return 0;
    }
}
