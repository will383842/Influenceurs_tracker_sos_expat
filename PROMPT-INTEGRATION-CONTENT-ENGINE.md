# PROMPT : Integration Content Engine + SEO natif dans Influenceurs Tracker

> **Contexte** : Tu es une équipe de 30 des meilleurs développeurs full-stack, architectes logiciels, experts SEO technique, designers UI/UX et spécialistes IA au monde. Vous travaillez ensemble pour intégrer un moteur complet de génération de contenu IA + SEO natif dans un outil existant de CRM/prospection d'influenceurs.

---

## 1. PROJET EXISTANT : Influenceurs Tracker SOS-Expat

### Stack actuel
- **Backend** : Laravel 12, PHP 8.2+, PostgreSQL 16, Redis 7
- **Frontend** : React 19 + TypeScript 5.9 + Vite 8 + Tailwind CSS 3.4 + React Router 7
- **UI** : Radix UI primitives, Recharts, Lucide icons, React Hook Form, dark theme
- **Auth** : Laravel Sanctum (cookie-based SPA)
- **Queue** : Redis (3 workers : default, email, scraper)
- **Infra** : Docker Compose (10 containers), Hetzner VPS, Nginx reverse proxy
- **Domaine** : influenceurs.life-expat.com
- **CI/CD** : GitHub Actions

### Modèles existants (28 Eloquent models)
```
User (users) — roles: admin, manager, member, researcher
Influenceur (influenceurs) — 24 types de contacts, pipeline 15 statuts
Contact (contacts) — timeline interactions (email, phone, WhatsApp, LinkedIn)
Reminder (reminders) — follow-up auto
Objective (objectives) — targets par researcher
ActivityLog (activity_logs) — audit trail
ContactTypeModel (contact_types) — types dynamiques
EmailTemplate (email_templates) — templates multi-step par type/langue
OutreachEmail (outreach_emails) — emails générés, statut envoi
OutreachSequence (outreach_sequences) — séquences multi-step
OutreachConfig (outreach_configs) — config par type
EmailEvent (email_events) — open/click tracking
DomainHealth (domain_health) — SPF/DKIM/DMARC
WarmupState (warmup_states) — warmup domaines email
AiPrompt (ai_prompts) — prompts Claude par type de contact
AiResearchSession (ai_research_sessions) — sessions recherche IA
EmailVerification (email_verifications) — vérification emails
ContentSource (content_sources) — sources scraping (ex: expat.com)
ContentCountry (content_countries) — pays par source
ContentArticle (content_articles) — articles scrapés
ContentExternalLink (content_external_links) — liens extraits
ContentGenerated (content_generated) — contenu généré (basique)
DuplicateFlag (duplicate_flags) — détection doublons
TypeVerificationFlag (type_verification_flags) — vérification types
AutoCampaign (auto_campaigns) — campagnes recherche auto
AutoCampaignTask (auto_campaign_tasks) — tâches campagne
Setting (settings) — paramètres app
ContentMetric (content_metrics) — métriques contenu
```

### Routes API existantes (~70 endpoints)
```
POST   /login, /logout, GET /me
GET    /influenceurs (cursor pagination, filters)
POST   /influenceurs
GET    /influenceurs/{id}
PUT    /influenceurs/{id}
DELETE /influenceurs/{id}
POST   /influenceurs/{id}/contacts
GET    /influenceurs/{id}/reminders
POST   /ai-research
GET    /ai-research/sessions
POST   /directories/{id}/scrape
GET    /outreach/emails
POST   /outreach/emails/{id}/approve
POST   /outreach/emails/{id}/send
GET    /outreach/sequences
POST   /outreach/generate
GET    /email-templates
POST   /email-templates
PUT    /email-templates/{id}
GET    /content-engine/sources
POST   /content-engine/sources/{id}/scrape
GET    /content-engine/countries/{id}/articles
GET    /content-engine/external-links
GET    /stats/overview
GET    /stats/coverage-matrix
GET    /admin/team
POST   /admin/team
PUT    /admin/team/{id}
GET    /admin/ai-prompts
PUT    /admin/ai-prompts/{id}
GET    /admin/contact-types
POST   /auto-campaigns
GET    /auto-campaigns
...etc
```

### Pages frontend existantes (22 pages React)
```
/login — Authentification
/dashboard — Dashboard admin (stats globales)
/researcher-dashboard — Dashboard researcher (vue limitée)
/influenceurs — Liste prospects avec filtres avancés
/influenceurs/:id — Détail prospect + timeline contacts
/a-relancer — Prospects à relancer
/ai-research — Recherche IA (Claude/Perplexity/Tavily)
/directories — Annuaires à scraper
/prospection — Hub prospection
/prospection/overview — Vue d'ensemble outreach
/prospection/emails — Emails générés/envoyés
/prospection/sequences — Séquences multi-step
/prospection/contacts — Contacts outreach
/prospection/config — Configuration outreach
/outreach — Vue classique outreach
/content — Hub contenu (scraping)
/content/sources — Sources de contenu
/content/countries/:id — Articles par pays
/content/links — Liens externes extraits
/admin/ai-prompts — Gestion prompts IA
/admin/contact-types — Types de contacts
/admin/scraper — Config scraper
/auto-campaigns — Campagnes automatiques
/coverage — Matrice couverture
/quality — Dashboard qualité (doublons, vérifications)
/equipe — Gestion équipe
/journal — Journal d'activité
/statistiques — Statistiques (legacy)
```

### Services backend existants (18 services)
```
AiEmailGenerationService — Génération emails via Claude API (Anthropic SDK)
EmailSendingService — Envoi PMTA + bounce handling
OutreachService — Orchestration séquences
AiPromptService — Gestion prompts IA
ClaudeSearchService — Appels directs Claude API
PerplexitySearchService — Intégration Perplexity
ResultParserService — Parsing réponses IA en contacts structurés
ContentScraperService — Scraping DOM + extraction liens + détection affiliés
WebScraperService — Scraping web générique
DirectoryScraperService — Scraping annuaires
EmailVerificationService — Validation emails
QualityScoreService — Score qualité contacts
DeduplicationService — Détection doublons
TypeVerificationService — Vérification types
BlockedDomainService — Liste domaines bloqués
EmailDomainMatchService — Match emails/domaines
UrlNormalizationService — Normalisation URLs
```

### Docker Compose existant (10 containers)
```yaml
services:
  app          # Laravel PHP-FPM (API principale)
  postgres     # PostgreSQL 16
  redis        # Redis 7 (queue, cache, session)
  scheduler    # php artisan schedule:work
  queue        # Worker default queue
  email-worker # Worker email queue
  scraper      # Worker scraper queue
  content-scraper # Worker content scraping (512MB)
  nginx-api    # Nginx reverse proxy API (port 8090)
  frontend     # React build servi par Nginx (port 8091)
```

---

## 2. PROJET A INTEGRER : eng-content-generate

### Ce qu'il contient (à fusionner)
Un moteur complet de génération de contenu IA avec :

