<?php

namespace App\Jobs;

use App\Models\ContentCampaignItem;
use App\Models\ContentGenerationCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessContentCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    public function __construct(
        public int $campaignId,
    ) {
        $this->onQueue('content');
    }

    public function handle(): void
    {
        $campaign = ContentGenerationCampaign::with('items')->findOrFail($this->campaignId);

        // Campaign might have been paused or cancelled since dispatch
        if ($campaign->status !== 'running') {
            Log::info('ProcessContentCampaignJob skipped — campaign not running', [
                'campaign_id' => $campaign->id,
                'status' => $campaign->status,
            ]);
            return;
        }

        // Get next pending item (by sort_order, scheduled_at <= now or null)
        $nextItem = $campaign->items()
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                  ->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('sort_order')
            ->first();

        // No more pending items — campaign is complete
        if (!$nextItem) {
            $pendingCount = $campaign->items()->where('status', 'pending')->count();
            $generatingCount = $campaign->items()->where('status', 'generating')->count();

            if ($pendingCount === 0 && $generatingCount === 0) {
                $campaign->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'completed_items' => $campaign->items()->where('status', 'completed')->count(),
                    'failed_items' => $campaign->items()->where('status', 'failed')->count(),
                ]);

                Log::info('Campaign completed', [
                    'campaign_id' => $campaign->id,
                    'name' => $campaign->name,
                    'completed' => $campaign->completed_items,
                    'failed' => $campaign->failed_items,
                ]);
            }

            return;
        }

        // Determine which generation job to dispatch based on campaign type or item config
        $config = array_merge(
            $campaign->config ?? [],
            $nextItem->config_override ?? [],
        );

        $contentType = $config['content_type'] ?? 'article';

        // Build generation params from campaign config + item config
        $params = [
            'topic' => $nextItem->title_hint ?? $config['topic'] ?? '',
            'language' => $config['language'] ?? $config['languages'][0] ?? 'fr',
            'country' => $config['country'] ?? null,
            'content_type' => $contentType,
            'keywords' => $config['keywords'] ?? [],
            'tone' => $config['tone'] ?? 'professional',
            'length' => $config['length'] ?? 'medium',
            'generate_faq' => $config['generate_faq'] ?? true,
            'faq_count' => $config['faq_count'] ?? 5,
            'research_sources' => $config['research_sources'] ?? false,
            'image_source' => $config['image_source'] ?? 'unsplash',
            'auto_internal_links' => $config['auto_internal_links'] ?? true,
            'auto_affiliate_links' => $config['auto_affiliate_links'] ?? true,
            'preset_id' => $config['preset_id'] ?? null,
            'created_by' => $campaign->created_by,
            'campaign_id' => $campaign->id,
            'campaign_item_id' => $nextItem->id,
        ];

        // Dispatch appropriate job
        match ($contentType) {
            'comparative' => GenerateComparativeJob::dispatch($params),
            default => GenerateArticleJob::dispatch($params),
        };

        // Mark item as generating
        $nextItem->update(['status' => 'generating']);

        Log::info('Campaign item dispatched', [
            'campaign_id' => $campaign->id,
            'item_id' => $nextItem->id,
            'content_type' => $contentType,
            'title_hint' => $nextItem->title_hint,
        ]);

        // Schedule next processing based on articles_per_day setting
        $articlesPerDay = $config['articles_per_day'] ?? 5;
        $delayMinutes = max(1, (int) round((24 * 60) / $articlesPerDay));

        self::dispatch($this->campaignId)->delay(now()->addMinutes($delayMinutes));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessContentCampaignJob failed', [
            'campaign_id' => $this->campaignId,
            'error' => $e->getMessage(),
        ]);
    }
}
