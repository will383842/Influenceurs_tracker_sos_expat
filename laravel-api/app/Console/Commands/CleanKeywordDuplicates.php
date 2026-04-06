<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Phase 2.1 — Clean generic keywords that duplicate country template topics.
 *
 * Reads both CSV files and outputs a cleaned version with duplicates flagged.
 * Also generates a report showing overlap analysis.
 *
 * Usage: php artisan keywords:clean-duplicates [--apply]
 */
class CleanKeywordDuplicates extends Command
{
    protected $signature = 'keywords:clean-duplicates
        {--apply : Actually write the cleaned CSV file (default: dry-run report only)}';

    protected $description = 'Analyze and clean generic keywords that duplicate country template topics';

    // Country template topic keywords (extracted from SOS_Expat_KW_PAYS.csv 37 templates)
    private const TEMPLATE_TOPICS = [
        'visa' => ['visa', 'permis de sejour', 'residence permanente', 'visa touriste', 'visa travail', 'visa retraite', 'visa digital nomad', 'visa etudiant', 'visa investisseur'],
        'cout_vie' => ['cout de la vie', 'prix des loyers', 'budget mensuel', 'combien coute'],
        'juridique' => ['avocat francophone', 'droit du travail', 'licenciement expatrie', 'droits des travailleurs'],
        'sante' => ['systeme de sante', 'assurance sante expatrie', 'urgence medicale', 'hopitaux'],
        'fiscalite' => ['impots expatrie', 'convention fiscale', 'double imposition', 'statut fiscal', 'resident ou non-resident'],
        'logement' => ['trouver un logement', 'acheter un bien immobilier', 'logement expatrie'],
        'finance' => ['ouvrir un compte bancaire', 'transferer de l\'argent'],
        'education' => ['ecoles internationales', 'etudier en', 'universites'],
        'securite' => ['securite en', 'zones a eviter', 'ambassade de france'],
        'transport' => ['permis de conduire', 'immatriculer une voiture'],
        'entrepreneuriat' => ['creer une entreprise', 'devenir freelance'],
        'retraite' => ['prendre sa retraite', 'retraites francais'],
        'demenagement' => ['demenager en', 'checklist'],
        'communaute' => ['communaute francaise', 'associations', 'groupes d\'expatries'],
        'urgences' => ['numeros d\'urgence', 'passeport perdu', 'vole', 'arrete par la police'],
    ];

    // Country names that indicate a keyword is country-specific (not generic)
    private const COUNTRY_INDICATORS = [
        'france', 'espagne', 'portugal', 'allemagne', 'italie', 'angleterre', 'royaume-uni',
        'canada', 'etats-unis', 'usa', 'japon', 'thailande', 'maroc', 'tunisie', 'senegal',
        'belgique', 'suisse', 'luxembourg', 'pays-bas', 'irlande', 'grece', 'turquie',
        'dubai', 'emirats', 'singapour', 'australie', 'nouvelle-zelande', 'mexique', 'bresil',
        'colombie', 'costa rica', 'bali', 'indonesie', 'malaisie', 'vietnam', 'cambodge',
        'chine', 'coree', 'inde', 'russie', 'pologne', 'roumanie', 'hongrie', 'republique tcheque',
    ];