#### 2.1 Types de contenu générables
- **Articles standards** : Recherche Perplexity → GPT-4o → 6-8 sections H2 → FAQ auto (8+ questions) → meta tags → JSON-LD → liens internes/externes/affiliés → images
- **Comparatifs** : 2-5 entités comparées, tableaux structurés, pros/cons, données auto-fetched
- **Landing pages** : Multi-sections CTA, hero/features/testimonials/CTA blocks
- **Pillar content** : Articles piliers avec clusters de sous-articles, stratégie de liens pilier↔cluster
- **Communiqués de presse** : Structure personnalisable, export PDF/Word
- **Dossiers de presse** : Collections d'articles/communiqués bundlés

#### 2.2 Pipeline de génération (15 étapes)
```
1.  Validation des paramètres
2.  Recherche sources (Perplexity web search)
3.  Génération titre unique (SHA256 dedup)
4.  Génération excerpt/intro
5.  Génération contenu principal (6-8 sections H2)
6.  Génération FAQ (8+ questions)
7.  Génération meta tags optimisés (<60 chars title, <160 chars description)
8.  Génération JSON-LD (Article, BreadcrumbList, FAQPage)
9.  Ajout liens internes
10. Ajout liens externes
11. Ajout liens affiliés
12. Optimisation images (alt text, responsive srcset)
13. Génération slugs locale-specific (fr-DE, en-US, etc.)
14. Calcul quality score
15. Dispatch traductions multi-langues
```

#### 2.3 Modèles à migrer depuis eng-content-generate
```
Article (articles) — uuid, title, slug, content, status, quality_score, generation_cost, published_at
ArticleFaq (article_faqs) — article_id, question, answer
ArticleSource (article_sources) — article_id, url, title, excerpt
ArticleTranslation (article_translations) — article_id, language_id, content complet traduit
ArticleVersion (article_versions) — article_id, changes_json, created_by
ArticlePublication (article_publications) — article_id, published_at, platform_id
ArticleExport (article_exports) — article_id, format, file_path
Comparative (comparatives) — title, entities_json, comparison_data
LandingPage (landing_pages) — title, sections_json, cta_config
LandingCtaLink (landing_cta_links) — landing_id, url, text, position
PressRelease (press_releases) — title, content, status, export_path
PressDossier (press_dossiers) — name, articles_json, description
ContentCampaign (content_campaigns) — name, type, status, progress_json
CampaignArticle (campaign_articles) — campaign_id, article_id
Template (templates) — name, type, content, variables_json
ContentTemplate (content_templates) — type, language_code, content, platform_id
TemplateVariable (template_variables) — template_id, name, value
PromptTemplate (prompt_templates) — name, type, system_message, user_message
QualityScore (quality_scores) — scorable_type/id, overall_score, readability_score, seo_score
GenerationLog (generation_logs) — article_id, phase, status, tokens_used, cost
GenerationQueue (generation_queue) — type, payload_json, status, attempts
GenerationPreset (generation_presets) — name, config_json
InternalLink (internal_links) — source_id, target_id, anchor_text
ExternalLink (external_links) — article_id, url, anchor_text, trust_score
AffiliateLink (affiliate_links) — article_id, url, program, commission_rate
ApiCost (api_costs) — service, model, input_tokens, output_tokens, cost
PlatformKnowledge (platform_knowledge) — platform_id, knowledge_json
BrandGuideline (brand_guidelines) — platform_id, guideline_json
BrandViolation (brand_violations) — article_id, violation_json
PublicationQueue (publication_queue) — article_id, status, scheduled_at, attempts
PublicationSchedule (publication_schedules) — platform_id, articles_per_day, max_per_hour
PublishingEndpoint (publishing_endpoints) — platform_id, url, method, headers_json
Webhook (webhooks) — event, payload_url, retry_count
ContentImage (content_images) — article_id, url, alt_text, source, attribution
AffiliationBlock (affiliation_blocks) — platform_id, type, config_json
GoldenExample (golden_examples) — platform_id, article_id, criteria_json
PlagiarismCheck (plagiarism_checks) — article_id, original_score, plagiarism_detected
BulkUpdateLog (bulk_update_logs) — operation, total_records, successful, failed
AdminUser (admin_users) — email, name, password, roles_json [NE PAS MIGRER — utiliser User existant]
ApiKey (api_keys) — user_id, key, permissions_json
```

#### 2.4 Services à migrer/adapter
```
AI/
  OpenAIService — Completions GPT-4o (articles)
  GptService — Wrapper GPT spécialisé
  PerplexityService — Recherche web (EXISTE DEJA dans Influenceurs → fusionner)
  DalleService — Génération images DALL-E 3
  ModelSelectionService — Sélection modèle intelligent
  PromptOptimizerService — Raffinement prompts
  ContentCacheService — Cache réponses IA
  CostTracker — Suivi coûts temps réel

Content/
  ArticleGenerationService — Orchestration pipeline 15 étapes
  ComparativeGenerationService — Génération comparatifs
  LandingGenerationService — Génération landing pages
  PillarService — Gestion pillar/cluster
  PressReleaseService — Communiqués de presse
  PressDossierService — Dossiers de presse
  TranslationService — Traductions multi-langues

Quality/
  ReadabilityAnalyzer — Analyse lisibilité (Flesch-Kincaid)
  ToneAnalyzer — Analyse ton/voix
  BrandComplianceService — Conformité marque
  QualityScoreService — Score qualité global

Publishing/
  PublicationConfigService — Règles publication par plateforme
  PublicationScheduler — Scheduling intelligent
  AntiSpamChecker — Rate limiting + détection patterns

SEO/ [ETAIT DELEGUE — A CONSTRUIRE NATIVEMENT]
  → Voir section 3
```

#### 2.5 APIs IA utilisées
```
OpenAI GPT-4o      — Génération contenu principal ($2.50/1M input, $10/1M output)
OpenAI GPT-4o-mini — Traductions ($0.15/1M input, $0.60/1M output)
Perplexity Sonar   — Recherche web ($0.001/token)
DALL-E 3           — Génération images (~$0.08/image)
Unsplash API       — Images stock (gratuit, 50 req/heure)
News API           — Sources actualités (gratuit, 100 req/jour)
```

#### 2.6 Système de publication existant
```
Règles par plateforme :
  - Articles/jour : 100 (configurable)
  - Max/heure : 15
  - Heures actives : 9h-17h
  - Jours actifs : Lun-Ven
  - Intervalle min : 6 minutes entre articles
  - Auto-pause après 5+ erreurs consécutives

File de publication :
  - Priority levels : high, default, low
  - Retry avec exponential backoff
  - Health checks post-publication
  - Notifications échec (email Zoho + Telegram)

Export :
  - PDF (DomPDF)
  - Word (PHPWord)
  - Excel/CSV (Maatwebsite/Excel — DEJA dans Influenceurs)
```

#### 2.7 Frontend eng-content-generate (100+ composants React)
```
Sections principales :
  Content     → articles, comparatives, landings, pillars, manual-titles
  Press       → releases, dossiers
  Generation  → generation hub, campaigns, programs
  Quality     → quality dashboard, brand-validation, golden-examples, feedback
  Settings    → templates, prompts, brand guidelines, variables, presets
  Analytics   → dashboard, charts, benchmarks, conversions, costs
  Admin       → users, permissions, activity, system health
  Publishing  → publication queue, schedules
  Research    → knowledge base, sources
  Linking     → internal links, external links, authority domains
  Media       → images, unsplash integration, image library
  Dashboards  → overview, live stats, coverage, exports
```

