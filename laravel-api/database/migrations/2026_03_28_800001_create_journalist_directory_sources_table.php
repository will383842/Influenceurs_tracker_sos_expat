<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journalist_directory_sources', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('base_url');
            $table->string('search_url')->nullable();           // URL pattern avec {keyword} et {page}
            $table->string('browse_url')->nullable();           // URL à paginer directement
            $table->json('keywords')->nullable();               // Mots-clés de filtrage
            $table->string('scrape_strategy')->default('search'); // search | browse | association
            $table->string('status')->nullable();               // pending | running | completed | failed
            $table->integer('contacts_found')->default(0);
            $table->integer('pages_scraped')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_scraped_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ── Seed des annuaires cibles ──────────────────────────────────────────
        DB::table('journalist_directory_sources')->insert([

            // ── Annuaires généralistes avec recherche ────────────────────────
            [
                'slug'            => 'annuaire-journaliste-fr',
                'name'            => 'annuaire.journaliste.fr (SDJ)',
                'base_url'        => 'https://annuaire.journaliste.fr',
                'search_url'      => 'https://annuaire.journaliste.fr/annuaire?q={keyword}&page={page}',
                'browse_url'      => null,
                'keywords'        => json_encode(['expat', 'expatriation', 'immigration', 'mobilité internationale', 'international', 'voyage', 'tourisme', 'fiscal', 'droit international', 'juridique', 'entrepreneurs']),
                'scrape_strategy' => 'search',
                'status'          => 'pending',
                'notes'           => 'Annuaire principal des journalistes français (SDJ). ~50 000 journalistes.',
                'created_at'      => now(), 'updated_at' => now(),
            ],
            [
                'slug'            => 'presselib',
                'name'            => 'PresseLib — Journalistes indépendants',
                'base_url'        => 'https://www.presselib.com',
                'search_url'      => 'https://www.presselib.com/journalistes?search={keyword}&page={page}',
                'browse_url'      => 'https://www.presselib.com/journalistes?page={page}',
                'keywords'        => json_encode(['expat', 'expatriation', 'immigration', 'voyage', 'international', 'fiscal', 'juridique']),
                'scrape_strategy' => 'search',
                'status'          => 'pending',
                'notes'           => 'Pigistes et correspondants indépendants avec email direct souvent visible.',
                'created_at'      => now(), 'updated_at' => now(),
            ],

            // ── Associations par spécialité (membres tous pertinents) ────────
            [
                'slug'            => 'aejt',
                'name'            => 'AEJT — Journalistes de Tourisme',
                'base_url'        => 'https://www.aejt.fr',
                'search_url'      => null,
                'browse_url'      => 'https://www.aejt.fr/annuaire?page={page}',
                'keywords'        => null,
                'scrape_strategy' => 'association',
                'status'          => 'pending',
                'notes'           => 'Tous les membres sont journalistes tourisme/voyage — très pertinent expat.',
                'created_at'      => now(), 'updated_at' => now(),
            ],
            [
                'slug'            => 'ajef',
                'name'            => 'AJEF — Journalistes Économiques & Financiers',
                'base_url'        => 'https://www.ajef.net',
                'search_url'      => null,
                'browse_url'      => 'https://www.ajef.net/membres?page={page}',
                'keywords'        => null,
                'scrape_strategy' => 'association',
                'status'          => 'pending',
                'notes'           => 'Économie, finances, fiscalité — pertinent pour sujets expat fiscal/patrimoine.',
                'created_at'      => now(), 'updated_at' => now(),
            ],
            [
                'slug'            => 'spej',
                'name'            => 'SPEJ — Presse Économique et Juridique',
                'base_url'        => 'https://www.spej.fr',
                'search_url'      => null,
                'browse_url'      => 'https://www.spej.fr/annuaire?page={page}',
                'keywords'        => null,
                'scrape_strategy' => 'association',
                'status'          => 'pending',
                'notes'           => 'Presse économique et juridique professionnelle.',
                'created_at'      => now(), 'updated_at' => now(),
            ],
            [
                'slug'            => 'apf-presse',
                'name'            => 'APF — Association de la Presse Francophone',
                'base_url'        => 'https://www.apf-presse.com',
                'search_url'      => null,
                'browse_url'      => 'https://www.apf-presse.com/membres?page={page}',
                'keywords'        => null,
                'scrape_strategy' => 'association',
                'status'          => 'pending',
                'notes'           => 'Médias francophones international — correspondants étrangers très pertinents.',
                'created_at'      => now(), 'updated_at' => now(),
            ],
            [
                'slug'            => 'ujjef',
                'name'            => 'UJJEF — Presse Professionnelle',
                'base_url'        => 'https://www.ujjef.com',
                'search_url'      => null,
                'browse_url'      => 'https://www.ujjef.com/membres?page={page}',
                'keywords'        => null,
                'scrape_strategy' => 'association',
                'status'          => 'pending',
                'notes'           => 'Presse professionnelle B2B — secteurs RH/mobilité/international.',
                'created_at'      => now(), 'updated_at' => now(),
            ],

            // ── Annuaires spécialisés international / expat ──────────────────
            [
                'slug'            => 'correspondants-fr',
                'name'            => 'Correspondants.fr — Journalistes expatriés',
                'base_url'        => 'https://www.correspondants.fr',
                'search_url'      => 'https://www.correspondants.fr/correspondants?q={keyword}&page={page}',
                'browse_url'      => 'https://www.correspondants.fr/correspondants?page={page}',
                'keywords'        => json_encode(['expat', 'correspondant', 'international', 'étranger']),
                'scrape_strategy' => 'browse',
                'status'          => 'pending',
                'notes'           => 'Correspondants et journalistes expatriés — cible directe.',
                'created_at'      => now(), 'updated_at' => now(),
            ],
            [
                'slug'            => 'spiil',
                'name'            => 'SPIIL — Presse Indépendante en Ligne',
                'base_url'        => 'https://www.spiil.org',
                'search_url'      => null,
                'browse_url'      => 'https://www.spiil.org/membres?page={page}',
                'keywords'        => null,
                'scrape_strategy' => 'association',
                'status'          => 'pending',
                'notes'           => 'Médias numériques indépendants — presse en ligne.',
                'created_at'      => now(), 'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('journalist_directory_sources');
    }
};
