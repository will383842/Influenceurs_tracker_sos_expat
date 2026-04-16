<?php

namespace App\Console\Commands;

use App\Models\LinkedInToken;
use App\Services\Social\TelegramAlertService;
use Illuminate\Console\Command;

/**
 * Daily token health check — runs at 08:00 UTC.
 * Sends ONE Telegram alert only when manual reconnection is truly needed:
 *  - Token expires in ≤ 7 days AND no refresh token
 *  - Token already expired
 */
class CheckLinkedInTokenCommand extends Command
{
    protected $signature   = 'linkedin:check-token';
    protected $description = 'Alert via Telegram only when LinkedIn reconnection is required';

    public function __construct(private TelegramAlertService $telegram) {
        parent::__construct();
    }

    public function handle(): int
    {
        $token = LinkedInToken::where('account_type', 'personal')->first();

        if (!$token) return self::SUCCESS;

        $daysLeft   = $token->expiresInDays();
        $hasRefresh = !empty($token->refresh_token);

        // Only alert when reconnection is truly needed: expired OR expiring soon without auto-refresh
        $needsReconnect = !$token->isValid() || (!$hasRefresh && $daysLeft <= 7);

        if ($needsReconnect && $this->telegram->isConfigured()) {
            $reason = !$token->isValid()
                ? 'Le token a expiré.'
                : "Le token expire dans <b>{$daysLeft} jour(s)</b> et ne peut pas se renouveler automatiquement.";

            $this->telegram->sendMessage(
                "🔴 <b>LinkedIn — reconnexion requise</b>\n\n"
                . "{$reason}\n\n"
                . "Les publications seront suspendues sans action.\n\n"
                . "→ <b>Mission Control → LinkedIn → ⚙️ Gérer → 🔄 Reconnecter</b>"
            );

            $this->warn("Alert sent: {$reason}");
        }

        return self::SUCCESS;
    }
}
