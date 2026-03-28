<?php

namespace App\Services;

use App\Models\PressContact;
use App\Models\PressPublication;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Scrapes journalist names/emails from:
 *   1. Author index pages (/auteurs/, /redaction/, /reporters/)
 *   2. Article bylines (pagination through articles to extract author names)
 *   3. Individual author profile pages
 *   4. Journalist directory pages (annuaire.journaliste.fr, presselib.com etc.)
 *
 * Once names are collected, the EmailInferenceService generates candidate emails.
 */
class PublicationBylinesScraperService
{
    private const TIMEOUT = 20;
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    ];

    /** Selectors for extracting author names from article pages */
    private const AUTHOR_SELECTORS = [
        'a[rel="author"]',
        '[class*="author-name"]',
        '[class*="author_name"]',
        '[class*="byline"]',
        '[itemprop="author"] [itemprop="name"]',
        '[itemprop="author"]',
        '.journalist-name',
        '.reporter-name',
        'span.author',
        'div.author',
        'p.author',
        '.article-author',
        '.entry-author',
        '.post-author',
        '[class*="signature"]',
        '[class*="journaliste"]',
        'meta[name="author"]',
    ];

    /** Special scraper configs for specific publication domains */
    private const DOMAIN_CONFIGS = [
        'lemonde.fr' => [
            'author_page_pattern'    => '/journaliste/{slug}/',
            'author_link_selector'   => 'a[href*="/journaliste/"]',
            'name_selector'          => 'h1.author__name, .author__fullname',
        ],
        'lefigaro.fr' => [
            'author_page_pattern'    => '/auteur/{slug}/',
            'author_link_selector'   => 'a[href*="/auteur/"]',
            'name_selector'          => 'h1.auteur-name, .auteur__name',
        ],
        'liberation.fr' => [
            'author_link_selector'   => 'a[href*="/auteurs/"]',
            'name_selector'          => 'h1',
        ],
        'bfmtv.com' => [
            'author_page_pattern'    => '/mediaplayer/bio/{slug}/',
            'author_link_selector'   => 'a[href*="/mediaplayer/bio/"]',
            'name_selector'          => 'h1.bio-name, .journalist-name',
        ],
        'france24.com' => [
            'author_link_selector'   => 'a[href*="/fr/reporters/"]',
            'name_selector'          => 'h1.reporter-name, .journalist__name',
        ],
        'rfi.fr' => [
            'author_link_selector'   => 'a[href*="/fr/profil/"]',
            'name_selector'          => 'h1',
        ],
        'presselib.com' => [
            'author_link_selector'   => 'a[href*="/journaliste/"]',
            'name_selector'          => 'h1.entry-title, .journalist-name',
            'email_selector'         => 'a[href^="mailto:"]',
        ],
        'annuaire.journaliste.fr' => [
            'author_link_selector'   => 'a[href*="/journaliste/"], .journalist-card a',
            'name_selector'          => 'h2, h3, .name',
            'pagination'             => true,
        ],
    ];

    /**
     * Scrape all author names (and emails if available) from a publication's author index.
     *
     * @return array{authors: array[], pages_scraped: int, error: ?string}
     */
    public function scrapeAuthorIndex(PressPublication $pub): array
    {
        $authorIndexUrl = $pub->authors_url;
        if (!$authorIndexUrl) {
            return $this->tryFallbackPaths($pub);
        }

        $domain = $this->extractDomain($pub->base_url);
        $config = $this->getDomainConfig($domain);

        $authors  = [];
        $maxPages = 10;

        for ($page = 1; $page <= $maxPages; $page++) {
            $url  = $page > 1 ? $authorIndexUrl . '?page=' . $page : $authorIndexUrl;
            $html = $this->fetch($url);
            if (!$html) break;

            $pageAuthors = $this->extractAuthorsFromPage($html, $url, $pub, $config);
            if (empty($pageAuthors) && $page > 1) break;

            foreach ($pageAuthors as $a) {
                $key = strtolower(Str::slug($a['full_name']));
                if (!isset($authors[$key])) {
                    $authors[$key] = $a;
                }
            }

            // Check if there's a next page
            if (!$this->hasNextPage($html, $page)) break;

            usleep(random_int(1500000, 2500000));
        }

        return ['authors' => array_values($authors), 'pages_scraped' => $page, 'error' => null];
    }

    /**
     * Scrape article listing pages to extract author bylines.
     * This catches journalists not listed in the author index.
     *
     * @return array{authors: array[], articles_scraped: int}
     */
    public function scrapeArticleBylines(PressPublication $pub, int $maxArticlePages = 5): array
    {
        $articlesUrl = $pub->articles_url ?? ($pub->base_url . '/');
        $domain      = $this->extractDomain($pub->base_url);
        $config      = $this->getDomainConfig($domain);

        $authors  = [];
        $scraped  = 0;

        for ($page = 1; $page <= $maxArticlePages; $page++) {
            $url  = $page > 1 ? $articlesUrl . 'page/' . $page . '/' : $articlesUrl;
            $html = $this->fetch($url);
            if (!$html) break;
            $scraped++;

            // Extract author names from bylines
            foreach (self::AUTHOR_SELECTORS as $selector) {
                preg_match_all('/' . $this->selectorToPattern($selector) . '/', $html, $matches);
                foreach (($matches[1] ?? []) as $name) {
                    $name = strip_tags($name);
                    $name = html_entity_decode(trim($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if ($this->isValidJournalistName($name)) {
                        $key = strtolower(Str::slug($name));
                        if (!isset($authors[$key])) {
                            $authors[$key] = ['full_name' => $name, 'email' => null, 'role' => null, 'source_url' => $url, 'profile_url' => null];
                        }
                    }
                }
            }

            // Also extract via meta tags and structured data
            $meta = $this->extractMetaAuthors($html, $url);
            foreach ($meta as $name) {
                $key = strtolower(Str::slug($name));
                if (!isset($authors[$key])) {
                    $authors[$key] = ['full_name' => $name, 'email' => null, 'role' => null, 'source_url' => $url, 'profile_url' => null];
                }
            }

            if (!$this->hasNextPage($html, $page)) break;
            usleep(random_int(1000000, 2000000));
        }

        return ['authors' => array_values($authors), 'articles_scraped' => $scraped];
    }

    /**
     * Visit an author's profile page to extract their email and role.
     */
    public function scrapeAuthorProfile(string $profileUrl): array
    {
        $html = $this->fetch($profileUrl);
        if (!$html) return [];

        $emails = $this->extractEmails($html);
        $phone  = $this->extractPhone($html);
        $role   = $this->extractRole($html);
        $twitter = $this->extractTwitter($html);

        return [
            'email'       => !empty($emails) ? $emails[0] : null,
            'phone'       => $phone,
            'role'        => $role,
            'twitter'     => $twitter,
            'source_url'  => $profileUrl,
        ];
    }

    /**
     * Save a batch of discovered authors to the press_contacts table.
     * Returns number of new records inserted.
     */
    public function saveAuthors(PressPublication $pub, array $authors): int
    {
        $saved = 0;
        foreach ($authors as $author) {
            if (empty($author['full_name'])) continue;
            if (!$this->isValidJournalistName($author['full_name'])) continue;

            // Check uniqueness
            $exists = PressContact::where('publication', $pub->name)
                ->where(function ($q) use ($author) {
                    if (!empty($author['email'])) {
                        $q->where('email', $author['email']);
                    } else {
                        $q->where('full_name', $author['full_name']);
                    }
                })->exists();

            if ($exists) continue;

            $parts = $this->splitName($author['full_name']);

            PressContact::create([
                'publication_id' => $pub->id,
                'publication'    => $pub->name,
                'full_name'      => $author['full_name'],
                'first_name'     => $parts['first'],
                'last_name'      => $parts['last'],
                'email'          => $author['email'] ?? null,
                'email_source'   => $author['email'] ? 'scraped' : null,
                'phone'          => $author['phone'] ?? null,
                'role'           => $author['role'] ?? null,
                'beat'           => $author['beat'] ?? null,
                'media_type'     => $pub->media_type,
                'source_url'     => $author['source_url'] ?? null,
                'profile_url'    => $author['profile_url'] ?? null,
                'twitter'        => $author['twitter'] ?? null,
                'linkedin'       => $author['linkedin'] ?? null,
                'country'        => $pub->country,
                'language'       => $pub->language,
                'topics'         => $pub->topics,
                'contact_status' => 'new',
                'scraped_from'   => $pub->slug,
                'scraped_at'     => now(),
            ]);
            $saved++;
        }

        $total = PressContact::where('publication_id', $pub->id)->count();
        $pub->update([
            'authors_discovered' => $total,
            'contacts_count'     => $total,
            'last_scraped_at'    => now(),
            'status'             => 'scraped',
            'last_error'         => null,
        ]);

        return $saved;
    }

    // ─── PRIVATE HELPERS ─────────────────────────────────────────────────────

    private function tryFallbackPaths(PressPublication $pub): array
    {
        $fallbacks = ['/auteurs/', '/auteur/', '/redaction/', '/equipe/', '/reporters/', '/journalistes/'];
        foreach ($fallbacks as $path) {
            $url  = rtrim($pub->base_url, '/') . $path;
            $html = $this->fetch($url);
            if ($html && strlen($html) > 2000) {
                $authors = $this->extractAuthorsFromPage($html, $url, $pub, []);
                if (!empty($authors)) {
                    // Save the discovered URL
                    $pub->update(['authors_url' => $url]);
                    return ['authors' => $authors, 'pages_scraped' => 1, 'error' => null];
                }
            }
            usleep(500000);
        }
        return ['authors' => [], 'pages_scraped' => 0, 'error' => 'No author index found'];
    }

    private function extractAuthorsFromPage(string $html, string $url, PressPublication $pub, array $config): array
    {
        $authors = [];

        // Strategy 1: Domain-specific author link extraction
        if (!empty($config['author_link_selector'])) {
            $links = $this->extractLinksMatchingPattern($html, $config['author_link_selector'] ?? 'a[href*="/auteur"]');
            foreach ($links as $link) {
                $name = strip_tags($link['text'] ?? '');
                if ($this->isValidJournalistName($name)) {
                    $authors[] = [
                        'full_name'   => $name,
                        'email'       => null,
                        'role'        => null,
                        'source_url'  => $url,
                        'profile_url' => $link['href'] ?? null,
                    ];
                }
            }
        }

        // Strategy 2: Generic author card patterns
        if (empty($authors)) {
            preg_match_all(
                '/<(?:h2|h3|h4|p|span|div)[^>]*class="[^"]*(?:author|journaliste|reporter|redacteur|chroniqueur)[^"]*"[^>]*>([^<]{4,80})</i',
                $html, $m
            );
            foreach (($m[1] ?? []) as $name) {
                $name = html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($this->isValidJournalistName($name)) {
                    $authors[] = ['full_name' => $name, 'email' => null, 'role' => null, 'source_url' => $url, 'profile_url' => null];
                }
            }
        }

        // Strategy 3: JSON-LD Person objects
        $jsonPersons = $this->extractJsonLdPersons($html, $url);
        $authors     = array_merge($authors, $jsonPersons);

        // Strategy 4: mailto: links — extract associated name from context
        preg_match_all('/<a[^>]+href="mailto:([^"@\s]{2,}@[^"]{4,})"[^>]*>([^<]{3,60})<\/a>/i', $html, $m);
        foreach (($m[1] ?? []) as $i => $email) {
            $label = strip_tags($m[2][$i] ?? '');
            if ($this->isValidJournalistName($label)) {
                $authors[] = ['full_name' => $label, 'email' => $email, 'role' => null, 'source_url' => $url, 'profile_url' => null];
            } elseif ($name = $this->guessNameFromEmail($email)) {
                $authors[] = ['full_name' => $name, 'email' => $email, 'role' => null, 'source_url' => $url, 'profile_url' => null];
            }
        }

        return $authors;
    }

    private function extractJsonLdPersons(string $html, string $sourceUrl): array
    {
        $persons = [];
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches);
        foreach (($matches[1] ?? []) as $json) {
            try {
                $data  = json_decode($json, true);
                if (!$data) continue;
                $items = isset($data['@graph']) ? $data['@graph'] : [$data];
                foreach ($items as $item) {
                    $type = is_array($item['@type'] ?? '') ? ($item['@type'][0] ?? '') : ($item['@type'] ?? '');
                    if (strtolower($type) !== 'person') continue;
                    $name = $item['name'] ?? '';
                    if (!$this->isValidJournalistName($name)) continue;

                    $email = null;
                    if (!empty($item['email'])) {
                        $email = str_replace('mailto:', '', $item['email']);
                    }

                    $sameAs = $item['sameAs'] ?? null;
                    $twitter = null;
                    $linkedin = null;
                    if ($sameAs) {
                        $links = is_array($sameAs) ? $sameAs : [$sameAs];
                        foreach ($links as $l) {
                            if (str_contains((string)$l, 'twitter.com') || str_contains((string)$l, 'x.com')) $twitter = (string)$l;
                            if (str_contains((string)$l, 'linkedin.com')) $linkedin = (string)$l;
                        }
                    }

                    $persons[] = [
                        'full_name'   => $name,
                        'email'       => $email,
                        'role'        => $item['jobTitle'] ?? null,
                        'source_url'  => $sourceUrl,
                        'profile_url' => $item['url'] ?? null,
                        'twitter'     => $twitter,
                        'linkedin'    => $linkedin,
                    ];
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }
        return $persons;
    }

    private function extractMetaAuthors(string $html, string $url): array
    {
        $names = [];
        // <meta name="author" content="Jean Dupont">
        preg_match_all('/<meta[^>]+name=["\']author["\'][^>]+content=["\']([^"\']{4,80})["\'][^>]*>/i', $html, $m);
        foreach (($m[1] ?? []) as $name) {
            $name = html_entity_decode(trim($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($this->isValidJournalistName($name)) $names[] = $name;
        }
        return array_unique($names);
    }

    private function extractEmails(string $html): array
    {
        $clean = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $clean = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $clean ?? $html);
        $decoded = html_entity_decode($clean ?? $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $emails = [];
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $decoded, $m);
        $emails = $m[0] ?? [];

        // Obfuscated
        preg_match_all('/([a-zA-Z0-9._%+\-]+)\s*[\[\(]\s*(?:at|arobase|@)\s*[\]\)]\s*([a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i', $decoded, $m2);
        foreach (($m2[0] ?? []) as $i => $match) {
            $emails[] = ($m2[1][$i] ?? '') . '@' . ($m2[2][$i] ?? '');
        }

        return array_unique(array_values(array_filter(array_map('strtolower', $emails), function ($e) {
            if (!filter_var($e, FILTER_VALIDATE_EMAIL)) return false;
            $blocked = ['noreply', 'no-reply', 'wordpress', 'privacy', 'donotreply'];
            foreach ($blocked as $b) { if (str_contains($e, $b)) return false; }
            foreach (['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'] as $d) {
                if (str_ends_with($e, $d)) return false;
            }
            return true;
        })));
    }

    private function extractPhone(string $html): ?string
    {
        $text = strip_tags($html);
        preg_match('/(?:\+33|0033|0)[1-9](?:[.\-\s]?\d{2}){4}/', $text, $m);
        return $m[0] ?? null;
    }

    private function extractRole(string $html): ?string
    {
        $roles = ['rédacteur en chef', 'directeur de la rédaction', 'journaliste', 'reporter', 'correspondant', 'grand reporter', 'rédactrice en chef', 'chef de rubrique'];
        $text  = strtolower(strip_tags($html));
        foreach ($roles as $role) {
            if (str_contains($text, $role)) return ucfirst($role);
        }
        return null;
    }

    private function extractTwitter(string $html): ?string
    {
        preg_match('/https?:\/\/(?:twitter\.com|x\.com)\/([a-zA-Z0-9_]{1,50})/', $html, $m);
        return $m[0] ?? null;
    }

    private function extractLinksMatchingPattern(string $html, string $cssSelector): array
    {
        $links = [];
        // Simple extraction: find <a> tags containing the selector pattern
        $pattern = str_replace(['/auteur/', '/auteurs/', '/journaliste/', '/reporters/', '/profil/', '/bio/'], '', $cssSelector);
        $urlPart  = trim($pattern, 'a[href*=""]');

        preg_match_all('/<a[^>]+href=["\']([^"\']*' . preg_quote($urlPart, '/') . '[^"\']*)["\'][^>]*>(.*?)<\/a>/si', $html, $m);
        foreach (($m[1] ?? []) as $i => $href) {
            $text = strip_tags($m[2][$i] ?? '');
            if (strlen($text) > 2 && strlen($text) < 80) {
                $links[] = ['href' => $href, 'text' => html_entity_decode(trim($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')];
            }
        }
        return $links;
    }

    private function hasNextPage(string $html, int $currentPage): bool
    {
        // Check for pagination: next page link or "Page N of M"
        $hasNext = str_contains($html, 'page=' . ($currentPage + 1))
            || str_contains($html, '/page/' . ($currentPage + 1))
            || preg_match('/rel=["\']next["\']/', $html);
        return (bool) $hasNext;
    }

    private function selectorToPattern(string $selector): string
    {
        // Convert simple CSS selectors to regex patterns for text extraction
        // This is a simplified version
        if (str_starts_with($selector, 'meta')) {
            return '<meta[^>]+name=["\']author["\'][^>]+content=["\']([^"\']{4,80})["\']';
        }
        if (str_contains($selector, '[class*="')) {
            preg_match('/\[class\*="([^"]+)"\]/', $selector, $m);
            $cls = $m[1] ?? 'author';
            return '<[^>]+class="[^"]*' . preg_quote($cls, '/') . '[^"]*"[^>]*>([^<]{4,80})';
        }
        if (str_contains($selector, '[itemprop="author"]')) {
            return '<[^>]+itemprop=["\']author["\'][^>]*>([^<]{4,80})';
        }
        if (str_contains($selector, 'rel="author"')) {
            return '<a[^>]+rel=["\']author["\'][^>]*>([^<]{4,80})';
        }
        return '<[^>]+class="[^"]*(?:author|auteur|byline|journaliste)[^"]*"[^>]*>([^<]{4,80})';
    }

    private function isValidJournalistName(string $name): bool
    {
        $name = trim($name);
        if (strlen($name) < 4 || strlen($name) > 70) return false;
        // Must have at least 2 words (first + last name)
        $words = array_filter(explode(' ', $name));
        if (count($words) < 2) return false;
        // Must start with uppercase
        if (!preg_match('/^[A-ZÁÀÂÉÈÊËÏÎÔÙÛÜÇŒ]/u', $name)) return false;
        // Must not contain obviously non-name characters
        if (preg_match('/[@#<>{}|\\\\\/\d{4}]/', $name)) return false;
        // Blacklist generic terms
        $blacklist = ['Par notre', 'La rédaction', 'Par AFP', 'Par AP', 'Reuters', 'AFP', 'Associated Press',
                      'Nos équipes', 'La Rédaction', 'Rédaction', 'Le service', 'Notre équipe'];
        foreach ($blacklist as $b) {
            if (stripos($name, $b) !== false) return false;
        }
        return true;
    }

    private function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        return preg_replace('/^www\./', '', $host) ?? '';
    }

    private function getDomainConfig(string $domain): array
    {
        foreach (self::DOMAIN_CONFIGS as $key => $config) {
            if ($key === $domain || str_contains($domain, $key)) {
                return $config;
            }
        }
        return [];
    }

    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);
        return count($parts) === 2
            ? ['first' => $parts[0], 'last' => $parts[1]]
            : ['first' => null, 'last' => $fullName];
    }

    private function guessNameFromEmail(string $email): ?string
    {
        $local = explode('@', $email)[0] ?? '';
        $generic = ['contact', 'redaction', 'presse', 'info', 'admin', 'webmaster', 'direction', 'secretariat', 'editorial', 'press'];
        foreach ($generic as $g) {
            if (str_starts_with($local, $g)) return null;
        }
        $parts = preg_split('/[._\-]/', $local);
        if (count($parts) >= 2) {
            return implode(' ', array_map('ucfirst', $parts));
        }
        return null;
    }

    private function fetch(string $url): ?string
    {
        try {
            $ua = self::USER_AGENTS[array_rand(self::USER_AGENTS)];
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'User-Agent'      => $ua,
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.5',
                    'Cache-Control'   => 'no-cache',
                ])
                ->get($url);

            if ($response->successful()) return $response->body();
        } catch (\Throwable $e) {
            Log::debug("PublicationBylinesScraperService: failed to fetch {$url}: " . $e->getMessage());
        }
        return null;
    }
}
