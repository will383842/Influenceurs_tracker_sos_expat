<?php

namespace App\Services\Seo;

use App\Models\CountryGeo;
use Illuminate\Support\Facades\Cache;

/**
 * Provides geo metadata (coordinates, capital, language) for a given country code.
 * Used to enrich article meta tags and JSON-LD schemas.
 */
class GeoMetaService
{
    private const CACHE_TTL = 86400; // 24h

    /** Map of language codes → og:locale */
    public const OG_LOCALE_MAP = [
        'fr' => 'fr_FR',
        'en' => 'en_US',
        'es' => 'es_ES',
        'de' => 'de_DE',
        'pt' => 'pt_PT',
        'ru' => 'ru_RU',
        'zh' => 'zh_CN',
        'hi' => 'hi_IN',
        'ar' => 'ar_SA',
    ];

    /** Map of language codes → default country ISO for URL building */
    public const DEFAULT_COUNTRY_MAP = [
        'fr' => 'fr', 'en' => 'us', 'es' => 'es',
        'de' => 'de', 'pt' => 'pt', 'ru' => 'ru',
        'zh' => 'cn', 'hi' => 'in', 'ar' => 'sa',
    ];

    public function getByCode(string $countryCode): ?CountryGeo
    {
        $code = strtoupper(trim($countryCode));
        return Cache::remember("country_geo_{$code}", self::CACHE_TTL, fn () => CountryGeo::find($code));
    }

    public function getGeoRegion(string $countryCode): string
    {
        return strtoupper(trim($countryCode));
    }

    public function getGeoPlacename(string $countryCode, string $lang = 'fr'): string
    {
        $geo = $this->getByCode($countryCode);
        if (!$geo) return $countryCode;
        return $lang === 'en' ? $geo->country_name_en : $geo->country_name_fr;
    }

    /** Returns "lat;lon" for meta geo.position */
    public function getGeoPosition(string $countryCode): ?string
    {
        $geo = $this->getByCode($countryCode);
        return $geo ? "{$geo->latitude};{$geo->longitude}" : null;
    }

    /** Returns "lat, lon" for meta ICBM */
    public function getIcbm(string $countryCode): ?string
    {
        $geo = $this->getByCode($countryCode);
        return $geo ? "{$geo->latitude}, {$geo->longitude}" : null;
    }

    public function getCapital(string $countryCode, string $lang = 'fr'): string
    {
        $geo = $this->getByCode($countryCode);
        if (!$geo) return '';
        return $lang === 'en' ? $geo->capital_en : $geo->capital_fr;
    }

    public function getOfficialLanguage(string $countryCode): string
    {
        $geo = $this->getByCode($countryCode);
        return $geo ? $geo->official_language : '';
    }

    public function getRegion(string $countryCode): string
    {
        $geo = $this->getByCode($countryCode);
        return $geo ? ($geo->region ?? '') : '';
    }

    public function getExpatCount(string $countryCode): int
    {
        $geo = $this->getByCode($countryCode);
        return $geo ? $geo->expat_approx : 0;
    }

    public function getOgLocale(string $language): string
    {
        return self::OG_LOCALE_MAP[$language] ?? 'fr_FR';
    }

    /**
     * Build a rich country context paragraph for injecting in article prompts.
     * Used in Phase 5 of ArticleGenerationService.
     */
    public function buildCountryContextForPrompt(string $countryCode, string $language = 'fr'): string
    {
        $geo = $this->getByCode($countryCode);
        if (!$geo) return '';

        $countryName = $language === 'en' ? $geo->country_name_en : $geo->country_name_fr;
        $capital = $language === 'en' ? $geo->capital_en : $geo->capital_fr;
        $expat = number_format($geo->expat_approx);

        return "═══ CONTEXTE LOCAL OBLIGATOIRE ═══\n"
            . "Pays cible : {$countryName} (code ISO : {$countryCode})\n"
            . "Capitale : {$capital}\n"
            . "Langue officielle : {$geo->official_language}\n"
            . "Devise : {$geo->currency_name} ({$geo->currency_code})\n"
            . "Région : {$geo->region}\n"
            . ($expat > 0 ? "Expatriés estimés : ~{$expat}\n" : '')
            . "\nOBLIGATOIRE dans l'article :\n"
            . "- Mentionner '{$countryName}' dans le H1 ET dans le premier paragraphe\n"
            . "- Mentionner la capitale '{$capital}' dans le contenu\n"
            . "- Inclure au moins 1 fait local spécifique à {$countryName} (contexte légal, visa, coût de la vie, communauté expat)\n"
            . "- NE PAS copier-coller d'un pays à l'autre : ce contenu doit être UNIQUE à {$countryName}\n";
    }

    /**
     * Build country context suffix for FAQ prompt (Phase 6).
     */
    public function buildFaqCountryConstraint(string $countryCode, string $language = 'fr'): string
    {
        $geo = $this->getByCode($countryCode);
        if (!$geo) return '';
        $countryName = $language === 'en' ? $geo->country_name_en : $geo->country_name_fr;
        return "\nCONTRAINTE ABSOLUE : CHAQUE question de la FAQ DOIT mentionner explicitement le nom du pays '{$countryName}'. "
            . "Ex: 'Comment obtenir un visa pour {$countryName} ?' — jamais de question générique sans le pays.";
    }
}
