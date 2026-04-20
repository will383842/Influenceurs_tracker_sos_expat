<?php

namespace App\Http\Controllers;

use App\Models\ContentBusiness;
use App\Models\ContentContact;
use App\Models\ContentSource;
use App\Models\Influenceur;
use App\Models\Lawyer;
use App\Models\PressContact;
use App\Models\ScraperRotationState;
use App\Models\ScraperRun;
use App\Services\Scraping\AntiBanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

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

    /**
     * État de synchronisation Backlink Engine par table.
     * Utilisé pour afficher les 5 cards "X / Y (%)" dans AdminScraper.tsx.
     */
    public function syncState(): JsonResponse
    {
        try {
            return response()->json([
                'tables' => [
                    [
                        'key'    => 'influenceurs',
                        'label'  => '🎬 Influenceurs',
                        'total'  => Influenceur::whereNotNull('email')->count(),
                        'synced' => Influenceur::whereNotNull('backlink_synced_at')->count(),
                    ],
                    [
                        'key'    => 'press',
                        'label'  => '📰 Journalistes',
                        'total'  => PressContact::whereNotNull('email')->count(),
                        'synced' => PressContact::whereNotNull('backlink_synced_at')->count(),
                    ],
                    [
                        'key'    => 'lawyers',
                        'label'  => '⚖️ Avocats',
                        'total'  => Lawyer::whereNotNull('email')->count(),
                        'synced' => Lawyer::whereNotNull('backlink_synced_at')->count(),
                    ],
                    [
                        'key'    => 'businesses',
                        'label'  => '🏢 Entreprises',
                        'total'  => ContentBusiness::whereNotNull('contact_email')->count(),
                        'synced' => ContentBusiness::whereNotNull('backlink_synced_at')->count(),
                    ],
                    [
                        'key'    => 'web-contacts',
                        'label'  => '🌐 Contacts web',
                        'total'  => ContentContact::whereNotNull('email')->count(),
                        'synced' => ContentContact::whereNotNull('backlink_synced_at')->count(),
                    ],
                ],
                'generated_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['tables' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Lance une commande `backlink:resync --only=<table>` en queue.
     * Réponse immédiate, le travail tourne en arrière-plan (queue `scraper`).
     */
    public function resync(string $table): JsonResponse
    {
        $allowed = ['influenceurs', 'press', 'lawyers', 'businesses', 'web-contacts'];
        if (!in_array($table, $allowed, true)) {
            return response()->json(['error' => 'table invalide'], 422);
        }

        try {
            Artisan::queue('backlink:resync', ['--only' => $table]);
            return response()->json([
                'queued'  => true,
                'table'   => $table,
                'message' => "Resync de {$table} dispatché en queue. Résultat visible via logs et /api/scrapers/sync-state.",
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lance un scraper immédiatement via la queue.
     * Tous les runs longs partent en async → retour immédiat 202.
     */
    public function runNow(string $scraper): JsonResponse
    {
        $dispatched = false;
        $message = '';

        try {
            switch ($scraper) {
                case 'lawyers':
                    Artisan::queue('lawyers:scrape', ['source' => 'all']);
                    $dispatched = true;
                    $message = 'Lawyers scrape (toutes sources) dispatché.';
                    break;

                case 'press':
                    Artisan::queue('press:scrape-journalists', []);
                    $dispatched = true;
                    $message = 'Press scrape-journalists dispatché.';
                    break;

                case 'instagram':
                    Artisan::queue('instagram:scrape-francophones', ['--rotation' => true]);
                    $dispatched = true;
                    $message = 'Instagram scrape (1 pays via rotation) dispatché.';
                    break;

                case 'youtube':
                    Artisan::queue('youtube:scrape-francophones', ['--rotation' => true]);
                    $dispatched = true;
                    $message = 'YouTube scrape (1 pays via rotation) dispatché.';
                    break;

                case 'businesses':
                    $source = ContentSource::where('slug', 'like', '%expat%')->first();
                    if (!$source) {
                        return response()->json(['error' => 'ContentSource expat introuvable'], 404);
                    }
                    \App\Jobs\ScrapeBusinessDirectoryJob::dispatch($source->id);
                    $dispatched = true;
                    $message = "ScrapeBusinessDirectoryJob dispatché (source #{$source->id}).";
                    break;

                case 'femmexpat':
                    $source = ContentSource::where('slug', 'femmexpat')->first();
                    if (!$source) {
                        return response()->json(['error' => 'ContentSource femmexpat introuvable'], 404);
                    }
                    \App\Jobs\ScrapeFemmexpatJob::dispatch($source->id);
                    $dispatched = true;
                    $message = "ScrapeFemmexpatJob dispatché (source #{$source->id}).";
                    break;

                case 'francaisaletranger':
                    $source = ContentSource::where('slug', 'francais-a-l-etranger')->first();
                    if (!$source) {
                        return response()->json(['error' => 'ContentSource francais-a-l-etranger introuvable'], 404);
                    }
                    \App\Jobs\ScrapeFrancaisEtrangerJob::dispatch($source->id);
                    $dispatched = true;
                    $message = "ScrapeFrancaisEtrangerJob dispatché (source #{$source->id}).";
                    break;

                case 'discover-press':
                    \App\Jobs\DiscoverPressPublicationsJob::dispatch();
                    $dispatched = true;
                    $message = 'DiscoverPressPublicationsJob dispatché.';
                    break;

                case 'daily-report':
                    Artisan::queue('scrapers:daily-report', []);
                    $dispatched = true;
                    $message = 'Rapport Telegram dispatché.';
                    break;

                // Option D (2026-04-22) : 4 nouveaux scrapers
                case 'bloggers-rss':
                    \App\Jobs\ScrapeBloggerRssFeedsJob::dispatch();
                    $dispatched = true;
                    $message = 'Bloggers RSS dispatché (tous feeds actifs dus).';
                    break;

                case 'bloggers-ai':
                    \App\Jobs\DispatchAiResearchByTypeJob::dispatch('blog');
                    $dispatched = true;
                    $message = 'Blogueurs IA dispatché (1 pays via rotation Perplexity).';
                    break;

                case 'podcasters-ai':
                    \App\Jobs\DispatchAiResearchByTypeJob::dispatch('podcast_radio');
                    $dispatched = true;
                    $message = 'Podcasters IA dispatché (1 pays via rotation Perplexity).';
                    break;

                case 'influencers-ai':
                    \App\Jobs\DispatchAiResearchByTypeJob::dispatch('influenceur');
                    $dispatched = true;
                    $message = 'Influenceurs IA dispatché (1 pays via rotation Perplexity).';
                    break;

                default:
                    return response()->json(['error' => "scraper inconnu: {$scraper}"], 422);
            }

            return response()->json([
                'dispatched' => $dispatched,
                'scraper'    => $scraper,
                'message'    => $message,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