    public function handle(): int
    {
        $genericPath = database_path('data/SOS_Expat_KW_GENERIC.csv');
        $outputPath = database_path('data/SOS_Expat_KW_GENERIC_CLEANED.csv');

        if (!file_exists($genericPath)) {
            $this->error("CSV not found: {$genericPath}");
            return 1;
        }

        $this->info('=== KEYWORD CLEANUP ANALYSIS ===');
        $this->newLine();

        // Parse CSV
        $rows = [];
        $handle = fopen($genericPath, 'r');
        $header = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4) continue;
            $rows[] = [
                'num' => $row[0],
                'cluster' => $row[1],
                'sub_cluster' => $row[2],
                'keyword' => $row[3],
                'secondary' => $row[4] ?? '',
                'count' => $row[5] ?? 1,
                'intent' => $row[6] ?? '',
                'tab' => $row[7] ?? '',
            ];
        }
        fclose($handle);

        $this->info("Total generic keywords: " . count($rows));

        // Analyze each keyword
        $keep = [];
        $remove = [];
        $flag = [];
        $clusterStats = [];

        foreach ($rows as $row) {
            $kw = $this->normalize($row['keyword']);
            $cluster = $row['cluster'];

            $clusterStats[$cluster] = ($clusterStats[$cluster] ?? 0) + 1;

            // Check if country-specific
            $isCountrySpecific = false;
            foreach (self::COUNTRY_INDICATORS as $country) {
                if (str_contains($kw, $country)) {
                    $isCountrySpecific = true;
                    break;
                }
            }

            // Check overlap with template topics
            $templateOverlap = null;
            foreach (self::TEMPLATE_TOPICS as $topic => $patterns) {
                foreach ($patterns as $pattern) {
                    if (str_contains($kw, $this->normalize($pattern))) {
                        $templateOverlap = $topic;
                        break 2;
                    }
                }
            }

            if ($isCountrySpecific && $templateOverlap) {
                // Country-specific AND overlaps with template = REMOVE
                $remove[] = array_merge($row, ['reason' => "Doublon template '{$templateOverlap}' + pays specifique"]);
            } elseif ($templateOverlap && !$isCountrySpecific) {
                // Generic but overlaps with template topic = FLAG for review
                $flag[] = array_merge($row, ['reason' => "Recouvre template '{$templateOverlap}' mais generique"]);
            } else {
                // Unique generic keyword = KEEP
                $keep[] = $row;
            }
        }

        // Display results
        $this->newLine();
        $this->info("=== RESULTS ===");
        $this->info("KEEP (generic unique): " . count($keep));
        $this->info("FLAG (needs review):   " . count($flag));
        $this->warn("REMOVE (duplicates):   " . count($remove));
        $this->newLine();

        // Cluster distribution
        $this->info("=== KEYWORDS BY CLUSTER ===");
        $table = [];
        foreach ($clusterStats as $cluster => $count) {
            $table[] = [$cluster, $count];
        }
        $this->table(['Cluster', 'Count'], $table);

        // Show removals
        if (count($remove) > 0) {
            $this->newLine();
            $this->warn("=== KEYWORDS TO REMOVE (" . count($remove) . ") ===");
            foreach (array_slice($remove, 0, 20) as $r) {
                $this->line("  - [{$r['cluster']}] {$r['keyword']} → {$r['reason']}");
            }
            if (count($remove) > 20) {
                $this->line("  ... and " . (count($remove) - 20) . " more");
            }
        }

        // Show flags
        if (count($flag) > 0) {
            $this->newLine();
            $this->info("=== KEYWORDS TO REVIEW (" . count($flag) . ") ===");
            foreach (array_slice($flag, 0, 10) as $f) {
                $this->line("  ? [{$f['cluster']}] {$f['keyword']} → {$f['reason']}");
            }
            if (count($flag) > 10) {
                $this->line("  ... and " . (count($flag) - 10) . " more");
            }
        }

        // Write cleaned CSV if --apply
        if ($this->option('apply')) {
            $this->newLine();
            $this->info("Writing cleaned CSV...");

            $out = fopen($outputPath, 'w');
            // BOM for Excel UTF-8
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge($header, ['Status', 'Reason']));

            foreach ($keep as $row) {
                fputcsv($out, [$row['num'], $row['cluster'], $row['sub_cluster'], $row['keyword'], $row['secondary'], $row['count'], $row['intent'], $row['tab'], 'KEEP', '']);
            }
            foreach ($flag as $row) {
                fputcsv($out, [$row['num'], $row['cluster'], $row['sub_cluster'], $row['keyword'], $row['secondary'], $row['count'], $row['intent'], $row['tab'], 'FLAG', $row['reason']]);
            }
            foreach ($remove as $row) {
                fputcsv($out, [$row['num'], $row['cluster'], $row['sub_cluster'], $row['keyword'], $row['secondary'], $row['count'], $row['intent'], $row['tab'], 'REMOVE', $row['reason']]);
            }

            fclose($out);
            $this->info("Cleaned CSV written to: {$outputPath}");
            $this->info("KEEP: " . count($keep) . " | FLAG: " . count($flag) . " | REMOVE: " . count($remove));
        } else {
            $this->newLine();
            $this->comment("Dry run complete. Use --apply to write the cleaned CSV file.");
        }

        return 0;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = str_replace(['à', 'â', 'ä'], 'a', $text);
        $text = str_replace(['é', 'è', 'ê', 'ë'], 'e', $text);
        $text = str_replace(['î', 'ï'], 'i', $text);
        $text = str_replace(['ô', 'ö'], 'o', $text);
        $text = str_replace(['ù', 'û', 'ü'], 'u', $text);
        $text = str_replace(['ç'], 'c', $text);

        return $text;
    }
}
