# PROMPT : Pipeline de Génération de Contenu à partir d'Articles Scrappés

> **Contexte** : Nous avons un système de scraping qui collecte des milliers d'articles depuis des sites comme expat.com (organisés par source → continent → pays → articles). L'objectif est de transformer ces articles scrappés en contenu original de qualité supérieure, parfaitement optimisé SEO/AEO, avec un suivi complet des mots-clés et un maillage interne intelligent. Les articles sont générés EN FRANCAIS UNIQUEMENT. Les traductions sont lancées MANUELLEMENT par l'admin, langue par langue, quand il le décide. Le système inclut aussi un module Q&A dédié pour capturer les PAA Google et le voice search.

---

## 1. DONNEES EXISTANTES (ce qu'on a déjà)

### Tables existantes dans PostgreSQL
```
content_sources         — Sources de scraping (expat.com, etc.)
content_countries       — ~219 pays par source, avec continent
content_articles        — ~5000-10000 articles scrappés par source
  → title, slug, url, content_text, content_html, word_count
  → category (visa, logement, sante, emploi, transport, education, banque, culture, demarches, telecom)
  → country_id, language, meta_title, meta_description, images[], external_links[]
  → is_guide, section (guide/magazine/services/thematic/cities)
content_external_links  — Liens externes extraits, classifiés
  → url, domain, anchor_text, context, link_type (official/news/resource/service)
  → is_affiliate, occurrences
content_businesses      — Annuaires d'entreprises scrappés
content_contacts        — Contacts scrappés
```

### Modèles Eloquent existants
```php
ContentSource    → hasMany(ContentCountry), hasMany(ContentArticle), hasMany(ContentExternalLink)
ContentCountry   → belongsTo(ContentSource), hasMany(ContentArticle)
ContentArticle   → belongsTo(ContentSource), belongsTo(ContentCountry), hasMany(ContentExternalLink)
ContentExternalLink → belongsTo(ContentSource), belongsTo(ContentArticle), belongsTo(ContentCountry)
```

### Articles scrappés : structure type
```json
{
  "id": 1234,
  "source_id": 1,
  "country_id": 45,
  "title": "Les formalités pour obtenir un visa en Allemagne",
  "category": "visa",
  "content_text": "Pour s'installer en Allemagne, les ressortissants...",
  "content_html": "<p>Pour s'installer en Allemagne...</p>",
  "word_count": 1200,
  "language": "fr",
  "meta_title": "Visa Allemagne : guide complet des formalités",
  "meta_description": "Tout savoir sur le visa pour l'Allemagne...",
  "images": [{"url": "...", "alt": "..."}],
  "external_links": [{"url": "...", "domain": "...", "type": "official"}],
  "is_guide": true,
  "section": "guide",
  "scraped_at": "2026-03-25"
}
```

---

## 2. PIPELINE COMPLET : SCRAPED → CLUSTERED → GENERATED → PUBLISHED

