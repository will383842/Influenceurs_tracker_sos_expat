<?php

namespace App\Services\Scraping;

use App\Models\RssBlogFeed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * Option D — P2 : Parser RSS dedie aux blogs pour extraire auteurs + emails.
 *
 * Strategie zero-ban :
 * - Parsing XML public (RSS 2.0 + Atom via SimpleXML natif)
 * - User-Agent declare (SOS-Expat-BloggerBot/1.0)
 * - Timeout 15s, pas de follow redirect agressif
 * - fetch_about max 1x/semaine/feed via cache about_emails
 *
 * Cascade email (ordre priorite) :
 *   N1 : dc:creator / author / itunes:email
 *   N2 : regex email dans content:encoded / description
 *   N3 : match avec about_emails si fetch_about=true
 *   N4 : inference pattern firstname.lastname@domain si fetch_pattern_inference=true
 *
 * Pattern emprunte a :
 *   - RssFetcherService::downloadFeed (ligne 110-134) pour download
 *   - ScrapeJournalistsFromPublications (ligne 248-375) pour extraction mailto/JSON-LD
 */
class BloggerRssParser
{
    private const USER_AGENT = 'Mozilla/5.0 (compatible; SOS-Expat-BloggerBot/1.0; +https://sos-expat.com/bot)';
    private const TIMEOUT_SECONDS = 15;
    private const ABOUT_CACHE_DAYS = 7;

    /** Domaines email personnels a filtrer (pas d'email "officiel" du blog) */
    private const JUNK_EMAIL_DOMAINS = [
        'gmail.com', 'yahoo.com', 'yahoo.fr', 'yahoo.co.uk',
        'hotmail.com', 'hotmail.fr', 'outlook.com', 'outlook.fr',
        'live.com', 'live.fr', 'me.com', 'icloud.com', 'mail.com',
        'aol.com', 'protonmail.com', 'proton.me',
    ];

    /** Paths typiques pour trouver la page About / Contact d'un blog */
    private const ABOUT_PATHS = ['/', '/about', '/about-us', '/a-propos', '/contact', '/contacts', '/equipe', '/team', '/auteurs', '/authors'];

    /** Noms a rejeter (placeholders, admin accounts, etc.) */
    private const INVALID_NAMES = [
        'admin', 'administrator', 'editor', 'editeur', 'redaction', 'rédaction',
        'webmaster', 'contact', 'info', 'team', 'staff', 'auteur', 'author',
        'anonymous', 'anonyme', 'guest', 'user', 'test',
    ];

    /**
     * Parse un feed RSS blog et retourne la liste des auteurs trouves.
     *
     * @return array<int,array{name:string,email:?string,email_source:string,source_url:?string,language:?string,country:?string}>
     */
    public function parse(RssBlogFeed $feed): array
    {
        $xml = $this->downloadFeed($feed->url);
        if ($xml === null) {
            return [];
        }

        $rss = $this->parseXml($xml);
        if ($rss === null) {
            return [];
        }

        // Extraction niveau N1 + N2 depuis les items RSS
        $authors = $this->extractAuthorsFromRss($rss, $feed);

        // Niveau N3 : fetch /about 1x/7j si active et au moins 1 auteur sans email
        $needAbout = $feed->fetch_about && $this->hasAuthorsWithoutEmail($authors);
        if ($needAbout) {
            $aboutEmails = $this->fetchAboutPageOnce($feed);
            $authors = $this->enrichFromAbout($authors, $aboutEmails);
        }

        // Niveau N4 : inference pattern si active
        if ($feed->fetch_pattern_inference) {
            $domain = $this->extractDomainFromFeed($feed);
            if ($domain) {
                $authors = $this->enrichFromPattern($authors, $domain);
            }
        }

        return $this->deduplicate($authors);
    }

    // ─────────────────────────────────────────────────────────────────
    // DOWNLOAD + PARSE XML
    // ─────────────────────────────────────────────────────────────────

    private function downloadFeed(string $url): ?string
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->withHeaders(['User-Agent' => self::USER_AGENT])
                    ->get($url);

