<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url', 500);
            $table->string('domain', 255)->index();
            $table->string('category', 50)->index();       // contact_type: school, lawyer, etc.
            $table->string('country', 100)->nullable()->index();
            $table->string('language', 10)->nullable();
            $table->string('status', 20)->default('pending'); // pending, scraping, completed, failed
            $table->integer('contacts_extracted')->default(0);
            $table->integer('contacts_created')->default(0);
            $table->integer('pages_scraped')->default(0);
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();  // Anti-ban: don't rescrape before this
            $table->json('metadata')->nullable();              // Extraction details, errors, etc.
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['url', 'category'], 'directories_url_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directories');
    }
};