```
┌─────────────────────────────────────────────────────────────────────┐
│                    PHASE 1 : CLUSTERING                             │
│                                                                     │
│  Articles scrappés    →   Grouper par {pays + catégorie + sujet}   │
│  (content_articles)       Détecter sujets similaires               │
│                           Marquer comme "clustered"                 │
│                                                                     │
│  Résultat: TopicCluster = {pays, catégorie, sujet,                 │
│            source_articles[3-10], mots-clés détectés}              │
└─────────────────────┬───────────────────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    PHASE 2 : RECHERCHE & ENRICHISSEMENT             │
│                                                                     │
│  Pour chaque cluster :                                              │
│  1. Extraire les faits clés de chaque article source               │
│  2. Recherche Perplexity : données récentes, stats, lois           │
│  3. Identifier les lacunes (ce que les sources ne couvrent pas)    │
│  4. Collecter mots-clés + longue traîne (via IA)                  │
│  5. Identifier les PAA (People Also Ask) pour le sujet             │
│                                                                     │
│  Résultat: ResearchBrief = {facts[], sources[], gaps[],            │
│            keywords[], long_tail[], paa_questions[]}               │
└─────────────────────┬───────────────────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    PHASE 3 : GENERATION IA                          │
│                                                                     │
│  GPT-4o génère l'article en utilisant :                            │
│  - Les faits extraits des articles sources (JAMAIS de copie)       │
│  - Les données récentes de Perplexity                              │
│  - Les mots-clés + longue traîne à placer naturellement           │
│  - Les PAA comme base pour les FAQ                                 │
│  - Le brief de recherche complet                                   │
│                                                                     │
│  Structure générée :                                                │
│  - H1 unique (titre optimisé SEO)                                  │
│  - Introduction avec hook + mot-clé principal                      │
│  - 6-10 sections H2 (chacune avec H3/H4 si nécessaire)           │
│  - Tableaux de données quand pertinent                             │
│  - Listes à puces pour lisibilité                                  │
│  - FAQ (8-12 questions basées sur PAA + sujet)                     │
│  - Conclusion avec CTA                                             │
│  - Longueur : 2500-4000 mots                                      │
└─────────────────────┬───────────────────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    PHASE 4 : OPTIMISATION SEO COMPLETE              │
│                                                                     │
│  4.1 On-Page SEO                                                   │
│  - Title tag (<60 chars, mot-clé en début)                         │
│  - Meta description (<160 chars, CTA, mot-clé)                     │
│  - URL/slug localisé par langue                                    │
│  - Canonical URL                                                    │
│  - 1 seul H1, hiérarchie H2→H3→H4 stricte                        │
│  - Mot-clé principal dans: H1, premier paragraphe, un H2, alt img │
│  - Densité mots-clés : 1-2% principal, 0.5-1% secondaires        │
│  - Mots sémantiques LSI intégrés naturellement                    │
│                                                                     │
│  4.2 Structured Data (JSON-LD)                                     │
│  - @type: Article (ou HowTo, FAQPage, etc.)                       │
│  - BreadcrumbList (fil d'Ariane)                                   │
│  - FAQPage (pour les questions générées)                           │
│  - Speakable (pour AEO/voice search)                               │
│  - author avec sameAs (E-E-A-T)                                   │
│  - Organization (publisher)                                         │
│  - datePublished, dateModified                                     │
│  - image, mainEntityOfPage                                         │
│                                                                     │
│  4.3 Featured Snippets Optimization                                │
│  - Paragraphe de définition (40-60 mots) après le H1              │
│  - Tableaux structurés pour les comparaisons                       │
│  - Listes numérotées pour les étapes/processus                    │
│  - "What is" / "How to" pattern dans les H2                       │
│                                                                     │
│  4.4 E-E-A-T Signals                                               │
│  - Author box avec expertise (JSON-LD Person)                      │
│  - Sources citées (liens vers .gov, .org, études)                  │
│  - Date de publication + date de mise à jour                       │
│  - Mention "Vérifié par" ou "Mis à jour le"                       │
│  - Liens vers sources officielles (consulats, gov)                 │
│  - Pas de claims sans source                                       │
│                                                                     │
│  4.5 Rich Results                                                   │
│  - FAQ Schema (FAQPage)                                            │
│  - HowTo Schema (pour les guides étape par étape)                 │
│  - BreadcrumbList Schema                                           │
│  - Article Schema complet                                          │
│  - Speakable Schema (pour Google Assistant)                        │
│  - ItemList (pour les listes/classements)                          │
│                                                                     │
│  4.6 AEO (Answer Engine Optimization)                              │
│  - Réponses directes en 40-60 mots (featured snippet format)      │
│  - Questions en H2/H3 (format conversationnel)                    │
│  - Phrases complètes (pas de fragments)                            │
│  - Speakable markup sur les réponses clés                         │
│  - Format "Question → Réponse concise → Détails"                  │
│  - Optimisation pour PAA (People Also Ask)                         │
└─────────────────────┬───────────────────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    PHASE 5 : MAILLAGE & LIENS                       │
│                                                                     │
│  5.1 Liens internes (par langue + sujet)                           │
│  - Chercher les articles PUBLIES dans la MEME LANGUE              │
│  - Scorer la pertinence : même pays (+3), même catégorie (+2),    │
│    même cluster (+2), mots-clés communs (+1 par match)            │
│  - Insérer 5-8 liens internes avec ancres variées                 │
│  - Ne JAMAIS lier vers un article dans une autre langue            │
│  - Priorité : articles pilier > articles du même pays > même cat  │
│                                                                     │
│  5.2 Liens externes (depuis notre liste scrappée)                  │
│  - Chercher dans content_external_links les liens pertinents      │
│  - Filtrer : même pays, même catégorie, link_type = official/news │
│  - Prioriser : .gov/.gouv (confiance max), .org, news reconnus   │
│  - Insérer 3-6 liens externes de haute autorité                   │
│  - Ajouter rel="nofollow" sur les liens commerciaux               │
│  - Ne PAS inclure de liens affiliés                                │
│                                                                     │
│  5.3 Liens affiliés SOS-Expat                                     │
│  - Insérer 1-2 liens vers les services SOS-Expat pertinents      │
│  - Contextualiser : "Besoin d'aide ? Consultez un avocat expat"   │
│  - Tracking UTM : ?utm_source=blog&utm_medium=article&utm_camp=.. │
└─────────────────────┬───────────────────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    PHASE 6 : Q&A (Questions & Réponses)             │
│                                                                     │
│  Module dédié de pages Q&A individuelles pour le SEO :             │
│                                                                     │
│  6.1 Sources des questions :                                        │
│  - FAQ générées dans les articles (8-12 par article)               │
│  - PAA Google détectées via Perplexity                             │
│  - Questions extraites des articles scrappés                       │
│  - Suggestions IA (basées sur sujet + pays)                       │
│  - Questions ajoutées manuellement par l'admin                    │
│                                                                     │
│  6.2 Structure d'une page Q&A :                                    │
│  URL: /{lang}/qa/{country-slug}/{question-slug}                    │
│  - H1 = La question exacte                                         │
│  - Réponse directe (40-60 mots, bold, featured snippet ready)     │
│  - Réponse détaillée (500-1500 mots, H2/H3 structurés)           │
│  - Sources officielles citées                                      │
│  - Questions connexes (liens vers d'autres Q&A du même sujet)     │
│  - Lien vers l'article principal du sujet                         │
│  - Schema QAPage + BreadcrumbList + Speakable                     │
│  - Signaux E-E-A-T (auteur, date, sources)                       │
│                                                                     │
│  6.3 Génération automatique :                                      │
│  - Après génération d'un article, ses FAQ sont candidates Q&A     │
│  - L'admin sélectionne lesquelles transformer en pages Q&A        │
│  - Ou lance "Auto-generate Q&A" pour tout un pays/catégorie       │
│  - Chaque Q&A est liée à son article parent                      │
│  - Les Q&A alimentent le maillage interne (article ↔ Q&A ↔ Q&A)  │
│                                                                     │
│  6.4 Optimisation SEO/AEO des Q&A :                               │
│  - Featured snippet : réponse directe en format paragraphe court   │
│  - Voice search : phrases complètes, ton conversationnel          │
│  - Speakable markup sur la réponse directe                        │
│  - PAA targeting : question = exactement la PAA Google             │
│  - Longue traîne : chaque Q&A cible une longue traîne spécifique │
│  - Maillage : Q&A → article parent + Q&A connexes même langue    │
└─────────────────────┬───────────────────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────────────────┐
│               PHASE 7 : TRADUCTION MANUELLE PAR LANGUE              │
│                                                                     │
│  IMPORTANT : PAS de traduction automatique à la génération.        │
│  L'admin contrôle quand et quelle langue lancer.                   │
│                                                                     │
│  7.1 Workflow :                                                     │
│  - Les articles sont générés EN FRANCAIS UNIQUEMENT                │
│  - L'admin va dans l'onglet "Traductions"                         │
│  - Il voit : FR (247 articles), EN (0/247), DE (0/247), ES...     │
│  - Il clique "Lancer la traduction EN"                            │
│  - Le système dispatche les jobs progressivement (queue Redis)    │
│  - Progression temps réel : 0/247 → 23/247 → ... → 247/247       │
│  - Pause / Reprendre possible à tout moment                       │
│  - Chaque langue est indépendante                                  │
│                                                                     │
│  7.2 Traduction inclut :                                           │
│  - Articles (generated_articles)                                    │
│  - Q&A (qa_entries)                                                │
│  - Traduction NATIVE (pas mot à mot) via GPT-4o                  │
│  - Adaptation culturelle (exemples locaux, expressions)           │
│  - Slug localisé par langue                                       │
│  - Mots-clés adaptés à la langue cible                            │
│  - Maillage interne dans la langue cible                          │
│  - Hreflang bidirectionnel (activé seulement quand traduit)       │
│  - Canonical par langue                                            │
│  - x-default vers la version anglaise                             │
│                                                                     │
│  7.3 Table translation_batches :                                   │
│  - Suit la progression de chaque batch de traduction              │
│  - target_language, total_items, completed, failed, status        │
│  - Permet pause/resume/cancel                                      │
│                                                                     │
│  Langues supportées : FR (source), EN, DE, ES, PT, RU, ZH, AR, HI│
└─────────────────────┬───────────────────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    PHASE 8 : SUIVI & TRACKING                       │
│                                                                     │
│  7.1 Suivi des articles sources                                    │
│  - Marquer chaque content_article utilisé comme "processed"        │
│  - Stocker le lien : source_article → generated_article            │
│  - Empêcher la réutilisation (pas de doublons)                    │
│  - Dashboard : articles traités vs non traités par pays/catégorie  │
│                                                                     │
│  7.2 Suivi des mots-clés                                          │
│  Table keyword_tracking :                                          │
│  - keyword (le mot-clé)                                            │
│  - type : 'primary' | 'secondary' | 'long_tail' | 'lsi' | 'paa' │
│  - language : 'fr', 'en', etc.                                    │
│  - search_volume_estimate (optionnel)                              │
│  - difficulty_estimate (optionnel)                                  │
│  - articles_count (combien d'articles l'utilisent)                 │
│                                                                     │
│  Table article_keywords (pivot) :                                  │
│  - article_id → generated_articles                                 │
│  - keyword_id → keyword_tracking                                   │
│  - usage_type : 'h1' | 'h2' | 'content' | 'meta_title' |         │
│                 'meta_description' | 'alt_text' | 'anchor'         │
│  - density_percent                                                  │
│  - position (dans quel H2/paragraphe)                              │
│                                                                     │
│  7.3 Dashboard mots-clés                                           │
│  - Mots-clés principaux les plus utilisés                          │
│  - Longues traînes couvertes vs manquantes                        │
│  - Distribution par catégorie et par langue                        │
│  - Mots-clés orphelins (identifiés mais pas encore couverts)      │
│  - Cannibalization check (2+ articles sur le même mot-clé)        │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 3. NOUVELLES TABLES A CREER

### 3.1 `topic_clusters` — Regroupement d'articles par sujet
```sql
id, name, slug, country (string 100), category (string 50),
language (string 5, default 'fr'),
description (text nullable),
source_articles_count (integer default 0),
status (string 20 default 'pending') — pending, ready, generating, generated, archived
keywords_detected (jsonb nullable) — mots-clés auto-détectés du cluster
generated_article_id (FK nullable → generated_articles) — l'article généré à partir de ce cluster
created_by (FK nullable → users), timestamps
Index: country+category, status, generated_article_id
```

### 3.2 `topic_cluster_articles` — Pivot cluster ↔ articles sources
```sql
id, cluster_id (FK → topic_clusters cascade),
source_article_id (FK → content_articles cascade),
relevance_score (integer default 50) — 0-100
is_primary (boolean default false) — article principal du cluster
processing_status (string 20 default 'pending') — pending, extracted, used
extracted_facts (jsonb nullable) — faits extraits de cet article
timestamps
Index: cluster_id, source_article_id unique composite
```

### 3.3 `keyword_tracking` — Suivi global des mots-clés
```sql
id, keyword (string 300),
type (string 20) — primary, secondary, long_tail, lsi, paa, semantic
language (string 5),
country (string 100 nullable) — si spécifique à un pays
category (string 50 nullable),
search_volume_estimate (integer nullable),
difficulty_estimate (integer nullable) — 0-100
trend (string 20 nullable) — rising, stable, declining
articles_using_count (integer default 0) — cache dénormalisé
first_used_at (timestamp nullable),
timestamps
Index: keyword+language unique, type, language, country, category, articles_using_count
```

### 3.4 `article_keywords` — Pivot article ↔ mots-clés
```sql
id, article_id (FK → generated_articles cascade),
keyword_id (FK → keyword_tracking cascade),
usage_type (string 30) — h1, h2, h3, content, meta_title, meta_description, alt_text, anchor, faq_question, faq_answer
density_percent (decimal 5,2 nullable),
occurrences (integer default 1),
position_context (string 200 nullable) — "H2: Visa et permis de séjour"
timestamps
Index: article_id+keyword_id unique, keyword_id, usage_type
```

### 3.5 `research_briefs` — Brief de recherche par cluster
```sql
id, cluster_id (FK → topic_clusters cascade),
perplexity_response (longText nullable) — raw response
extracted_facts (jsonb nullable) — faits structurés
recent_data (jsonb nullable) — données récentes trouvées
identified_gaps (jsonb nullable) — lacunes des sources
paa_questions (jsonb nullable) — People Also Ask
suggested_keywords (jsonb nullable) — {primary: [], secondary: [], long_tail: [], lsi: []}
suggested_structure (jsonb nullable) — plan d'article suggéré
tokens_used (integer default 0),
cost_cents (integer default 0),
timestamps
Index: cluster_id
```

### 3.6 Modifier `content_articles` (existant) — Ajouter colonnes de tracking
```sql
ALTER TABLE content_articles ADD COLUMN:
  processing_status (string 20 default 'unprocessed') — unprocessed, clustered, extracted, used, skipped
  processed_at (timestamp nullable)
  quality_rating (integer nullable) — 0-100 score de qualité de l'article source
  cluster_id (FK nullable → topic_clusters) — dans quel cluster cet article a été mis
