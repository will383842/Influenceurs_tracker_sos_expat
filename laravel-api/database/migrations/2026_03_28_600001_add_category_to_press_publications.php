<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_publications', function (Blueprint $table) {
            $table->string('category')->nullable()->after('media_type');
            // Editorial category:
            // presse_nationale | presse_economique | presse_entrepreneuriat | presse_voyage
            // presse_expat | presse_tech | presse_juridique | presse_sante | presse_lifestyle
            // tv_news | tv_economique | tv_voyage | radio_nationale | radio_internationale
            // presse_regionale | presse_francophone | annuaire_presse | magazine_generaliste
        });
    }

    public function down(): void
    {
        Schema::table('press_publications', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
