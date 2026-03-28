<?php

namespace App\Jobs;

use App\Models\ContentArticle;
use App\Models\ContentCity;
use App\Models\ContentExternalLink;
use App\Services\ContentScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scrape tous les articles d'une ville (calqué sur ScrapeContentCountryJob).
 * Stocke les articles avec city_id + country_id pour un classement propre.
 */
class ScrapeContentCityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries   = 1;

    public function __construct(
        private int $cityId,
    ) {
        $this->onQueue('content-scraper');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('scrape-city-' . $this->cityId))
                ->releaseAfter(7200)
                ->expireAfter(7200),
        ];
    }

    public function handle(ContentScraperService $scraper): void
    {
        $city = ContentCity::with(['source', 'country'])->find($this->cityId);
        if (!$city || !$city->source || !$city->country) {
            Log::warning('ScrapeContentCityJob: city not found', ['id' => $this->cityId]);
            return;
        }

        $source  = $city->source;
        $country = $city->country;

        if (in_array($source->status, ['paused', 'pending'])) {
            Log::info('ScrapeContentCityJob: source paused, skipping', ['city' => $city->slug]);
            return;
        }

        Log::info('ScrapeContentCityJob: starting', [
            'city'    => $city->slug,
            'country' => $country->slug,
            'source'  => $source->slug,
        ]);

        // Pré-charger les URLs existantes pour éviter les doublons
        $existingArticleUrls = ContentArticle::where('city_id', $city->id)
            ->pluck('url')
            ->flip()
            ->toArray();

        // Aussi vérifier les articles du pays qui auraient déjà cette URL
        $existingSourceUrls = ContentArticle::where('source_id', $source->id)
            ->pluck('url')
            ->flip()
            ->toArray();

        $existingLinkHashes = ContentExternalLink::where('source_id', $source->id)
            ->pluck('url_hash')
            ->flip()
            ->toArray();

        $scrapedCount       = 0;
        $failedCount        = 0;
        $consecutiveFailures = 0;

        try {
            $articleLinks = $scraper->scrapeCityArticles($city);
            $scraper->rateLimitSleep();

            // Si aucun article individuel trouvé, scraper la page guide de la ville elle-même
            if (empty($articleLinks)) {
                $guideUrl = $city->guide_url;
                if (!isset($existingSourceUrls[$guideUrl])) {
                    $guideContent = $scraper->scrapeArticle($guideUrl);
                    if ($guideContent && $guideContent['word_count'] > 50) {
                        $urlHash = hash('sha256', $guideUrl);
                        $article = ContentArticle::create([
                            'source_id'        => $source->id,
                            'country_id'       => $country->id,
                            'city_id'          => $city->id,
                            'title'            => $guideContent['title'] ?: "Guide {$city->name}",
                            'slug'             => $guideContent['slug'] ?: $city->slug,
                            'url'              => $guideUrl,
                            'url_hash'         => $urlHash,
                            'category'         => null,
                            'section'          => 'guide',
                            'content_text'     => $guideContent['content_text'],
                            'content_html'     => $guideContent['content_html'],
                            'word_count'       => $guideContent['word_count'],
                            'language'         => $guideContent['language'],
                            'external_links'   => $guideContent['external_links'],
                            'ads_and_sponsors' => $guideContent['ads_and_sponsors'],
                            'images'           => $guideContent['images'],
                            'meta_title'       => $guideContent['meta_title'],
                            'meta_description' => $guideContent['meta_description'],
                            'is_guide'         => true,
                            'scraped_at'       => now(),
                        ]);

                        foreach ($guideContent['external_links'] as $link) {
                            $linkHash = hash('sha256', $link['url']);
                            if (!isset($existingLinkHashes[$linkHash])) {
                                ContentExternalLink::create([
                                    'source_id'    => $source->id,
                                    'article_id'   => $article->id,
                                    'url'          => $link['url'],
                                    'url_hash'     => $linkHash,
                                    'original_url' => $link['original_url'],
                                    'domain'       => $link['domain'],
                                    'anchor_text'  => $link['anchor_text'],
                                    'context'      => $link['context'],
                                    'country_id'   => $country->id,
                                    'link_type'    => $link['link_type'],
                                    'is_affiliate' => $link['is_affiliate'],
                                    'language'     => $guideContent['language'] ?? 'fr',
                                ]);
                                $existingLinkHashes[$linkHash] = true;
                            }
                        }

                        $scrapedCount++;
                        Log::info('ScrapeContentCityJob: scraped city guide page', [
                            'city'       => $city->slug,
                            'word_count' => $guideContent['word_count'],
                        ]);
                    }
                }
            }

            foreach ($articleLinks as $articleData) {
                // Skip si déjà en base (depuis pays ou ville)
                if (isset($existingArticleUrls[$articleData['url']]) || isset($existingSourceUrls[$articleData['url']])) {
                    // Si l'article existe mais n'a pas de city_id, on le met à jour
                    ContentArticle::where('url', $articleData['url'])
                        ->whereNull('city_id')
                        ->update(['city_id' => $city->id]);
                    continue;
                }

                try {
                    $articleContent = $scraper->scrapeArticle($articleData['url']);
                    if (!$articleContent) {
                        $failedCount++;
                        $consecutiveFailures++;
                        if ($consecutiveFailures >= 10) {
                            Log::error('ScrapeContentCityJob: aborting after 10 consecutive failures', [
                                'city' => $city->slug,
                            ]);
                            break;
                        }
                        $scraper->rateLimitSleep();
                        continue;
                    }

                    $urlHash = hash('sha256', $articleData['url']);

                    $article = ContentArticle::create([
                        'source_id'        => $source->id,
                        'country_id'       => $country->id,
                        'city_id'          => $city->id,
                        'title'            => $articleContent['title'] ?: $articleData['title'],
                        'slug'             => $articleContent['slug'] ?: substr($urlHash, 0, 12),
                        'url'              => $articleData['url'],
                        'url_hash'         => $urlHash,
                        'category'         => $articleData['category'],
                        'section'          => 'guide',
                        'content_text'     => $articleContent['content_text'],
                        'content_html'     => $articleContent['content_html'],
                        'word_count'       => $articleContent['word_count'],
                        'language'         => $articleContent['language'],
                        'external_links'   => $articleContent['external_links'],
                        'ads_and_sponsors' => $articleContent['ads_and_sponsors'],
                        'images'           => $articleContent['images'],
                        'meta_title'       => $articleContent['meta_title'],
                        'meta_description' => $articleContent['meta_description'],
                        'is_guide'         => $articleData['is_guide'] ?? false,
                        'scraped_at'       => now(),
                    ]);

                    $existingArticleUrls[$articleData['url']] = true;
                    $existingSourceUrls[$articleData['url']]  = true;
                    $consecutiveFailures = 0;

                    foreach ($articleContent['external_links'] as $link) {
                        $linkHash = hash('sha256', $link['url']);
                        if (isset($existingLinkHashes[$linkHash])) {
                            ContentExternalLink::where('source_id', $source->id)
                                ->where('url_hash', $linkHash)
                                ->increment('occurrences');
                        } else {
                            ContentExternalLink::create([
                                'source_id'    => $source->id,
                                'article_id'   => $article->id,
                                'url'          => $link['url'],
                                'url_hash'     => $linkHash,
                                'original_url' => $link['original_url'],
                                'domain'       => $link['domain'],
                                'anchor_text'  => $link['anchor_text'],
                                'context'      => $link['context'],
                                'country_id'   => $country->id,
                                'link_type'    => $link['link_type'],
                                'is_affiliate' => $link['is_affiliate'],
                                'language'     => $articleContent['language'] ?? 'fr',
                            ]);
                            $existingLinkHashes[$linkHash] = true;
                        }
                    }

                    $scrapedCount++;

                    if ($scrapedCount % 20 === 0) {
                        gc_collect_cycles();
                    }

                    $scraper->rateLimitSleep();

                } catch (\Throwable $e) {
                    $failedCount++;
                    $consecutiveFailures++;
                    Log::warning('ScrapeContentCityJob: article failed', [
                        'url'   => $articleData['url'],
                        'error' => $e->getMessage(),
                    ]);
                    if ($consecutiveFailures >= 10) break;
                    $scraper->rateLimitSleep();
                }
            }

            Log::info('ScrapeContentCityJob: completed', [
                'city'    => $city->slug,
                'country' => $country->slug,
                'scraped' => $scrapedCount,
                'failed'  => $failedCount,
            ]);

        } catch (\Throwable $e) {
            Log::error('ScrapeContentCityJob: failed', [
                'city'  => $city->slug,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $city->update([
                'articles_count' => $city->articles()->count(),
                'scraped_at'     => now(),
            ]);

            // Mettre à jour les stats source
            $source->update([
                'total_articles' => $source->articles()->count(),
                'total_links'    => $source->externalLinks()->count(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ScrapeContentCityJob: job failed permanently', [
            'cityId' => $this->cityId,
            'error'  => $e->getMessage(),
        ]);
    }
}