Index: processing_status, cluster_id
```

### 3.7 `seo_checklists` — Checklist SEO détaillée par article
```sql
id, article_id (FK → generated_articles cascade),
-- On-Page
has_single_h1 (boolean default false),
h1_contains_keyword (boolean default false),
title_tag_length (integer nullable),
title_tag_contains_keyword (boolean default false),
meta_desc_length (integer nullable),
meta_desc_contains_cta (boolean default false),
keyword_in_first_paragraph (boolean default false),
keyword_density_ok (boolean default false),
heading_hierarchy_valid (boolean default false),
has_table_or_list (boolean default false),
-- Structured Data
has_article_schema (boolean default false),
has_faq_schema (boolean default false),
has_breadcrumb_schema (boolean default false),
has_speakable_schema (boolean default false),
has_howto_schema (boolean default false),
json_ld_valid (boolean default false),
-- E-E-A-T
has_author_box (boolean default false),
has_sources_cited (boolean default false),
has_date_published (boolean default false),
has_date_modified (boolean default false),
has_official_links (boolean default false),
-- Links
internal_links_count (integer default 0),
external_links_count (integer default 0),
official_links_count (integer default 0),
broken_links_count (integer default 0),
-- Featured Snippets
has_definition_paragraph (boolean default false),
has_numbered_steps (boolean default false),
has_comparison_table (boolean default false),
-- AEO
has_speakable_content (boolean default false),
has_direct_answers (boolean default false),
paa_questions_covered (integer default 0),
-- Images
all_images_have_alt (boolean default false),
featured_image_has_keyword (boolean default false),
images_count (integer default 0),
-- Translation
hreflang_complete (boolean default false),
translations_count (integer default 0),
-- Scores
overall_checklist_score (integer default 0) — pourcentage de checks passés
timestamps
Index: article_id unique, overall_checklist_score
```

### 3.8 `qa_entries` — Pages Q&A individuelles
```sql
id, uuid (uuid unique),
parent_article_id (FK nullable → generated_articles, nullOnDelete) — article dont cette Q&A est issue
cluster_id (FK nullable → topic_clusters, nullOnDelete),
question (text) — la question exacte (= H1 de la page)
answer_short (text) — réponse directe 40-60 mots (featured snippet)
answer_detailed_html (longText nullable) — réponse détaillée 500-1500 mots (HTML)
language (string 5, default 'fr'),
country (string 100 nullable),
category (string 50 nullable),
slug (string 300),
meta_title (string 70 nullable),
meta_description (string 170 nullable),
canonical_url (string 1000 nullable),
json_ld (jsonb nullable) — QAPage + BreadcrumbList + Speakable
hreflang_map (jsonb nullable),
keywords_primary (string 200 nullable),
keywords_secondary (jsonb nullable),
seo_score (integer default 0),
word_count (integer default 0),
source_type (string 30 default 'article_faq') — article_faq, paa, scraped, manual, ai_suggested
status (string 20 default 'draft') — draft, generating, review, published, archived
generation_cost_cents (integer default 0),
parent_qa_id (FK nullable → qa_entries, self-ref for translations, nullOnDelete),
related_qa_ids (jsonb nullable) — IDs des Q&A connexes
published_at (timestamp nullable),
created_by (FK nullable → users, nullOnDelete),
timestamps, softDeletes
Index: slug+language (unique), parent_article_id, country+category, status, source_type, language, parent_qa_id
```

### 3.9 `translation_batches` — Suivi des batches de traduction
```sql
id, target_language (string 5) — en, de, es, pt, ru, zh, ar, hi
content_type (string 30 default 'article') — article, qa, all
status (string 20 default 'pending') — pending, running, paused, completed, cancelled, failed
total_items (integer default 0),
completed_items (integer default 0),
failed_items (integer default 0),
skipped_items (integer default 0),
total_cost_cents (integer default 0),
current_item_id (unsignedBigInteger nullable) — l'item en cours de traduction
error_log (jsonb nullable) — [{item_id, error, timestamp}]
started_at (timestamp nullable),
paused_at (timestamp nullable),
completed_at (timestamp nullable),
created_by (FK nullable → users, nullOnDelete),
timestamps
Index: target_language+content_type, status
```

---

## 4. NOUVEAUX SERVICES

### 4.1 `TopicClusteringService` — Clustering intelligent des articles
```
Méthodes:
  clusterByCountryAndCategory(string $country, string $category): Collection<TopicCluster>
    — Prend tous les content_articles non traités pour un pays+catégorie
    — Utilise la similarité de titre + contenu pour grouper
    — Calcule un score de similarité (Jaccard sur les mots-clés)
    — Crée les TopicCluster + TopicClusterArticle records
    — Marque les articles sources comme "clustered"

  autoClusterAll(): void
    — Lance le clustering sur tous les pays × catégories non traités
    — Dispatche en jobs async

  detectKeywords(TopicCluster $cluster): array
    — Analyse les titres et contenus du cluster
    — Extrait mots-clés communs, fréquents
    — Classifie : primary, secondary, long_tail
    — Sauvegarde dans cluster.keywords_detected
