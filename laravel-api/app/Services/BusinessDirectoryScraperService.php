<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BusinessDirectoryScraperService
{
    private const RATE_LIMIT_SECONDS = 2;
    private const MAX_PAGE_SIZE = 5 * 1024 * 1024;
    private const USER_AGENT = 'Mozilla/5.0 (compatible; SOSExpatBot/1.0; +https://sos-expat.com)';
    private const BASE_URL = 'https://www.expat.com';

    private float $lastRequestTime = 0;

    /**
     * Discover all countries/cities available in the business directory.
     * Returns array of ['country' => ..., 'country_slug' => ..., 'continent' => ..., 'cities' => [...]]
     */
    public function discoverLocations(string $baseUrl): array
    {
        $html = $this->fetchPage($baseUrl);
        if (!$html) return [];

        $locations = [];

        // Extract from embedded JSON — same pattern as guides: absolute escaped URLs
        $unescaped = str_replace('\\/', '/', $html);

        // Match country/city links: /fr/entreprises/{continent}/{country}/{city}/
        // First get countries
        $countryPattern = '/"id"\s*:\s*"https?:\/\/[^"]*\/fr\/entreprises\/([a-z-]+)\/([a-z-]+)\/"\s*,\s*"text"\s*:\s*"([^"]+)"/';
        preg_match_all($countryPattern, $unescaped, $countryMatches, PREG_SET_ORDER);

        $countrySeen = [];
        foreach ($countryMatches as $m) {
            $continent = $m[1];
            $countrySlug = $m[2];
            $countryName = html_entity_decode($m[3], ENT_QUOTES, 'UTF-8');

            if (isset($countrySeen[$countrySlug])) continue;
            $countrySeen[$countrySlug] = true;

            $locations[] = [
                'country'      => $countryName,
                'country_slug' => $countrySlug,
                'continent'    => $this->normalizeContinent($continent),
                'url'          => self::BASE_URL . "/fr/entreprises/{$continent}/{$countrySlug}/",
                'cities'       => [],
            ];
        }

        // Also get cities from the children pattern
        $cityPattern = '/"id"\s*:\s*"https?:\/\/[^"]*\/fr\/entreprises\/([a-z-]+)\/([a-z-]+)\/([a-z-]+)\/"\s*,\s*"text"\s*:\s*"([^"]+)"/';
        preg_match_all($cityPattern, $unescaped, $cityMatches, PREG_SET_ORDER);

        foreach ($cityMatches as $m) {
            $continent = $m[1];
            $countrySlug = $m[2];
            $citySlug = $m[3];
            $cityName = html_entity_decode($m[4], ENT_QUOTES, 'UTF-8');

            // Find parent country and add city
            foreach ($locations as &$loc) {
                if ($loc['country_slug'] === $countrySlug) {
                    $loc['cities'][] = [
                        'name' => $cityName,
                        'slug' => $citySlug,
                        'url'  => self::BASE_URL . "/fr/entreprises/{$continent}/{$countrySlug}/{$citySlug}/",
                    ];
                    break;
                }
            }
            unset($loc);
        }

        // Also parse static <a> links as fallback
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $links = $xpath->query('//a[contains(@href, "/fr/entreprises/")]');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $text = trim($link->textContent);

            if (preg_match('#/fr/entreprises/([a-z-]+)/([a-z-]+)/?$#', $href, $m)) {
                $countrySlug = $m[2];
                if (!isset($countrySeen[$countrySlug])) {
                    $countrySeen[$countrySlug] = true;
                    $locations[] = [
                        'country'      => $text ?: ucfirst(str_replace('-', ' ', $countrySlug)),
                        'country_slug' => $countrySlug,
                        'continent'    => $this->normalizeContinent($m[1]),
                        'url'          => $this->resolveUrl($href),
                        'cities'       => [],
                    ];
                }
            }
        }
        unset($xpath, $dom);

        Log::info('BusinessScraper: discovered locations', ['countries' => count($locations)]);
        return $locations;
    }

    /**
     * Discover all categories from a city page.
     * Parses `var categories = [...]` JSON.
     */
    public function discoverCategories(string $pageUrl): array
    {
        $html = $this->fetchPage($pageUrl);
        if (!$html) return [];

        return $this->extractCategoriesFromJs($html);
    }

    /**
     * Scrape businesses from a listing page.
     * Parses `var businesses = [...]` JSON embedded in the page.
     */
    public function scrapeListingPage(string $pageUrl): array
    {
        $html = $this->fetchPage($pageUrl);
        if (!$html) return [];

        $businesses = [];

        // Extract var businesses = [...] JSON
        if (preg_match('/var\s+businesses\s*=\s*(\[[\s\S]*?\])\s*;/', $html, $m)) {
            $json = $this->cleanJsJson($m[1]);
            $data = @json_decode($json, true);

            if (is_array($data)) {
                foreach ($data as $biz) {
                    $businesses[] = [
                        'external_id'      => $biz['id'] ?? null,
                        'name'             => $biz['name'] ?? '',
                        'contact_name'     => $biz['contactName'] ?? null,
                        'contact_phone'    => $biz['contactNumber'] ?? null,
                        'address'          => $biz['address'] ?? null,
                        'logo_url'         => $biz['logo'] ?? null,
                        'url'              => isset($biz['link']) ? self::BASE_URL . $biz['link'] : null,
                        'website_redirect' => $biz['ctaLink'] ?? null,
                        'is_premium'       => !($biz['isFree'] ?? true),
                        'recommendations'  => (int) ($biz['likes'] ?? 0),
                        'category'         => $biz['category']['text'] ?? null,
                        'category_url'     => $biz['category']['url'] ?? null,
                    ];
                }
            }
        }

        Log::info('BusinessScraper: scraped listing', [
            'url'   => $pageUrl,
            'count' => count($businesses),
        ]);

        return $businesses;
    }

    /**
     * Scrape a single business detail page for email, website, description, etc.
     * Parses `var businessItem = {...}` and JSON-LD.
     */
    public function scrapeBusinessDetail(string $pageUrl): ?array
    {
        $html = $this->fetchPage($pageUrl);
        if (!$html) return null;

        $data = [];

        // Extract var businessItem = {...}
        if (preg_match('/var\s+businessItem\s*=\s*(\{[\s\S]*?\})\s*;/', $html, $m)) {
            $json = $this->cleanJsJson($m[1]);
            $biz = @json_decode($json, true);

            if (is_array($biz)) {
                $qi = $biz['quickInfo'] ?? [];
                $data = [
                    'contact_email'  => $qi['contactEmail'] ?? null,
                    'contact_phone'  => $qi['contactNumber'] ?? null,
                    'contact_name'   => $qi['contactName'] ?? null,
                    'website'        => $qi['website'] ?? null,
                    'description'    => $this->extractDescription($biz),
                    'is_premium'     => $biz['isPremium'] ?? false,
                    'views'          => $biz['views'] ?? 0,
                    'recommendations' => (int) ($biz['recommendation'] ?? 0),
                    'images'         => $this->extractImages($biz),
                    'opening_hours'  => $biz['openingHours'] ?? null,
                    'logo_url'       => $biz['logoUrl'] ?? null,
                ];
            }
        }

        // Extract GPS + schema type from JSON-LD
        if (preg_match('/<script\s+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $ldMatch)) {
            $ld = @json_decode(trim($ldMatch[1]), true);
            if (is_array($ld)) {
                $data['schema_type'] = $ld['@type'] ?? null;
                $data['latitude'] = $ld['geo']['latitude'] ?? null;
                $data['longitude'] = $ld['geo']['longitude'] ?? null;
                if (!isset($data['contact_phone']) && isset($ld['telephone'])) {
                    $data['contact_phone'] = $ld['telephone'];
                }
                if (empty($data['address']) && isset($ld['address'])) {
                    $addr = $ld['address'];
                    $data['address'] = trim(
                        ($addr['streetAddress'] ?? '') . ', ' . ($addr['addressLocality'] ?? ''),
                        ', '
                    );
                }
            }
        }

        // Extract description from meta if not found
        if (empty($data['description'])) {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            libxml_clear_errors();
            $xpath = new \DOMXPath($dom);
            $descNode = $xpath->query('//meta[@property="og:description"]')->item(0);
            if ($descNode) {
                $data['description'] = $descNode->getAttribute('content');
            }
            unset($xpath, $dom);
        }

        return $data;
    }

    /**
     * Rate-limited sleep.
     */
    public function rateLimitSleep(): void
    {
        $elapsed = microtime(true) - $this->lastRequestTime;
        if ($elapsed < self::RATE_LIMIT_SECONDS) {
            usleep((int) ((self::RATE_LIMIT_SECONDS - $elapsed) * 1_000_000));
        }
    }

    // ──── Private helpers ──────────────────────────────────────

    private function fetchPage(string $url): ?string
    {
        if (!$this->isAllowedUrl($url)) {
            Log::warning('BusinessScraper: blocked URL', ['url' => $url]);
            return null;
        }

        $elapsed = microtime(true) - $this->lastRequestTime;
        if ($elapsed < self::RATE_LIMIT_SECONDS) {
            usleep((int) ((self::RATE_LIMIT_SECONDS - $elapsed) * 1_000_000));
        }
        $this->lastRequestTime = microtime(true);

        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('BusinessScraper: HTTP error', ['url' => $url, 'status' => $response->status()]);
                return null;
            }

            $body = $response->body();
            if (strlen($body) > self::MAX_PAGE_SIZE) return null;

            return $body;
        } catch (\Throwable $e) {
            Log::error('BusinessScraper: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function isAllowedUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return false;
        $ip = gethostbyname($host);
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) return false;
        return filter_var($ip, FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function resolveUrl(string $href): string
    {
        if (str_starts_with($href, 'http')) return $href;
        return self::BASE_URL . $href;
    }

    /**
     * Clean JavaScript object/array to valid JSON (remove trailing commas, single quotes, etc.)
     */
    private function cleanJsJson(string $js): string
    {
        // Remove JS comments
        $js = preg_replace('/\/\/[^\n]*/', '', $js);
        // Remove trailing commas before } or ]
        $js = preg_replace('/,\s*([\}\]])/', '$1', $js);
        return $js;
    }

    private function extractCategoriesFromJs(string $html): array
    {
        $categories = [];

        if (preg_match('/var\s+categories\s*=\s*(\[[\s\S]*?\])\s*;/', $html, $m)) {
            $json = $this->cleanJsJson($m[1]);
            $data = @json_decode($json, true);

            if (is_array($data)) {
                foreach ($data as $cat) {
                    $catEntry = [
                        'label' => $cat['label'] ?? '',
                        'url'   => isset($cat['url']) ? self::BASE_URL . $cat['url'] : null,
                        'count' => $cat['count'] ?? 0,
                        'children' => [],
                    ];

                    // Extract category ID from URL
                    if (preg_match('/(\d+)_([a-z-]+)\/?$/', $cat['url'] ?? '', $idMatch)) {
                        $catEntry['id'] = (int) $idMatch[1];
                        $catEntry['slug'] = $idMatch[2];
                    }

                    foreach ($cat['children'] ?? [] as $sub) {
                        $subEntry = [
                            'label' => $sub['label'] ?? '',
                            'url'   => isset($sub['url']) ? self::BASE_URL . $sub['url'] : null,
                            'count' => $sub['count'] ?? 0,
                        ];
                        if (preg_match('/(\d+)_([a-z-]+)\/?$/', $sub['url'] ?? '', $subIdMatch)) {
                            $subEntry['id'] = (int) $subIdMatch[1];
                            $subEntry['slug'] = $subIdMatch[2];
                        }
                        $catEntry['children'][] = $subEntry;
                    }

                    $categories[] = $catEntry;
                }
            }
        }

        return $categories;
    }

    private function extractDescription(array $biz): ?string
    {
        // Description can be in translations or direct fields
        $translations = $biz['translations'] ?? [];
        foreach ($translations as $t) {
            if (!empty($t['description'])) {
                return strip_tags($t['description']);
            }
        }
        return $biz['description'] ?? null;
    }

    private function extractImages(array $biz): array
    {
        $images = [];
        foreach ($biz['images'] ?? [] as $img) {
            if (isset($img['url'])) {
                $images[] = ['url' => $img['url'], 'alt' => $img['alt'] ?? ''];
            }
        }
        return $images;
    }

    private function normalizeContinent(string $slug): string
    {
        return match ($slug) {
            'afrique'            => 'Afrique',
            'amerique-du-nord'   => 'Amerique du Nord',
            'amerique-du-sud'    => 'Amerique du Sud',
            'amerique-centrale'  => 'Amerique Centrale',
            'asie'               => 'Asie',
            'europe'             => 'Europe',
            'moyen-orient'       => 'Moyen-Orient',
            'oceanie'            => 'Oceanie',
            default              => ucfirst(str_replace('-', ' ', $slug)),
        };
    }
}
