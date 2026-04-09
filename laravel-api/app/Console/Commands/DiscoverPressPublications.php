<?php

namespace App\Console\Commands;

use App\Models\PressPublication;
use App\Services\PressScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Discover French-language press publications automatically by scraping
 * free press directories and aggregator pages. No AI API needed.
 *
 * Strategy:
 *  1. Scrape known press directory pages (Feedspot, ACPM, Wikipedia, etc.)
 *  2. Extract publication names + URLs
 *  3. Deduplicate against existing PressPublication records
 *  4. Insert new ones → PressScraperService handles contact extraction
 *
 * Usage:
 *   php artisan press:discover                          → all categories
 *   php artisan press:discover --category=voyage        → travel only
 *   php artisan press:discover --scrape                 → also scrape contacts after
 *   php artisan press:discover --dry-run                → show without saving
 */
class DiscoverPressPublications extends Command
{
    protected $signature = 'press:discover
                            {--category=all : Category (voyage, expatriation, lifestyle, all)}
                            {--scrape : Also run the press scraper on discovered publications}
                            {--dry-run : Display results without saving}';

    protected $description = 'Discover French press publications by scraping free directories (no AI credits needed)';

    private const TIMEOUT = 25;

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.2 Safari/605.1.15',
    ];

    /**
     * Discovery sources: directories/pages to scrape for publication lists.
     *
     * Each source defines:
     *   - url: page to fetch
     *   - link_pattern: regex to extract publication URLs
     *   - name_pattern: regex to extract publication names (optional, falls back to <a> text)
     *   - topics: default topics assigned to discovered publications
     *   - filter: regex the URL must match to be relevant (optional)
     */
    private const SOURCES = [
        // ─── FEEDSPOT: TOP BLOGS/MAGAZINES BY CATEGORY ───────────────
        'voyage' => [
            [
                'name'    => 'Feedspot — Top French Travel Blogs',
                'url'     => 'https://blog.feedspot.com/french_travel_blogs/',
                'topics'  => ['voyage', 'lifestyle'],
            ],
            [
                'name'    => 'Feedspot — Top Travel Magazines',
                'url'     => 'https://blog.feedspot.com/travel_magazines/',
                'topics'  => ['voyage'],
                'filter'  => '/\.fr|france|french|francais/i',
            ],
            [
                'name'    => 'Feedspot — Tourism Blogs',
                'url'     => 'https://blog.feedspot.com/tourism_blogs/',
                'topics'  => ['voyage', 'business'],
                'filter'  => '/\.fr|france|french|francais/i',
            ],
            // ─── CURATED AGGREGATOR PAGES ─────────────────────────────
            [
                'name'   => 'Webrankinfo — Annuaire voyage',
                'url'    => 'https://www.webrankinfo.com/annuaire/cat-131-Voyage.htm',
                'topics' => ['voyage'],
            ],
            [
                'name'   => 'DMOZ-ODPSearch — Voyage',
                'url'    => 'https://odp.org/search/?q=voyage+magazine+france&lang=fr',
                'topics' => ['voyage'],
            ],
        ],

        'expatriation' => [
            [
                'name'    => 'Feedspot — Expat Blogs',
                'url'     => 'https://blog.feedspot.com/expat_blogs/',
                'topics'  => ['expatriation', 'international'],
                'filter'  => '/\.fr|france|french|francais|expat/i',
            ],
            [
                'name'    => 'Feedspot — French Expat Blogs',
                'url'     => 'https://blog.feedspot.com/french_expat_blogs/',
                'topics'  => ['expatriation', 'voyage'],
            ],
        ],

        'lifestyle' => [
            [
                'name'    => 'Feedspot — French Lifestyle Blogs',
                'url'     => 'https://blog.feedspot.com/french_lifestyle_blogs/',
                'topics'  => ['lifestyle', 'voyage'],
            ],
        ],
    ];

    /**
     * Hardcoded seed: well-known French travel press not always in directories.
     * Only inserted if not already present.
     */
    private const SEED_PUBLICATIONS = [
        'voyage' => [
            ['name' => 'Lonely Planet France', 'url' => 'https://www.lonelyplanet.fr', 'media_type' => 'web'],
            ['name' => 'Voyageons Autrement', 'url' => 'https://www.voyageons-autrement.com', 'media_type' => 'web'],
            ['name' => 'Quotidien du Tourisme', 'url' => 'https://www.quotidiendutourisme.com', 'media_type' => 'web'],
            ['name' => 'Easyvoyage', 'url' => 'https://www.easyvoyage.com', 'media_type' => 'web'],
            ['name' => 'Travel On Move', 'url' => 'https://www.travelonmove.com', 'media_type' => 'web'],
            ['name' => 'A/R Magazine', 'url' => 'https://www.ar-magazine.com', 'media_type' => 'presse_ecrite'],
            ['name' => 'Voyager Luxe', 'url' => 'https://www.voyagerluxe.com', 'media_type' => 'web'],
            ['name' => 'Carnets de Traverse', 'url' => 'https://www.carnets-traverse.com', 'media_type' => 'web'],
            ['name' => 'I-Voyages', 'url' => 'https://www.i-voyages.net', 'media_type' => 'web'],
            ['name' => 'TourduMondiste', 'url' => 'https://www.tourdumondiste.com', 'media_type' => 'web'],
            ['name' => 'Instinct Voyageur', 'url' => 'https://www.instinct-voyageur.fr', 'media_type' => 'web'],
            ['name' => 'Novo-monde', 'url' => 'https://www.novo-monde.com', 'media_type' => 'web'],
            ['name' => 'OneDayOneTravel', 'url' => 'https://www.onedayonetravel.com', 'media_type' => 'web'],
            ['name' => 'Détours en France', 'url' => 'https://www.detoursenfrance.fr', 'media_type' => 'presse_ecrite'],
            ['name' => 'Grands Reportages', 'url' => 'https://www.grandsreportages.com', 'media_type' => 'presse_ecrite'],
            ['name' => 'Voyages et Vagabondages', 'url' => 'https://www.voyagesetvagabondages.com', 'media_type' => 'web'],
            ['name' => 'Le Blog de Sarah', 'url' => 'https://leblogdesarah.com', 'media_type' => 'web'],
            ['name' => 'Madame Oreille', 'url' => 'https://www.madame-oreille.com', 'media_type' => 'web'],
            ['name' => 'Un Sac sur le Dos', 'url' => 'https://unsacsurledos.com', 'media_type' => 'web'],
            ['name' => 'Skyscanner France Mag', 'url' => 'https://www.skyscanner.fr/actualites-voyage', 'media_type' => 'web'],
            ['name' => 'Le Monde Voyage', 'url' => 'https://www.lemonde.fr/voyage', 'media_type' => 'presse_ecrite'],
            ['name' => 'Libération Voyages', 'url' => 'https://www.liberation.fr/voyages', 'media_type' => 'presse_ecrite'],
            ['name' => 'Télérama Voyages', 'url' => 'https://www.telerama.fr/voyages', 'media_type' => 'presse_ecrite'],
            ['name' => 'Vanity Fair France Voyage', 'url' => 'https://www.vanityfair.fr/voyage', 'media_type' => 'presse_ecrite'],
            ['name' => 'Vogue France Voyage', 'url' => 'https://www.vogue.fr/voyage', 'media_type' => 'presse_ecrite'],
            ['name' => 'ELLE Voyage', 'url' => 'https://www.elle.fr/Voyage', 'media_type' => 'presse_ecrite'],
            ['name' => 'Marie Claire Voyage', 'url' => 'https://www.marieclaire.fr/voyage', 'media_type' => 'presse_ecrite'],
            ['name' => 'Glamour Voyage', 'url' => 'https://www.glamourparis.com/voyage', 'media_type' => 'web'],
            ['name' => 'Grazia Voyage', 'url' => 'https://www.grazia.fr/voyage', 'media_type' => 'web'],
            ['name' => 'AD Magazine Voyage', 'url' => 'https://www.admagazine.fr/voyage', 'media_type' => 'presse_ecrite'],
            ['name' => 'Trek Magazine', 'url' => 'https://www.trekmag.com', 'media_type' => 'presse_ecrite'],
            ['name' => 'Ulysse Magazine', 'url' => 'https://www.ulysse-magazine.com', 'media_type' => 'presse_ecrite'],
            ['name' => 'Terre Sauvage', 'url' => 'https://www.terresauvage.com', 'media_type' => 'presse_ecrite'],
            ['name' => 'Voyages d\'Affaires', 'url' => 'https://www.voyages-d-affaires.com', 'media_type' => 'web'],
            ['name' => 'Business Travel', 'url' => 'https://www.businesstravel.fr', 'media_type' => 'web'],
            ['name' => 'TravellerMag', 'url' => 'https://www.travellermag.fr', 'media_type' => 'web'],
            ['name' => 'Condé Nast Traveller France', 'url' => 'https://www.condenasttraveller.com/fr', 'media_type' => 'presse_ecrite'],
            // Belgique / Suisse / Canada
            ['name' => 'Moustique Voyages (BE)', 'url' => 'https://www.moustique.be/voyages', 'media_type' => 'presse_ecrite'],
            ['name' => 'Le Vif Weekend Voyages (BE)', 'url' => 'https://weekend.levif.be/destinations', 'media_type' => 'presse_ecrite'],
            ['name' => 'Le Soir Voyages (BE)', 'url' => 'https://www.lesoir.be/voyages', 'media_type' => 'presse_ecrite'],
            ['name' => 'Touring Magazine (BE)', 'url' => 'https://www.touring.be/fr/magazine', 'media_type' => 'presse_ecrite'],
            ['name' => 'Le Temps Voyages (CH)', 'url' => 'https://www.letemps.ch/voyages', 'media_type' => 'presse_ecrite'],
            ['name' => 'Espaces (CA)', 'url' => 'https://www.espaces.ca', 'media_type' => 'web'],
            ['name' => 'Globe-Trotters (CA)', 'url' => 'https://www.globe-trotters.net', 'media_type' => 'web'],
            ['name' => 'PAX Nouvelles (CA)', 'url' => 'https://www.paxnouvelles.com', 'media_type' => 'web'],
        ],

        'expatriation' => [
            ['name' => 'Vivre à l\'étranger', 'url' => 'https://www.vivrealetranger.com', 'media_type' => 'web'],
            ['name' => 'MyExpatJob', 'url' => 'https://www.myexpatjob.com', 'media_type' => 'web'],
            ['name' => 'FemmExpat', 'url' => 'https://www.femmexpat.com', 'media_type' => 'web'],
            ['name' => 'Expatriation.com', 'url' => 'https://www.expatriation.com', 'media_type' => 'web'],
            ['name' => 'MondeExpat', 'url' => 'https://www.mondeexpat.com', 'media_type' => 'web'],
            ['name' => 'VoyageForum', 'url' => 'https://voyageforum.com', 'media_type' => 'web'],
        ],
    ];

    /**
     * Default team/contact paths.
     */
    private const TEAM_PATHS   = ['/equipe', '/la-redaction', '/redaction', '/qui-sommes-nous', '/a-propos'];
    private const CONTACT_PATHS = ['/contact', '/contactez-nous', '/mentions-legales'];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $doScrape = $this->option('scrape');
        $categoryFilter = strtolower($this->option('category') ?? 'all');

        $categories = $categoryFilter === 'all'
            ? array_keys(self::SOURCES)
            : [$categoryFilter];

        $this->info('=== Press Publication Discovery (free scraping) ===');
        $this->info('Categories: ' . implode(', ', $categories) . ($isDryRun ? ' [DRY RUN]' : ''));
        $this->newLine();

        $totalFound    = 0;
        $totalInserted = 0;
        $totalDup      = 0;
        $insertedIds   = [];

        foreach ($categories as $category) {
            $this->line("--- <fg=cyan>{$category}</> ---");

            // Phase 1: Seed publications (hardcoded known publications)
            $seedPubs = self::SEED_PUBLICATIONS[$category] ?? [];
            if (!empty($seedPubs)) {
                $this->line("  Phase 1: " . count($seedPubs) . " known publications...");

                foreach ($seedPubs as $pub) {
                    $result = $this->insertPublication($pub['name'], $pub['url'], $pub['media_type'] ?? 'web', [$category], $isDryRun);

                    match ($result['status']) {
                        'inserted' => (function () use (&$totalInserted, &$insertedIds, $result, $pub) {
                            $totalInserted++;
                            if ($result['id']) $insertedIds[] = $result['id'];
                            $this->line("    <fg=green>NEW</> {$pub['name']}");
                        })(),
                        'duplicate' => (function () use (&$totalDup) { $totalDup++; })(),
                        default => null,
                    };
                    $totalFound++;
                }
            }

            // Phase 2: Scrape directories for more publications
            $sources = self::SOURCES[$category] ?? [];
            if (!empty($sources)) {
                $this->line("  Phase 2: Scraping " . count($sources) . " directories...");

                foreach ($sources as $source) {
                    $this->line("    Fetching: {$source['name']}...");

                    $discovered = $this->scrapeDirectory($source);

                    foreach ($discovered as $pub) {
                        $result = $this->insertPublication(
                            $pub['name'], $pub['url'],
                            $pub['media_type'] ?? 'web',
                            $source['topics'] ?? [$category],
                            $isDryRun
                        );

                        match ($result['status']) {
                            'inserted' => (function () use (&$totalInserted, &$insertedIds, $result, $pub) {
                                $totalInserted++;
                                if ($result['id']) $insertedIds[] = $result['id'];
                                $this->line("      <fg=green>NEW</> {$pub['name']} | {$pub['url']}");
                            })(),
                            'duplicate' => (function () use (&$totalDup) { $totalDup++; })(),
                            default => null,
                        };
                        $totalFound++;
                    }

                    usleep(random_int(2_000_000, 4_000_000)); // 2-4s between directories
                }
            }

            $this->newLine();
        }

        // Summary
        $this->newLine();
        $this->info('=== SUMMARY ===');
        $this->line("Total checked       : {$totalFound}");
        $this->line("New (inserted)      : {$totalInserted}");
        $this->line("Duplicates (skipped): {$totalDup}");
        $this->line("Total in DB         : " . PressPublication::count());

        // Phase 3: scrape contacts from newly discovered publications
        if ($doScrape && !$isDryRun && !empty($insertedIds)) {
            $this->newLine();
            $this->info("=== SCRAPING CONTACTS FROM {$totalInserted} NEW PUBLICATIONS ===");

            $scraper       = app(PressScraperService::class);
            $totalContacts = 0;

            foreach ($insertedIds as $pubId) {
                $pub = PressPublication::find($pubId);
                if (!$pub) continue;

                $this->line("  Scraping {$pub->name}...");

                try {
                    $result = $scraper->scrapePublication($pub);

                    if (!empty($result['contacts'])) {
                        $saved = $scraper->saveContacts($pub, $result['contacts']);
                        $totalContacts += $saved;
                        $this->line("    <fg=green>{$saved} contacts saved</>");
                    } else {
                        $pub->update(['status' => 'no_contacts', 'last_scraped_at' => now()]);
                        $this->line("    <fg=yellow>No contacts found</>");
                    }
                } catch (\Throwable $e) {
                    $pub->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
                    $this->line("    <fg=red>Error: " . Str::limit($e->getMessage(), 80) . "</>");
                }

                usleep(random_int(2_000_000, 4_000_000));
            }

            $this->newLine();
            $this->info("Total contacts scraped: {$totalContacts}");
        }

        return 0;
    }

    // =========================================================================
    // DIRECTORY SCRAPING
    // =========================================================================

    /**
     * Scrape a directory page and extract publication name + URL pairs.
     *
     * @return array<array{name: string, url: string, media_type: string}>
     */
    private function scrapeDirectory(array $source): array
    {
        $html = $this->fetchPage($source['url']);
        if (!$html) {
            $this->line("      <fg=red>Failed to fetch</>");
            return [];
        }

        $publications = [];
        $filter       = $source['filter'] ?? null;

        // Strategy 1: Feedspot-style — extract from <h3> with links or data-url
        preg_match_all(
            '/<(?:h2|h3|h4)[^>]*>\s*<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i',
            $html, $headingLinks
        );

        foreach (($headingLinks[1] ?? []) as $i => $url) {
            $name = html_entity_decode(strip_tags($headingLinks[2][$i] ?? ''), ENT_QUOTES, 'UTF-8');
            $name = trim($name);
            $url  = trim($url);

            if (!$name || strlen($name) < 3 || strlen($name) > 120) continue;
            if (!$this->isValidPublicationUrl($url)) continue;
            if ($filter && !preg_match($filter, $url . ' ' . $name)) continue;

            $publications[] = ['name' => $name, 'url' => $this->normalizeUrl($url), 'media_type' => 'web'];
        }

        // Strategy 2: Generic <a> links with title or text that look like publication names
        if (count($publications) < 5) {
            preg_match_all(
                '/<a[^>]+href=["\']([^"\']+)["\'][^>]*(?:title=["\']([^"\']+)["\'])?[^>]*>([^<]{4,80})<\/a>/i',
                $html, $allLinks
            );

            foreach (($allLinks[1] ?? []) as $i => $url) {
                $name = trim($allLinks[2][$i] ?? '') ?: trim(strip_tags($allLinks[3][$i] ?? ''));
                $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
                $url  = trim($url);

                if (!$name || strlen($name) < 5 || strlen($name) > 100) continue;
                if (!$this->isValidPublicationUrl($url)) continue;
                if ($filter && !preg_match($filter, $url . ' ' . $name)) continue;

                // Skip nav/footer links
                if (preg_match('/^(Home|Contact|About|Login|Sign|Privacy|Terms|Cookie|Menu|Blog|RSS)/i', $name)) continue;

                $publications[] = ['name' => $name, 'url' => $this->normalizeUrl($url), 'media_type' => 'web'];
            }
        }

        // Deduplicate by domain
        $seen   = [];
        $unique = [];
        foreach ($publications as $pub) {
            $domain = $this->extractDomain($pub['url']);
            if ($domain && !isset($seen[$domain])) {
                $seen[$domain] = true;
                $unique[]      = $pub;
            }
        }

        $this->line("      " . count($unique) . " publications extracted");
        return $unique;
    }

    // =========================================================================
    // INSERT
    // =========================================================================

    /**
     * @return array{status: string, id: ?int}
     */
    private function insertPublication(string $name, string $url, string $mediaType, array $topics, bool $isDryRun): array
    {
        $url  = $this->normalizeUrl($url);
        $slug = Str::slug($name);

        if (!$url || !$slug) {
            return ['status' => 'skipped', 'id' => null];
        }

        $domain = $this->extractDomain($url);

        // Check duplicate by slug or domain
        $exists = PressPublication::where('slug', $slug)
            ->orWhere('base_url', 'LIKE', "%{$domain}%")
            ->exists();

        if ($exists) {
            return ['status' => 'duplicate', 'id' => null];
        }

        if ($isDryRun) {
            return ['status' => 'inserted', 'id' => null];
        }

        $base = rtrim($url, '/');

        $record = PressPublication::create([
            'name'        => $name,
            'slug'        => $slug,
            'base_url'    => $base,
            'team_url'    => $base . self::TEAM_PATHS[0],
            'contact_url' => $base . self::CONTACT_PATHS[0],
            'media_type'  => $this->normalizeMediaType($mediaType),
            'topics'      => $topics,
            'language'    => 'fr',
            'country'     => 'France',
            'status'      => 'pending',
        ]);

        return ['status' => 'inserted', 'id' => $record->id];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function fetchPage(string $url): ?string
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

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable $e) {
            Log::debug("DiscoverPressPublications: fetch failed for {$url}: " . $e->getMessage());
            return null;
        }
    }

    private function normalizeUrl(string $url): ?string
    {
        if (!$url) return null;
        if (!str_starts_with($url, 'http')) {
            $url = 'https://' . ltrim($url, '/');
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? rtrim($url, '/') : null;
    }

    private function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        return preg_replace('/^www\./', '', $host) ?? '';
    }

    private function isValidPublicationUrl(string $url): bool
    {
        if (!$url || strlen($url) < 10) return false;
        if (!str_starts_with($url, 'http')) return false;

        // Skip social media, aggregators, and non-publication URLs
        $blocked = ['facebook.com', 'twitter.com', 'x.com', 'instagram.com', 'youtube.com',
                     'linkedin.com', 'tiktok.com', 'pinterest.com', 'feedspot.com',
                     'wikipedia.org', 'amazon.', 'google.', 'apple.com', 'play.google.com'];
        foreach ($blocked as $b) {
            if (str_contains(strtolower($url), $b)) return false;
        }

        return true;
    }

    private function normalizeMediaType(string $type): string
    {
        $type = strtolower(trim($type));
        return match (true) {
            in_array($type, ['presse_ecrite', 'presse', 'print', 'magazine']) => 'presse_ecrite',
            in_array($type, ['tv', 'television']) => 'tv',
            $type === 'radio' => 'radio',
            default => 'web',
        };
    }
}
