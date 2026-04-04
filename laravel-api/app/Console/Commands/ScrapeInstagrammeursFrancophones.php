<?php

namespace App\Console\Commands;

use App\Models\Influenceur;
use App\Models\User;
use App\Services\AI\PerplexityService;
use App\Services\AI\ClaudeService;
use App\Services\InstagramProfileScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Recherche les Instagrammeurs francophones pays par pays via Perplexity,
 * extrait leurs emails, et les importe dans Mission Control.
 *
 * Usage :
 *   php artisan instagram:scrape-francophones                    → toute l'Asie
 *   php artisan instagram:scrape-francophones --pays=Thaïlande   → un seul pays
 *   php artisan instagram:scrape-francophones --region=europe    → autre région
 *   php artisan instagram:scrape-francophones --dry-run          → affiche sans sauvegarder
 */
class ScrapeInstagrammeursFrancophones extends Command
{
    protected $signature = 'instagram:scrape-francophones
                            {--region=asie : Région (asie, europe, amerique, afrique, moyen-orient, oceanie, all)}
                            {--pays= : Traiter un seul pays}
                            {--dry-run : Affiche sans sauvegarder}
                            {--skip-scrape : Utilise uniquement Perplexity, sans scraper Instagram}';

    protected $description = 'Recherche les Instagrammeurs francophones pays par pays via Perplexity et les importe dans Mission Control';

    private const REGIONS = [
        'asie' => [
            'Thaïlande', 'Vietnam', 'Cambodge', 'Laos', 'Myanmar',
            'Indonésie', 'Malaisie', 'Singapour', 'Philippines',
            'Chine', 'Japon', 'Corée du Sud', 'Inde', 'Népal',
            'Sri Lanka', 'Bangladesh', 'Pakistan', 'Mongolie',
            'Hong Kong', 'Taïwan', 'Macao', 'Brunei', 'Timor-Leste',
            'Birmanie', 'Bhoutan', 'Maldives',
        ],
        'europe' => [
            'France', 'Belgique', 'Suisse', 'Luxembourg', 'Monaco',
            'Allemagne', 'Espagne', 'Portugal', 'Italie', 'Pays-Bas',
            'Royaume-Uni', 'Irlande', 'Pologne', 'Roumanie', 'Autriche',
            'République Tchèque', 'Hongrie', 'Grèce', 'Suède', 'Norvège',
            'Danemark', 'Finlande', 'Turquie', 'Ukraine', 'Russie',
            'Serbie', 'Croatie', 'Bulgarie', 'Slovaquie', 'Slovénie',
        ],
        'amerique' => [
            'Canada', 'États-Unis', 'Mexique', 'Brésil', 'Argentine',
            'Chili', 'Colombie', 'Pérou', 'Équateur', 'Uruguay',
            'Guadeloupe', 'Martinique', 'Guyane', 'Haïti', 'Bolivie',
            'Paraguay', 'Venezuela', 'Costa Rica', 'Panama', 'Cuba',
            'République Dominicaine', 'Porto Rico',
        ],
        'afrique' => [
            'Maroc', 'Algérie', 'Tunisie', 'Sénégal', 'Côte d\'Ivoire',
            'Cameroun', 'Madagascar', 'Réunion', 'Maurice', 'Congo',
            'RDC', 'Bénin', 'Togo', 'Burkina Faso', 'Mali', 'Niger',
            'Gabon', 'Guinée', 'Djibouti', 'Éthiopie', 'Kenya', 'Ghana',
            'Nigeria', 'Tanzanie', 'Mozambique', 'Afrique du Sud', 'Namibie',
            'Angola', 'Rwanda', 'Burundi', 'Comores', 'Cap-Vert', 'Égypte',
        ],
        'moyen-orient' => [
            'Émirats Arabes Unis', 'Qatar', 'Arabie Saoudite', 'Liban',
            'Jordanie', 'Bahreïn', 'Koweït', 'Oman', 'Israël', 'Iran',
        ],
        'oceanie' => [
            'Australie', 'Nouvelle-Zélande', 'Nouvelle-Calédonie',
            'Polynésie Française', 'Fidji', 'Vanuatu',
        ],
    ];

