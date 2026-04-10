<?php

namespace App\Console\Commands;

use App\Models\Influenceur;
use App\Models\PressContact;
use App\Services\BacklinkEngineWebhookService;
use Illuminate\Console\Command;

/**
 * Re-synchronize all unsynced contacts to the Backlink Engine.
 * Skips contacts with email provider domains (gmail, hotmail, etc.).
 *
 * Usage: php artisan backlink:resync [--force] [--type=consulat]
 */
class ResyncBacklinkEngine extends Command
{
    protected $signature = 'backlink:resync
                            {--force : Re-sync ALL contacts, even already synced}
                            {--type= : Only sync a specific contact_type}
                            {--dry-run : Count without sending}';

    protected $description = 'Re-synchronize contacts to the Backlink Engine webhook';

    private const JUNK_EMAIL_DOMAINS = [
        'gmail.com', 'yahoo.com', 'yahoo.fr', 'hotmail.com', 'hotmail.fr',
        'outlook.com', 'outlook.fr', 'live.com', 'live.fr', 'aol.com',
        'wanadoo.fr', 'orange.fr', 'free.fr', 'sfr.fr', 'laposte.net',
        'icloud.com', 'me.com', 'mac.com', 'protonmail.com', 'proton.me',
        'ymail.com', 'mail.com', 'gmx.com', 'gmx.fr', 'zoho.com',
        'msn.com', 'comcast.net', 'att.net', 'verizon.net',
    ];

    public function handle(): int
    {
        $force  = $this->option('force');
        $type   = $this->option('type');
        $dryRun = $this->option('dry-run');

        $this->info('=== Backlink Engine Re-Sync ===');

        // ── Influenceurs ──────────────────────────────────────────────
        $query = Influenceur::query()->whereNotNull('email');

        if (! $force) {
            $query->whereNull('backlink_synced_at');
        }
        if ($type) {
            $query->where('contact_type', $type);
        }

        $influenceurs = $query->get();
        $this->info("Influenceurs à synchro: {$influenceurs->count()}");

        $sent   = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($influenceurs as $contact) {
            $contactType = $contact->contact_type instanceof \App\Enums\ContactType
                ? $contact->contact_type->value
                : (string) $contact->contact_type;

            if (! BacklinkEngineWebhookService::isSyncable($contactType)) {
                $skipped++;
                continue;
            }

            // Skip junk email domains
            $emailDomain = strtolower(explode('@', $contact->email)[1] ?? '');
            if (in_array($emailDomain, self::JUNK_EMAIL_DOMAINS)) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $sent++;
                continue;
            }

            $synced = BacklinkEngineWebhookService::sendContactCreated([
                'email'        => $contact->email,
                'name'         => $contact->name,
                'firstName'    => $contact->first_name,
                'lastName'     => $contact->last_name,
                'type'         => $contactType,
                'publication'  => $contact->company,
                'country'      => $contact->country,
                'language'     => $contact->language,
                'source_url'   => $contact->website_url ?? $contact->profile_url,
                'source_table' => 'influenceurs',
                'source_id'    => $contact->id,
            ]);

            if ($synced) {
                $contact->updateQuietly(['backlink_synced_at' => now()]);
                $sent++;
            } else {
                $errors++;
            }

            // Small delay to avoid overwhelming the webhook
            usleep(100_000); // 100ms
        }

        $this->line("  Sent: {$sent} | Skipped: {$skipped} | Errors: {$errors}");

        // ── Press Contacts ────────────────────────────────────────────
        $pressQuery = PressContact::query()->whereNotNull('email');
        if (! $force) {
            $pressQuery->whereNull('backlink_synced_at');
        }

        $pressContacts = $pressQuery->get();
        $this->info("Press contacts à synchro: {$pressContacts->count()}");

        $pSent = 0;
        $pSkipped = 0;
        $pErrors = 0;

        foreach ($pressContacts as $pc) {
            $emailDomain = strtolower(explode('@', $pc->email)[1] ?? '');
            if (in_array($emailDomain, self::JUNK_EMAIL_DOMAINS)) {
                $pSkipped++;
                continue;
            }

            if ($dryRun) {
                $pSent++;
                continue;
            }

            $synced = BacklinkEngineWebhookService::sendContactCreated([
                'email'        => $pc->email,
                'name'         => $pc->full_name,
                'firstName'    => $pc->first_name,
                'lastName'     => $pc->last_name,
                'type'         => 'presse',
                'publication'  => $pc->publication,
                'country'      => $pc->country,
                'language'     => $pc->language,
                'source_url'   => $pc->source_url ?? $pc->profile_url,
                'source_table' => 'press_contacts',
                'source_id'    => $pc->id,
            ]);

            if ($synced) {
                $pc->updateQuietly(['backlink_synced_at' => now()]);
                $pSent++;
            } else {
                $pErrors++;
            }

            usleep(100_000);
        }

        $this->line("  Sent: {$pSent} | Skipped: {$pSkipped} | Errors: {$pErrors}");

        $this->newLine();
        $this->info("TOTAL: " . ($sent + $pSent) . " envoyés, " . ($skipped + $pSkipped) . " ignorés, " . ($errors + $pErrors) . " erreurs");

        return Command::SUCCESS;
    }
}
