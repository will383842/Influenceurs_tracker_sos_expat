<?php

namespace App\Services\Social\Drivers;

use App\Models\SocialPost;
use App\Models\SocialToken;
use App\Services\Social\AbstractSocialDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pinterest driver — REST API v5.
 *
 * Required env:
 *   PINTEREST_CLIENT_ID, PINTEREST_CLIENT_SECRET, PINTEREST_REDIRECT_URI,
 *   PINTEREST_BOARD_ID (the board to publish Pins to)
 *
 * OAuth scopes (Pinterest review ~3-5 days, faster than Meta):
 *   pins:read, pins:write, boards:read, user_accounts:read
 *
 * Publishing model:
 *   POST /v5/pins  with image_url + title + description + link + board_id
 *
 * Capabilities :
 *   - Image MANDATORY (Pinterest = visual platform)
 *   - No native comments (Pinterest only has reactions/saves)
 *   - Hashtags work in description (Pinterest = visual search engine)
 *   - 500 chars description max
 *   - 100 chars title max
 */
class PinterestDriver extends AbstractSocialDriver
{
    private const OAUTH_DIALOG = 'https://www.pinterest.com/oauth/';
    private const TOKEN_URL    = 'https://api.pinterest.com/v5/oauth/token';
    private const API          = 'https://api.pinterest.com/v5';
    private const SCOPES       = 'pins:read,pins:write,boards:read,user_accounts:read';

    public function platform(): string { return 'pinterest'; }

    public function supportedAccountTypes(): array { return ['business']; }
    public function supportsFirstComment(): bool   { return false; } // Pinterest has no comments concept
    public function supportsHashtags(): bool       { return true; }   // hashtags in description = SEO signal
    public function requiresImage(): bool          { return true; }   // Pin = image
    public function maxContentLength(): int        { return 500; }    // description max

    // ── Publishing ─────────────────────────────────────────────────────

