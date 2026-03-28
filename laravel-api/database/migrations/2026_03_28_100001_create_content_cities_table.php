<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table des villes scrapées, classées par pays
        Schema::create('content_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('content_sources')->onDelete('cascade');
            $table->foreignId('country_id')->constrained('content_countries')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('continent', 50)->nullable();
            $table->string('guide_url', 500);
            $table->integer('articles_count')->default(0);
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'country_id', 'slug']);
            $table->index('country_id');
            $table->index('continent');
            $table->index('source_id');
        });

        // Colonne city_id nullable sur content_articles
        Schema::table('content_articles', function (Blueprint $table) {
            $table->foreignId('city_id')
                ->nullable()
                ->after('country_id')
                ->constrained('content_cities')
                ->onDelete('set null');
            $table->index('city_id');
        });
    }

    public function down(): void
    {
        Schema::table('content_articles', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropIndex(['city_id']);
            $table->dropColumn('city_id');
        });

        Schema::dropIfExists('content_cities');
    }
};
