<?php

namespace App\Console\Commands;

use App\Models\CountryDirectory;
use App\Services\WikidataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importe les ambassades/consulats depuis Wikidata pour une ou toutes les nationalités.
 *
 * Exemples d'utilisation :
 *   php artisan annuaire:import-wikidata --nationality=DE
 *   php artisan annuaire:import-wikidata --nationality=all
 *   php artisan annuaire:import-wikidata --nationality=all --skip-existing
 *   php artisan annuaire:import-wikidata --nationality=FR,DE,ES --dry-run
 */
class ImportWikidataEmbassies extends Command
{
    protected $signature = 'annuaire:import-wikidata
        {--nationality=  : Code(s) ISO alpha-2 (ex: DE | FR,DE,ES | all)}
        {--dry-run       : Simule sans insérer en base}
        {--skip-existing : Ignore les nationalités déjà importées}
        {--delay=1       : Délai en secondes entre requêtes Wikidata (défaut 1)}';

    protected $description = 'Importe les ambassades depuis Wikidata (une nationalité ou toutes)';

    public function __construct(private WikidataService $wikidata)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $natOpt  = $this->option('nationality');
        $dryRun  = (bool) $this->option('dry-run');
        $skipEx  = (bool) $this->option('skip-existing');
        $delay   = (int)  $this->option('delay');

        if (!$natOpt) {
            $this->error('Spécifiez --nationality=XX ou --nationality=all');
            $this->line('  Exemple : php artisan annuaire:import-wikidata --nationality=DE');
            return self::FAILURE;
        }

        // Construire la liste des ISO à importer
        $toImport = match (strtolower($natOpt)) {
            'all'   => WikidataService::getSupportedIsoCodes(),
            default => array_map('strtoupper', array_filter(array_map('trim', explode(',', $natOpt)))),
        };

        $this->info(sprintf(
            'Import Wikidata : %d nationalité(s)%s%s',
            count($toImport),
            $dryRun   ? ' [DRY RUN]'      : '',
            $skipEx   ? ' [skip-existing]' : ''
        ));
        $this->newLine();

        $stats = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($toImport as $iso) {
            if (!isset(WikidataService::COUNTRY_QID[$iso])) {
                $this->warn("  [{$iso}] QID Wikidata inconnu — ignoré");
                continue;
            }

            if ($skipEx && $this->hasExistingEmbassies($iso)) {
                $this->line("  [{$iso}] Déjà importé — ignoré (--skip-existing)");
                continue;
            }

            try {
                $this->importNationality($iso, $dryRun, $stats);
            } catch (\Exception $e) {
                $this->error("  [{$iso}] Erreur : " . $e->getMessage());
                $stats['errors']++;
            }

            if ($delay > 0) {
                sleep($delay); // Respecter les rate limits Wikidata
            }
        }

        $this->newLine();
        $this->table(
            ['Insérés', 'Mis à jour', 'Ignorés (doublon)', 'Erreurs'],
            [[$stats['inserted'], $stats['updated'], $stats['skipped'], $stats['errors']]]
        );

        return self::SUCCESS;
    }

    // ── Privés ────────────────────────────────────────────────────────────────

    private function importNationality(string $iso, bool $dryRun, array &$stats): void
    {
        $name = WikidataService::COUNTRY_NAMES_FR[$iso] ?? $iso;
        $this->line("  [{$iso}] Interrogation Wikidata pour {$name}...");

        $bindings  = $this->wikidata->getEmbassiesByNationality($iso);
        $embassies = $this->wikidata->normalizeEmbassies($bindings, $iso);

        $this->line("        → {$name} : " . count($embassies) . " ambassades trouvées");

        if (empty($embassies)) return;

        if ($dryRun) {
            foreach ($embassies as $e) {
                $this->line("        [DRY] {$e['country_name']} — {$e['title']} ({$e['url']})");
            }
            return;
        }

        foreach ($embassies as $data) {
            $now = now();
            $data['updated_at'] = $now;

            $existing = CountryDirectory::where('country_code', $data['country_code'])
                ->where('nationality_code', $data['nationality_code'])
                ->where('url', $data['url'])
                ->first();

            if ($existing) {
                // Mettre à jour uniquement les champs non-nuls de Wikidata
                $toUpdate = array_filter($data, fn($v) => $v !== null);
                unset($toUpdate['created_at']);
                $existing->update($toUpdate);
                $stats['updated']++;
            } else {
                $data['created_at'] = $now;
                CountryDirectory::create($data);
                $stats['inserted']++;
            }
        }
    }

    private function hasExistingEmbassies(string $iso): bool
    {
        return CountryDirectory::where('nationality_code', $iso)
            ->where('category', 'ambassade')
            ->exists();
    }
}
