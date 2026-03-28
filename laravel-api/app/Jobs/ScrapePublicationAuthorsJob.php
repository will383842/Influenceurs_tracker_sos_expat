<?php

namespace App\Jobs;

use App\Models\PressPublication;
use App\Services\EmailInferenceService;
use App\Services\PublicationBylinesScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Deep author scraping job: iterates author index pages + article bylines,
 * then applies email inference for contacts without email addresses.
 */
class ScrapePublicationAuthorsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;   // 5 min — author pages can be slow
    public int $tries   = 2;

    public function __construct(
        private int  $publicationId,
        private bool $inferEmails     = true,
        private int  $maxArticlePages = 5,
    ) {
        $this->onQueue('scraper');
    }

    public function middleware(): array
    {
        return [new WithoutOverlapping("scrape-authors-{$this->publicationId}")];
    }

    public function handle(
        PublicationBylinesScraperService $bylinesSvc,
        EmailInferenceService            $emailSvc,
    ): void {
        $pub = PressPublication::find($this->publicationId);
        if (!$pub) {
            Log::warning("ScrapePublicationAuthorsJob: publication #{$this->publicationId} not found");
            return;
        }

        Log::info("ScrapePublicationAuthorsJob: starting for {$pub->name}");

        $totalSaved = 0;

        // 1. Scrape dedicated author/team index page
        if ($pub->authors_url) {
            $authors = $bylinesSvc->scrapeAuthorIndex($pub);
            if (!empty($authors)) {
                $saved = $bylinesSvc->saveAuthors($pub, $authors);
                $totalSaved += $saved;
                Log::info("ScrapePublicationAuthorsJob [{$pub->name}] author-index: saved {$saved}/" . count($authors));
            }
        }

        // 2. Scrape article bylines (discover authors via article pages)
        if ($pub->articles_url) {
            $authors = $bylinesSvc->scrapeArticleBylines($pub, $this->maxArticlePages);
            if (!empty($authors)) {
                $saved = $bylinesSvc->saveAuthors($pub, $authors);
                $totalSaved += $saved;
                Log::info("ScrapePublicationAuthorsJob [{$pub->name}] article-bylines: saved {$saved}/" . count($authors));
            }
        }

        // 3. Infer emails from pattern when no email was found by scraping
        if ($this->inferEmails && ($pub->email_pattern || $pub->email_domain)) {
            $result = $emailSvc->inferForPublication($pub);
            Log::info("ScrapePublicationAuthorsJob [{$pub->name}] email-inference: " . json_encode($result));
        }

        // 4. Update publication status
        $pub->refresh();
        $statusUpdate = [
            'last_scraped_at' => now(),
        ];

        if ($totalSaved > 0 || $pub->contacts_count > 0) {
            $statusUpdate['status'] = 'scraped';
            unset($statusUpdate['last_error']);
        } elseif ($pub->status !== 'scraped') {
            $statusUpdate['status']     = 'failed';
            $statusUpdate['last_error'] = 'No authors discovered via bylines or author index';
        }

        $pub->update($statusUpdate);
        Log::info("ScrapePublicationAuthorsJob: completed {$pub->name} — total_saved={$totalSaved}");
    }
}
