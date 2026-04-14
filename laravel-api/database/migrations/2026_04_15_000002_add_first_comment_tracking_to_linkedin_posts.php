<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add first_comment tracking + auto_scheduled + reply_variants to linkedin_posts.
 * PostgreSQL-compatible: no ENUM, no AFTER, no TINYINT(1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('linkedin_posts', 'first_comment_posted_at')) {
                $table->timestamp('first_comment_posted_at')->nullable();
            }
            if (!Schema::hasColumn('linkedin_posts', 'first_comment_status')) {
                $table->string('first_comment_status', 20)->nullable(); // pending|posted|failed
            }
            if (!Schema::hasColumn('linkedin_posts', 'reply_variants')) {
                $table->json('reply_variants')->nullable();
            }
            if (!Schema::hasColumn('linkedin_posts', 'auto_scheduled')) {
                $table->boolean('auto_scheduled')->default(false);
            }
        });

        // Index for the auto-publish cron (status + scheduled_at frequently queried together)
        if (!$this->indexExists('linkedin_posts', 'idx_linkedin_auto_publish')) {
            \Illuminate\Support\Facades\DB::statement(
                'CREATE INDEX idx_linkedin_auto_publish ON linkedin_posts (status, scheduled_at)'
            );
        }
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS idx_linkedin_auto_publish');

        Schema::table('linkedin_posts', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('linkedin_posts', 'auto_scheduled')           ? 'auto_scheduled' : null,
                Schema::hasColumn('linkedin_posts', 'reply_variants')           ? 'reply_variants' : null,
                Schema::hasColumn('linkedin_posts', 'first_comment_status')     ? 'first_comment_status' : null,
                Schema::hasColumn('linkedin_posts', 'first_comment_posted_at')  ? 'first_comment_posted_at' : null,
            ]);
            if ($cols) $table->dropColumn(array_values($cols));
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $count = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT COUNT(*) as cnt FROM pg_indexes WHERE tablename = ? AND indexname = ?",
            [$table, $index]
        );
        return ($count->cnt ?? 0) > 0;
    }
};
