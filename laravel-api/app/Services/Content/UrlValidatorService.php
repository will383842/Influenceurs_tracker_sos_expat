<?php

namespace App\Services\Content;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Validates external URLs before they get injected into article HTML.
 *
 * Strategy: send a HEAD request, follow redirects. Treat the URL as VALID
 * for any 2xx/3xx, plus 401/403/405/429 (these typically mean the page
 * exists but blocks bots — common for sites like idealista, sepe, etc.).
 * Treat 404/410 as broken. Network failures don't penalise the link
 * (we'd rather inject than over-prune on a transient hiccup).
 *
 * Results are cached 24h to keep the article generation pipeline fast and
 * to avoid hammering external sites on every cycle.
 */
class UrlValidatorService
{
    private const CACHE_TTL = 86400; // 24h
    private const HEAD_TIMEOUT = 6;
    private const CONNECT_TIMEOUT = 4;

    /** HTTP codes that mean "page exists but blocks our bot" — keep them. */
    private const BOT_BLOCKED_CODES = [401, 403, 405, 429];

    /** HTTP codes that mean "page doesn't exist" — drop them. */
    private const DEAD_CODES = [404, 410];

    public function isValid(string $url): bool
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $cacheKey = 'ext_url_valid:' . md5($url);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($url) {
            return $this->probe($url);
        });
    }

    /**
     * Validate a batch in parallel using cURL multi. Returns [url => bool].
     * Uses the same cache as isValid() to avoid re-probing known-good links.
     */
    public function isValidBatch(array $urls): array
    {
        $result = [];
        $toProbe = [];

        foreach ($urls as $url) {
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                $result[$url] = false;
                continue;
            }
            $cacheKey = 'ext_url_valid:' . md5($url);
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $result[$url] = (bool) $cached;
            } else {
                $toProbe[] = $url;
            }
        }

        if (empty($toProbe)) {
            return $result;
        }

        $mh = curl_multi_init();
        $handles = [];

        foreach ($toProbe as $url) {
            $ch = $this->newHandle($url);
            curl_multi_add_handle($mh, $ch);
            $handles[(int)$ch] = ['ch' => $ch, 'url' => $url];
        }

        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 0.3);
        } while ($running > 0);

        foreach ($handles as $h) {
            $ok = $this->interpretResult($h['ch']);
            $result[$h['url']] = $ok;
            Cache::put('ext_url_valid:' . md5($h['url']), $ok, self::CACHE_TTL);
            curl_multi_remove_handle($mh, $h['ch']);
            curl_close($h['ch']);
        }
        curl_multi_close($mh);

        return $result;
    }

    private function probe(string $url): bool
    {
        $ch = $this->newHandle($url);
        curl_exec($ch);
        $ok = $this->interpretResult($ch);
        curl_close($ch);
        return $ok;
    }

    private function newHandle(string $url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => self::HEAD_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; SOS-Expat-LinkChecker/1.0; +https://sos-expat.com)',
        ]);
        return $ch;
    }

    private function interpretResult($ch): bool
    {
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);

        // Network failure → optimistic: keep the link (don't penalize transient issues).
        if ($errno !== 0) {
            Log::debug('UrlValidatorService: probe network error, keeping link', [
                'errno' => $errno,
                'url'   => curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
            ]);
            return true;
        }

        // Definitively dead.
        if (in_array($code, self::DEAD_CODES, true)) {
            return false;
        }

        // Bot-blocked but exists.
        if (in_array($code, self::BOT_BLOCKED_CODES, true)) {
            return true;
        }

        // 2xx/3xx
        if ($code >= 200 && $code < 400) {
            return true;
        }

        // 5xx → optimistic (could be a temporary outage).
        if ($code >= 500) {
            return true;
        }

        // Other 4xx (400, 402, 406, 408, 411, 413, ...) → drop.
        return false;
    }
}
