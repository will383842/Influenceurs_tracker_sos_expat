<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeFemmexpatJob;
use App\Jobs\ScrapeFrancaisEtrangerJob;
use App\Jobs\ScrapeGenericSiteJob;
use App\Jobs\ScrapeContentMagazineJob;
use App\Jobs\ScrapeContentSourceJob;
use App\Jobs\ScrapeContentCitiesJob;
use App\Models\ContentArticle;
use App\Models\ContentBusiness;
use App\Models\ContentCity;
use App\Models\ContentCountry;
use App\Models\ContentExternalLink;
use App\Models\ContentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ContentEngineController extends Controller
{
    public function sources(): JsonResponse
    {
        $sources = ContentSource::withCount(['countries', 'articles', 'externalLinks'])
            ->orderBy('name')
            ->get();

        return response()->json($sources);
    }

    public function createSource(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:100|unique:content_sources,name',
            'base_url' => ['required', 'url', 'max:500', 'regex:/^https:\/\//i', 'unique:content_sources,base_url'],
        ]);

        // Generate unique slug
        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 1;
        while (ContentSource::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i++;
        }

        // Block reserved slugs that conflict with routes
        if (in_array($slug, ['links', 'articles', 'stats', 'sources'])) {
            $slug = $baseSlug . '-source';
        }

        $source = ContentSource::create([
            'name'     => $validated['name'],
            'slug'     => $slug,
            'base_url' => rtrim($validated['base_url'], '/') . '/',
        ]);

        return response()->json($source, 201);
    }

    public function showSource(string $slug): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)
            ->withCount(['countries', 'articles', 'externalLinks'])
            ->firstOrFail();

        $scrapedCountries = $source->countries()->whereNotNull('scraped_at')->count();

        return response()->json([
            ...$source->toArray(),
            'scraped_countries' => $scrapedCountries,
        ]);
    }

    public function scrapeSource(string $slug): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();

        if ($source->status === 'scraping') {
            // Auto-reset if stuck for more than 4 hours
            if ($source->updated_at && $source->updated_at->diffInHours(now()) > 4) {
                $source->update(['status' => 'pending']);
            } else {
                return response()->json(['message' => 'Scraping already in progress'], 409);
            }
        }

        $source->update(['status' => 'scraping']);
        ScrapeContentSourceJob::dispatch($source->id);

        return response()->json(['message' => 'Scraping started', 'source' => $source->fresh()]);
    }

    /**
     * Pause a running scrape.
     */
    public function pauseSource(string $slug): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();
        $source->update(['status' => 'paused']);
        return response()->json(['message' => 'Scraping paused', 'source' => $source]);
    }

    public function countries(string $slug, Request $request): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();

        $query = $source->countries()->withCount('articles');

        if ($request->filled('continent')) {
            $query->where('continent', $request->input('continent'));
        }

        $countries = $query->orderBy('continent')->orderBy('name')->get();

        // Extract continents from result instead of a separate query
        $continents = $countries->pluck('continent')->filter()->unique()->sort()->values();

        return response()->json([
            'countries'  => $countries,
            'continents' => $continents,
        ]);
    }

    public function countryArticles(string $slug, string $countrySlug, Request $request): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();
        $country = ContentCountry::where('source_id', $source->id)
            ->where('slug', $countrySlug)
            ->firstOrFail();

        $query = ContentArticle::where('country_id', $country->id)
            ->select('id', 'title', 'slug', 'url', 'category', 'word_count', 'is_guide', 'scraped_at')
            ->withCount('links')
            ->orderByDesc('is_guide')
            ->orderBy('category')
            ->orderBy('title');

        $perPage = min((int) $request->input('per_page', 50), 100);

        return response()->json([
            'country'  => $country,
            'articles' => $query->paginate($perPage),
        ]);
    }

    public function showArticle(int $id): JsonResponse
    {
        $article = ContentArticle::with(['country:id,name,slug', 'source:id,name,slug'])
            ->findOrFail($id);

        $links = ContentExternalLink::where('article_id', $id)
            ->orderBy('domain')
            ->get();

        return response()->json([
            'article' => $article,
            'links'   => $links,
        ]);
    }

    public function externalLinks(Request $request): JsonResponse
    {
        $query = ContentExternalLink::with(['source:id,name,slug', 'country:id,name,slug']);

        if ($request->filled('source')) {
            $source = ContentSource::where('slug', $request->input('source'))->first();
            if ($source) {
                $query->where('source_id', $source->id);
            }
        }
        if ($request->filled('country_id')) {
            $query->where('country_id', (int) $request->input('country_id'));
        }
        if ($request->filled('domain')) {
            $domain = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('domain'));
            $query->where('domain', 'ilike', '%' . $domain . '%');
        }
        if ($request->filled('link_type')) {
            $query->where('link_type', $request->input('link_type'));
        }
        if ($request->filled('is_affiliate')) {
            $query->where('is_affiliate', $request->boolean('is_affiliate'));
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('url', 'ilike', '%' . $search . '%')
                  ->orWhere('anchor_text', 'ilike', '%' . $search . '%')
                  ->orWhere('domain', 'ilike', '%' . $search . '%');
            });
        }

        // Whitelist sort columns to prevent SQL injection
        $allowedSorts = ['occurrences', 'domain', 'url', 'link_type', 'created_at', 'anchor_text'];
        $sort = in_array($request->input('sort'), $allowedSorts) ? $request->input('sort') : 'occurrences';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        $perPage = min((int) $request->input('per_page', 50), 100);

        return response()->json($query->paginate($perPage));
    }

    public function stats(): JsonResponse
    {
        $data = Cache::remember('content-engine-stats', 300, function () {
            return [
                'total_sources'   => ContentSource::count(),
                'total_countries' => ContentCountry::count(),
                'total_articles'  => ContentArticle::count(),
                'total_links'     => ContentExternalLink::count(),
                'total_words'     => (int) ContentArticle::sum('word_count'),
                'affiliate_links' => ContentExternalLink::where('is_affiliate', true)->count(),
                'top_domains'     => ContentExternalLink::selectRaw('domain, COUNT(*) as count, SUM(occurrences) as total_occurrences')
                    ->groupBy('domain')->orderByDesc('total_occurrences')->limit(20)->get(),
                'by_category'     => ContentArticle::selectRaw('category, COUNT(*) as count')
                    ->whereNotNull('category')->groupBy('category')->orderByDesc('count')->get(),
                'link_types'      => ContentExternalLink::selectRaw('link_type, COUNT(*) as count')
                    ->groupBy('link_type')->orderByDesc('count')->get(),
                'articles_by_status' => ContentArticle::selectRaw('processing_status, COUNT(*) as count')
                    ->groupBy('processing_status')->orderByDesc('count')->get(),
                'questions_by_status' => \DB::table('content_questions')
                    ->selectRaw('article_status, COUNT(*) as count, SUM(views) as total_views')
                    ->groupBy('article_status')->get(),
                'total_opportunities' => \DB::table('content_opportunities')->count(),
                'total_questions'     => \DB::table('content_questions')->count(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Data cleanup dashboard: processing stats, opportunities, monetizable themes.
     */
    public function dataCleanupStats(): JsonResponse
    {
        $data = Cache::remember('data-cleanup-stats', 300, function () {
            $articlesByStatus = ContentArticle::selectRaw('processing_status, COUNT(*) as count, ROUND(AVG(word_count)) as avg_words')
                ->groupBy('processing_status')->get()->keyBy('processing_status');

            $articlesBySource = ContentArticle::selectRaw('
                    content_sources.name as source_name,
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE processing_status = \'processed\') as processed,
                    COUNT(*) FILTER (WHERE processing_status = \'duplicate\') as duplicates,
                    COUNT(*) FILTER (WHERE processing_status = \'low_quality\') as low_quality,
                    COUNT(*) FILTER (WHERE country_id IS NOT NULL) as with_country,
                    COUNT(*) FILTER (WHERE country_id IS NULL) as without_country
                ')
                ->join('content_sources', 'content_articles.source_id', '=', 'content_sources.id')
                ->groupBy('content_sources.name')
                ->orderByDesc('total')
                ->get();

            $questionStats = \DB::table('content_questions')
                ->selectRaw('article_status, COUNT(*) as count, SUM(views) as total_views, SUM(replies) as total_replies')
                ->groupBy('article_status')->get();

            $topOpportunities = \DB::table('content_opportunities')
                ->select('question_title', 'country', 'theme', 'views', 'replies', 'priority_score')
                ->where('status', 'opportunity')
                ->orderByDesc('priority_score')
                ->limit(100)
                ->get();

            $opportunitiesByTheme = \DB::table('content_opportunities')
                ->selectRaw('theme, COUNT(*) as count, SUM(views) as total_views')
                ->where('status', 'opportunity')
                ->groupBy('theme')
                ->orderByDesc('total_views')
                ->get();

            $opportunitiesByCountry = \DB::table('content_opportunities')
                ->selectRaw('country, COUNT(*) as count, SUM(views) as total_views')
                ->where('status', 'opportunity')
                ->whereNotNull('country')
                ->groupBy('country')
                ->orderByDesc('total_views')
                ->limit(30)
                ->get();

            $monetizableThemes = \DB::table('monetizable_themes')
                ->select('country', 'theme', 'nb_existing_articles', 'qa_total_views', 'monetization_score')
                ->orderByDesc('monetization_score')
                ->limit(50)
                ->get();

            $affiliatePrograms = ContentExternalLink::selectRaw('domain, COUNT(*) as nb_links, COUNT(DISTINCT article_id) as nb_articles')
                ->where('is_affiliate', true)
                ->groupBy('domain')
                ->orderByDesc('nb_links')
                ->get();

            return [
                'articles_by_status'       => $articlesByStatus,
                'articles_by_source'       => $articlesBySource,
                'question_stats'           => $questionStats,
                'top_opportunities'        => $topOpportunities,
                'opportunities_by_theme'   => $opportunitiesByTheme,
                'opportunities_by_country' => $opportunitiesByCountry,
                'monetizable_themes'       => $monetizableThemes,
                'affiliate_programs'       => $affiliatePrograms,
            ];
        });

        return response()->json($data);
    }

    /**
     * Affiliate domains: sites with affiliate programs, grouped by domain.
     * Filters out false positives (gov sites, europa.eu, google, etc. that just have UTM params).
     */
    public function affiliateDomains(): JsonResponse
    {
        // Domains that are NOT affiliate programs (just have UTM tracking on their links)
        $excludePatterns = [
            '%.gov', '%.gov.%', '%.gouv.%', '%.gob.%', '%.edu', '%.edu.%',
            '%.int', 'europa.eu', '%.europa.eu',
            'www.google.%', 'maps.google.%',
            '%.wikipedia.org', '%.pagesjaunes.%',
            '%.ac.%', // academic
        ];

        $query = ContentExternalLink::selectRaw("
            domain,
            SUM(occurrences) as total_mentions,
            COUNT(*) as liens_uniques,
            MIN(url) as exemple_url,
            MIN(anchor_text) FILTER (WHERE anchor_text IS NOT NULL AND anchor_text != '') as exemple_anchor
        ")
        ->where('is_affiliate', true);

        foreach ($excludePatterns as $pattern) {
            $query->where('domain', 'NOT ILIKE', $pattern);
        }

        $results = $query->groupBy('domain')
            ->havingRaw('SUM(occurrences) >= 2')
            ->orderByDesc('total_mentions')
            ->get();

        return response()->json($results);
    }

    public function scrapeMagazine(string $slug): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();
        ScrapeContentMagazineJob::dispatch($source->id, 'magazine');
        return response()->json(['message' => 'Magazine scraping started']);
    }

    public function scrapeServices(string $slug): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();
        ScrapeContentMagazineJob::dispatch($source->id, 'services');
        return response()->json(['message' => 'Services scraping started']);
    }

    public function scrapeThematic(string $slug): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();
        ScrapeContentMagazineJob::dispatch($source->id, 'thematic');
        return response()->json(['message' => 'Thematic guides scraping started']);
    }

    /**
     * Lance le scraping complet des villes : découverte + scraping per-ville.
     * Optionnel: country_id pour limiter à un seul pays.
     */
    public function scrapeCities(string $slug, Request $request): JsonResponse
    {
        $source    = ContentSource::where('slug', $slug)->firstOrFail();
        $countryId = $request->input('country_id') ? (int) $request->input('country_id') : null;

        ScrapeContentCitiesJob::dispatch($source->id, $countryId);

        return response()->json([
            'message'    => 'Scraping des villes lancé',
            'source'     => $source->slug,
            'country_id' => $countryId,
        ]);
    }

    /**
     * Liste toutes les villes scrapées pour une source, groupées par pays.
     */
    public function cities(string $slug, Request $request): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();

        $query = ContentCity::where('source_id', $source->id)
            ->with('country:id,name,slug,continent')
            ->withCount('articles');

        if ($request->filled('country_slug')) {
            $country = ContentCountry::where('source_id', $source->id)
                ->where('slug', $request->input('country_slug'))
                ->first();
            if ($country) {
                $query->where('country_id', $country->id);
            }
        }

        if ($request->filled('continent')) {
            $query->where('continent', $request->input('continent'));
        }

        $cities = $query->orderBy('continent')->orderBy('name')->get();

        $continents = $cities->pluck('continent')->filter()->unique()->sort()->values();
        $countries  = $cities->pluck('country')->filter()->unique('id')->sortBy('name')->values();

        $stats = [
            'total_cities'          => $cities->count(),
            'cities_scraped'        => $cities->whereNotNull('scraped_at')->count(),
            'cities_pending'        => $cities->whereNull('scraped_at')->count(),
            'total_city_articles'   => $cities->sum('articles_count'),
        ];

        return response()->json([
            'cities'     => $cities,
            'continents' => $continents,
            'countries'  => $countries,
            'stats'      => $stats,
        ]);
    }

    /**
     * Articles d'une ville spécifique.
     */
    public function cityArticles(string $slug, string $citySlug, Request $request): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();
        $city   = ContentCity::where('source_id', $source->id)
            ->where('slug', $citySlug)
            ->with('country:id,name,slug')
            ->firstOrFail();

        $query = ContentArticle::where('city_id', $city->id)
            ->select('id', 'title', 'slug', 'url', 'category', 'word_count', 'is_guide', 'scraped_at')
            ->withCount('links')
            ->orderByDesc('is_guide')
            ->orderBy('category')
            ->orderBy('title');

        $perPage = min((int) $request->input('per_page', 50), 100);

        return response()->json([
            'city'     => $city,
            'articles' => $query->paginate($perPage),
        ]);
    }

    /**
     * Stats globales des villes scrapées (pour le dashboard).
     */
    public function cityStats(string $slug): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();

        $stats = Cache::remember("city-stats-{$source->id}", 120, function () use ($source) {
            $cities = ContentCity::where('source_id', $source->id)->get();

            $byContinent = ContentCity::where('source_id', $source->id)
                ->selectRaw('continent, COUNT(*) as nb_villes, SUM(articles_count) as nb_articles')
                ->groupBy('continent')
                ->orderByDesc('nb_articles')
                ->get();

            $topCities = ContentCity::where('source_id', $source->id)
                ->whereNotNull('scraped_at')
                ->with('country:id,name,slug')
                ->orderByDesc('articles_count')
                ->limit(20)
                ->get(['id', 'name', 'slug', 'continent', 'country_id', 'articles_count', 'scraped_at']);

            return [
                'total_cities'        => $cities->count(),
                'scraped_cities'      => $cities->whereNotNull('scraped_at')->count(),
                'pending_cities'      => $cities->whereNull('scraped_at')->count(),
                'total_city_articles' => (int) $cities->sum('articles_count'),
                'by_continent'        => $byContinent,
                'top_cities'          => $topCities,
            ];
        });

        return response()->json($stats);
    }

    public function scrapeFull(string $slug): JsonResponse
    {
        $source = ContentSource::where('slug', $slug)->firstOrFail();
        $source->update(['status' => 'scraping']);

        // Dispatch the right scraper based on the source
        match ($source->slug) {
            'femmexpat'              => ScrapeFemmexpatJob::dispatch($source->id),
            'francais-a-l-etranger' => ScrapeFrancaisEtrangerJob::dispatch($source->id),
            default                 => ScrapeGenericSiteJob::dispatch($source->id),
        };

        return response()->json(['message' => 'Full site scraping started', 'source' => $source->fresh()]);
    }

    /**
     * Country profiles: unified data per country from materialized view + businesses.
     */
    public function countryProfiles(): JsonResponse
    {
        $data = Cache::remember('country-profiles', 300, function () {
            // Use the materialized view for fast aggregated data
            $profiles = \DB::table('country_profiles')
                ->orderByDesc('priority_score')
                ->get();

            // Get business counts per country
            $businessCounts = ContentBusiness::selectRaw('country_slug, COUNT(*) as count')
                ->groupBy('country_slug')
                ->pluck('count', 'country_slug');

            $result = $profiles->map(function ($c) use ($businessCounts) {
                return [
                    'id'                => $c->country_id,
                    'name'              => $c->country_name,
                    'slug'              => $c->country_slug,
                    'continent'         => $c->continent,
                    'total_articles'    => (int) $c->total_articles,
                    'nb_sources'        => (int) $c->nb_sources,
                    'sources'           => $c->sources,
                    'total_words'       => (int) ($c->total_words ?? 0),
                    'total_businesses'  => (int) ($businessCounts[$c->country_slug] ?? 0),
                    'total_questions'   => (int) $c->total_questions,
                    'total_qa_views'    => (int) $c->total_qa_views,
                    'priority_score'    => (int) $c->priority_score,
                    'thematic_coverage' => (int) $c->thematic_coverage,
                    'visa'              => (int) $c->visa_articles,
                    'emploi'            => (int) $c->emploi_articles,
                    'logement'          => (int) $c->logement_articles,
                    'sante'             => (int) $c->sante_articles,
                    'banque'            => (int) $c->banque_articles,
                    'education'         => (int) $c->education_articles,
                    'transport'         => (int) $c->transport_articles,
                    'telecom'           => (int) $c->telecom_articles,
                    'culture'           => (int) $c->culture_articles,
                    'demarches'         => (int) $c->demarches_articles,
                ];
            });

            $grouped = $result->groupBy('continent');

            return [
                'countries'    => $result,
                'by_continent' => $grouped,
                'totals'       => [
                    'countries'  => $result->count(),
                    'articles'   => $result->sum('total_articles'),
                    'words'      => $result->sum('total_words'),
                    'questions'  => $result->sum('total_questions'),
                    'businesses' => $result->sum('total_businesses'),
                ],
            ];
        });

        return response()->json($data);
    }

    /**
     * Single country profile with detailed data.
     */
    public function countryProfile(string $countrySlug): JsonResponse
    {
        $country = ContentCountry::where('slug', $countrySlug)->firstOrFail();

        $articles = ContentArticle::where('country_id', $country->id)
            ->select('id', 'title', 'slug', 'url', 'category', 'section', 'word_count', 'is_guide', 'meta_description', 'scraped_at')
            ->orderByDesc('is_guide')
            ->orderBy('category')
            ->get();

        $links = ContentExternalLink::where('country_id', $country->id)
            ->select('id', 'url', 'domain', 'anchor_text', 'link_type', 'is_affiliate', 'occurrences')
            ->orderByDesc('occurrences')
            ->limit(50)
            ->get();

        $businesses = ContentBusiness::where('country_slug', $country->slug)
            ->select('id', 'name', 'contact_email', 'contact_phone', 'website', 'city', 'category', 'subcategory', 'is_premium')
            ->orderByDesc('recommendations')
            ->limit(50)
            ->get();

        $categories = ContentArticle::where('country_id', $country->id)
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'country'    => $country,
            'articles'   => $articles,
            'links'      => $links,
            'businesses' => $businesses,
            'categories' => $categories,
        ]);
    }

    /**
     * City profiles: aggregated data per city from materialized view.
     */
    public function cityProfiles(Request $request): JsonResponse
    {
        $data = Cache::remember('city-profiles', 300, function () {
            $profiles = \DB::table('city_profiles')
                ->orderByDesc('priority_score')
                ->get();

            $result = $profiles->map(function ($c) {
                return [
                    'id'                => $c->city_id,
                    'name'              => $c->city_name,
                    'slug'              => $c->city_slug,
                    'continent'         => $c->continent,
                    'country_id'        => $c->country_id,
                    'country_name'      => $c->country_name,
                    'country_slug'      => $c->country_slug,
                    'total_articles'    => (int) $c->total_articles,
                    'total_words'       => (int) ($c->total_words ?? 0),
                    'avg_word_count'    => (int) ($c->avg_word_count ?? 0),
                    'nb_sources'        => (int) $c->nb_sources,
                    'thematic_coverage' => (int) $c->thematic_coverage,
                    'priority_score'    => (int) $c->priority_score,
                    'visa'              => (int) $c->visa_articles,
                    'emploi'            => (int) $c->emploi_articles,
                    'logement'          => (int) $c->logement_articles,
                    'sante'             => (int) $c->sante_articles,
                    'banque'            => (int) $c->banque_articles,
                    'transport'         => (int) $c->transport_articles,
                    'culture'           => (int) $c->culture_articles,
                ];
            });

            return [
                'cities'    => $result,
                'totals'    => [
                    'cities'   => $result->count(),
                    'articles' => $result->sum('total_articles'),
                    'words'    => $result->sum('total_words'),
                    'with_content' => $result->where('total_articles', '>', 0)->count(),
                ],
            ];
        });

        return response()->json($data);
    }

    /**
     * Single city profile with detailed articles.
     */
    public function cityProfile(string $citySlug): JsonResponse
    {
        $city = ContentCity::with('country')->where('slug', $citySlug)->firstOrFail();

        $articles = ContentArticle::where('city_id', $city->id)
            ->select('id', 'title', 'slug', 'url', 'category', 'section', 'word_count', 'is_guide', 'meta_description', 'scraped_at')
            ->orderByDesc('is_guide')
            ->orderBy('category')
            ->get();

        $categories = ContentArticle::where('city_id', $city->id)
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('count')
            ->get();

        $profile = \DB::table('city_profiles')->where('city_id', $city->id)->first();

        return response()->json([
            'city'       => $city,
            'articles'   => $articles,
            'categories' => $categories,
            'profile'    => $profile,
        ]);
    }

    public function exportLinks(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = ContentExternalLink::query()
            ->join('content_sources', 'content_external_links.source_id', '=', 'content_sources.id')
            ->leftJoin('content_countries', 'content_external_links.country_id', '=', 'content_countries.id')
            ->select(
                'content_external_links.*',
                'content_sources.name as source_name',
                'content_countries.name as country_name'
            );

        if ($request->filled('source')) {
            $source = ContentSource::where('slug', $request->input('source'))->first();
            if ($source) $query->where('content_external_links.source_id', $source->id);
        }
        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('content_external_links.url', 'ilike', '%' . $search . '%')
                  ->orWhere('content_external_links.domain', 'ilike', '%' . $search . '%');
            });
        }
        if ($request->filled('link_type')) {
            $query->where('content_external_links.link_type', $request->input('link_type'));
        }
        if ($request->filled('is_affiliate')) {
            $query->where('content_external_links.is_affiliate', $request->boolean('is_affiliate'));
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="content-links-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['URL', 'Domain', 'Anchor Text', 'Type', 'Affiliate', 'Occurrences', 'Source', 'Country', 'Context']);

            $query->orderBy('content_external_links.domain')->chunk(500, function ($links) use ($out) {
                foreach ($links as $link) {
                    fputcsv($out, [
                        $this->csvSafe($link->url),
                        $link->domain,
                        $this->csvSafe($link->anchor_text ?? ''),
                        $link->link_type,
                        $link->is_affiliate ? 'Yes' : 'No',
                        $link->occurrences,
                        $link->source_name ?? '',
                        $link->country_name ?? '',
                        $this->csvSafe(mb_substr($link->context ?? '', 0, 200)),
                    ]);
                }
            });

            fclose($out);
        }, 200, $headers);
    }

    /** Prevent CSV injection (=, +, -, @) */
    private function csvSafe(string $value): string
    {
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }
}
