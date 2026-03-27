<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->string('og_title', 100)->nullable()->after('meta_description');
            $table->string('og_description', 200)->nullable()->after('og_title');
            $table->text('ai_summary')->nullable()->after('og_description');
        });
    }

    public function down(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->dropColumn(['og_title', 'og_description', 'ai_summary']);
        });
    }
};
