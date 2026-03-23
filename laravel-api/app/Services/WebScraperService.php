<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebScraperService
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    private const TIMEOUT = 15;
    private const MAX_REDIRECTS = 3;
    private const MAX_PAGES = 8;
    private const DELAY_BETWEEN_REQUESTS_MS = 2000;

    /**
     * Contact/about page paths to try after the main page.
     */
    private const CONTACT_PATHS = [
        '/contact',
        '/contact-us',
        '/contact.html',
        '/contact.php',
        '/about',
        '/about-us',
        '/about.html',
        '/a-propos',
        '/nous-contacter',
        '/contactez-nous',
        '/impressum',
        '/kontakt',
        '/info',
        '/information',
        '/team',
        '/staff',
        '/our-team',
        '/notre-equipe',
        '/equipe',
        '/direction',
        '/administration',
        '/coordonnees',
        '/footer',
    ];

    /**
     * Social media domains we should never scrape (handled differently).
     */
    private const SKIP_DOMAINS = [
        // Social platforms
        'youtube.com', 'youtu.be',
        'tiktok.com',
        'instagram.com',
        'facebook.com', 'fb.com',
        'x.com', 'twitter.com',
        'linkedin.com',
        'threads.net',
        'snapchat.com',
        'pinterest.com',

        // News/aggregator sites (scraping these gives OTHER people's emails)
        'lepetitjournal.com',
        'lefigaro.fr',
        'lemonde.fr',
        'france24.com',
        'rfi.fr',
        'bfmtv.com',
        'leparisien.fr',
        'liberation.fr',
        'huffingtonpost.fr',
        'ouest-france.fr',
        'nouvelobs.com',
        'lepoint.fr',
        'lexpress.fr',
        'courrierinternational.com',
        'tv5monde.com',

        // Search engines & encyclopedias
        'google.com', 'google.fr', 'google.de', 'google.co.uk', 'google.es',
        'bing.com',
        'wikipedia.org',
        'wikidata.org',
        'wikimedia.org',

        // School/education directories & government (NOT individual schools)
        'aefe.fr',
        'aefe.gouv.fr',
        'mlfmonde.org',
        'education.gouv.fr',
        'enseignementsup-recherche.gouv.fr',
        'onisep.fr',
        'letudiant.fr',
        'studyrama.com',
        'campusfrance.org',
        'odyssey.education',
        'french-schools.org',
        'efep.education',
        'diplomatie.gouv.fr',
        'service-public.fr',
        'data.gouv.fr',

        // Expat directories & forums (list sites, not individual orgs)
        'expat.com',
        'expatries.org',
        'internations.org',
        'femmexpat.com',
        'expatfocus.com',
        'expatica.com',
        'justlanded.com',
        'angloinfo.com',
        'numbeo.com',
        'livingcost.org',
        'forumvietnam.fr',

        // Review/listing aggregators
        'tripadvisor.com', 'tripadvisor.fr',
        'yelp.com', 'yelp.fr',
        'pagesjaunes.fr',
        'yellowpages.com',
        'trustpilot.com',
        'glassdoor.com', 'glassdoor.fr',
        'indeed.com', 'indeed.fr',
        'kompass.com',
        'societe.com',
        'europages.fr',
        'mappy.com',
        'cylex.fr',

        // General purpose / e-commerce / other
        'amazon.com', 'amazon.fr',
        'booking.com',
        'airbnb.com', 'airbnb.fr',
    ];

    /**
     * Binary/non-HTML content types to skip.
     */
    private const SKIP_CONTENT_TYPES = [
        'image/', 'video/', 'audio/', 'application/pdf',
        'application/zip', 'application/octet-stream',
    ];

    /**
     * Scrape a URL and extract contact information.
     *
     * @return array{emails: string[], phones: string[], social_links: array, addresses: string[], contact_persons: string[], scraped_pages: string[], success: bool, error?: string}
     */
    public function scrape(string $url): array
    {
        $result = [
            'emails'           => [],
            'phones'           => [],
            'social_links'     => [],
            'addresses'        => [],
            'contact_persons'  => [],
            'linked_contacts'  => [],  // name ↔ email ↔ phone ↔ role associations
            'suggested_emails' => [],  // Guessed from domain MX when scraping finds nothing
            'detected_language' => null, // Detected from HTML lang attr + content analysis
            'scraped_pages'    => [],
            'success'          => false,
            'error'            => null,
        ];

        try {
            $parsed = parse_url($url);
            if (!$parsed || empty($parsed['host'])) {
                $result['error'] = 'Invalid URL';
                return $result;
            }

            $domain = strtolower($parsed['host']);

            // Skip social media platforms
            foreach (self::SKIP_DOMAINS as $skipDomain) {
                if (str_contains($domain, $skipDomain)) {
                    $result['error'] = "Skipped social platform: {$skipDomain}";
                    return $result;
                }
            }

            // Normalize base URL
            $scheme = $parsed['scheme'] ?? 'https';
            $baseUrl = "{$scheme}://{$domain}";

            // Check robots.txt
            if (!$this->isAllowedByRobotsTxt($baseUrl)) {
                $result['error'] = 'Blocked by robots.txt';
                return $result;
            }

            // 1. Scrape the main page (with HTTPS→HTTP fallback)
            $mainHtml = $this->fetchPageWithFallback($url, $baseUrl);
            if ($mainHtml !== null) {
                $result['scraped_pages'][] = $url;
                $this->extractFromHtml($mainHtml, $result);
            }

            // Also try the URL without /fr/ or /en/ prefix if present
            $altUrl = $this->stripLocalePrefix($url);
            if ($altUrl !== null && $this->normalizeUrl($altUrl) !== $this->normalizeUrl($url)) {
                $altHtml = $this->fetchPageWithFallback($altUrl, $baseUrl);
                if ($altHtml !== null && count($result['scraped_pages']) < self::MAX_PAGES) {
                    $result['scraped_pages'][] = $altUrl;
                    $this->extractFromHtml($altHtml, $result);
                }
            }

            // 2. Discover contact pages from links found in main page HTML
            $discoveredPages = [];
            if ($mainHtml !== null) {
                $discoveredPages = $this->discoverContactPages($mainHtml, $baseUrl);
            }

            // Track already-scraped normalized URLs to avoid duplicates
            $scrapedNormalized = array_map([$this, 'normalizeUrl'], $result['scraped_pages']);

            // 3. Try discovered pages FIRST (more likely to be real contact pages)
            foreach ($discoveredPages as $pageUrl) {
                if (count($result['scraped_pages']) >= self::MAX_PAGES) {
                    break;
                }

                $normalized = $this->normalizeUrl($pageUrl);
                if (in_array($normalized, $scrapedNormalized, true)) {
                    continue;
                }

                usleep(self::DELAY_BETWEEN_REQUESTS_MS * 1000);

                $html = $this->fetchPage($pageUrl);
                if ($html !== null) {
                    $result['scraped_pages'][] = $pageUrl;
                    $scrapedNormalized[] = $normalized;
                    $this->extractFromHtml($html, $result);
                }
            }

            // 4. Try hardcoded contact/about pages (up to MAX_PAGES total)
            foreach (self::CONTACT_PATHS as $path) {
                if (count($result['scraped_pages']) >= self::MAX_PAGES) {
                    break;
                }

                $pageUrl = rtrim($baseUrl, '/') . $path;

                $normalized = $this->normalizeUrl($pageUrl);
                if (in_array($normalized, $scrapedNormalized, true)) {
                    continue;
                }

                // Rate limit between requests
                usleep(self::DELAY_BETWEEN_REQUESTS_MS * 1000);

                $html = $this->fetchPage($pageUrl);
                if ($html !== null) {
                    $result['scraped_pages'][] = $pageUrl;
                    $scrapedNormalized[] = $normalized;
                    $this->extractFromHtml($html, $result);
                }
            }

            $result['success'] = true;

        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::warning('WebScraper: unexpected error', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
        }

        // ALWAYS deduplicate + clean (even after catch)
        $result['emails'] = $this->cleanAndDeduplicateEmails($result['emails']);
        $result['phones'] = array_values(array_unique($result['phones']));
        $result['social_links'] = $this->deduplicateSocialLinks($result['social_links']);
        $result['addresses'] = array_values(array_unique($result['addresses']));
        $result['contact_persons'] = array_values(array_unique($result['contact_persons']));

        // Deduplicate linked contacts by email
        $seenLinked = [];
        $uniqueLinked = [];
        foreach ($result['linked_contacts'] as $lc) {
            $key = $lc['email'] ?? $lc['phone'] ?? $lc['name'] ?? '';
            if (empty($key) || isset($seenLinked[$key])) continue;
            $seenLinked[$key] = true;
            $uniqueLinked[] = $lc;
        }
        $result['linked_contacts'] = $uniqueLinked;

        // If no emails found by scraping, try to guess common emails for the domain
        if (empty($result['emails'])) {
            $result['suggested_emails'] = $this->guessEmailsForDomain($url);
        }

        return $result;
    }

    /**
     * Clean and deduplicate emails — removes u003e prefix, trims, lowercases, deduplicates.
     */
    private function cleanAndDeduplicateEmails(array $emails): array
    {
        $cleaned = [];
        $seen = [];

        foreach ($emails as $email) {
            // Fix unicode escapes (u003e = ">", common in JSON-in-HTML)
            $email = preg_replace('/u003[ce]/i', '', $email);
            $email = strtolower(trim($email, " \t\n\r\0\x0B<>"));

            // Re-validate after cleaning
            if (!$this->isValidEmail($email)) continue;

            // Skip if already seen
            if (isset($seen[$email])) continue;
            $seen[$email] = true;

            $cleaned[] = $email;
        }

        return $cleaned;
    }

    /**
     * Extract linked contacts: associate names with nearby emails/phones/roles.
     * Analyzes proximity in the text to link data that belongs together.
     */
    private function extractLinkedContacts(string $text, array &$linkedContacts): void
    {
        try {
            $text = preg_replace('/\s+/', ' ', $text);

            // Split text into chunks of ~300 chars around email addresses
            $emailPattern = '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/';
            if (!preg_match_all($emailPattern, $text, $emailMatches, PREG_OFFSET_CAPTURE)) {
                return;
            }

            $namePattern = '/([A-ZÀ-ÖØ-Þ][a-zà-öø-ÿ]+(?:[\-\s][A-ZÀ-ÖØ-Þ][a-zà-öø-ÿ]+){1,3})/u';
            $phonePattern = '/(\+?\d[\d\s.\-()]{7,18}\d)/';
            $rolePatterns = [
                'director' => 'Directeur',
                'directeur' => 'Directeur', 'directrice' => 'Directrice',
                'principal' => 'Principal',
                'president' => 'Président', 'présidente' => 'Présidente',
                'responsable' => 'Responsable',
                'manager' => 'Manager',
                'founder' => 'Fondateur', 'fondateur' => 'Fondateur', 'fondatrice' => 'Fondatrice',
                'secretary' => 'Secrétaire', 'secrétaire' => 'Secrétaire',
                'admissions' => 'Admissions',
                'coordinator' => 'Coordinateur', 'coordinateur' => 'Coordinateur',
                'contact' => 'Contact',
                'head' => 'Responsable',
                'chef' => 'Chef',
            ];

            $seenEmails = [];

            foreach ($emailMatches[0] as [$email, $offset]) {
                $email = strtolower($email);
                if (!$this->isValidEmail($email) || isset($seenEmails[$email])) continue;
                $seenEmails[$email] = true;

                // Get context: 200 chars before and after the email
                $start = max(0, $offset - 200);
                $length = min(strlen($text) - $start, 400 + strlen($email));
                $context = substr($text, $start, $length);

                // Find name in context
                $name = null;
                if (preg_match($namePattern, $context, $nameMatch)) {
                    $candidateName = trim($nameMatch[1]);
                    // Validate: at least 2 words, not too long
                    if (mb_strlen($candidateName) >= 5 && mb_strlen($candidateName) <= 60 && str_contains($candidateName, ' ')) {
                        $name = $candidateName;
                    }
                }

                // Find phone in context
                $phone = null;
                if (preg_match($phonePattern, $context, $phoneMatch)) {
                    $candidatePhone = trim($phoneMatch[1]);
                    $digits = preg_replace('/\D/', '', $candidatePhone);
                    if (strlen($digits) >= 8 && strlen($digits) <= 15) {
                        $phone = $candidatePhone;
                    }
                }

                // Find role in context
                $role = null;
                $contextLower = strtolower($context);
                foreach ($rolePatterns as $keyword => $label) {
                    if (str_contains($contextLower, $keyword)) {
                        $role = $label;
                        break;
                    }
                }

                $linkedContacts[] = [
                    'name'  => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'role'  => $role,
                ];
            }

            // Also add phones that weren't near any email
            if (preg_match_all($phonePattern, $text, $phoneMatches, PREG_OFFSET_CAPTURE)) {
                $linkedPhones = array_column(array_column($linkedContacts, 'phone'), null);
                foreach ($phoneMatches[0] as [$phone, $offset]) {
                    $phone = trim($phone);
                    $digits = preg_replace('/\D/', '', $phone);
                    if (strlen($digits) < 8 || strlen($digits) > 15) continue;

                    // Check if already linked
                    $alreadyLinked = false;
                    foreach ($linkedContacts as $lc) {
                        if ($lc['phone'] === $phone) { $alreadyLinked = true; break; }
                    }
                    if ($alreadyLinked) continue;

                    // Get context around this phone
                    $start = max(0, $offset - 150);
                    $context = substr($text, $start, 300 + strlen($phone));

                    $name = null;
                    if (preg_match($namePattern, $context, $nameMatch)) {
                        $candidateName = trim($nameMatch[1]);
                        if (mb_strlen($candidateName) >= 5 && mb_strlen($candidateName) <= 60 && str_contains($candidateName, ' ')) {
                            $name = $candidateName;
                        }
                    }

                    $linkedContacts[] = [
                        'name'  => $name,
                        'email' => null,
                        'phone' => $phone,
                        'role'  => null,
                    ];
                }
            }

        } catch (\Throwable $e) {
            Log::debug('WebScraper: linked contacts extraction failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Fetch a single page, returning the HTML body or null on failure.
     */
    private function fetchPage(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                    'User-Agent'      => self::USER_AGENT,
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9,fr;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Cache-Control'   => 'no-cache',
                    'Sec-Fetch-Dest'  => 'document',
                    'Sec-Fetch-Mode'  => 'navigate',
                    'Sec-Fetch-Site'  => 'none',
                    'Sec-Fetch-User'  => '?1',
                    'Upgrade-Insecure-Requests' => '1',
                ])
                ->timeout(self::TIMEOUT)
                ->maxRedirects(self::MAX_REDIRECTS)
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            // Skip binary content
            $contentType = $response->header('Content-Type') ?? '';
            foreach (self::SKIP_CONTENT_TYPES as $skipType) {
                if (str_contains(strtolower($contentType), $skipType)) {
                    return null;
                }
            }

            $body = $response->body();

            // Sanity check: must look like HTML (check in full body, not just first 1000 chars)
            $bodyLower = strtolower($body);
            $isHtmlContent = str_contains(strtolower($contentType), 'text/html');
            $hasHtmlTags = str_contains($bodyLower, '<html') || str_contains($bodyLower, '<!doctype')
                || str_contains($bodyLower, '<head') || str_contains($bodyLower, '<body')
                || str_contains($bodyLower, '<div') || str_contains($bodyLower, '<p');
            if (!$isHtmlContent && !$hasHtmlTags) {
                return null;
            }

            return $body;

        } catch (\Throwable $e) {
            Log::debug('WebScraper: failed to fetch page', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if our bot is allowed by the site's robots.txt.
     */
    private function isAllowedByRobotsTxt(string $baseUrl): bool
    {
        try {
            $response = Http::withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept'     => 'text/plain,*/*',
                ])
                ->timeout(5)
                ->get(rtrim($baseUrl, '/') . '/robots.txt');

            if (!$response->successful()) {
                // No robots.txt = assume allowed
                return true;
            }

            $body = strtolower($response->body());

            // Check for full-site disallow: "user-agent: *" block containing "disallow: /"
            // Split into blocks per user-agent, check the wildcard block
            if (preg_match('/user-agent:\s*\*\s*\n(.*?)(?=user-agent:|\z)/is', $body, $blockMatch)) {
                $block = $blockMatch[1];
                if (preg_match('/disallow:\s*\/\s*$/m', $block)) {
                    // Check if there's an explicit allow that overrides it
                    if (!preg_match('/allow:\s*\/\s/m', $block)) {
                        return false;
                    }
                }
            }

            return true;

        } catch (\Throwable $e) {
            // Can't fetch robots.txt = assume allowed
            return true;
        }
    }

    /**
     * Extract emails, phones, and social links from HTML content.
     */
    private function extractFromHtml(string $html, array &$result): void
    {
        // 1. Decode CloudFlare email protection BEFORE stripping scripts
        $html = $this->decodeCloudflareEmails($html);

        // 2. Strip scripts and styles to avoid false positives
        $cleaned = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $cleaned = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $cleaned);

        // 3. Extract emails from HTML attributes (data-email, value, content, etc.)
        $this->extractEmailsFromAttributes($html, $result['emails']);

        // 4. Extract from raw HTML (catches mailto: and href links)
        $this->extractEmails($html, $result['emails']);
        $this->extractEmails($cleaned, $result['emails']);

        // 5. Strip HTML tags for text-based extraction
        $text = strip_tags($cleaned);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 6. Extract from plain text
        $this->extractEmails($text, $result['emails']);

        // 7. Extract obfuscated emails: "info [at] domain [dot] com" etc.
        $this->extractObfuscatedEmails($text, $result['emails']);

        $this->extractPhones($text, $result['phones']);
        $this->extractSocialLinks($html, $result['social_links'], $result['phones']);
        $this->extractAddresses($text, $result['addresses']);

        // Extract contact person names (wrapped in try/catch — regex can fail on exotic HTML)
        try {
            $persons = $this->extractContactPersons($text);
            foreach ($persons as $person) {
                $result['contact_persons'][] = $person;
            }
        } catch (\Throwable $e) {
            Log::debug('WebScraper: person extraction failed', ['error' => $e->getMessage()]);
        }

        // Extract linked contacts: associate names ↔ emails ↔ phones ↔ roles
        $this->extractLinkedContacts($text, $result['linked_contacts']);

        // 8. DOM-based structured extraction: tables, team lists, contact cards
        // This catches data that regex on flat text misses
        $this->extractStructuredContacts($cleaned, $result);

        // 9. Extract WhatsApp from onclick/data attributes and button text
        $this->extractWhatsAppExtended($html, $result['social_links'], $result['phones']);

        // 10. Extract tel: links (href="tel:+123456789")
        $this->extractTelLinks($html, $result['phones']);

        // Detect language (only on first page, where lang= attribute is most reliable)
        if ($result['detected_language'] === null) {
            $result['detected_language'] = $this->detectLanguage($html, $text);
        }
    }

    /**
     * Decode CloudFlare email protection.
     * CF encodes emails as hex in data-cfemail attributes and /cdn-cgi/l/email-protection# URLs.
     * Format: first 2 hex chars = XOR key, remaining pairs XOR'd with key = original chars.
     */
    private function decodeCloudflareEmails(string $html): string
    {
        // Pattern 1: <a href="/cdn-cgi/l/email-protection#HEX">
        $html = preg_replace_callback(
            '/href=["\']\/cdn-cgi\/l\/email-protection#([0-9a-fA-F]+)["\']/',
            function ($m) {
                $decoded = $this->cfDecode($m[1]);
                return $decoded ? 'href="mailto:' . $decoded . '"' : $m[0];
            },
            $html
        );

        // Pattern 2: data-cfemail="HEX"
        $html = preg_replace_callback(
            '/data-cfemail=["\']([0-9a-fA-F]+)["\']/',
            function ($m) {
                $decoded = $this->cfDecode($m[1]);
                return $decoded ? 'data-cfemail-decoded="' . $decoded . '"' : $m[0];
            },
            $html
        );

        // Pattern 3: [email&#160;protected] or [email protected] placeholder text → replace with decoded email from nearby attribute
        // After decoding attributes above, also replace the visible placeholder
        $html = preg_replace_callback(
            '/<span[^>]*class=["\'][^"\']*__cf_email__[^"\']*["\'][^>]*data-cfemail-decoded=["\']([^"\']+)["\'][^>]*>.*?<\/span>/is',
            function ($m) {
                return $m[1]; // Replace entire span with the decoded email
            },
            $html
        );

        // Also try reversed attribute order
        $html = preg_replace_callback(
            '/<span[^>]*data-cfemail-decoded=["\']([^"\']+)["\'][^>]*class=["\'][^"\']*__cf_email__[^"\']*["\'][^>]*>.*?<\/span>/is',
            function ($m) {
                return $m[1];
            },
            $html
        );

        return $html;
    }

    /**
     * Decode a CloudFlare hex-encoded email string.
     * Algorithm: first byte = XOR key, remaining bytes XOR'd with key.
     */
    private function cfDecode(string $hex): ?string
    {
        if (strlen($hex) < 4 || strlen($hex) % 2 !== 0) {
            return null;
        }

        try {
            $key = hexdec(substr($hex, 0, 2));
            $decoded = '';
            for ($i = 2, $len = strlen($hex); $i < $len; $i += 2) {
                $decoded .= chr(hexdec(substr($hex, $i, 2)) ^ $key);
            }
            // Validate it looks like an email
            if (filter_var($decoded, FILTER_VALIDATE_EMAIL)) {
                return $decoded;
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Extract emails from HTML attributes: data-email, value, content (meta tags), title, alt, etc.
     */
    private function extractEmailsFromAttributes(string $html, array &$emails): void
    {
        // data-email="...", data-mail="...", data-contact="..."
        if (preg_match_all('/data-(?:email|mail|contact|e-mail)[=:]["\']?\s*([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i', $html, $matches)) {
            foreach ($matches[1] as $email) {
                $email = strtolower(trim($email));
                if ($this->isValidEmail($email)) {
                    $emails[] = $email;
                }
            }
        }

        // <input ... value="email@domain.com" ... > (contact forms pre-filled)
        if (preg_match_all('/<input[^>]*value=["\']([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $email) {
                $email = strtolower(trim($email));
                if ($this->isValidEmail($email)) {
                    $emails[] = $email;
                }
            }
        }

        // <meta content="email@domain.com"> (often in meta tags for contact)
        if (preg_match_all('/<meta[^>]*content=["\']([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})["\'][^>]*>/i', $html, $matches)) {
            foreach ($matches[1] as $email) {
                $email = strtolower(trim($email));
                if ($this->isValidEmail($email)) {
                    $emails[] = $email;
                }
            }
        }

        // JSON-LD structured data: "email":"contact@domain.com"
        if (preg_match_all('/"(?:email|contactPoint|e-?mail)":\s*"([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})"/i', $html, $matches)) {
            foreach ($matches[1] as $email) {
                $email = strtolower(trim($email));
                if ($this->isValidEmail($email)) {
                    $emails[] = $email;
                }
            }
        }

        // href="mailto:" already handled in extractEmails, but also catch encoded mailto
        // &#109;&#97;&#105;&#108;&#116;&#111;&#58; = "mailto:" (WordPress antispambot)
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded !== $html) {
            if (preg_match_all('/mailto:([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i', $decoded, $matches)) {
                foreach ($matches[1] as $email) {
                    $email = strtolower(trim($email));
                    if ($this->isValidEmail($email)) {
                        $emails[] = $email;
                    }
                }
            }
        }
    }

    /**
     * Extract obfuscated email patterns from plain text.
     * Catches: "info [at] domain [dot] com", "info(at)domain(dot)com",
     *          "info AT domain DOT com", "info @ domain . com" (with extra spaces)
     */
    private function extractObfuscatedEmails(string $text, array &$emails): void
    {
        // Patterns for [at]/@/AT/(at) and [dot]/./DOT/(dot)
        $atPatterns = '\s*(?:\[at\]|\(at\)|@|\{at\}|AT|\bat\b)\s*';
        $dotPatterns = '\s*(?:\[dot\]|\(dot\)|\{dot\}|DOT|\bdot\b)\s*';

        $pattern = '/([a-zA-Z0-9._%+\-]+)' . $atPatterns . '([a-zA-Z0-9.\-]+)' . $dotPatterns . '([a-zA-Z]{2,})/i';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $email = strtolower(trim($m[1]) . '@' . trim($m[2]) . '.' . trim($m[3]));
                if ($this->isValidEmail($email)) {
                    $emails[] = $email;
                }
            }
        }
    }

    /**
     * Extract email addresses using regex.
     */
    private function extractEmails(string $text, array &$emails): void
    {
        // Match mailto: links (TLD max 12 chars)
        if (preg_match_all('/mailto:([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,12})/i', $text, $matches)) {
            foreach ($matches[1] as $email) {
                $email = strtolower(trim($email));
                if ($this->isValidEmail($email)) {
                    $emails[] = $email;
                }
            }
        }

        // Match email patterns in text (TLD max 12 chars to avoid matching "user@domain.myhoraires")
        if (preg_match_all('/\b([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,12})\b/', $text, $matches)) {
            foreach ($matches[1] as $email) {
                $email = strtolower(trim($email));
                if ($this->isValidEmail($email)) {
                    $emails[] = $email;
                }
            }
        }
    }

    /**
     * Extract phone numbers using regex (international formats).
     */
    private function extractPhones(string $text, array &$phones): void
    {
        // International formats: +33 1 23 45 67 89, +66-2-123-4567, (212) 555-1234, etc.
        $patterns = [
            '/(\+\d{1,3}[\s.\-]?\(?\d{1,4}\)?[\s.\-]?\d{1,4}[\s.\-]?\d{1,4}[\s.\-]?\d{0,4})/',
            '/(\(\d{2,4}\)\s?\d{3,4}[\s.\-]?\d{3,4})/',
            '/(\b0\d[\s.\-]?\d{2}[\s.\-]?\d{2}[\s.\-]?\d{2}[\s.\-]?\d{2}\b)/',  // French: 01 23 45 67 89
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $phone) {
                    $phone = trim($phone);
                    if ($this->isValidPhone($phone)) {
                        $phones[] = $this->normalizePhone($phone);
                    }
                }
            }
        }
    }

    /**
     * Extract social media profile URLs from HTML href attributes.
     */
    private function extractSocialLinks(string $html, array &$socialLinks, array &$phones = []): void
    {
        $platforms = [
            'facebook'  => 'facebook\.com/[a-zA-Z0-9._\-]+',
            'linkedin'  => 'linkedin\.com/(in|company)/[a-zA-Z0-9._\-]+',
            'twitter'   => '(twitter\.com|x\.com)/[a-zA-Z0-9._\-]+',
            'instagram' => 'instagram\.com/[a-zA-Z0-9._\-]+',
            'tiktok'    => 'tiktok\.com/@[a-zA-Z0-9._\-]+',
            'youtube'   => 'youtube\.com/(@[a-zA-Z0-9._\-]+|c/[a-zA-Z0-9._\-]+|channel/[a-zA-Z0-9._\-]+)',
            'pinterest' => 'pinterest\.(com|fr|de|co\.uk)/[a-zA-Z0-9._\-]+',
            'telegram'  => 't\.me/[a-zA-Z0-9._\-]+',
            'skype'     => '(join\.skype\.com/[a-zA-Z0-9._\-]+)',
            'line'      => 'line\.me/(R/ti/p/|R/)?[a-zA-Z0-9._\-~@]+',
        ];

        foreach ($platforms as $platform => $pattern) {
            if (preg_match_all('/https?:\/\/(www\.)?' . $pattern . '/i', $html, $matches)) {
                foreach ($matches[0] as $url) {
                    $url = rtrim($url, '/');
                    // Skip generic pages (login, share, etc.)
                    if (preg_match('/(login|share|sharer|dialog|intent)/i', $url)) {
                        continue;
                    }
                    $socialLinks[$platform] = $socialLinks[$platform] ?? $url;
                }
            }
        }

        // WhatsApp: wa.me/XXXXX and api.whatsapp.com/send?phone=XXXXX
        if (preg_match_all('/https?:\/\/(www\.)?wa\.me\/(\d+)/i', $html, $matches)) {
            foreach ($matches[0] as $idx => $url) {
                $url = rtrim($url, '/');
                $socialLinks['whatsapp'] = $socialLinks['whatsapp'] ?? $url;
                // Also extract the phone number
                $phoneNumber = '+' . $matches[2][$idx];
                if ($this->isValidPhone($phoneNumber)) {
                    $phones[] = $this->normalizePhone($phoneNumber);
                }
            }
        }

        if (preg_match_all('/https?:\/\/(www\.)?api\.whatsapp\.com\/send\?phone=(\d+)/i', $html, $matches)) {
            foreach ($matches[0] as $idx => $url) {
                $cleanUrl = preg_replace('/&.*$/', '', $url); // Keep only phone param
                $socialLinks['whatsapp'] = $socialLinks['whatsapp'] ?? $cleanUrl;
                // Also extract the phone number
                $phoneNumber = '+' . $matches[2][$idx];
                if ($this->isValidPhone($phoneNumber)) {
                    $phones[] = $this->normalizePhone($phoneNumber);
                }
            }
        }

        // Skype: skype:username pattern (not a URL, found in href="skype:...")
        if (!isset($socialLinks['skype'])) {
            if (preg_match('/skype:([a-zA-Z0-9._\-]+)/i', $html, $match)) {
                $socialLinks['skype'] = 'skype:' . $match[1];
            }
        }

        // WeChat: detect common wechat ID patterns (often displayed as text/images)
        if (preg_match('/(?:wechat|weixin|微信)\s*(?:id|ID|Id)?\s*[:：]\s*([a-zA-Z0-9_\-]+)/i', $html, $match)) {
            $socialLinks['wechat'] = $socialLinks['wechat'] ?? 'wechat:' . $match[1];
        }
    }

    /**
     * Extract postal addresses from plain text.
     * Wrapped in try/catch because regex on arbitrary HTML can fail.
     */
    private function extractAddresses(string $text, array &$addresses): void
    {
        try {
            $this->doExtractAddresses($text, $addresses);
        } catch (\Throwable $e) {
            Log::debug('WebScraper: address extraction failed', ['error' => $e->getMessage()]);
        }
    }

    private function doExtractAddresses(string $text, array &$addresses): void
    {
        // Normalize whitespace for easier matching
        $text = preg_replace('/\s+/', ' ', $text);

        // Pattern 1: Street number + street name (French style: "12 Rue de la Paix", "123 Avenue des Champs")
        $streetPatterns = [
            '/\b(\d{1,5}\s*,?\s*(?:rue|avenue|boulevard|bvd|blvd|allée|impasse|chemin|place|cours|passage|route|voie)\s+[A-ZÀ-Ÿa-zà-ÿ\s\'\-]{3,50}(?:\s*,\s*\d{4,5}\s+[A-ZÀ-Ÿa-zà-ÿ\s\'\-]+)?)/iu',
            // English style: "123 Main Street", "456 Oak Avenue"
            '/\b(\d{1,5}\s+[A-Za-z\s\'\-]{2,30}\s+(?:street|st|road|rd|avenue|ave|boulevard|blvd|drive|dr|lane|ln|way|court|ct|place|pl|circle|cir|terrace|ter)\.?\b(?:\s*,\s*[A-Za-z\s]+(?:\s*,\s*[A-Z]{2}\s+\d{5}(?:-\d{4})?)?)?)/iu',
        ];

        foreach ($streetPatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $address) {
                    $address = trim($address, " ,.");
                    if (mb_strlen($address) >= 10 && mb_strlen($address) <= 200) {
                        $addresses[] = $address;
                    }
                }
            }
        }

        // Pattern 2: BP / PO Box patterns
        if (preg_match_all('/\b((?:BP|B\.P\.|P\.?O\.?\s*Box)\s*\d{1,6}(?:\s*,\s*\d{4,5}\s+[A-ZÀ-Ÿa-zà-ÿ\s\'\-]+)?)/iu', $text, $matches)) {
            foreach ($matches[1] as $address) {
                $address = trim($address, " ,.");
                if (mb_strlen($address) >= 5) {
                    $addresses[] = $address;
                }
            }
        }

        // Pattern 3: Zip code + city near address keywords
        $keywords = 'adresse|address|siège|siege|localisation|location|bureau|office|headquarter|sitz';
        // Look for content near address keywords: capture up to 150 chars after keyword
        if (preg_match_all('/(?:' . $keywords . ')\s*[:：]?\s*(.{10,150})/iu', $text, $matches)) {
            foreach ($matches[1] as $candidate) {
                // Must contain a zip code pattern (4-5 digits) to be considered an address
                if (preg_match('/\b\d{4,5}\b/', $candidate)) {
                    // Trim at sentence boundary
                    $candidate = preg_replace('/[.!?|].*$/', '', $candidate);
                    $candidate = trim($candidate, " ,.\t\n\r");
                    if (mb_strlen($candidate) >= 10 && mb_strlen($candidate) <= 200) {
                        $addresses[] = $candidate;
                    }
                }
            }
        }
    }

    /**
     * Validate an email address.
     */
    private function isValidEmail(string $email): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Skip technical/spam/system emails
        $skipPatterns = [
            'noreply@', 'no-reply@', 'mailer-daemon@',
            'postmaster@', 'webmaster@', 'hostmaster@', 'abuse@',
            '@example.', '@test.', '@localhost',
            '@sentry', '@wixpress', '@wix.com', '@squarespace',
            '@wordpress', '@cloudflare', '@google', '@gstatic',
            '@googleapis', '@jquery', '@bootstrap',
            '@github', '@sentry.io', '@sentry-next',
            '.png', '.jpg', '.gif', '.svg', '.webp', '.css', '.js',
            'donotreply', 'do-not-reply', 'unsubscribe',
            'tracking@', 'pixel@', 'analytics@',
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains(strtolower($email), $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate a phone number (at least 8 digits after stripping formatting).
     */
    private function isValidPhone(string $phone): bool
    {
        $digitsOnly = preg_replace('/\D/', '', $phone);
        return strlen($digitsOnly) >= 8 && strlen($digitsOnly) <= 15;
    }

    /**
     * Normalize a phone number (keep + prefix, strip excess formatting).
     */
    private function normalizePhone(string $phone): string
    {
        // Preserve leading +, strip everything except digits and +
        $phone = trim($phone);
        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/[^\d]/', '', $phone);

        return $hasPlus ? "+{$digits}" : $digits;
    }

    /**
     * Normalize a URL for comparison purposes.
     */
    private function normalizeUrl(string $url): string
    {
        $url = strtolower(rtrim($url, '/'));
        $url = preg_replace('/^https?:\/\/(www\.)?/', '', $url);
        return $url;
    }

    /**
     * Fetch a page with HTTPS→HTTP fallback.
     */
    private function fetchPageWithFallback(string $url, string $baseUrl): ?string
    {
        $html = $this->fetchPage($url);
        if ($html !== null) {
            return $html;
        }

        // If HTTPS failed, try HTTP
        if (str_starts_with($url, 'https://')) {
            $httpUrl = preg_replace('/^https:/', 'http:', $url);
            $html = $this->fetchPage($httpUrl);
            if ($html !== null) {
                return $html;
            }
        }

        return null;
    }

    /**
     * Strip locale prefix from URL path (e.g., /fr/, /en/, /de/).
     * Returns the modified URL or null if no locale prefix found.
     */
    private function stripLocalePrefix(string $url): ?string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';

        // Match common locale prefixes: /fr/, /en/, /de/, /es/, /it/, /pt/, /nl/, /pl/, /ru/, etc.
        if (preg_match('#^/([a-z]{2}(?:-[a-z]{2})?)(/.*)?$#i', $path, $matches)) {
            $locale = strtolower($matches[1]);
            $rest = $matches[2] ?? '/';
            if (in_array($locale, ['fr', 'en', 'de', 'es', 'it', 'pt', 'nl', 'pl', 'ru', 'ar', 'zh', 'ja', 'ko', 'th', 'vi', 'tr'])) {
                $scheme = $parsed['scheme'] ?? 'https';
                $host = $parsed['host'] ?? '';
                return "{$scheme}://{$host}{$rest}";
            }
        }

        return null;
    }

    /**
     * Discover contact/about pages from links found in page HTML.
     * Returns an array of full URLs to try.
     */
    private function discoverContactPages(string $html, string $baseUrl): array
    {
        $pages = [];

        if (preg_match_all('/href=["\']([^"\']+)["\']/', $html, $matches)) {
            $contactKeywords = [
                'contact', 'about', 'team', 'staff', 'equipe', 'direction',
                'admin', 'info', 'coordonn', 'nous-contacter', 'a-propos',
                'impressum', 'kontakt', 'who-we-are', 'our-people',
                'notre-equipe', 'our-team', 'contactez', 'joindre',
            ];

            $baseHost = parse_url($baseUrl, PHP_URL_HOST);

            foreach ($matches[1] as $href) {
                $hrefLower = strtolower($href);

                // Skip anchors, javascript, mailto, tel
                if (str_starts_with($hrefLower, '#') || str_starts_with($hrefLower, 'javascript:')
                    || str_starts_with($hrefLower, 'mailto:') || str_starts_with($hrefLower, 'tel:')) {
                    continue;
                }

                // Check if it contains a contact keyword
                foreach ($contactKeywords as $keyword) {
                    if (str_contains($hrefLower, $keyword)) {
                        // Resolve to full URL
                        if (str_starts_with($href, '/')) {
                            $pages[] = rtrim($baseUrl, '/') . $href;
                        } elseif (str_starts_with($hrefLower, 'http')) {
                            $hrefHost = parse_url($href, PHP_URL_HOST);
                            if ($hrefHost && str_contains($hrefHost, $baseHost)) {
                                $pages[] = $href;
                            }
                        }
                        break;
                    }
                }
            }
        }

        return array_unique($pages);
    }

    /**
     * Extract contact person names found near titles/roles or email addresses.
     */
    private function extractContactPersons(string $text): array
    {
        $persons = [];

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Pattern 1: Title/role followed by a name
        // e.g., "Director: John Smith", "Directeur : Marie Dupont", "President - Jane Doe"
        $titlePatterns = [
            // English titles
            'director', 'principal', 'president', 'chairman', 'chairwoman', 'chairperson',
            'ceo', 'cto', 'cfo', 'coo', 'founder', 'co-founder', 'cofounder',
            'manager', 'head of', 'chief', 'lead', 'coordinator', 'supervisor',
            'owner', 'partner', 'editor', 'publisher', 'secretary', 'treasurer',
            // French titles
            'directeur', 'directrice', 'président', 'présidente', 'responsable',
            'gérant', 'gérante', 'fondateur', 'fondatrice', 'rédacteur', 'rédactrice',
            'coordinateur', 'coordinatrice', 'secrétaire', 'trésorier', 'trésorière',
            'chef', 'patron', 'patronne',
            // German titles
            'geschäftsführer', 'geschäftsführerin', 'leiter', 'leiterin', 'inhaber', 'inhaberin',
            'vorsitzender', 'vorsitzende',
        ];

        $titlesRegex = implode('|', array_map(fn($t) => preg_quote($t, '/'), $titlePatterns));

        // Match: Title [separator] Firstname Lastname
        // Name pattern: uppercase letter followed by lowercase, e.g., "Jean-Pierre Dupont"
        $namePattern = '([A-ZÀ-ÖØ-Þ][a-zà-öø-ÿ]+(?:[\-\s][A-ZÀ-ÖØ-Þ][a-zà-öø-ÿ]+){1,3})';

        if (preg_match_all('/(?:' . $titlesRegex . ')\s*[:：\-–—,]?\s*' . $namePattern . '/iu', $text, $matches)) {
            foreach ($matches[1] as $name) {
                $name = trim($name);
                if (mb_strlen($name) >= 4 && mb_strlen($name) <= 60) {
                    $persons[] = $name;
                }
            }
        }

        // Pattern 2: "Contact: Name" or "Contact : Name"
        if (preg_match_all('/contact\s*[:：]\s*' . $namePattern . '/iu', $text, $matches)) {
            foreach ($matches[1] as $name) {
                $name = trim($name);
                if (mb_strlen($name) >= 4 && mb_strlen($name) <= 60) {
                    $persons[] = $name;
                }
            }
        }

        // Pattern 3: Name followed by email (e.g., "Marie Dupont marie@example.com")
        if (preg_match_all('/' . $namePattern . '\s+[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/u', $text, $matches)) {
            foreach ($matches[1] as $name) {
                $name = trim($name);
                if (mb_strlen($name) >= 4 && mb_strlen($name) <= 60) {
                    $persons[] = $name;
                }
            }
        }

        return $persons;
    }

    /**
     * Detect the primary language of a page.
     * Uses HTML lang attribute first, then content analysis as fallback.
     *
     * @return string|null ISO 639-1 code (fr, en, de, etc.) or null if unknown
     */
    private function detectLanguage(string $html, string $text): ?string
    {
        // 1. HTML lang attribute (most reliable)
        if (preg_match('/<html[^>]*\slang=["\']([a-z]{2})/i', $html, $match)) {
            return strtolower($match[1]);
        }

        // 2. Content-Language meta tag
        if (preg_match('/<meta[^>]*http-equiv=["\']content-language["\'][^>]*content=["\']([a-z]{2})/i', $html, $match)) {
            return strtolower($match[1]);
        }
        if (preg_match('/<meta[^>]*content=["\']([a-z]{2})["\'][^>]*http-equiv=["\']content-language["\']/', $html, $match)) {
            return strtolower($match[1]);
        }

        // 3. Content analysis — count French vs English indicator words
        $textLower = strtolower($text);

        $frenchWords = ['école', 'lycée', 'collège', 'maternelle', 'primaire', 'inscriptions',
            'bienvenue', 'accueil', 'notre', 'établissement', 'enseignement', 'pédagogie',
            'parents', 'élèves', 'nous contacter', 'actualités', 'rentrée', 'secrétariat',
            'cantine', 'horaires', 'directeur', 'directrice', 'professeurs'];

        $englishWords = ['school', 'welcome', 'admission', 'enrolment', 'enrollment',
            'curriculum', 'campus', 'about us', 'contact us', 'our school', 'students',
            'teachers', 'principal', 'headmaster', 'facilities', 'learning', 'tuition'];

        $frCount = 0;
        $enCount = 0;

        foreach ($frenchWords as $w) {
            $frCount += substr_count($textLower, $w);
        }
        foreach ($englishWords as $w) {
            $enCount += substr_count($textLower, $w);
        }

        // Need at least 3 matches to be confident
        if ($frCount >= 3 && $frCount > $enCount * 1.5) return 'fr';
        if ($enCount >= 3 && $enCount > $frCount * 1.5) return 'en';

        // If both present equally, likely bilingual — still useful
        if ($frCount >= 2 && $enCount >= 2) return 'fr'; // Bilingual with French = still relevant

        return null;
    }

    /**
     * Remove duplicate social links (keep first found per platform).
     */
    private function deduplicateSocialLinks(array $links): array
    {
        // Already keyed by platform, so just return as-is
        return $links;
    }

    /**
     * Discover a website URL for a contact by searching DuckDuckGo.
     * Uses DuckDuckGo HTML endpoint (no CAPTCHA, no API key needed).
     * Returns the first non-social, non-directory result URL.
     */
    public function discoverWebsiteUrl(string $name, ?string $country = null): ?string
    {
        try {
            $query = $name;
            if ($country) {
                $query .= ' ' . $country;
            }

            $searchUrl = 'https://html.duckduckgo.com/html/?q=' . urlencode($query);

            $response = Http::withHeaders([
                    'User-Agent'      => self::USER_AGENT,
                    'Accept'          => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'en-US,en;q=0.9,fr;q=0.8',
                ])
                ->timeout(10)
                ->maxRedirects(3)
                ->get($searchUrl);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();

            // DuckDuckGo HTML embeds result URLs in uddg= redirect parameter
            $urls = [];
            if (preg_match_all('/uddg=(https?[^&"]+)/', $html, $matches)) {
                foreach ($matches[1] as $u) {
                    $urls[] = urldecode($u);
                }
            }

            // Fallback: result__a class links
            if (empty($urls) && preg_match_all('/class="result__a"[^>]*href="([^"]+)"/', $html, $matches)) {
                foreach ($matches[1] as $u) {
                    $urls[] = urldecode($u);
                }
            }

            // Filter out social media, search engines, directories, and aggregators
            $skipDomains = array_merge(self::SKIP_DOMAINS, [
                'duckduckgo.com',
                'gstatic.com', 'googleapis.com',
                // Education directories (for school lookups)
                'mlfmonde.org', 'campusfrance.org', 'onisep.fr',
                'letudiant.fr', 'studyrama.com', 'odyssey.education',
                'french-schools.org', 'efep.education',
                // Government sites
                'education.gouv.fr', 'diplomatie.gouv.fr',
                'service-public.fr', 'data.gouv.fr',
                '.gouv.fr',
                // Expat directories
                'expat.com', 'expatries.org', 'internations.org',
                'femmexpat.com', 'expatfocus.com', 'expatica.com',
                'justlanded.com', 'angloinfo.com',
                'forumvietnam.fr', 'thailandee.com',
                // Listing aggregators
                'kompass.com', 'societe.com', 'europages.fr',
                'cylex.fr', 'mappy.com',
                'indeed.com', 'indeed.fr', 'glassdoor.com',
                'numbeo.com', 'livingcost.org',
            ]);

            // Deduplicate and filter
            $seen = [];
            foreach ($urls as $url) {
                // Already decoded during extraction, don't double-decode
                $host = parse_url($url, PHP_URL_HOST);
                if (!$host) continue;
                $host = strtolower($host);

                // Skip duplicates
                if (isset($seen[$host])) continue;
                $seen[$host] = true;

                // Skip unwanted domains
                $skip = false;
                foreach ($skipDomains as $skipDomain) {
                    if (str_contains($host, $skipDomain)) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) continue;

                // Found a valid URL
                return rtrim($url, '/');
            }

            return null;
        } catch (\Throwable $e) {
            Log::debug('WebScraper: website discovery failed', [
                'name'  => $name,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Common email prefixes to try when scraping finds no email.
     */
    private const COMMON_EMAIL_PREFIXES = [
        'info',
        'contact',
        'admin',
        'office',
        'enquiries',
        'hello',
        'mail',
        'reception',
        'secretary',
        'accueil',
        'direction',
        'secretariat',
        'general',
    ];

    /**
     * Guess likely email addresses for a domain by checking if MX records exist,
     * then returning common prefix@domain candidates.
     *
     * @return string[] List of suggested email addresses (unverified but domain has MX)
     */
    public function guessEmailsForDomain(string $url): array
    {
        try {
            $parsed = parse_url($url);
            $host = $parsed['host'] ?? '';
            if (empty($host)) {
                return [];
            }

            // Strip www.
            $domain = preg_replace('/^www\./', '', strtolower($host));

            // Check if domain has MX records (= can receive email)
            $mxRecords = [];
            if (!getmxrr($domain, $mxRecords)) {
                // No MX records — also try DNS_A as fallback (some small sites use A record for mail)
                $dnsA = dns_get_record($domain, DNS_A);
                if (empty($dnsA)) {
                    return [];
                }
            }

            // Domain can receive email — suggest common prefixes
            $suggestions = [];
            foreach (self::COMMON_EMAIL_PREFIXES as $prefix) {
                $suggestions[] = "{$prefix}@{$domain}";
            }

            return $suggestions;
        } catch (\Throwable $e) {
            Log::debug('WebScraper: email guess failed', ['url' => $url, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * DOM-based structured contact extraction.
     *
     * Parses HTML as DOM to find structured contact blocks:
     * - Tables with columns (Name, Email, Phone, Role, etc.)
     * - Team/staff cards (<div class="team-member">, <article>, etc.)
     * - Definition lists (<dl><dt>Name</dt><dd>details</dd></dl>)
     * - Structured lists (<ul><li> with emails/phones)
     *
     * This catches data that flat-text regex misses because it understands
     * which email belongs to which person/department.
     */
    private function extractStructuredContacts(string $html, array &$result): void
    {
        try {
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($dom);

            // Strategy 1: Tables with contact data
            $this->extractFromContactTables($xpath, $result);

            // Strategy 2: Team/staff cards
            $this->extractFromTeamCards($xpath, $result);

            // Strategy 3: Contact sections with labels
            $this->extractFromLabeledSections($xpath, $result);

        } catch (\Throwable $e) {
            Log::debug('WebScraper: structured extraction failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Extract contacts from HTML tables (team pages, directory tables).
     */
    private function extractFromContactTables(\DOMXPath $xpath, array &$result): void
    {
        $tables = $xpath->query('//table');
        if ($tables->length === 0) return;

        foreach ($tables as $table) {
            $rows = $xpath->query('.//tr', $table);
            if ($rows->length < 2) continue;

            foreach ($rows as $row) {
                $text = trim($row->textContent);
                if (strlen($text) < 10) continue;

                // Extract all emails from this row
                $rowEmails = [];
                $this->extractEmails($text, $rowEmails);

                // Extract all phones
                $rowPhones = [];
                $this->extractPhones($text, $rowPhones);

                // Extract mailto links
                $mailtoLinks = $xpath->query('.//a[starts-with(@href, "mailto:")]', $row);
                foreach ($mailtoLinks as $link) {
                    $email = strtolower(trim(str_replace('mailto:', '', explode('?', $link->getAttribute('href'))[0])));
                    if ($this->isValidEmail($email)) {
                        $rowEmails[] = $email;
                    }
                }

                // Extract tel links
                $telLinks = $xpath->query('.//a[starts-with(@href, "tel:")]', $row);
                foreach ($telLinks as $link) {
                    $phone = trim(str_replace('tel:', '', $link->getAttribute('href')));
                    $phone = preg_replace('/[^\d+\-\s.]/', '', $phone);
                    if ($this->isValidPhone($phone)) {
                        $rowPhones[] = $this->normalizePhone($phone);
                    }
                }

                if (empty($rowEmails) && empty($rowPhones)) continue;

                // Try to find a name in the first cell or strong tag
                $cells = $xpath->query('.//td', $row);
                $name = null;
                $role = null;

                if ($cells->length >= 2) {
                    $firstCell = trim($cells->item(0)->textContent);
                    if (strlen($firstCell) >= 3 && strlen($firstCell) <= 80 && !str_contains($firstCell, '@')) {
                        $name = $firstCell;
                    }
                    // Check if second cell looks like a role/position
                    $secondCell = trim($cells->item(1)->textContent);
                    if (strlen($secondCell) >= 3 && strlen($secondCell) <= 100 && !str_contains($secondCell, '@') && !preg_match('/^\+?\d/', $secondCell)) {
                        $role = $secondCell;
                    }
                }

                // Add unique emails and phones to global result
                foreach ($rowEmails as $email) {
                    $email = strtolower(trim($email));
                    if ($this->isValidEmail($email)) {
                        $result['emails'][] = $email;
                    }
                }
                foreach ($rowPhones as $phone) {
                    $result['phones'][] = $phone;
                }

                // Create linked contact if we have a name
                if ($name && (!empty($rowEmails) || !empty($rowPhones))) {
                    $result['linked_contacts'][] = [
                        'name'  => $name,
                        'email' => !empty($rowEmails) ? strtolower($rowEmails[0]) : null,
                        'phone' => !empty($rowPhones) ? $rowPhones[0] : null,
                        'role'  => $role,
                    ];
                }
            }
        }
    }

    /**
     * Extract contacts from team/staff card structures.
     */
    private function extractFromTeamCards(\DOMXPath $xpath, array &$result): void
    {
        // Common patterns for team member cards
        $cardQueries = [
            '//div[contains(@class,"team")]//div[contains(@class,"member")]',
            '//div[contains(@class,"team")]//article',
            '//div[contains(@class,"team")]//li',
            '//div[contains(@class,"staff")]//div',
            '//div[contains(@class,"staff")]//article',
            '//div[contains(@class,"equipe")]//div',
            '//div[contains(@class,"personnel")]//div',
            '//div[contains(@class,"contact")]//div[contains(@class,"card")]',
            '//div[contains(@class,"contact")]//article',
            '//section[contains(@class,"team")]//div',
            '//section[contains(@class,"contact")]//div[contains(@class,"item")]',
            // vCard structured data
            '//div[contains(@class,"vcard")]',
            '//div[contains(@class,"h-card")]',
        ];

        $processed = [];

        foreach ($cardQueries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes->length < 1 || $nodes->length > 100) continue;

            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                if (strlen($text) < 15 || strlen($text) > 2000) continue;

                // Avoid processing same content twice
                $key = substr(preg_replace('/\s+/', ' ', $text), 0, 80);
                if (isset($processed[$key])) continue;
                $processed[$key] = true;

                // Extract email
                $emails = [];
                $this->extractEmails($text, $emails);
                $mailtoLinks = $xpath->query('.//a[starts-with(@href, "mailto:")]', $node);
                foreach ($mailtoLinks as $link) {
                    $email = strtolower(trim(str_replace('mailto:', '', explode('?', $link->getAttribute('href'))[0])));
                    if ($this->isValidEmail($email)) {
                        $emails[] = $email;
                    }
                }

                // Extract phones
                $phones = [];
                $this->extractPhones($text, $phones);
                $telLinks = $xpath->query('.//a[starts-with(@href, "tel:")]', $node);
                foreach ($telLinks as $link) {
                    $phone = trim(str_replace('tel:', '', $link->getAttribute('href')));
                    $phone = preg_replace('/[^\d+\-\s.]/', '', $phone);
                    if ($this->isValidPhone($phone)) {
                        $phones[] = $this->normalizePhone($phone);
                    }
                }

                if (empty($emails) && empty($phones)) continue;

                // Find name (h2/h3/h4/strong)
                $name = null;
                foreach (['h2', 'h3', 'h4', 'h5', 'strong', 'b'] as $tag) {
                    $headings = $xpath->query(".//{$tag}", $node);
                    if ($headings->length > 0) {
                        $candidate = trim($headings->item(0)->textContent);
                        if (strlen($candidate) >= 3 && strlen($candidate) <= 80 && !str_contains($candidate, '@')) {
                            $name = $candidate;
                            break;
                        }
                    }
                }

                // Find role (p.role, span.role, p.position, etc.)
                $role = null;
                $roleNodes = $xpath->query('.//*[contains(@class,"role") or contains(@class,"position") or contains(@class,"title") or contains(@class,"fonction") or contains(@class,"poste")]', $node);
                if ($roleNodes->length > 0) {
                    $role = trim($roleNodes->item(0)->textContent);
                    if (strlen($role) > 100 || str_contains($role, '@')) {
                        $role = null;
                    }
                }

                // Add to results
                foreach ($emails as $email) {
                    $email = strtolower(trim($email));
                    if ($this->isValidEmail($email)) {
                        $result['emails'][] = $email;
                    }
                }
                foreach ($phones as $phone) {
                    $result['phones'][] = $phone;
                }

                if ($name) {
                    $result['linked_contacts'][] = [
                        'name'  => $name,
                        'email' => !empty($emails) ? strtolower($emails[0]) : null,
                        'phone' => !empty($phones) ? $phones[0] : null,
                        'role'  => $role,
                    ];
                }
            }
        }
    }

    /**
     * Extract contacts from labeled sections (label: value patterns in DOM).
     * Catches: "Email: x@y.com", "Tél: +33...", "WhatsApp: +66..."
     */
    private function extractFromLabeledSections(\DOMXPath $xpath, array &$result): void
    {
        // Definition lists: <dt>Email</dt><dd>contact@example.com</dd>
        $dts = $xpath->query('//dl/dt');
        foreach ($dts as $dt) {
            $label = strtolower(trim($dt->textContent));
            $dd = $xpath->query('following-sibling::dd[1]', $dt);
            if ($dd->length === 0) continue;

            $value = trim($dd->item(0)->textContent);
            if (empty($value) || strlen($value) > 300) continue;

            if (preg_match('/e-?mail|courriel|correo/i', $label)) {
                $emails = [];
                $this->extractEmails($value, $emails);
                foreach ($emails as $e) {
                    if ($this->isValidEmail($e)) $result['emails'][] = strtolower($e);
                }
            } elseif (preg_match('/t[eé]l|phone|fax|mobile|portable|whatsapp|cellulaire/i', $label)) {
                $phones = [];
                $this->extractPhones($value, $phones);
                foreach ($phones as $p) $result['phones'][] = $p;
            } elseif (preg_match('/adresse|address|location|localisation|si[eè]ge/i', $label)) {
                if (strlen($value) >= 10 && strlen($value) <= 200) {
                    $result['addresses'][] = $value;
                }
            }
        }

        // List items with explicit labels
        $lis = $xpath->query('//li');
        foreach ($lis as $li) {
            $text = trim($li->textContent);
            if (strlen($text) < 8 || strlen($text) > 300) continue;

            // "Email: contact@example.com" or "Tel: +33 1 23 45 67 89"
            if (preg_match('/^(?:e-?mail|courriel)\s*[:：]\s*(.+)/i', $text, $m)) {
                $emails = [];
                $this->extractEmails($m[1], $emails);
                foreach ($emails as $e) {
                    if ($this->isValidEmail($e)) $result['emails'][] = strtolower($e);
                }
            }
            if (preg_match('/^(?:t[eé]l|phone|mobile|whatsapp|fax)\s*[:：]\s*(.+)/i', $text, $m)) {
                $phones = [];
                $this->extractPhones($m[1], $phones);
                foreach ($phones as $p) $result['phones'][] = $p;
            }

            // Also check for mailto/tel links in this li
            $mailtoLinks = $xpath->query('.//a[starts-with(@href, "mailto:")]', $li);
            foreach ($mailtoLinks as $link) {
                $email = strtolower(trim(str_replace('mailto:', '', explode('?', $link->getAttribute('href'))[0])));
                if ($this->isValidEmail($email)) $result['emails'][] = $email;
            }
            $telLinks = $xpath->query('.//a[starts-with(@href, "tel:")]', $li);
            foreach ($telLinks as $link) {
                $phone = trim(str_replace('tel:', '', $link->getAttribute('href')));
                $phone = preg_replace('/[^\d+\-\s.]/', '', $phone);
                if ($this->isValidPhone($phone)) $result['phones'][] = $this->normalizePhone($phone);
            }
        }
    }

    /**
     * Extract WhatsApp links from onclick handlers, data attributes, and button text.
     * Complements the existing wa.me/api.whatsapp.com detection.
     */
    private function extractWhatsAppExtended(string $html, array &$socialLinks, array &$phones): void
    {
        // onclick="window.open('https://wa.me/...')" or onclick handlers with WhatsApp
        if (preg_match_all('/(?:onclick|data-href|data-url|data-whatsapp)\s*=\s*["\'][^"\']*wa\.me\/(\d+)/i', $html, $matches)) {
            foreach ($matches[1] as $number) {
                $phone = '+' . $number;
                if ($this->isValidPhone($phone)) {
                    $socialLinks['whatsapp'] = $socialLinks['whatsapp'] ?? 'https://wa.me/' . $number;
                    $phones[] = $this->normalizePhone($phone);
                }
            }
        }

        // WhatsApp business links with phone
        if (preg_match_all('/(?:onclick|data-href|data-url)\s*=\s*["\'][^"\']*whatsapp\.com\/send\?phone=(\d+)/i', $html, $matches)) {
            foreach ($matches[1] as $number) {
                $phone = '+' . $number;
                if ($this->isValidPhone($phone)) {
                    $socialLinks['whatsapp'] = $socialLinks['whatsapp'] ?? 'https://wa.me/' . $number;
                    $phones[] = $this->normalizePhone($phone);
                }
            }
        }

        // "WhatsApp: +66 123 456 789" or "WhatsApp : 06 12 34 56 78" in text
        if (preg_match_all('/whatsapp\s*[:：]\s*(\+?\d[\d\s.\-()]{7,18}\d)/i', $html, $matches)) {
            foreach ($matches[1] as $phone) {
                $phone = trim($phone);
                if ($this->isValidPhone($phone)) {
                    $normalized = $this->normalizePhone($phone);
                    $phones[] = $normalized;
                    $number = preg_replace('/[^\d]/', '', $normalized);
                    $socialLinks['whatsapp'] = $socialLinks['whatsapp'] ?? 'https://wa.me/' . $number;
                }
            }
        }
    }

    /**
     * Extract phone numbers from tel: links (href="tel:+123456789").
     */
    private function extractTelLinks(string $html, array &$phones): void
    {
        if (preg_match_all('/href\s*=\s*["\']tel:([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $phone) {
                $phone = urldecode(trim($phone));
                $phone = preg_replace('/[^\d+\-\s.]/', '', $phone);
                if ($this->isValidPhone($phone)) {
                    $phones[] = $this->normalizePhone($phone);
                }
            }
        }
    }
}
