<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('linkedin_tokens', 'refresh_token_expires_at')) return;

        Schema::table('linkedin_tokens', function (Blueprint $table) {
            $table->timestamp('refresh_token_expires_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('linkedin_tokens', function (Blueprint $table) {
            $table->dropColumn('refresh_token_expires_at');
        });
    }
};