```

### 4.2 `ResearchBriefService` — Recherche et enrichissement
```
Méthodes:
  generateBrief(TopicCluster $cluster): ResearchBrief
    — Phase 1: Extraire les faits clés de chaque article source (GPT-4o-mini)
    — Phase 2: Recherche Perplexity pour données récentes
    — Phase 3: Identifier les lacunes (GPT-4o)
    — Phase 4: Générer PAA questions (GPT-4o)
    — Phase 5: Suggérer mots-clés et structure
    — Sauvegarder le ResearchBrief
    — Return brief
```

### 4.3 `KeywordTrackingService` — Suivi des mots-clés
```
Méthodes:
  trackKeywordsForArticle(GeneratedArticle $article, array $keywords): void
    — Pour chaque mot-clé : créer/trouver dans keyword_tracking
    — Créer les pivot article_keywords avec usage_type et densité
    — Incrémenter articles_using_count

  analyzeKeywordUsage(string $keyword, string $language): array
    — Retourne : articles qui l'utilisent, densité moyenne, positions

  findKeywordGaps(string $country, string $category, string $language): array
    — Compare mots-clés existants vs mots-clés potentiels
    — Identifie les longues traînes non couvertes

  checkCannibalization(string $keyword, string $language): array
    — Trouve les articles qui ciblent le même mot-clé principal
    — Alerte si 2+ articles en compétition

  suggestLongTailKeywords(string $topic, string $country, string $language): array
    — Utilise Perplexity pour trouver les longues traînes populaires
    — Format : [{keyword, estimated_volume, difficulty, type}]
