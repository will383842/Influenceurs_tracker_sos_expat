<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FUSION: Add manual journal support to activity_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('details');
            $table->text('manual_note')->nullable()->after('is_manual');
            $table->string('contact_type', 50)->nullable()->after('manual_note');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['user_id', 'is_manual', 'created_at'], 'idx_activity_journal');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_activity_journal');
            $table->dropColumn(['is_manual', 'manual_note', 'contact_type']);
        });
    }
};