---

## 3. CE QUI DOIT ETRE CONSTRUIT NATIVEMENT : SEO ENGINE INTEGRE

**CRITIQUE** : Dans eng-content-generate, le SEO était délégué à un engine externe (`eng-seo-perfect`). Ici, on veut tout intégrer nativement. Voici ce que le SEO natif doit couvrir :

### 3.1 Keyword Research & Analysis
```
- Extraction de mots-clés principaux et secondaires depuis le sujet
- Analyse sémantique LSI (Latent Semantic Indexing)
- Densité de mots-clés optimale (1-2% principal, 0.5-1% secondaires)
- Suggestions PAA (People Also Ask) via Perplexity
- Keyword clustering par intention de recherche
- Volume de recherche estimé (si API disponible)
```

### 3.2 On-Page SEO
```
Meta Tags :
  - Title tag optimisé (<60 caractères, mot-clé principal en début)
  - Meta description optimisée (<160 caractères, CTA inclus)
  - Meta robots (index, follow par défaut)
  - Canonical URL

Headings :
  - H1 unique (= titre article)
  - H2 avec mots-clés secondaires (6-8 sections)
  - H3 pour sous-sections si nécessaire
  - Hiérarchie stricte (pas de H3 sans H2 parent)

Contenu :
  - Longueur optimale par type (articles: 1500-3000 mots, comparatifs: 2000-4000)
  - Premier paragraphe contient le mot-clé principal
  - Mots-clés en gras (<strong>) naturellement
  - Tableaux de données quand pertinent
  - Listes à puces pour la lisibilité
```

### 3.3 Technical SEO
```
Structured Data (JSON-LD) :
  - Article / NewsArticle / BlogPosting
  - FAQPage (pour les FAQ auto-générées)
  - BreadcrumbList
  - HowTo (pour les guides)
  - Product (pour les comparatifs)
  - Organization (pour les communiqués)
  - Review / AggregateRating (si applicable)

Performance :
  - Images optimisées (WebP, lazy loading, srcset responsive)
  - Alt text descriptif avec mot-clé
  - Compression HTML (minification des espaces)

Indexation :
  - Sitemap XML dynamique (par langue, par type de contenu)
  - robots.txt optimisé
  - IndexNow API (soumission instantanée Bing/Yandex)
  - Ping Google (via Sitemap ping URL)
```

### 3.4 Multilingue & International SEO (CRITIQUE pour SOS-Expat)
```
Langues cibles : FR, EN, DE, ES, PT, RU, ZH, AR, HI (9 langues)

Hreflang :
  - Balises hreflang bidirectionnelles sur chaque page
  - Format : <link rel="alternate" hreflang="fr-FR" href="..." />
  - x-default vers la version anglaise
  - Validation croisée (chaque page référence toutes les autres langues)

Slugs localisés :
  - FR : /fr/guide-expatriation-allemagne
  - EN : /en/germany-expat-guide
  - DE : /de/auswandern-nach-deutschland
  - ES : /es/guia-expatriacion-alemania
  - Etc.
  - Mapping slug ↔ article_id stocké en DB

URL Structure :
  /{lang}/blog/{slug}              — Articles
  /{lang}/comparatif/{slug}        — Comparatifs
  /{lang}/guide/{country}/{slug}   — Guides par pays
  /{lang}/faq/{slug}               — Pages FAQ
  /{lang}/actualites/{slug}        — Communiqués de presse
```

### 3.5 Maillage interne
```
Stratégie :
  - Liens pilier → cluster (et inverse)
  - Liens entre articles du même pays
  - Liens entre articles de la même thématique
  - Ancres texte optimisées (pas de "cliquez ici")
  - Maximum 5-8 liens internes par article
  - Détection de pages orphelines (0 lien entrant)

Algorithme :
  - Score de pertinence basé sur : même pays, même langue, même thématique, même cluster
  - Rotation des ancres texte (éviter sur-optimisation)
  - Exclusion des liens vers contenu non publié
```

### 3.6 SEO Scoring Dashboard
```
Score global /100 composé de :
  - Title tag (10 pts) : longueur, mot-clé, unicité
  - Meta description (10 pts) : longueur, CTA, mot-clé
  - Headings (10 pts) : hiérarchie, mots-clés H2
  - Contenu (20 pts) : longueur, densité mots-clés, lisibilité
  - Images (10 pts) : alt text, optimisation, nombre
  - Liens internes (10 pts) : nombre, pertinence, ancres
  - Liens externes (5 pts) : autorité domaines, nombre
  - Structured Data (10 pts) : JSON-LD complet, valide
  - Hreflang (10 pts) : toutes langues, bidirectionnel
  - Technical (5 pts) : canonical, robots, sitemap
```

---

## 4. PUBLICATION VERS SOS-EXPAT

### 4.1 Architecture de publication
```
L'outil doit pouvoir publier vers :

1. Site SOS-Expat (Firebase/Firestore + Cloudflare Pages)
   - Push contenu vers collection Firestore `blog_articles/{id}`
   - Structure : title, slug, content_html, meta, faq, hreflang, published_at, language, country
   - Le frontend Vite/React de SOS-Expat consomme Firestore
   - Ou : génération de fichiers statiques pour Cloudflare Pages

2. WordPress (optionnel, pour sites partenaires)
   - REST API WordPress : POST /wp-json/wp/v2/posts
   - Auth : Application Passwords ou JWT
   - Mapping catégories/tags

3. API Custom (webhook)
   - POST vers URL configurable
   - Headers personnalisables
   - Payload JSON standard
   - Retry avec backoff

4. Export fichiers
   - HTML statique (pour import manuel)
   - Markdown (pour GitHub Pages / Hugo / Gatsby)
   - PDF (pour dossiers de presse)
   - Word (pour relecture hors-ligne)
```

### 4.2 Connecteur Firebase/Firestore (prioritaire pour SOS-Expat)
```
Config :
  FIREBASE_PROJECT_ID=sos-urgently-ac307
  FIREBASE_SERVICE_ACCOUNT_KEY=path/to/key.json

Collection Firestore : blog_articles/{articleId}
Document structure :
{
  id: string (UUID),
  title: string,
  slug: string,
  content_html: string,
  excerpt: string,
  meta_title: string,
  meta_description: string,
  featured_image: { url, alt, attribution },
  faq: [{ question, answer }],
  json_ld: object,
  hreflang: { "fr": "/fr/slug", "en": "/en/slug", ... },
  language: string,
  country: string,
  category: string,
  tags: string[],
  internal_links: [{ url, anchor }],
  author: string,
  published_at: Timestamp,
  updated_at: Timestamp,
  status: "draft" | "published" | "archived",
  seo_score: number,
  word_count: number,
  reading_time_minutes: number,
  translations: { [lang]: articleId }
}

Sous-collection : blog_articles/{articleId}/versions/{versionId}
{
  content_html: string,
  changed_by: string,
  changed_at: Timestamp,
  changes_summary: string
}
```

