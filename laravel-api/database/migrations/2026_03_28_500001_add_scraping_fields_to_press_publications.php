<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_publications', function (Blueprint $table) {
            $table->string('authors_url')->nullable()->after('contact_url');
            // URL of the author/journalist index page (e.g. /auteurs/, /redaction/, /reporters/)
            $table->string('articles_url')->nullable()->after('authors_url');
            // URL of the main article listing (RSS or paginated list) to extract bylines
            $table->string('email_pattern')->nullable()->after('articles_url');
            // Pattern for email inference: e.g. "{first}.{last}@lefigaro.fr"
            // Tokens: {first}, {last}, {f} (initial of first), {fl} (first+last no dot)
            $table->string('email_domain')->nullable()->after('email_pattern');
            // Domain used for emails (sometimes different from base_url domain)
            $table->integer('authors_discovered')->default(0)->after('contacts_count');
            $table->integer('emails_inferred')->default(0)->after('authors_discovered');
            $table->integer('emails_verified')->default(0)->after('emails_inferred');
        });

        // Add email inference status to press_contacts
        Schema::table('press_contacts', function (Blueprint $table) {
            $table->string('email_source')->nullable()->after('email');
            // scraped | inferred | manual | directory
            $table->boolean('email_smtp_valid')->nullable()->after('email_verified');
            $table->timestamp('email_checked_at')->nullable()->after('email_smtp_valid');
        });
    }

    public function down(): void
    {
        Schema::table('press_publications', function (Blueprint $table) {
            $table->dropColumn(['authors_url', 'articles_url', 'email_pattern', 'email_domain',
                                'authors_discovered', 'emails_inferred', 'emails_verified']);
        });
        Schema::table('press_contacts', function (Blueprint $table) {
            $table->dropColumn(['email_source', 'email_smtp_valid', 'email_checked_at']);
        });
    }
};
