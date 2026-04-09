<?php

namespace App\Http\Controllers;

use App\Jobs\DiscoverPressPublicationsJob;
use App\Jobs\ScrapePressPublicationJob;
use App\Jobs\ScrapePublicationAuthorsJob;
use App\Jobs\ScrapeLawyerDirectoryJob;
use App\Jobs\ScrapeBusinessDirectoryJob;
use App\Jobs\ScrapeDirectoryJob;
use App\Jobs\ScrapeGenericSiteJob;
use App\Jobs\ScrapeJournalistDirectoryJob;
use App\Models\PressPublication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScrapingDashboardController extends Controller
{
    // ─── GLOBAL STATUS ────────────────────────────────────────────────────────

    public function status(): JsonResponse
    {
        // Queue state
        $queuePending = DB::table('jobs')->count();
        $queueFailed  = DB::table('failed_jobs')->count();

        // Per-scraper stats
        $scrapers = [

            // ── Découverte de publications ────────────────────────────────
            [
                'id'          => 'discover_press',
                'label'       => 'Découvrir nouvelles publications',
                'icon'        => '🔍',
                'category'    => 'Presse',
                'description' => 'Découvre automatiquement de nouvelles publications presse (voyage, expat, lifestyle) et scrape leurs contacts. 100% gratuit.',
                'stats'       => $this->journalistStats(),
                'actions'     => [
                    ['id' => 'discover_press_voyage',       'label' => 'Découvrir — Voyage',       'color' => 'blue'],
                    ['id' => 'discover_press_expatriation', 'label' => 'Découvrir — Expatriation', 'color' => 'green'],
                    ['id' => 'discover_press_lifestyle',    'label' => 'Découvrir — Lifestyle',    'color' => 'pink'],
                    ['id' => 'discover_press_all',          'label' => 'Découvrir — Tout',         'color' => 'violet'],
                ],
            ],

            // ── Annuaires de Journalistes ─────────────────────────────────
            [
                'id'          => 'journalist_directories',
                'label'       => 'Annuaires de journalistes',
                'icon'        => '🗂️',
                'category'    => 'Presse',
                'description' => 'Scrape les annuaires (annuaire.journaliste.fr, presselib.com, AEJT, AJEF…) par mots-clés expat/voyage/international',
                'stats'       => $this->journalistDirectoryStats(),
                'actions'     => [
                    ['id' => 'scrape_journalist_directories', 'label' => 'Scraper tous les annuaires', 'color' => 'violet'],
                ],
            ],

            // ── Journalistes & Presse ──────────────────────────────────────
            [
                'id'          => 'journalists_team',
                'label'       => 'Journalistes — Pages équipe',
                'icon'        => '🗞️',
                'category'    => 'Presse',
                'description' => 'Scrape les pages "Rédaction / Équipe" des 135 publications',
                'stats'       => $this->journalistStats(),
                'actions'     => [
                    ['id' => 'scrape_journalists_team', 'label' => 'Scraper pages équipe', 'color' => 'violet'],
                ],
            ],
            [
                'id'          => 'journalists_bylines',
                'label'       => 'Journalistes — Bylines articles',
                'icon'        => '✍️',
                'category'    => 'Presse',
                'description' => 'Extrait les auteurs depuis les bylines d\'articles (73 publications configurées)',
                'stats'       => $this->journalistStats(),
                'actions'     => [
                    ['id' => 'scrape_journalists_bylines', 'label' => 'Scraper bylines', 'color' => 'green'],
                    ['id' => 'infer_emails', 'label' => 'Inférer emails', 'color' => 'amber'],
                ],
            ],

            // ── Avocats ───────────────────────────────────────────────────
            [
                'id'          => 'lawyers',
                'label'       => 'Avocats',
                'icon'        => '⚖️',
                'category'    => 'Professionnels',
                'description' => 'Annuaires de barreaux, avocats spécialisés immigration & expat',
                'stats'       => $this->lawyerStats(),
                'actions'     => [
                    ['id' => 'scrape_lawyers', 'label' => 'Scraper annuaires avocats', 'color' => 'yellow'],
                ],
            ],

            // ── CRM / Annuaires ───────────────────────────────────────────
            [
                'id'          => 'directories',
                'label'       => 'Annuaires (écoles, assoc, consulats…)',
                'icon'        => '📚',
                'category'    => 'CRM',
                'description' => 'Scrape les annuaires web pour alimenter le CRM (25 types de contacts)',
                'stats'       => $this->directoryStats(),
                'actions'     => [
                    ['id' => 'scrape_directories_pending', 'label' => 'Scraper annuaires en attente', 'color' => 'blue'],
                ],
            ],

            // ── Entreprises ───────────────────────────────────────────────
            [
                'id'          => 'businesses',
                'label'       => 'Entreprises (expat.com)',
                'icon'        => '🏢',
                'category'    => 'Entreprises',
                'description' => 'Entreprises et prestataires issus de l\'annuaire expat.com',
                'stats'       => $this->businessStats(),
                'actions'     => [
                    ['id' => 'scrape_businesses', 'label' => 'Scraper entreprises', 'color' => 'green'],
                ],
            ],

            // ── Sites web génériques ──────────────────────────────────────
            [
                'id'          => 'sites',
                'label'       => 'Sites web — Contacts',
                'icon'        => '🌐',
                'category'    => 'Web',
                'description' => 'Extraction de contacts depuis des sites web génériques',
                'stats'       => $this->siteContactStats(),
                'actions'     => [
                    ['id' => 'scrape_sites', 'label' => 'Scraper sites en attente', 'color' => 'cyan'],
                ],
            ],
        ];

        return response()->json([
            'queue'   => ['pending' => $queuePending, 'failed' => $queueFailed],
            'scrapers' => $scrapers,
        ]);
    }

    // ─── LAUNCH SCRAPER ───────────────────────────────────────────────────────

    public function launch(Request $request): JsonResponse
    {
        $action = $request->input('action');
        $queued = 0;

        switch ($action) {

            case 'discover_press_voyage':
            case 'discover_press_expatriation':
            case 'discover_press_lifestyle':
            case 'discover_press_all':
                $category = match ($action) {
                    'discover_press_voyage'       => 'voyage',
                    'discover_press_expatriation' => 'expatriation',
                    'discover_press_lifestyle'    => 'lifestyle',
                    default                       => 'all',
                };
                DiscoverPressPublicationsJob::dispatch($category, true);
                $queued = 1;
                break;

            case 'scrape_journalist_directories':
                $sources = DB::table('journalist_directory_sources')
                    ->where('status', '!=', 'running')
                    ->get();
                foreach ($sources as $i => $src) {
                    ScrapeJournalistDirectoryJob::dispatch($src->slug)->delay(now()->addSeconds($i * 30));
                }
                $queued = $sources->count();
                break;

            case 'scrape_journalists_team':
                $pubs = PressPublication::whereIn('status', ['pending', 'failed'])->get();
                foreach ($pubs as $i => $pub) {
                    ScrapePressPublicationJob::dispatch($pub->id)->delay(now()->addSeconds($i * 5));
                }
                $queued = $pubs->count();
                break;

            case 'scrape_journalists_bylines':
                $pubs = PressPublication::where(function ($q) {
                    $q->whereNotNull('authors_url')->orWhereNotNull('articles_url');
                })->get();
                foreach ($pubs as $i => $pub) {
                    ScrapePublicationAuthorsJob::dispatch($pub->id, true)->delay(now()->addSeconds($i * 10));
                }
                $queued = $pubs->count();
                break;

            case 'infer_emails':
                $pubs = PressPublication::whereNotNull('email_pattern')->whereNotNull('email_domain')->get();
                foreach ($pubs as $i => $pub) {
                    ScrapePublicationAuthorsJob::dispatch($pub->id, true, 0)->delay(now()->addSeconds($i * 3));
                }
                $queued = $pubs->count();
                break;

            case 'scrape_lawyers':
                $sources = DB::table('lawyer_directory_sources')
                    ->where('status', '!=', 'completed')
                    ->orWhereNull('status')
                    ->get();
                foreach ($sources as $i => $src) {
                    ScrapeLawyerDirectoryJob::dispatch($src->slug)->delay(now()->addSeconds($i * 8));
                }
                $queued = $sources->count();
                break;

            case 'scrape_directories_pending':
                $dirs = DB::table('directories')
                    ->whereIn('status', ['pending', 'failed'])
                    ->orWhereNull('status')
                    ->limit(50)
                    ->get();
                foreach ($dirs as $i => $dir) {
                    ScrapeDirectoryJob::dispatch($dir->id)->delay(now()->addSeconds($i * 6));
                }
                $queued = $dirs->count();
                break;

            case 'scrape_businesses':
                $sources = DB::table('content_sources')
                    ->where('type', 'business_directory')
                    ->orWhere('slug', 'like', '%expat%')
                    ->limit(10)
                    ->get();
                foreach ($sources as $i => $src) {
                    ScrapeBusinessDirectoryJob::dispatch($src->id)->delay(now()->addSeconds($i * 10));
                }
                $queued = $sources->count();
                break;

            case 'scrape_sites':
                $sites = DB::table('influenceurs')
                    ->whereNotNull('website_url')
                    ->where('scraper_status', 'pending')
                    ->orWhere('scraper_status', 'failed')
                    ->limit(30)
                    ->pluck('id');
                foreach ($sites as $i => $id) {
                    ScrapeGenericSiteJob::dispatch($id)->delay(now()->addSeconds($i * 4));
                }
                $queued = $sites->count();
                break;

            default:
                return response()->json(['error' => "Action inconnue : {$action}"], 422);
        }

        return response()->json([
            'message' => "{$queued} jobs envoyés en queue",
            'queued'  => $queued,
            'action'  => $action,
        ]);
    }

    // ─── PER-SCRAPER STATS HELPERS ────────────────────────────────────────────

    private function journalistDirectoryStats(): array
    {
        $sources = DB::selectOne("
            SELECT COUNT(*) as total,
                COUNT(*) FILTER (WHERE status = 'completed') as completed,
                COUNT(*) FILTER (WHERE status = 'pending' OR status IS NULL) as pending,
                COUNT(*) FILTER (WHERE status = 'running') as running,
                COUNT(*) FILTER (WHERE status = 'failed') as failed,
                COALESCE(SUM(contacts_found), 0) as contacts_found,
                MAX(last_scraped_at) as last_run
            FROM journalist_directory_sources
        ");
        $withEmail = DB::selectOne("
            SELECT COUNT(*) as total,
                COUNT(*) FILTER (WHERE email IS NOT NULL) as with_email
            FROM press_contacts WHERE scraped_from IN (
                SELECT slug FROM journalist_directory_sources
            )
        ");

        return [
            'sources_total'       => (int) ($sources->total ?? 0),
            'sources_completed'   => (int) ($sources->completed ?? 0),
            'sources_pending'     => (int) ($sources->pending ?? 0),
            'sources_running'     => (int) ($sources->running ?? 0),
            'sources_failed'      => (int) ($sources->failed ?? 0),
            'contacts_found'      => (int) ($sources->contacts_found ?? 0),
            'contacts_with_email' => (int) ($withEmail->with_email ?? 0),
            'last_run'            => $sources->last_run,
        ];
    }

    private function journalistStats(): array
    {
        $pubs = DB::selectOne("
            SELECT COUNT(*) as total,
                COUNT(*) FILTER (WHERE status = 'scraped') as scraped,
                COUNT(*) FILTER (WHERE status = 'pending') as pending,
                COUNT(*) FILTER (WHERE status = 'failed') as failed,
                SUM(contacts_count) as contacts,
                SUM(emails_inferred) as emails_inferred,
                COUNT(*) FILTER (WHERE authors_url IS NOT NULL OR articles_url IS NOT NULL) as bylines_configured,
                COUNT(*) FILTER (WHERE email_pattern IS NOT NULL) as email_pattern_configured,
                MAX(last_scraped_at) as last_run
            FROM press_publications
        ");
        $contacts = DB::selectOne("SELECT COUNT(*) as total, COUNT(*) FILTER (WHERE email IS NOT NULL) as with_email FROM press_contacts");

        return [
            'publications'           => (int) ($pubs->total ?? 0),
            'publications_scraped'   => (int) ($pubs->scraped ?? 0),
            'publications_pending'   => (int) ($pubs->pending ?? 0),
            'publications_failed'    => (int) ($pubs->failed ?? 0),
            'bylines_configured'     => (int) ($pubs->bylines_configured ?? 0),
            'email_pattern_configured' => (int) ($pubs->email_pattern_configured ?? 0),
            'contacts_found'         => (int) ($contacts->total ?? 0),
            'contacts_with_email'    => (int) ($contacts->with_email ?? 0),
            'emails_inferred'        => (int) ($pubs->emails_inferred ?? 0),
            'last_run'               => $pubs->last_run,
        ];
    }

    private function lawyerStats(): array
    {
        $data = DB::selectOne("
            SELECT COUNT(*) as total,
                COUNT(*) FILTER (WHERE email IS NOT NULL) as with_email,
                COUNT(*) FILTER (WHERE email_verified = true) as verified,
                COUNT(*) FILTER (WHERE detail_scraped = true) as detailed,
                MAX(scraped_at) as last_run
            FROM lawyers
        ");
        $sources = DB::selectOne("
            SELECT COUNT(*) as total,
                COUNT(*) FILTER (WHERE status = 'completed') as completed
            FROM lawyer_directory_sources
        ");

        return [
            'contacts_found'      => (int) ($data->total ?? 0),
            'contacts_with_email' => (int) ($data->with_email ?? 0),
            'contacts_verified'   => (int) ($data->verified ?? 0),
            'sources_total'       => (int) ($sources->total ?? 0),
            'sources_completed'   => (int) ($sources->completed ?? 0),
            'last_run'            => $data->last_run,
        ];
    }

    private function directoryStats(): array
    {
        $dirs = DB::selectOne("
            SELECT COUNT(*) as total,
                COUNT(*) FILTER (WHERE status = 'completed') as completed,
                COUNT(*) FILTER (WHERE status = 'pending' OR status IS NULL) as pending,
                SUM(contacts_extracted) as extracted,
                SUM(contacts_created) as created,
                MAX(last_scraped_at) as last_run
            FROM directories
        ");
        $inf = DB::selectOne("
            SELECT COUNT(*) as total,
                COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '') as with_email
            FROM influenceurs WHERE deleted_at IS NULL
        ");

        return [
            'directories_total'     => (int) ($dirs->total ?? 0),
            'directories_completed' => (int) ($dirs->completed ?? 0),
            'directories_pending'   => (int) ($dirs->pending ?? 0),
            'contacts_extracted'    => (int) ($dirs->extracted ?? 0),
            'contacts_created'      => (int) ($dirs->created ?? 0),
            'crm_total'             => (int) ($inf->total ?? 0),
            'crm_with_email'        => (int) ($inf->with_email ?? 0),
            'last_run'              => $dirs->last_run,
        ];
    }

    private function businessStats(): array
    {
        $data = DB::selectOne("
            SELECT COUNT(*) as total,
                COUNT(*) FILTER (WHERE contact_email IS NOT NULL) as with_email,
                COUNT(*) FILTER (WHERE detail_scraped = true) as detailed,
                MAX(scraped_at) as last_run
            FROM content_businesses
        ");

        return [
            'contacts_found'      => (int) ($data->total ?? 0),
            'contacts_with_email' => (int) ($data->with_email ?? 0),
            'detailed_scraped'    => (int) ($data->detailed ?? 0),
            'last_run'            => $data->last_run,
        ];
    }

    private function siteContactStats(): array
    {
        $pending = DB::table('influenceurs')
            ->whereNotNull('website_url')
            ->where(function ($q) {
                $q->where('scraper_status', 'pending')->orWhere('scraper_status', 'failed');
            })
            ->count();

        $done = DB::table('influenceurs')
            ->where('scraper_status', 'completed')
            ->count();

        $cc = DB::selectOne("SELECT COUNT(*) as total, COUNT(*) FILTER (WHERE email IS NOT NULL) as with_email FROM content_contacts");

        return [
            'sites_pending'       => $pending,
            'sites_done'          => $done,
            'contacts_found'      => (int) ($cc->total ?? 0),
            'contacts_with_email' => (int) ($cc->with_email ?? 0),
            'last_run'            => null,
        ];
    }
}
