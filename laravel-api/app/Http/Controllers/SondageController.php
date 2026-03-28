<?php

namespace App\Http\Controllers;

use App\Models\Sondage;
use App\Models\SondageQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SondageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Sondage::with('questions')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('language')) {
            $query->where('language', $request->language);
        }

        $sondages = $query->paginate(20);

        return response()->json($sondages);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'                    => 'required|string|max:255',
            'description'              => 'nullable|string|max:5000',
            'status'                   => 'required|in:draft,active,closed',
            'language'                 => 'required|string|max:5',
            'closes_at'                => 'nullable|date',
            'questions'                => 'required|array|min:1|max:50',
            'questions.*.text'         => 'required|string|max:500',
            'questions.*.type'         => 'required|in:single,multiple,open,scale',
            'questions.*.options'      => 'nullable|array',
            'questions.*.options.*'    => 'string|max:255',
        ]);

        $sondage = DB::transaction(function () use ($data) {
            $sondage = Sondage::create([
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'status'      => $data['status'],
                'language'    => $data['language'],
                'closes_at'   => $data['closes_at'] ?? null,
            ]);

            foreach ($data['questions'] as $i => $q) {
                SondageQuestion::create([
                    'sondage_id' => $sondage->id,
                    'text'       => $q['text'],
                    'type'       => $q['type'],
                    'options'    => isset($q['options']) ? array_values(array_filter($q['options'])) : null,
                    'sort_order' => $i,
                ]);
            }

            return $sondage->load('questions');
        });

        return response()->json($sondage, 201);
    }

    public function show(Sondage $sondage): JsonResponse
    {
        return response()->json($sondage->load('questions'));
    }

    public function update(Request $request, Sondage $sondage): JsonResponse
    {
        $data = $request->validate([
            'title'                    => 'sometimes|required|string|max:255',
            'description'              => 'nullable|string|max:5000',
            'status'                   => 'sometimes|required|in:draft,active,closed',
            'language'                 => 'sometimes|required|string|max:5',
            'closes_at'                => 'nullable|date',
            'questions'                => 'sometimes|array|min:1|max:50',
            'questions.*.text'         => 'required_with:questions|string|max:500',
            'questions.*.type'         => 'required_with:questions|in:single,multiple,open,scale',
            'questions.*.options'      => 'nullable|array',
            'questions.*.options.*'    => 'string|max:255',
        ]);

        DB::transaction(function () use ($data, $sondage) {
            $sondage->update(array_filter([
                'title'       => $data['title'] ?? null,
                'description' => array_key_exists('description', $data) ? $data['description'] : $sondage->description,
                'status'      => $data['status'] ?? null,
                'language'    => $data['language'] ?? null,
                'closes_at'   => array_key_exists('closes_at', $data) ? $data['closes_at'] : $sondage->closes_at,
            ], fn ($v) => $v !== null));

            if (isset($data['questions'])) {
                $sondage->questions()->delete();
                foreach ($data['questions'] as $i => $q) {
                    SondageQuestion::create([
                        'sondage_id' => $sondage->id,
                        'text'       => $q['text'],
                        'type'       => $q['type'],
                        'options'    => isset($q['options']) ? array_values(array_filter($q['options'])) : null,
                        'sort_order' => $i,
                    ]);
                }
            }

            // Marquer comme désynchronisé si des changements ont eu lieu
            $sondage->update(['synced_to_blog' => false]);
        });

        return response()->json($sondage->fresh()->load('questions'));
    }

    public function destroy(Sondage $sondage): JsonResponse
    {
        $sondage->delete();
        return response()->json(['message' => 'Sondage supprimé.']);
    }

    /**
     * Synchronise le sondage vers le Blog SSR (POST webhook).
     */
    public function syncToBlog(Sondage $sondage): JsonResponse
    {
        $blogUrl   = config('services.blog.url');
        $apiKey    = config('services.blog.api_key');

        if (!$blogUrl || !$apiKey) {
            return response()->json(['error' => 'Blog API non configurée (BLOG_API_URL / BLOG_API_KEY).'], 500);
        }

        $sondage->load('questions');

        $payload = [
            'external_id' => $sondage->external_id,
            'title'       => $sondage->title,
            'description' => $sondage->description,
            'status'      => $sondage->status,
            'language'    => $sondage->language,
            'closes_at'   => $sondage->closes_at?->toIso8601String(),
            'questions'   => $sondage->questions->map(fn ($q) => [
                'id'         => $q->id,
                'text'       => $q->text,
                'type'       => $q->type,
                'options'    => $q->options,
                'sort_order' => $q->sort_order,
            ])->toArray(),
        ];

        // Signature HMAC-SHA256 (même pattern que BlogPublisher)
        $timestamp = (string) time();
        $body      = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $apiKey);

        try {
            $response = Http::withHeaders([
                    'X-Webhook-Timestamp' => $timestamp,
                    'X-Webhook-Signature' => $signature,
                    'Content-Type'        => 'application/json',
                    'Accept'              => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->timeout(15)
                ->post(rtrim($blogUrl, '/') . '/api/v1/webhook/sondage');

            if ($response->successful()) {
                $sondage->update([
                    'synced_to_blog' => true,
                    'last_synced_at' => now(),
                ]);
                return response()->json([
                    'message' => 'Sondage synchronisé avec le Blog.',
                    'blog'    => $response->json(),
                ]);
            }

            Log::warning('SondageController::syncToBlog — Blog a répondu ' . $response->status(), [
                'body' => $response->body(),
            ]);

            return response()->json([
                'error'  => 'Le Blog a retourné une erreur.',
                'status' => $response->status(),
                'body'   => $response->body(),
            ], 502);

        } catch (\Exception $e) {
            Log::error('SondageController::syncToBlog — ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Récupère les résultats (statistiques de réponses) depuis le Blog.
     */
    public function resultats(Sondage $sondage): JsonResponse
    {
        $blogUrl = config('services.blog.url');
        $apiKey  = config('services.blog.api_key');

        if (!$blogUrl || !$apiKey) {
            return response()->json(['error' => 'Blog API non configurée.'], 500);
        }

        // GET signé avec HMAC (même secret, body vide)
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.', $apiKey);

        try {
            $response = Http::withHeaders([
                    'X-Webhook-Timestamp' => $timestamp,
                    'X-Webhook-Signature' => $signature,
                    'Accept'              => 'application/json',
                ])
                ->timeout(15)
                ->get(rtrim($blogUrl, '/') . "/api/v1/sondages/{$sondage->external_id}/resultats");

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json(['error' => 'Erreur Blog.', 'status' => $response->status()], 502);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
