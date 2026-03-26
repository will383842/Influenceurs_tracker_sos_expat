<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('content_sources')->onDelete('cascade');
            $table->string('name', 200);
            $table->string('role', 200)->nullable();
            $table->string('email', 300)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('company', 200)->nullable();
            $table->string('company_url', 500)->nullable();
            $table->string('linkedin', 500)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('sector', 100)->nullable(); // assurance, education, media, etc.
            $table->text('notes')->nullable();
            $table->string('page_url', 1000)->nullable(); // where we found this contact
            $table->string('language', 10)->default('fr');
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->index('source_id');
            $table->index('email');
            $table->index('company');
            $table->index('sector');
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_contacts');
    }
};
