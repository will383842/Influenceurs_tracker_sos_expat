<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            // Image Unsplash (même structure que generated_articles)
            $table->string('featured_image_url', 500)->nullable()->after('seo_score');
            $table->string('featured_image_alt', 300)->nullable()->after('featured_image_url');
            $table->string('featured_image_attribution', 300)->nullable()->after('featured_image_alt');
            $table->string('photographer_name', 150)->nullable()->after('featured_image_attribution');
            $table->string('photographer_url', 500)->nullable()->after('photographer_name');

            // Lien parent pour les variantes langues (FR = parent, EN/ES/... = enfants)
            // La colonne parent_id existait déjà dans la migration initiale landing_pages
            // mais sans index — on l'ajoute ici si elle manque
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn([
                'featured_image_url',
                'featured_image_alt',
                'featured_image_attribution',
                'photographer_name',
                'photographer_url',
            ]);
        });
    }
};
