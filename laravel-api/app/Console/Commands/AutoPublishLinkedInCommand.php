<?php

namespace App\Console\Commands;

use App\Models\LinkedInPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoPublishLinkedInCommand extends Command
{
    protected $signature   = 'linkedin:auto-publish';
    protected $description = 'Auto-publish scheduled LinkedIn posts that have reached their scheduled_at time';

    public function handle(): int
    {
        $posts = LinkedInPost::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($posts->isEmpty()) {
            return self::SUCCESS;
        }

        $published = 0;
        $failed    = 0;

        foreach ($posts as $post) {
            try {
                // TODO: LinkedIn API v2 — call $this->publishViaApi($post) once OAuth is configured.
                // For now, mark as published (manual/operator confirms the post was published).
                $post->update([
                    'status'       => 'published',
                    'published_at' => now(),
                ]);
                $published++;
                Log::info('linkedin:auto-publish: published', ['post_id' => $post->id, 'day' => $post->day_type]);

            } catch (\Throwable $e) {
                $post->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                $failed++;
                Log::error('linkedin:auto-publish: failed', [
                    'post_id' => $post->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $this->info("LinkedIn auto-publish: {$published} published, {$failed} failed.");
        return self::SUCCESS;
    }
}
