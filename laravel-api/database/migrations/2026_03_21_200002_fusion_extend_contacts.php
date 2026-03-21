<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FUSION: Extend contacts (interactions) table with email tracking + more channels.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Convert channel and result from enum to varchar for extensibility
        DB::statement("ALTER TABLE contacts ALTER COLUMN channel TYPE VARCHAR(50) USING channel::VARCHAR");
        DB::statement("ALTER TABLE contacts ALTER COLUMN result TYPE VARCHAR(50) USING result::VARCHAR");

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('direction', 10)->default('outbound')->after('channel');
            $table->string('subject', 500)->nullable()->after('result');
            $table->timestamp('email_opened_at')->nullable()->after('notes');
            $table->timestamp('email_clicked_at')->nullable()->after('email_opened_at');
            $table->string('template_used', 100)->nullable()->after('email_clicked_at');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['influenceur_id', 'date'], 'idx_contacts_inf_date');
            $table->index(['result'], 'idx_contacts_result');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('idx_contacts_inf_date');
            $table->dropIndex('idx_contacts_result');
            $table->dropColumn(['direction', 'subject', 'email_opened_at', 'email_clicked_at', 'template_used']);
        });
    }
};
