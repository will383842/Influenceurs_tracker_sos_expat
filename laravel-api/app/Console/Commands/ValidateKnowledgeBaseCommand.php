<?php

namespace App\Console\Commands;

use App\Services\Content\KnowledgeBaseService;
use Illuminate\Console\Command;

/**
 * kb:validate — verifies structural invariants of the Knowledge Base.
 *
 * Run as part of CI and before every content-generation batch. Exits with code 1
 * on any failure so CI can gate deploys.
 *
 * Usage:
 *   php artisan kb:validate
 *   php artisan kb:validate --strict   (also fails on warnings)
 */
class ValidateKnowledgeBaseCommand extends Command
{
    protected $signature = 'kb:validate {--strict : Fail on warnings too}';

    protected $description = 'Validate Knowledge Base structural invariants (pricing, commissions, meta, content_rules)';

    private array $errors = [];
    private array $warnings = [];

    public function handle(): int
    {
        $kb = config('knowledge-base', []);
        if (empty($kb)) {
            $this->error('knowledge-base config is empty');
            return self::FAILURE;
        }

        $this->info('Validating Knowledge Base v' . ($kb['meta']['kb_version'] ?? '?') . '...');

        $this->validateMeta($kb);
        $this->validatePricing($kb);
        $this->validatePrograms($kb);
        $this->validateSubscriptions($kb);
        $this->validateCoverage($kb);
        $this->validateContentRules($kb);
        $this->validateService();

        $this->report();

        if (!empty($this->errors)) {
            return self::FAILURE;
        }
        if ($this->option('strict') && !empty($this->warnings)) {
            return self::FAILURE;
        }
        return self::SUCCESS;
    }

    // -----------------------------------------------------------------

