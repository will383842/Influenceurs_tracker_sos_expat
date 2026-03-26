<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IndexNow API — instant URL submission to search engines.
 */
class IndexNowService
{
    private bool $enabled;
    private string $apiKey;
    private int $delay;

    public function __construct()
    {
        $this->enabled = (bool) config('services.indexnow.enabled', false);
        $this->apiKey = config('services.indexnow.key', '');
        $this->delay = (int) config('services.indexnow.delay', 60);
    }

    /**
     * Submit a single URL to IndexNow.
     */
    public function submit(string $url): array
    {
        if (!$this->enabled || empty($this->apiKey)) {
            Log::debug('IndexNow disabled or not configured');
            return ['success' => false, 'error' => 'IndexNow not enabled or configured'];
        }

        try {
            $host = parse_url($url, PHP_URL_HOST);

            $response = Http::timeout(30)->post('https://api.indexnow.org/indexnow', [
                'host' => $host,
                'key' => $this->apiKey,
                'urlList' => [$url],
            ]);

            if ($response->successful() || $response->status() === 202) {
                Log::info('IndexNow submit OK', ['url' => $url, 'status' => $response->status()]);

                return [
                    'success' => true,
                    'status' => $response->status(),
                    'url' => $url,
                ];
            }

            Log::warning('IndexNow submit error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'HTTP ' . $response->status(),
                'url' => $url,
            ];
        } catch (\Throwable $e) {
            Log::error('IndexNow submit exception', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'url' => $url,
            ];
        }
    }

    /**
     * Submit multiple URLs in a single batch (max 10,000 per request).
     */
    public function submitBatch(array $urls): array
    {
        if (!$this->enabled || empty($this->apiKey)) {
            Log::debug('IndexNow disabled or not configured');
            return ['success' => false, 'error' => 'IndexNow not enabled or configured', 'submitted' => 0];
        }

        if (empty($urls)) {
            return ['success' => true, 'submitted' => 0];
        }

        try {
            $results = [];
            $totalSubmitted = 0;

            // Split into chunks of 10,000 (IndexNow max per request)
            $chunks = array_chunk($urls, 10000);

            foreach ($chunks as $chunk) {
                $host = parse_url($chunk[0], PHP_URL_HOST);

                $response = Http::timeout(60)->post('https://api.indexnow.org/indexnow', [
                    'host' => $host,
                    'key' => $this->apiKey,
                    'urlList' => $chunk,
                ]);

                if ($response->successful() || $response->status() === 202) {
                    $totalSubmitted += count($chunk);
                    $results[] = [
                        'success' => true,
                        'count' => count($chunk),
                        'status' => $response->status(),
                    ];
                } else {
                    $results[] = [
                        'success' => false,
                        'count' => count($chunk),
                        'status' => $response->status(),
                        'error' => $response->body(),
                    ];
                }
            }

            Log::info('IndexNow batch submit', [
                'total_urls' => count($urls),
                'submitted' => $totalSubmitted,
                'chunks' => count($chunks),
            ]);

            return [
                'success' => $totalSubmitted > 0,
                'submitted' => $totalSubmitted,
                'total' => count($urls),
                'results' => $results,
            ];
        } catch (\Throwable $e) {
            Log::error('IndexNow batch submit exception', [
                'url_count' => count($urls),
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'submitted' => 0,
            ];
        }
    }
}
