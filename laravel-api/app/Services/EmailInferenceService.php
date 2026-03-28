<?php

namespace App\Services;

use App\Models\PressContact;
use App\Models\PressPublication;
use Illuminate\Support\Facades\Log;

/**
 * Generates candidate email addresses from name + domain pattern,
 * then optionally SMTP-verifies them.
 *
 * Supported pattern tokens:
 *   {first}  → firstname (lowercase, accents removed, hyphens kept)
 *   {last}   → lastname (lowercase, accents removed)
 *   {f}      → initial of first name
 *   {fl}     → first + last concatenated without separator
 *   {f_last} → f + last   (e.g. jdupont)
 */
class EmailInferenceService
{
    // ─── PUBLIC API ───────────────────────────────────────────────────────────

    /**
     * Infer emails for all contacts of a publication that have no email yet.
     *
     * @return array{inferred: int, failed: int, skipped: int}
     */
    public function inferForPublication(PressPublication $pub): array
    {
        if (!$pub->email_pattern || !$pub->email_domain) {
            return ['inferred' => 0, 'failed' => 0, 'skipped' => 0, 'reason' => 'no_pattern'];
        }

        $contacts = $pub->contacts()
            ->whereNull('email')
            ->whereNotNull('first_name')
            ->whereNotNull('last_name')
            ->get();

        $inferred = 0;
        $failed   = 0;
        $skipped  = 0;

        foreach ($contacts as $contact) {
            $candidate = $this->buildEmail($contact->first_name, $contact->last_name, $pub->email_pattern, $pub->email_domain);

            if (!$candidate) {
                $skipped++;
                continue;
            }

            // Check no other contact already has this email
            if (PressContact::where('email', $candidate)->exists()) {
                $skipped++;
                continue;
            }

            $contact->update([
                'email'        => $candidate,
                'email_source' => 'inferred',
            ]);
            $inferred++;
        }

        if ($inferred > 0) {
            $pub->increment('emails_inferred', $inferred);
        }

        Log::info("EmailInferenceService: {$pub->name} — inferred={$inferred}, failed={$failed}, skipped={$skipped}");

        return compact('inferred', 'failed', 'skipped');
    }

    /**
     * Build a single candidate email from first/last name + pattern + domain.
     */
    public function buildEmail(string $firstName, string $lastName, string $pattern, string $domain): ?string
    {
        $first = $this->normalize($firstName);
        $last  = $this->normalize($lastName);

        if (!$first || !$last) {
            return null;
        }

        $f    = mb_substr($first, 0, 1);
        $fl   = $first . $last;
        $fLast = $f . $last;

        $local = str_replace(
            ['{first}', '{last}', '{f}', '{fl}', '{f_last}'],
            [$first,    $last,    $f,    $fl,    $fLast],
            $pattern
        );

        // Remove @domain part from pattern if present (it may include it)
        $local = preg_replace('/@.+$/', '', $local);

        if (!preg_match('/^[a-z0-9._-]+$/i', $local)) {
            return null;
        }

        $email = strtolower($local) . '@' . strtolower(trim($domain, '@'));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * SMTP-verify a list of email addresses.
     * Returns array keyed by email => bool (valid/invalid).
     *
     * This performs a lightweight MX + SMTP RCPT TO check without sending any email.
     * May be rate-limited by mail servers — use sparingly.
     *
     * @param  string[]  $emails
     * @return array<string, bool>
     */
    public function smtpVerify(array $emails, int $timeoutSec = 5): array
    {
        $results = [];

        // Group by domain for MX lookup efficiency
        $byDomain = [];
        foreach ($emails as $email) {
            $email = strtolower(trim($email));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results[$email] = false;
                continue;
            }
            $domain = explode('@', $email)[1] ?? null;
            if ($domain) {
                $byDomain[$domain][] = $email;
            }
        }

        foreach ($byDomain as $domain => $domainEmails) {
            $mx = $this->getMxHost($domain);
            if (!$mx) {
                foreach ($domainEmails as $e) $results[$e] = false;
                continue;
            }

            foreach ($domainEmails as $email) {
                $results[$email] = $this->checkSmtp($mx, $email, $timeoutSec);
            }
        }

        return $results;
    }

