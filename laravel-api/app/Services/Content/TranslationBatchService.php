<?php

namespace App\Services\Content;

use App\Jobs\ProcessTranslationBatchJob;
use App\Models\GeneratedArticle;
use App\Models\QaEntry;
use App\Models\TranslationBatch;
use Illuminate\Support\Facades\Log;

/**
 * Translation batch management — creates, pauses, resumes, and cancels
 * batch translation jobs for articles and Q&A entries.
 */
class TranslationBatchService
{
    /**
     * Start a new translation batch for a target language.
     */
    public function startBatch(string $targetLanguage, string $contentType = 'article', ?int $createdBy = null): TranslationBatch
    {
        try {
            $totalItems = 0;

            if ($contentType === 'article' || $contentType === 'all') {
                $totalItems += $this->countUntranslatedArticles($targetLanguage);
            }

            if ($contentType === 'qa' || $contentType === 'all') {
                $totalItems += $this->countUntranslatedQa($targetLanguage);
            }

            $batch = TranslationBatch::create([
                'target_language' => $targetLanguage,
                'content_type' => $contentType,
                'status' => 'running',
                'total_items' => $totalItems,
                'completed_items' => 0,
                'failed_items' => 0,
                'skipped_items' => 0,
                'total_cost_cents' => 0,
                'started_at' => now(),
                'created_by' => $createdBy,
            ]);

            Log::info('TranslationBatch: started', [
                'batch_id' => $batch->id,
                'target_language' => $targetLanguage,
                'content_type' => $contentType,
                'total_items' => $totalItems,
            ]);

            // Dispatch first processing job
            if ($totalItems > 0) {
                ProcessTranslationBatchJob::dispatch($batch->id);
            } else {
                $batch->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            return $batch;
        } catch (\Throwable $e) {
            Log::error('TranslationBatch: start failed', [
                'target_language' => $targetLanguage,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Pause a running batch.
     */
    public function pauseBatch(TranslationBatch $batch): void
    {
        try {
            if ($batch->status !== 'running') {
                return;
            }

            $batch->update([
                'status' => 'paused',
                'paused_at' => now(),
            ]);

            Log::info('TranslationBatch: paused', ['batch_id' => $batch->id]);
        } catch (\Throwable $e) {
            Log::error('TranslationBatch: pause failed', [
                'batch_id' => $batch->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Resume a paused batch.
     */
    public function resumeBatch(TranslationBatch $batch): void
    {
        try {
            if ($batch->status !== 'paused') {
                return;
            }

            $batch->update([
                'status' => 'running',
                'paused_at' => null,
            ]);

            ProcessTranslationBatchJob::dispatch($batch->id);

            Log::info('TranslationBatch: resumed', ['batch_id' => $batch->id]);
        } catch (\Throwable $e) {
            Log::error('TranslationBatch: resume failed', [
                'batch_id' => $batch->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Cancel a batch.
     */
    public function cancelBatch(TranslationBatch $batch): void
    {
        try {
            if (in_array($batch->status, ['completed', 'cancelled'], true)) {
                return;
            }

            $batch->update(['status' => 'cancelled']);

            Log::info('TranslationBatch: cancelled', ['batch_id' => $batch->id]);
        } catch (\Throwable $e) {
            Log::error('TranslationBatch: cancel failed', [
                'batch_id' => $batch->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get progress details for a batch.
     *
     * @return array{total: int, completed: int, failed: int, skipped: int, percent: float, status: string, estimated_remaining_minutes: int}
     */
    public function getProgress(TranslationBatch $batch): array
    {
        $total = $batch->total_items;
        $completed = $batch->completed_items;
        $failed = $batch->failed_items;
        $skipped = $batch->skipped_items;
        $processed = $completed + $failed + $skipped;

        $percent = ($total > 0) ? round(($processed / $total) * 100, 1) : 0;

        // Estimate remaining time based on elapsed time
        $estimatedMinutes = 0;
        if ($batch->started_at && $processed > 0) {
            $elapsedSeconds = now()->diffInSeconds($batch->started_at);
            $secondsPerItem = $elapsedSeconds / $processed;
            $remaining = $total - $processed;
            $estimatedMinutes = (int) ceil(($remaining * $secondsPerItem) / 60);
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'skipped' => $skipped,
            'percent' => $percent,
            'status' => $batch->status,
            'estimated_remaining_minutes' => $estimatedMinutes,
        ];
    }

    /**
     * Count untranslated articles for a target language.
     */
    private function countUntranslatedArticles(string $targetLanguage): int
    {
        return GeneratedArticle::where('language', 'fr')
            ->whereNull('parent_article_id') // Only originals
            ->whereIn('status', ['review', 'published'])
            ->whereDoesntHave('translations', function ($q) use ($targetLanguage) {
                $q->where('language', $targetLanguage);
            })
            ->count();
    }

    /**
     * Count untranslated Q&A entries for a target language.
     */
    private function countUntranslatedQa(string $targetLanguage): int
    {
        return QaEntry::where('language', 'fr')
            ->whereNull('parent_qa_id') // Only originals
            ->whereIn('status', ['draft', 'review', 'published'])
            ->whereDoesntHave('translations', function ($q) use ($targetLanguage) {
                $q->where('language', $targetLanguage);
            })
            ->count();
    }
}