    private function validateMeta(array $kb): void
    {
        $meta = $kb['meta'] ?? null;
        if (!$meta) {
            $this->errors[] = 'meta block missing';
            return;
        }
        if (!preg_match('/^\d+\.\d+\.\d+$/', $meta['kb_version'] ?? '')) {
            $this->errors[] = "meta.kb_version must follow semver (got: " . ($meta['kb_version'] ?? 'null') . ')';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $meta['kb_updated_at'] ?? '')) {
            $this->errors[] = 'meta.kb_updated_at must be ISO-8601 date';
        }
        // Warn if kb_updated_at is stale (> 30 days)
        if (isset($meta['kb_updated_at'])) {
            $age = (strtotime('now') - strtotime($meta['kb_updated_at'])) / 86400;
            if ($age > 30) {
                $this->warnings[] = sprintf('meta.kb_updated_at is %d days old — consider re-verifying', (int) $age);
            }
        }
    }

    private function validatePricing(array $kb): void
    {
        foreach (['lawyer', 'expat'] as $service) {
            $s = $kb['services'][$service] ?? null;
            if (!$s) {
                $this->errors[] = "services.{$service} missing";
                continue;
            }
            foreach (['eur', 'usd'] as $cur) {
                $price = $s["price_{$cur}"] ?? null;
                $payout = $s["provider_payout_{$cur}"] ?? null;
                $fee = $s["platform_fee_{$cur}"] ?? null;
                if ($price === null || $payout === null || $fee === null) {
                    $this->errors[] = "services.{$service} missing {$cur} fields";
                    continue;
                }
                if ($payout + $fee !== $price) {
                    $this->errors[] = sprintf(
                        'services.%s %s: %d (payout) + %d (fee) != %d (price)',
                        $service, strtoupper($cur), $payout, $fee, $price
                    );
                }
            }
        }
    }

    private function validatePrograms(array $kb): void
    {
        $programs = $kb['programs'] ?? [];
        if (!isset($programs['common'])) {
            $this->errors[] = 'programs.common missing';
            return;
        }
        $common = $programs['common'];
        if (($common['withdrawal_minimum'] ?? 0) <= 0) {
            $this->errors[] = 'programs.common.withdrawal_minimum must be > 0';
        }
        if (($common['withdrawal_fee'] ?? -1) < 0) {
            $this->errors[] = 'programs.common.withdrawal_fee must be >= 0';
        }

        // Ghost program guard
        if (isset($programs['general_affiliate'])) {
            $this->errors[] = 'programs.general_affiliate is a ghost — remove it';
        }

        foreach ($programs as $key => $program) {
            if (!is_array($program) || $key === 'common') {
                continue;
            }
            $this->validateProgramMilestones($key, $program);
            $this->validateProgramTop3($key, $program);
        }
    }

    private function validateProgramMilestones(string $key, array $program): void
    {
        if (!isset($program['milestones'])) {
            return;
        }
        $thresholds = array_keys($program['milestones']);
        $sorted = $thresholds;
        sort($sorted, SORT_NUMERIC);
        if ($sorted !== $thresholds) {
            $this->errors[] = "programs.{$key}.milestones must be sorted ascending by threshold";
        }
        $prev = 0;
        foreach ($program['milestones'] as $threshold => $bonus) {
            if ($bonus < $prev) {
                $this->errors[] = "programs.{$key}.milestones bonuses must be non-decreasing";
                break;
            }
            $prev = $bonus;
        }
    }

    private function validateProgramTop3(string $key, array $program): void
    {
        if (!isset($program['top3_monthly'])) {
            return;
        }
        $ranks = array_keys($program['top3_monthly']);
        if ($ranks !== [1, 2, 3]) {
            $this->errors[] = "programs.{$key}.top3_monthly must have ranks 1, 2, 3";
        }
        foreach ($program['top3_monthly'] as $rank => $entry) {
            if (isset($entry['multiplier'])) {
                $this->errors[] = "programs.{$key}.top3_monthly[{$rank}].multiplier is removed — use cash only";
            }
        }
        if (isset($program['top3_monthly_multipliers'])) {
            $this->errors[] = "programs.{$key}.top3_monthly_multipliers is removed — use top3_monthly cash only";
        }
    }

    private function validateSubscriptions(array $kb): void
    {
        $subs = $kb['subscriptions'] ?? [];
        $annual = $subs['annual_discount'] ?? null;
        if (!is_float($annual) && !is_int($annual)) {
            $this->errors[] = 'subscriptions.annual_discount must be numeric (0..1)';
        } elseif ($annual <= 0 || $annual >= 1) {
            $this->errors[] = 'subscriptions.annual_discount must be in (0, 1) exclusive';
        }
        if (!isset($subs['annual_discount_label'])) {
            $this->warnings[] = 'subscriptions.annual_discount_label missing — AI can\'t display % nicely';
        }
    }

    private function validateCoverage(array $kb): void
    {
        $cov = $kb['coverage'] ?? [];
        foreach (($cov['languages'] ?? []) as $code) {
            if (!isset($cov['language_names'][$code])) {
                $this->errors[] = "coverage.language_names missing entry for '{$code}'";
            }
        }
        if (($cov['language_code_canonical'] ?? null) !== 'zh') {
            $this->warnings[] = 'coverage.language_code_canonical should be "zh" (ISO 639-1)';
        }
    }

    private function validateContentRules(array $kb): void
    {
        $required = ['qr', 'news', 'pillar', 'tutorial', 'statistics', 'pain_point', 'testimonial', 'landing'];
        foreach ($required as $key) {
            if (empty($kb['content_rules'][$key] ?? '')) {
                $this->errors[] = "content_rules.{$key} missing or empty";
            }
        }
    }

    private function validateService(): void
    {
        try {
            $svc = new KnowledgeBaseService();
            if ($svc->getVersion() === 'unknown') {
                $this->errors[] = 'KnowledgeBaseService::getVersion() returns unknown';
            }
            $prompt = $svc->getSystemPrompt('article', 'France', 'fr');
            if (!str_contains($prompt, 'SOS-Expat')) {
                $this->errors[] = 'getSystemPrompt did not include brand name';
            }
        } catch (\Throwable $e) {
            $this->errors[] = 'KnowledgeBaseService instantiation failed: ' . $e->getMessage();
        }
    }

    private function report(): void
    {
        $this->newLine();
        if (!empty($this->warnings)) {
            $this->warn('Warnings (' . count($this->warnings) . '):');
            foreach ($this->warnings as $w) {
                $this->line('  - ' . $w);
            }
        }
        if (!empty($this->errors)) {
            $this->error('Errors (' . count($this->errors) . '):');
            foreach ($this->errors as $e) {
                $this->line('  - ' . $e);
            }
        } else {
            $this->info('OK — no errors.');
        }
    }
}