---

## 5. FRONTEND / UI / UX — SPECIFICATIONS COMPLETES

### 5.1 Principes directeurs
```
- Dark theme par défaut (cohérent avec l'existant)
- Design system unifié : même palette violet/orange/gray que l'Influenceurs Tracker
- Mobile-responsive mais optimisé desktop (outil pro, utilisé sur grand écran)
- Sidebar navigation avec sections collapsibles
- Breadcrumbs sur toutes les pages
- Raccourcis clavier (Cmd+K pour recherche globale, Cmd+N pour nouveau)
- Notifications temps réel (WebSocket via Soketi/Pusher existant)
- Loading states avec skeletons (jamais de spinner simple)
- Toasts pour confirmations (react-hot-toast ou Sonner)
- Transitions fluides entre pages (pas de flash blanc)
- Tables avec tri, filtres, pagination cursor, export CSV
- Formulaires avec validation temps réel et autosave drafts
```

### 5.2 Nouvelle navigation (sidebar mise à jour)
```
📊 Dashboard
   ├── Vue d'ensemble (métriques globales)
   └── Live Stats (temps réel)

👥 Prospects (existant)
   ├── Liste prospects
   ├── A relancer
   ├── Recherche IA
   └── Annuaires

📧 Prospection (existant)
   ├── Vue d'ensemble
   ├── Emails
   ├── Séquences
   ├── Contacts
   └── Configuration

✍️  Contenu (NOUVEAU — coeur de l'intégration)
   ├── Vue d'ensemble (stats contenu)
   ├── Articles
   │   ├── Liste articles
   │   ├── Créer article
   │   └── Pillar / Clusters
   ├── Comparatifs
   │   ├── Liste comparatifs
   │   └── Créer comparatif
   ├── Landings
   │   ├── Liste landings
   │   └── Créer landing
   ├── Presse
   │   ├── Communiqués
   │   └── Dossiers
   ├── Campagnes
   │   ├── Campagnes en cours
   │   └── Créer campagne
   └── Scraping (existant, déplacé ici)
       ├── Sources
       ├── Articles scrapés
       └── Liens externes

🔍 SEO (NOUVEAU)
   ├── Dashboard SEO (scores globaux)
   ├── Mots-clés
   ├── Maillage interne
   ├── Hreflang Manager
   ├── Sitemap
   └── IndexNow

📤 Publication (NOUVEAU)
   ├── File d'attente
   ├── Planification
   ├── Endpoints configurés
   └── Historique

🖼️  Médias (NOUVEAU)
   ├── Bibliothèque images
   ├── Unsplash
   └── DALL-E

📈 Analytics
   ├── Statistiques (existant)
   ├── Couverture (existant)
   ├── Coûts IA
   └── Performance contenu

⚙️  Admin
   ├── Equipe (existant)
   ├── Prompts IA (existant, étendu)
   ├── Templates contenu (NOUVEAU)
   ├── Brand Guidelines (NOUVEAU)
   ├── Presets génération (NOUVEAU)
   ├── Types de contacts (existant)
   ├── Configuration scraper (existant)
   └── Qualité
       ├── Dashboard qualité (existant)
       ├── Golden Examples (NOUVEAU)
       └── Brand Validation (NOUVEAU)
```

### 5.3 Pages clés — UX détaillé

#### Page "Créer un article"
```
Layout : 2 colonnes (70% éditeur / 30% sidebar)

Colonne gauche — Editeur :
  ┌─────────────────────────────────────────────────┐
  │ [Breadcrumb: Contenu > Articles > Créer]        │
  │                                                   │
  │ Sujet / Titre provisoire                         │
  │ ┌───────────────────────────────────────────────┐│
  │ │ Ex: Guide expatriation Allemagne 2026         ││
  │ └───────────────────────────────────────────────┘│
  │                                                   │
  │ ┌──────────┐ ┌──────────┐ ┌──────────┐          │
  │ │ Langue ▼ │ │ Pays   ▼ │ │ Type   ▼ │          │
  │ │ Français │ │ Allemagne│ │ Article  │          │
  │ └──────────┘ └──────────┘ └──────────┘          │
  │                                                   │
  │ Mots-clés cibles                                 │
  │ ┌───────────────────────────────────────────────┐│
  │ │ [expatriation allemagne] [visa allemand] [+]  ││
  │ └───────────────────────────────────────────────┘│
  │                                                   │
  │ Instructions spécifiques (optionnel)             │
  │ ┌───────────────────────────────────────────────┐│
  │ │ Focus sur les démarches administratives...     ││
  │ └───────────────────────────────────────────────┘│
  │                                                   │
  │ ┌──────────┐ ┌──────────────┐ ┌────────────────┐│
  │ │ Preset ▼ │ │ Ton ▼        │ │ Longueur ▼     ││
  │ │ Standard │ │ Professionnel│ │ Long (2500+)   ││
  │ └──────────┘ └──────────────┘ └────────────────┘│
  │                                                   │
  │ Options avancées (collapsible) :                 │
  │ ☑ Générer FAQ (8+ questions)                     │
  │ ☑ Recherche sources (Perplexity)                 │
  │ ☑ Images (Unsplash)                              │
  │ ☐ Images IA (DALL-E) — +$0.08/image             │
  │ ☑ Liens internes auto                            │
  │ ☑ Liens affiliés                                 │
  │ ☑ Traductions auto (cocher langues)              │
  │   ☑ EN ☑ DE ☐ ES ☐ PT ☐ RU ☐ ZH ☐ AR ☐ HI    │
  │                                                   │
  │         ┌──────────────────────────┐             │
  │         │ 🚀 Générer l'article    │             │
  │         └──────────────────────────┘             │
  │                                                   │
  │ Coût estimé : ~$0.35 (article) + $0.12 (2 trad) │
  └─────────────────────────────────────────────────┘

Colonne droite — Sidebar :
  ┌──────────────────────┐
  │ PRESET RAPIDES       │
  │ ┌──────────────────┐ │
  │ │ Guide pays       │ │
  │ │ FAQ thématique   │ │
  │ │ Comparatif       │ │
  │ │ Actualité expat  │ │
  │ │ Conseil juridique│ │
  │ └──────────────────┘ │
  │                      │
  │ ARTICLES SIMILAIRES  │
  │ (auto-détectés)      │
  │ • Guide expat DE (FR)│
  │ • Visa Allemagne     │
  │ • Assurance DE       │
  │                      │
  │ BUDGET IA RESTANT    │
  │ Aujourd'hui: $42/$50 │
  │ Ce mois: $830/$1000  │
  │ ████████████░░ 83%   │
  └──────────────────────┘
```

