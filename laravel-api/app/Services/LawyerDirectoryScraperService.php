<?php

namespace App\Services;

use App\Models\Lawyer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LawyerDirectoryScraperService
{
    private const RATE_LIMIT_SECONDS = 2;
    private const USER_AGENT = 'Mozilla/5.0 (compatible; SOSExpatBot/1.0; +https://sos-expat.com)';
    private const TIMEOUT = 20;

    private float $lastRequestTime = 0;

    public const COUNTRY_CODES = [
        'afghanistan'=>'AF','albania'=>'AL','algeria'=>'DZ','angola'=>'AO','argentina'=>'AR',
        'armenia'=>'AM','australia'=>'AU','austria'=>'AT','azerbaijan'=>'AZ','bahamas'=>'BS',
        'bahrain'=>'BH','bangladesh'=>'BD','barbados'=>'BB','belgium'=>'BE','benin'=>'BJ',
        'bermuda'=>'BM','bolivia'=>'BO','bosnia-and-herzegovina'=>'BA','botswana'=>'BW',
        'brazil'=>'BR','brunei'=>'BN','bulgaria'=>'BG','burkina-faso'=>'BF','burundi'=>'BI',
        'cambodia'=>'KH','cameroon'=>'CM','canada'=>'CA','cape-verde'=>'CV','cayman-islands'=>'KY',
        'chad'=>'TD','chile'=>'CL','china'=>'CN','colombia'=>'CO','congo'=>'CG',
        'costa-rica'=>'CR','croatia'=>'HR','cuba'=>'CU','cyprus'=>'CY','czech-republic'=>'CZ',
        'democratic-republic-of-the-congo'=>'CD','denmark'=>'DK','dominican-republic'=>'DO',
        'east-anglia'=>'GB','ecuador'=>'EC','egypt'=>'EG','el-salvador'=>'SV',
        'equatorial-guinea'=>'GQ','estonia'=>'EE','ethiopia'=>'ET','finland'=>'FI',
        'france'=>'FR','gabon'=>'GA','gambia'=>'GM','georgia'=>'GE','germany'=>'DE',
        'ghana'=>'GH','gibraltar'=>'GI','greece'=>'GR','greenland'=>'GL','grenada'=>'GD',
        'guatemala'=>'GT','guernsey'=>'GG','guinea'=>'GN','honduras'=>'HN','hong-kong'=>'HK',
        'hungary'=>'HU','iceland'=>'IS','india'=>'IN','indonesia'=>'ID','iran'=>'IR',
        'iraq'=>'IQ','ireland'=>'IE','isle-of-man'=>'IM','israel'=>'IL','italy'=>'IT',
        'ivory-coast'=>'CI','jamaica'=>'JM','japan'=>'JP','jersey'=>'JE','jordan'=>'JO',
        'kazakhstan'=>'KZ','kenya'=>'KE','kosovo'=>'XK','kuwait'=>'KW','kyrgyzstan'=>'KG',
        'laos'=>'LA','latvia'=>'LV','lebanon'=>'LB','lesotho'=>'LS','libya'=>'LY',
        'liechtenstein'=>'LI','lithuania'=>'LT','london'=>'GB','luxembourg'=>'LU',
        'macau'=>'MO','madagascar'=>'MG','malawi'=>'MW','malaysia'=>'MY','maldives'=>'MV',
        'mali'=>'ML','malta'=>'MT','mauritania'=>'MR','mauritius'=>'MU','mexico'=>'MX',
        'moldova'=>'MD','monaco'=>'MC','mongolia'=>'MN','montenegro'=>'ME','morocco'=>'MA',
        'mozambique'=>'MZ','myanmar'=>'MM','namibia'=>'NA','nepal'=>'NP','netherlands'=>'NL',
        'new-zealand'=>'NZ','nicaragua'=>'NI','niger'=>'NE','nigeria'=>'NG',
        'north-macedonia'=>'MK','north'=>'GB','north-west'=>'GB','northern-ireland'=>'GB',
        'norway'=>'NO','oman'=>'OM','pakistan'=>'PK','panama'=>'PA','papua-new-guinea'=>'PG',
        'paraguay'=>'PY','peru'=>'PE','philippines'=>'PH','poland'=>'PL','portugal'=>'PT',
        'puerto-rico'=>'PR','qatar'=>'QA','romania'=>'RO','rwanda'=>'RW',
        'saudi-arabia'=>'SA','scotland'=>'GB','senegal'=>'SN','serbia'=>'RS',
        'seychelles'=>'SC','sierra-leone'=>'SL','singapore'=>'SG','slovakia'=>'SK',
        'slovenia'=>'SI','south-africa'=>'ZA','south-east'=>'GB','south-korea'=>'KR',
        'south-west'=>'GB','spain'=>'ES','sri-lanka'=>'LK','sudan'=>'SD',
        'swaziland'=>'SZ','sweden'=>'SE','switzerland'=>'CH','syria'=>'SY',
        'taiwan'=>'TW','tajikistan'=>'TJ','tanzania'=>'TZ','thailand'=>'TH',
        'tunisia'=>'TN','turkey'=>'TR','turkmenistan'=>'TM','turks-and-caicos-islands'=>'TC',
        'uganda'=>'UG','ukraine'=>'UA','united-arab-emirates'=>'AE','united-kingdom'=>'GB',
        'united-states'=>'US','uruguay'=>'UY','uzbekistan'=>'UZ','venezuela'=>'VE',
        'vietnam'=>'VN','wales'=>'GB','west-midlands'=>'GB','yemen'=>'YE',
        'yorkshire'=>'GB','zambia'=>'ZM','zimbabwe'=>'ZW',
    ];

    public function rateLimitSleep(): void
    {
        $elapsed = microtime(true) - $this->lastRequestTime;
        if ($elapsed < self::RATE_LIMIT_SECONDS) {
            usleep((int)(((self::RATE_LIMIT_SECONDS - $elapsed)) * 1_000_000));
        }
        $this->lastRequestTime = microtime(true);
    }

    public function fetchPage(string $url): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }
            Log::warning('LawyerScraper: HTTP error', ['url' => $url, 'status' => $response->status()]);
            return null;
        } catch (\Throwable $e) {
            Log::warning('LawyerScraper: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function extractEmails(string $html): array
    {
        $emails = [];

        // mailto: links
        preg_match_all('/href=["\']mailto:([^"\'?]+)/i', $html, $m);
        foreach ($m[1] as $email) {
            $email = strtolower(trim($email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) $emails[] = $email;
        }

        // Plain text emails
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $html, $m);
        foreach ($m[0] as $email) {
            $email = strtolower(trim($email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && !str_ends_with($email, '.png') && !str_ends_with($email, '.jpg')) {
                $emails[] = $email;
            }
        }

        // CloudFlare email protection decode
        preg_match_all('/data-cfemail="([a-f0-9]+)"/i', $html, $m);
        foreach ($m[1] as $encoded) {
            $decoded = $this->decodeCfEmail($encoded);
            if ($decoded && filter_var($decoded, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($decoded);
            }
        }

        $emails = array_unique($emails);
        $emails = array_filter($emails, fn($e) =>
            !str_contains($e, 'noreply') && !str_contains($e, 'no-reply') &&
            !str_contains($e, 'example.com') && !str_contains($e, 'sentry.io') &&
            !str_contains($e, 'wixpress.com')
        );

        return array_values($emails);
    }

    private function decodeCfEmail(string $encoded): ?string
    {
        try {
            $key = hexdec(substr($encoded, 0, 2));
            $email = '';
            for ($i = 2; $i < strlen($encoded); $i += 2) {
                $email .= chr(hexdec(substr($encoded, $i, 2)) ^ $key);
            }
            return $email;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getCountryCode(string $countrySlug): ?string
    {
        return self::COUNTRY_CODES[strtolower($countrySlug)] ?? null;
    }

    // ═══════════════════════════════════════════════════
    //  LEGAL500 — 200+ countries
    // ═══════════════════════════════════════════════════

    public function legal500DiscoverCountries(): array
    {
        $html = $this->fetchPage('https://www.legal500.com/rankings');
        if (!$html) return [];

        $countries = [];
        preg_match_all('#href="/c/([a-z][a-z0-9\-]+)"[^>]*>([^<]+)<#i', $html, $matches, PREG_SET_ORDER);

        $seen = [];
        foreach ($matches as $m) {
            $slug = $m[1];
            $name = html_entity_decode(trim($m[2]), ENT_QUOTES, 'UTF-8');
            if (str_starts_with($slug, 'worldwide') || str_contains($slug, '-bar')) continue;
            if (isset($seen[$slug])) continue;
            $seen[$slug] = true;

            $countryCode = $this->getCountryCode($slug);
            if ($countryCode && Lawyer::isFrancophone($countryCode)) continue;

            $countries[] = [
                'slug' => $slug, 'name' => $name, 'country_code' => $countryCode,
                'url' => "https://www.legal500.com/c/{$slug}/",
            ];
        }

        Log::info('Legal500: discovered countries', ['count' => count($countries)]);
        return $countries;
    }

    public function legal500ScrapeFirms(string $countrySlug, string $countryName, ?string $countryCode): array
    {
        $url = "https://www.legal500.com/c/{$countrySlug}/";
        $html = $this->fetchPage($url);
        if (!$html) return [];

        $firms = [];
        // Legal500 uses complex HTML — just extract firm IDs and slugs from href
        preg_match_all('#href="/firms/(\d+)-([^"]+)/c-' . preg_quote($countrySlug, '#') . '"#i', $html, $matches, PREG_SET_ORDER);

        $seen = [];
        foreach ($matches as $m) {
            $firmId = $m[1]; $firmSlug = $m[2];
            if (isset($seen[$firmId])) continue;
            $seen[$firmId] = true;

            // Derive name from slug (will be overridden if found on contact page)
            $firmName = ucwords(str_replace(['-', ' Llp', ' Llc'], [' ', ' LLP', ' LLC'], $firmSlug));

            $firms[] = [
                'firm_id' => $firmId, 'firm_slug' => $firmSlug, 'firm_name' => $firmName,
                'country' => $countryName, 'country_slug' => $countrySlug, 'country_code' => $countryCode,
                'contact_url' => "https://www.legal500.com/firms/{$firmId}-{$firmSlug}/c-{$countrySlug}/contact",
                'lawyers_url' => "https://www.legal500.com/firms/{$firmId}-{$firmSlug}/c-{$countrySlug}/lawyers",
            ];
        }

        return $firms;
    }

    public function legal500ScrapeFirmContact(array $firm): array
    {
        $lawyers = [];

        // Scrape lawyers list
        $html = $this->fetchPage($firm['lawyers_url']);
        $lawyerProfiles = [];
        if ($html) {
            preg_match_all('#<a[^>]*href="(/firms/[^"]*lawyers/[^"]*)"[^>]*>([^<]+)</a>#i', $html, $m, PREG_SET_ORDER);
            foreach ($m as $match) {
                $name = html_entity_decode(trim($match[2]), ENT_QUOTES, 'UTF-8');
                if ($name && $name !== 'Firm profile' && strlen($name) > 2) {
                    $lawyerProfiles[] = ['name' => $name, 'url' => 'https://www.legal500.com' . $match[1]];
                }
            }
        }

        // Scrape contact page
        $this->rateLimitSleep();
        $contactHtml = $this->fetchPage($firm['contact_url']);
        if (!$contactHtml) return [];

        $emails = $this->extractEmails($contactHtml);
        $phones = []; $website = null;

        preg_match_all('/(?:\+|00)[1-9]\d{0,3}[\s\-.]?\(?\d{1,4}\)?[\s\-.]?\d{2,4}[\s\-.]?\d{2,4}[\s\-.]?\d{0,4}/', $contactHtml, $phoneMatches);
        foreach ($phoneMatches[0] as $phone) {
            $clean = preg_replace('/[^\d+]/', '', $phone);
            if (strlen($clean) >= 8) $phones[] = $phone;
        }

        if (preg_match('#href="(https?://(?!www\.legal500)[^"]+)"[^>]*>.*?(?:website|\.com|\.co\.|\.org|\.net)#is', $contactHtml, $wm)) {
            $website = $wm[1];
        }

        $address = null;
        preg_match_all('#<p[^>]*class="[^"]*address[^"]*"[^>]*>(.*?)</p>#is', $contactHtml, $addrMatches);
        if (!empty($addrMatches[1])) {
            $address = strip_tags(html_entity_decode($addrMatches[1][0], ENT_QUOTES, 'UTF-8'));
        }

        if (!empty($emails)) {
            if (!empty($lawyerProfiles)) {
                foreach ($lawyerProfiles as $i => $profile) {
                    $email = $emails[$i] ?? $emails[0] ?? null;
                    if (!$email) break;
                    $nameParts = $this->parseName($profile['name']);
                    $lawyers[] = [
                        'full_name' => $profile['name'], 'first_name' => $nameParts['first'],
                        'last_name' => $nameParts['last'], 'email' => $email,
                        'phone' => $phones[0] ?? null, 'firm_name' => $firm['firm_name'],
                        'website' => $website, 'country' => $firm['country'],
                        'country_code' => $firm['country_code'], 'address' => $address,
                        'source_url' => $firm['contact_url'],
                    ];
                }
            } else {
                foreach ($emails as $email) {
                    $lawyers[] = [
                        'full_name' => $firm['firm_name'], 'email' => $email,
                        'phone' => $phones[0] ?? null, 'firm_name' => $firm['firm_name'],
                        'website' => $website, 'country' => $firm['country'],
                        'country_code' => $firm['country_code'], 'address' => $address,
                        'source_url' => $firm['contact_url'],
                    ];
                }
            }
        }

        // Fallback: try firm website
        if (empty($lawyers) && $website) {
            $this->rateLimitSleep();
            $siteEmails = $this->enrichFromWebsite($website);
            if (!empty($siteEmails)) {
                $lawyers[] = [
                    'full_name' => $firm['firm_name'], 'email' => $siteEmails[0],
                    'phone' => $phones[0] ?? null, 'firm_name' => $firm['firm_name'],
                    'website' => $website, 'country' => $firm['country'],
                    'country_code' => $firm['country_code'], 'address' => $address,
                    'source_url' => $website,
                ];
            }
        }

        return $lawyers;
    }

    // ═══════════════════════════════════════════════════
    //  LAWYER.COM — US
    // ═══════════════════════════════════════════════════

    public function lawyerComScrapeByState(string $state): array
    {
        $url = "https://www.lawyer.com/{$state}-lawyer.htm";
        $html = $this->fetchPage($url);
        if (!$html) return [];

        $lawyers = [];
        preg_match_all('#href="(/[a-z\-]+-\d+\.html)"#i', $html, $matches);

        foreach (array_unique($matches[1]) as $profilePath) {
            $this->rateLimitSleep();
            $profileUrl = "https://www.lawyer.com{$profilePath}";
            $profileHtml = $this->fetchPage($profileUrl);
            if (!$profileHtml) continue;

            $lawyer = $this->parseLawyerComProfile($profileHtml, $profileUrl);
            if ($lawyer && !empty($lawyer['email'])) $lawyers[] = $lawyer;
        }

        return $lawyers;
    }

    private function parseLawyerComProfile(string $html, string $url): ?array
    {
        $name = null;
        if (preg_match('#<h1[^>]*>([^<]+)</h1>#i', $html, $m)) {
            $name = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }
        if (!$name) return null;

        $emails = $this->extractEmails($html);
        if (empty($emails)) return null;

        $phone = null;
        if (preg_match('/(?:tel:|phone)[^"]*"?([+\d\s\-().]{7,20})/i', $html, $m)) $phone = trim($m[1]);

        $city = $state = null;
        if (preg_match('/(\w[\w\s]+),\s*([A-Z]{2})\s+\d{5}/', $html, $m)) {
            $city = trim($m[1]); $state = trim($m[2]);
        }

        $specialty = null;
        if (preg_match('#(?:Practice Area|Specialty)[^<]*<[^>]*>([^<]+)#i', $html, $m)) {
            $specialty = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }

        $bar = null;
        if (preg_match('/Bar Admission[^<]*<[^>]*>([^<]+)/i', $html, $m)) {
            $bar = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }

        $nameParts = $this->parseName($name);
        return [
            'full_name' => $name, 'first_name' => $nameParts['first'], 'last_name' => $nameParts['last'],
            'email' => $emails[0], 'phone' => $phone, 'country' => 'United States',
            'country_code' => 'US', 'city' => $city, 'region' => $state,
            'specialty' => $specialty, 'bar_association' => $bar, 'language' => 'en',
            'source_url' => $url,
        ];
    }

    // ═══════════════════════════════════════════════════
    //  ABOGADOS.COM.AR — Argentina
    // ═══════════════════════════════════════════════════

    public function abogadosArScrape(): array
    {
        $html = $this->fetchPage('https://abogados.com.ar/directorio');
        if (!$html) return [];

        $lawyers = [];
        preg_match_all('#href="(https?://abogados\.com\.ar/directorio/[^"]+)"#i', $html, $matches);

        foreach (array_unique($matches[1]) as $firmUrl) {
            $this->rateLimitSleep();
            $firmHtml = $this->fetchPage($firmUrl);
            if (!$firmHtml) continue;

            $firmName = null;
            if (preg_match('#<h1[^>]*>([^<]+)</h1>#i', $firmHtml, $m)) {
                $firmName = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
            }

            $emails = $this->extractEmails($firmHtml);
            if (empty($emails)) continue;

            foreach ($emails as $email) {
                $lawyers[] = [
                    'full_name' => $firmName ?? 'Unknown', 'email' => $email,
                    'firm_name' => $firmName, 'country' => 'Argentina', 'country_code' => 'AR',
                    'city' => 'Buenos Aires', 'language' => 'es', 'source_url' => $firmUrl,
                ];
            }
        }

        return $lawyers;
    }

    // ═══════════════════════════════════════════════════
    //  RECHTSANWALT.COM — DE/AT/CH
    // ═══════════════════════════════════════════════════

    public function rechtsanwaltComScrape(): array
    {
        $lawyers = [];
        $cities = [
            'berlin','hamburg','muenchen','koeln','frankfurt','stuttgart','duesseldorf',
            'dortmund','essen','leipzig','bremen','dresden','hannover','nuernberg',
            'wien','graz','linz','zuerich','bern','basel',
        ];

        foreach ($cities as $city) {
            $this->rateLimitSleep();
            $url = "https://www.rechtsanwalt.com/anwalt/{$city}/";
            $html = $this->fetchPage($url);
            if (!$html) continue;

            preg_match_all('#href="(https?://www\.rechtsanwalt\.com/anwalt/[^"]+/[^"]+)"#i', $html, $matches);
            foreach (array_unique($matches[1]) as $profileUrl) {
                if ($profileUrl === $url) continue;
                $this->rateLimitSleep();
                $profileHtml = $this->fetchPage($profileUrl);
                if (!$profileHtml) continue;

                $emails = $this->extractEmails($profileHtml);
                if (empty($emails)) continue;

                $name = null;
                if (preg_match('#<h1[^>]*>([^<]+)</h1>#i', $profileHtml, $m)) {
                    $name = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
                }

                $phone = null;
                if (preg_match('/(?:Tel|Telefon)[.:]*\s*([+\d\s\-\/()]{7,25})/i', $profileHtml, $m)) $phone = trim($m[1]);

                $specialty = null;
                if (preg_match('#Rechtsgebiet[^<]*<[^>]*>([^<]+)#i', $profileHtml, $m)) {
                    $specialty = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
                }

                $countryCode = 'DE';
                if (in_array($city, ['wien','graz','linz'])) $countryCode = 'AT';
                if (in_array($city, ['zuerich','bern','basel'])) $countryCode = 'CH';

                $nameParts = $this->parseName($name ?? $emails[0]);
                $lawyers[] = [
                    'full_name' => $name ?? $emails[0], 'first_name' => $nameParts['first'],
                    'last_name' => $nameParts['last'], 'email' => $emails[0], 'phone' => $phone,
                    'country' => $countryCode === 'DE' ? 'Germany' : ($countryCode === 'AT' ? 'Austria' : 'Switzerland'),
                    'country_code' => $countryCode, 'city' => ucfirst($city), 'language' => 'de',
                    'specialty' => $specialty, 'source_url' => $profileUrl,
                ];
            }
        }

        return $lawyers;
    }

    // ═══════════════════════════════════════════════════
    //  ENRICHMENT — visit firm websites
    // ═══════════════════════════════════════════════════

    public function enrichFromWebsite(string $website): array
    {
        $emails = [];
        $html = $this->fetchPage($website);
        if ($html) $emails = array_merge($emails, $this->extractEmails($html));

        if (empty($emails)) {
            foreach (['/contact','/contact-us','/about','/team','/en/contact'] as $path) {
                $this->rateLimitSleep();
                $cHtml = $this->fetchPage(rtrim($website, '/') . $path);
                if ($cHtml) {
                    $emails = array_merge($emails, $this->extractEmails($cHtml));
                    if (!empty($emails)) break;
                }
            }
        }

        return array_values(array_unique($emails));
    }

    // ═══════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════

    public function parseName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName));
        if (count($parts) <= 1) return ['first' => $fullName, 'last' => null];
        $last = array_pop($parts);
        return ['first' => implode(' ', $parts), 'last' => $last];
    }

    public function saveLawyer(array $data, string $sourceSlug): bool
    {
        if (empty($data['email'])) return false;

        $countryCode = $data['country_code'] ?? null;
        if ($countryCode && Lawyer::isFrancophone($countryCode, $data['language'] ?? null, $data['city'] ?? null)) {
            return false;
        }

        $urlHash = hash('sha256', $data['source_url'] . '|' . $data['email']);

        try {
            Lawyer::updateOrCreate(
                ['url_hash' => $urlHash],
                [
                    'source_slug' => $sourceSlug, 'source_url' => $data['source_url'],
                    'full_name' => $data['full_name'],
                    'first_name' => $data['first_name'] ?? null, 'last_name' => $data['last_name'] ?? null,
                    'firm_name' => $data['firm_name'] ?? null, 'title' => $data['title'] ?? null,
                    'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null,
                    'website' => $data['website'] ?? null, 'country' => $data['country'] ?? null,
                    'country_code' => $countryCode, 'city' => $data['city'] ?? null,
                    'region' => $data['region'] ?? null, 'address' => $data['address'] ?? null,
                    'specialty' => $data['specialty'] ?? null,
                    'bar_association' => $data['bar_association'] ?? null,
                    'bar_number' => $data['bar_number'] ?? null,
                    'language' => $data['language'] ?? 'en',
                    'is_immigration_lawyer' => Lawyer::isImmigrationSpecialty($data['specialty'] ?? null),
                    'is_francophone' => false, 'scraped_at' => now(),
                ]
            );
            return true;
        } catch (\Throwable $e) {
            Log::warning('LawyerScraper: save failed', ['email' => $data['email'], 'error' => $e->getMessage()]);
            return false;
        }
    }
}