```

### 4.4 `SeoChecklistService` — Validation SEO complète
```
Méthodes:
  evaluate(GeneratedArticle $article): SeoChecklist
    — Exécute TOUS les checks (30+ critères)
    — Calcule le score global (% de checks passés)
    — Sauvegarde dans seo_checklists
    — Retourne la checklist avec détails

  getFailedChecks(GeneratedArticle $article): array
    — Retourne les checks échoués avec suggestions de correction

  autoFix(GeneratedArticle $article): array
    — Corrige automatiquement ce qui est corrigeable :
      - Ajouter alt text manquant
      - Corriger hiérarchie headings
      - Ajouter schema manquant
      - Ajuster densité mots-clés
    — Retourne les corrections appliquées
```

### 4.5 `EeatSignalService` — Signaux E-E-A-T
```
Méthodes:
  addEeatSignals(GeneratedArticle $article): string
    — Ajoute au HTML :
      - Author box en fin d'article
      - "Sources" section avec liens officiels
      - "Dernière mise à jour : {date}"
      - Schema Person pour l'auteur
      - Schema Organization pour SOS-Expat
    — Return modified HTML

  generateAuthorSchema(): array
    — JSON-LD @type Person avec credentials expat
```

### 4.6 `FeaturedSnippetService` — Optimisation Featured Snippets
```
Méthodes:
  optimizeForSnippets(string $html, string $primaryKeyword): string
    — Ajoute un paragraphe de définition (40-60 mots) après le H1
    — Convertit les processus en listes numérotées
    — Ajoute des tableaux comparatifs structurés
    — Return modified HTML

  addSpeakableMarkup(array $jsonLd, string $html): array
    — Identifie les sections "speakable" (réponses directes)
    — Ajoute Speakable schema
    — Return updated JSON-LD