#### Page "Article en cours de génération" (temps réel)
```
  ┌─────────────────────────────────────────────────┐
  │ Génération en cours...                           │
  │                                                   │
  │ ✅ 1. Paramètres validés                         │
  │ ✅ 2. Sources recherchées (4 trouvées)           │
  │ ✅ 3. Titre généré: "Guide complet expatriation…"│
  │ ✅ 4. Introduction rédigée                       │
  │ 🔄 5. Contenu principal (section 4/7)...         │
  │ ⏳ 6. FAQ                                        │
  │ ⏳ 7. Meta tags                                  │
  │ ⏳ 8. JSON-LD                                    │
  │ ⏳ 9. Liens internes                             │
  │ ⏳ 10. Liens externes                            │
  │ ⏳ 11. Liens affiliés                            │
  │ ⏳ 12. Images                                    │
  │ ⏳ 13. Slugs multilingues                        │
  │ ⏳ 14. Score qualité                             │
  │ ⏳ 15. Traductions                               │
  │                                                   │
  │ Temps écoulé: 1m 23s                             │
  │ Tokens utilisés: 12,450 / ~25,000 estimés       │
  │ ████████████████░░░░░░░░ 33%                     │
  │                                                   │
  │ [Aperçu en direct ▼]                             │
  │ ┌───────────────────────────────────────────────┐│
  │ │ <h1>Guide complet expatriation Allemagne</h1>││
  │ │ <p>L'Allemagne reste en 2026 l'une des...</p>││
  │ │ <h2>1. Visa et permis de séjour</h2>         ││
  │ │ <p>Pour s'installer en Allemagne...</p>       ││
  │ │ ...                                           ││
  │ └───────────────────────────────────────────────┘│
  └─────────────────────────────────────────────────┘
```

#### Page "Editeur d'article" (post-génération)
```
Layout : TipTap rich text editor (WYSIWYG)

  ┌─────────────────────────────────────────────────────────────┐
  │ [Breadcrumb: Contenu > Articles > Guide expat Allemagne]    │
  │                                                               │
  │ ┌─────────┐┌─────────┐┌─────────┐┌──────┐┌───────┐         │
  │ │ Contenu ││   SEO   ││   FAQ   ││Médias││Publier│         │
  │ └─────────┘└─────────┘└─────────┘└──────┘└───────┘         │
  │                                                               │
  │ Tab "Contenu" :                                              │
  │ ┌───────────────────────────────────────────────────────────┐│
  │ │ [B] [I] [H2] [H3] [Link] [Image] [Table] [Quote] [Code]│ │
  │ │─────────────────────────────────────────────────────────── │
  │ │                                                           ││
  │ │  Guide complet expatriation Allemagne 2026               ││
  │ │  ═══════════════════════════════════════                  ││
  │ │                                                           ││
  │ │  L'Allemagne reste en 2026 l'une des destinations...     ││
  │ │                                                           ││
  │ │  ## 1. Visa et permis de séjour                          ││
  │ │  Pour s'installer en Allemagne, les ressortissants...    ││
  │ │                                                           ││
  │ │  [Image: Berlin skyline — Unsplash @photographer]        ││
  │ │                                                           ││
  │ │  ## 2. Trouver un logement                               ││
  │ │  ...                                                      ││
  │ └───────────────────────────────────────────────────────────┘│
  │                                                               │
  │ Sidebar droite :                                             │
  │ ┌──────────────────────┐                                     │
  │ │ SEO SCORE: 87/100    │                                     │
  │ │ ████████████████░░ 87%│                                    │
  │ │                      │                                     │
  │ │ ✅ Title (9/10)      │                                     │
  │ │ ✅ Meta desc (9/10)  │                                     │
  │ │ ✅ Headings (10/10)  │                                     │
  │ │ ⚠️ Contenu (16/20)   │                                     │
  │ │ ✅ Images (10/10)    │                                     │
  │ │ ⚠️ Liens int. (7/10) │                                     │
  │ │ ✅ Liens ext. (5/5)  │                                     │
  │ │ ✅ JSON-LD (10/10)   │                                     │
  │ │ ✅ Hreflang (10/10)  │                                     │
  │ │ ✅ Technical (5/5)   │                                     │
  │ │                      │                                     │
  │ │ INFOS                │                                     │
  │ │ Mots: 2,847          │                                     │
  │ │ Lecture: 12 min      │                                     │
  │ │ FAQ: 8 questions     │                                     │
  │ │ Liens int: 5         │                                     │
  │ │ Liens ext: 3         │                                     │
  │ │ Images: 4            │                                     │
  │ │ Coût: $0.38          │                                     │
  │ │                      │                                     │
  │ │ TRADUCTIONS          │                                     │
  │ │ ✅ FR (original)     │                                     │
  │ │ ✅ EN (traduit)      │                                     │
  │ │ ✅ DE (traduit)      │                                     │
  │ │ ⏳ ES (en cours...)  │                                     │
  │ │ ➕ Ajouter langue    │                                     │
  │ │                      │                                     │
  │ │ VERSIONS             │                                     │
  │ │ v3 — 14:32 (actuel)  │                                     │
  │ │ v2 — 14:15           │                                     │
  │ │ v1 — 14:02 (généré)  │                                     │
  │ │ [Restaurer v2]       │                                     │
  │ └──────────────────────┘                                     │
  │                                                               │
  │ Tab "SEO" :                                                  │
  │ ┌───────────────────────────────────────────────────────────┐│
  │ │ Title tag:                                                ││
  │ │ [Guide expatriation Allemagne 2026 | SOS-Expat   ] 54/60 ││
  │ │                                                           ││
  │ │ Meta description:                                         ││
  │ │ [Tout savoir pour s'expatrier en Allemagne : visa,       ││
  │ │  logement, assurance, emploi. Guide complet 2026.]  148/160│
  │ │                                                           ││
  │ │ Slug:                                                     ││
  │ │ FR: /fr/guide-expatriation-allemagne-2026                 ││
  │ │ EN: /en/germany-expat-guide-2026                          ││
  │ │ DE: /de/auswandern-deutschland-leitfaden-2026             ││
  │ │ [Modifier slugs]                                          ││
  │ │                                                           ││
  │ │ Canonical: https://sos-expat.com/fr/guide-expatriation... ││
  │ │                                                           ││
  │ │ Mot-clé principal: [expatriation allemagne]               ││
  │ │ Densité: 1.4% ✅                                          ││
  │ │                                                           ││
  │ │ Mots-clés secondaires:                                    ││
  │ │ [visa allemand] 0.8% ✅                                   ││
  │ │ [logement allemagne] 0.6% ✅                              ││
  │ │ [assurance expatrié] 0.4% ⚠️ (un peu faible)              ││
  │ │                                                           ││
  │ │ JSON-LD Preview:                                          ││
  │ │ ┌────────────────────────────────────────┐                ││
  │ │ │ { "@type": "Article",                  │                ││
  │ │ │   "headline": "Guide complet...",      │                ││
  │ │ │   "datePublished": "2026-03-26",       │                ││
  │ │ │   ... }                                │                ││
  │ │ └────────────────────────────────────────┘                ││
  │ │ [Valider JSON-LD] ✅ Valide                               ││
  │ └───────────────────────────────────────────────────────────┘│
  │                                                               │
  │ Tab "FAQ" :                                                  │
  │ ┌───────────────────────────────────────────────────────────┐│
  │ │ 8 questions générées — glisser-déposer pour réordonner   ││
  │ │                                                           ││
  │ │ ┌── Q1 ──────────────────────────────────────────────┐   ││
  │ │ │ Q: Quel visa pour s'expatrier en Allemagne ?       │   ││
  │ │ │ R: Les ressortissants de l'UE n'ont pas besoin...  │   ││
  │ │ │ [Modifier] [Supprimer] [Régénérer]                 │   ││
  │ │ └────────────────────────────────────────────────────┘   ││
  │ │                                                           ││
  │ │ ┌── Q2 ──────────────────────────────────────────────┐   ││
  │ │ │ Q: Combien coûte la vie en Allemagne ?             │   ││
  │ │ │ R: Le coût de la vie varie selon la ville...       │   ││
  │ │ │ [Modifier] [Supprimer] [Régénérer]                 │   ││
  │ │ └────────────────────────────────────────────────────┘   ││
  │ │ ... (6 autres)                                            ││
  │ │                                                           ││
  │ │ [+ Ajouter une question] [Régénérer toutes les FAQ]      ││
  │ └───────────────────────────────────────────────────────────┘│
  │                                                               │
  │ Tab "Publier" :                                              │
  │ ┌───────────────────────────────────────────────────────────┐│
  │ │ Statut actuel: Brouillon                                  ││
  │ │                                                           ││
  │ │ Destination de publication :                              ││
  │ │ ☑ SOS-Expat (Firestore) — sos-urgently-ac307             ││
  │ │ ☐ WordPress — blog.life-expat.com                         ││
  │ │ ☐ Webhook custom                                          ││
  │ │                                                           ││
  │ │ Planification :                                           ││
  │ │ ○ Publier maintenant                                      ││
  │ │ ● Planifier : [27/03/2026] [09:00] [Europe/Paris]        ││
  │ │                                                           ││
  │ │ Langues à publier :                                       ││
  │ │ ☑ FR (original) — prêt                                    ││
  │ │ ☑ EN (traduit) — prêt                                     ││
  │ │ ☑ DE (traduit) — prêt                                     ││
  │ │ ☐ ES — traduction en cours...                             ││
  │ │                                                           ││
  │ │ Checklist pré-publication :                               ││
  │ │ ✅ SEO score > 80                                         ││
  │ │ ✅ Pas de liens cassés                                    ││
  │ │ ✅ Images optimisées                                      ││
  │ │ ✅ FAQ complètes                                          ││
  │ │ ⚠️ Brand compliance non vérifiée                          ││
  │ │                                                           ││
  │ │      ┌────────────────────────────┐                       ││
  │ │      │ 📤 Planifier la publication │                      ││
  │ │      └────────────────────────────┘                       ││
  │ └───────────────────────────────────────────────────────────┘│
  └─────────────────────────────────────────────────────────────┘
```

