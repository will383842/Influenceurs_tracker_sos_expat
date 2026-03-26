<?php

namespace App\Services\Seo;

use App\Models\GeneratedArticle;
use Illuminate\Support\Facades\Log;

/**
 * Hreflang management — bidirectional language alternate links.
 */
class HreflangService
{
    /**
     * Generate hreflang map for an article and all its translations.
     */
    public function generateHreflangMap(GeneratedArticle $article): array
    {
        try {
            $map = [];

            // Get the root article (either self if original, or the parent)
            $rootArticle = $article->parent_article_id
                ? $article->parentArticle
                : $article;

            if (!$rootArticle) {
                $rootArticle = $article;
            }

            // Add root article
            $map[$rootArticle->language] = $rootArticle->url;

            // Add all translations
            $translations = $rootArticle->translations()->get();
            foreach ($translations as $translation) {
                $map[$translation->language] = $translation->url;
            }

            // If current article is a translation, make sure it's included
            if ($article->parent_article_id) {
                $map[$article->language] = $article->url;
            }

            // Add x-default (English version, or first available)
            if (isset($map['en'])) {
                $map['x-default'] = $map['en'];
            } else {
                // Use the first available language
                $firstUrl = reset($map);
                if ($firstUrl) {
                    $map['x-default'] = $firstUrl;
                }
            }

            Log::debug('Hreflang map generated', [
                'article_id' => $article->id,
                'languages' => array_keys($map),
            ]);

            return $map;
        } catch (\Throwable $e) {
            Log::error('Hreflang map generation failed', [
                'article_id' => $article->id,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Validate that hreflang references are bidirectional.
     */
    public function validateBidirectional(GeneratedArticle $article): array
    {
        $valid = true;
        $issues = [];

        try {
            $hreflangMap = $article->hreflang_map ?? [];

            if (empty($hreflangMap)) {
                return ['valid' => false, 'issues' => [['lang' => '*', 'message' => 'No hreflang map on this article']]];
            }

            // Get root article
            $rootArticle = $article->parent_article_id
                ? $article->parentArticle
                : $article;

            if (!$rootArticle) {
                return ['valid' => false, 'issues' => [['lang' => '*', 'message' => 'Cannot find root article']]];
            }

            // Get all sibling articles (root + translations)
            $allArticles = collect([$rootArticle])->merge($rootArticle->translations()->get());

            foreach ($hreflangMap as $lang => $url) {
                if ($lang === 'x-default') {
                    continue;
                }

                // Find the target article for this language
                $targetArticle = $allArticles->firstWhere('language', $lang);

                if (!$targetArticle) {
                    $valid = false;
                    $issues[] = [
                        'lang' => $lang,
                        'message' => "Target article for language '{$lang}' not found in database",
                    ];
                    continue;
                }

                // Check that the target article also references back
                $targetMap = $targetArticle->hreflang_map ?? [];
                $currentLang = $article->language;

                if (!isset($targetMap[$currentLang])) {
                    $valid = false;
                    $issues[] = [
                        'lang' => $lang,
                        'message' => "Article '{$lang}' does not reference back to '{$currentLang}'",
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::error('Hreflang bidirectional validation failed', [
                'article_id' => $article->id,
                'message' => $e->getMessage(),
            ]);

            $valid = false;
            $issues[] = ['lang' => '*', 'message' => 'Validation error: ' . $e->getMessage()];
        }

        return ['valid' => $valid, 'issues' => $issues];
    }

    /**
     * Generate HTML hreflang link tags.
     */
    public function generateHreflangTags(array $hreflangMap, string $baseUrl): string
    {
        $tags = [];
        $baseUrl = rtrim($baseUrl, '/');

        foreach ($hreflangMap as $lang => $path) {
            $href = str_starts_with($path, 'http') ? $path : $baseUrl . $path;
            $tags[] = '<link rel="alternate" hreflang="' . htmlspecialchars($lang) . '" href="' . htmlspecialchars($href) . '" />';
        }

        return implode("\n", $tags);
    }

    /**
     * Sync hreflang maps across an article and all its translations.
     * Ensures bidirectional consistency.
     */
    public function syncAllTranslations(GeneratedArticle $article): void
    {
        try {
            // Generate the authoritative map
            $map = $this->generateHreflangMap($article);

            if (empty($map)) {
                return;
            }

            // Get root article
            $rootArticle = $article->parent_article_id
                ? $article->parentArticle
                : $article;

            if (!$rootArticle) {
                return;
            }

            // Update root article
            $rootArticle->update(['hreflang_map' => $map]);

            // Update all translations
            $translations = $rootArticle->translations()->get();
            foreach ($translations as $translation) {
                $translation->update(['hreflang_map' => $map]);
            }

            Log::info('Hreflang synced across translations', [
                'root_id' => $rootArticle->id,
                'languages' => array_keys($map),
                'translations_count' => $translations->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Hreflang sync failed', [
                'article_id' => $article->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