    public function handle(PerplexityService $perplexity, ClaudeService $claude, InstagramProfileScraperService $igScraper): int
    {
        // Choisir le service IA : Perplexity si quota OK, Claude sinon
        $ai = null;
        if ($perplexity->isConfigured()) {
            $ping = $perplexity->search('test');
            if ($ping['success'] || !str_contains($ping['error'] ?? '', '401')) {
                $ai = $perplexity;
                $this->info('🔍 Moteur IA : Perplexity Sonar (recherche web temps réel)');
            }
        }
        if (!$ai && $claude->isConfigured()) {
            $ai = $claude;
            $this->info('🤖 Moteur IA : Claude Haiku (connaissances entraînement)');
        }
        if (!$ai) {
            $this->error('Aucune clé API disponible (PERPLEXITY_API_KEY quota épuisé, ANTHROPIC_API_KEY absent)');
            return 1;
        }

        $isDryRun   = $this->option('dry-run');
        $skipScrape = $this->option('skip-scrape');
        $singlePays = $this->option('pays');
        $regionKey  = strtolower($this->option('region') ?? 'asie');

        if ($singlePays) {
            $countries = [$singlePays];
        } elseif ($regionKey === 'all') {
            $countries = array_merge(...array_values(self::REGIONS));
        } else {
            $countries = self::REGIONS[$regionKey] ?? self::REGIONS['asie'];
        }

        $this->info('=== Instagrammeurs Francophones — ' . strtoupper($regionKey) . ' ===');
        $this->info(count($countries) . ' pays à traiter' . ($isDryRun ? ' [DRY RUN]' : ''));
        $this->newLine();

        $adminId = User::where('role', 'admin')->value('id') ?? User::first()?->id ?? 1;

        $totalFound   = 0;
        $totalSaved   = 0;
        $totalSkipped = 0;

        foreach ($countries as $pays) {
            $this->line("🔍 <fg=yellow>{$pays}</>");

            $profiles = $this->discoverWithPerplexity($ai, $pays);

            if (empty($profiles)) {
                $this->line("  → Aucun profil trouvé");
                continue;
            }

            $this->line("  → " . count($profiles) . " profils trouvés");
            $totalFound += count($profiles);

            foreach ($profiles as $profile) {
                $name         = trim($profile['nom_compte'] ?? $profile['name'] ?? '');
                $handle       = trim($profile['handle'] ?? $profile['instagram_handle'] ?? '');
                $instagramUrl = trim($profile['url_instagram'] ?? $profile['instagram_url'] ?? '');
                $email        = strtolower(trim($profile['email'] ?? ''));
                $langue       = strtolower(trim($profile['langue'] ?? $profile['language'] ?? 'fr'));
                $abonnes      = $this->parseFollowers($profile['abonnes'] ?? $profile['followers'] ?? '');
                $sujet        = trim($profile['sujet'] ?? $profile['niche'] ?? '');

                if (!$name && !$handle) continue;
                if (!$name) $name = $handle;

                // Construire l'URL Instagram si on a le handle
                if (!$instagramUrl && $handle) {
                    $h = ltrim($handle, '@');
                    $instagramUrl = "https://www.instagram.com/{$h}/";
                }

                // Valider l'email Perplexity
                if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = '';
                }

                // Phase 2 : scraper le profil Instagram pour trouver l'email
                if (!$email && !$skipScrape && $instagramUrl) {
                    $this->line("    ↳ Scrape Instagram pour email ({$name})...");
                    $scraped = $igScraper->scrapeProfile($instagramUrl);
                    if ($scraped['email']) {
                        $email = $scraped['email'];
                    }
                    if (!$abonnes && $scraped['followers']) {
                        $abonnes = (int) $scraped['followers'];
                    }
                    usleep(800_000);
                }

                // Phase 3 : Perplexity ciblé si toujours pas d'email
                if (!$email && $name) {
                    $email = $this->findEmailWithPerplexity($ai, $name, $handle, $pays);
                    if ($email) usleep(500_000);
                }

                // Règle stricte : email obligatoire
                if (!$email) {
                    $this->line("    <fg=gray>✗ {$name} — pas d'email</>");
                    $totalSkipped++;
                    continue;
                }

                if ($isDryRun) {
                    $this->line("    <fg=green>✓ {$name}</> | {$email} | {$pays} | {$langue}");
                    $totalSaved++;
                    continue;
                }

                $saved = $this->saveProfile([
                    'name'          => $name,
                    'handle'        => $handle ? ltrim($handle, '@') : null,
                    'instagram_url' => $instagramUrl,
                    'email'         => $email,
                    'country'       => $pays,
                    'language'      => $langue,
                    'followers'     => $abonnes,
                    'sujet'         => $sujet,
                    'admin_id'      => $adminId,
                ]);

                if ($saved) {
                    $this->line("    <fg=green>✓ SAUVÉ</> {$name} | {$email}");
                    $totalSaved++;
                } else {
                    $this->line("    <fg=cyan>~ EXISTANT</> {$name} | {$email}");
                }

                usleep(300_000);
            }

            $this->newLine();
            sleep(2);
        }

