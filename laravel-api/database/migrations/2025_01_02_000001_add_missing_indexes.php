<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->index('assigned_to');
            $table->index('created_by');
            $table->index('status');
            $table->index('primary_platform');
            $table->index('last_contact_at');
            $table->index(['status', 'reminder_active']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('date');
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->index('influenceur_id');
            $table->index('status');
            $table->index(['status', 'due_date']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('influenceur_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['status']);
            $table->dropIndex(['primary_platform']);
            $table->dropIndex(['last_contact_at']);
            $table->dropIndex(['status', 'reminder_active']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['date']);
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropIndex(['influenceur_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'due_date']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['influenceur_id']);
            $table->dropIndex(['action']);
            $table->dropIndex(['created_at']);
        });
    }
};
