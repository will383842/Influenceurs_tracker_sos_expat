<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries_geo', function (Blueprint $table) {
            $table->string('country_code', 2)->primary();
            $table->string('country_name_fr');
            $table->string('country_name_en');
            $table->decimal('latitude', 10, 6);
            $table->decimal('longitude', 10, 6);
            $table->string('capital_fr');
            $table->string('capital_en');
            $table->string('official_language');
            $table->string('currency_code', 3)->nullable();
            $table->string('currency_name')->nullable();
            $table->string('region')->nullable();         // europe, asia, africa, americas, oceania, middle_east
            $table->unsignedInteger('expat_approx')->default(0);  // approximate expat count
            $table->string('timezone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries_geo');
    }
};
