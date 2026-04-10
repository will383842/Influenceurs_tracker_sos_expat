CONTEXTE : Mission Control SOS-Expat — Audit E2E COMPLET du système de génération de contenu.

Projet : C:\Users\willi\Documents\Projets\VS_CODE\Outils_communication\Mission_control_sos-expat
- Backend : laravel-api/ (PHP 8.2, Laravel 12, PostgreSQL, Docker sur Hetzner VPS 95.216.179.163)
- Frontend : react-dashboard/ (React + TypeScript + Vite + Tailwind)
- Blog SSR : /opt/blog-sos-expat sur même VPS (Laravel, containers blog-app/blog-nginx/blog-postgres)
- Containers MC : inf-app, inf-queue, inf-scheduler, inf-content-worker, inf-content-scraper, inf-publication-worker, inf-email-worker, inf-scraper, inf-frontend, inf-nginx-api, inf-postgres, inf-redis
- URL dashboard : https://influenceurs.life-expat.com
- URL blog : https://sos-expat.com
- Réseau Docker partagé : shared-network (MC ↔ Blog)
- IA : Claude API (Anthropic) + GPT-4o (OpenAI) + Perplexity (recherche)
- Images : Unsplash API + DALL-E
- Alertes : Telegram bot

MISSION : Audit E2E exhaustif du pipeline complet — de la source brute jusqu'à l'article publié sur sos-expat.com en 9 langues. Tu dois EXÉCUTER des commandes réelles (curl, docker exec, requêtes DB via php artisan tinker) — ne suppose RIEN. NE CORRIGE RIEN — diagnostic et rapport uniquement. Communique en français.

═══════════════════════════════════════════════════════════════
PHASE 1 — INVENTAIRE COMPLET DES TYPES DE CONTENU
═══════════════════════════════════════════════════════════════

1.1. Lire laravel-api/app/Services/Content/ContentTypeConfig.php
Pour CHAQUE type de contenu, noter :
- Nom, slug, content_type
- Modèle IA utilisé (GPT-4o, Claude, etc.)
- Température, min_words, max_words, faq_count
- research_depth (full/light/none)
- comparison_table (bool)
- Instructions spécifiques

Les 15 types attendus :
guide_city, guide/pillar, article, comparative, qa, testimonial, qa_needs, tutorial, statistics, pain_point, news, outreach, affiliation + default fallback

1.2. Vérifier les search_intent overrides :
- informational, commercial_investigation, transactional, local, urgency
- Comment chaque intent modifie la structure du contenu

1.3. Vérifier la correspondance entre :
- ContentTypeConfig.php (backend)
- TYPE_CONFIG dans ContentGenerator.tsx (frontend)
- Les slugs dans la table generation_source_categories (DB)
- Les routes dans App.tsx (react-dashboard)
- Les 19 pages de contenu dans le dashboard

1.4. Identifier tout type défini d'un côté mais absent de l'autre (désynchronisation backend/frontend/DB).

1.5. Interroger la base :
```
ssh root@95.216.179.163 "docker exec inf-app php artisan tinker --execute=\"
  \\\$cats = DB::table('generation_source_categories')->get();
  foreach(\\\$cats as \\\$c) echo \\\$c->slug.' | total='.\\\$c->total_items.' ready='.\\\$c->ready_items.' used='.\\\$c->used_items.' paused='.(\\\$c->is_paused?'Y':'N').' quota='.\\\$c->daily_quota.' weight='.\\\$c->weight_percent.PHP_EOL;
\""
```

═══════════════════════════════════════════════════════════════
PHASE 2 — PIPELINE SOURCE → ITEMS (alimentation)
═══════════════════════════════════════════════════════════════

