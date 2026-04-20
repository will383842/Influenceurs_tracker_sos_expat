<?php

namespace App\Console\Commands;

use App\Services\Content\KnowledgeBaseService;
use Illuminate\Console\Command;

/**
 * kb:dump-prompts — render one prompt per content type and save to file(s).
 *
 * Used as a smoke audit: opens the rendered prompts to a human review folder
 * so you can scan for drift (prices, commissions, copy) before launching a
 * large content batch.
 *
 * Usage:
 *   php artisan kb:dump-prompts                           # writes to storage/kb-snapshots/YYYY-MM-DD/
 *   php artisan kb:dump-prompts --only=chatters,blogger
 *   php artisan kb:dump-prompts --light                   # uses getLightPrompt instead
 */
class DumpKnowledgeBasePromptsCommand extends Command
{
    protected $signature = 'kb:dump-prompts
        {--only= : Comma-separated list of content types (default: all)}
        {--light : Use getLightPrompt instead of getSystemPrompt}
        {--country=France : Country context}
        {--language=fr : Language context}';

    protected $description = 'Render a sample prompt per content type into storage/ for manual audit.';

    private const ALL_TYPES = [
        'qa', 'news', 'article', 'guide', 'guide_city', 'comparative', 'outreach',
        'chatters', 'influenceurs', 'admin_groupes', 'avocats', 'expats_aidants',
        'statistics', 'pain_point', 'testimonial', 'landing', 'press_release',
        'pillar', 'tutorial',
    ];

    public function handle(): int
    {
        $svc = new KnowledgeBaseService();
        $only = $this->option('only');
        $types = $only ? array_map('trim', explode(',', $only)) : self::ALL_TYPES;

        $country = $this->option('country');
        $language = $this->option('language');
        $useLight = (bool) $this->option('light');

        $date = date('Y-m-d_His');
        $dir = storage_path('kb-snapshots/' . $date);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->error("Cannot create {$dir}");
            return self::FAILURE;
        }

        $index = [];
        foreach ($types as $type) {
            $prompt = $useLight
                ? $svc->getLightPrompt($type, $country, $language)
                : $svc->getSystemPrompt($type, $country, $language);

            $file = $dir . '/' . $type . ($useLight ? '.light' : '') . '.md';
            file_put_contents($file, "# Prompt for content type: {$type}\n\n"
                . "- Country: {$country}\n"
                . "- Language: {$language}\n"
                . "- Mode: " . ($useLight ? 'light' : 'system') . "\n"
                . "- KB version: " . $svc->getVersion() . "\n"
                . "- Dumped at: " . date('c') . "\n"
                . "- Length: " . strlen($prompt) . " chars\n\n"
                . "---\n\n"
                . $prompt
            );
            $index[] = [
                'type' => $type,
                'file' => basename($file),
                'length' => strlen($prompt),
                'cites_sos_expat' => str_contains($prompt, 'SOS-Expat.com'),
                'cites_5_dollar_not_5_pct' => !str_contains($prompt, '5%'),
                'cites_197' => str_contains($prompt, '197'),
            ];
            $this->line("  wrote {$file} (" . strlen($prompt) . " chars)");
        }

        // Index file
        $indexFile = $dir . '/_index.json';
        file_put_contents($indexFile, json_encode([
            'kb_version' => $svc->getVersion(),
            'kb_updated_at' => $svc->getUpdatedAt(),
            'generated_at' => date('c'),
            'country' => $country,
            'language' => $language,
            'mode' => $useLight ? 'light' : 'system',
            'prompts' => $index,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("\nWrote {$indexFile}");
        $this->info(count($index) . ' prompts dumped to: ' . $dir);

        // Flag potential issues
        $issues = array_filter($index, fn($p) => !$p['cites_sos_expat'] || !$p['cites_197']);
        if ($issues) {
            $this->warn("\nWarnings:");
            foreach ($issues as $p) {
                if (!$p['cites_sos_expat']) {
                    $this->line("  {$p['type']}: does NOT cite 'SOS-Expat.com'");
                }
                if (!$p['cites_197']) {
                    $this->line("  {$p['type']}: does NOT cite '197' (countries)");
                }
            }
        } else {
            $this->info("All prompts cite SOS-Expat.com and '197'. OK.");
        }

        return self::SUCCESS;
    }
}
