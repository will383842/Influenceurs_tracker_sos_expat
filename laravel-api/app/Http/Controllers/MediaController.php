<?php

namespace App\Http\Controllers;

use App\Services\AI\OpenAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MediaController extends Controller
{
    public function searchUnsplash(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query'    => 'required|string|max:200',
            'per_page' => 'nullable|integer|min:1|max:30',
            'page'     => 'nullable|integer|min:1',
            'orientation' => 'nullable|string|in:landscape,portrait,squarish',
        ]);

        $apiKey = config('services.unsplash.access_key');

        if (!$apiKey) {
            return response()->json([
                'message' => 'Unsplash API key not configured',
            ], 503);
        }

        $params = [
            'query'    => $validated['query'],
            'per_page' => $validated['per_page'] ?? 10,
            'page'     => $validated['page'] ?? 1,
        ];

        if (!empty($validated['orientation'])) {
            $params['orientation'] = $validated['orientation'];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Client-ID {$apiKey}",
                    'Accept-Version' => 'v1',
                ])
                ->get('https://api.unsplash.com/search/photos', $params);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'Unsplash API error: ' . $response->status(),
                ], $response->status());
            }

            $data = $response->json();

            // Simplify the response — only return what the frontend needs
            $images = collect($data['results'] ?? [])->map(fn ($img) => [
                'id'          => $img['id'],
                'description' => $img['description'] ?? $img['alt_description'] ?? '',
                'width'       => $img['width'],
                'height'      => $img['height'],
                'color'       => $img['color'],
                'urls'        => [
                    'thumb'   => $img['urls']['thumb'] ?? null,
                    'small'   => $img['urls']['small'] ?? null,
                    'regular' => $img['urls']['regular'] ?? null,
                    'full'    => $img['urls']['full'] ?? null,
                ],
                'author'      => [
                    'name' => $img['user']['name'] ?? 'Unknown',
                    'link' => $img['user']['links']['html'] ?? null,
                ],
                'download_url' => $img['links']['download_location'] ?? null,
            ]);

            return response()->json([
                'total'       => $data['total'] ?? 0,
                'total_pages' => $data['total_pages'] ?? 0,
                'images'      => $images,
            ]);
        } catch (\Throwable $e) {
            Log::error('Unsplash search failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to search Unsplash',
            ], 503);
        }
    }

    public function generateImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:1000',
            'size'   => 'nullable|string|in:1024x1024,1792x1024,1024x1792',
            'style'  => 'nullable|string|in:vivid,natural',
            'quality' => 'nullable|string|in:standard,hd',
        ]);

        try {
            /** @var OpenAiService $aiService */
            $aiService = app(OpenAiService::class);

            $result = $aiService->generateImage($validated['prompt'], [
                'size' => $validated['size'] ?? '1024x1024',
                'quality' => $validated['quality'] ?? 'standard',
            ]);

            return response()->json([
                'success'  => true,
                'url'      => $result['url'] ?? null,
                'b64_json' => $result['b64_json'] ?? null,
                'revised_prompt' => $result['revised_prompt'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Image generation failed', [
                'prompt' => $validated['prompt'],
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Image generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
