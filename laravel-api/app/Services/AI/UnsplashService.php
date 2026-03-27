<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Unsplash API — royalty-free image search with proper attribution.
 */
class UnsplashService
{
    private string $accessKey;

    public function __construct()
    {
        $this->accessKey = config('services.unsplash.access_key', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->accessKey);
    }

    /**
     * Search for photos on Unsplash.
     */
    public function search(string $query, int $perPage = 5, string $orientation = 'landscape'): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'images' => [], 'error' => 'Unsplash access key not configured'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Client-ID ' . $this->accessKey,
            ])->timeout(30)->get('https://api.unsplash.com/search/photos', [
                'query' => $query,
                'per_page' => $perPage,
                'orientation' => $orientation,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $results = $data['results'] ?? [];
                $images = [];

                foreach ($results as $photo) {
                    $rawUrl = $photo['urls']['raw'] ?? $photo['urls']['regular'] ?? '';
                    $photographerName = $photo['user']['name'] ?? 'Unknown';
                    $photographerUrl = $photo['user']['links']['html'] ?? 'https://unsplash.com';

                    $images[] = [
                        'url' => $rawUrl ? $rawUrl . '&w=1200&q=80&auto=format' : '',
                        'thumb_url' => $rawUrl ? $rawUrl . '&w=400&q=75&auto=format' : ($photo['urls']['thumb'] ?? ''),
                        'alt_text' => $photo['description'] ?? $photo['alt_description'] ?? $query,
                        'attribution' => "Photo by {$photographerName} on Unsplash",
                        'photographer_name' => $photographerName,
                        'photographer_url' => $photographerUrl . '?utm_source=sos-expat&utm_medium=referral',
                        'unsplash_url' => ($photo['links']['html'] ?? 'https://unsplash.com') . '?utm_source=sos-expat&utm_medium=referral',
                        'raw_url' => $rawUrl,
                        'width' => $photo['width'] ?? 0,
                        'height' => $photo['height'] ?? 0,
                        'download_url' => $photo['links']['download_location'] ?? '',
                        'srcset' => $rawUrl ? implode(', ', [
                            $rawUrl . '&w=640&q=80&auto=format 640w',
                            $rawUrl . '&w=960&q=80&auto=format 960w',
                            $rawUrl . '&w=1200&q=80&auto=format 1200w',
                        ]) : '',
                    ];

                    // Trigger download tracking (Unsplash API requirement)
                    $downloadLocation = $photo['links']['download_location'] ?? null;
                    if ($downloadLocation) {
                        $this->triggerDownloadTracking($downloadLocation);
                    }
                }

                Log::info('Unsplash search OK', [
                    'query' => $query,
                    'results' => count($images),
                ]);

                return [
                    'success' => true,
                    'images' => $images,
                ];
            }

            Log::warning('Unsplash search error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'query' => $query,
            ]);

            return [
                'success' => false,
                'images' => [],
                'error' => 'HTTP ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('Unsplash search exception', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            return [
                'success' => false,
                'images' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get a random photo matching a query.
     */
    public function getRandomPhoto(string $query): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Client-ID ' . $this->accessKey,
            ])->timeout(30)->get('https://api.unsplash.com/photos/random', [
                'query' => $query,
                'orientation' => 'landscape',
            ]);

            if ($response->successful()) {
                $photo = $response->json();

                // Trigger download tracking
                $downloadLocation = $photo['links']['download_location'] ?? null;
                if ($downloadLocation) {
                    $this->triggerDownloadTracking($downloadLocation);
                }

                Log::info('Unsplash random OK', ['query' => $query]);

                $rawUrl = $photo['urls']['raw'] ?? $photo['urls']['regular'] ?? '';
                $photographerName = $photo['user']['name'] ?? 'Unknown';
                $photographerUrl = $photo['user']['links']['html'] ?? 'https://unsplash.com';

                return [
                    'url' => $rawUrl ? $rawUrl . '&w=1200&q=80&auto=format' : '',
                    'thumb_url' => $rawUrl ? $rawUrl . '&w=400&q=75&auto=format' : ($photo['urls']['thumb'] ?? ''),
                    'alt_text' => $photo['description'] ?? $photo['alt_description'] ?? $query,
                    'attribution' => "Photo by {$photographerName} on Unsplash",
                    'photographer_name' => $photographerName,
                    'photographer_url' => $photographerUrl . '?utm_source=sos-expat&utm_medium=referral',
                    'unsplash_url' => ($photo['links']['html'] ?? 'https://unsplash.com') . '?utm_source=sos-expat&utm_medium=referral',
                    'raw_url' => $rawUrl,
                    'width' => $photo['width'] ?? 0,
                    'height' => $photo['height'] ?? 0,
                    'download_url' => $downloadLocation ?? '',
                    'srcset' => $rawUrl ? implode(', ', [
                        $rawUrl . '&w=640&q=80&auto=format 640w',
                        $rawUrl . '&w=960&q=80&auto=format 960w',
                        $rawUrl . '&w=1200&q=80&auto=format 1200w',
                    ]) : '',
                ];
            }

            Log::warning('Unsplash random error', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('Unsplash random exception', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            return null;
        }
    }

    /**
     * Trigger Unsplash download tracking (required by API guidelines).
     */
    private function triggerDownloadTracking(string $downloadLocation): void
    {
        try {
            Http::withHeaders([
                'Authorization' => 'Client-ID ' . $this->accessKey,
            ])->timeout(10)->get($downloadLocation);
        } catch (\Throwable $e) {
            // Non-critical — just log and move on
            Log::debug('Unsplash download tracking failed', ['message' => $e->getMessage()]);
        }
    }
}
