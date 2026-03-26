<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeSeoJob;
use App\Jobs\GenerateSitemapJob;
use App\Models\GeneratedArticle;
use App\Models\InternalLink;
use App\Models\SeoAnalysis;
use App\Services\Seo\InternalLinkingService;
use App\Services\Seo\SitemapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeoController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        // scores_by_language — frontend expects { language, avg_score, count }[]
        $scoresByLanguage = GeneratedArticle::where('status', 'published')
            ->whereNotNull('seo_score')
            ->selectRaw('language, AVG(seo_score) as avg_score, COUNT(*) as count')
            ->groupBy('language')
            ->get()
            ->map(fn ($row) => [
                'language'  => $row->language,
                'avg_score' => round((float) $row->avg_score, 1),
                'count'     => (int) $row->count,
            ]);

        // score_ranges — frontend expects { range, count }[]
        $scoreRanges = collect([
            ['range' => '0-50',   'min' => 0,  'max' => 50],
            ['range' => '50-70',  'min' => 50, 'max' => 70],
            ['range' => '70-85',  'min' => 70, 'max' => 85],
            ['range' => '85-100', 'min' => 85, 'max' => 101],
        ])->map(fn ($r) => [
            'range' => $r['range'],
            'count' => GeneratedArticle::where('status', 'published')
                ->where('seo_score', '>=', $r['min'])
                ->where('seo_score', '<', $r['max'])
                ->count(),
        ]);

        // top_issues — frontend expects { type, count, severity }[]
        $topIssues = SeoAnalysis::whereNotNull('issues')
            ->pluck('issues')
            ->flatMap(fn ($issues) => collect($issues ?? []))
            ->groupBy('type')
            ->map(fn ($group, $type) => [
                'type'     => $type,
                'count'    => $group->count(),
                'severity' => $group->first()['severity'] ?? 'warning',
            ])
            ->values()
            ->sortByDesc('count')
            ->take(10)
            ->values();

        // orphaned_count
        $orphanedCount = app(InternalLinkingService::class)->findOrphanedArticles()->count();

        return response()->json([
            'scores_by_language' => $scoresByLanguage,
            'score_ranges'       => $scoreRanges,
            'top_issues'         => $topIssues,
            'orphaned_count'     => $orphanedCount,
        ]);
    }

    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_type' => 'required|string|in:GeneratedArticle,Comparative,LandingPage,PressRelease',
            'model_id'   => 'required|integer',
        ]);

        $typeMap = [
            'GeneratedArticle' => \App\Models\GeneratedArticle::class,
            'Comparative'      => \App\Models\Comparative::class,
            'LandingPage'      => \App\Models\LandingPage::class,
            'PressRelease'     => \App\Models\PressRelease::class,
        ];

        $fullType = $typeMap[$validated['model_type']];

        // Verify model exists
        $fullType::findOrFail($validated['model_id']);

        AnalyzeSeoJob::dispatch($fullType, $validated['model_id']);

        return response()->json([
            'message' => 'SEO analysis started',
            'model_type' => $validated['model_type'],
            'model_id' => $validated['model_id'],
        ], 202);
    }

    public function hreflangMatrix(Request $request): JsonResponse
    {
        $supportedLanguages = ['fr', 'en', 'de', 'es', 'pt', 'ru', 'zh', 'ar', 'hi'];

        // Get all published originals (no parent)
        $originals = GeneratedArticle::published()
            ->originals()
            ->select('id', 'title', 'slug', 'language')
            ->with('translations:id,parent_article_id,language,status')
            ->orderBy('title')
            ->get();

        $matrix = $originals->map(function ($article) use ($supportedLanguages) {
            $translations = [];

            // The original language is present
            $translations[$article->language] = true;

            // Check which translations exist
            foreach ($article->translations as $translation) {
                $translations[$translation->language] = $translation->status === 'published';
            }

            // Fill missing languages with false
            foreach ($supportedLanguages as $lang) {
                if (!isset($translations[$lang])) {
                    $translations[$lang] = false;
                }
            }

            return [
                'article_id'   => $article->id,
                'title'        => $article->title,
                'slug'         => $article->slug,
                'language'     => $article->language,
                'translations' => $translations,
            ];
        });

        // Frontend expects a flat array of HreflangMatrixEntry[], not a wrapped object
        return response()->json($matrix);
    }

    public function internalLinksGraph(Request $request): JsonResponse
    {
        $links = InternalLink::with(['source', 'target'])->get();

        // Build unique nodes from all sources and targets
        $nodesMap = [];

        foreach ($links as $link) {
            if ($link->source) {
                $key = $link->source_type . ':' . $link->source_id;
                if (!isset($nodesMap[$key])) {
                    $nodesMap[$key] = [
                        'id'        => $key,
                        'model_id'  => $link->source_id,
                        'type'      => class_basename($link->source_type),
                        'title'     => $link->source->title ?? 'Unknown',
                        'seo_score' => $link->source->seo_score ?? null,
                    ];
                }
            }

            if ($link->target) {
                $key = $link->target_type . ':' . $link->target_id;
                if (!isset($nodesMap[$key])) {
                    $nodesMap[$key] = [
                        'id'        => $key,
                        'model_id'  => $link->target_id,
                        'type'      => class_basename($link->target_type),
                        'title'     => $link->target->title ?? 'Unknown',
                        'seo_score' => $link->target->seo_score ?? null,
                    ];
                }
            }
        }

        $edges = $links->map(fn ($link) => [
            'source'      => $link->source_type . ':' . $link->source_id,
            'target'      => $link->target_type . ':' . $link->target_id,
            'anchor_text' => $link->anchor_text,
            'is_auto'     => $link->is_auto_generated,
        ])->values();

        return response()->json([
            'nodes' => array_values($nodesMap),
            'edges' => $edges,
        ]);
    }

    public function sitemap(SitemapService $service): \Illuminate\Http\Response
    {
        $xml = $service->generate();

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function orphanedArticles(Request $request, InternalLinkingService $service): JsonResponse
    {
        $orphaned = $service->findOrphanedArticles();

        // Frontend expects a flat array, not a wrapped object
        return response()->json($orphaned);
    }

    public function fixOrphaned(Request $request, InternalLinkingService $service): JsonResponse
    {
        $validated = $request->validate([
            'article_id' => 'required|integer|exists:generated_articles,id',
        ]);

        $article = GeneratedArticle::findOrFail($validated['article_id']);

        $suggestions = $service->suggestLinks($article);
        if (!empty($suggestions)) {
            $html = $service->injectLinks($article, $suggestions);
            $article->update(['content_html' => $html]);
        }

        return response()->json([
            'message'     => 'Links added',
            'links_added' => count($suggestions),
        ]);
    }
}
