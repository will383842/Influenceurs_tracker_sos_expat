<?php

namespace App\Console\Commands;

use App\Jobs\GenerateArticleJob;
use Illuminate\Console\Command;

/**
 * One-shot command to queue 10 Thailand legal articles with HIGH priority.
 * Uses the same GenerateArticleJob as the dashboard — identical quality.
 */
class GenerateThailandBatchCommand extends Command
{
    protected $signature = 'content:generate-thailand-batch
        {--dry-run : Show what would be queued without actually queuing}';

    protected $description = 'Queue 10 Thailand legal articles with high priority (before other pending content)';

    public function handle(): int
    {
        $articles = [
            [
                'topic' => 'Visa Thaïlande : que faire si votre demande est refusée ?',
                'keywords' => ['visa thaïlande refusé', 'recours refus visa thaïlande', 'appel visa thailande'],
                'search_intent' => 'informational',
            ],
            [
                'topic' => 'Accident de moto en Thaïlande : vos droits et recours',
                'keywords' => ['accident moto thaïlande', 'droits accident thaïlande', 'assurance moto thailande'],
                'search_intent' => 'urgency',
            ],
            [
                'topic' => 'Arnaque immobilière en Thaïlande : comment se défendre ?',
                'keywords' => ['arnaque immobilier thaïlande', 'recours arnaque thailande', 'achat immobilier thailande'],
                'search_intent' => 'informational',
            ],
            [
                'topic' => 'Divorce en Thaïlande pour un expatrié : procédure et coûts',
                'keywords' => ['divorce thaïlande expatrié', 'procédure divorce thailande', 'coût divorce thailande'],
                'search_intent' => 'informational',
            ],
            [
                'topic' => 'Expulsé de Thaïlande : que faire en urgence ?',
                'keywords' => ['expulsion thaïlande', 'que faire expulsé thailande', 'overstay thailande'],
                'search_intent' => 'urgency',
            ],
            [
                'topic' => 'Créer une entreprise en Thaïlande : les pièges juridiques à éviter',
                'keywords' => ['créer entreprise thaïlande', 'pièges juridiques thailande', 'BOI thailande'],
                'search_intent' => 'informational',
            ],
            [
                'topic' => 'Problème avec votre employeur en Thaïlande : quels recours ?',
                'keywords' => ['litige employeur thaïlande', 'droit travail thailande', 'recours salarié thailande'],
                'search_intent' => 'informational',
            ],
            [
                'topic' => 'Garde d\'enfant en Thaïlande après séparation : ce que dit la loi',
                'keywords' => ['garde enfant thaïlande', 'droit famille thailande', 'custody thailand expat'],
                'search_intent' => 'informational',
            ],
            [
                'topic' => 'Arrestation en Thaïlande : premiers réflexes et droits',
                'keywords' => ['arrestation thaïlande', 'droits arrestation thailande', 'avocat urgence thailande'],
                'search_intent' => 'urgency',
            ],
            [
                'topic' => 'Héritage en Thaïlande : comment protéger vos biens en tant qu\'étranger ?',
                'keywords' => ['héritage thaïlande étranger', 'succession thailande', 'testament thailande expatrié'],
                'search_intent' => 'informational',
            ],
        ];

        $isDryRun = $this->option('dry-run');

        $this->info($isDryRun ? '=== DRY RUN ===' : '=== QUEUING 10 THAILAND ARTICLES ===');
        $this->newLine();

        foreach ($articles as $i => $config) {
            $num = $i + 1;
            $intent = $config['search_intent'];
            $this->line("{$num}/10 [{$intent}] {$config['topic']}");

            if (!$isDryRun) {
                // Dispatch with HIGH priority (queue position)
                // onQueue('content') is the dedicated content queue
                // delay(i * 30) staggers generation to avoid rate limits
                GenerateArticleJob::dispatch([
                    'topic'          => $config['topic'],
                    'content_type'   => 'article',
                    'language'       => 'fr',
                    'country'        => 'TH',
                    'keywords'       => $config['keywords'],
                    'search_intent'  => $config['search_intent'],
                    'force_generate' => true,  // bypass rate limiter for this batch
                    'image_source'   => 'unsplash',
                ])->delay(now()->addSeconds($i * 30));  // stagger: 0s, 30s, 60s... avoids API bursts
            }
        }

        $this->newLine();

        if ($isDryRun) {
            $this->warn('Dry run — nothing queued. Run without --dry-run to queue.');
        } else {
            $this->info('10 articles queued on [content] queue with HIGH priority.');
            $this->info('Monitor: php artisan queue:listen content --timeout=600');
        }

        return 0;
    }
}