#### Page "Dashboard SEO"
```
  ┌─────────────────────────────────────────────────────────────┐
  │ SEO Dashboard                                     [Période ▼]│
  │                                                               │
  │ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────────────┐ │
  │ │Score moyen│ │ Articles │ │ Indexés  │ │ Pages orphelines │ │
  │ │  84/100   │ │   247    │ │   198    │ │      12          │ │
  │ │ ▲ +3 pts  │ │ ▲ +23   │ │ ▲ +18   │ │ ▼ -4            │ │
  │ └──────────┘ └──────────┘ └──────────┘ └──────────────────┘ │
  │                                                               │
  │ Score par langue :                                           │
  │ FR ████████████████████ 89/100 (142 articles)                │
  │ EN ██████████████████░░ 85/100 (67 articles)                 │
  │ DE ████████████████░░░░ 78/100 (23 articles)                 │
  │ ES ██████████████░░░░░░ 71/100 (8 articles)                  │
  │ PT ████████████░░░░░░░░ 65/100 (4 articles)                  │
  │ RU ██████████░░░░░░░░░░ 52/100 (2 articles)                  │
  │ ZH ████░░░░░░░░░░░░░░░░ 30/100 (1 article)                   │
  │                                                               │
  │ Top issues à corriger :                                      │
  │ ┌───────────────────────────────────────────────────────────┐│
  │ │ ⚠️ 12 articles sans liens internes                        ││
  │ │ ⚠️ 8 meta descriptions > 160 chars                        ││
  │ │ ⚠️ 5 articles sans hreflang complet                       ││
  │ │ ⚠️ 3 images sans alt text                                 ││
  │ │ ❌ 2 JSON-LD invalides                                    ││
  │ │ [Voir tout] [Corriger automatiquement]                    ││
  │ └───────────────────────────────────────────────────────────┘│
  │                                                               │
  │ Hreflang Coverage :                                          │
  │ ┌───────────────────────────────────────────────────────────┐│
  │ │        FR    EN    DE    ES    PT    RU    ZH    AR    HI ││
  │ │ FR     —     ✅    ✅    ⚠️    ⚠️    ❌    ❌    ❌    ❌ ││
  │ │ EN     ✅    —     ✅    ⚠️    ❌    ❌    ❌    ❌    ❌ ││
  │ │ DE     ✅    ✅    —     ❌    ❌    ❌    ❌    ❌    ❌ ││
  │ │ (✅ = 100%, ⚠️ = partiel, ❌ = manquant)                  ││
  │ └───────────────────────────────────────────────────────────┘│
  │                                                               │
  │ Maillage interne (graphe) :                                  │
  │ ┌───────────────────────────────────────────────────────────┐│
  │ │        [Graphe interactif D3.js / force-directed]         ││
  │ │   Noeuds = articles, liens = maillage interne             ││
  │ │   Couleur = score SEO, taille = nb liens entrants         ││
  │ └───────────────────────────────────────────────────────────┘│
  └─────────────────────────────────────────────────────────────┘
```

