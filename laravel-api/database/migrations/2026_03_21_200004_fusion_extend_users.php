<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FUSION: Add manager role + territories to users.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Convert role from enum to varchar for extensibility
        DB::statement("ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(20) USING role::VARCHAR");
        DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'member'");

        Schema::table('users', function (Blueprint $table) {
            $table->json('territories')->nullable()->after('contact_types');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('territories');
        });
    }
};
