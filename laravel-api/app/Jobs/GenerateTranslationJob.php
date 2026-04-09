<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use App\Services\Content\TranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateTranslationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;

    public function __construct(
        public int $articleId,
        public string $targetLanguage,
    ) {
        $this->onQueue('content');
    }

    public function handle(TranslationService $service): void
    {
        $article = GeneratedArticle::findOrFail($this->articleId);

        Log::info('GenerateTranslationJob started', [
            'article_id' => $article->id,
            'source_language' => $article->language,
            'target_language' => $this->targetLanguage,
            'title' => $article->title,
        ]);

        $translation = $service->translateArticle($article, $this->targetLanguage);

        Log::info('GenerateTranslationJob completed', [
            'article_id' => $article->id,
            'translation_id' => $translation->id,
            'target_language' => $this->targetLanguage,
            'title' => $translation->title,
            'cost_cents' => $translation->generation_cost_cents,
        ]);

        // Dispatch SEO analysis for the translation
        AnalyzeSeoJob::dispatch(GeneratedArticle::class, $translation->id);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateTranslationJob failed', [
            'article_id' => $this->articleId,
            'target_language' => $this->targetLanguage,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $botToken = config('services.telegram_alerts.bot_token');
        $chatId = config('services.telegram_alerts.chat_id');
        if ($botToken && $chatId) {
            try {
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'parse_mode' => 'Markdown',
                    'text' => "🚨 *Job Failed*: `GenerateTranslationJob`\n" .
                              "Article: {$this->articleId}\n" .
                              "Lang: {$this->targetLanguage}\n" .
                              "Error: " . mb_substr($e->getMessage(), 0, 500) . "\n" .
                              "Time: " . now()->toDateTimeString(),
                ]);
            } catch (\Throwable $tgError) {
                Log::warning('Failed to send Telegram alert', ['error' => $tgError->getMessage()]);
            }
        }
    }
}
