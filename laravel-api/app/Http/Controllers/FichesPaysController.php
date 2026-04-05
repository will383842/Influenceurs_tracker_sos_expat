<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fiches Pays proxy — calls Blog Laravel admin API for fiches management.
 * Supports 3 types: general, expatriation, vacances.
 */
class FichesPaysController extends Controller
{
    private const PROGRESS_KEY = 'fiches_generation_progress';

    private const TYPES = ['general', 'expatriation', 'vacances'];

    private function blogUrl(): string
    {
        return rtrim(config('services.blog.url', env('BLOG_API_URL', '')), '/');
    }

    private function blogToken(): string
    {
        return config('services.blog.api_key', env('BLOG_API_KEY', ''));
    }

    private function blogRequest()
    {
        return Http::withToken($this->blogToken())
            ->acceptJson()
            ->timeout(30);
    }

    // ─────────────────────────────────────────────────────────────
    // STATS — coverage per fiche type
    // ─────────────────────────────────────────────────────────────

    /** GET /content-gen/fiches/{type}/stats */
    public function stats(string $type): JsonResponse
    {
        if (! in_array($type, self::TYPES)) {
            return response()->json(['error' => "Type invalide: {$type}"], 422);
        }

        $cacheKey = "fiches_stats_{$type}";
        $data = Cache::remember($cacheKey, 300, function () use ($type) {
            $response = $this->blogRequest()
                ->get("{$this->blogUrl()}/api/v1/fiches/{$type}/stats");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("FichesPays: blog stats failed", ['status' => $response->status()]);
            return null;
        });

        if (! $data) {
            return response()->json([
                'covered' => 0,
                'total' => 197,
                'progress' => 0,
                'articles' => [],
                'missing' => [],
            ]);
        }

        return response()->json($data);
    }

    // ─────────────────────────────────────────────────────────────
    // LIST — fiches articles for a type
    // ─────────────────────────────────────────────────────────────

    /** GET /content-gen/fiches/{type}/articles */
    public function articles(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, self::TYPES)) {
            return response()->json(['error' => "Type invalide: {$type}"], 422);
        }

        $page = (int) $request->query('page', 1);

        $response = $this->blogRequest()
            ->get("{$this->blogUrl()}/api/v1/fiches/{$type}/articles", [
                'page' => $page,
            ]);

        if (! $response->successful()) {
            return response()->json(['error' => 'Blog API error', 'status' => $response->status()], 502);
        }

        return response()->json($response->json());
    }

    // ─────────────────────────────────────────────────────────────
    // GENERATE — launch fiche generation for a country
    // ─────────────────────────────────────────────────────────────

    /** POST /content-gen/fiches/{type}/generate */
    public function generate(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, self::TYPES)) {
            return response()->json(['error' => "Type invalide: {$type}"], 422);
        }

        $data = $request->validate([
            'country' => 'required|string|size:2',
            'draft'   => 'nullable|boolean',
        ]);

        $current = Cache::get(self::PROGRESS_KEY);
        if ($current && ($current['status'] ?? '') === 'running') {
            return response()->json([
                'message' => 'Une generation de fiche est deja en cours.',
            ], 409);
        }

        // Call blog admin to queue generation
        $response = $this->blogRequest()
            ->post("{$this->blogUrl()}/api/v1/fiches/{$type}/generate", [
                'country' => strtoupper($data['country']),
                'draft'   => $data['draft'] ?? false,
            ]);

        if (! $response->successful()) {
            return response()->json([
                'error' => 'Blog generation failed',
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ], 502);
        }

        // Track progress locally
        Cache::put(self::PROGRESS_KEY, [
            'status'  => 'running',
            'type'    => $type,
            'country' => strtoupper($data['country']),
            'started' => now()->toIso8601String(),
        ], 600);

        // Invalidate stats cache
        Cache::forget("fiches_stats_{$type}");

        return response()->json($response->json());
    }

    // ─────────────────────────────────────────────────────────────
    // PROGRESS — check generation status
    // ─────────────────────────────────────────────────────────────

    /** GET /content-gen/fiches/{type}/progress */
    public function progress(string $type): JsonResponse
    {
        $progress = Cache::get(self::PROGRESS_KEY);

        return response()->json([
            'progress' => $progress,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // MISSING — countries without this fiche type
    // ─────────────────────────────────────────────────────────────

    /** GET /content-gen/fiches/{type}/missing */
    public function missing(string $type): JsonResponse
    {
        if (! in_array($type, self::TYPES)) {
            return response()->json(['error' => "Type invalide: {$type}"], 422);
        }

        $response = $this->blogRequest()
            ->get("{$this->blogUrl()}/api/v1/fiches/{$type}/missing");

        if (! $response->successful()) {
            return response()->json(['countries' => []], 200);
        }

        return response()->json($response->json());
    }
}
