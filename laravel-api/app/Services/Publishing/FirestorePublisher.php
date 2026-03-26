<?php

namespace App\Services\Publishing;

use Illuminate\Database\Eloquent\Model;
use App\Models\PublishingEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirestorePublisher
{
    public function publish(Model $content, PublishingEndpoint $endpoint): array
    {
        $config = $endpoint->config;
        $projectId = $config['project_id'] ?? config('services.firebase.project_id');
        $collection = $config['collection'] ?? 'blog_articles';

        if (empty($projectId)) {
            throw new \RuntimeException('Firebase project_id not configured');
        }

        // Build the document data from the content model
        $data = [
            'fields' => $this->toFirestoreFields([
                'title' => $content->title,
                'slug' => $content->slug,
                'content_html' => $content->content_html ?? '',
                'excerpt' => $content->excerpt ?? '',
                'meta_title' => $content->meta_title ?? '',
                'meta_description' => $content->meta_description ?? '',
                'language' => $content->language ?? 'fr',
                'country' => $content->country ?? '',
                'status' => 'published',
                'seo_score' => $content->seo_score ?? 0,
                'word_count' => $content->word_count ?? 0,
                'published_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ]),
        ];

        // Add JSON-LD if available
        if ($content->json_ld) {
            $data['fields']['json_ld'] = ['stringValue' => json_encode($content->json_ld)];
        }
        if ($content->hreflang_map) {
            $data['fields']['hreflang_map'] = ['stringValue' => json_encode($content->hreflang_map)];
        }

        // Use Firebase REST API
        $documentId = $content->uuid ?? $content->id;

        try {
            // For production, use service account auth. For now, use simple REST.
            $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}/{$documentId}";

            $response = Http::timeout(30)
                ->patch($url, $data);

            if ($response->successful()) {
                Log::info('FirestorePublisher: published', ['id' => $documentId, 'collection' => $collection]);
                return [
                    'external_id' => $documentId,
                    'external_url' => "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}/{$documentId}",
                ];
            }

            Log::error('FirestorePublisher: failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Firestore publish failed: HTTP ' . $response->status());
        } catch (\Throwable $e) {
            Log::error('FirestorePublisher: exception', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function toFirestoreFields(array $data): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            if (is_int($value)) {
                $fields[$key] = ['integerValue' => (string) $value];
            } elseif (is_bool($value)) {
                $fields[$key] = ['booleanValue' => $value];
            } elseif (is_float($value)) {
                $fields[$key] = ['doubleValue' => $value];
            } else {
                $fields[$key] = ['stringValue' => (string) ($value ?? '')];
            }
        }
        return $fields;
    }
}
