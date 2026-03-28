<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE MATERIALIZED VIEW IF NOT EXISTS city_profiles AS
            WITH article_stats AS (
                SELECT
                    cc.id          AS city_id,
                    cc.name        AS city_name,
                    cc.slug        AS city_slug,
                    cc.continent,
                    cco.id         AS country_id,
                    cco.name       AS country_name,
                    cco.slug       AS country_slug,
                    COUNT(ca.id)   AS total_articles,
                    SUM(ca.word_count) AS total_words,
                    COUNT(DISTINCT cs.id) AS nb_sources,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'visa'    OR ca.title ~* 'visa') AS visa_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'emploi'  OR ca.title ~* '(emploi|travaill|work|job)') AS emploi_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'logement' OR ca.title ~* '(loger|logement|hous|rent|apartment)') AS logement_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'sante'   OR ca.title ~* '(sant|health|médecin|doctor|hospital)') AS sante_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'banque'  OR ca.title ~* '(banque|bank|financ)') AS banque_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'transport' OR ca.title ~* '(transport|conduire|driv)') AS transport_articles,
                    COUNT(ca.id) FILTER (WHERE ca.category = 'culture' OR ca.title ~* '(cultur|tradition|coutume|custom)') AS culture_articles,
                    ROUND(AVG(ca.word_count)) AS avg_word_count,
                    (
                        CASE WHEN COUNT(ca.id) FILTER (WHERE ca.category = 'visa'     OR ca.title ~* 'visa') > 0 THEN 1 ELSE 0 END +
                        CASE WHEN COUNT(ca.id) FILTER (WHERE ca.category = 'emploi'   OR ca.title ~* '(emploi|travaill|work|job)') > 0 THEN 1 ELSE 0 END +
                        CASE WHEN COUNT(ca.id) FILTER (WHERE ca.category = 'logement' OR ca.title ~* '(loger|logement|hous|rent)') > 0 THEN 1 ELSE 0 END +
                        CASE WHEN COUNT(ca.id) FILTER (WHERE ca.category = 'sante'    OR ca.title ~* '(sant|health|médecin|doctor)') > 0 THEN 1 ELSE 0 END +
                        CASE WHEN COUNT(ca.id) FILTER (WHERE ca.category = 'banque'   OR ca.title ~* '(banque|bank|financ)') > 0 THEN 1 ELSE 0 END +
                        CASE WHEN COUNT(ca.id) FILTER (WHERE ca.category = 'transport' OR ca.title ~* '(transport|conduire|driv)') > 0 THEN 1 ELSE 0 END +
                        CASE WHEN COUNT(ca.id) FILTER (WHERE ca.category = 'culture'  OR ca.title ~* '(cultur|tradition|coutume)') > 0 THEN 1 ELSE 0 END
                    ) AS thematic_coverage,
                    (COUNT(ca.id) * 10 + COALESCE(SUM(ca.word_count), 0) / 100) AS priority_score
                FROM content_cities cc
                LEFT JOIN content_countries cco ON cco.id = cc.country_id
                LEFT JOIN content_articles ca   ON ca.city_id = cc.id
                LEFT JOIN content_sources cs    ON ca.source_id = cs.id
                GROUP BY cc.id, cc.name, cc.slug, cc.continent, cco.id, cco.name, cco.slug
            )
            SELECT * FROM article_stats
        ");

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS city_profiles_city_id_idx ON city_profiles(city_id)");
        DB::statement("CREATE INDEX IF NOT EXISTS city_profiles_country_slug_idx ON city_profiles(country_slug)");
        DB::statement("CREATE INDEX IF NOT EXISTS city_profiles_continent_idx ON city_profiles(continent)");
    }

    public function down(): void
    {
        DB::statement("DROP MATERIALIZED VIEW IF EXISTS city_profiles");
    }
};