#### Page "Campagne de contenu"
```
  ┌─────────────────────────────────────────────────────────────┐
  │ Créer une campagne de contenu                               │
  │                                                               │
  │ Nom: [Campagne Allemagne Q2 2026                          ] │
  │                                                               │
  │ Type: ○ Articles thématiques                                 │
  │       ● Couverture pays (1 article par thème × pays)        │
  │       ○ Pillar + clusters                                    │
  │       ○ Comparatifs série                                    │
  │       ○ Mix personnalisé                                     │
  │                                                               │
  │ Configuration :                                              │
  │ ┌───────────────────────────────────────────────────────────┐│
  │ │ Pays: [Allemagne ▼]                                       ││
  │ │                                                           ││
  │ │ Thèmes à couvrir (cocher) :                               ││
  │ │ ☑ Visa & immigration        ☑ Logement                   ││
  │ │ ☑ Emploi & travail          ☑ Santé & assurance          ││
  │ │ ☑ Fiscalité                 ☑ Éducation & écoles         ││
  │ │ ☑ Banque & finances         ☐ Culture & intégration      ││
  │ │ ☐ Transport                 ☐ Retraite                   ││
  │ │                                                           ││
  │ │ Langues: ☑ FR ☑ EN ☑ DE ☐ ES ☐ PT                       ││
  │ │                                                           ││
  │ │ Planning: Générer [2] articles/jour pendant [4] jours    ││
  │ │ Début: [28/03/2026]                                       ││
  │ │                                                           ││
  │ │ Budget estimé: 8 articles × 3 langues × ~$0.45 = ~$10.80 ││
  │ └───────────────────────────────────────────────────────────┘│
  │                                                               │
  │ Aperçu des articles qui seront générés :                     │
  │ ┌───────────────────────────────────────────────────────────┐│
  │ │ 1. Guide visa et immigration Allemagne 2026     FR/EN/DE ││
  │ │ 2. Trouver un logement en Allemagne             FR/EN/DE ││
  │ │ 3. Travailler en Allemagne : guide emploi       FR/EN/DE ││
  │ │ 4. Assurance santé expatrié Allemagne            FR/EN/DE ││
  │ │ 5. Fiscalité des expatriés en Allemagne          FR/EN/DE ││
  │ │ 6. Écoles internationales en Allemagne           FR/EN/DE ││
  │ │ 7. Ouvrir un compte bancaire en Allemagne        FR/EN/DE ││
  │ │ 8. Comparatif : expatriation Allemagne vs France FR/EN/DE ││
  │ └───────────────────────────────────────────────────────────┘│
  │                                                               │
  │      ┌─────────────────────────────────┐                     │
  │      │ 🚀 Lancer la campagne (8 articles)│                   │
  │      └─────────────────────────────────┘                     │
  └─────────────────────────────────────────────────────────────┘
```

#### Page "Coûts IA"
```
  ┌─────────────────────────────────────────────────────────────┐
  │ Coûts IA                                      [Mars 2026 ▼] │
  │                                                               │
  │ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────────────┐ │
  │ │Aujourd'hui│ │ Semaine  │ │  Mois    │ │  Budget restant  │ │
  │ │  $4.23    │ │  $28.70  │ │ $142.50  │ │ $857.50 / $1000  │ │
  │ └──────────┘ └──────────┘ └──────────┘ └──────────────────┘ │
  │                                                               │
  │ Par service (graphe empilé) :                                │
  │ ┌───────────────────────────────────────────────────────────┐│
  │ │ [Graphe Recharts: barres empilées par jour]               ││
  │ │ ██ GPT-4o (articles)   █ GPT-4o-mini (traductions)       ││
  │ │ ██ Perplexity (recherche)  █ DALL-E (images)             ││
  │ │ █ Claude (emails prospection)                             ││
  │ └───────────────────────────────────────────────────────────┘│
  │                                                               │
  │ Détail par type de contenu :                                 │
  │ ┌────────────────┬────────┬────────┬──────────┬────────────┐│
  │ │ Type           │ Nombre │ Tokens │ Coût     │ Coût moyen ││
  │ ├────────────────┼────────┼────────┼──────────┼────────────┤│
  │ │ Articles       │ 23     │ 485K   │ $89.50   │ $3.89      ││
  │ │ Traductions    │ 41     │ 320K   │ $24.30   │ $0.59      ││
  │ │ Comparatifs    │ 5      │ 98K    │ $15.20   │ $3.04      ││
  │ │ Emails prosp.  │ 156    │ 78K    │ $8.50    │ $0.05      ││
  │ │ Recherche IA   │ 34     │ 45K    │ $5.00    │ $0.15      ││
  │ ├────────────────┼────────┼────────┼──────────┼────────────┤│
  │ │ TOTAL          │ 259    │ 1.03M  │ $142.50  │            ││
  │ └────────────────┴────────┴────────┴──────────┴────────────┘│
  └─────────────────────────────────────────────────────────────┘
```

### 5.4 Composants UI réutilisables à créer
```
ContentCard — Carte article avec aperçu, score SEO, statut, actions
SeoScoreBadge — Badge circulaire avec score coloré (rouge/orange/vert)
SeoScoreBreakdown — Détail du score SEO (10 critères)
GenerationProgress — Barre de progression 15 étapes temps réel
LanguagePicker — Sélecteur multi-langues avec drapeaux
CountryPicker — Sélecteur pays avec recherche
KeywordInput — Input tags pour mots-clés (ajout/suppression)
HreflangMatrix — Tableau croisé langues × articles
ContentEditor — Wrapper TipTap avec toolbar custom
FaqEditor — Editeur FAQ drag-and-drop
PublishScheduler — Calendrier + heure de publication
CostEstimator — Estimation coût en temps réel
BudgetGauge — Jauge budget IA (aujourd'hui/mois)
InternalLinkGraph — Graphe interactif maillage (D3.js ou react-force-graph)
ContentTimeline — Timeline de génération/publication
QualityChecklist — Checklist pré-publication
ImagePicker — Galerie Unsplash + DALL-E intégrée
SlugEditor — Editeur slugs multilingues avec aperçu URL
JsonLdPreview — Aperçu + validation JSON-LD
SitemapViewer — Visualisation sitemap dynamique
```

---

## 6. REGLES TECHNIQUES D'IMPLEMENTATION

### 6.1 Base de données
```
- PostgreSQL 16 (GARDER — pas migrer vers MySQL)
- Toutes les nouvelles tables utilisent UUID comme primary key
- Soft deletes sur tous les modèles de contenu
- Indexes sur : slug+language (unique composite), status, published_at, quality_score
- Foreign keys avec cascade delete où approprié
- JSONB pour les champs flexibles (json_ld, hreflang_map, entities_json)
- Full-text search PostgreSQL (tsvector) pour la recherche dans le contenu
```

### 6.2 API
```
- Préfixer toutes les nouvelles routes : /api/content/*, /api/seo/*, /api/publishing/*
- Versionning : /api/v1/ (prêt pour v2 si besoin)
- Pagination cursor sur toutes les listes
- Rate limiting : 60 req/min (auth), 10 req/min (génération)
- Réponses JSON standardisées : { data, meta, links }
- Validation FormRequest sur tous les endpoints
- Resource classes pour la sérialisation
```

### 6.3 Queue & Workers
```
Ajouter 2 workers Docker :
  content-generator  — queue "content" (timeout 600s, tries 3, memory 512MB)
  publication-worker — queue "publication" (timeout 120s, tries 5)

Jobs :
  GenerateArticleJob (queue: content, timeout: 600s)
  GenerateComparativeJob (queue: content, timeout: 300s)
  GenerateLandingJob (queue: content, timeout: 300s)
  GenerateTranslationJob (queue: content, timeout: 180s)
  AnalyzeSeoJob (queue: default, timeout: 60s)
  PublishContentJob (queue: publication, timeout: 120s)
  GenerateSitemapJob (queue: default, timeout: 60s)
  SubmitIndexNowJob (queue: default, timeout: 30s)
```

