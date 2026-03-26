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

    public int $timeout = 7200; // 2h — magazine can have many articles
    public int $tries = 1;

    public function __construct(
        private int $sourceId,
        private string $section, // 'magazine' or 'services'
    ) {
        $this->onQueue('content-scraper');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('scrape-mag-' . $this->sourceId . '-' . $this->section))
                ->releaseAfter(7200)
                ->expireAfter(7200),
        ];
    }

    public function handle(ContentScraperService $scraper): void
    {
        $source = ContentSource::find($this->sourceId);
        if (!$source) return;

        $baseUrl = rtrim($source->base_url, '/');
        $siteBase = preg_replace('#/fr/guide/?$#', '', $baseUrl);

        $sectionUrl = match ($this->section) {
            'magazine' => $siteBase . '/fr/expat-mag/',
            'services' => $siteBase . '/fr/services/',
            default    => null,
        };

        if (!$sectionUrl) return;

        Log::info('ScrapeContentMagazineJob: starting', [
            'source'  => $source->slug,
            'section' => $this->section,
            'url'     => $sectionUrl,
        ]);

        // Pre-load existing URLs
        $existingUrls = ContentArticle::where('source_id', $source->id)
            ->where('section', $this->section)
            ->pluck('url')
            ->flip()
            ->toArray();

        $existingLinkHashes = ContentExternalLink::where('source_id', $source->id)
            ->pluck('url_hash')
            ->flip()
            ->toArray();

        $scrapedCount = 0;
        $consecutiveFailures = 0;

        try {
            // Discover article URLs — scrape paginated listing pages
            $articleUrls = $this->discoverArticleUrls($sectionUrl, $siteBase, $scraper);

            Log::info('ScrapeContentMagazineJob: discovered articles', [
                'section' => $this->section,
                'count'   => count($articleUrls),
            ]);

            foreach ($articleUrls as $articleData) {
                if (isset($existingUrls[$articleData['url']])) {
                    $scrapedCount++;
                    continue;
                }

                try {
                    $scraper->rateLimitSleep();
                    $content = $scraper->scrapeArticle($articleData['url']);
                    if (!$content) {
                        $consecutiveFailures++;
                        if ($consecutiveFailures >= 15) break;
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
                        'section'          => $this->section,
                        'content_text'     => $content['content_text'],
                        'content_html'     => $content['content_html'],
                        'word_count'       => $content['word_count'],
                        'language'         => $content['language'],
                        'external_links'   => $content['external_links'],
                        'ads_and_sponsors' => $content['ads_and_sponsors'],
                        'images'           => $content['images'],
                        'meta_title'       => $content['meta_title'],
                        'meta_description' => $content['meta_description'],
                        'is_guide'         => false,
                        'scraped_at'       => now(),
                    ]);

                    $existingUrls[$articleData['url']] = true;

                    // Save external links
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

                    if ($scrapedCount % 20 === 0) {
                        gc_collect_cycles();
                        Log::info('ScrapeContentMagazineJob: progress', [
                            'section' => $this->section,
                            'scraped' => $scrapedCount,
                        ]);
                    }

                } catch (\Throwable $e) {
                    $consecutiveFailures++;
                    Log::warning('ScrapeContentMagazineJob: article failed', [
                        'url'   => $articleData['url'],
                        'error' => $e->getMessage(),
                    ]);
                    if ($consecutiveFailures >= 15) break;
                }
            }

            // Update source stats
            $source->update([
                'total_articles' => $source->articles()->count(),
                'total_links'    => $source->externalLinks()->count(),
            ]);

            Log::info('ScrapeContentMagazineJob: completed', [
                'section' => $this->section,
                'scraped' => $scrapedCount,
            ]);

        } catch (\Throwable $e) {
            Log::error('ScrapeContentMagazineJob: failed', [
                'section' => $this->section,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Discover article URLs from the magazine/services index pages.
     */
    private function discoverArticleUrls(string $sectionUrl, string $siteBase, ContentScraperService $scraper): array
    {
        $articles = [];
        $seen = [];

        // Scrape up to 20 pages of listings
        for ($page = 1; $page <= 20; $page++) {
            $url = $page === 1 ? $sectionUrl : $sectionUrl . '?page=' . $page;
            $scraper->rateLimitSleep();

            $html = $this->fetchPage($url);
            if (!$html) break;

            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            libxml_clear_errors();
            $xpath = new \DOMXPath($dom);

            $foundOnPage = 0;

            // Find article links — magazine uses /fr/expat-mag/{id}-{slug}.html
            // Services uses /fr/services/{category}/{slug}/ or similar
            $links = $xpath->query('//a[@href]');
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $text = trim($link->textContent);

                $fullUrl = $this->resolveUrl($href, $siteBase);

                // Match magazine articles: /fr/expat-mag/{something}.html or /fr/expat-mag/{category}/{slug}
                if ($this->section === 'magazine' && preg_match('#/fr/expat-mag/[^?]+#', $href)) {
                    // Skip category index pages, only take articles
                    if (str_ends_with($href, '.html') || preg_match('#/fr/expat-mag/[^/]+/[^/]+#', $href)) {
                        if (!isset($seen[$fullUrl]) && $text && strlen($text) > 10) {
                            $seen[$fullUrl] = true;
                            $articles[] = [
                                'url'      => $fullUrl,
                                'title'    => $text,
                                'category' => $this->extractCategoryFromUrl($href),
                            ];
                            $foundOnPage++;
                        }
                    }
                }

                // Match services pages
                if ($this->section === 'services' && preg_match('#/fr/services/[^?]+#', $href)) {
                    if (!isset($seen[$fullUrl]) && $text && strlen($text) > 5) {
                        $seen[$fullUrl] = true;
                        $articles[] = [
                            'url'      => $fullUrl,
                            'title'    => $text,
                            'category' => $this->extractCategoryFromUrl($href),
                        ];
                        $foundOnPage++;
                    }
                }
            }

            unset($xpath, $dom);

            // Also extract from embedded JSON if available
            $unescaped = str_replace('\\/', '/', $html);
            $pattern = $this->section === 'magazine'
                ? '#"url"\s*:\s*"https?://[^"]*?/fr/expat-mag/[^"]+\.html"#'
                : '#"url"\s*:\s*"https?://[^"]*?/fr/services/[^"]+"#';

            if (preg_match_all($pattern, $unescaped, $jsonUrls)) {
                foreach ($jsonUrls[0] as $match) {
                    if (preg_match('#"(https?://[^"]+)"#', $match, $urlMatch)) {
                        $articleUrl = $urlMatch[1];
                        if (!isset($seen[$articleUrl])) {
                            $seen[$articleUrl] = true;
                            $articles[] = [
                                'url'      => $articleUrl,
                                'title'    => '',
                                'category' => null,
                            ];
                            $foundOnPage++;
                        }
                    }
                }
            }

            // Stop pagination if no new articles found
            if ($foundOnPage === 0) break;
        }

        return $articles;
    }

    private function extractCategoryFromUrl(string $url): ?string
    {
        // /fr/expat-mag/7840-interview-expat.html → null
        // /fr/expat-mag/destination/article.html → destination
        // /fr/services/assurance/ → assurance
        if (preg_match('#/fr/(?:expat-mag|services)/([a-z-]+)/#', $url, $m)) {
            return ucfirst(str_replace('-', ' ', $m[1]));
        }
        return null;
    }

    private function fetchPage(string $url): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SOSExpatBot/1.0; +https://sos-expat.com)'])
                ->get($url);
            return $response->successful() ? $response->body() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveUrl(string $href, string $siteBase): string
    {
        if (str_starts_with($href, 'http')) return $href;
        if (str_starts_with($href, '//')) return 'https:' . $href;
        return $siteBase . $href;
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
