<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlogToolsProxyController extends Controller
{
    private function blogHttp()
    {
        $url   = rtrim(config('services.blog.url', env('BLOG_API_URL', '')), '/');
        $token = config('services.blog.api_key', env('BLOG_API_KEY', ''));

        return Http::baseUrl($url)
            ->withToken($token)
            ->timeout(15)
            ->acceptJson();
    }

    /**
     * GET /api/blog/tools
     * Proxy → Blog GET /api/v1/tools
     */
    public function index(): JsonResponse
    {
        try {
            $response = $this->blogHttp()->get('/api/v1/tools');

            if ($response->failed()) {
                Log::error('BlogToolsProxy: index failed', ['status' => $response->status()]);
                return response()->json(['error' => 'Blog API error'], 502);
            }

            return response()->json($response->json());
        } catch (\Throwable $e) {
            Log::error('BlogToolsProxy: index exception', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Blog unreachable'], 503);
        }
    }

    /**
     * POST /api/blog/tools/{id}/toggle
     * Proxy → Blog POST /api/v1/tools/{tool}/toggle
     */
    public function toggle(string $id): JsonResponse
    {
        if (!preg_match('/^[a-zA-Z0-9\-]{1,64}$/', $id)) {
            return response()->json(['error' => 'Invalid ID format'], 422);
        }

        try {
            $response = $this->blogHttp()->post("/api/v1/tools/{$id}/toggle");

            if ($response->failed()) {
                Log::error('BlogToolsProxy: toggle failed', ['id' => $id, 'status' => $response->status()]);
                return response()->json(['error' => 'Blog API error'], 502);
            }

            return response()->json($response->json());
        } catch (\Throwable $e) {
            Log::error('BlogToolsProxy: toggle exception', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Blog unreachable'], 503);
        }
    }

    /**
     * GET /api/blog/tools/leads
     * Proxy → Blog GET /api/v1/tools/leads
     */
    public function leads(Request $request): JsonResponse
    {
        try {
            $response = $this->blogHttp()->get('/api/v1/tools/leads', $request->only([
                'tool_id', 'language', 'search', 'from', 'to', 'per_page', 'page',
            ]));

            if ($response->failed()) {
                Log::error('BlogToolsProxy: leads failed', ['status' => $response->status()]);
                return response()->json(['error' => 'Blog API error'], 502);
            }

            return response()->json($response->json());
        } catch (\Throwable $e) {
            Log::error('BlogToolsProxy: leads exception', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Blog unreachable'], 503);
        }
    }
}
