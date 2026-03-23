<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\Directory;
use App\Models\Influenceur;
use App\Services\DirectoryScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeDirectoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 1;

    /**
     * Rotating User-Agents for anti-ban protection.
     */
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
    ];

    /**
     * Cooldown period after scraping (hours).
     */
    private const COOLDOWN_HOURS = 24;

    /**
     * Max requests per minute per domain.
     */
    private const MAX_REQUESTS_PER_MINUTE = 3;

    /**
     * Random delay range between pages (seconds).
     */
    private const MIN_DELAY_SECONDS = 3;
    private const MAX_DELAY_SECONDS = 8;

    public function __construct(
        private int $directoryId,
    ) {
        $this->onQueue('scraper');
    }

    public function handle(DirectoryScraperService $scraper): void
    {
        $directory = Directory::find($this->directoryId);
        if (!$directory) {
            Log::warning('ScrapeDirectoryJob: directory not found', ['id' => $this->directoryId]);
            return;
        }

        // Check cooldown
        if ($directory->isOnCooldown()) {
            Log::info('ScrapeDirectoryJob: directory on cooldown', [
                'id'    => $directory->id,
                'until' => $directory->cooldown_until,
            ]);
            return;
        }

        // Mark as scraping
        $directory->update(['status' => 'scraping']);

        Log::info('ScrapeDirectoryJob: starting', [
            'id'       => $directory->id,
            'name'     => $directory->name,
            'url'      => $directory->url,
            'category' => $directory->category,
        ]);

        try {
            $result = $scraper->scrapeDirectory(
                $directory->url,
                $directory->category,
                $directory->country
            );

            $created = 0;
            $skipped = 0;

            if ($result['success'] && !empty($result['contacts'])) {
                foreach ($result['contacts'] as $contactData) {
                    // Check if contact already exists (by name + country + type)
                    $exists = Influenceur::whereRaw('LOWER(name) = ?', [strtolower(trim($contactData['name']))])
                        ->where('contact_type', $directory->category)
                        ->where('country', $directory->country)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    // Also check by website URL if available
                    if (!empty($contactData['website_url'])) {
                        $existsByUrl = Influenceur::where('website_url', $contactData['website_url'])
                            ->where('contact_type', $directory->category)
                            ->exists();

                        if ($existsByUrl) {
                            $skipped++;
                            continue;
                        }
                    }

                    // Also check by email
                    if (!empty($contactData['email'])) {
                        if (Influenceur::where('email', strtolower($contactData['email']))->exists()) {
                            $skipped++;
                            continue;
                        }
                    }

                    // Create new contact from directory data
                    $influenceur = Influenceur::create([
                        'name'         => $contactData['name'],
                        'contact_type' => $directory->category,
                        'country'      => $contactData['country'] ?? $directory->country,
                        'language'     => $directory->language ?? 'fr',
                        'email'        => $contactData['email'] ?? null,
                        'phone'        => $contactData['phone'] ?? null,
                        'website_url'  => $contactData['website_url'] ?? null,
                        'source'       => 'directory:' . $directory->domain,
                        'status'       => 'new',
                        'created_by'   => $directory->created_by,
                        'notes'        => 'Extrait de l\'annuaire : ' . $directory->name,
                    ]);

                    // Dispatch individual scraping for each extracted contact (to get their own data)
                    if (!empty($contactData['website_url'])) {
                        ScrapeContactJob::dispatch($influenceur->id);
                    }

                    $created++;
                }
            }

            // Update directory with results
            $directory->update([
                'status'             => $result['success'] ? 'completed' : 'failed',
                'contacts_extracted' => count($result['contacts']),
                'contacts_created'   => $directory->contacts_created + $created,
                'pages_scraped'      => $result['pages_scraped'],
                'last_scraped_at'    => now(),
                'cooldown_until'     => now()->addHours(self::COOLDOWN_HOURS),
                'metadata'           => array_merge($directory->metadata ?? [], [
                    'last_scrape' => [
                        'contacts_found'   => count($result['contacts']),
                        'contacts_created' => $created,
                        'contacts_skipped' => $skipped,
                        'pages_scraped'    => $result['pages_scraped'],
                        'error'            => $result['error'],
                        'scraped_at'       => now()->toIso8601String(),
                    ],
                ]),
            ]);

            // Activity log
            ActivityLog::create([
                'user_id'      => $directory->created_by,
                'action'       => 'directory_scraped',
                'details'      => [
                    'directory_id'     => $directory->id,
                    'directory_name'   => $directory->name,
                    'url'              => $directory->url,
                    'contacts_found'   => count($result['contacts']),
                    'contacts_created' => $created,
                    'contacts_skipped' => $skipped,
                    'pages_scraped'    => $result['pages_scraped'],
                ],
                'contact_type' => $directory->category,
            ]);

            Log::info('ScrapeDirectoryJob: completed', [
                'id'               => $directory->id,
                'contacts_found'   => count($result['contacts']),
                'contacts_created' => $created,
                'contacts_skipped' => $skipped,
            ]);

        } catch (\Throwable $e) {
            $directory->update([
                'status'          => 'failed',
                'last_scraped_at' => now(),
                'cooldown_until'  => now()->addHours(self::COOLDOWN_HOURS),
                'metadata'        => array_merge($directory->metadata ?? [], [
                    'last_error' => [
                        'message' => substr($e->getMessage(), 0, 500),
                        'at'      => now()->toIso8601String(),
                    ],
                ]),
            ]);

            ActivityLog::create([
                'user_id'      => $directory->created_by,
                'action'       => 'directory_scrape_failed',
                'details'      => [
                    'directory_id'   => $directory->id,
                    'directory_name' => $directory->name,
                    'url'            => $directory->url,
                    'error'          => substr($e->getMessage(), 0, 500),
                ],
                'contact_type' => $directory->category,
            ]);

            Log::error('ScrapeDirectoryJob: exception', [
                'id'    => $directory->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