        $this->newLine();
        $this->info("=== RÉSUMÉ ===");
        $this->line("Profils trouvés       : {$totalFound}");
        $this->line("Sauvegardés           : {$totalSaved}");
        $this->line("Sans email (ignorés)  : {$totalSkipped}");

        return 0;
    }

    // =========================================================================
    // PERPLEXITY — 3 requêtes par pays
    // =========================================================================

    private function discoverWithPerplexity(PerplexityService|ClaudeService $perplexity, string $pays): array
    {
        $systemPrompt = <<<SYS
Tu es un expert en veille Instagram. Tu réponds UNIQUEMENT en JSON valide, sans texte autour.
Le JSON doit être un tableau d'objets. Ne jamais inventer des données. Si tu n'es pas sûr, mets null.
SYS;

        $queries = [
            // Angle 1 : micro-influenceurs expat avec email public
            "Liste des comptes Instagram de francophones (français/belges/suisses/québécois) vivant en {$pays}, "
            . "de taille moyenne (1k-100k abonnés), spécialisés expatriation/voyage/lifestyle. "
            . "IMPORTANT : inclure uniquement ceux dont l'email de contact professionnel est connu et public (dans bio, linktree, site perso). "
            . "Format JSON strict : [{\"nom_compte\":\"...\",\"handle\":\"@...\",\"url_instagram\":\"https://instagram.com/...\",\"email\":\"contact@...\",\"abonnes\":\"5000\",\"langue\":\"fr\",\"sujet\":\"...\"}]",

            // Angle 2 : blogueurs/créateurs de contenu expat
            "Instagrammeurs francophones actifs sur le thème 'vivre en {$pays}', expat, digital nomad, couples mixtes. "
            . "Préférer les créateurs qui ont un blog ou site web avec un email de contact visible. "
            . "JSON uniquement : [{\"nom_compte\":\"...\",\"handle\":\"@...\",\"url_instagram\":\"...\",\"email\":\"...\",\"abonnes\":\"...\",\"langue\":\"fr\",\"sujet\":\"...\"}]",

            // Angle 3 : comptes avec email vérifié
            "Quels créateurs Instagram francophones parlent de {$pays} (tourisme, food, culture, business, immobilier expat) "
            . "et ont un email professionnel public accessible (email dans bio Instagram ou sur leur site/linktree) ? "
            . "JSON : [{\"nom_compte\":\"...\",\"handle\":\"@...\",\"url_instagram\":\"...\",\"email\":\"contact@...\",\"abonnes\":\"...\",\"langue\":\"fr\",\"sujet\":\"...\"}]",
        ];

        $allProfiles = [];
        $seenNames   = [];

        foreach ($queries as $i => $query) {
            if ($i > 0) usleep(1_500_000);

            $result = $perplexity->searchJson($query, $systemPrompt);

            if (!$result['success']) {
                Log::warning("Perplexity Instagram query {$i} failed", ['pays' => $pays]);
                continue;
            }

            $data = $result['data'] ?? [];
            if (is_array($data) && !array_is_list($data)) {
                $data = array_values($data)[0] ?? [];
            }
            if (!is_array($data)) continue;

            foreach ($data as $profile) {
                $key = strtolower(trim(
                    $profile['handle'] ?? $profile['nom_compte'] ?? $profile['name'] ?? ''
                ));
                if (!$key || isset($seenNames[$key])) continue;
                $seenNames[$key] = true;
                $allProfiles[]   = $profile;
            }
        }

        return $allProfiles;
    }

    // =========================================================================
    // PERPLEXITY — Recherche ciblée d'email
    // =========================================================================

    private function findEmailWithPerplexity(PerplexityService|ClaudeService $perplexity, string $name, string $handle, string $pays): ?string
    {
        $handleStr = $handle ? " (@{$handle})" : '';
        $query = "Quelle est l'adresse email de contact publique de l'Instagrammeur \"{$name}\"{$handleStr} basé en {$pays} ? "
               . "Cherche dans la bio Instagram, le linktree, le site officiel et les réseaux sociaux. "
               . "Réponds UNIQUEMENT avec l'email si trouvé, ou le mot NULL si non trouvé. Pas d'autre texte.";

        $result = $perplexity->search($query);
        if (!$result['success']) return null;

        $content = trim($result['content']);
        if (preg_match('/\b([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})\b/', $content, $m)) {
            $email = strtolower($m[1]);
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }
        return null;
    }

    // =========================================================================
    // SAUVEGARDE
    // =========================================================================

    private function saveProfile(array $data): bool
    {
        $existing = Influenceur::where(function ($q) use ($data) {
            if ($data['email']) $q->where('email', $data['email']);
            if ($data['instagram_url']) $q->orWhere('instagram_url', $data['instagram_url']);
            if ($data['handle']) $q->orWhere('handle', $data['handle']);
        })->first();

        if ($existing) {
            $updates = [];
            if (!$existing->email && $data['email'])               $updates['email']          = $data['email'];
            if (!$existing->instagram_url && $data['instagram_url']) $updates['instagram_url'] = $data['instagram_url'];
            if (!$existing->followers && $data['followers'])        $updates['followers']      = $data['followers'];
            if ($updates) $existing->update($updates);
            return false;
        }

        Influenceur::create([
            'contact_type'     => 'instagrammeur',
            'category'         => 'medias_influence',
            'name'             => $data['name'],
            'handle'           => $data['handle'],
            'email'            => $data['email'],
            'has_email'        => true,
            'instagram_url'    => $data['instagram_url'] ?: null,
            'profile_url'      => $data['instagram_url'] ?: null,
            'primary_platform' => 'instagram',
            'platforms'        => ['instagram'],
            'followers'        => $data['followers'],
            'country'          => $data['country'],
            'language'         => substr($this->normalizeLanguage($data['language']), 0, 10),
            'status'           => 'prospect',
            'source'           => 'perplexity_instagram',
            'notes'            => $data['sujet'] ? 'Niche: ' . $data['sujet'] : null,
            'created_by'       => $data['admin_id'],
            'score'            => $this->computeScore($data),
        ]);

        return true;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function normalizeLanguage(?string $lang): string
    {
        if (!$lang) return 'fr';
        $lang = strtolower(trim($lang));
        $map = [
            'français'  => 'fr', 'french'    => 'fr', 'francais'  => 'fr',
            'anglais'   => 'en', 'english'   => 'en',
            'vietnamien'=> 'vi', 'vietnamese'=> 'vi',
            'chinois'   => 'zh', 'chinese'   => 'zh',
            'japonais'  => 'ja', 'japanese'  => 'ja',
            'thai'      => 'th', 'thaï'      => 'th',
            'espagnol'  => 'es', 'spanish'   => 'es',
            'allemand'  => 'de', 'german'    => 'de',
            'portugais' => 'pt', 'portuguese'=> 'pt',
            'arabe'     => 'ar', 'arabic'    => 'ar',
            'coréen'    => 'ko', 'korean'    => 'ko',
        ];
        foreach ($map as $key => $code) {
            if (str_contains($lang, $key)) return $code;
        }
        if (strlen($lang) <= 5) return $lang;
        return 'fr';
    }

    private function parseFollowers(string|int|null $raw): ?int
    {
        if ($raw === null || $raw === '') return null;
        if (is_int($raw)) return $raw;
        $raw = strtolower(str_replace([' ', ',', '.'], '', (string) $raw));
        if (str_ends_with($raw, 'k')) return (int)((float)$raw * 1_000);
        if (str_ends_with($raw, 'm')) return (int)((float)$raw * 1_000_000);
        return is_numeric($raw) ? (int)$raw : null;
    }

    private function computeScore(array $data): int
    {
        $score = 20;
        if ($data['instagram_url'])         $score += 10;
        if (($data['followers'] ?? 0) >= 5000)  $score += 15;
        if (($data['followers'] ?? 0) >= 20000) $score += 20;
        if (($data['followers'] ?? 0) >= 100000) $score += 15;
        return min($score, 100);
    }
}
