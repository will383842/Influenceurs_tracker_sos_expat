<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;

/**
 * Détecte si Perplexity est utilisable avant de lancer un scraper IA.
 *
 * Retourne un diagnostic précis (clé absente, 401, 429, quota épuisé, 5xx),
 * mis en cache 5 min pour ne pas gaspiller des appels de ping à chaque run.
 *
 * Utilisation :
 *   $check = app(PerplexityHealthChecker::class)->isUsable();
 *   if (!$check['usable']) {
 *       $this->alertSkipped($check['reason']);
 *       return 0;
 *   }
 */
class PerplexityHealthChecker
{
    private const CACHE_KEY = 'perplexity:health';
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(private PerplexityService $perplexity)
    {
    }

    /**
     * @return array{usable: bool, reason: string}
     */
    public function isUsable(bool $useCache = true): array
    {
        if (!$this->perplexity->isConfigured()) {
            return ['usable' => false, 'reason' => 'Perplexity API key not configured'];
        }

        if ($useCache) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached) && array_key_exists('usable', $cached)) {
                return $cached;
            }
        }

        $result = $this->ping();
        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL_SECONDS);

        return $result;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{usable: bool, reason: string}
     */
    private function ping(): array
    {
        $ping = $this->perplexity->search('ping');

        if ($ping['success']) {
            return ['usable' => true, 'reason' => 'ok'];
        }

        $error = strtolower((string) ($ping['error'] ?? ''));

        if (str_contains($error, 'insufficient_credits') || str_contains($error, 'quota')) {
            return ['usable' => false, 'reason' => 'Crédits Perplexity épuisés (insufficient_credits)'];
        }
        if (str_contains($error, 'http 401')) {
            return ['usable' => false, 'reason' => 'Clé Perplexity invalide (HTTP 401)'];
        }
        if (str_contains($error, 'http 429')) {
            return ['usable' => false, 'reason' => 'Rate limit ou quota Perplexity atteint (HTTP 429)'];
        }
        if (preg_match('/http 5\d\d/', $error)) {
            return ['usable' => false, 'reason' => 'Perplexity indisponible (' . strtoupper($error) . ')'];
        }

        return ['usable' => false, 'reason' => 'Erreur Perplexity : ' . ($ping['error'] ?? 'unknown')];
    }
}
