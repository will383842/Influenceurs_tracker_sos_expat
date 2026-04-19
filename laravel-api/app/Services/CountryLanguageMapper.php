<?php

namespace App\Services;

use App\Models\CountryGeo;
use Illuminate\Support\Facades\Log;

/**
 * Résout la langue ISO-2 d'un contact scrapé à partir :
 *   1. de l'attribut <html lang="…"> si fourni (source la plus fiable),
 *   2. de la langue officielle stockée dans countries_geo (si pays reconnu),
 *   3. d'un fallback 'en' + log warning.
 *
 * Accepte le pays sous plusieurs formats :
 *   - code ISO-2 ('FR', 'fr')
 *   - nom français ('France', 'Corée du Sud')
 *   - nom anglais ('France', 'South Korea')
 *
 * Quand la fiche pays liste plusieurs langues ("Français/Néerlandais"),
 * on prend la première (= langue dominante).
 */
class CountryLanguageMapper
{
    /** Cache en mémoire par requête pour éviter les lookups répétés. */
    private array $cache = [];

    /** Mapping nom de langue (FR/EN) → code ISO-2. Étend le mapping des scrapers existants. */
    private const LANGUAGE_NAME_TO_ISO = [
        'français'      => 'fr', 'francais'    => 'fr', 'french'      => 'fr',
        'anglais'       => 'en', 'english'     => 'en',
        'espagnol'      => 'es', 'spanish'     => 'es',
        'portugais'     => 'pt', 'portuguese'  => 'pt',
        'allemand'      => 'de', 'german'      => 'de',
        'italien'       => 'it', 'italian'     => 'it',
        'néerlandais'   => 'nl', 'nederlandais'=> 'nl', 'dutch'       => 'nl',
        'arabe'         => 'ar', 'arabic'      => 'ar',
        'russe'         => 'ru', 'russian'     => 'ru',
        'chinois'       => 'zh', 'chinese'     => 'zh', 'mandarin'    => 'zh',
        'japonais'      => 'ja', 'japanese'    => 'ja',
        'coréen'        => 'ko', 'korean'      => 'ko', 'coreen'      => 'ko',
        'thai'          => 'th', 'thaï'        => 'th', 'thaïlandais' => 'th',
        'vietnamien'    => 'vi', 'vietnamese'  => 'vi',
        'hindi'         => 'hi',
        'bengali'       => 'bn',
        'turc'          => 'tr', 'turkish'     => 'tr',
        'polonais'      => 'pl', 'polish'      => 'pl',
        'tchèque'       => 'cs', 'czech'       => 'cs',
        'hongrois'      => 'hu', 'hungarian'   => 'hu',
        'suédois'       => 'sv', 'swedish'     => 'sv',
        'norvégien'     => 'no', 'norwegian'   => 'no',
        'danois'        => 'da', 'danish'      => 'da',
        'finnois'       => 'fi', 'finnish'     => 'fi',
        'grec'          => 'el', 'greek'       => 'el',
        'roumain'       => 'ro', 'romanian'    => 'ro',
        'bulgare'       => 'bg', 'bulgarian'   => 'bg',
        'ukrainien'     => 'uk', 'ukrainian'   => 'uk',
        'hébreu'        => 'he', 'hebrew'      => 'he',
        'persan'        => 'fa', 'persian'     => 'fa', 'dari'  => 'fa',
        'malais'        => 'ms', 'malay'       => 'ms',
        'indonésien'    => 'id', 'indonesian'  => 'id',
        'tagalog'       => 'tl', 'filipino'    => 'tl',
        'khmer'         => 'km',
        'swahili'       => 'sw',
        'amharique'     => 'am',
        'albanais'      => 'sq', 'albanian'    => 'sq',
        'bosnien'       => 'bs',
        'serbe'         => 'sr', 'serbian'     => 'sr',
        'croate'        => 'hr', 'croatian'    => 'hr',
        'slovaque'      => 'sk', 'slovak'      => 'sk',
        'slovène'       => 'sl', 'slovenian'   => 'sl',
        'catalan'       => 'ca',
        'arménien'      => 'hy', 'armenian'    => 'hy',
        'géorgien'      => 'ka', 'georgian'    => 'ka',
        'kirundi'       => 'rn',
        'bengali'       => 'bn',
        'dzongkha'      => 'dz',
        'pashto'        => 'ps',
    ];

    /**
     * Retourne le code ISO-2 en minuscules (ex: 'fr', 'en', 'th').
     */
    public function resolveLanguage(?string $country, ?string $htmlLangAttr = null): string
    {
        // 1. Attribut HTML <html lang="fr-FR"> → priorité maximale
        $fromHtml = $this->parseHtmlLang($htmlLangAttr);
        if ($fromHtml) {
            return $fromHtml;
        }

        // 2. Lookup via CountryGeo
        if ($country) {
            $cacheKey = strtolower(trim($country));
            if (isset($this->cache[$cacheKey])) {
                return $this->cache[$cacheKey];
            }

            $geo = $this->findCountry($country);
            if ($geo) {
                $lang = $this->normalizeLanguageName($geo->official_language);
                if ($lang) {
                    return $this->cache[$cacheKey] = $lang;
                }
            }

            Log::warning('CountryLanguageMapper: language unresolved', ['country' => $country]);
        }

        // 3. Fallback
        return 'en';
    }

    /**
     * Retourne l'entrée CountryGeo correspondant au pays, quel que soit le format.
     */
    public function findCountry(string $country): ?CountryGeo
    {
        $input = trim($country);
        if ($input === '') {
            return null;
        }

        // Code ISO-2 direct (2 lettres)
        if (strlen($input) === 2) {
            $found = CountryGeo::findByCode($input);
            if ($found) return $found;
        }

        // Nom FR ou EN (case-insensitive)
        return CountryGeo::where('country_name_fr', $input)
            ->orWhere('country_name_en', $input)
            ->orWhereRaw('LOWER(country_name_fr) = ?', [mb_strtolower($input)])
            ->orWhereRaw('LOWER(country_name_en) = ?', [mb_strtolower($input)])
            ->first();
    }

    private function parseHtmlLang(?string $raw): ?string
    {
        if (!$raw) return null;
        $raw = strtolower(trim($raw));
        if ($raw === '') return null;

        // "fr-FR", "en-US", "zh-CN" → prendre les 2 premières lettres
        if (preg_match('/^([a-z]{2,3})(?:[-_][a-z]{2,4})?$/', $raw, $m)) {
            $code = $m[1];
            return strlen($code) === 2 ? $code : null;
        }
        return null;
    }

    /**
     * "Français" → "fr", "Français/Néerlandais" → "fr" (1ère langue),
     * "fr" → "fr", "en-US" → "en".
     */
    private function normalizeLanguageName(?string $raw): ?string
    {
        if (!$raw) return null;
        $raw = trim($raw);
        if ($raw === '') return null;

        // Multi-langues séparées par '/' ou ',' : on prend la première
        $first = preg_split('#[/,]#', $raw)[0] ?? $raw;
        $first = mb_strtolower(trim($first));

        // Déjà un code ISO-2 ?
        if (preg_match('/^[a-z]{2}$/', $first)) {
            return $first;
        }

        // Code type "en-US"
        if (preg_match('/^([a-z]{2})[-_][a-z]{2,4}$/', $first, $m)) {
            return $m[1];
        }

        // Nom de langue (FR ou EN)
        foreach (self::LANGUAGE_NAME_TO_ISO as $name => $iso) {
            if (str_contains($first, $name)) {
                return $iso;
            }
        }

        return null;
    }
}
