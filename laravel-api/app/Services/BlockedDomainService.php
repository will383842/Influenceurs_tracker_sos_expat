<?php

namespace App\Services;

/**
 * Centralized directory/aggregator domain detection.
 * Single source of truth used by:
 * - ResultParserService (intercept at AI import)
 * - ProcessAutoCampaignJob (intercept at auto-import)
 * - InfluenceurController::store() (intercept at manual creation)
 * - ScrapeContactJob (exploit directories)
 * - WebScraperService (skip scraping)
 */
class BlockedDomainService
{
    /**
     * Directory/aggregator domains that should NOT be stored as individual contacts.
     * Instead, these should be stored in the `directories` table and scraped for contacts.
     */
    public const DIRECTORY_DOMAINS = [
        // School/education directories & government
        'aefe.fr', 'aefe.gouv.fr', 'mlfmonde.org',
        'education.gouv.fr', 'enseignementsup-recherche.gouv.fr',
        'onisep.fr', 'letudiant.fr', 'studyrama.com',
        'campusfrance.org', 'odyssey.education', 'french-schools.org',
        'efep.education', 'diplomatie.gouv.fr', 'service-public.fr',
        'data.gouv.fr',

        // Expat directories & forums
        'expat.com', 'expatries.org', 'internations.org',
        'femmexpat.com', 'expatfocus.com', 'expatica.com',
        'justlanded.com', 'angloinfo.com',
        'forumvietnam.fr', 'thailandee.com',

        // News/media aggregators
        'lepetitjournal.com', 'france24.com', 'rfi.fr',
        'tv5monde.com', 'courrierinternational.com',
        'lefigaro.fr', 'lemonde.fr', 'bfmtv.com',
        'leparisien.fr', 'liberation.fr', 'huffingtonpost.fr',
        'ouest-france.fr', 'nouvelobs.com', 'lepoint.fr', 'lexpress.fr',

        // Listing/review aggregators
        'tripadvisor.com', 'tripadvisor.fr',
        'pagesjaunes.fr', 'yellowpages.com',
        'kompass.com', 'societe.com', 'europages.fr',
        'cylex.fr', 'mappy.com',
        'indeed.com', 'indeed.fr', 'glassdoor.com', 'glassdoor.fr',
        'yelp.com', 'yelp.fr', 'trustpilot.com',

        // Search engines & encyclopedias
        'google.com', 'google.fr', 'google.de', 'google.co.uk', 'google.es',
        'bing.com', 'wikipedia.org', 'wikidata.org', 'wikimedia.org',

        // Social platforms
        'youtube.com', 'youtu.be', 'tiktok.com', 'instagram.com',
        'facebook.com', 'fb.com', 'x.com', 'twitter.com',
        'linkedin.com', 'threads.net', 'snapchat.com', 'pinterest.com',

        // General purpose
        'amazon.com', 'amazon.fr', 'booking.com',
        'numbeo.com', 'livingcost.org',

        // Patterns (partial match)
        'vivre-en-',
        '.gouv.fr',
    ];

    /**
     * Check if a URL belongs to a directory/aggregator domain.
     */
    public static function isDirectoryUrl(?string $url): bool
    {
        if (empty($url)) return false;

        $urlLower = strtolower($url);
        foreach (self::DIRECTORY_DOMAINS as $domain) {
            if (str_contains($urlLower, $domain)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract the matching directory domain from a URL.
     * Returns the domain string or null.
     */
    public static function getMatchingDomain(?string $url): ?string
    {
        if (empty($url)) return null;

        $urlLower = strtolower($url);
        foreach (self::DIRECTORY_DOMAINS as $domain) {
            if (str_contains($urlLower, $domain)) {
                return $domain;
            }
        }
        return null;
    }

    /**
     * Check if a URL is a pure social platform (not a directory to scrape).
     */
    public static function isSocialPlatform(?string $url): bool
    {
        if (empty($url)) return false;

        $socialDomains = [
            'youtube.com', 'youtu.be', 'tiktok.com', 'instagram.com',
            'facebook.com', 'fb.com', 'x.com', 'twitter.com',
            'linkedin.com', 'threads.net', 'snapchat.com', 'pinterest.com',
        ];

        $urlLower = strtolower($url);
        foreach ($socialDomains as $domain) {
            if (str_contains($urlLower, $domain)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a URL is a scrapable directory (not just a social/search platform).
     * These are directories that could yield individual contacts.
     */
    public static function isScrapableDirectory(?string $url): bool
    {
        return self::isDirectoryUrl($url) && !self::isSocialPlatform($url);
    }
}
