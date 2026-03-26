<?php

namespace App\Jobs;

use App\Models\ContentArticle;
use App\Models\ContentExternalLink;
use App\Models\ContentSource;
use App\Services\ContentScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeContentMagazineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400; // 4h — can have 1000+ articles
    public int $tries = 1;

    public function __construct(
        private int $sourceId,
        private string $section, // 'magazine', 'services', 'thematic', 'cities'
    ) {
        $this->onQueue('content-scraper');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('scrape-mag-' . $this->sourceId . '-' . $this->section))
                ->releaseAfter(14400)
                ->expireAfter(14400),
        ];
    }

    public function handle(ContentScraperService $scraper): void
    {
        $source = ContentSource::find($this->sourceId);
        if (!$source) return;

        Log::info('ScrapeContentMagazineJob: starting', [
            'source'  => $source->slug,
            'section' => $this->section,
        ]);

        // Pre-load existing URLs
        $existingUrls = ContentArticle::where('source_id', $source->id)
            ->pluck('url')
            ->flip()
            ->toArray();

        $existingLinkHashes = ContentExternalLink::where('source_id', $source->id)
            ->pluck('url_hash')
            ->flip()
            ->toArray();

        // Discover article URLs based on section type
        $articleUrls = match ($this->section) {
            'magazine'  => $this->discoverFromSitemaps($source, 'news'),
            'thematic'  => $this->discoverThematicGuides($source),
            'cities'    => $this->discoverFromSitemaps($source, 'guide'),
            default     => [],
        };

        Log::info('ScrapeContentMagazineJob: discovered URLs', [
            'section' => $this->section,
            'total'   => count($articleUrls),
            'new'     => count(array_filter($articleUrls, fn($a) => !isset($existingUrls[$a['url']]))),
        ]);

        $scrapedCount = 0;
        $skippedCount = 0;
        $consecutiveFailures = 0;

        foreach ($articleUrls as $articleData) {
            if (isset($existingUrls[$articleData['url']])) {
                $skippedCount++;
                continue;
            }

            try {
                $scraper->rateLimitSleep();
                $content = $scraper->scrapeArticle($articleData['url']);
                if (!$content || $content['word_count'] < 30) {
                    $consecutiveFailures++;
                    if ($consecutiveFailures >= 20) {
                        Log::warning('ScrapeContentMagazineJob: stopping after 20 consecutive failures');
                        break;
                    }
                    continue;
                }

                $urlHash = hash('sha256', $articleData['url']);

                $article = ContentArticle::create([
                    'source_id'        => $source->id,
                    'country_id'       => null,
                    'title'            => $content['title'] ?: $articleData['title'] ?? '',
                    'slug'             => $content['slug'] ?: substr($urlHash, 0, 12),
                    'url'              => $articleData['url'],
                    'url_hash'         => $urlHash,
                    'category'         => $articleData['category'] ?? null,
                    'section'          => $this->section === 'cities' ? 'guide' : $this->section,
                    'content_text'     => $content['content_text'],
                    'content_html'     => $content['content_html'],
                    'word_count'       => $content['word_count'],
                    'language'         => $content['language'],
                    'external_links'   => $content['external_links'],
                    'ads_and_sponsors' => $content['ads_and_sponsors'],
                    'images'           => $content['images'],
                    'meta_title'       => $content['meta_title'],
                    'meta_description' => $content['meta_description'],
                    'is_guide'         => $this->section !== 'magazine',
                    'scraped_at'       => now(),
                ]);

                $existingUrls[$articleData['url']] = true;

                foreach ($content['external_links'] as $link) {
                    $linkHash = hash('sha256', $link['url']);
                    if (!isset($existingLinkHashes[$linkHash])) {
                        ContentExternalLink::create([
                            'source_id'    => $source->id,
                            'article_id'   => $article->id,
                            'url'          => $link['url'],
                            'url_hash'     => $linkHash,
                            'original_url' => $link['original_url'],
                            'domain'       => $link['domain'],
                            'anchor_text'  => $link['anchor_text'],
                            'context'      => $link['context'],
                            'country_id'   => null,
                            'link_type'    => $link['link_type'],
                            'is_affiliate' => $link['is_affiliate'],
                            'language'     => $content['language'] ?? 'fr',
                        ]);
                        $existingLinkHashes[$linkHash] = true;
                    }
                }

                $scrapedCount++;
                $consecutiveFailures = 0;

                if ($scrapedCount % 50 === 0) {
                    gc_collect_cycles();
                    Log::info('ScrapeContentMagazineJob: progress', [
                        'section' => $this->section,
                        'scraped' => $scrapedCount,
                        'skipped' => $skippedCount,
                    ]);
                }

            } catch (\Throwable $e) {
                $consecutiveFailures++;
                Log::warning('ScrapeContentMagazineJob: article failed', [
                    'url'   => $articleData['url'],
                    'error' => $e->getMessage(),
                ]);
                if ($consecutiveFailures >= 20) break;
            }
        }

        $source->update([
            'total_articles' => $source->articles()->count(),
            'total_links'    => $source->externalLinks()->count(),
        ]);

        Log::info('ScrapeContentMagazineJob: completed', [
            'section' => $this->section,
            'scraped' => $scrapedCount,
            'skipped' => $skippedCount,
        ]);
    }

    /**
     * Discover article URLs from XML sitemaps.
     * For 'news': parses fr-news-*.xml sitemaps (magazine articles)
     * For 'guide': parses fr-guide-*.xml sitemaps (city/country articles not yet scraped)
     */
    private function discoverFromSitemaps(ContentSource $source, string $type): array
    {
        $articles = [];
        $siteBase = 'https://www.expat.com';

        $regions = ['world', 'africa', 'asia', 'central-america', 'europe', 'middle-east', 'north-america', 'oceania', 'south-america'];

        foreach ($regions as $region) {
            $sitemapUrl = "{$siteBase}/fr/fr-{$type}-{$region}-1.xml";
            $xml = $this->fetchSitemap($sitemapUrl);
            if (!$xml) continue;

            foreach ($xml as $url) {
                $loc = (string) $url->loc;
                if (empty($loc)) continue;

                // For news: only take /fr/expat-mag/ article URLs (not category indexes)
                if ($type === 'news') {
                    if (!str_contains($loc, '/fr/expat-mag/')) continue;
                    // Skip category index pages (e.g. /fr/expat-mag/3-vie-quotidienne/)
                    if (preg_match('#/fr/expat-mag/\d+-[^/]+/$#', $loc)) continue;
                    // Skip the main index
                    if ($loc === $siteBase . '/fr/expat-mag/') continue;
                }

                // For guide: only take .html articles (individual pages)
                if ($type === 'guide') {
                    if (!str_ends_with($loc, '.html')) continue;
                    if (!str_contains($loc, '/fr/guide/')) continue;
                }

                $articles[] = [
                    'url'      => $loc,
                    'title'    => '',
                    'category' => $this->extractCategoryFromUrl($loc),
                ];
            }
        }

        return $articles;
    }

    /**
     * Discover thematic guide articles (transversal, not per-country).
     * e.g. /fr/guide/e-2-travailler-a-l-etranger.html
     */
    private function discoverThematicGuides(ContentSource $source): array
    {
        $articles = [];
        $siteBase = 'https://www.expat.com';

        // Known thematic guide index pages
        $thematicPages = [
            $siteBase . '/fr/guide/e-2-travailler-a-l-etranger.html',
            $siteBase . '/fr/guide/e-5-retraite-a-l-etranger.html',
            $siteBase . '/fr/guide/e-8-programme-vacances-travail.html',
        ];

        // First add the index pages themselves
        foreach ($thematicPages as $pageUrl) {
            $articles[] = ['url' => $pageUrl, 'title' => '', 'category' => 'thematique'];
        }

        // Also get from the world sitemap
        $sitemapUrl = "{$siteBase}/fr/fr-guide-world-1.xml";
        $xml = $this->fetchSitemap($sitemapUrl);
        if ($xml) {
            foreach ($xml as $url) {
                $loc = (string) $url->loc;
                if (empty($loc)) continue;
                // Thematic articles: /fr/guide/e-{id}-{slug}.html
                if (preg_match('#/fr/guide/e-\d+-#', $loc)) {
                    $articles[] = [
                        'url'      => $loc,
                        'title'    => '',
                        'category' => 'thematique',
                    ];
                }
            }
        }

        return $articles;
    }

    private function fetchSitemap(string $url): ?\SimpleXMLElement
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SOSExpatBot/1.0)'])
                ->get($url);

            if (!$response->successful()) return null;

            $xml = @simplexml_load_string($response->body());
            if (!$xml) return null;

            // Register namespace for xpath
            $xml->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');

            return $xml;
        } catch (\Throwable $e) {
            Log::warning('ScrapeContentMagazineJob: sitemap fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function extractCategoryFromUrl(string $url): ?string
    {
        // /fr/expat-mag/8-formalites/ → Formalites
        if (preg_match('#/fr/expat-mag/\d+-([a-z-]+)/#', $url, $m)) {
            return ucfirst(str_replace('-', ' ', $m[1]));
        }
        // /fr/expat-mag/afrique/senegal/... → Afrique
        if (preg_match('#/fr/expat-mag/([a-z-]+)/#', $url, $m)) {
            return ucfirst(str_replace('-', ' ', $m[1]));
        }
        if (str_contains($url, '/e-2-')) return 'Travailler a l\'etranger';
        if (str_contains($url, '/e-5-')) return 'Retraite a l\'etranger';
        if (str_contains($url, '/e-8-')) return 'Programme Vacances-Travail';
        return null;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ScrapeContentMagazineJob: job failed', [
            'sourceId' => $this->sourceId,
            'section'  => $this->section,
            'error'    => $e->getMessage(),
        ]);
    }
}
