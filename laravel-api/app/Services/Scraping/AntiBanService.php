<?php

namespace App\Services\Scraping;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Garde-fous globaux anti-bannissement pour tous les scrapers.
 *
 *  - Rate limit per-domaine (max 20 req/min par hostname).
 *  - Circuit breaker : si un domaine renvoie 3× 403/429 en 1h → pause 24h.
 *  - Delays aléatoires entre 1.5s et 4.5s.
 *
 * Ces garde-fous VIENNENT EN PLUS des limites locales existantes
 * (ex. ScrapeDirectoryJob::MAX_REQUESTS_PER_MINUTE = 3). En cas de conflit,
 * c'est la limite la plus stricte qui prime (ceinture + bretelles).
 */
class AntiBanService
{
    private const MAX_REQUESTS_PER_MINUTE = 20;
    private const FAILURE_WINDOW_SECONDS  = 3600;
    private const FAILURE_THRESHOLD       = 3;
    private const CIRCUIT_PAUSE_SECONDS   = 86400;

    private const DELAY_MIN_MS = 1500;
    private const DELAY_MAX_MS = 4500;

    public function shouldThrottle(string $hostname): bool
    {
        if ($this->isCircuitOpen($hostname)) {
            return true;
        }

        $count = (int) Cache::get($this->rateKey($hostname), 0);
        return $count >= self::MAX_REQUESTS_PER_MINUTE;
    }

    public function registerHit(string $hostname): void
    {
        $key = $this->rateKey($hostname);
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, 60);
    }

    public function registerFailure(string $hostname, int $httpCode): void
    {
        if (!in_array($httpCode, [403, 429], true)) {
            return;
        }

        $key = $this->failureKey($hostname);
        $failures = (array) Cache::get($key, []);
        $failures[] = now()->timestamp;

        // Ne garder que les échecs de la fenêtre glissante
        $cutoff = now()->timestamp - self::FAILURE_WINDOW_SECONDS;
        $failures = array_values(array_filter($failures, fn ($ts) => $ts >= $cutoff));

        Cache::put($key, $failures, self::FAILURE_WINDOW_SECONDS);

        if (count($failures) >= self::FAILURE_THRESHOLD) {
            $this->openCircuit($hostname);
            Log::warning('AntiBan: circuit breaker opened', [
                'hostname' => $hostname,
                'failures' => count($failures),
                'http_code' => $httpCode,
            ]);
        }
    }

    public function randomDelay(): void
    {
        $ms = random_int(self::DELAY_MIN_MS, self::DELAY_MAX_MS);
        usleep($ms * 1000);
    }

    public function isCircuitOpen(string $hostname): bool
    {
        return Cache::has($this->circuitKey($hostname));
    }

    public function circuitBreakerDomains(): array
    {
        // Best-effort list — limité au Redis/DB cache courant.
        // Try/catch pour résilience : si le driver cache est indispo,
        // on renvoie une liste vide plutôt que de faire crasher le controller.
        try {
            return (array) Cache::get('scraper:circuit_breakers', []);
        } catch (\Throwable $e) {
            Log::warning('AntiBanService: circuitBreakerDomains cache error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public static function hostnameFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        return $host ? strtolower($host) : null;
    }

    private function openCircuit(string $hostname): void
    {
        Cache::put($this->circuitKey($hostname), true, self::CIRCUIT_PAUSE_SECONDS);

        $list = (array) Cache::get('scraper:circuit_breakers', []);
        $list[$hostname] = now()->timestamp + self::CIRCUIT_PAUSE_SECONDS;
        Cache::put('scraper:circuit_breakers', $list, self::CIRCUIT_PAUSE_SECONDS);
    }

    private function rateKey(string $hostname): string
    {
        return 'scraper:rate:' . $hostname;
    }

    private function failureKey(string $hostname): string
    {
        return 'scraper:failures:' . $hostname;
    }

    private function circuitKey(string $hostname): string
    {
        return 'scraper:circuit:' . $hostname;
    }
}