                if ($response->successful()) {
                    return $response->body();
                }
                Log::warning("BloggerRssParser: HTTP {$response->status()} pour {$url} (tentative {$attempt})");
            } catch (\Throwable $e) {
                Log::warning("BloggerRssParser: exception download {$url}", ['error' => $e->getMessage(), 'attempt' => $attempt]);
            }
            if ($attempt < 2) sleep(2);
        }
        return null;
    }

    private function parseXml(string $xml): ?SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $rss = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NOERROR);
        if ($rss === false) {
            Log::warning('BloggerRssParser: XML parse failed');
            libxml_clear_errors();
            return null;
        }
        return $rss;
    }

    // ─────────────────────────────────────────────────────────────────
    // EXTRACTION DES AUTEURS (N1 + N2)
    // ─────────────────────────────────────────────────────────────────

    /**
     * @return array<int,array{name:string,email:?string,email_source:string,source_url:?string}>
     */
    private function extractAuthorsFromRss(SimpleXMLElement $rss, RssBlogFeed $feed): array
    {
        $authors = [];

        // Items : RSS 2.0 <channel><item> ou Atom <entry>
        $items = $rss->channel->item ?? $rss->entry ?? [];

        foreach ($items as $item) {
            $itemUrl = (string) ($item->link ?? '');
            // Atom : link est un attribut
            if (empty($itemUrl) && isset($item->link['href'])) {
                $itemUrl = (string) $item->link['href'];
            }

            // Namespaces
            $dc = $item->children('http://purl.org/dc/elements/1.1/');
            $itunes = $item->children('http://www.itunes.com/dtds/podcast-1.0.dtd');
            $contentNs = $item->children('http://purl.org/rss/1.0/modules/content/');

            // N1 — dc:creator (WordPress classique)
            if (isset($dc->creator)) {
                foreach ($dc->creator as $creator) {
                    $name = trim((string) $creator);
                    if ($this->isValidName($name)) {
                        $authors[] = ['name' => $name, 'email' => null, 'email_source' => '', 'source_url' => $itemUrl];
                    }
                }
            }

            // N1 — <author> Atom ou RSS 2.0
            if (isset($item->author)) {
                // RSS 2.0: <author>email@x.com (Name)</author>
                // Atom : <author><name>...</name><email>...</email></author>
                $authorRaw = (string) $item->author;
                $authorName = isset($item->author->name) ? trim((string) $item->author->name) : null;
                $authorEmail = isset($item->author->email) ? trim((string) $item->author->email) : null;

                if ($authorName) {
                    if ($this->isValidName($authorName)) {
                        $authors[] = [
                            'name' => $authorName,
                            'email' => $authorEmail && $this->isValidEmail($authorEmail) ? strtolower($authorEmail) : null,
                            'email_source' => $authorEmail ? 'rss_tag' : '',
                            'source_url' => $itemUrl,
                        ];
                    }
                } elseif ($authorRaw) {
                    // Parse "email@x.com (Name)"
                    if (preg_match('/^([\w\.\-+]+@[\w\.\-]+\.\w+)\s*\(([^)]+)\)/', $authorRaw, $m)) {
                        $name = trim($m[2]);
                        $email = strtolower(trim($m[1]));
                        if ($this->isValidName($name) && $this->isValidEmail($email)) {
                            $authors[] = ['name' => $name, 'email' => $email, 'email_source' => 'rss_tag', 'source_url' => $itemUrl];
                        }
                    } elseif ($this->isValidName($authorRaw)) {
                        $authors[] = ['name' => trim($authorRaw), 'email' => null, 'email_source' => '', 'source_url' => $itemUrl];
                    }
                }
            }

            // N1 — itunes:author + itunes:email (podcasts)
            if (isset($itunes->author)) {
                $name = trim((string) $itunes->author);
                $email = isset($itunes->email) ? strtolower(trim((string) $itunes->email)) : null;
                if ($this->isValidName($name)) {
                    $authors[] = [
                        'name' => $name,
                        'email' => $email && $this->isValidEmail($email) ? $email : null,
                        'email_source' => $email ? 'rss_tag' : '',
                        'source_url' => $itemUrl,
                    ];
                }
            }

            // N2 — regex email dans content:encoded + description
            $content = (string) ($contentNs->encoded ?? $item->description ?? $item->summary ?? '');
            if ($content !== '') {
                $emailsInContent = $this->extractEmailsFromContent($content);
                foreach ($emailsInContent as $email) {
                    // On associe l'email au dernier auteur sans email du meme item, si possible.
                    // Sinon on cree une entree "anonyme" ignoree plus tard (pas d'auteur = skip).
                    foreach (array_reverse(array_keys($authors)) as $idx) {
                        if ($authors[$idx]['source_url'] === $itemUrl && empty($authors[$idx]['email'])) {
                            $authors[$idx]['email'] = $email;
                            $authors[$idx]['email_source'] = 'rss_content';
                            break;
                        }
                    }
                }
            }
        }

        return $authors;
    }

    /**
     * Extrait les emails non-junk d'un contenu HTML/texte.
     * Seuls les emails du domaine du blog sont gardes (pas gmail/yahoo).
     *
     * @return array<int,string>
     */
    public function extractEmailsFromContent(string $content): array
    {
        if ($content === '') return [];
        preg_match_all('/([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/u', $content, $matches);
        $emails = array_unique(array_map('strtolower', $matches[1] ?? []));
        return array_values(array_filter($emails, fn($e) => $this->isValidEmail($e) && !$this->isJunkDomain($e)));
    }

    // ─────────────────────────────────────────────────────────────────
    // N3 — FETCH HOMEPAGE /about (1x par 7j via cache)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Retourne [['name'=>..., 'email'=>...], ...] ou cache si < 7j.
     * Toujours persiste dans about_emails + about_fetched_at.
     *
     * @return array<int,array{name:?string,email:string}>
     */
    public function fetchAboutPageOnce(RssBlogFeed $feed): array
    {
        // Cache valide ? Retour direct sans fetch HTTP.
        if ($feed->hasValidAboutCache()) {
            $cached = $feed->about_emails ?? [];
            return is_array($cached) ? $cached : [];
        }

        $baseUrl = $feed->resolvedBaseUrl();
        if (!$baseUrl) {
            return [];
        }

        $found = [];
        foreach (self::ABOUT_PATHS as $path) {
            $url = $baseUrl . $path;
            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->withHeaders(['User-Agent' => self::USER_AGENT])
                    ->get($url);
                if (!$response->successful()) {
                    continue;
                }
                $html = $response->body();
                $found = array_merge($found, $this->extractMailtoFromHtml($html));
                $found = array_merge($found, $this->extractJsonLdPersons($html));
                // Si on a trouve au moins 1 email, on s'arrete (evite 5 requetes inutiles)
                if (count($found) > 0) break;
            } catch (\Throwable $e) {
                Log::debug('BloggerRssParser: fetch about failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        // Deduplique par email
        $seen = [];
        $deduped = [];
        foreach ($found as $entry) {
            $email = strtolower($entry['email'] ?? '');
            if ($email === '' || isset($seen[$email]) || $this->isJunkDomain($email)) continue;
            $seen[$email] = true;
            $deduped[] = ['name' => $entry['name'] ?? null, 'email' => $email];
        }

        // Cache 7j (meme si vide, evite de retry a chaque run)
        $feed->update([
            'about_emails' => $deduped,
            'about_fetched_at' => now(),
        ]);

        return $deduped;
    }

    /** Extrait mailto:xxx + nom en contexte depuis HTML. */
    private function extractMailtoFromHtml(string $html): array
    {
        $out = [];
        // mailto: dans les href
        preg_match_all('/<a[^>]+href=["\']mailto:([^"\'?]+)[^"\']*["\'][^>]*>([^<]*)<\/a>/iu', $html, $m);
        foreach ($m[1] as $i => $email) {
            $email = trim($email);
            if (!$this->isValidEmail($email)) continue;
            $name = trim(strip_tags($m[2][$i] ?? ''));
            if (!$this->isValidName($name)) $name = null;
            $out[] = ['name' => $name, 'email' => $email];
        }
        return $out;
    }

    /** Extrait JSON-LD Person + Organization contact emails. */
    private function extractJsonLdPersons(string $html): array
    {
        $out = [];
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/siu', $html, $m);
        foreach ($m[1] as $json) {
            $data = json_decode(trim($json), true);
            if (!is_array($data)) continue;
            $out = array_merge($out, $this->walkJsonLdForPersons($data));
        }
        return $out;
    }

    private function walkJsonLdForPersons(array $data): array
    {
        $out = [];
        $type = $data['@type'] ?? null;
        if (is_string($type) && in_array($type, ['Person', 'Organization', 'NewsMediaOrganization'], true)) {
            $email = $data['email'] ?? null;
            $name = $data['name'] ?? null;
            if ($email && $this->isValidEmail($email)) {
                $out[] = ['name' => is_string($name) ? $name : null, 'email' => strtolower($email)];
            }
        }
        // Recurse sur tableaux + @graph
        foreach ($data as $value) {
            if (is_array($value)) {
                $out = array_merge($out, $this->walkJsonLdForPersons($value));
            }
        }
        return $out;
    }

    /**
     * Enrichit les auteurs sans email via matching dans about_emails.
     */
    private function enrichFromAbout(array $authors, array $aboutEmails): array
    {
        if (empty($aboutEmails)) return $authors;

        foreach ($authors as &$author) {
            if (!empty($author['email'])) continue;
            $authorName = strtolower($author['name'] ?? '');
            if ($authorName === '') continue;

            foreach ($aboutEmails as $entry) {
                $aboutName = strtolower($entry['name'] ?? '');
                if ($aboutName !== '' && str_contains($aboutName, $authorName)) {
                    $author['email'] = $entry['email'];
                    $author['email_source'] = 'about';
                    break;
                }
            }
        }
        unset($author);
        return $authors;
    }

    // ─────────────────────────────────────────────────────────────────
    // N4 — INFERENCE PATTERN firstname.lastname@domain
    // ─────────────────────────────────────────────────────────────────

    private function enrichFromPattern(array $authors, string $domain): array
    {
        foreach ($authors as &$author) {
            if (!empty($author['email'])) continue;
            $email = $this->inferEmailFromPattern($author['name'], $domain);
            if ($email) {
                $author['email'] = $email;
                $author['email_source'] = 'pattern';
            }
        }
        unset($author);
        return $authors;
    }

    public function inferEmailFromPattern(string $fullName, string $domain): ?string
    {
        $parts = preg_split('/\s+/', trim($fullName));
        if (!$parts || count($parts) < 2) return null;

        $first = $this->normalize(array_shift($parts));
        $last = $this->normalize(implode('-', $parts));
        if ($first === '' || $last === '') return null;

        $email = "{$first}.{$last}@{$domain}";
        return $this->isValidEmail($email) ? $email : null;
    }

    private function normalize(string $s): string
    {
        $s = strtolower($s);
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        return preg_replace('/[^a-z0-9\-]/', '', $s);
    }

    private function extractDomainFromFeed(RssBlogFeed $feed): ?string
    {
        $baseUrl = $feed->resolvedBaseUrl();
        if (!$baseUrl) return null;
        $host = parse_url($baseUrl, PHP_URL_HOST);
        return $host ? preg_replace('/^www\./', '', $host) : null;
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    private function hasAuthorsWithoutEmail(array $authors): bool
    {
        foreach ($authors as $a) {
            if (empty($a['email'])) return true;
        }
        return false;
    }

    private function isValidName(string $name): bool
    {
        $name = trim($name);
        if (strlen($name) < 3 || strlen($name) > 100) return false;
        if (str_contains($name, '@') || str_contains($name, 'http')) return false;
        if (in_array(strtolower($name), self::INVALID_NAMES, true)) return false;
        // Au moins 1 lettre
        if (!preg_match('/\p{L}/u', $name)) return false;
        return true;
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function isJunkDomain(string $email): bool
    {
        $domain = strtolower(substr($email, strpos($email, '@') + 1));
        return in_array($domain, self::JUNK_EMAIL_DOMAINS, true);
    }

    /**
     * Dedup par (name, email). Si 2 entries avec meme email mais
     * emails differents, on garde celle avec email non null en priorite.
     */
    private function deduplicate(array $authors): array
    {
        $seen = [];
        $result = [];
        foreach ($authors as $a) {
            $key = strtolower(($a['email'] ?? '') ?: $a['name']);
            if (isset($seen[$key])) {
                // Si existant n'a pas d'email et nouveau en a un, remplacer
                if (empty($seen[$key]['email']) && !empty($a['email'])) {
                    $result[$seen[$key]['idx']] = $a;
                    $seen[$key]['email'] = $a['email'];
                }
                continue;
            }
            $seen[$key] = ['idx' => count($result), 'email' => $a['email'] ?? null];
            $result[] = $a;
        }
        return $result;
    }
}
