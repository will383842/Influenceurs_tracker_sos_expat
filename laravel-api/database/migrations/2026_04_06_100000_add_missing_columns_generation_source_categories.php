<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two tables were created via direct SQL and are missing columns.
 *
 * generation_source_categories:
 *   - config_json   (required by commandCenter / pause / trigger / visibility / quota / weight)
 *   - updated_at    (written on every config change)
 *
 * generation_source_items:
 *   - input_quality (selected by GenerationSourceController::categoryItems())
 *
 * All additions are guarded with hasColumn() so this migration is safe to
 * run on databases that already have some / all of the columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── generation_source_categories ─────────────────────────────────────
        if (Schema::hasTable('generation_source_categories')) {
            Schema::table('generation_source_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('generation_source_categories', 'config_json')) {
                    $table->jsonb('config_json')->nullable()->after('sort_order');
                }
                if (!Schema::hasColumn('generation_source_categories', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                }
            });
        }

        // ── generation_source_items ──────────────────────────────────────────
        if (Schema::hasTable('generation_source_items')) {
            Schema::table('generation_source_items', function (Blueprint $table) {
                if (!Schema::hasColumn('generation_source_items', 'input_quality')) {
                    // Possible values: 'title_only', 'summary', 'full_text'
                    $table->string('input_quality', 30)->default('title_only')->after('used_count');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('generation_source_categories')) {
            Schema::table('generation_source_categories', function (Blueprint $table) {
                if (Schema::hasColumn('generation_source_categories', 'config_json')) {
                    $table->dropColumn('config_json');
                }
                if (Schema::hasColumn('generation_source_categories', 'updated_at')) {
                    $table->dropColumn('updated_at');
                }
            });
        }

        if (Schema::hasTable('generation_source_items')) {
            Schema::table('generation_source_items', function (Blueprint $table) {
                if (Schema::hasColumn('generation_source_items', 'input_quality')) {
                    $table->dropColumn('input_quality');
                }
            });
        }
    }
};
