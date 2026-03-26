<?php

namespace App\Jobs;

use App\Models\ContentArticle;
use App\Models\ContentContact;
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
use Illuminate\Support\Str;

class ScrapeFrancaisEtrangerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400; // 4h
    public int $tries = 1;

    public function __construct(
        private int $sourceId,
    ) {
        $this->onQueue('content-scraper');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('scrape-frae-' . $this->sourceId))
                ->releaseAfter(14400)
                ->expireAfter(14400),
        ];
    }

    public function handle(ContentScraperService $scraper): void
    {
        $source = ContentSource::find($this->sourceId);
        if (!$source) return;

        $source->update(['status' => 'scraping']);
        $baseUrl = rtrim($source->base_url, '/');

        Log::info('ScrapeFrancaisEtrangerJob: starting', ['source' => $source->slug]);

        try {
            // Step 1: Save editorial contact + social media
            $this->saveContacts($source, $baseUrl);

            // Step 2: Scrape author names from author sitemap
            $this->scrapeAuthors($source, $baseUrl, $scraper);

            // Step 3: Discover all articles from post sitemaps
            $articleUrls = $this->discoverFromSitemaps($baseUrl, 'post');

            // Step 4: Add country profile pages from pays sitemap
            $countryUrls = $this->discoverFromSitemaps($baseUrl, 'pays');
            $articleUrls = array_merge($articleUrls, $countryUrls);

            Log::info('ScrapeFrancaisEtrangerJob: discovered URLs', [
                'articles' => count($articleUrls),
            ]);

            // Pre-load existing
            $existingUrls = ContentArticle::where('source_id', $source->id)
                ->pluck('url')->flip()->toArray();
            $existingLinkHashes = ContentExternalLink::where('source_id', $source->id)
                ->pluck('url_hash')->flip()->toArray();

            $scrapedCount = 0;
            $consecutiveFailures = 0;

            foreach ($articleUrls as $articleData) {
                if (isset($existingUrls[$articleData['url']])) continue;

                try {
                    $scraper->rateLimitSleep();
                    $content = $scraper->scrapeArticle($articleData['url']);
                    if (!$content || $content['word_count'] < 30) {
                        $consecutiveFailures++;
                        if ($consecutiveFailures >= 20) break;
                        continue;
                    }

                    $urlHash = hash('sha256', $articleData['url']);

                    $article = ContentArticle::create([
                        'source_id'        => $source->id,
                        'title'            => $content['title'] ?: $articleData['title'] ?? '',
                        'slug'             => $content['slug'] ?: substr($urlHash, 0, 12),
                        'url'              => $articleData['url'],
                        'url_hash'         => $urlHash,
                        'category'         => $articleData['category'] ?? null,
                        'section'          => $articleData['section'] ?? 'guide',
                        'content_text'     => $content['content_text'],
                        'content_html'     => $content['content_html'],
                        'word_count'       => $content['word_count'],
                        'language'         => 'fr',
                        'external_links'   => $content['external_links'],
                        'ads_and_sponsors' => $content['ads_and_sponsors'],
                        'images'           => $content['images'],
                        'meta_title'       => $content['meta_title'],
                        'meta_description' => $content['meta_description'],
                        'is_guide'         => $articleData['section'] === 'pays',
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
                                'link_type'    => $link['link_type'],
                                'is_affiliate' => $link['is_affiliate'],
                                'language'     => 'fr',
                            ]);
                            $existingLinkHashes[$linkHash] = true;
                        }
                    }

                    $scrapedCount++;
                    $consecutiveFailures = 0;

                    if ($scrapedCount % 50 === 0) {
                        gc_collect_cycles();
                        Log::info('ScrapeFrancaisEtrangerJob: progress', ['scraped' => $scrapedCount]);
                    }

                } catch (\Throwable $e) {
                    $consecutiveFailures++;
                    if ($consecutiveFailures >= 20) break;
                }
            }

            $source->update([
                'status'          => 'completed',
                'total_articles'  => $source->articles()->count(),
                'total_links'     => $source->externalLinks()->count(),
                'last_scraped_at' => now(),
            ]);

            Log::info('ScrapeFrancaisEtrangerJob: completed', ['scraped' => $scrapedCount]);

        } catch (\Throwable $e) {
            Log::error('ScrapeFrancaisEtrangerJob: failed', ['error' => $e->getMessage()]);
            $source->update(['status' => 'pending']);
        }
    }

    private function saveContacts(ContentSource $source, string $baseUrl): void
    {
        // Editorial contact
        ContentContact::updateOrCreate(
            ['email' => 'contact@francaisaletranger.fr', 'source_id' => $source->id],
            [
                'name'        => 'Redaction Francais a l\'Etranger',
                'role'        => 'Contact editorial',
                'phone'       => '+33 7 71 70 78 54',
                'company'     => 'French Morning LLC',
                'company_url' => $baseUrl,
                'country'     => 'Etats-Unis',
                'city'        => 'Brooklyn, NY',
                'address'     => '450 Degraw Street, 11217 Brooklyn, NY',
                'sector'      => 'media',
                'linkedin'    => 'https://www.linkedin.com/company/journaldesfrancaisaletranger/',
                'page_url'    => $baseUrl . '/contact/',
                'language'    => 'fr',
                'scraped_at'  => now(),
            ]
        );

        Log::info('ScrapeFrancaisEtrangerJob: contact saved');
    }

    private function scrapeAuthors(ContentSource $source, string $baseUrl, ContentScraperService $scraper): void
    {
        // Fetch author sitemap
        $sitemapUrl = $baseUrl . '/author-sitemap.xml';
        $xml = $this->fetchSitemap($sitemapUrl);
        if (!$xml) return;

        $authorCount = 0;
        foreach ($xml->children() as $url) {
            $loc = (string) ($url->loc ?? '');
            if (empty($loc)) continue;

            // Extract author name from URL slug: /auteur/prenom-nom/
            if (preg_match('#/auteur/([^/]+)/?$#', $loc, $m)) {
                $slug = $m[1];
                $name = ucwords(str_replace('-', ' ', $slug));

                // Check if slug contains email pattern (rare but exists)
                $email = null;
                if (str_contains($slug, 'gmail-com') || str_contains($slug, 'yahoo') || str_contains($slug, 'hotmail')) {
                    $email = str_replace(['-at-', '-dot-'], ['@', '.'], $slug);
                    $email = preg_replace('/-com$/', '.com', $email);
                    $email = str_replace('-', '.', $email);
                }

                ContentContact::updateOrCreate(
                    ['name' => $name, 'source_id' => $source->id],
                    [
                        'role'       => 'Auteur / Journaliste',
                        'email'      => $email,
                        'company'    => 'Francais a l\'Etranger',
                        'company_url' => $baseUrl,
                        'sector'     => 'media',
                        'page_url'   => $loc,
                        'language'   => 'fr',
                        'notes'      => 'Profil auteur: ' . $loc,
                        'scraped_at' => now(),
                    ]
                );
                $authorCount++;
            }
        }

        Log::info('ScrapeFrancaisEtrangerJob: authors saved', ['count' => $authorCount]);
    }

    private function discoverFromSitemaps(string $baseUrl, string $type): array
    {
        $articles = [];
        $seen = [];

        // Try numbered sitemaps (post-sitemap.xml, post-sitemap2.xml, etc.)
        for ($i = 1; $i <= 15; $i++) {
            $suffix = $i === 1 ? '' : $i;
            $sitemapUrl = $baseUrl . "/{$type}-sitemap{$suffix}.xml";
            $xml = $this->fetchSitemap($sitemapUrl);
            if (!$xml) break;

            $count = 0;
            foreach ($xml->children() as $url) {
                $loc = (string) ($url->loc ?? '');
                if (empty($loc) || isset($seen[$loc])) continue;

                // Skip non-content URLs
                if (str_contains($loc, '/wp-') || str_contains($loc, '/tag/') || str_contains($loc, '/auteur/')) continue;
                if ($loc === $baseUrl . '/' || $loc === $baseUrl) continue;

                $seen[$loc] = true;

                $section = 'magazine';
                $category = null;

                if ($type === 'pays') {
                    $section = 'pays';
                    if (preg_match('#/pays/([^/]+)/?$#', $loc, $m)) {
                        $category = ucwords(str_replace('-', ' ', $m[1]));
                    }
                } elseif (str_contains($loc, '/destinations/') || str_contains($loc, '/vivre/')) {
                    $section = 'guide';
                    $category = 'Destinations';
                } elseif (str_contains($loc, '/travailler/') || str_contains($loc, '/emploi/')) {
                    $section = 'magazine';
                    $category = 'Emploi';
                } elseif (str_contains($loc, '/etudier/')) {
                    $section = 'magazine';
                    $category = 'Education';
                } elseif (str_contains($loc, '/sante/')) {
                    $section = 'magazine';
                    $category = 'Sante';
                } elseif (str_contains($loc, '/fiscalite/') || str_contains($loc, '/impots/')) {
                    $section = 'magazine';
                    $category = 'Fiscalite';
                } elseif (str_contains($loc, '/retraite/')) {
                    $section = 'magazine';
                    $category = 'Retraite';
                }

                $articles[] = [
                    'url'      => $loc,
                    'title'    => '',
                    'category' => $category,
                    'section'  => $section,
                ];
                $count++;
            }

            Log::info("ScrapeFrancaisEtrangerJob: sitemap parsed", [
                'type'  => $type,
                'index' => $i,
                'urls'  => $count,
            ]);
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
            return @simplexml_load_string($response->body()) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function failed(\Throwable $e): void
    {
        $source = ContentSource::find($this->sourceId);
        if ($source) $source->update(['status' => 'pending']);
        Log::error('ScrapeFrancaisEtrangerJob: job failed', ['error' => $e->getMessage()]);
    }
}
