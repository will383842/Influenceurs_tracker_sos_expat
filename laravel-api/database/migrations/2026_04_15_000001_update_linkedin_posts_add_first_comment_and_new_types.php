<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add first_comment + featured_image_url columns to linkedin_posts.
 * source_type and status are VARCHAR in PostgreSQL — no ENUM modification needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('linkedin_posts', 'first_comment')) {
                $table->text('first_comment')->nullable();
            }
            if (!Schema::hasColumn('linkedin_posts', 'featured_image_url')) {
                $table->string('featured_image_url', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('linkedin_posts', function (Blueprint $table) {
            $cols = array_filter([
                Schema::hasColumn('linkedin_posts', 'featured_image_url') ? 'featured_image_url' : null,
                Schema::hasColumn('linkedin_posts', 'first_comment') ? 'first_comment' : null,
            ]);
            if ($cols) $table->dropColumn(array_values($cols));
        });
    }
};
