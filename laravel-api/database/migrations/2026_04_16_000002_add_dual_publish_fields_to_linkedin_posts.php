<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add dual-publish fields to linkedin_posts (account=both strategy).
 *   page_publish_after  → when to publish on the company page (personal + 4h30)
 *   page_published_at   → actual page publish timestamp
 *   publish_error_page  → error message if page publish failed
 *
 * PostgreSQL-compatible (no AFTER clause, no multi-column single statement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('linkedin_posts', 'page_publish_after')) {
                $table->timestamp('page_publish_after')->nullable();
            }
            if (!Schema::hasColumn('linkedin_posts', 'page_published_at')) {
                $table->timestamp('page_published_at')->nullable();
            }
            if (!Schema::hasColumn('linkedin_posts', 'publish_error_page')) {
                $table->text('publish_error_page')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('linkedin_posts', 'publish_error_page')  ? 'publish_error_page' : null,
                Schema::hasColumn('linkedin_posts', 'page_published_at')   ? 'page_published_at' : null,
                Schema::hasColumn('linkedin_posts', 'page_publish_after')  ? 'page_publish_after' : null,
            ]);
            if ($cols) $table->dropColumn(array_values($cols));
        });
    }
};