```

### 4.7 `EnhancedArticleGenerationService` (étend ArticleGenerationService)
```
Le nouveau pipeline complet (remplace/étend le pipeline 15 étapes) :

Phase 1:  Validation du cluster
Phase 2:  Extraction des faits depuis les articles sources
Phase 3:  Recherche Perplexity (données récentes + PAA)
Phase 4:  Génération du brief de recherche
Phase 5:  Suggestion mots-clés + longue traîne
Phase 6:  Génération titre SEO optimisé
Phase 7:  Génération contenu principal (2500-4000 mots)
Phase 8:  Génération FAQ (8-12 questions basées PAA)
Phase 9:  Optimisation featured snippets
Phase 10: Signaux E-E-A-T
Phase 11: Meta tags optimisés
Phase 12: Structured Data complet (Article + FAQ + Breadcrumb + Speakable + HowTo)
Phase 13: Maillage interne (par langue + sujet + pays)
Phase 14: Liens externes (depuis content_external_links, filtrés par pertinence)
Phase 15: Liens affiliés SOS-Expat
Phase 16: Images (Unsplash avec alt text optimisé)
Phase 17: Slug multilingue
Phase 18: Checklist SEO (30+ critères)
Phase 19: Auto-fix des problèmes détectables
Phase 20: Calcul score qualité
Phase 21: Tracking mots-clés
Phase 22: Marquage articles sources comme "used"
Phase 23: Dispatch traductions 9 langues
Phase 24: Sync hreflang bidirectionnel
```

---

## 5. NOUVELLES PAGES FRONTEND

### 5.1 Page "Clusters" (`/content/clusters`)
```
Vue d'ensemble des clusters d'articles :
- Filtres : pays, catégorie, statut (pending/ready/generating/generated)
- Table : Nom cluster, Pays, Catégorie, Nb articles source, Mots-clés, Statut, Actions
- Actions : Voir détails, Lancer génération, Archiver
- Bouton "Auto-cluster tous les articles non traités"
- Stats : articles non traités, clusters prêts, articles générés
```

### 5.2 Page "Cluster Detail" (`/content/clusters/:id`)
```
Détail d'un cluster :
- Liste des articles sources avec :
  - Titre, Source, Word count, Qualité, Statut processing
  - Preview du contenu (expandable)
  - Score de pertinence au cluster
  - Checkbox "article principal"
- Brief de recherche (si généré) :
  - Faits extraits
  - Données récentes
  - Lacunes identifiées
  - PAA questions
  - Mots-clés suggérés
