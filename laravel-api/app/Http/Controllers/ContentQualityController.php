<?php

namespace App\Http\Controllers;

use App\Jobs\ImproveArticleQualityJob;
use App\Models\GeneratedArticle;
use App\Services\Quality\BrandComplianceService;
use App\Services\Quality\FactCheckingService;
use App\Services\Quality\PlagiarismService;
use App\Services\Quality\ReadabilityService;
use App\Services\Quality\ToneAnalyzerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContentQualityController extends Controller
{
    /**
     * Readability analysis for an article.
     */
    public function readability(GeneratedArticle $article, ReadabilityService $service): JsonResponse
    {
        try {
            $result = $service->analyze($article->content_html ?? '');

            return response()->json([
                'success'    => true,
                'article_id' => $article->id,
                'data'       => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Readability analysis failed', [
                'article_id' => $article->id,
                'message'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Readability analysis failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tone analysis for an article.
     */
    public function tone(GeneratedArticle $article, ToneAnalyzerService $service): JsonResponse
    {
        try {
            $result = $service->analyze($article->content_html ?? '');

            return response()->json([
                'success'    => true,
                'article_id' => $article->id,
                'data'       => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Tone analysis failed', [
                'article_id' => $article->id,
                'message'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Tone analysis failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Brand compliance check for an article.
     */
    public function brand(GeneratedArticle $article, BrandComplianceService $service): JsonResponse
    {
        try {
            $result = $service->check($article->content_html ?? '');

            return response()->json([
                'success'    => true,
                'article_id' => $article->id,
                'data'       => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Brand compliance check failed', [
                'article_id' => $article->id,
                'message'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Brand compliance check failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Plagiarism check for an article.
     */
    public function plagiarism(GeneratedArticle $article, PlagiarismService $service): JsonResponse
    {
        try {
            $result = $service->check($article);

            return response()->json([
                'success'    => true,
                'article_id' => $article->id,
                'data'       => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Plagiarism check failed', [
                'article_id' => $article->id,
                'message'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Plagiarism check failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fact-checking for an article.
     */
    public function factCheck(GeneratedArticle $article, FactCheckingService $service): JsonResponse
    {
        try {
            $result = $service->check(
                $article->content_html ?? '',
                $article->country ?? '',
                $article->language ?? 'fr'
            );

            return response()->json([
                'success'    => true,
                'article_id' => $article->id,
                'data'       => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Fact-checking failed', [
                'article_id' => $article->id,
                'message'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Fact-checking failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dispatch an auto-improvement job for an article.
     */
    public function improve(Request $request, GeneratedArticle $article): JsonResponse
    {
        $targetScore = $request->input('target_score', 85);
        $maxPasses = $request->input('max_passes', 3);

        ImproveArticleQualityJob::dispatch($article->id, (int) $targetScore, (int) $maxPasses);

        return response()->json([
            'success'      => true,
            'article_id'   => $article->id,
            'message'      => 'Quality improvement job dispatched',
            'target_score' => $targetScore,
            'max_passes'   => $maxPasses,
        ]);
    }

    /**
     * Full quality audit: run all 5 checks and return a combined report.
     */
    public function fullAudit(
        GeneratedArticle $article,
        ReadabilityService $readabilityService,
        ToneAnalyzerService $toneService,
        BrandComplianceService $brandService,
        PlagiarismService $plagiarismService,
        FactCheckingService $factCheckService,
    ): JsonResponse {
        try {
            $html = $article->content_html ?? '';

            $readability = $readabilityService->analyze($html);
            $tone = $toneService->analyze($html);
            $brand = $brandService->check($html);
            $plagiarism = $plagiarismService->check($article);
            $factCheck = $factCheckService->check(
                $html,
                $article->country ?? '',
                $article->language ?? 'fr'
            );

            // Compute a combined quality score (0-100)
            $readabilityScore = $readability['overall_score'] ?? 0;
            $toneCompliant = ($tone['is_brand_compliant'] ?? false) ? 100 : 40;
            $brandScore = $brand['score'] ?? 0;
            $plagiarismScore = ($plagiarism['is_original'] ?? true) ? 100 : max(0, 100 - ($plagiarism['similarity_percent'] ?? 0));
            $factCheckScore = 100;
            if (($factCheck['claims_found'] ?? 0) > 0) {
                $total = $factCheck['claims_found'];
                $verified = $factCheck['verified'] ?? 0;
                $factCheckScore = (int) round(($verified / $total) * 100);
            }

            $combinedScore = (int) round(
                ($readabilityScore * 0.25)
                + ($toneCompliant * 0.15)
                + ($brandScore * 0.20)
                + ($plagiarismScore * 0.25)
                + ($factCheckScore * 0.15)
            );

            return response()->json([
                'success'        => true,
                'article_id'     => $article->id,
                'combined_score' => $combinedScore,
                'readability'    => $readability,
                'tone'           => $tone,
                'brand'          => $brand,
                'plagiarism'     => $plagiarism,
                'fact_check'     => $factCheck,
            ]);
        } catch (\Throwable $e) {
            Log::error('Full quality audit failed', [
                'article_id' => $article->id,
                'message'    => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Full quality audit failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