### 6.4 Services IA
```
Réutiliser le pattern existant de AiEmailGenerationService :
  - Config via .env (API keys, modèles, timeouts)
  - Retry avec exponential backoff
  - Cost tracking par appel
  - Cache des réponses identiques
  - Logs détaillés (tokens, durée, coût)

Nouveaux services :
  OpenAiContentService — extends base AI service, prompt management
  PerplexityResearchService — FUSIONNER avec PerplexitySearchService existant
  DalleImageService — génération images
  UnsplashService — recherche images stock
  TranslationService — traductions multi-langues
  SeoAnalysisService — analyse SEO complète (NATIF, pas externe)
  HreflangService — gestion hreflang bidirectionnel
  InternalLinkingService — maillage interne intelligent
  SitemapService — génération sitemap XML dynamique
  IndexNowService — soumission moteurs de recherche
  SlugService — génération slugs localisés
  JsonLdService — génération/validation structured data
  ContentPublisher — publication multi-plateforme
  FirestorePublisher — connecteur spécifique Firebase/Firestore
```

### 6.5 Frontend
```
- Réutiliser le design system existant (Tailwind dark theme, même palette)
- Ajouter TipTap (@tiptap/react, @tiptap/starter-kit) pour l'éditeur WYSIWYG
- Ajouter react-beautiful-dnd pour le drag-and-drop FAQ
- Ajouter D3.js ou react-force-graph pour le graphe de maillage
- Ajouter react-day-picker pour le calendrier de publication
- WebSocket (Laravel Echo + Pusher) pour le suivi temps réel de la génération
- Zustand stores séparés : useContentStore, useSeoStore, usePublishingStore
- React Query (TanStack Query) pour le cache API et les mutations
- Code splitting par section (lazy loading des pages Content/SEO/Publishing)
```

### 6.6 Variables d'environnement à ajouter
```env
# OpenAI
OPENAI_API_KEY=sk-xxxxxx
OPENAI_DEFAULT_MODEL=gpt-4o
OPENAI_TRANSLATION_MODEL=gpt-4o-mini
OPENAI_TIMEOUT=180

# Perplexity (étendre config existante)
PERPLEXITY_CONTENT_MODEL=sonar

# Images
DALLE_MODEL=dall-e-3
DALLE_TIMEOUT=180
UNSPLASH_ACCESS_KEY=xxxxxx

# Budget IA
AI_DAILY_BUDGET=50
AI_MONTHLY_BUDGET=1000
AI_ALERT_EMAIL=admin@sos-expat.com
AI_BLOCK_ON_EXCEEDED=false

# SEO
INDEXNOW_ENABLED=true
INDEXNOW_KEY=votre-cle-indexnow
INDEXNOW_DELAY=60
SITE_URL=https://sos-expat.com

# Publication Firebase
FIREBASE_PROJECT_ID=sos-urgently-ac307
FIREBASE_SERVICE_ACCOUNT_KEY=storage/firebase-key.json

# Publication WordPress (optionnel)
WORDPRESS_URL=
WORDPRESS_USERNAME=
WORDPRESS_APP_PASSWORD=

# Broadcasting (étendre config existante Soketi)
# Déjà configuré dans l'Influenceurs Tracker
```

---

## 7. PLAN D'IMPLEMENTATION (ORDRE DE PRIORITE)

### Phase 1 : Fondations (backend)
```
1. Migrations DB : créer les ~25 nouvelles tables PostgreSQL
2. Modèles Eloquent : créer les modèles avec relations
3. Services IA : OpenAiContentService, DalleImageService, UnsplashService
4. Service SEO natif : SeoAnalysisService (scoring, meta, keywords)
5. Service hreflang : HreflangService
6. Service JSON-LD : JsonLdService
7. Tests unitaires pour chaque service
```

### Phase 2 : Génération de contenu
```
8. ArticleGenerationService (pipeline 15 étapes)
9. ComparativeGenerationService
10. LandingGenerationService
11. TranslationService
12. Jobs de génération (queue Redis)
13. API endpoints CRUD + génération
14. Tests d'intégration
```

### Phase 3 : SEO complet
```
15. SlugService (slugs multilingues)
16. InternalLinkingService (maillage automatique)
17. SitemapService (XML dynamique)
18. IndexNowService
19. SEO scoring dashboard API
20. Hreflang matrix API
```

### Phase 4 : Publication
```
21. ContentPublisher (orchestration)
22. FirestorePublisher (connecteur SOS-Expat)
23. PublicationScheduler
24. AntiSpamChecker
25. Webhook publisher
26. Export PDF/Word
```

### Phase 5 : Frontend
```
27. Navigation mise à jour (sidebar étendue)
28. Pages contenu (liste, création, édition)
29. Editeur TipTap intégré
30. Page génération temps réel (WebSocket)
31. Dashboard SEO
32. Hreflang manager
33. Publication scheduler UI
34. Coûts IA dashboard
35. Image picker (Unsplash + DALL-E)
36. Campagnes de contenu UI
```

### Phase 6 : Polish & qualité
```
37. Golden examples system
38. Brand compliance checking
39. A/B testing prompts
40. Quality score weights configurables
41. Graphe maillage interne (D3.js)
42. Raccourcis clavier
43. Tests E2E
44. Documentation API (Scribe)
```

---

## 8. CONTRAINTES & REGLES

```
1. NE PAS casser l'existant — l'Influenceurs Tracker doit continuer à fonctionner parfaitement
2. NE PAS dupliquer les services existants — fusionner (ex: PerplexitySearchService)
3. NE PAS créer un 2ème système d'auth — utiliser le User/Sanctum existant
4. NE PAS utiliser MySQL — rester sur PostgreSQL 16
5. NE PAS déléguer le SEO à un service externe — tout doit être natif
6. Garder le dark theme et la palette existante
7. Chaque nouveau service doit avoir des tests
8. Chaque nouveau endpoint doit avoir une FormRequest validation
9. Le budget IA doit être configurable et monitoré
10. Les traductions doivent être bidirectionnelles (hreflang croisé)
11. Le système de publication doit supporter au minimum Firebase/Firestore
12. Tout le contenu généré passe par un état "draft" avant publication
13. Le frontend doit utiliser le même stack (React 19 + TypeScript + Tailwind + Vite)
14. Les WebSockets doivent utiliser le Soketi/Pusher existant
15. Docker Compose doit rester un seul fichier avec tous les services
```

---

## 9. DELIVERABLES ATTENDUS

```
Pour chaque phase, livrer :
1. Code source complet (backend + frontend)
2. Migrations de base de données
3. Tests (unitaires + intégration)
4. Variables d'environnement documentées
5. Docker Compose mis à jour
6. Routes API documentées
```

---

## 10. CRITERES DE SUCCES

```
✅ Un utilisateur peut créer un article de A à Z sans quitter l'interface
✅ L'article est généré avec FAQ, images, liens, meta tags, JSON-LD
✅ Le score SEO est calculé nativement et affiché en temps réel
✅ Les traductions sont lancées automatiquement dans les langues choisies
✅ Les hreflang sont bidirectionnels et complets
✅ L'article peut être publié sur Firebase/Firestore en 1 clic
✅ Le sitemap XML se met à jour automatiquement
✅ IndexNow notifie les moteurs de recherche
✅ Le maillage interne se fait automatiquement
✅ Les coûts IA sont trackés et budgetés
✅ Le CRM existant (prospects, outreach, scraping) fonctionne toujours parfaitement
✅ L'UX est fluide, professionnelle et intuitive
✅ Le tout tourne dans un seul Docker Compose sur le VPS Hetzner
```
