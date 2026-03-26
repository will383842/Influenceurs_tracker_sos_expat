<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_external_links', function (Blueprint $table) {
            $table->string('language', 10)->default('fr')->after('occurrences');
            $table->index('language');
        });
    }

    public function down(): void
    {
        Schema::table('content_external_links', function (Blueprint $table) {
            $table->dropIndex(['language']);
            $table->dropColumn('language');
        });
    }
};
