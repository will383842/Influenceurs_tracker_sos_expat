<?php

namespace App\Services\Publishing;

use Illuminate\Database\Eloquent\Model;
use App\Models\PublishingEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressPublisher
{
    public function publish(Model $content, PublishingEndpoint $endpoint): array
    {
        $config = $endpoint->config;
        $baseUrl = rtrim($config['url'] ?? '', '/');
        $username = $config['username'] ?? '';
        $appPassword = $config['app_password'] ?? '';

        if (empty($baseUrl) || empty($username) || empty($appPassword)) {
            throw new \RuntimeException('WordPress credentials not configured');
        }

        $postData = [
            'title' => $content->title ?? '',
            'content' => $content->content_html ?? '',
            'excerpt' => $content->excerpt ?? '',
            'status' => 'publish',
            'slug' => $content->slug ?? '',
            'meta' => [
                'seo_title' => $content->meta_title ?? '',
                'seo_description' => $content->meta_description ?? '',
            ],
        ];

        try {
            $response = Http::withBasicAuth($username, $appPassword)
                ->timeout(30)
                ->post("{$baseUrl}/wp-json/wp/v2/posts", $postData);

            if ($response->successful()) {
                $wpPost = $response->json();
                Log::info('WordPressPublisher: published', ['wp_id' => $wpPost['id']]);
                return [
                    'external_id' => (string) $wpPost['id'],
                    'external_url' => $wpPost['link'] ?? '',
                ];
            }

            Log::error('WordPressPublisher: failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('WordPress publish failed: HTTP ' . $response->status());
        } catch (\Throwable $e) {
            Log::error('WordPressPublisher: exception', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
