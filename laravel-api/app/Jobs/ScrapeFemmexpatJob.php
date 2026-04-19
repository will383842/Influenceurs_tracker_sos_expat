<?php

namespace App\Jobs;

use App\Models\ContentArticle;
use App\Models\ContentContact;
use App\Models\ContentExternalLink;
use App\Models\ContentSource;
use App\Services\ContentScraperService;
use App\Services\CountryLanguageMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeFemmexpatJob implements ShouldQueue
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
            (new WithoutOverlapping('scrape-femmexpat-' . $this->sourceId))
                ->releaseAfter(14400)
                ->expireAfter(14400),
        ];
    }

    public function handle(ContentScraperService $scraper): void
    {
        $source = ContentSource::find($this->sourceId);
        if (!$source) return;

        $source->update(['status' => 'scraping']);
        Log::info('ScrapeFemmexpatJob: starting', ['source' => $source->slug]);

        try {
            // Step 1: Scrape contacts from /nous-contacter/
            $this->scrapeContacts($source, $scraper);

            // Step 2: Scrape partners from various pages
            $this->scrapePartners($source, $scraper);

            // Step 3: Discover all articles from sitemaps
            $articleUrls = $this->discoverFromSitemaps($source);

            Log::info('ScrapeFemmexpatJob: discovered articles from sitemaps', [
                'count' => count($articleUrls),
            ]);

            // Pre-load existing
            $existingUrls = ContentArticle::where('source_id', $source->id)
                ->pluck('url')
                ->flip()
                ->toArray();

            $existingLinkHashes = ContentExternalLink::where('source_id', $source->id)
                ->pluck('url_hash')
                ->flip()
                ->toArray();

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
                        if ($consecutiveFailures >= 20) break;
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
                        'is_guide'         => str_contains($articleData['url'], '/destination'),
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
                                'language'     => 'fr',
                            ]);
                            $existingLinkHashes[$linkHash] = true;
                        }
                    }

                    $scrapedCount++;
                    $consecutiveFailures = 0;

                    if ($scrapedCount % 50 === 0) {
                        gc_collect_cycles();
                        Log::info('ScrapeFemmexpatJob: progress', [
                            'scraped' => $scrapedCount,
                            'skipped' => $skippedCount,
                        ]);
                    }

                } catch (\Throwable $e) {
                    $consecutiveFailures++;
                    Log::warning('ScrapeFemmexpatJob: article failed', [
                        'url'   => $articleData['url'],
                        'error' => $e->getMessage(),
                    ]);
                    if ($consecutiveFailures >= 20) break;
                }
            }

            $source->update([
                'status'          => 'completed',
                'total_articles'  => $source->articles()->count(),
                'total_links'     => $source->externalLinks()->count(),
                'last_scraped_at' => now(),
            ]);

            Log::info('ScrapeFemmexpatJob: completed', [
                'scraped' => $scrapedCount,
                'skipped' => $skippedCount,
            ]);

        } catch (\Throwable $e) {
            Log::error('ScrapeFemmexpatJob: failed', ['error' => $e->getMessage()]);
            $source->update(['status' => 'pending']);
        }
    }

    /**
     * Scrape contact info from /nous-contacter/ page.
     */
    private function scrapeContacts(ContentSource $source, ContentScraperService $scraper): void
    {
        $baseUrl = rtrim($source->base_url, '/');
        $html = $this->fetchPage($baseUrl . '/nous-contacter/');
        if (!$html) return;

        // Known contacts from femmexpat.com (structured data from the contact page)
        $contacts = [
            ['name' => 'Stephanie Merlant', 'role' => 'Directrice associee / Annonceurs', 'email' => 'stephanie.merlant@expatcommunication.com', 'phone' => '01 42 36 91 91', 'sector' => 'media'],
            ['name' => 'Nathalie Buet', 'role' => 'Equipe editoriale', 'email' => 'nathalie.buet@expatcommunication.com', 'phone' => '01 42 36 91 91', 'sector' => 'media'],
            ['name' => 'Alix Carnot', 'role' => 'Relations presse', 'email' => 'alix.carnot@femmexpat.com', 'phone' => '01 42 36 91 91', 'sector' => 'media'],
            ['name' => 'Beatrice Jullien', 'role' => 'Entreprises en expatriation', 'email' => 'beatrice.jullien@expatcommunication.com', 'phone' => '01 42 36 91 91', 'sector' => 'media'],
            ['name' => 'Aurane Manceau', 'role' => 'Account Manager (regie pub)', 'email' => 'aurane.manceau@expatcommunication.com', 'sector' => 'media'],
            ['name' => 'Jean Poulin', 'role' => 'Business Developer (regie pub)', 'email' => 'jean.poulin@expatcommunication.com', 'sector' => 'media'],
            ['name' => 'Julie Dechet', 'role' => 'Club Premium', 'email' => 'julie.dechet@expatcommunication.com', 'sector' => 'media'],
        ];

        // Also try to extract emails dynamically from the page
        if (preg_match_all('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $html, $emailMatches)) {
            $foundEmails = array_unique($emailMatches[1]);
            foreach ($foundEmails as $email) {
                $email = strtolower(trim($email));
                // Skip known non-person emails
                if (in_array($email, ['contact@expatcommunication.com', 'recrutement@expatcommunication.com', 'digital@expatcommunication.com'])) continue;
                // Check if already in our list
                $already = false;
                foreach ($contacts as $c) {
                    if (strtolower($c['email']) === $email) { $already = true; break; }
                }
                if (!$already) {
                    $contacts[] = [
                        'name' => ucwords(str_replace(['.', '@expatcommunication.com', '@femmexpat.com', '@gmail.com'], [' ', '', '', ''], explode('@', $email)[0])),
                        'role' => 'Contributeur',
                        'email' => $email,
                        'sector' => 'media',
                    ];
                }
            }
        }

        foreach ($contacts as $contact) {
            ContentContact::updateOrCreate(
                ['email' => $contact['email'], 'source_id' => $source->id],
                [
                    'name'        => $contact['name'],
                    'role'        => $contact['role'] ?? null,
                    'phone'       => $contact['phone'] ?? null,
                    'company'     => 'Expat Communication / FemmExpat',
                    'company_url' => 'https://www.femmexpat.com',
                    'country'     => 'France',
                    'city'        => 'Paris',
                    'address'     => '6 rue d\'Uzes, 75002 Paris',
                    'sector'      => $contact['sector'] ?? 'media',
                    'page_url'    => $baseUrl . '/nous-contacter/',
                    'language'    => app(CountryLanguageMapper::class)->resolveLanguage('France'),
                    'scraped_at'  => now(),
                ]
            );
        }

        Log::info('ScrapeFemmexpatJob: contacts saved', ['count' => count($contacts)]);
    }

    /**
     * Scrape partner/sponsor info from pages.
     */
    private function scrapePartners(ContentSource $source, ContentScraperService $scraper): void
    {
        $baseUrl = rtrim($source->base_url, '/');

        // Known partners from femmexpat.com
        $partners = [
            ['name' => 'Henner', 'sector' => 'assurance', 'company_url' => 'https://www.henner.com'],
            ['name' => 'International Sante', 'sector' => 'assurance', 'company_url' => 'https://www.international-sante.com'],
            ['name' => 'Freedom Portage', 'sector' => 'emploi', 'company_url' => 'https://www.freedomportage.com'],
            ['name' => 'Eutelmed', 'sector' => 'sante', 'company_url' => 'https://www.eutelmed.com'],
            ['name' => 'Ecole Jeannine Manuel', 'sector' => 'education', 'company_url' => 'https://www.ejm.net'],
            ['name' => 'CNED', 'sector' => 'education', 'company_url' => 'https://www.cned.fr'],
            ['name' => 'CFE', 'sector' => 'social', 'company_url' => 'https://www.cfe.fr'],
            ['name' => 'ZappTax', 'sector' => 'fiscalite', 'company_url' => 'https://www.zapptax.com'],
            ['name' => 'CEI', 'sector' => 'education', 'company_url' => 'https://www.cei.fr'],
            ['name' => 'IFS Singapore', 'sector' => 'education', 'company_url' => 'https://www.ifs.edu.sg'],
        ];

        foreach ($partners as $partner) {
            ContentContact::updateOrCreate(
                ['company' => $partner['name'], 'source_id' => $source->id],
                [
                    'name'        => $partner['name'],
                    'role'        => 'Partenaire FemmExpat',
                    'company'     => $partner['name'],
                    'company_url' => $partner['company_url'] ?? null,
                    'sector'      => $partner['sector'],
                    'page_url'    => $baseUrl . '/regie-publicitaire-femmexpat/',
                    'language'    => 'fr',
                    'scraped_at'  => now(),
                ]
            );
        }

        Log::info('ScrapeFemmexpatJob: partners saved', ['count' => count($partners)]);
    }

    /**
     * Discover all article URLs from WordPress sitemaps.
     */
    private function discoverFromSitemaps(ContentSource $source): array
    {
        $articles = [];
        $seen = [];
        $baseUrl = rtrim($source->base_url, '/');

        // WordPress sitemap index
        $indexUrl = $baseUrl . '/sitemap_index.xml';
        $indexXml = $this->fetchSitemap($indexUrl);
        if (!$indexXml) {
            // Try direct sitemaps
            $indexXml = $this->fetchSitemap($baseUrl . '/sitemap.xml');
        }

        if (!$indexXml) {
            Log::warning('ScrapeFemmexpatJob: no sitemap found');
            return [];
        }

        // Collect all sub-sitemap URLs
        $sitemapUrls = [];
        foreach ($indexXml->children() as $child) {
            $loc = (string) ($child->loc ?? '');
            if ($loc && str_contains($loc, 'post-sitemap')) {
                $sitemapUrls[] = $loc;
            }
            // Also grab category and event sitemaps
            if ($loc && (str_contains($loc, 'category-sitemap') || str_contains($loc, 'event-sitemap'))) {
                // Skip these - we only want actual articles
            }
        }

        // If no sub-sitemaps found, the main sitemap might contain URLs directly
        if (empty($sitemapUrls)) {
            $sitemapUrls[] = $indexUrl;
        }

        Log::info('ScrapeFemmexpatJob: found sitemaps', ['count' => count($sitemapUrls)]);

        // Parse each sitemap for article URLs
        foreach ($sitemapUrls as $sitemapUrl) {
            $xml = $this->fetchSitemap($sitemapUrl);
            if (!$xml) continue;

            foreach ($xml->children() as $url) {
                $loc = (string) ($url->loc ?? '');
                if (empty($loc)) continue;

                // Skip non-article URLs
                if (str_contains($loc, '/wp-') || str_contains($loc, '/tag/') || str_contains($loc, '/author/')) continue;
                if ($loc === $baseUrl . '/' || $loc === $baseUrl) continue;

                if (isset($seen[$loc])) continue;
                $seen[$loc] = true;

                // Determine section and category from URL
                $section = 'guide';
                $category = null;

                if (str_contains($loc, '/destination')) {
                    $section = 'guide';
                    $category = $this->extractCountryFromUrl($loc);
                } elseif (str_contains($loc, '/vie-pro/') || str_contains($loc, '/entrepreneuriat/')) {
                    $section = 'magazine';
                    $category = 'Vie professionnelle';
                } elseif (str_contains($loc, '/expatriation/')) {
                    $section = 'magazine';
                    $category = 'Expatriation';
                } elseif (str_contains($loc, '/dossiers/')) {
                    $section = 'magazine';
                    $category = 'Dossiers';
                } elseif (str_contains($loc, '/fiche-pratique/')) {
                    $section = 'guide';
                    $category = 'Fiche pratique';
                } elseif (str_contains($loc, '/sante/') || str_contains($loc, '/psycho/')) {
                    $section = 'magazine';
                    $category = 'Sante';
                } elseif (str_contains($loc, '/education/') || str_contains($loc, '/scolarite/')) {
                    $section = 'magazine';
                    $category = 'Education';
                } else {
                    $section = 'magazine';
                }

                $articles[] = [
                    'url'      => $loc,
                    'title'    => '',
                    'category' => $category,
                    'section'  => $section,
                ];
            }
        }

        return $articles;
    }

    private function extractCountryFromUrl(string $url): ?string
    {
        // /destination-2/europe/france/ → France
        if (preg_match('#/destination[^/]*/[^/]+/([^/]+)#', $url, $m)) {
            return ucfirst(str_replace('-', ' ', $m[1]));
        }
        return null;
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

    private function fetchPage(string $url): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SOSExpatBot/1.0)'])
                ->get($url);
            return $response->successful() ? $response->body() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function failed(\Throwable $e): void
    {
        $source = ContentSource::find($this->sourceId);
        if ($source) $source->update(['status' => 'pending']);
        Log::error('ScrapeFemmexpatJob: job failed', ['error' => $e->getMessage()]);
    }
}