    public function publish(SocialPost $post, ?string $accountType = null): ?string
    {
        if (!$post->featured_image_url) {
            $this->logError('publish: featured_image_url required for Pinterest');
            return null;
        }

        $token = $this->resolveToken($this->resolveAccountType($accountType));
        if (!$token) {
            $this->logError('publish: no valid token');
            return null;
        }

        $boardId = $token->metadata['board_id'] ?? config('services.pinterest.board_id');
        if (!$boardId) {
            $this->logError('publish: no board_id configured (set PINTEREST_BOARD_ID or run setBoard)');
            return null;
        }

        // Pinterest model:
        //   title (~100 chars) = the hook
        //   description (~500 chars) = hook + body trimmed, with hashtags inline (SEO)
        //   link = source URL (the Pin clicks through to this)
        $title = mb_substr(trim($post->hook), 0, 100);
        $hashtags = array_map(fn($h) => "#{$h}", $post->hashtags ?? []);
        $description = trim($post->body . ($hashtags ? "\n\n" . implode(' ', $hashtags) : ''));
        $description = mb_substr($description, 0, $this->maxContentLength());

        $link = $post->platform_metadata['source_url']
            ?? config('services.site.url', 'https://sos-expat.com');

        try {
            $r = Http::withToken($token->access_token)
                ->acceptJson()
                ->asJson()
                ->post(self::API . '/pins', [
                    'board_id'    => $boardId,
                    'title'       => $title,
                    'description' => $description,
                    'link'        => $link,
                    'media_source' => [
                        'source_type' => 'image_url',
                        'url'         => $post->featured_image_url,
                    ],
                ]);

            if (!$r->successful()) {
                $this->handleApiError($r, $token);
                throw new \RuntimeException("Pinterest publish failed: HTTP {$r->status()} — " . mb_substr($r->body(), 0, 300));
            }

            $pinId = $r->json()['id'] ?? null;
            Log::info('PinterestDriver: published', ['post_id' => $post->id, 'pin_id' => $pinId]);
            return $pinId;

        } catch (\Throwable $e) {
            $this->logError('publish failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function getComments(string $platformPostId, ?string $accountType = null): array
    {
        // Pinterest API v5 does not expose Pin comments (only reactions/saves count).
        // Return empty array — the social:check-comments command will skip cleanly.
        return [];
    }

    public function postReply(string $platformPostId, string $text, ?string $accountType = null): bool
    {
        // No native comments → no reply mechanism. Return false (graceful skip).
        return false;
    }

    public function postFirstComment(string $platformPostId, string $text, ?string $accountType = null): bool
    {
        // Same reason — Pinterest has no comments concept.
        return false;
    }

    public function fetchAnalytics(string $platformPostId, ?string $accountType = null): array
    {
        $token = $this->resolveToken($this->resolveAccountType($accountType));
        if (!$token) return [];

        try {
            // Pin-level analytics via /v5/pins/{pin_id}/analytics
            $r = Http::withToken($token->access_token)
                ->acceptJson()
                ->get(self::API . "/pins/{$platformPostId}/analytics", [
                    'metric_types' => 'IMPRESSION,SAVE,PIN_CLICK,OUTBOUND_CLICK',
                ]);

            if (!$r->successful()) return [];

            // Pinterest analytics shape: { "all": { "summary_metrics": { "IMPRESSION": 123, "SAVE": 4, ...} } }
            $metrics = $r->json()['all']['summary_metrics'] ?? [];

            return [
                'reach'    => (int) ($metrics['IMPRESSION']     ?? 0),
                'likes'    => 0, // Pinterest n'a pas de likes natifs
                'comments' => 0, // pas de comments
                'shares'   => (int) ($metrics['SAVE']           ?? 0), // saves = équivalent share
                'clicks'   => (int) ($metrics['OUTBOUND_CLICK'] ?? 0)
                            + (int) ($metrics['PIN_CLICK']      ?? 0),
            ];
        } catch (\Throwable $e) {
            $this->logError('fetchAnalytics exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ── OAuth ──────────────────────────────────────────────────────────

    public function getOAuthUrl(string $accountType, string $state): string
    {
        $params = http_build_query([
            'client_id'     => config('services.pinterest.client_id'),
            'redirect_uri'  => config('services.pinterest.redirect_uri'),
            'response_type' => 'code',
            'state'         => $state,
            'scope'         => self::SCOPES,
        ]);
        return self::OAUTH_DIALOG . '?' . $params;
    }

    public function handleOAuthCallback(string $code, string $accountType): ?SocialToken
    {
        try {
            // Pinterest uses HTTP Basic auth (client_id:client_secret) for token exchange
            $r = Http::withBasicAuth(
                    config('services.pinterest.client_id'),
                    config('services.pinterest.client_secret'),
                )
                ->asForm()
                ->post(self::TOKEN_URL, [
                    'grant_type'   => 'authorization_code',
                    'code'         => $code,
                    'redirect_uri' => config('services.pinterest.redirect_uri'),
                ]);

            if (!$r->successful()) {
                $this->logError('OAuth code exchange failed', [
                    'status' => $r->status(),
                    'body'   => mb_substr($r->body(), 0, 300),
                ]);
                return null;
            }

            $d = $r->json();
            $accessToken  = $d['access_token']  ?? null;
            $refreshToken = $d['refresh_token'] ?? null;
            $expiresIn    = (int) ($d['expires_in'] ?? 2592000); // 30d default
            if (!$accessToken) return null;

            // Fetch user profile to get account name
            $profile = Http::withToken($accessToken)
                ->acceptJson()
                ->get(self::API . '/user_account')
                ->json();
            $username = $profile['username'] ?? null;
            $userId   = $profile['account_type'] ?? 'business';

            // Auto-pick first business board if none configured
            $boardId = config('services.pinterest.board_id');
            $metadata = ['board_id' => $boardId];
            if (!$boardId) {
                $boards = Http::withToken($accessToken)
                    ->acceptJson()
                    ->get(self::API . '/boards', ['page_size' => 1])
                    ->json();
                $firstBoard = $boards['items'][0]['id'] ?? null;
                $metadata['board_id'] = $firstBoard;
            }

            return SocialToken::updateOrCreate(
                ['platform' => 'pinterest', 'account_type' => 'business'],
                [
                    'access_token'             => $accessToken,
                    'refresh_token'            => $refreshToken,
                    'expires_at'               => now()->addSeconds($expiresIn),
                    'refresh_token_expires_at' => isset($d['refresh_token_expires_in'])
                                                    ? now()->addSeconds((int) $d['refresh_token_expires_in'])
                                                    : null,
                    'platform_user_id'         => $username ?? 'pinterest_user',
                    'platform_user_name'       => $username,
                    'scope'                    => self::SCOPES,
                    'metadata'                 => $metadata,
                ]
            );

        } catch (\Throwable $e) {
            $this->logError('handleOAuthCallback exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function resolveToken(string $accountType): ?SocialToken
    {
        $token = $this->findToken($accountType);
        if (!$token) return null;

        // Auto-refresh if expiring within 7 days and we have a refresh token
        if ($token->expires_at
            && $token->expires_at->diffInDays(now(), false) >= -7
            && $token->refresh_token) {
            $refreshed = $this->refreshAccessToken($token);
            if ($refreshed) $token = $refreshed;
        }

        return $token->isValid() ? $token : null;
    }

    private function refreshAccessToken(SocialToken $token): ?SocialToken
    {
        try {
            $r = Http::withBasicAuth(
                    config('services.pinterest.client_id'),
                    config('services.pinterest.client_secret'),
                )
                ->asForm()
                ->post(self::TOKEN_URL, [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $token->refresh_token,
                ]);

            if (!$r->successful()) {
                $this->notifyTokenExpired($token->account_type, $r->status());
                return null;
            }

            $d = $r->json();
            $token->access_token = $d['access_token'] ?? $token->access_token;
            $token->expires_at   = now()->addSeconds((int) ($d['expires_in'] ?? 2592000));
            if (!empty($d['refresh_token'])) $token->refresh_token = $d['refresh_token'];
            $token->save();
            return $token->fresh();
        } catch (\Throwable $e) {
            $this->logError('refreshAccessToken exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function handleApiError(\Illuminate\Http\Client\Response $r, SocialToken $token): void
    {
        $err = $r->json() ?? [];
        $code = $err['code'] ?? $r->status();
        $message = $err['message'] ?? '';

        // 401 = unauthorized → notify token issue
        if ($r->status() === 401) {
            $this->notifyTokenExpired($token->account_type, 401);
        }

        Log::warning('PinterestDriver: API error', [
            'http_status' => $r->status(),
            'code'        => $code,
            'message'     => $message,
        ]);
    }
}
