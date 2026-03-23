<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeDirectoryJob;
use App\Models\Directory;
use App\Models\Influenceur;
use App\Services\BlockedDomainService;
use Illuminate\Console\Command;

/**
 * Scans the influenceurs table for contacts that are actually directories/aggregators
 * and moves them to the directories table.
 *
 * Usage:
 *   php artisan contacts:clean-directories           # Dry run (preview)
 *   php artisan contacts:clean-directories --apply    # Apply changes
 *   php artisan contacts:clean-directories --scrape   # Apply + launch scraping
 */
class CleanDirectoryContacts extends Command
{
    protected $signature = 'contacts:clean-directories
                            {--apply : Actually move contacts to directories (default: dry run)}
                            {--scrape : Also launch scraping for each new directory}
                            {--delete : Delete the original influenceur record after migration}';

    protected $description = 'Find influenceur records that are actually directories and move them to the directories table';

    public function handle(): int
    {
        $dryRun = !$this->option('apply');
        $scrape = $this->option('scrape');
        $delete = $this->option('delete');

        if ($dryRun) {
            $this->warn('=== DRY RUN (use --apply to execute) ===');
        }

        $influenceurs = Influenceur::all();
        $found = 0;
        $moved = 0;
        $alreadyExists = 0;

        $this->info("Scanning {$influenceurs->count()} contacts...");

        foreach ($influenceurs as $inf) {
            // Check both profile_url and website_url
            $urlsToCheck = array_filter([
                $inf->profile_url,
                $inf->website_url,
            ]);

            $matchedUrl = null;
            foreach ($urlsToCheck as $url) {
                if (BlockedDomainService::isScrapableDirectory($url)) {
                    $matchedUrl = $url;
                    break;
                }
            }

            if (!$matchedUrl) continue;

            $found++;
            $domain = Directory::extractDomain($matchedUrl);
            $contactType = $inf->contact_type instanceof \App\Enums\ContactType
                ? $inf->contact_type->value
                : (string) $inf->contact_type;

            $this->line("  [{$inf->id}] <fg=yellow>{$inf->name}</> → <fg=red>{$domain}</> (type: {$contactType}, country: {$inf->country})");

            if ($dryRun) continue;

            // Check if directory already exists
            $existing = Directory::where('domain', $domain)
                ->where('category', $contactType)
                ->where(function ($q) use ($inf) {
                    $q->where('country', $inf->country)->orWhereNull('country');
                })
                ->first();

            if ($existing) {
                $alreadyExists++;
                $this->line("    → <fg=blue>Annuaire existe déjà</> (ID {$existing->id})");
            } else {
                $dir = Directory::create([
                    'name'       => $inf->name ?: 'Annuaire ' . $domain,
                    'url'        => $matchedUrl,
                    'domain'     => $domain,
                    'category'   => $contactType,
                    'country'    => $inf->country,
                    'language'   => $inf->language,
                    'status'     => 'pending',
                    'notes'      => 'Migré depuis contacts (nettoyage automatique)',
                    'created_by' => $inf->created_by,
                ]);

                $this->line("    → <fg=green>Annuaire créé</> (ID {$dir->id})");
                $moved++;

                if ($scrape) {
                    ScrapeDirectoryJob::dispatch($dir->id);
                    $this->line("    → <fg=cyan>Scraping lancé</>");
                }
            }

            if ($delete) {
                $inf->forceDelete();
                $this->line("    → <fg=red>Contact supprimé</>");
            }
        }

        $this->newLine();
        $this->info("=== Résultat ===");
        $this->info("Contacts scannés : {$influenceurs->count()}");
        $this->info("Annuaires détectés : {$found}");
        if (!$dryRun) {
            $this->info("Annuaires créés : {$moved}");
            $this->info("Déjà existants : {$alreadyExists}");
            if ($delete) {
                $this->info("Contacts supprimés : " . ($moved + $alreadyExists));
            }
        }

        return 0;
    }
}
