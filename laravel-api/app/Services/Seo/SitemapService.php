<?php

namespace App\Services\Seo;

use App\Models\Comparative;
use App\Models\GeneratedArticle;
use App\Models\LandingPage;
use App\Models\PressRelease;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * XML Sitemap generation with hreflang alternate links.
 */
class SitemapService
{
    private const BASE_URL = 'https://www.sos-expat.com';
    private const MAX_URLS_PER_SITEMAP = 50000;

    /**
     * Generate the full XML sitemap.
     */
    public function generate(): string
    {
        try {
            $urls = [];

            // Collect all published content
            $articles = GeneratedArticle::published()->get();
            foreach ($articles as $article) {
                $urls[] = $this->buildUrlEntry($article->url, $article->updated_at, 'weekly', 0.8, $article->hreflang_map);
            }

            $comparatives = Comparative::published()->get();
            foreach ($comparatives as $comparative) {
                $url = "/{$comparative->language}/comparatif/{$comparative->slug}";
                $urls[] = $this->buildUrlEntry($url, $comparative->updated_at, 'monthly', 0.7, $comparative->hreflang_map);
            }

            $landingPages = LandingPage::published()->get();
            foreach ($landingPages as $landing) {
                $url = "/{$landing->language}/{$landing->slug}";
                $urls[] = $this->buildUrlEntry($url, $landing->updated_at, 'monthly', 0.9, $landing->hreflang_map);
            }

            $pressReleases = PressRelease::published()->get();
            foreach ($pressReleases as $press) {
                $url = "/{$press->language}/communique/{$press->slug}";
                $urls[] = $this->buildUrlEntry($url, $press->updated_at, 'yearly', 0.5, $press->hreflang_map);
            }

            // If too many URLs, generate sitemap index instead
            if (count($urls) > self::MAX_URLS_PER_SITEMAP) {
                return $this->generateIndex();
            }

            $xml = $this->buildSitemapXml($urls);

            Log::info('Sitemap generated', ['url_count' => count($urls)]);

            return $xml;
        } catch (\Throwable $e) {
            Log::error('Sitemap generation failed', ['message' => $e->getMessage()]);

            return $this->buildSitemapXml([]);
        }
    }

    /**
     * Generate sitemap index with sub-sitemaps per language.
     */
    public function generateIndex(): string
    {
        try {
            $languages = GeneratedArticle::published()
                ->distinct()
                ->pluck('language')
                ->toArray();

            $sitemaps = [];
            foreach ($languages as $lang) {
                // Generate and save per-language sitemap
                $urls = [];

                $articles = GeneratedArticle::published()->language($lang)->get();
                foreach ($articles as $article) {
                    $urls[] = $this->buildUrlEntry($article->url, $article->updated_at, 'weekly', 0.8, $article->hreflang_map);
                }

                $comparatives = Comparative::published()->language($lang)->get();
                foreach ($comparatives as $comparative) {
                    $url = "/{$comparative->language}/comparatif/{$comparative->slug}";
                    $urls[] = $this->buildUrlEntry($url, $comparative->updated_at, 'monthly', 0.7, $comparative->hreflang_map);
                }

                $landingPages = LandingPage::published()->language($lang)->get();
                foreach ($landingPages as $landing) {
                    $url = "/{$landing->language}/{$landing->slug}";
                    $urls[] = $this->buildUrlEntry($url, $landing->updated_at, 'monthly', 0.9, $landing->hreflang_map);
                }

                if (!empty($urls)) {
                    $sitemapXml = $this->buildSitemapXml($urls);
                    $filename = "sitemap-{$lang}.xml";
                    Storage::disk('public')->put($filename, $sitemapXml);

                    $sitemaps[] = [
                        'loc' => self::BASE_URL . '/storage/' . $filename,
                        'lastmod' => now()->toDateString(),
                    ];
                }
            }

            // Build index XML
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            foreach ($sitemaps as $sitemap) {
                $xml .= "  <sitemap>\n";
                $xml .= "    <loc>" . htmlspecialchars($sitemap['loc']) . "</loc>\n";
                $xml .= "    <lastmod>{$sitemap['lastmod']}</lastmod>\n";
                $xml .= "  </sitemap>\n";
            }

            $xml .= "</sitemapindex>\n";

            Log::info('Sitemap index generated', ['sitemaps_count' => count($sitemaps)]);

            return $xml;
        } catch (\Throwable $e) {
            Log::error('Sitemap index generation failed', ['message' => $e->getMessage()]);

            return '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>';
        }
    }

    /**
     * Save the sitemap to disk and return the file path.
     */
    public function saveToDisk(): string
    {
        try {
            $xml = $this->generate();
            $path = 'sitemap.xml';

            Storage::disk('public')->put($path, $xml);

            $fullPath = Storage::disk('public')->path($path);

            Log::info('Sitemap saved to disk', ['path' => $fullPath]);

            return $fullPath;
        } catch (\Throwable $e) {
            Log::error('Sitemap save failed', ['message' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Build a URL entry for the sitemap.
     */
    private function buildUrlEntry(string $url, $lastmod, string $changefreq, float $priority, ?array $hreflangMap = null): array
    {
        return [
            'loc' => $url,
            'lastmod' => $lastmod ? (is_string($lastmod) ? $lastmod : $lastmod->toDateString()) : now()->toDateString(),
            'changefreq' => $changefreq,
            'priority' => $priority,
            'hreflang_map' => $hreflangMap,
        ];
    }

    /**
     * Build the XML sitemap string from URL entries.
     */
    private function buildSitemapXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($urls as $entry) {
            $loc = str_starts_with($entry['loc'], 'http')
                ? $entry['loc']
                : self::BASE_URL . $entry['loc'];

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
            $xml .= "    <lastmod>{$entry['lastmod']}</lastmod>\n";
            $xml .= "    <changefreq>{$entry['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$entry['priority']}</priority>\n";

            // Add hreflang alternate links
            if (!empty($entry['hreflang_map'])) {
                foreach ($entry['hreflang_map'] as $lang => $path) {
                    $href = str_starts_with($path, 'http') ? $path : self::BASE_URL . $path;
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($lang) . '" href="' . htmlspecialchars($href) . '" />' . "\n";
                }
            }

            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        return $xml;
    }
}
