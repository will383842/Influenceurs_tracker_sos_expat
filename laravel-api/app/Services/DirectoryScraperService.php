<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Scrapes directory/aggregator websites (AEFE, MLF, expat.com, etc.)
 * to extract individual contacts listed on them.
 *
 * Instead of blocking these sites, we USE them as data sources.
 * Each listing item becomes a new Influenceur record with its own website.
 */
class DirectoryScraperService
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    private const TIMEOUT = 20;
    private const DELAY_BETWEEN_REQUESTS_MS = 2000;

    /**
     * Directory domains we know how to extract listings from.
     * Maps domain → parser config.
     */
    private const DIRECTORY_CONFIGS = [
        'aefe.fr' => [
            'type'    => 'school',
            'name'    => 'AEFE',
            'pages'   => [
                // AEFE lists schools by country — URL pattern: /fr/etablissements?pays=COUNTRY
                // Also has a global search page
            ],
        ],
        'mlfmonde.org' => [
            'type' => 'school',
            'name' => 'MLF',
        ],
        'campusfrance.org' => [
            'type' => 'erasmus',
            'name' => 'Campus France',
        ],
        'french-schools.org' => [
            'type' => 'school',
            'name' => 'French Schools Directory',
        ],
    ];

    /**
     * Check if a URL is a known exploitable directory.
     */
    public static function isExploitableDirectory(string $url): bool
    {
        $urlLower = strtolower($url);
        foreach (array_keys(self::DIRECTORY_CONFIGS) as $domain) {
            if (str_contains($urlLower, $domain)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the directory config for a given URL.
     */
    private function getDirectoryConfig(string $url): ?array
    {
        $urlLower = strtolower($url);
        foreach (self::DIRECTORY_CONFIGS as $domain => $config) {
            if (str_contains($urlLower, $domain)) {
                return array_merge($config, ['domain' => $domain]);
            }
        }
        return null;
    }

    /**
     * Scrape a directory URL and extract individual contacts.
     *
     * @return array{contacts: array[], source: string, success: bool, error: ?string, pages_scraped: int}
     */
    public function scrapeDirectory(string $url, string $contactType, ?string $country = null): array
    {
        $result = [
            'contacts'      => [],
            'source'        => $url,
            'success'       => false,
            'error'         => null,
            'pages_scraped' => 0,
        ];

        try {
            $config = $this->getDirectoryConfig($url);

            // Fetch the main directory page
            $html = $this->fetchPage($url);
            if (!$html) {
                $result['error'] = 'Failed to fetch directory page';
                return $result;
            }
            $result['pages_scraped']++;

            // Try to find pagination and scrape additional pages
            $allHtml = [$html];
            $nextPages = $this->findPaginationLinks($html, $url);
            foreach (array_slice($nextPages, 0, 4) as $nextUrl) { // Max 4 extra pages
                usleep(self::DELAY_BETWEEN_REQUESTS_MS * 1000);
                $pageHtml = $this->fetchPage($nextUrl);
                if ($pageHtml) {
                    $allHtml[] = $pageHtml;
                    $result['pages_scraped']++;
                }
            }

            // Extract contacts from all pages
            foreach ($allHtml as $pageHtml) {
                $contacts = $this->extractContactsFromHtml($pageHtml, $url, $contactType, $country);
                $result['contacts'] = array_merge($result['contacts'], $contacts);
            }

            // Deduplicate by name
            $result['contacts'] = $this->deduplicateContacts($result['contacts']);

            $result['success'] = true;

            Log::info('DirectoryScraperService: extraction complete', [
                'url'            => $url,
                'contacts_found' => count($result['contacts']),
                'pages_scraped'  => $result['pages_scraped'],
            ]);

        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::error('DirectoryScraperService: exception', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Extract individual contacts from HTML listing page.
     * Uses DOM parsing to find repeated structures (tables, lists, cards).
     */
    private function extractContactsFromHtml(string $html, string $sourceUrl, string $contactType, ?string $country): array
    {
        $contacts = [];

        // Decode CloudFlare email protection first
        $html = $this->decodeCloudFlareEmails($html);

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);

        // Strategy 1: Table rows (common in school directories)
        $contacts = array_merge($contacts, $this->extractFromTables($xpath, $sourceUrl, $contactType, $country));

        // Strategy 2: Repeated div/article/li structures (cards, list items)
        $contacts = array_merge($contacts, $this->extractFromCards($xpath, $sourceUrl, $contactType, $country));

        // Strategy 3: Definition lists (dl/dt/dd)
        $contacts = array_merge($contacts, $this->extractFromDefinitionLists($xpath, $sourceUrl, $contactType, $country));

        return $contacts;
    }

    /**
     * Extract contacts from HTML tables.
     */
    private function extractFromTables(\DOMXPath $xpath, string $sourceUrl, string $contactType, ?string $country): array
    {
        $contacts = [];

        // Find all tables
        $tables = $xpath->query('//table');
        if ($tables->length === 0) return [];

        foreach ($tables as $table) {
            $rows = $xpath->query('.//tr', $table);
            if ($rows->length < 2) continue; // Need header + at least 1 data row

            // Try to identify column structure from header
            $headerRow = $rows->item(0);
            $headers = [];
            $headerCells = $xpath->query('.//th|.//td', $headerRow);
            foreach ($headerCells as $i => $cell) {
                $headers[$i] = strtolower(trim($cell->textContent));
            }

            // Process data rows
            for ($r = 1; $r < $rows->length; $r++) {
                $row = $rows->item($r);
                $cells = $xpath->query('.//td', $row);
                if ($cells->length < 2) continue;

                $rowText = trim($row->textContent);
                if (strlen($rowText) < 5) continue; // Skip empty/trivial rows

                $contact = $this->extractContactFromElement($row, $xpath, $sourceUrl, $contactType, $country);
                if ($contact) {
                    $contacts[] = $contact;
                }
            }
        }

        return $contacts;
    }

    /**
     * Extract contacts from repeated card/list structures.
     */
    private function extractFromCards(\DOMXPath $xpath, string $sourceUrl, string $contactType, ?string $country): array
    {
        $contacts = [];

        // Common card selectors - look for repeated structures
        $cardQueries = [
            // Articles (blog-style listings)
            '//article',
            // List items within main content
            '//main//li[.//a]',
            '//div[contains(@class,"content")]//li[.//a]',
            // Cards with links and text
            '//div[contains(@class,"card")]',
            '//div[contains(@class,"item")]',
            '//div[contains(@class,"listing")]',
            '//div[contains(@class,"result")]',
            '//div[contains(@class,"etablissement")]',
            '//div[contains(@class,"school")]',
            '//div[contains(@class,"entry")]',
            '//div[contains(@class,"member")]',
            // Sections with headings + content
            '//section[.//h2 or .//h3]',
        ];

        $processedTexts = []; // Avoid duplicates across selectors

        foreach ($cardQueries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes->length < 2) continue; // Need at least 2 items to be a listing

            // Skip if too many items (probably navigation or footer)
            if ($nodes->length > 200) continue;

            foreach ($nodes as $node) {
                $text = trim($node->textContent);

                // Skip tiny elements (nav items, buttons)
                if (strlen($text) < 15 || strlen($text) > 3000) continue;

                // Skip already processed (by content similarity)
                $textKey = substr(preg_replace('/\s+/', ' ', $text), 0, 100);
                if (isset($processedTexts[$textKey])) continue;
                $processedTexts[$textKey] = true;

                $contact = $this->extractContactFromElement($node, $xpath, $sourceUrl, $contactType, $country);
                if ($contact) {
                    $contacts[] = $contact;
                }
            }
        }

        return $contacts;
    }

    /**
     * Extract contacts from definition lists (dl > dt + dd).
     */
    private function extractFromDefinitionLists(\DOMXPath $xpath, string $sourceUrl, string $contactType, ?string $country): array
    {
        $contacts = [];

        $dts = $xpath->query('//dl/dt');
        foreach ($dts as $dt) {
            $dd = $xpath->query('following-sibling::dd[1]', $dt);
            if ($dd->length === 0) continue;

            $name = trim($dt->textContent);
            $details = trim($dd->item(0)->textContent);

            if (strlen($name) < 3 || strlen($name) > 200) continue;

            // Extract data from the dd element
            $contact = $this->extractContactFromElement($dd->item(0), $xpath, $sourceUrl, $contactType, $country);
            if ($contact) {
                $contact['name'] = $name;
                $contacts[] = $contact;
            }
        }

        return $contacts;
    }

    /**
     * Extract a single contact's data from a DOM element.
     */
    private function extractContactFromElement(\DOMNode $element, \DOMXPath $xpath, string $sourceUrl, string $contactType, ?string $country): ?array
    {
        $text = trim($element->textContent);

        // Extract name from headings or first strong/bold text
        $name = $this->extractName($element, $xpath);
        if (!$name || strlen($name) < 3) return null;

        // Skip navigation/menu items
        $nameLower = strtolower($name);
        $skipWords = ['accueil', 'menu', 'contact', 'connexion', 'login', 'recherche', 'search',
            'suivant', 'précédent', 'next', 'previous', 'voir plus', 'lire la suite', 'en savoir plus',
            'accepter', 'refuser', 'cookies', 'privacy', 'mentions légales'];
        foreach ($skipWords as $sw) {
            if ($nameLower === $sw || str_starts_with($nameLower, $sw . ' ')) return null;
        }

        // Extract website URL from links
        $websiteUrl = $this->extractWebsiteUrl($element, $xpath, $sourceUrl);

        // Extract email
        $email = $this->extractEmail($text, $element, $xpath);

        // Extract phone
        $phone = $this->extractPhone($text);

        // Need at least a name + (website or email or phone) to be useful
        if (!$websiteUrl && !$email && !$phone) return null;

        return [
            'name'         => $name,
            'email'        => $email,
            'phone'        => $phone,
            'website_url'  => $websiteUrl,
            'source'       => $sourceUrl,
            'contact_type' => $contactType,
            'country'      => $country,
        ];
    }

    /**
     * Extract a name from a DOM element (heading, bold, or first text).
     */
    private function extractName(\DOMNode $element, \DOMXPath $xpath): ?string
    {
        // Priority 1: Heading tags (h1-h6)
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
            $headings = $xpath->query(".//{$tag}", $element);
            if ($headings->length > 0) {
                $name = trim($headings->item(0)->textContent);
                if (strlen($name) >= 3 && strlen($name) <= 200) {
                    return $name;
                }
            }
        }

        // Priority 2: Strong/bold text
        $strongs = $xpath->query('.//strong|.//b', $element);
        if ($strongs->length > 0) {
            $name = trim($strongs->item(0)->textContent);
            if (strlen($name) >= 3 && strlen($name) <= 200) {
                return $name;
            }
        }

        // Priority 3: First link text (common in listings)
        $links = $xpath->query('.//a', $element);
        if ($links->length > 0) {
            $name = trim($links->item(0)->textContent);
            if (strlen($name) >= 3 && strlen($name) <= 200) {
                return $name;
            }
        }

        // Priority 4: First line of text
        $lines = preg_split('/[\r\n]+/', trim($element->textContent));
        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) >= 3 && strlen($line) <= 200) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Extract the website URL from links within an element.
     * Filters out directory/aggregator URLs — we want the REAL website.
     */
    private function extractWebsiteUrl(\DOMNode $element, \DOMXPath $xpath, string $sourceUrl): ?string
    {
        $sourceHost = parse_url($sourceUrl, PHP_URL_HOST) ?? '';

        $links = $xpath->query('.//a[@href]', $element);

        $skipDomains = [
            // Social platforms
            'youtube.com', 'tiktok.com', 'instagram.com', 'facebook.com', 'fb.com',
            'x.com', 'twitter.com', 'linkedin.com', 'threads.net', 'pinterest.com',
            // Search/encyclopedia
            'google.com', 'google.fr', 'wikipedia.org', 'bing.com',
            // Same directory (we want external links)
            $sourceHost,
        ];

        foreach ($links as $link) {
            $href = trim($link->getAttribute('href'));
            if (empty($href) || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                continue;
            }

            // Resolve relative URLs
            if (!preg_match('/^https?:\/\//i', $href)) {
                // Skip internal relative links (same directory)
                if (str_starts_with($href, '/')) continue;
                continue;
            }

            $host = strtolower(parse_url($href, PHP_URL_HOST) ?? '');
            if (empty($host)) continue;

            // Skip links pointing back to the directory itself
            $skip = false;
            foreach ($skipDomains as $sd) {
                if (str_contains($host, $sd)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            // Skip mailto/tel links
            if (str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) continue;

            // This is an external link — likely the real website
            return rtrim($href, '/');
        }

        return null;
    }

    /**
     * Extract email from text content or mailto links.
     */
    private function extractEmail(string $text, \DOMNode $element, \DOMXPath $xpath): ?string
    {
        // Check mailto links first
        $mailtoLinks = $xpath->query('.//a[starts-with(@href, "mailto:")]', $element);
        if ($mailtoLinks->length > 0) {
            $mailto = $mailtoLinks->item(0)->getAttribute('href');
            $email = trim(str_replace('mailto:', '', explode('?', $mailto)[0]));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return strtolower($email);
            }
        }

        // Regex extraction from text
        if (preg_match('/([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,12})/', $text, $m)) {
            $email = strtolower(trim($m[1]));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        // Obfuscated formats
        $patterns = [
            '/([a-zA-Z0-9._%+-]+)\s*\[at\]\s*([a-zA-Z0-9.-]+)\s*\[dot\]\s*([a-zA-Z]{2,12})/i',
            '/([a-zA-Z0-9._%+-]+)\s*\(at\)\s*([a-zA-Z0-9.-]+)\s*\(dot\)\s*([a-zA-Z]{2,12})/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $text, $m)) {
                $email = strtolower("{$m[1]}@{$m[2]}.{$m[3]}");
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $email;
                }
            }
        }

        return null;
    }

    /**
     * Extract phone number from text.
     */
    private function extractPhone(string $text): ?string
    {
        // International format: +XX XXX XXX XXXX or variations
        if (preg_match('/(\+\d{1,3}[\s.\-]?\(?\d{1,4}\)?[\s.\-]?\d{2,4}[\s.\-]?\d{2,4}[\s.\-]?\d{0,4})/', $text, $m)) {
            $phone = preg_replace('/[^\d+]/', '', $m[1]);
            if (strlen($phone) >= 8 && strlen($phone) <= 16) {
                return $m[1]; // Return formatted version
            }
        }

        // Local format: (XX) XXXX-XXXX or 0X XX XX XX XX
        if (preg_match('/(\(?\d{2,4}\)?\s*\d{3,4}[\s.\-]\d{3,4}[\s.\-]?\d{0,4})/', $text, $m)) {
            $digits = preg_replace('/\D/', '', $m[1]);
            if (strlen($digits) >= 8 && strlen($digits) <= 15) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * Find pagination links on a directory page.
     */
    private function findPaginationLinks(string $html, string $currentUrl): array
    {
        $links = [];

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);

        // Common pagination patterns
        $paginationQueries = [
            '//nav[contains(@class,"pagination")]//a[@href]',
            '//ul[contains(@class,"pagination")]//a[@href]',
            '//div[contains(@class,"pagination")]//a[@href]',
            '//div[contains(@class,"pager")]//a[@href]',
            '//a[contains(@class,"page-link")][@href]',
            '//a[contains(@rel,"next")][@href]',
        ];

        $baseUrl = rtrim(parse_url($currentUrl, PHP_URL_SCHEME) . '://' . parse_url($currentUrl, PHP_URL_HOST), '/');
        $seen = [];

        foreach ($paginationQueries as $query) {
            $nodes = $xpath->query($query);
            foreach ($nodes as $node) {
                $href = trim($node->getAttribute('href'));
                if (empty($href) || $href === '#' || str_starts_with($href, 'javascript:')) continue;

                // Resolve relative URLs
                if (!preg_match('/^https?:\/\//i', $href)) {
                    $href = $baseUrl . '/' . ltrim($href, '/');
                }

                // Skip current page
                if ($this->normalizeUrl($href) === $this->normalizeUrl($currentUrl)) continue;

                // Deduplicate
                $normalized = $this->normalizeUrl($href);
                if (isset($seen[$normalized])) continue;
                $seen[$normalized] = true;

                $links[] = $href;
            }
        }

        return $links;
    }

    /**
     * Decode CloudFlare email protection in HTML.
     */
    private function decodeCloudFlareEmails(string $html): string
    {
        // Decode data-cfemail attributes
        return preg_replace_callback(
            '/data-cfemail="([a-f0-9]+)"/i',
            function ($matches) {
                $encoded = $matches[1];
                $decoded = $this->cfDecode($encoded);
                return 'data-decoded-email="' . $decoded . '"';
            },
            $html
        );
    }

    private function cfDecode(string $encoded): string
    {
        $key = hexdec(substr($encoded, 0, 2));
        $result = '';
        for ($i = 2; $i < strlen($encoded); $i += 2) {
            $result .= chr(hexdec(substr($encoded, $i, 2)) ^ $key);
        }
        return $result;
    }

    /**
     * Fetch a page with proper headers.
     */
    private function fetchPage(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent'      => self::USER_AGENT,
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
            ])
                ->timeout(self::TIMEOUT)
                ->maxRedirects(3)
                ->get($url);

            if (!$response->successful()) return null;

            $contentType = $response->header('Content-Type') ?? '';
            if (!str_contains($contentType, 'text/html') && !str_contains($contentType, 'text/xml') && !str_contains($contentType, 'application/xhtml')) {
                return null;
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::debug('DirectoryScraperService: fetch failed', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Normalize URL for deduplication.
     */
    private function normalizeUrl(string $url): string
    {
        $url = strtolower(trim($url));
        $url = rtrim($url, '/');
        $url = preg_replace('/^https?:\/\//', '', $url);
        $url = preg_replace('/^www\./', '', $url);
        return $url;
    }

    /**
     * Deduplicate contacts by name similarity.
     */
    private function deduplicateContacts(array $contacts): array
    {
        $unique = [];
        $seenNames = [];

        foreach ($contacts as $contact) {
            $nameKey = strtolower(trim($contact['name']));
            // Normalize spaces and accents for comparison
            $nameKey = preg_replace('/\s+/', ' ', $nameKey);

            if (isset($seenNames[$nameKey])) {
                // Merge: keep whichever has more data
                $existing = &$unique[$seenNames[$nameKey]];
                if (empty($existing['email']) && !empty($contact['email'])) {
                    $existing['email'] = $contact['email'];
                }
                if (empty($existing['phone']) && !empty($contact['phone'])) {
                    $existing['phone'] = $contact['phone'];
                }
                if (empty($existing['website_url']) && !empty($contact['website_url'])) {
                    $existing['website_url'] = $contact['website_url'];
                }
                continue;
            }

            $seenNames[$nameKey] = count($unique);
            $unique[] = $contact;
        }

        return array_values($unique);
    }
}