- Boutons : "Générer le brief", "Générer l'article", "Voir l'article généré"
```

### 5.3 Page "Keyword Tracker" (`/seo/keywords`)
```
Dashboard mots-clés :
- Onglet "Mots-clés principaux" :
  - Table : Mot-clé, Langue, Type, Nb articles, Volume estimé, Difficulté
  - Filtre par langue, type, pays
- Onglet "Longue traîne" :
  - Mêmes colonnes mais filtré type=long_tail
  - Détection longues traînes non couvertes
- Onglet "Cannibalization" :
  - Mots-clés ciblés par 2+ articles
  - Suggestion de fusion ou différenciation
- Onglet "Gaps" :
  - Mots-clés identifiés mais pas encore couverts
  - Bouton "Créer un cluster pour ce mot-clé"
- Stats globales : total keywords, couverture par langue, top 10 keywords
```

### 5.4 Page "SEO Checklist" (`/seo/checklist/:articleId`)
```
Checklist visuelle pour un article :
- 30+ critères groupés par catégorie
- Chaque critère : ✅ passé / ❌ échoué / ⚠️ avertissement
- Score global : X/30 (XX%)
- Bouton "Auto-fix" pour corriger automatiquement
- Catégories :
  - On-Page (H1, title, meta, density...)
  - Structured Data (schemas)
  - E-E-A-T (auteur, sources, dates)
  - Links (internes, externes, officiels)
  - Featured Snippets (définition, listes, tableaux)
  - AEO (speakable, réponses directes, PAA)
  - Images (alt, keyword, count)
  - Translation (hreflang, count)
```

### 5.5 Mise à jour de la sidebar
```
Ajouter sous "Contenu" :
  - Clusters (NOUVEAU)

Ajouter sous "SEO" :
  - Keyword Tracker (NOUVEAU)

La checklist SEO est accessible depuis l'ArticleDetail (onglet SEO)
```

---

## 6. WORKFLOW UX COMPLET

```
L'admin dans la console d'administration :

1. VA DANS "Content" (scraping existant)
   → Voit les sources, lance les scrapes, consulte les articles scrapés

2. VA DANS "Clusters"
   → Clique "Auto-cluster" → le système groupe les articles par sujet
   → Voit les clusters créés : "Visa Allemagne (4 articles)", "Logement France (7 articles)"
   → Clique sur un cluster pour voir les articles sources

3. DANS LE DETAIL DU CLUSTER
   → Clique "Générer le brief" → Perplexity recherche, faits extraits, PAA trouvées
   → Review le brief, ajuste si nécessaire
   → Clique "Générer l'article" → Pipeline 24 étapes lance

4. L'ARTICLE EST GENERE
   → Navigue vers l'ArticleDetail
   → Voit le contenu, les FAQ, les mots-clés trackés
   → Onglet SEO : voit la checklist (30+ critères), score SEO
   → Onglet Publier : publie sur Firestore en 9 langues

5. VA DANS "Keyword Tracker"
   → Voit tous les mots-clés utilisés dans tous les articles
   → Identifie les gaps, les cannibalisations
   → Crée de nouveaux clusters pour couvrir les gaps

6. BOUCLE : Scrape → Cluster → Generate → Publish → Track → Repeat
```

---

## 7. PROMPTS IA CLES

### Prompt extraction de faits (Phase 2)
```
Tu es un analyste de contenu expert. Analyse cet article sur "{sujet}" pour le pays "{pays}".

ARTICLE SOURCE :
{content_text}

Extrais UNIQUEMENT les faits vérifiables, les données chiffrées, les procédures officielles, et les informations pratiques.
NE COPIE JAMAIS de phrases entières.
Retourne un JSON :
{
  "key_facts": ["fait 1", "fait 2", ...],
  "statistics": ["stat 1", "stat 2", ...],
  "procedures": ["étape 1", "étape 2", ...],
  "official_sources_mentioned": ["source 1", ...],
  "outdated_info": ["info possiblement obsolète 1", ...],
  "quality_rating": 0-100
}
```

### Prompt recherche Perplexity (Phase 3)
```
Recherche les informations les plus récentes et fiables sur : "{sujet}" pour "{pays}" en {année}.

Focus sur :
1. Changements récents de législation ou réglementation
2. Statistiques à jour (coûts, délais, chiffres clés)
3. Sources officielles (.gov, consulats, organismes)
4. Questions fréquentes que les expatriés posent (PAA)
5. Longues traînes populaires liées à ce sujet

Retourne des faits précis avec leurs sources.
```

### Prompt génération article (Phase 7)
```
Tu es un rédacteur web expert en SEO et expatriation. Rédige un article de 2500-4000 mots sur "{sujet}" pour les expatriés en/au "{pays}".

BRIEF DE RECHERCHE :
{research_brief}

FAITS EXTRAITS DES SOURCES :
{extracted_facts}

