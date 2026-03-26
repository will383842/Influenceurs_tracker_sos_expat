<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('content_sources')->onDelete('cascade');
            $table->unsignedInteger('external_id')->nullable(); // expat.com business ID
            $table->string('name', 300);
            $table->string('slug', 300);
            $table->string('url', 1000);          // Full URL on expat.com
            $table->string('url_hash', 64)->unique();

            // Contact info
            $table->string('contact_name', 200)->nullable();
            $table->string('contact_email', 300)->nullable();
            $table->string('contact_phone', 100)->nullable();
            $table->string('website', 1000)->nullable();       // Direct website URL
            $table->string('website_redirect', 1000)->nullable(); // Tracked URL via expat.com

            // Location
            $table->string('country', 100)->nullable();
            $table->string('country_slug', 100)->nullable();
            $table->string('continent', 50)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Classification
            $table->string('category', 100)->nullable();
            $table->string('category_slug', 100)->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->string('subcategory', 100)->nullable();
            $table->string('subcategory_slug', 100)->nullable();
            $table->unsignedInteger('subcategory_id')->nullable();

            // Details
            $table->text('description')->nullable();
            $table->string('logo_url', 1000)->nullable();
            $table->json('images')->nullable();
            $table->json('opening_hours')->nullable();
            $table->unsignedInteger('recommendations')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->boolean('is_premium')->default(false);
            $table->string('schema_type', 50)->nullable(); // Schema.org @type

            // Scraping metadata
            $table->string('language', 10)->default('fr');
            $table->boolean('detail_scraped')->default(false); // true = fiche detail fetchee
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('source_id');
            $table->index('country_slug');
            $table->index('city');
            $table->index('category_slug');
            $table->index('subcategory_slug');
            $table->index('is_premium');
            $table->index('detail_scraped');
            $table->index(['country_slug', 'city']);
            $table->index(['country_slug', 'category_slug']);
            $table->index('contact_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_businesses');
    }
};
