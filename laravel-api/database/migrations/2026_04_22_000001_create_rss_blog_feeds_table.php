<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Option D — P1 : Table rss_blog_feeds pour le scraper RSS de blogueurs.
 *
 * Ecosysteme separe de rss_feeds (news), pour alimenter influenceurs
 * avec contact_type=blog (zero risque ban : XML public, UA declare).
 *
 * fetch_about=true : permet fetch homepage /about 1x/semaine/feed pour
 * extraire mailto + JSON-LD Person (cache about_emails, TTL 7j).
 *
 * Idempotente via Schema::hasTable check.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rss_blog_feeds')) {
            return;
        }

        Schema::create('rss_blog_feeds', function (Blueprint $t) {
            $t->id();
            $t->string('name', 255);
            $t->string('url', 500)->unique();
            $t->string('base_url', 500)->nullable();
            $t->string('language', 5)->default('fr');
            $t->string('country', 100)->nullable();
            $t->string('category', 100)->nullable();
            $t->boolean('active')->default(true);
            // fetch_about ON par defaut : fetch homepage /about 1x/7j
            $t->boolean('fetch_about')->default(true);
            // fetch_pattern_inference OFF par defaut : pas d'emails inferes (evite bounces)
            $t->boolean('fetch_pattern_inference')->default(false);
            $t->smallInteger('fetch_interval_hours')->unsigned()->default(6);
            $t->timestamp('last_scraped_at')->nullable();
            $t->unsignedInteger('last_contacts_found')->default(0);
            $t->unsignedInteger('total_contacts_found')->default(0);
            // about_emails : cache JSON des emails trouves sur homepage (TTL 7j)
            $t->json('about_emails')->nullable();
            $t->timestamp('about_fetched_at')->nullable();
            $t->text('last_error')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index('active', 'rss_blog_feeds_active_idx');
            $t->index('country', 'rss_blog_feeds_country_idx');
            $t->index('last_scraped_at', 'rss_blog_feeds_last_scraped_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rss_blog_feeds');
    }
};