2.1. Pour CHAQUE type, vérifier comment les items sources sont alimentés :
- Expansion automatique 197 pays (quels types ?)
- Import CSV (ArtMotsCles)
- Flux RSS (NewsHub → FetchRssFeedsJob toutes les 4h)
- Découverte IA automatique (ArtLonguesTraines → /content-gen/keywords/discover)
- Recherche statistiques (World Bank, OECD, Eurostat → /content-gen/statistics-data/fetch/*)
- Création manuelle (ArticlesList, ComparativesList)
- Auto-clustering (clusters → /content-gen/clusters/auto-cluster)
- Question clustering (/content-gen/question-clusters/auto-cluster)

2.2. Vérifier les doublons d'items sources :
```sql
SELECT title, COUNT(*) FROM generation_source_items GROUP BY title HAVING COUNT(*) > 1;
```

2.3. Vérifier les items bloqués en "processing" depuis plus de 2h :
```sql
SELECT id, title, processing_status, updated_at FROM generation_source_items 
WHERE processing_status='processing' AND updated_at < NOW() - INTERVAL '2 hours';
```

2.4. Vérifier la distribution des items par statut et catégorie :
```sql
SELECT category_slug, processing_status, COUNT(*) 
FROM generation_source_items GROUP BY category_slug, processing_status 
ORDER BY category_slug, processing_status;
```

═══════════════════════════════════════════════════════════════
PHASE 3 — PIPELINE GÉNÉRATION IA (15 phases)
═══════════════════════════════════════════════════════════════

Lire laravel-api/app/Services/Content/ArticleGenerationService.php et vérifier CHAQUE phase :

Phase 01 - Validate : paramètres d'entrée (topic, language, country)
Phase 02 - Research : Perplexity (PerplexitySearchService) pour full, skip pour light/none
Phase 03 - Structure : outline H2/H3
Phase 04 - Generate Content : prompt IA complet via GPT-4o/Claude, KB prompt, search intent
Phase 05 - Extract Facts : statistiques clés extraites du contenu
Phase 06 - Calculate SEO : SeoAnalysisService (titre, meta, keyword density, readability Flesch-Kincaid)
Phase 07 - Title Variants : alternatives pour A/B test
Phase 08 - Meta Tags : meta_title (<60 chars), meta_description (<155 chars), OG tags
Phase 09 - Generate FAQ : 3-12 Q&A selon content_type
Phase 10 - Slug Generation : SlugService, translittération pour non-Latin (AR, ZH, RU, HI)
Phase 11 - Affiliate Links : 0-2 liens injectés par matching keywords dans les paragraphes
Phase 12 - Images : Unsplash search OU DALL-E, featured_image_url/alt/attribution/srcset
Phase 13 - Internal Links : AdvancedLinkingService (TF-IDF cosine similarity), scoring (country +0.15, type +0.10, cluster +0.10), max 2/section H2, 6-7 total, anchor types (exact 70%, partial 15%, branded 5%, conversational 10%)
Phase 14 - JSON-LD : JsonLdService → Article schema + FAQPage + BreadcrumbList + speakable + Table of Contents
Phase 15 - Auto-Publish : dispatch PublishContentJob avec délai 90s pour traductions

3.1. Vérifier que les 15 phases s'exécutent — prendre 3 articles récents :
```sql
SELECT phase, status, elapsed, LEFT(details, 100) as details 
FROM generation_logs WHERE loggable_id=X ORDER BY id;
```

3.2. Vérifier les coûts API par article :
```sql
SELECT provider, model, tokens_input, tokens_output, cost_cents 
FROM api_costs WHERE costable_id=X;
```

3.3. Vérifier le système de retry :
- GenerateArticleJob : tries=3, maxExceptions=2, backoff=[60, 300]
- Jobs failed : `docker exec inf-app php artisan queue:failed`

3.4. Vérifier les logs du content worker :
```
ssh root@95.216.179.163 "docker logs inf-content-worker --since 24h 2>&1 | grep -i 'phase\|error\|fail\|completed' | tail -50"
```

═══════════════════════════════════════════════════════════════
PHASE 4 — ORCHESTRATEUR AUTO-PILOT
═══════════════════════════════════════════════════════════════

Lire RunOrchestratorCycleJob.php et ContentOrchestratorService.php.

4.1. Vérifier la config orchestrateur en base :
```sql
SELECT * FROM content_orchestrator_config LIMIT 1;
```
- daily_target, today_generated, auto_pilot status
- type_distribution (JSON %)
- priority_countries
- today_cost_cents
- status (running/paused/stopped)

4.2. Vérifier les logs d'orchestration récents :
```sql
SELECT cycle_date, cycle_time, types_generated, errors, cost_cents, duration_seconds 
FROM content_orchestrator_logs ORDER BY id DESC LIMIT 20;
```

4.3. Vérifier le scheduler (routes/console.php) — toutes les commandes schedulées :
- RunOrchestratorCycleJob : toutes les 15 min, withoutOverlapping
- FetchRssFeedsJob : toutes les 4h
- RunNewsGenerationJob : daily à 08:00 UTC, withoutOverlapping(7200)
- Stale recovery news : toutes les 15 min (reset generating > 30min)
- Vérifier les logs : `docker logs inf-scheduler --tail 100`

4.4. Vérifier la fenêtre active (06:00-22:00 UTC).
4.5. Vérifier le calcul cycle count : daily_target / ~64 cycles.
4.6. Vérifier les alertes Telegram configurées :
- Erreurs → message Telegram
- Quota atteint → cost summary
- API provider down → fallback

═══════════════════════════════════════════════════════════════
PHASE 5 — STRUCTURE ARTICLE GÉNÉRÉ
═══════════════════════════════════════════════════════════════

5.1. Lire le modèle GeneratedArticle.php — vérifier TOUS les champs :
- title, slug, content_html, content_text, excerpt
- language, country, content_type
- parent_article_id (traductions), pillar_article_id (clustering)
- meta_title, meta_description, og_title, og_description, twitter_title
- featured_image_url, featured_image_alt, featured_image_attribution, featured_image_srcset
- photographer_name, photographer_url
- keywords_primary, keywords_secondary (JSON), keyword_density
- seo_score, quality_score, readability_score
- word_count, reading_time_minutes
- json_ld, hreflang_map (JSON)
- status (generating/draft/review/scheduled/published/archived), published_at, scheduled_at
- generation_cost_cents, generation_tokens_input/output, generation_duration_seconds
- source_slug, input_quality, source_article_id
- title_variants (JSON)

5.2. Prendre 10 articles published de types DIFFÉRENTS :
```sql
SELECT id, title, slug, language, content_type, word_count, seo_score, quality_score, 
  LENGTH(content_html) as html_len, LENGTH(json_ld) as jsonld_len,
  featured_image_url IS NOT NULL as has_image,
  (SELECT COUNT(*) FROM generated_article_faqs WHERE article_id=a.id) as faq_count,
  (SELECT COUNT(*) FROM generated_articles WHERE parent_article_id=a.id) as translation_count
FROM generated_articles a WHERE status='published' AND parent_article_id IS NULL
ORDER BY RANDOM() LIMIT 10;
```

5.3. Vérifier les slugs ASCII pour TOUTES les langues :
```sql
SELECT id, slug, language FROM generated_articles WHERE slug ~ '[^a-z0-9\-]' AND status='published';
```
→ DOIT retourner 0 résultats.

5.4. Vérifier l'unicité des slugs :
```sql
SELECT slug, COUNT(*) FROM generated_articles GROUP BY slug HAVING COUNT(*) > 1;
```

5.5. Vérifier les meta tags :
```sql
SELECT id, title, LENGTH(meta_title) as mt_len, LENGTH(meta_description) as md_len 
FROM generated_articles WHERE status='published' 
AND (LENGTH(meta_title) > 60 OR LENGTH(meta_description) > 160 OR meta_title IS NULL OR meta_description IS NULL)
LIMIT 20;
```

5.6. Vérifier les JSON-LD :
```sql
SELECT id, title FROM generated_articles WHERE status='published' AND (json_ld IS NULL OR json_ld = '' OR json_ld = '{}');
```

═══════════════════════════════════════════════════════════════
PHASE 6 — TRADUCTIONS
═══════════════════════════════════════════════════════════════

Lire TranslationService.php et ProcessTranslationBatchJob.php.

6.1. Identifier les langues RÉELLEMENT supportées :
```sql
SELECT language, COUNT(*) as total FROM generated_articles WHERE status='published' GROUP BY language ORDER BY total DESC;
```

6.2. Vérifier la complétude des traductions :
```sql
SELECT a.id, a.title, a.language, 
  (SELECT COUNT(*) FROM generated_articles t WHERE t.parent_article_id=a.id) as translation_count
FROM generated_articles a WHERE a.parent_article_id IS NULL AND a.status='published'
ORDER BY translation_count ASC LIMIT 20;
```
→ Chaque article parent devrait avoir 8 traductions (en, es, de, pt, ru, zh, ar, hi).

6.3. Vérifier la qualité — prendre 1 article et ses 9 versions :
```sql
SELECT id, language, slug, LEFT(title, 80) as title, word_count, seo_score
FROM generated_articles WHERE id=X OR parent_article_id=X ORDER BY language;
```
- Le titre est-il traduit (pas identique au FR) ?
- Le slug est-il ASCII pour AR, ZH, RU, HI ?
- Le word_count est-il cohérent (pas 0, pas identique au FR) ?

6.4. Détecter les traductions échouées silencieusement :
```sql
SELECT a.id, a.title, t.language, t.title as titre_traduit
FROM generated_articles a JOIN generated_articles t ON t.parent_article_id=a.id
WHERE a.language='fr' AND a.status='published' AND a.title = t.title
LIMIT 20;
```
→ Titres identiques FR/traduit = traduction échouée.

6.5. Vérifier les batches de traduction :
```sql
SELECT id, status, source_language, target_languages, created_at 
FROM translation_batches ORDER BY id DESC LIMIT 10;
```

6.6. Traductions orphelines (parent supprimé) :
```sql
SELECT COUNT(*) FROM generated_articles 
WHERE parent_article_id IS NOT NULL 
AND parent_article_id NOT IN (SELECT id FROM generated_articles);
```

═══════════════════════════════════════════════════════════════
PHASE 7 — PUBLICATION SUR LE BLOG
═══════════════════════════════════════════════════════════════

Lire BlogPublisher.php et PublishContentJob.php.

7.1. Vérifier les endpoints de publication :
```sql
SELECT id, name, type, is_active, is_default, config FROM publication_endpoints;
```

7.2. Vérifier les schedules de publication :
```sql
SELECT * FROM publication_schedules;
```
- active_days, active_hours_start/end
- max_per_hour, max_per_day, min_interval_minutes
- Rate limiting avec lockForUpdate()

7.3. Vérifier la queue de publication par statut :
```sql
SELECT status, COUNT(*) FROM publication_queue_items GROUP BY status;
```

7.4. Vérifier les publications échouées :
```sql
SELECT pqi.id, pqi.publishable_type, pqi.publishable_id, pqi.attempts, pqi.last_error, pqi.updated_at
FROM publication_queue_items pqi WHERE pqi.status='failed' ORDER BY pqi.updated_at DESC LIMIT 20;
```

7.5. Vérifier la connectivité MC → Blog :
```
ssh root@95.216.179.163 "docker exec inf-publication-worker curl -s -o /dev/null -w '%{http_code}' http://blog-app:80/api/health"
```

7.6. Vérifier 5 articles published sur le blog RÉEL :
```sql
SELECT a.id, a.title, a.slug, pqi.external_url 
FROM generated_articles a JOIN publication_queue_items pqi ON pqi.publishable_id=a.id 
WHERE a.status='published' AND pqi.external_url IS NOT NULL 
ORDER BY a.published_at DESC LIMIT 5;
```
Pour chaque URL : `curl -sI "URL" | grep "HTTP\|Location"` → doit retourner 200.

7.7. Vérifier le contenu HTML servi par le blog :
```
curl -s "https://sos-expat.com/article/{slug}" | grep -o '<title>[^<]*</title>\|<meta name="description"[^>]*>'
```

7.8. Vérifier le payload BlogPublisher (lire le code) :
- idempotency_key, content_type, category_slug (mapping guide→fiches-pays, article→fiches-pratiques, etc.)
- translations array (toutes les langues)
- faqs array par langue
- sources array (url, title, domain, trust_score)
- images (featured + body, srcset, photographer)
- tags, countries
- OG/Twitter/Geo meta
- JSON-LD complet

7.9. Post-publication :
- GenerateSitemapJob dispatché ?
- IndexNow soumis pour indexation rapide ?
- Article status → 'published', published_at rempli ?

═══════════════════════════════════════════════════════════════
PHASE 8 — SEO TECHNIQUE SUR LE BLOG LIVE
═══════════════════════════════════════════════════════════════

8.1. robots.txt :
```
curl -s "https://sos-expat.com/robots.txt"
```
- Pas de Disallow sur les articles
- Sitemap déclaré

8.2. Sitemap :
```
curl -s "https://sos-expat.com/sitemap.xml" | head -50
```
- Existe ? Contient les articles récents ?
- Versions multilingues ?

8.3. Hreflang — prendre 1 article FR publié avec traductions :
```
curl -s "https://sos-expat.com/article/{slug-fr}" | grep -o 'hreflang="[^"]*"' | sort
```
→ Doit lister les 9 langues (fr, en, es, de, pt, ru, zh, ar, hi) + x-default.
→ Chaque hreflang URL doit retourner 200 (tester avec curl -sI).

8.4. JSON-LD sur le blog :
```
curl -s "https://sos-expat.com/article/{slug}" | grep -oP '<script type="application/ld\+json">[^<]+</script>'
```
- Article schema : headline, author (Organization: SOS-Expat), publisher, datePublished, wordCount, inLanguage, speakable
- FAQPage schema : mainEntity avec Question/Answer
- BreadcrumbList
- Valider le JSON (pas de syntaxe cassée)

8.5. Structure HTML SEO d'un article :
```
curl -s "https://sos-expat.com/article/{slug}" | grep -oP '<h[1-6][^>]*>[^<]*</h[1-6]>'
```
- H1 unique = titre de l'article
- Hiérarchie correcte (H1 > H2 > H3, pas de saut H1 → H3)
- Canonical URL présente : `<link rel="canonical">`

8.6. API SEO interne :
- GET /api/content-gen/seo/dashboard
- GET /api/content-gen/seo/hreflang-matrix
- GET /api/content-gen/seo/orphaned (articles sans liens entrants)
- GET /api/content-gen/seo/internal-links-graph
- GET /api/content-gen/keywords/cannibalization

═══════════════════════════════════════════════════════════════
PHASE 9 — LIENS INTERNES & AFFILIÉS
═══════════════════════════════════════════════════════════════

Lire InternalLinkingService.php et AdvancedLinkingService.php.

9.1. Stats liens internes :
```sql
SELECT COUNT(*) as total, ROUND(AVG(relevance_score)::numeric, 2) as avg_score FROM internal_links;
```

9.2. Distribution des liens par article :
```sql
SELECT source_id, COUNT(*) as link_count FROM internal_links GROUP BY source_id ORDER BY link_count DESC LIMIT 10;
```
→ Attendu : 3-8 liens par article, max 2 par section H2.

9.3. Types d'ancres :
```sql
SELECT anchor_type, COUNT(*) FROM internal_links GROUP BY anchor_type;
```
→ Attendu : exact ~70%, partial ~15%, branded ~5%, conversational ~10%.

9.4. Liens cassés (pointant vers articles supprimés) :
```sql
SELECT il.id, il.source_id, il.target_id FROM internal_links il 
WHERE il.target_id NOT IN (SELECT id FROM generated_articles);
```

9.5. Liens affiliés :
```sql
SELECT COUNT(DISTINCT linkable_id) as articles_avec_affil, COUNT(*) as total_links 
FROM affiliate_links WHERE linkable_type LIKE '%GeneratedArticle%';
```
→ Max 2 par article.

9.6. Vérifier les URLs des liens affiliés (pas de 404) :
```sql
SELECT url, COUNT(*) FROM affiliate_links GROUP BY url ORDER BY COUNT(*) DESC LIMIT 10;
```
Tester les top URLs avec curl.

═══════════════════════════════════════════════════════════════
PHASE 10 — IMAGES & MÉDIAS
═══════════════════════════════════════════════════════════════

10.1. Articles publiés sans image :
```sql
SELECT COUNT(*) FROM generated_articles WHERE featured_image_url IS NULL AND status='published';
```

10.2. Vérifier que les URLs d'images sont accessibles — prendre 5 :
```sql
SELECT id, featured_image_url FROM generated_articles WHERE featured_image_url IS NOT NULL AND status='published' ORDER BY RANDOM() LIMIT 5;
```
Pour chaque : `curl -sI "URL" | grep HTTP` → 200.

10.3. Attributions Unsplash manquantes (obligation légale) :
```sql
SELECT id, featured_image_url FROM generated_articles 
WHERE featured_image_url LIKE '%unsplash%' 
AND (featured_image_attribution IS NULL OR photographer_name IS NULL)
AND status='published' LIMIT 10;
```

10.4. Images avec srcset (responsive) :
```sql
SELECT COUNT(*) as avec_srcset FROM generated_articles WHERE featured_image_srcset IS NOT NULL AND status='published';
SELECT COUNT(*) as sans_srcset FROM generated_articles WHERE featured_image_srcset IS NULL AND status='published';
```

═══════════════════════════════════════════════════════════════
PHASE 11 — NEWS RSS PIPELINE
═══════════════════════════════════════════════════════════════

11.1. Feeds RSS actifs :
```sql
SELECT id, name, url, is_active, last_fetched_at FROM rss_feeds WHERE is_active=true;
```

11.2. Items RSS par statut :
```sql
SELECT status, COUNT(*) FROM rss_feed_items GROUP BY status;
```

11.3. Items bloqués en "generating" > 30 min :
```sql
SELECT id, title, status, updated_at FROM rss_feed_items 
WHERE status='generating' AND updated_at < NOW() - INTERVAL '30 minutes';
```

11.4. Quota news du jour :
- Quota dans settings table (key='news_daily_quota')
- Articles news générés aujourd'hui vs quota
- GET /api/news/stats

═══════════════════════════════════════════════════════════════
PHASE 12 — STATISTIQUES & DATASETS
═══════════════════════════════════════════════════════════════

12.1. Sources de données :
- GET /api/content-gen/statistics-data/stats
- World Bank, OECD, Eurostat : data points, pays couverts, indicateurs

12.2. Datasets :
- GET /api/content-gen/statistics/stats
- GET /api/content-gen/statistics/themes
- Statuts : draft, validated, generating, published, failed

12.3. Couverture pays :
- GET /api/content-gen/statistics/coverage
- Pays/thèmes manquants

═══════════════════════════════════════════════════════════════
PHASE 13 — Q/R & QUESTION CLUSTERS
═══════════════════════════════════════════════════════════════

13.1. Pipeline Q/R :
- GET /api/content-gen/question-clusters/stats
- Auto-clustering des questions
- Génération Q/A + articles depuis clusters

13.2. Q/A publiés :
- GET /api/content-gen/qa
- Statuts, langues, pays couverts

═══════════════════════════════════════════════════════════════
PHASE 14 — FICHES PAYS (3 TYPES)
═══════════════════════════════════════════════════════════════

Pour chaque type (general, expatriation, vacances) :

14.1. Stats :
- GET /api/content-gen/fiches/{type}/stats → covered, total, progress

14.2. Pays manquants :
- GET /api/content-gen/fiches/{type}/missing

14.3. Articles existants :
- GET /api/content-gen/fiches/{type}/articles

14.4. Couverture : combien de pays sur 197 sont couverts ?

═══════════════════════════════════════════════════════════════
PHASE 15 — QUALITÉ, PLAGIAT & ANALYTICS
═══════════════════════════════════════════════════════════════

Lire les services dans app/Services/Quality/.

15.1. Scores par type de contenu :
```sql
SELECT content_type, COUNT(*), 
  ROUND(AVG(seo_score)::numeric, 1) as avg_seo, 
  ROUND(AVG(quality_score)::numeric, 1) as avg_quality, 
  ROUND(AVG(readability_score)::numeric, 1) as avg_readability,
  ROUND(AVG(word_count)::numeric, 0) as avg_words
FROM generated_articles WHERE status='published' GROUP BY content_type ORDER BY COUNT(*) DESC;
```

15.2. Articles avec SEO score faible (< 40) :
```sql
SELECT id, title, content_type, seo_score, quality_score 
FROM generated_articles WHERE status='published' AND seo_score < 40;
```

15.3. Doublons détectés :
- GET /api/quality/duplicates

15.4. Misclassifications de type :
- GET /api/quality/type-flags

15.5. Full audit sur 3 articles (si l'endpoint existe) :
- GET /api/quality/{article}/readability
- GET /api/quality/{article}/tone
- GET /api/quality/{article}/brand
- GET /api/quality/{article}/plagiarism
- GET /api/quality/{article}/fact-check

═══════════════════════════════════════════════════════════════
PHASE 16 — COÛTS API & MÉTRIQUES
═══════════════════════════════════════════════════════════════

16.1. Coûts par provider et modèle (7 derniers jours) :
```sql
SELECT provider, model, COUNT(*) as requests, 
  SUM(tokens_input) as total_in, SUM(tokens_output) as total_out, 
  SUM(cost_cents) as total_cost_cents
FROM api_costs WHERE created_at > NOW() - INTERVAL '7 days' 
GROUP BY provider, model ORDER BY total_cost_cents DESC;
```

16.2. Coût moyen par article et par type :
```sql
SELECT content_type, COUNT(*) as articles, 
  ROUND(AVG(generation_cost_cents)::numeric) as avg_cost_cents, 
  ROUND(AVG(generation_duration_seconds)::numeric) as avg_duration_s
FROM generated_articles WHERE created_at > NOW() - INTERVAL '7 days' 
GROUP BY content_type ORDER BY avg_cost_cents DESC;
```

16.3. Métriques quotidiennes :
```sql
SELECT * FROM content_metrics ORDER BY created_at DESC LIMIT 7;
```

16.4. API endpoints coûts :
- GET /api/content-gen/costs/overview
- GET /api/content-gen/costs/breakdown
- GET /api/content-gen/costs/trends

═══════════════════════════════════════════════════════════════
PHASE 17 — WORKERS & QUEUES (santé système)
═══════════════════════════════════════════════════════════════

17.1. Vérifier que TOUS les workers tournent :
```
ssh root@95.216.179.163 "docker ps --format '{{.Names}} {{.Status}}' | grep inf-"
```

17.2. Vérifier les queues Redis :
```
ssh root@95.216.179.163 "for q in default content publication email scraper content-scraper; do echo \"queue:$q = $(docker exec inf-redis redis-cli LLEN queues:$q)\"; done"
```

17.3. Jobs failed :
```
ssh root@95.216.179.163 "docker exec inf-app php artisan queue:failed | head -30"
```
Compter par type de job.

17.4. Logs d'erreur récents (24h) :
```
ssh root@95.216.179.163 "docker logs inf-content-worker --since 24h 2>&1 | grep -i 'error\|exception\|failed' | tail -20"
ssh root@95.216.179.163 "docker logs inf-publication-worker --since 24h 2>&1 | grep -i 'error\|exception\|failed' | tail -20"
ssh root@95.216.179.163 "docker logs inf-scheduler --since 24h 2>&1 | grep -i 'error\|exception\|failed' | tail -20"
```

17.5. Articles bloqués en "generating" > 1h :
```sql
SELECT id, title, status, updated_at FROM generated_articles 
WHERE status='generating' AND updated_at < NOW() - INTERVAL '1 hour';
```

17.6. Vérifier les configs workers (docker-compose.yml) :
- inf-queue : queue=default, sleep=3, tries=1, timeout=28800
- inf-content-worker : queue=content, sleep=3, tries=3, timeout=600, memory=512
- inf-content-scraper : queue=content-scraper, sleep=5, tries=1, memory=256, max-time=14400
- inf-publication-worker : queue=publication, sleep=5, tries=0, timeout=120, memory=256
- inf-email-worker : queue=email, sleep=5, tries=2, max-time=3600
- inf-scraper : queue=scraper, sleep=5, tries=1, max-jobs=50, max-time=3600

═══════════════════════════════════════════════════════════════
PHASE 18 — CAMPAGNES & CLUSTERING
═══════════════════════════════════════════════════════════════

18.1. Campagnes actives :
- GET /api/content-gen/campaigns
- Statuts (active, paused, completed, cancelled)
- Items par campagne

18.2. Topic clusters :
- GET /api/content-gen/clusters
- Articles piliers générés ?
- Satellites liés aux piliers (pillar_article_id) ?
```sql
SELECT pillar_article_id, COUNT(*) as satellites 
FROM generated_articles WHERE pillar_article_id IS NOT NULL 
GROUP BY pillar_article_id ORDER BY satellites DESC LIMIT 10;
```

═══════════════════════════════════════════════════════════════
PHASE 19 — QUALITÉ DES TITRES & SEARCH INTENT (SEO 2026)
═══════════════════════════════════════════════════════════════

CONTEXTE : En 2026, Google privilégie les contenus qui répondent EXACTEMENT à l'intention de recherche. Un bon titre doit : matcher un search intent réel, être cliquable dans les SERPs, contenir le mot-clé principal en début, et être formulé comme une requête naturelle.

19.1. AUDIT DES TITRES PAR TYPE DE CONTENU

Pour CHAQUE type, extraire 20 titres publiés :
```sql
SELECT id, title, content_type, language, country, 
  keywords_primary, seo_score,
  LENGTH(title) as title_len, LENGTH(meta_title) as meta_len
FROM generated_articles 
WHERE status='published' AND language='fr' AND content_type='{TYPE}'
ORDER BY created_at DESC LIMIT 20;
```

Critères à vérifier pour CHAQUE titre :

a) SEARCH INTENT MATCH :
- Le titre correspond-il à une vraie requête Google ?
- L'intent est-il clair (informationnel, transactionnel, local, urgence) ?
- Exemples MAUVAIS : "Guide complet pour expatriés" (trop vague)
- Exemples BONS : "Comment ouvrir un compte bancaire en Espagne en 2026"

b) FORMAT DU TITRE :
- Commence par le mot-clé principal (ou les 3 premiers mots) ?
- Contient le pays/ville quand applicable ?
- < 60 caractères (pas tronqué dans les SERPs) ?
- Pas de titre générique/IA détectable ("Guide ultime", "Tout savoir sur")
- Pas de mots vides en début ("Le", "La", "Les", "Un")

c) PATTERNS SEO 2026 attendus par type :

| Type | Intent attendu | Pattern titre attendu |
|------|---------------|----------------------|
| guide_city | informational + local | "Vivre à {Ville} : guide expatrié {Année}" |
| guide/pillar | informational | "Expatriation {Pays} : démarches, coût, visa {Année}" |
| article | informational | "{Sujet} pour expatriés : {bénéfice}" |
| comparative | commercial_investigation | "{A} vs {B} pour expatriés : comparatif {Année}" |
| qa | informational (featured snippet) | "Comment {action} quand on est expatrié en {Pays} ?" |
| testimonial | informational | "Témoignage : {prénom} en {Pays}" |
| qa_needs | informational (long-tail) | Question directe PAA format |
| tutorial | informational (how-to) | "Comment {action} en {Pays} : étapes {Année}" |
| statistics | informational | "Expatriation {Pays} : {X} chiffres clés {Année}" |
| pain_point | urgency | "{Problème urgent} en {Pays} : solutions" |
| news | informational (freshness) | "{Événement} : impact pour les expatriés en {Pays}" |
| outreach | transactional | "Devenir {rôle} SOS-Expat : {bénéfice}" |

Vérifier que ContentTypeConfig.php guide l'IA vers ces patterns.

19.2. DÉTECTION DES TITRES PROBLÉMATIQUES

a) Titres trop longs (tronqués dans Google) :
```sql
SELECT id, title, LENGTH(title) as len, content_type 
FROM generated_articles WHERE status='published' AND LENGTH(title) > 60
ORDER BY LENGTH(title) DESC LIMIT 20;
```

b) Titres trop courts (pas assez descriptifs) :
```sql
SELECT id, title, LENGTH(title) as len, content_type 
FROM generated_articles WHERE status='published' AND LENGTH(title) < 25
ORDER BY LENGTH(title) ASC LIMIT 20;
```

c) Titres génériques/IA détectables :
```sql
SELECT id, title, content_type FROM generated_articles 
WHERE status='published' AND (
  title ILIKE '%guide ultime%' OR title ILIKE '%guide complet%'
  OR title ILIKE '%tout savoir%' OR title ILIKE '%tout ce que vous%'
  OR title ILIKE '%les secrets%' OR title ILIKE '%ne manquez pas%'
  OR title ILIKE '%incontournable%' OR title ILIKE '%indispensable%'
  OR title ILIKE '%top %' OR title ILIKE '%meilleur guide%'
  OR title ILIKE '%vous devez savoir%' OR title ILIKE '%découvrez%'
  OR title ILIKE '%révélé%' OR title ILIKE '%ultime%'
  OR title ILIKE '%n°1%' OR title ILIKE '%incroyable%'
);
```

d) Titres sans localisation (pays/ville manquant quand requis) :
```sql
SELECT id, title, content_type, country FROM generated_articles 
WHERE status='published' 
AND content_type IN ('guide_city','guide','tutorial','statistics','pain_point')
AND country IS NOT NULL AND title NOT ILIKE '%' || country || '%'
LIMIT 20;
```

e) Titres dupliqués :
```sql
SELECT title, COUNT(*) as cnt FROM generated_articles 
WHERE status='published' GROUP BY title HAVING COUNT(*) > 1;
```

f) Meta titles incohérents avec le titre :
```sql
SELECT id, LEFT(title, 60) as title, LEFT(meta_title, 60) as meta_title 
FROM generated_articles WHERE status='published' AND meta_title IS NOT NULL
AND meta_title NOT ILIKE '%' || LEFT(title, 20) || '%'
LIMIT 20;
```

19.3. TITRES MULTILINGUES

Vérifier que les titres traduits :
- Sont réellement traduits (pas identiques au FR)
- Conservent le search intent original
- Respectent < 60 chars dans chaque langue

```sql
SELECT a.id, a.title as titre_fr, t.language, t.title as titre_traduit,
  LENGTH(t.title) as len_traduit, (a.title = t.title) as identique_au_fr
FROM generated_articles a
JOIN generated_articles t ON t.parent_article_id = a.id
WHERE a.status='published' AND a.language='fr'
AND (t.title = a.title OR LENGTH(t.title) > 65)
LIMIT 30;
```

19.4. TITLE VARIANTS (A/B testing)

Si le système génère des variantes (Phase 07) :
```sql
SELECT id, title, title_variants FROM generated_articles 
WHERE title_variants IS NOT NULL AND status='published' LIMIT 10;
```
- Combien de variantes ? Sont-elles réellement différentes ?

19.5. VÉRIFICATION SUR GOOGLE (échantillon)

Prendre 5 titres publiés et vérifier :
- Le titre apparaît dans Google ? (site:sos-expat.com "{titre}")
- Google l'a-t-il réécrit ?
- La meta description s'affiche correctement ?
- Rich snippets FAQ visibles ?

═══════════════════════════════════════════════════════════════
PHASE 20 — DASHBOARD FRONTEND
═══════════════════════════════════════════════════════════════

20.1. Vérifier que les 19 pages de contenu ont les 3 sous-onglets uniformes (📋 Sources / ⚡ Génération / ✅ Contenus générés) :

Composants à vérifier :
- GenerateQr.tsx → /content/generate-qr
- NewsHub.tsx → /content/news
- FichesPays.tsx type=general → /content/fiches-general
- FichesPays.tsx type=expatriation → /content/fiches-expatriation
- FichesPays.tsx type=vacances → /content/fiches-vacances
- ContentGenerator.tsx type=guide-city → /content/fiches-villes
- ContentGenerator.tsx type=chatters → /content/chatters
- ContentGenerator.tsx type=influenceurs → /content/influenceurs
- ContentGenerator.tsx type=admin-groupes → /content/admin-groupes
- ContentGenerator.tsx type=avocats → /content/avocats
- ContentGenerator.tsx type=expats-aidants → /content/expats-aidants
- ContentGenerator.tsx type=tutorial → /content/tutoriels
- ContentGenerator.tsx type=testimonial → /content/temoignages
- ContentGenerator.tsx type=pain-point → /content/souffrances
- ArtMotsCles.tsx → /content/art-mots-cles
- ArtLonguesTraines.tsx → /content/longues-traines
- ArticlesList.tsx → /content/articles
- ComparativesList.tsx → /content/comparatives
- ArtStatistiques.tsx → /content/statistiques

20.2. Vérifier qu'aucun élément visuel ne ressemble à des sous-onglets parasites (boutons pilules de filtres, etc.).

20.3. Vérifier que chaque appel API frontend correspond à un endpoint backend existant.

20.4. Pages complémentaires à vérifier :
- ContentOrchestrator.tsx → config auto-pilot
- ContentCommandCenter.tsx → déclenchement sources
- PublishingDashboard.tsx → queue publication
- TranslationsDashboard.tsx → batches traduction
- SeoDashboard.tsx → métriques SEO
- CostsDashboard.tsx → suivi coûts API
- QualityMonitoring.tsx → qualité articles
- DailyScheduler.tsx → planification quotidienne

═══════════════════════════════════════════════════════════════
PHASE 21 — TESTS CROISÉS & COHÉRENCE
═══════════════════════════════════════════════════════════════

21.1. MC published ↔ Blog :
- Articles published dans MC avec external_url → existent sur le blog ? (curl)
- Articles sur le blog non référencés dans MC (orphelins)

21.2. Items used ↔ Articles :
- Items "used" sans article correspondant (génération échouée silencieusement)
- Items "processing" bloqués > 2h

21.3. Traductions :
- Articles published sans les 8 traductions attendues
- Traductions avec titre identique au FR
- Traductions avec slug non-ASCII

21.4. JSON-LD :
- Articles published avec json_ld NULL ou vide
- json_ld mal formé (pas du JSON valide)
```sql
SELECT id, title FROM generated_articles WHERE status='published' 
AND json_ld IS NOT NULL AND json_ld NOT LIKE '{%';
```

21.5. Images :
- Articles published sans featured_image_url
- featured_image_url qui retourne 404

21.6. Liens :
- Articles published avec 0 liens internes
```sql
SELECT a.id, a.title FROM generated_articles a 
WHERE a.status='published' AND a.parent_article_id IS NULL
AND NOT EXISTS (SELECT 1 FROM internal_links il WHERE il.source_id=a.id)
LIMIT 20;
```
- Liens internes pointant vers articles supprimés

21.7. FAQ :
- Types avec faq_count > 0 dans ContentTypeConfig mais articles publiés avec 0 FAQ
```sql
SELECT a.id, a.title, a.content_type,
  (SELECT COUNT(*) FROM generated_article_faqs WHERE article_id=a.id) as faq_count
FROM generated_articles a WHERE a.status='published' AND a.parent_article_id IS NULL
AND a.content_type IN ('guide_city','guide','article','comparative','testimonial','tutorial','statistics','pain_point','news','affiliation')
HAVING (SELECT COUNT(*) FROM generated_article_faqs WHERE article_id=a.id) = 0
LIMIT 20;
```

21.8. Publication :
- PublicationQueueItems avec status=failed et attempts >= 5
- Articles status='published' mais aucun PublicationQueueItem

═══════════════════════════════════════════════════════════════
PHASE 22 — TESTS API RÉELS (TOUS LES ENDPOINTS)
═══════════════════════════════════════════════════════════════

Tester CHAQUE endpoint via curl depuis le VPS. Vérifier : status 200, JSON valide, données cohérentes.

Generation Sources :
- GET /api/generation-sources/categories
- GET /api/generation-sources/stats
- GET /api/generation-sources/{slug}/items (tester 5 slugs)

Orchestrateur :
- GET /api/content/orchestrator/config
- GET /api/content/orchestrator/logs
- GET /api/content/orchestrator/daily-plan
- GET /api/content/orchestrator/alerts
- GET /api/content/scheduler/today

Articles :
- GET /api/content-gen/articles?status=published&per_page=5
- GET /api/content-gen/articles/{id}

Fiches :
- GET /api/content-gen/fiches/general/stats
- GET /api/content-gen/fiches/expatriation/stats
- GET /api/content-gen/fiches/vacances/stats

News :
- GET /api/news/stats
- GET /api/news/feeds
- GET /api/news/items?status=pending

Traductions :
- GET /api/content-gen/translations/overview

SEO :
- GET /api/content-gen/seo/dashboard
- GET /api/content-gen/seo/orphaned
- GET /api/content-gen/seo/hreflang-matrix
- GET /api/content-gen/seo/internal-links-graph

Keywords :
- GET /api/content-gen/keywords?type=art_mots_cles
- GET /api/content-gen/keywords?type=long_tail
- GET /api/content-gen/keywords/gaps
- GET /api/content-gen/keywords/cannibalization

Publication :
- GET /api/content-gen/publishing/endpoints
- GET /api/content-gen/publishing/queue?status=failed
- GET /api/content-gen/publication-stats

Qualité :
- GET /api/quality/dashboard
- GET /api/quality/duplicates
- GET /api/quality/type-flags

Statistiques :
- GET /api/content-gen/statistics/stats
- GET /api/content-gen/statistics/themes
- GET /api/content-gen/statistics/coverage
- GET /api/content-gen/statistics-data/stats
- GET /api/content-gen/statistics-data/coverage

Coûts :
- GET /api/content-gen/costs/overview
- GET /api/content-gen/costs/breakdown
- GET /api/content-gen/costs/trends

Comparatifs :
- GET /api/content-gen/comparatives

Q/R :
- GET /api/content-gen/qa
- GET /api/content-gen/question-clusters/stats

Campagnes :
- GET /api/content-gen/campaigns

Clusters :
- GET /api/content-gen/clusters

Pour chaque endpoint : noter le status HTTP, si le JSON est valide, et si les données sont cohérentes.

═══════════════════════════════════════════════════════════════
PHASE 23 — RAPPORT FINAL
═══════════════════════════════════════════════════════════════

23.1. TABLEAU DE BORD par type de contenu :
| Type | Config IA OK | Items total | Ready | Used | Articles FR | Traductions (sur 8) | Publiés blog | SEO moyen | Qualité moy | FAQ OK | Images OK | Liens internes | Titres OK | Erreurs |

23.2. TABLEAU DE BORD par langue :
| Langue | Articles totaux | Publiés | Slugs ASCII | Meta OK | Hreflang OK | Word count moyen | Titres < 60 chars |

23.3. TABLEAU DE BORD système :
| Worker | Docker status | Queue length | Failed jobs | Erreurs 24h |

23.4. TABLEAU DE BORD publication :
| Endpoint | Type | Active | Queue pending | Published today | Failed | Last error |

23.5. TABLEAU DE BORD coûts :
| Provider | Model | Requests 7j | Tokens in | Tokens out | Coût total | Coût/article moyen |

23.6. TABLEAU DE BORD titres & search intent :
| Type | Nb titres | Moy chars | > 60 chars | < 25 chars | Génériques IA | Sans pays | Dupliqués | Search intent OK | Note /10 |

23.7. LISTE DES BUGS classés par sévérité :

CRITIQUE (bloquant, à corriger immédiatement) :
- Pipeline cassé, workers down, publications échouées en masse, erreurs 500

HAUTE (impact SEO/utilisateur) :
- Traductions manquantes, slugs non-ASCII, SEO cassé, images 404, JSON-LD invalide
- Titres génériques IA, search intent incorrect, hreflang cassés

MOYENNE (incohérences) :
- Quotas mal configurés, liens orphelins, FAQ manquantes
- Titres trop longs, meta descriptions absentes

BASSE (optimisation) :
- Cosmétique UI, suggestions d'amélioration, optimisations possibles

23.8. ACTIONS CORRECTIVES :
Pour chaque bug : fichier + ligne + correction proposée + priorité.

23.9. SCORE GLOBAL : X/100

Grille de notation :
- Pipeline fonctionnel (workers, queues, génération) : /20
- Qualité des articles (contenu, word count, scores) : /15
- SEO technique (meta, hreflang, JSON-LD, sitemap, canonical) : /15
- Traductions (complétude, qualité, slugs) : /10
- Publication (blog live, 200 OK, contenu visible) : /10
- Titres & Search Intent (patterns, localisation, pas de IA générique) : /10
- Images (présence, accessibilité, attributions) : /5
- Liens (internes, affiliés, pas de cassés) : /5
- Dashboard UI (uniformité, pas de bugs visuels) : /5
- Monitoring (coûts, erreurs, alertes) : /5

═══════════════════════════════════════════════════════════════
INSTRUCTIONS CRITIQUES
═══════════════════════════════════════════════════════════════

- EXÉCUTE des commandes réelles — curl, docker exec, requêtes DB via tinker
- VÉRIFIE en production (VPS 95.216.179.163), pas seulement le code
- CROISE systématiquement : code ↔ base de données ↔ blog live ↔ API responses
- TESTE les langues non-latines (AR, ZH, RU, HI) EN PRIORITÉ
- Vérifie les cas edge : articles sans image, sans FAQ, sans traduction, sans liens
- NE CORRIGE RIEN — diagnostic et rapport uniquement
- Communique en français
- Sois EXHAUSTIF : chaque phase doit avoir des résultats concrets avec données chiffrées
- Chaque bug trouvé doit avoir : description, impact, fichier/ligne, correction proposée
