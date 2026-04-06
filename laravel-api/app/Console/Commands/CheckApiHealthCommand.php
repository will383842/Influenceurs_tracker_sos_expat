<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Daily API health check — tests all AI API keys and sends Telegram alert.
 *
 * Scheduled daily at 08:00 UTC via console.php.
 * Sends Telegram alert if any account is empty or has errors.
 * Also sends a daily report with all statuses.
 *
 * Usage:
 *   php artisan api:health-check              # Check and send Telegram
 *   php artisan api:health-check --no-telegram # Check only (no alert)
 */
class CheckApiHealthCommand extends Command
{
    protected $signature = 'api:health-check {--no-telegram : Skip Telegram notification}';
    protected $description = 'Check all AI API keys health and send Telegram alerts';

    private const BILLING_URLS = [
        'anthropic'  => 'https://console.anthropic.com/settings/billing',
        'openai'     => 'https://platform.openai.com/settings/organization/billing/overview',
        'perplexity' => 'https://www.perplexity.ai/settings/api',
        'tavily'     => 'https://app.tavily.com/home',
    ];

    public function handle(): int
    {
        $this->info('=== API HEALTH CHECK ===');

        $results = [
            'anthropic'  => $this->checkAnthropic(),
            'openai'     => $this->checkOpenAi(),
            'perplexity' => $this->checkPerplexity(),
            'tavily'     => $this->checkTavily(),
        ];

        // Display results
        foreach ($results as $name => $r) {
            $icon = $r['status'] === 'active' ? '✅' : ($r['status'] === 'empty' ? '❌' : '⚠️');
            $this->line("  {$icon} {$name}: {$r['status']} — {$r['message']}");
        }

        // Send Telegram if not disabled
        if (!$this->option('no-telegram')) {
            $this->sendTelegramReport($results);
        }

        $hasEmpty = collect($results)->contains(fn($r) => $r['status'] === 'empty');
        return $hasEmpty ? 1 : 0;
    }

    public function checkAll(): array
    {
        return [
            'anthropic'  => $this->checkAnthropic(),
            'openai'     => $this->checkOpenAi(),
            'perplexity' => $this->checkPerplexity(),
            'tavily'     => $this->checkTavily(),
        ];
    }

    private function checkAnthropic(): array
    {
        $key = config('services.anthropic.api_key') ?: config('services.claude.api_key');
        if (!$key) return ['status' => 'error', 'message' => 'Cle API non configuree'];

        try {
            $res = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(10)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 5,
                'messages' => [['role' => 'user', 'content' => 'ok']],
            ]);

            if ($res->successful()) {
                return ['status' => 'active', 'message' => 'Compte actif'];
            }

            $error = $res->json('error.message', 'Erreur inconnue');
            if (str_contains(strtolower($error), 'credit balance')) {
                return ['status' => 'empty', 'message' => 'Solde a zero — recharger immediatement'];
            }

            return ['status' => 'error', 'message' => $error];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkOpenAi(): array
    {
        $key = config('services.openai.api_key');
        if (!$key) return ['status' => 'error', 'message' => 'Cle API non configuree'];

        try {
            $res = Http::withToken($key)->timeout(10)->get('https://api.openai.com/v1/models');

            if ($res->successful()) {
                return ['status' => 'active', 'message' => 'Compte actif'];
            }

            $error = $res->json('error.message', 'Erreur inconnue');
            if (str_contains(strtolower($error), 'quota') || str_contains(strtolower($error), 'billing')) {
                return ['status' => 'empty', 'message' => 'Quota depasse ou facturation — verifier le compte'];
            }

            return ['status' => 'error', 'message' => $error];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkPerplexity(): array
    {
        $key = config('services.perplexity.api_key');
        if (!$key) return ['status' => 'error', 'message' => 'Cle API non configuree'];

        try {
            $res = Http::withToken($key)->timeout(10)
                ->post('https://api.perplexity.ai/chat/completions', [
                    'model' => 'sonar',
                    'messages' => [['role' => 'user', 'content' => 'ok']],
                    'max_tokens' => 5,
                ]);

            if ($res->successful()) {
                return ['status' => 'active', 'message' => 'Compte actif'];
            }

            $error = $res->json('error.message', $res->body());
            if (str_contains(strtolower($error), 'quota') || str_contains(strtolower($error), 'exceeded')) {
                return ['status' => 'empty', 'message' => 'Quota depasse — recharger'];
            }

            return ['status' => 'error', 'message' => mb_substr($error, 0, 200)];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkTavily(): array
    {
        $key = config('services.tavily.api_key');
        if (!$key) return ['status' => 'error', 'message' => 'Cle API non configuree (cote Blog, pas MC)'];

        try {
            $res = Http::timeout(10)->post('https://api.tavily.com/search', [
                'api_key' => $key,
                'query' => 'test',
                'max_results' => 1,
            ]);

            if ($res->successful() && $res->json('results')) {
                return ['status' => 'active', 'message' => 'Compte actif'];
            }

            return ['status' => 'error', 'message' => mb_substr($res->body(), 0, 200)];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function sendTelegramReport(array $results): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.admin_chat_id', '7560535072');

        if (!$botToken) {
            $this->warn('TELEGRAM_BOT_TOKEN non configure — alerte non envoyee');
            return;
        }

        $hasEmpty = collect($results)->contains(fn($r) => $r['status'] === 'empty');

        $lines = [];
        $lines[] = $hasEmpty ? '🚨 *ALERTE API — COMPTE(S) A ZERO*' : '📊 *Rapport API journalier*';
        $lines[] = '';

        foreach ($results as $name => $r) {
            $icon = $r['status'] === 'active' ? '✅' : ($r['status'] === 'empty' ? '❌' : '⚠️');
            $lines[] = "{$icon} *{$name}* : {$r['message']}";
            if ($r['status'] !== 'active') {
                $url = self::BILLING_URLS[$name] ?? '#';
                $lines[] = "   → [Recharger]({$url})";
            }
        }

        $lines[] = '';
        $lines[] = '_Verification auto — ' . now()->format('d/m/Y H:i') . ' UTC_';

        $text = implode("\n", $lines);

        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);
            $this->info('Alerte Telegram envoyee.');
        } catch (\Throwable $e) {
            $this->error("Erreur Telegram: {$e->getMessage()}");
        }
    }
}
