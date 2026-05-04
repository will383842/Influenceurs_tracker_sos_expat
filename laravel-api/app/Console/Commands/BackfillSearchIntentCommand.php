<?php

namespace App\Console\Commands;

use App\Models\GeneratedArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill search_intent on existing generated_articles using the same
 * regex heuristic as ArticleGenerationService::defaultIntent().
 *
 * Default mode is dry-run (no writes). Pass --apply to actually update rows.
 *
 * Examples:
 *   php artisan content:backfill-search-intent              # dry-run, all articles
 *   php artisan content:backfill-search-intent --apply      # write changes
 *   php artisan content:backfill-search-intent --type=qa    # restrict to one content_type
 *   php artisan content:backfill-search-intent --only-empty # skip rows that already have an intent
 */
class BackfillSearchIntentCommand extends Command
{
    protected $signature = 'content:backfill-search-intent
        {--apply : Write changes to the database (default is dry-run)}
        {--type= : Restrict to one content_type (qa, article, news, ...)}
        {--only-empty=1 : Skip rows that already have a search_intent (default 1)}
        {--chunk=500 : Chunk size for memory-safe iteration}';

    protected $description = 'Backfill search_intent on existing articles via title/keywords heuristic (dry-run by default)';

    public function handle(): int
    {
        $apply     = (bool) $this->option('apply');
        $type      = $this->option('type');
        $onlyEmpty = (bool) (int) $this->option('only-empty');
        $chunk     = max(50, (int) $this->option('chunk'));

        $query = GeneratedArticle::query()->whereNull('deleted_at');
        if ($type) {
            $query->where('content_type', $type);
        }
        if ($onlyEmpty) {
            $query->where(function ($q) {
                $q->whereNull('search_intent')->orWhere('search_intent', '');
            });
        }

        $total = (clone $query)->count();
        $this->info("Mode: " . ($apply ? 'APPLY (writes enabled)' : 'DRY-RUN (no writes)'));
        $this->info("Scope: {$total} articles" . ($type ? " (content_type={$type})" : '') . ($onlyEmpty ? ', only-empty' : ''));

        if ($total === 0) {
            $this->warn('Nothing to do.');
            return self::SUCCESS;
        }

        $stats = [
            'informational'            => 0,
            'commercial_investigation' => 0,
            'urgency'                  => 0,
            'transactional'            => 0,
            'local'                    => 0,
        ];
        $changed = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunkById($chunk, function ($articles) use (&$stats, &$changed, $apply, $bar) {
            foreach ($articles as $article) {
                $hint = trim(($article->title ?? '') . ' ' . ($article->keywords_primary ?? ''));
                $intent = self::classify($article->content_type ?? 'article', $hint);
                $stats[$intent] = ($stats[$intent] ?? 0) + 1;

                if (($article->search_intent ?? null) !== $intent) {
                    $changed++;
                    if ($apply) {
                        // Update without touching updated_at to avoid noise on existing rows
                        DB::table('generated_articles')
                            ->where('id', $article->id)
                            ->update(['search_intent' => $intent]);
                    }
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(['Intent', 'Count'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->info("Articles needing update: {$changed} / {$total}");
        if (!$apply) {
            $this->warn('Dry-run only. Re-run with --apply to persist.');
        }

        return self::SUCCESS;
    }

    /**
     * Mirror of ArticleGenerationService::defaultIntent() — kept in sync manually.
     * Heuristic on hint first, then content_type fallback.
     */
    public static function classify(string $contentType, ?string $hint = null): string
    {
        if ($hint !== null && $hint !== '') {
            $h = mb_strtolower($hint);

            if (preg_match('/(\bvs\b|\bversus\b|\bcomparaison\b|\bcomparatif\b|\bmeilleur(e|s|es)?\b|\bclassement\b|\btop\s+\d+\b|\balternative(s)?\b)/iu', $h)) {
                return 'commercial_investigation';
            }
            // \bsos\b excluded: brand "SOS-Expat" appears in many titles.
            if (preg_match('/(j\'ai\s+perdu|bloqu[eé]e?|refus[eé]e?|arnaque|escroquerie|urgent|harcel|victime|vol[eé]e?|que\s+faire|au\s+secours|emergency|danger|piratage|hack[eé]|attaque|agression)/iu', $h)) {
                return 'urgency';
            }
            if (preg_match('/(s\'inscrire|inscription|commander|r[eé]server|acheter|ach[eè]te|payer|s\'abonner|t[eé]l[eé]charger|download|buy\s+now|prix\b|tarifs?\b|devis|book(ing)?|order|sign\s*up)/iu', $h)) {
                return 'transactional';
            }
            if (preg_match('/(pr[eè]s\s+de\s+moi|near\s+me|[aà]\s+proximit[eé]|autour\s+de\s+moi|in\s+my\s+area)/iu', $h)) {
                return 'local';
            }
        }

        return match ($contentType) {
            'guide', 'guide_city', 'pillar'              => 'informational',
            'article', 'tutorial', 'statistics', 'news'    => 'informational',
            'qa', 'qa_needs'                              => 'informational',
            'comparative', 'affiliation'                  => 'commercial_investigation',
            'testimonial'                                 => 'informational',
            'outreach'                                    => 'transactional',
            'pain_point'                                  => 'urgency',
            default                                       => 'informational',
        };
    }
}
