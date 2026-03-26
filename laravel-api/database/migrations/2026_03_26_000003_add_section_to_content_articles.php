<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_articles', function (Blueprint $table) {
            $table->string('section', 30)->default('guide')->after('is_guide'); // guide, magazine, services
            $table->index('section');
        });

        // Mark existing articles as 'guide'
        \Illuminate\Support\Facades\DB::table('content_articles')->update(['section' => 'guide']);
    }

    public function down(): void
    {
        Schema::table('content_articles', function (Blueprint $table) {
            $table->dropIndex(['section']);
            $table->dropColumn('section');
        });
    }
};
