<?php

namespace App\Support;

/**
 * Word-aware truncation that never cuts in the middle of a word.
 *
 * Replaces the brittle pattern `mb_substr($text, 0, $max)` which produces
 * "Guide complet et à jo" instead of "Guide complet et à jour."
 *
 * Behavior:
 *  - If $text already fits in $max, returns it unchanged.
 *  - Else cuts at the last word boundary that keeps the result ≤ $max,
 *    appending $suffix (default "…").
 *  - Preserves the trailing terminal punctuation (.!?…) when present.
 *  - If no usable word boundary exists (single very long word), falls back
 *    to a hard cut at $max - mb_strlen($suffix) and appends $suffix.
 */
final class SmartTruncate
{
    public static function run(?string $text, int $max, string $suffix = '…'): string
    {
        $text = trim((string) ($text ?? ''));
        if ($text === '' || $max <= 0) return '';

        if (mb_strlen($text) <= $max) return $text;

        $suffixLen = mb_strlen($suffix);
        $window    = $max - $suffixLen;
        if ($window <= 0) return mb_substr($suffix, 0, $max);

        $truncated = mb_substr($text, 0, $window);

        // Prefer breaking at the last sentence terminator if one is in the window.
        if (preg_match('/[\.!\?…](?:\s|$)/u', $truncated, $m, PREG_OFFSET_CAPTURE)) {
            // find the *last* terminator
            $lastEnd = 0;
            $offset = 0;
            while (preg_match('/[\.!\?…](?:\s|$)/u', $truncated, $m2, PREG_OFFSET_CAPTURE, $offset)) {
                $lastEnd = $m2[0][1] + 1; // include the punctuation
                $offset = $m2[0][1] + 1;
            }
            if ($lastEnd >= $window * 0.5) {
                return rtrim(mb_substr($truncated, 0, $lastEnd));
            }
        }

        // Otherwise break at the last whitespace, but only if it's reasonably late
        // (≥ 70% of the window) so we don't return a stub.
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace >= $window * 0.7) {
            return rtrim(mb_substr($truncated, 0, $lastSpace), " ,;:") . $suffix;
        }

        // Single very long token — hard cut.
        return $truncated . $suffix;
    }
}