    /**
     * Mark verified emails on press_contacts and update publication counter.
     *
     * @param  array<string, bool>  $verificationResults  email => valid
     */
    public function saveSmtpResults(array $verificationResults): void
    {
        foreach ($verificationResults as $email => $valid) {
            PressContact::where('email', $email)->update([
                'email_smtp_valid' => $valid,
                'email_checked_at' => now(),
            ]);
        }

        // Update publication verified count
        $verifiedCount = collect($verificationResults)->filter()->count();
        if ($verifiedCount > 0) {
            // We don't have pub context here — update via join
            PressPublication::whereHas('contacts', fn($q) => $q->whereIn('email', array_keys($verificationResults)))
                ->each(function (PressPublication $pub) {
                    $count = $pub->contacts()->whereNotNull('email')->where('email_smtp_valid', true)->count();
                    $pub->update(['emails_verified' => $count]);
                });
        }
    }

    // ─── PRIVATE HELPERS ──────────────────────────────────────────────────────

    /**
     * Normalize a name component to lowercase ASCII-only slug for email use.
     * Preserves hyphens (Marie-Claire → marie-claire).
     */
    private function normalize(string $name): string
    {
        // Remove parenthetical (e.g. "Jean-Luc (reporter)" → "Jean-Luc")
        $name = preg_replace('/\s*\(.*\)/', '', $name);
        $name = trim($name);

        // Convert accented chars to ASCII
        $map = [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','í'=>'i','ì'=>'i',
            'ô'=>'o','ö'=>'o','ó'=>'o','ò'=>'o','õ'=>'o',
            'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
            'ç'=>'c','ñ'=>'n','ß'=>'ss',
            'À'=>'A','Â'=>'A','Ä'=>'A','Á'=>'A',
            'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
            'Î'=>'I','Ï'=>'I',
            'Ô'=>'O','Ö'=>'O',
            'Ù'=>'U','Û'=>'U','Ü'=>'U',
            'Ç'=>'C','Ñ'=>'N',
        ];

        $name = strtr($name, $map);

        // Keep only letters and hyphens
        $name = preg_replace('/[^a-zA-Z-]/', '', $name);
        $name = trim($name, '-');

        return strtolower($name);
    }

    /**
     * Get the highest-priority MX host for a domain.
     */
    private function getMxHost(string $domain): ?string
    {
        if (!function_exists('getmxrr')) {
            return $domain; // fallback: try domain directly
        }

        $mxHosts   = [];
        $mxWeights = [];

        if (!@getmxrr($domain, $mxHosts, $mxWeights)) {
            return null;
        }

        if (empty($mxHosts)) {
            return $domain;
        }

        // Sort by weight (lower = higher priority)
        array_multisort($mxWeights, SORT_ASC, $mxHosts);

        return $mxHosts[0];
    }

    /**
     * Attempt SMTP RCPT TO check without sending email.
     * Returns true if server indicates address exists.
     */
    private function checkSmtp(string $mxHost, string $email, int $timeout): bool
    {
        try {
            $socket = @fsockopen($mxHost, 25, $errno, $errstr, $timeout);
            if (!$socket) {
                // Try port 587
                $socket = @fsockopen($mxHost, 587, $errno, $errstr, $timeout);
                if (!$socket) return false;
            }

            stream_set_timeout($socket, $timeout);

            $read = fgets($socket, 1024);
            if (!str_starts_with(trim($read), '220')) {
                fclose($socket);
                return false;
            }

            $domain = gethostname() ?: 'verify.local';
            fputs($socket, "EHLO {$domain}\r\n");
            $this->readSmtpResponse($socket);

            fputs($socket, "MAIL FROM:<noreply@{$domain}>\r\n");
            $this->readSmtpResponse($socket);

            fputs($socket, "RCPT TO:<{$email}>\r\n");
            $response = $this->readSmtpResponse($socket);

            fputs($socket, "QUIT\r\n");
            fclose($socket);

            // 250 = OK, 251 = forwarded (also valid)
            return str_starts_with(trim($response), '25');

        } catch (\Throwable $e) {
            Log::debug("SMTP verify failed for {$email}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Read a full SMTP multi-line response and return the last line.
     */
    private function readSmtpResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 1024)) {
            $response = $line;
            // Multi-line responses have '-' as 4th character (e.g. "250-SIZE 10240000")
            if (isset($line[3]) && $line[3] !== '-') break;
        }
        return $response;
    }
}
