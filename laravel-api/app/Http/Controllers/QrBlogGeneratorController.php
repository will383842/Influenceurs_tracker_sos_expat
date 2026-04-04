<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateQrBlogJob;
use App\Models\ContentQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QrBlogGeneratorController extends Controller
{
    private const PROGRESS_KEY  = 'qr_blog_generation_progress';
    private const SCHEDULE_KEY  = 'qr_schedule';

    // ─────────────────────────────────────────────────────────────
    // STATS
    // ─────────────────────────────────────────────────────────────

    /** GET /content-gen/qr-blog/stats */
    public function stats(): JsonResponse
    {
        $available = ContentQuestion::where('article_status', 'opportunity')->count();
        $writing   = ContentQuestion::where('article_status', 'writing')->count();
        $published = ContentQuestion::where('article_status', 'published')->count();
        $skipped   = ContentQuestion::where('article_status', 'skipped')->count();
        $total     = ContentQuestion::count();

        return response()->json([
            'available' => $available,
            'writing'   => $writing,
            'published' => $published,
            'skipped'   => $skipped,
            'total'     => $total,
            'progress'  => Cache::get(self::PROGRESS_KEY),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GÉNÉRATION MANUELLE
    // ─────────────────────────────────────────────────────────────

    /** POST /content-gen/qr-blog/generate */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit'    => 'nullable|integer|min:1|max:200',
            'country'  => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
        ]);

        $current = Cache::get(self::PROGRESS_KEY);
        if ($current && ($current['status'] ?? '') === 'running') {
            return response()->json([
                'message' => 'Une génération Q/R est déjà en cours.',
            ], 409);
        }

        $questionIds = $this->pickQuestionIds(
            $data['limit'] ?? 50,
            $data['country'] ?? null,
            $data['category'] ?? null,
        );

        if (empty($questionIds)) {
            return response()->json(['message' => 'Aucune question disponible (status=opportunity).'], 422);
        }

        $this->initProgress(count($questionIds));
        GenerateQrBlogJob::dispatch($questionIds)->onQueue('default');

        return response()->json([
            'message' => count($questionIds) . ' Q/R lancées en génération.',
            'total'   => count($questionIds),
        ]);
    }

    /** GET /content-gen/qr-blog/progress */
    public function progress(): JsonResponse
    {
        $p = Cache::get(self::PROGRESS_KEY);
        return response()->json($p ?? ['status' => 'idle']);
    }

    /** POST /content-gen/qr-blog/reset */
    public function reset(): JsonResponse
    {
        $count = ContentQuestion::where('article_status', 'writing')
            ->update(['article_status' => 'opportunity']);
        Cache::forget(self::PROGRESS_KEY);

        return response()->json(['message' => "{$count} question(s) remises en file.", 'reset' => $count]);
    }

    // ─────────────────────────────────────────────────────────────
    // SOURCES (questions forum + ajout manuel)
    // ─────────────────────────────────────────────────────────────

    /** GET /content-gen/qr-blog/sources */
    public function sources(Request $request): JsonResponse
    {
        $query = ContentQuestion::query();

        if ($request->filled('status'))  $query->where('article_status', $request->input('status'));
        if ($request->filled('country')) $query->where('country_slug', $request->input('country'));
        if ($request->filled('search')) {
            $s = str_replace(['%', '_'], ['\\%', '\\_'], $request->input('search'));
            $query->where('title', 'ilike', '%' . $s . '%');
        }

        $sort = in_array($request->input('sort'), ['views', 'replies', 'title', 'created_at'])
            ? $request->input('sort') : 'views';
        $dir  = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json(
            $query->select('id', 'title', 'country', 'country_slug', 'language', 'views', 'replies', 'article_status', 'article_notes', 'created_at', 'url')
                ->orderBy($sort, $dir)
                ->paginate($perPage)
        );
    }

    /** POST /content-gen/qr-blog/sources — ajout manuel d'une question */
    public function addSource(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'   => 'required|string|max:500',
            'country' => 'nullable|string|max:100',
            'language'=> 'nullable|string|max:10',
            'notes'   => 'nullable|string|max:1000',
        ]);

        $countrySlug = $data['country']
            ? mb_strtolower(preg_replace('/[^a-z0-9]+/i', '-', $data['country']), 'UTF-8')
            : null;

        $question = ContentQuestion::create([
            'source_id'      => null,
            'title'          => $data['title'],
            'country'        => $data['country'] ?? null,
            'country_slug'   => $countrySlug,
            'language'       => $data['language'] ?? 'fr',
            'article_status' => 'opportunity',
            'article_notes'  => $data['notes'] ?? null,
            'views'          => 0,
            'replies'        => 0,
            'url'            => null,
            'url_hash'       => md5('manual_' . uniqid()),
        ]);

        return response()->json($question, 201);
    }

    /** PUT /content-gen/qr-blog/sources/{id} — modifier titre/notes/statut */
    public function updateSource(int $id, Request $request): JsonResponse
    {
        $question = ContentQuestion::findOrFail($id);
        $data = $request->validate([
            'title'          => 'sometimes|string|max:500',
            'article_status' => 'sometimes|in:opportunity,writing,published,skipped,covered',
            'article_notes'  => 'nullable|string|max:1000',
        ]);
        $question->update($data);
        return response()->json($question);
    }

    /** DELETE /content-gen/qr-blog/sources/{id} — supprimer une question manuelle */
    public function deleteSource(int $id): JsonResponse
    {
        $q = ContentQuestion::findOrFail($id);
        // Sécurité : ne supprime que les questions manuelles (source_id = null) ou skipped/opportunity
        if ($q->source_id !== null && ! in_array($q->article_status, ['opportunity', 'skipped'])) {
            return response()->json(['message' => 'Impossible de supprimer une question scrapée déjà traitée.'], 403);
        }
        $q->delete();
        return response()->json(['message' => 'Question supprimée.']);
    }

    // ─────────────────────────────────────────────────────────────
    // PROGRAMMATION QUOTIDIENNE
    // ─────────────────────────────────────────────────────────────

    /** GET /content-gen/qr-blog/schedule */
    public function getSchedule(): JsonResponse
    {
        $raw     = DB::table('settings')->where('key', self::SCHEDULE_KEY)->value('value');
        $config  = $raw ? json_decode($raw, true) : null;

        return response()->json($config ?? [
            'active'      => false,
            'daily_limit' => 20,
            'country'     => '',
            'category'    => '',
            'last_run_at' => null,
        ]);
    }

    /** PUT /content-gen/qr-blog/schedule */
    public function saveSchedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'active'      => 'required|boolean',
            'daily_limit' => 'required|integer|min:1|max:200',
            'country'     => 'nullable|string|max:100',
            'category'    => 'nullable|string|max:50',
        ]);

        $existing = DB::table('settings')->where('key', self::SCHEDULE_KEY)->value('value');
        $prev     = $existing ? json_decode($existing, true) : [];

        $config = array_merge($prev, [
            'active'      => $data['active'],
            'daily_limit' => $data['daily_limit'],
            'country'     => $data['country'] ?? '',
            'category'    => $data['category'] ?? '',
        ]);

        DB::table('settings')->updateOrInsert(
            ['key' => self::SCHEDULE_KEY],
            ['value' => json_encode($config), 'updated_at' => now()]
        );

        return response()->json(['message' => 'Programmation enregistrée.', 'config' => $config]);
    }

    // ─────────────────────────────────────────────────────────────
    // CONTENUS GÉNÉRÉS (proxy vers Blog API)
    // ─────────────────────────────────────────────────────────────

    /** GET /content-gen/qr-blog/generated */
    public function getGenerated(Request $request): JsonResponse
    {
        $blogUrl = rtrim(config('services.blog.url', ''), '/');
        $blogKey = config('services.blog.api_key', '');

        if (! $blogUrl || ! $blogKey) {
            return response()->json(['message' => 'Blog API non configurée.'], 503);
        }

        $params = array_filter([
            'content_type' => 'qa',
            'per_page'     => min((int) $request->input('per_page', 20), 50),
            'page'         => max(1, (int) $request->input('page', 1)),
            'language'     => $request->input('language', 'fr'),
            'search'       => $request->input('search'),
        ], fn($v) => $v !== null && $v !== '');

        try {
            $response = Http::withToken($blogKey)
                ->timeout(15)
                ->get("{$blogUrl}/api/v1/articles", $params);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            Log::warning('QR generated proxy failed', ['status' => $response->status()]);
            return response()->json(['message' => 'Erreur Blog API.', 'status' => $response->status()], 502);

        } catch (\Throwable $e) {
            Log::error('QR generated proxy exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Blog inaccessible.'], 503);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function pickQuestionIds(int $limit, ?string $country, ?string $category): array
    {
        $query = ContentQuestion::where('article_status', 'opportunity')->orderByDesc('views');

        if ($country) {
            $query->where(function ($q) use ($country) {
                $q->where('country_slug', $country)
                  ->orWhere('country', 'ilike', '%' . $country . '%');
            });
        }

        return $query->limit($limit)->pluck('id')->toArray();
    }

    private function initProgress(int $total): void
    {
        Cache::put(self::PROGRESS_KEY, [
            'status'        => 'running',
            'total'         => $total,
            'completed'     => 0,
            'skipped'       => 0,
            'errors'        => 0,
            'current_title' => null,
            'started_at'    => now()->toIso8601String(),
            'finished_at'   => null,
            'log'           => [],
        ], now()->addHours(24));
    }
}