MOTS-CLES A INTEGRER NATURELLEMENT :
- Principal : {primary_keyword} (densité cible : 1-2%)
- Secondaires : {secondary_keywords} (densité cible : 0.5-1% chacun)
- Longue traîne : {long_tail_keywords} (placer dans les H2/H3)
- LSI/sémantiques : {lsi_keywords} (disperser dans le contenu)

STRUCTURE OBLIGATOIRE :
- 1 seul H1 (titre avec mot-clé principal)
- 6-10 sections H2 (incluant les questions PAA quand pertinent)
- H3/H4 pour sous-sections si nécessaire
- Paragraphe de définition après le H1 (40-60 mots, format featured snippet)
- Au moins 1 tableau de données
- Au moins 1 liste numérotée (étapes/processus)
- Listes à puces pour la lisibilité
- FAQ : 8-12 questions (basées sur les PAA + sujet)
- Conclusion avec CTA vers SOS-Expat

REGLE ABSOLUE :
- NE COPIE JAMAIS de phrases des articles sources
- Reformule TOUT avec ta propre voix
- Apporte de la VRAIE valeur (conseils pratiques, astuces, pièges à éviter)
- Cite les sources officielles
- Format HTML (<h2>, <p>, <ul>, <ol>, <table>, <strong>, etc.)
- Ton professionnel mais accessible
- Signaux E-E-A-T : date, sources, expertise

Retourne le HTML complet de l'article.
```

---

## 8. REGLES & CONTRAINTES

```
1. JAMAIS de copie de contenu — toujours reformuler avec valeur ajoutée
2. Chaque article source ne peut être utilisé qu'UNE FOIS (tracking strict)
3. Les mots-clés doivent être intégrés NATURELLEMENT (pas de keyword stuffing)
4. Le maillage interne est TOUJOURS par langue (FR lie vers FR, EN vers EN)
5. Les liens externes viennent de notre base scrappée (content_external_links)
6. Les traductions sont NATIVES (pas de traduction mot à mot)
7. Chaque article doit passer la checklist SEO (30+ critères)
8. Le score SEO minimum pour publier est 80/100
9. L'article doit avoir au moins 2500 mots
10. Les FAQ doivent être basées sur les vraies questions PAA
11. Les signaux E-E-A-T sont OBLIGATOIRES (auteur, sources, dates)
12. Le JSON-LD doit inclure : Article + FAQPage + BreadcrumbList + Speakable
13. Le budget IA quotidien doit être respecté
14. Les images doivent avoir des alt text avec mots-clés
15. La hiérarchie heading est stricte : H1 > H2 > H3 > H4 (jamais de saut)
```

---

## 9. SEEDERS A CREER

### PromptTemplateSeeder
```
Créer ~20 prompts pour toutes les phases du pipeline :
- cluster_fact_extraction (extraction faits des sources)
- cluster_research (recherche Perplexity)
- cluster_keyword_suggestion (suggestion mots-clés)
- article_title (titre SEO)
- article_content (contenu principal — LE PLUS IMPORTANT)
- article_faq (FAQ basées PAA)
- article_meta (meta title + description)
- article_featured_snippet (paragraphe définition)
- article_eeat (signaux auteur/expertise)
- translation_fr, translation_en, translation_de, etc. (par langue)
- comparative_content (pour les comparatifs)
- landing_content (pour les landing pages)
```

### GenerationPresetSeeder
```
5 presets par défaut :
- "Standard (depuis sources)" — pipeline complet cluster→article
- "Guide pays complet" — focus profondeur, 3000+ mots
- "FAQ thématique" — focus questions, 12+ FAQ
- "Actualité expat" — focus données récentes, 1500-2000 mots
- "Comparatif pays" — focus tableau comparatif
```

### PublishingEndpointSeeder
```
1 endpoint par défaut :
- "SOS-Expat Firestore" — type: firestore, project: sos-urgently-ac307
```

---

## 10. DELIVERABLES

```
Backend :
- 2 nouvelles migrations (topic_clusters/research_briefs + keyword_tracking/seo_checklists + alter content_articles)
- 7 nouveaux modèles (TopicCluster, TopicClusterArticle, KeywordTracking, ArticleKeyword, ResearchBrief, SeoChecklist + modifier ContentArticle)
- 5 nouveaux services (TopicClustering, ResearchBrief, KeywordTracking, SeoChecklist, EeatSignal, FeaturedSnippet)
- EnhancedArticleGenerationService (pipeline 24 étapes)
- 3 nouveaux jobs (ClusterArticlesJob, GenerateFromClusterJob, ...)
- 2 nouveaux controllers (TopicClusterController, KeywordTrackingController)
- Nouvelles routes API
- 3 seeders (PromptTemplate, GenerationPreset, PublishingEndpoint)

Frontend :
- Page Clusters (liste + détail)
- Page Keyword Tracker (4 onglets)
- Composant SEO Checklist (intégré dans ArticleDetail)
- Mise à jour sidebar navigation
- Mise à jour ArticleDetail avec checklist

Config :
- Variables .env documentées
- Docker workers à jour
```
