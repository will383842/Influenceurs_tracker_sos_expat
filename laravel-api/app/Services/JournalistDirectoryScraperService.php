<?php

namespace App\Services;

use App\Models\PressContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Scrapes journalist directories (annuaires) to discover journalists
 * not covered by the publication-by-publication scraper.
 *
 * Three strategies:
 *   - search     : submit keyword queries → paginate results
 *   - browse     : paginate the full listing without keyword filter
 *   - association: all members are relevant (AEJT, AJEF etc.) → browse all pages
 *
 * Results are saved to press_contacts with source = 'directory'.
 */
class JournalistDirectoryScraperService
{
    private const TIMEOUT     = 25;
    private const MIN_DELAY   = 1500000; // 1.5s
    private const MAX_DELAY   = 3000000; // 3s
    private const MAX_PAGES   = 50;      // safety cap per keyword / browse

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    ];

    // ─── PER-DIRECTORY EXTRACTION CONFIG ─────────────────────────────────────

    /**
     * Per-domain extraction rules.
     * Keys: patterns matched against the domain.
     */
    private const SITE_CONFIGS = [
        'annuaire.journaliste.fr' => [
            'card_pattern'     => '/<(?:div|article|li)[^>]+class="[^"]*(?:journalist|journaliste|member|card|result)[^"]*"[^>]*>(.*?)<\/(?:div|article|li)>/si',
            'name_patterns'    => [
                '/<(?:h2|h3|h4|span)[^>]+class="[^"]*(?:name|nom)[^"]*"[^>]*>([^<]{4,70})</i',
                '/<a[^>]+href="[^"]*\/journaliste\/[^"]*"[^>]*>([^<]{4,70})</i',
            ],
            'email_patterns'   => ['/<a[^>]+href="mailto:([^"]{5,80})"[^>]*>/i'],
            'profile_pattern'  => '/<a[^>]+href="([^"]*\/journaliste\/[^"]{3,80})"[^>]*>/i',
            'pagination_check' => ['rel="next"', 'page='],
        ],
        'presselib.com' => [
            'card_pattern'     => '/<(?:div|article)[^>]+class="[^"]*(?:journalist|card|profile|member)[^"]*"[^>]*>(.*?)<\/(?:div|article)>/si',
            'name_patterns'    => [
                '/<(?:h2|h3|span)[^>]+class="[^"]*(?:name|nom|journalist)[^"]*"[^>]*>([^<]{4,70})</i',
                '/<a[^>]+href="[^"]*\/journaliste\/[^"]*"[^>]*>([^<]{4,70})</i',
            ],
            'email_patterns'   => [
                '/<a[^>]+href="mailto:([^"]{5,80})"[^>]*>/i',
                '/data-email="([^"]{5,80})"/i',
            ],
            'profile_pattern'  => '/<a[^>]+href="([^"]*\/journaliste[^"]{3,80})"[^>]*>/i',
            'pagination_check' => ['rel="next"', '?page=', '/page/'],
        ],
        'aejt.fr' => [
            'card_pattern'     => '/<(?:div|li|article)[^>]+class="[^"]*(?:member|membre|journalist|card)[^"]*"[^>]*>(.*?)<\/(?:div|li|article)>/si',
            'name_patterns'    => [
                '/<(?:h2|h3|h4|strong|span)[^>]*>([A-ZÁÀÂÉÈÊËÏÎÔÙÛÜÇŒ][^<]{3,60})</i',
                '/<a[^>]+class="[^"]*(?:name|nom)[^"]*"[^>]*>([^<]{4,70})</i',
            ],
            'email_patterns'   => ['/<a[^>]+href="mailto:([^"]{5,80})"[^>]*>/i'],
            'profile_pattern'  => '/<a[^>]+href="([^"]*\/membre[^"]{3,80})"[^>]*>/i',
            'extra_field'      => ['beat' => ['tourisme', 'voyage', 'travel']],
            'pagination_check' => ['rel="next"', '?page='],
        ],
        'ajef.net' => [
            'card_pattern'     => '/<(?:div|tr|li)[^>]+class="[^"]*(?:member|membre|journalist|row)[^"]*"[^>]*>(.*?)<\/(?:div|tr|li)>/si',
            'name_patterns'    => [
                '/<(?:td|h3|strong)[^>]*>([A-ZÁÀÂÉÈÊËÏÎÔÙÛÜÇŒ][^<]{3,60})</i',
            ],
            'email_patterns'   => ['/<a[^>]+href="mailto:([^"]{5,80})"[^>]*>/i'],
            'profile_pattern'  => null,
            'extra_field'      => ['beat' => ['économie', 'finance', 'fiscal']],
            'pagination_check' => ['rel="next"', '?page='],
        ],
        // Generic fallback for association sites
        '_default' => [
            'card_pattern'     => null,
            'name_patterns'    => [
                '/<(?:h2|h3|h4|strong)[^>]*>([A-ZÁÀÂÉÈÊËÏÎÔÙÛÜÇŒ][a-záàâéèêëïîôùûüçœ\-]+(?:\s+[A-ZÁÀÂÉÈÊËÏÎÔÙÛÜÇŒ][a-záàâéèêëïîôùûüçœ\-]+){1,3})</u',
            ],
            'email_patterns'   => ['/<a[^>]+href="mailto:([^"]{5,80})"[^>]*>/i'],
            'profile_pattern'  => null,
            'pagination_check' => ['rel="next"', '?page=', '/page/'],
        ],
    ];

    // ─── PUBLIC API ───────────────────────────────────────────────────────────

    /**
     * Scrape a directory source by its slug.
     *
     * @return array{saved: int, skipped: int, pages: int, error: ?string}
     */
    public function scrapeSource(string $slug): array
    {
        $source = DB::table('journalist_directory_sources')->where('slug', $slug)->first();
        if (!$source) {
            return ['saved' => 0, 'skipped' => 0, 'pages' => 0, 'error' => "Source '{$slug}' not found"];
        }

        DB::table('journalist_directory_sources')
            ->where('slug', $slug)
            ->update(['status' => 'running', 'updated_at' => now()]);

        try {
            $result = match ($source->scrape_strategy) {
                'search'      => $this->scrapeBySearch($source),
                'association' => $this->scrapeAssociation($source),
                default       => $this->scrapeByBrowse($source),
            };

            DB::table('journalist_directory_sources')->where('slug', $slug)->update([
                'status'          => 'completed',
                'contacts_found'  => DB::raw("contacts_found + {$result['saved']}"),
                'pages_scraped'   => DB::raw("pages_scraped + {$result['pages']}"),
                'last_scraped_at' => now(),
                'last_error'      => null,
                'updated_at'      => now(),
            ]);

            return $result;

        } catch (\Throwable $e) {
            Log::error("JournalistDirectoryScraperService: error on {$slug}: " . $e->getMessage());
            DB::table('journalist_directory_sources')->where('slug', $slug)->update([
                'status'      => 'failed',
                'last_error'  => $e->getMessage(),
                'updated_at'  => now(),
            ]);
            return ['saved' => 0, 'skipped' => 0, 'pages' => 0, 'error' => $e->getMessage()];
        }
    }

    // ─── STRATEGIES ──────────────────────────────────────────────────────────

    private function scrapeBySearch(object $source): array
    {
        $keywords = json_decode($source->keywords ?? '[]', true) ?: [];
        if (empty($keywords)) {
            return $this->scrapeByBrowse($source);
        }

        $allContacts = [];
        $totalPages  = 0;

        foreach ($keywords as $keyword) {
            $page = 1;
            do {
                $url  = $this->buildUrl($source->search_url, $keyword, $page);
                $html = $this->fetch($url);
                if (!$html) break;

                $contacts    = $this->extractFromPage($html, $url, $source);
                $allContacts = array_merge($allContacts, $contacts);
                $totalPages++;
                $hasNext = $this->hasNextPage($html, $page, $source->base_url);
                $page++;

                usleep(random_int(self::MIN_DELAY, self::MAX_DELAY));

            } while ($hasNext && $page <= self::MAX_PAGES);

            usleep(random_int(2000000, 4000000)); // Extra delay between keywords
        }

        return $this->persistContacts($allContacts, $source->slug, $source->name, $totalPages);
    }

    private function scrapeByBrowse(object $source): array
    {
        $browseUrl   = $source->browse_url ?: ($source->base_url . '?page={page}');
        $allContacts = [];
        $page        = 1;

        do {
            $url  = $this->buildUrl($browseUrl, '', $page);
            $html = $this->fetch($url);
            if (!$html) break;

            $contacts    = $this->extractFromPage($html, $url, $source);
            $allContacts = array_merge($allContacts, $contacts);

            $hasNext = $this->hasNextPage($html, $page, $source->base_url);
            $page++;

            usleep(random_int(self::MIN_DELAY, self::MAX_DELAY));

        } while ($hasNext && $page <= self::MAX_PAGES);

        return $this->persistContacts($allContacts, $source->slug, $source->name, $page - 1);
    }

    private function scrapeAssociation(object $source): array
    {
        // Same as browse — all members are relevant
        return $this->scrapeByBrowse($source);
    }

    // ─── EXTRACTION ──────────────────────────────────────────────────────────

    private function extractFromPage(string $html, string $url, object $source): array
    {
        $domain = $this->extractDomain($source->base_url);
        $config = $this->getConfig($domain);
        $contacts = [];

        // Strategy 1: JSON-LD Person objects
        $jsonContacts = $this->extractJsonLdPersons($html, $url);
        $contacts     = array_merge($contacts, $jsonContacts);

        // Strategy 2: Card-based extraction
        if ($config['card_pattern']) {
            preg_match_all($config['card_pattern'], $html, $cards);
            foreach (($cards[0] ?? []) as $card) {
                $contact = $this->extractFromCard($card, $url, $config);
                if ($contact) $contacts[] = $contact;
            }
        }

        // Strategy 3: mailto links with name context (always run)
        $mailtoContacts = $this->extractMailtoWithContext($html, $url);
        $contacts       = array_merge($contacts, $mailtoContacts);

        // Strategy 4: Generic name + email extraction (fallback)
        if (empty($contacts)) {
            $contacts = $this->extractGeneric($html, $url, $config);
        }

        // Apply keyword filter for search strategy
        $keywords = json_decode($source->keywords ?? '[]', true) ?: [];
        if (!empty($keywords) && $source->scrape_strategy === 'search') {
            // For search results, trust the site's search — no filtering needed
            return $contacts;
        }

        // For browse/association: filter by relevance
        if (!empty($keywords)) {
            $contacts = array_filter($contacts, function ($c) use ($keywords, $html) {
                return $this->isRelevant($c, $keywords, $html);
            });
        }

        return array_values($contacts);
    }

    private function extractFromCard(string $card, string $url, array $config): ?array
    {
        $name  = null;
        $email = null;

        // Extract name
        foreach ($config['name_patterns'] as $pattern) {
            if (preg_match($pattern, $card, $m)) {
                $name = html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $name = trim($name);
                if ($this->isValidName($name)) break;
                $name = null;
            }
        }
        if (!$name) return null;

        // Extract email
        foreach ($config['email_patterns'] as $pattern) {
            if (preg_match($pattern, $card, $m)) {
                $email = strtolower(trim($m[1]));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = null;
                break;
            }
        }

        // Extract profile URL
        $profileUrl = null;
        if ($config['profile_pattern'] && preg_match($config['profile_pattern'], $card, $m)) {
            $profileUrl = $m[1];
        }

        // Extract beat from extra_field config
        $beat = null;
        if (!empty($config['extra_field']['beat'])) {
            $cardLower = mb_strtolower(strip_tags($card));
            foreach ($config['extra_field']['beat'] as $b) {
                if (str_contains($cardLower, $b)) { $beat = ucfirst($b); break; }
            }
        }

        return [
            'full_name'   => $name,
            'email'       => $email,
            'role'        => null,
            'beat'        => $beat,
            'profile_url' => $profileUrl,
            'source_url'  => $url,
        ];
    }

    private function extractJsonLdPersons(string $html, string $url): array
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
                    if (!$this->isValidName($name)) continue;

                    $email = null;
                    if (!empty($item['email'])) {
                        $email = strtolower(str_replace('mailto:', '', $item['email']));
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = null;
                    }

                    $persons[] = [
                        'full_name'   => $name,
                        'email'       => $email,
                        'role'        => $item['jobTitle'] ?? null,
                        'beat'        => null,
                        'profile_url' => $item['url'] ?? null,
                        'source_url'  => $url,
                        'twitter'     => null,
                        'linkedin'    => null,
                    ];
                }
            } catch (\Throwable) {
                // Ignore
            }
        }
        return $persons;
    }

    private function extractMailtoWithContext(string $html, string $url): array
    {
        $contacts = [];
        // <a href="mailto:jean.dupont@pub.fr">Jean Dupont</a>
        preg_match_all(
            '/<a[^>]+href="mailto:([^"@\s]{2,}@[^"]{4,})"[^>]*>([^<]{3,60})<\/a>/i',
            $html, $m
        );
        foreach (($m[1] ?? []) as $i => $email) {
            $email = strtolower(trim($email));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $label = html_entity_decode(strip_tags($m[2][$i] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $label = trim($label);
            if ($this->isValidName($label)) {
                $contacts[] = ['full_name' => $label, 'email' => $email, 'role' => null, 'beat' => null, 'profile_url' => null, 'source_url' => $url];
            }
        }
        return $contacts;
    }

    private function extractGeneric(string $html, string $url, array $config): array
    {
        $contacts = [];
        $emails   = [];

        // Collect all valid emails from the page
        $clean = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $clean = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $clean ?? $html);
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $clean ?? $html, $m);
        foreach (($m[0] ?? []) as $e) {
            $e = strtolower($e);
            if (filter_var($e, FILTER_VALIDATE_EMAIL) && !$this->isGenericEmail($e)) {
                $emails[] = $e;
            }
        }

        // Try each name pattern from config
        $text = strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        foreach ($config['name_patterns'] as $pattern) {
            preg_match_all($pattern, $html, $nameMatches);
            foreach (($nameMatches[1] ?? []) as $name) {
                $name = html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $name = trim($name);
                if (!$this->isValidName($name)) continue;

                // Try to match an email to this name
                $email = $this->matchEmailToName($name, $emails);
                $contacts[] = ['full_name' => $name, 'email' => $email, 'role' => null, 'beat' => null, 'profile_url' => null, 'source_url' => $url];
            }
        }

        return $contacts;
    }

    // ─── PERSISTENCE ─────────────────────────────────────────────────────────

    private function persistContacts(array $contacts, string $sourceSlug, string $sourceName, int $pages): array
    {
        // Deduplicate by name slug
        $unique = [];
        foreach ($contacts as $c) {
            $key = Str::slug($c['full_name'] ?? '');
            if ($key && !isset($unique[$key])) {
                $unique[$key] = $c;
            }
        }

        $saved   = 0;
        $skipped = 0;

        foreach ($unique as $data) {
            if (empty($data['full_name'])) { $skipped++; continue; }

            // Check if already exists in press_contacts
            $exists = PressContact::where('full_name', $data['full_name'])
                ->where(function ($q) use ($sourceSlug) {
                    $q->where('scraped_from', $sourceSlug)
                      ->orWhere('email', '!=', null);
                })->exists();

            if ($exists) { $skipped++; continue; }

            $parts = $this->splitName($data['full_name']);

            PressContact::create([
                'publication_id' => null,
                'publication'    => $sourceName,
                'full_name'      => $data['full_name'],
                'first_name'     => $parts['first'],
                'last_name'      => $parts['last'],
                'email'          => $data['email'] ?? null,
                'email_source'   => $data['email'] ? 'scraped' : null,
                'role'           => $data['role'] ?? null,
                'beat'           => $data['beat'] ?? null,
                'media_type'     => 'directory',
                'source_url'     => $data['source_url'] ?? null,
                'profile_url'    => $data['profile_url'] ?? null,
                'twitter'        => $data['twitter'] ?? null,
                'linkedin'       => $data['linkedin'] ?? null,
                'country'        => 'FR',
                'language'       => 'fr',
                'topics'         => null,
                'contact_status' => 'new',
                'scraped_from'   => $sourceSlug,
                'scraped_at'     => now(),
            ]);
            $saved++;
        }

        return ['saved' => $saved, 'skipped' => $skipped, 'pages' => $pages, 'error' => null];
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

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
                    'Referer'         => 'https://www.google.fr/',
                ])
                ->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable $e) {
            Log::debug("JournalistDirectoryScraperService: fetch failed for {$url}: " . $e->getMessage());
            return null;
        }
    }

    private function buildUrl(string $template, string $keyword, int $page): string
    {
        return str_replace(
            ['{keyword}', '{page}'],
            [urlencode($keyword), $page],
            $template
        );
    }

    private function hasNextPage(string $html, int $currentPage, string $baseUrl): bool
    {
        return str_contains($html, 'rel="next"')
            || str_contains($html, 'page=' . ($currentPage + 1))
            || str_contains($html, '/page/' . ($currentPage + 1));
    }

    private function getConfig(string $domain): array
    {
        foreach (self::SITE_CONFIGS as $key => $config) {
            if ($key === '_default') continue;
            if ($key === $domain || str_contains($domain, $key) || str_contains($key, $domain)) {
                return $config;
            }
        }
        return self::SITE_CONFIGS['_default'];
    }

    private function isValidName(string $name): bool
    {
        $name = trim($name);
        if (strlen($name) < 4 || strlen($name) > 70) return false;
        $words = array_filter(explode(' ', $name));
        if (count($words) < 2) return false;
        if (!preg_match('/^[A-ZÁÀÂÉÈÊËÏÎÔÙÛÜÇŒ]/u', $name)) return false;
        if (preg_match('/[@#<>{}|\\\\\/\d{4}]/', $name)) return false;
        $blacklist = ['La rédaction', 'Par notre', 'AFP', 'Reuters', 'AP Photo', 'Rédaction', 'Notre équipe'];
        foreach ($blacklist as $b) {
            if (stripos($name, $b) !== false) return false;
        }
        return true;
    }

    private function isGenericEmail(string $email): bool
    {
        $generic = ['contact', 'info', 'admin', 'webmaster', 'noreply', 'no-reply', 'press', 'presse', 'redaction', 'secretariat', 'direction', 'privacy'];
        $local   = explode('@', $email)[0] ?? '';
        foreach ($generic as $g) {
            if (str_starts_with($local, $g)) return true;
        }
        return false;
    }

    private function matchEmailToName(string $name, array $emails): ?string
    {
        $parts = $this->splitName($name);
        $first = strtolower(Str::ascii($parts['first'] ?? ''));
        $last  = strtolower(Str::ascii($parts['last'] ?? ''));

        foreach ($emails as $email) {
            $local = explode('@', $email)[0];
            if ($first && str_contains($local, $first)) return $email;
            if ($last  && str_contains($local, $last))  return $email;
        }
        return null;
    }

    private function isRelevant(array $contact, array $keywords, string $pageHtml): bool
    {
        $text = mb_strtolower(($contact['full_name'] ?? '') . ' ' . ($contact['beat'] ?? '') . ' ' . ($contact['role'] ?? ''));
        foreach ($keywords as $kw) {
            if (str_contains($text, mb_strtolower($kw))) return true;
        }
        return false;
    }

    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);
        return count($parts) === 2
            ? ['first' => $parts[0], 'last' => $parts[1]]
            : ['first' => null, 'last' => $fullName];
    }

    private function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        return preg_replace('/^www\./', '', $host) ?? '';
    }
}
