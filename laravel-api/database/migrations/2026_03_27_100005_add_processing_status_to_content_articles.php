<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_articles', function (Blueprint $table) {
            $table->string('processing_status', 20)->default('unprocessed')->after('scraped_at');
            $table->timestamp('processed_at')->nullable()->after('processing_status');
            $table->integer('quality_rating')->nullable()->after('processed_at');

            $table->index('processing_status');
        });
    }

    public function down(): void
    {
        Schema::table('content_articles', function (Blueprint $table) {
            $table->dropIndex(['processing_status']);
            $table->dropColumn(['processing_status', 'processed_at', 'quality_rating']);
        });
    }
};
