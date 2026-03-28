<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SEO-friendly slug generation with language-specific transliteration.
 */
class SlugService
{
    /** German transliteration rules */
    private const GERMAN_MAP = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
        'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        'ß' => 'ss',
    ];

    /** French transliteration rules (beyond standard Str::slug) */
    private const FRENCH_MAP = [
        'œ' => 'oe', 'æ' => 'ae',
        'Œ' => 'Oe', 'Æ' => 'Ae',
    ];

    /** Turkish transliteration rules */
    private const TURKISH_MAP = [
        'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
        'Ç' => 'C', 'Ğ' => 'G', 'İ' => 'I', 'Ö' => 'O', 'Ş' => 'S', 'Ü' => 'U',
    ];

    /** Polish transliteration rules */
    private const POLISH_MAP = [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
        'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N',
        'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z',
    ];

    /** Romanian transliteration rules */
    private const ROMANIAN_MAP = [
        'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't',
        'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ț' => 'T',
    ];

    /**
     * Generate a clean, SEO-friendly slug from a title.
     */
    public function generateSlug(string $title, string $language): string
    {
        // Apply language-specific transliteration first
        $title = $this->transliterate($title, $language);

        // Use Laravel's Str::slug for the rest (handles accents, special chars)
        $slug = Str::slug($title);

        // Remove any double hyphens that may remain
        $slug = preg_replace('/-{2,}/', '-', $slug);

        // Trim hyphens from start/end
        $slug = trim($slug, '-');

        // SEO: slug must not exceed 60 characters; truncate at last hyphen
        if (mb_strlen($slug) > 60) {
            $slug = mb_substr($slug, 0, 60);
            $lastHyphen = mb_strrpos($slug, '-');
            if ($lastHyphen && $lastHyphen > 30) {
                $slug = mb_substr($slug, 0, $lastHyphen);
            }
            $slug = trim($slug, '-');
        }

        return $slug;
    }

    /**
     * Generate localized slugs for multiple languages.
     *
     * @param string $title Original title
     * @param array<string, string> $translatedTitles ["en" => "English title", "de" => "German title"]
     * @return array<string, string> ["fr" => "titre-en-francais", "en" => "english-title"]
     */
    public function generateLocalizedSlugs(string $title, array $translatedTitles): array
    {
        $slugs = [];

        // Detect original language from keys not in translations
        // Default to 'fr' if not specified
        $originalLang = 'fr';
        $slugs[$originalLang] = $this->generateSlug($title, $originalLang);

        foreach ($translatedTitles as $lang => $translatedTitle) {
            $slugs[$lang] = $this->generateSlug($translatedTitle, $lang);
        }

        return $slugs;
    }

    /**
     * Ensure slug + language combination is unique in the given table.
     */
    public function ensureUnique(string $slug, string $language, string $table = 'generated_articles', ?int $excludeId = null): string
    {
        // Cap base slug at 57 chars to leave room for "-NNN" suffix without exceeding 60
        if (mb_strlen($slug) > 57) {
            $truncated = mb_substr($slug, 0, 57);
            $lastHyphen = mb_strrpos($truncated, '-');
            $slug = ($lastHyphen && $lastHyphen > 30) ? mb_substr($truncated, 0, $lastHyphen) : $truncated;
            $slug = trim($slug, '-');
        }

        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = DB::table($table)
                ->where('slug', $slug)
                ->where('language', $language);

            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }

            // Also exclude soft-deleted records if table has deleted_at
            try {
                $query->whereNull('deleted_at');
            } catch (\Throwable $e) {
                // Table may not have deleted_at column — ignore
            }

            if (!$query->exists()) {
                break;
            }

            $counter++;
            $slug = $originalSlug . '-' . $counter;
        }

        if ($counter > 1) {
            Log::debug('Slug uniqueness applied', [
                'original' => $originalSlug,
                'unique' => $slug,
                'language' => $language,
                'table' => $table,
            ]);
        }

        return $slug;
    }

    /**
     * Apply language-specific transliteration rules.
     */
    private function transliterate(string $text, string $language): string
    {
        $map = match ($language) {
            'de' => self::GERMAN_MAP,
            'fr' => self::FRENCH_MAP,
            'tr' => self::TURKISH_MAP,
            'pl' => self::POLISH_MAP,
            'ro' => self::ROMANIAN_MAP,
            default => [],
        };

        if (!empty($map)) {
            $text = str_replace(array_keys($map), array_values($map), $text);
        }

        return $text;
    }
}
