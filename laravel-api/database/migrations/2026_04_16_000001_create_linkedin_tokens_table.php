<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores encrypted LinkedIn OAuth tokens (personal + page).
 * PostgreSQL-compatible (no MySQL ENGINE, no UNSIGNED, no ENUM).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('linkedin_tokens')) return;

        Schema::create('linkedin_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('account_type', 20)->unique(); // 'personal' | 'page'
            $table->text('access_token');                 // Encrypted OAuth access token
            $table->text('refresh_token')->nullable();    // Encrypted refresh token (365d TTL)
            $table->timestamp('expires_at');
            $table->string('linkedin_id', 100);           // person URN or org numeric ID
            $table->string('linkedin_name', 255)->nullable();
            $table->text('scope')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linkedin_tokens');
    }
};
