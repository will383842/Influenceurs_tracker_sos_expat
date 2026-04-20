<?php

namespace App\Jobs;

use App\Models\Influenceur;
use App\Models\RssBlogFeed;
use App\Services\Scraping\BloggerRssParser;
use App\Services\Scraping\ScraperRunRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Option D — P3 : Job qui scrape les feeds RSS de blogs et alimente
 * la table influenceurs avec contact_type=blog.
 *
 * ZERO RISQUE BAN :
 * - Parsing XML public (SOS-Expat-BloggerBot/1.0 User-Agent)
 * - Pas de scraping HTML Medium/Blogger/Hashnode
 * - 2-4s aleatoire entre feeds
 * - Timeout 15s par requete (parser)
 *
 * Dédup auto : Influenceur.email (case-insensitive via index
 * unique_email_lower_influenceurs).
 *
 * Push auto bl-app : InfluenceurObserver::created() déclenché par
 * chaque create() (contact_type=blog est dans SYNCABLE_TYPES).
 */
class ScrapeBloggerRssFeedsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int Timeout 1h pour supporter ~500-700 feeds en 1 run (sans fetch_about) */
    public int $timeout = 3600;

    /** @var int Pas de retry (les erreurs transitoires seront retraitées au prochain cron) */
    public int $tries = 1;

    /**
     * @param ?int  $feedId     Si fourni : scrape 1 seul feed (utile pour bouton UI)
     * @param bool  $skipAbout  Si true : ignore fetch_about même si activé sur le feed
     *                          (utile pour premier grand run où cache about n'existe pas)
     */
    public function __construct(
        private readonly ?int $feedId = null,
        private readonly bool $skipAbout = false,
    ) {
        $this->onQueue('scraper');
    }

    public function handle(BloggerRssParser $parser, ScraperRunRecorder $recorder): void
    {
        $query = RssBlogFeed::active();
        if ($this->feedId !== null) {
            $query->where('id', $this->feedId);
        } else {
            $query->due();
        }

        $feeds = $query->get();
        Log::info('ScrapeBloggerRssFeedsJob: start', [
            'mode' => $this->feedId ? "single-{$this->feedId}" : 'all-due',
            'count' => $feeds->count(),
        ]);

        foreach ($feeds as $feed) {
            // Option skipAbout : desactive fetch_about en memory pour ce run
            // sans modifier la DB (cache persist pour les runs suivants)
            $originalFetchAbout = $feed->fetch_about;
            if ($this->skipAbout) {
                $feed->fetch_about = false;
            }

            $recorder->track(
                'bloggers-rss',
                $feed->country,
                false, // requires_perplexity
                function ($run) use ($feed, $parser) {
                    try {
                        $authors = $parser->parse($feed);
                        $new = $this->persistAuthors($authors, $feed);

                        $feed->update([
                            'last_scraped_at'      => now(),
                            'last_contacts_found'  => $new,
                            'total_contacts_found' => $feed->total_contacts_found + $new,
                            'last_error'           => null,
                        ]);

                        // Anti-ban : pause 2-4s aleatoire entre feeds
                        if (count($authors) > 0) {
                            usleep(random_int(2_000_000, 4_000_000));
                        }

                        return [
                            'found' => count($authors),
                            'new'   => $new,
                            'meta'  => ['feed_id' => $feed->id, 'feed_name' => $feed->name],
                        ];
                    } catch (\Throwable $e) {
                        $feed->update(['last_error' => substr($e->getMessage(), 0, 500)]);
                        throw $e;
                    }
                }
            );
        }

        Log::info('ScrapeBloggerRssFeedsJob: done');
    }

    /**
     * Insere ou enrichit dans influenceurs selon dedup par email.
     * Retourne le nombre de NOUVEAUX contacts créés.
     *
     * @param array<int,array{name:string,email:?string,email_source:string,source_url:?string,language?:?string,country?:?string}> $authors
     */
    private function persistAuthors(array $authors, RssBlogFeed $feed): int
    {
        $new = 0;

        foreach ($authors as $a) {
            $name = trim($a['name'] ?? '');
            $email = isset($a['email']) ? strtolower(trim($a['email'])) : null;

            if (empty($name) && empty($email)) {
                continue;
            }

            // Dedup par email (index unique_email_lower_influenceurs)
            $existing = $email
                ? Influenceur::whereRaw('LOWER(email) = ?', [$email])->first()
                : null;

            if ($existing) {
                // Enrichissement soft : ne pas ecraser les valeurs existantes
                $updates = array_filter([
                    'name'             => empty($existing->name) ? $name : null,
                    'source_id_legacy' => empty($existing->source_id_legacy) ? $feed->id : null,
                    'source_origin'    => empty($existing->source_origin) ? 'rss_blog_feeds' : null,
                ], fn ($v) => $v !== null);

                if (!empty($updates)) {
                    $existing->update($updates);
                }
                continue;
            }

            // Nouvel Influenceur → InfluenceurObserver::created() push auto à bl-app
            try {
                Influenceur::create([
                    'contact_type'     => 'blog',
                    'name'             => $name ?: null,
                    'email'            => $email,
                    'profile_url'      => $a['source_url'] ?? $feed->resolvedBaseUrl(),
                    'website_url'      => $feed->resolvedBaseUrl(),
                    'country'          => $a['country'] ?? $feed->country,
                    'language'         => $a['language'] ?? $feed->language,
                    'source'           => 'rss_blog_feeds',
                    'source_origin'    => 'rss_blog_feeds',
                    'source_id_legacy' => $feed->id,
                    'notes'            => "Extrait de {$feed->name} (RSS, email_source={$a['email_source']})",
                    'is_verified'      => ($a['email_source'] ?? '') !== 'pattern',
                    'status'           => 'new',
                ]);
                $new++;
            } catch (\Throwable $e) {
                Log::warning('ScrapeBloggerRssFeedsJob: insert failed', [
                    'feed_id' => $feed->id,
                    'email'   => $email,
                    'name'    => $name,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return $new;
    }
}
