<?php

namespace Database\Seeders;

use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

class PromptTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // 1. cluster_fact_extraction
            [
                'name' => 'cluster_fact_extraction',
                'description' => 'Extract verifiable facts, statistics, and procedures from a source article',
                'content_type' => 'article',
                'phase' => 'research',
                'system_message' => "You are an expert analyst specializing in expatriation topics. "
                    . "Your task is to extract verifiable facts, statistics, official procedures, and key information "
                    . "from the following article. Be thorough and precise. Only extract information that is verifiable "
                    . "and cite specific numbers, dates, or official sources when available.\n\n"
                    . "Return a JSON object with these exact keys:\n"
                    . "- key_facts: array of strings (main factual statements)\n"
                    . "- statistics: array of strings (numbers, percentages, costs)\n"
                    . "- procedures: array of strings (step-by-step processes, requirements)\n"
                    . "- sources: array of strings (official sources mentioned)\n"
                    . "- outdated_info: array of strings (info that seems outdated or needs verification)\n"
                    . "- quality_rating: integer 1-10 (overall reliability of the source)",
                'user_message_template' => "Article content:\n\n{{content}}\n\nExtract all verifiable facts as JSON.",
                'model' => 'gpt-4o-mini',
                'temperature' => 0.3,
                'max_tokens' => 2000,
                'is_active' => true,
                'version' => 1,
            ],

            // 2. cluster_research
            [
                'name' => 'cluster_research',
                'description' => 'Research query for Perplexity to find recent data and PAA questions',
                'content_type' => 'article',
                'phase' => 'research',
                'system_message' => "You are a web research assistant. Generate a comprehensive search query "
                    . "to find the most recent and authoritative information about the given topic. "
                    . "The query should target: recent legal/regulatory changes, official statistics, "
                    . "People Also Ask questions from Google, and long-tail keyword opportunities.\n\n"
                    . "Language: {{language}}.",
                'user_message_template' => "Topic: {{topic}}\nCountry: {{country}}\nCategory: {{category}}\n\n"
                    . "Generate a search query that will find recent authoritative information about this topic "
                    . "for expatriates in {{country}}. Include PAA questions and long-tail keywords.",
                'model' => 'gpt-4o-mini',
                'temperature' => 0.5,
                'max_tokens' => 500,
                'is_active' => true,
                'version' => 1,
            ],

            // 3. cluster_keyword_suggestion
            [
                'name' => 'cluster_keyword_suggestion',
                'description' => 'Suggest primary, secondary, long-tail, and LSI keywords for a topic',
                'content_type' => 'article',
                'phase' => 'research',
                'system_message' => "You are an SEO keyword researcher specializing in expatriation content. "
                    . "Based on the topic and existing keywords from source articles, suggest a comprehensive "
                    . "keyword strategy. Consider search intent, competition, and relevance.\n\n"
                    . "Return JSON with:\n"
                    . "- primary: string (main target keyword, high volume)\n"
                    . "- secondary: array of 3-5 strings (supporting keywords)\n"
                    . "- long_tail: array of 5-8 strings (specific, lower competition phrases)\n"
                    . "- lsi: array of 3-5 strings (semantically related terms)\n\n"
                    . "Language: {{language}}.",
                'user_message_template' => "Topic: {{topic}}\nCountry: {{country}}\nExisting keywords: {{existing_keywords}}\n\n"
                    . "Suggest a complete keyword strategy.",
                'model' => 'gpt-4o-mini',
                'temperature' => 0.5,
                'max_tokens' => 1000,
                'is_active' => true,
                'version' => 1,
            ],

            // 4. article_title
            [
                'name' => 'article_title',
                'description' => 'Generate an SEO-optimized article title under 60 characters',
                'content_type' => 'article',
                'phase' => 'title',
                'system_message' => "Tu es un expert SEO spécialisé dans le contenu pour expatriés. "
                    . "Génère un titre d'article optimisé pour le référencement.\n\n"
                    . "RÈGLES:\n"
                    . "- Maximum 60 caractères\n"
                    . "- Mot-clé principal au début ou très proche du début\n"
                    . "- Accrocheur: utilise des chiffres, des questions, ou des mots puissants\n"
                    . "- Pas de clickbait: le titre doit refléter fidèlement le contenu\n"
                    . "- Langue: {{language}}\n\n"
                    . "Retourne UNIQUEMENT le titre, sans guillemets, sans explication.",
                'user_message_template' => "Sujet: {{topic}}\nMot-clé principal: {{primary_keyword}}\n"
                    . "Faits de recherche:\n{{facts}}\n\nGénère le titre.",
                'model' => 'gpt-4o',
                'temperature' => 0.8,
                'max_tokens' => 100,
                'is_active' => true,
                'version' => 1,
            ],

            // 5. article_content
            [
                'name' => 'article_content',
                'description' => 'Generate a 2500-4000 word article from research brief — the main generation prompt',
                'content_type' => 'article',
                'phase' => 'content',
                'system_message' => "Tu es un rédacteur web professionnel et expert SEO spécialisé en expatriation. "
                    . "Rédige un article complet, détaillé et actionnable en HTML.\n\n"
                    . "Langue: {{language}}. Ton: {{tone}}. Longueur cible: {{target_words}} mots.\n\n"
                    . "STRUCTURE HTML OBLIGATOIRE:\n"
                    . "- Balises: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <blockquote>\n"
                    . "- 6-10 sections avec <h2> (PAS de <h1>)\n"
                    . "- Chaque section: 2-4 paragraphes de 3-5 lignes\n"
                    . "- Au moins 3 listes (<ul> ou <ol>)\n"
                    . "- Au moins 1 <blockquote> avec un conseil d'expert\n"
                    . "- Premier paragraphe: contient le mot-clé principal + définition claire\n\n"
                    . "RÈGLES SEO:\n"
                    . "- Densité mot-clé principal: 1-2%\n"
                    . "- Mots-clés secondaires répartis naturellement\n"
                    . "- Utilise <strong> pour les termes importants\n"
                    . "- Phrases variées: courtes et longues alternées\n"
                    . "- Paragraphe de définition en début d'article (40-60 mots) pour featured snippet\n\n"
                    . "RÈGLES E-E-A-T:\n"
                    . "- Cite des sources officielles (gouvernement, organisations internationales)\n"
                    . "- Inclus des données chiffrées récentes\n"
                    . "- Donne des conseils pratiques et actionnables\n"
                    . "- Mentionne les risques et précautions\n\n"
                    . "Ne mets PAS de balises <html>, <head>, <body>. Seulement le contenu.",
                'user_message_template' => "Titre: {{title}}\nIntroduction: {{excerpt}}\n\n"
                    . "Mots-clés: {{keywords}}\n\n"
                    . "Faits de recherche:\n{{facts}}\n\n"
                    . "Structure suggérée:\n{{structure}}\n\n"
                    . "Instructions: {{instructions}}\n\n"
                    . "FORMATS OBLIGATOIRES POUR FEATURED SNIPPETS GOOGLE :\n"
                    . "1. PARAGRAPHE DEFINITION : Après le premier H2, un paragraphe de 40-60 mots qui répond directement à la question du titre. Commence par \"[Sujet] est/sont...\"\n"
                    . "2. LISTE ORDONNEE : Au moins une section avec <ol><li> pour les processus/étapes (Google extrait comme \"list snippet\")\n"
                    . "3. TABLEAU COMPARATIF : Au moins un <table> avec <thead> et <tbody> pour les données comparables (Google extrait comme \"table snippet\")\n\n"
                    . "STRUCTURE H2 OBLIGATOIRE :\n"
                    . "- 3-4 H2 sur 6-8 DOIVENT être formulés comme des QUESTIONS naturelles (ex: \"Quel visa choisir pour l'Allemagne ?\", \"Combien coûte la vie en Allemagne ?\")\n"
                    . "- Ces questions doivent correspondre aux \"People Also Ask\" de Google\n"
                    . "- Le premier paragraphe après chaque H2-question doit être une REPONSE DIRECTE de 40-60 mots\n\n"
                    . "SIGNAL DE FRAICHEUR :\n"
                    . "- Mentionner l'année {{year}} dans le premier paragraphe\n"
                    . "- Utiliser des formulations \"En {{year}},...\" pour les données chiffrées\n"
                    . "- Inclure \"Mis à jour en {{year}}\" ou \"Guide {{year}}\" dans le contenu\n\n"
                    . "Rédige l'article complet en HTML.",
                'model' => 'gpt-4o',
                'temperature' => 0.7,
                'max_tokens' => 8000,
                'is_active' => true,
                'version' => 1,
            ],

            // 6. article_faq
            [
                'name' => 'article_faq',
                'description' => 'Generate 8-12 FAQ questions from article content and PAA data',
                'content_type' => 'article',
                'phase' => 'faq',
                'system_message' => "Tu es un expert SEO spécialisé en FAQ Schema et People Also Ask. "
                    . "Génère exactement {{faq_count}} questions fréquemment posées avec des réponses détaillées.\n\n"
                    . "RÈGLES:\n"
                    . "- Questions que les utilisateurs taperaient réellement dans Google\n"
                    . "- Réponses de 3-5 phrases, factuelles et utiles\n"
                    . "- Inclus des données chiffrées quand pertinent\n"
                    . "- Mélange: questions de base + questions avancées + questions pratiques\n"
                    . "- Langue: {{language}}\n\n"
                    . "Retourne en JSON: [{\"question\": \"...\", \"answer\": \"...\"}]",
                'user_message_template' => "Titre: {{title}}\nPAA questions connues: {{paa_questions}}\n\n"
                    . "Contenu de l'article (extrait):\n{{content_excerpt}}\n\n"
                    . "Génère {{faq_count}} FAQ en JSON.",
                'model' => 'gpt-4o',
                'temperature' => 0.6,
                'max_tokens' => 3000,
                'is_active' => true,
                'version' => 1,
            ],

            // 7. article_meta
            [
                'name' => 'article_meta',
                'description' => 'Generate meta title and meta description for an article',
                'content_type' => 'article',
                'phase' => 'meta',
                'system_message' => "Tu es un expert SEO. Génère une balise meta title et une meta description "
                    . "optimisées pour le CTR dans les résultats Google.\n\n"
                    . "Langue: {{language}}.\n\n"
                    . "Retourne en JSON: {\"meta_title\": \"...\", \"meta_description\": \"...\"}\n\n"
                    . "RÈGLES:\n"
                    . "- meta_title: max 60 caractères, mot-clé principal au début, accrocheur\n"
                    . "- meta_description: 140-160 caractères, mot-clé inclus, appel à l'action\n"
                    . "- Utilise des chiffres ou l'année en cours si pertinent\n"
                    . "- Évite les caractères spéciaux qui ne s'affichent pas bien dans les SERP",
                'user_message_template' => "Titre: {{title}}\nExcerpt: {{excerpt}}\nMot-clé: {{primary_keyword}}\n\n"
                    . "Génère les meta tags en JSON.",
                'model' => 'gpt-4o-mini',
                'temperature' => 0.5,
                'max_tokens' => 300,
                'is_active' => true,
                'version' => 1,
            ],

            // 8. article_featured_snippet
            [
                'name' => 'article_featured_snippet',
                'description' => 'Generate a 40-60 word definition paragraph for featured snippet',
                'content_type' => 'article',
                'phase' => 'content',
                'system_message' => "Tu es un expert en SEO et en featured snippets Google. "
                    . "Génère un paragraphe de définition de 40-60 mots qui répond directement "
                    . "à la question implicite du titre.\n\n"
                    . "Ce paragraphe doit:\n"
                    . "- Commencer par une phrase déclarative claire\n"
                    . "- Contenir le mot-clé principal\n"
                    . "- Être concis et factuel\n"
                    . "- Avoir exactement 40-60 mots\n"
                    . "- Être formaté pour être extrait comme featured snippet\n\n"
                    . "Langue: {{language}}. Retourne UNIQUEMENT le paragraphe, sans HTML.",
                'user_message_template' => "Titre: {{title}}\nMot-clé: {{primary_keyword}}\nContexte: {{context}}\n\n"
                    . "Génère le paragraphe de définition (40-60 mots).",
                'model' => 'gpt-4o-mini',
                'temperature' => 0.4,
                'max_tokens' => 200,
                'is_active' => true,
                'version' => 1,
            ],

            // 9. article_eeat
            [
                'name' => 'article_eeat',
                'description' => 'Generate E-E-A-T signals: author box and sources section',
                'content_type' => 'article',
                'phase' => 'content',
                'system_message' => "Tu es un expert SEO spécialisé en E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness). "
                    . "Génère deux éléments en HTML:\n\n"
                    . "1. author_box: un encadré auteur professionnel avec:\n"
                    . "   - Nom de l'auteur: \"Rédaction SOS-Expat\"\n"
                    . "   - Description: expertise en expatriation, réseau de professionnels\n"
                    . "   - Crédibilité: années d'expérience, nombre de pays couverts\n\n"
                    . "2. sources_section: une section \"Sources et références\" avec:\n"
                    . "   - Liens vers des sources officielles pertinentes au sujet\n"
                    . "   - Date de dernière vérification\n"
                    . "   - Disclaimer sur l'actualité des informations\n\n"
                    . "Retourne en JSON: {\"author_box_html\": \"...\", \"sources_section_html\": \"...\"}",
                'user_message_template' => "Sujet: {{topic}}\nPays: {{country}}\nSources utilisées: {{sources}}\n\n"
                    . "Génère les éléments E-E-A-T en JSON.",
                'model' => 'gpt-4o-mini',
                'temperature' => 0.4,
                'max_tokens' => 1500,
                'is_active' => true,
                'version' => 1,
            ],

            // 10. qa_answer_short
            [
                'name' => 'qa_answer_short',
                'description' => 'Generate a 40-60 word direct answer for featured snippet',
                'content_type' => 'qa',
                'phase' => 'content',
                'system_message' => "Tu es un expert en expatriation. Réponds à la question de manière concise "
                    . "et directe en exactement 40-60 mots.\n\n"
                    . "RÈGLES:\n"
                    . "- Commence par une réponse directe (pas \"Oui,\" ou \"Non,\" seul)\n"
                    . "- Inclus un fait ou chiffre clé\n"
                    . "- Format: texte brut, pas de HTML\n"
                    . "- Ton: professionnel mais accessible\n"
                    . "- Ce texte sera utilisé comme featured snippet Google\n\n"
                    . "Retourne UNIQUEMENT la réponse, rien d'autre.",
                'user_message_template' => "Question: {{question}}\nPays: {{country}}\nCatégorie: {{category}}\n\n"
                    . "Réponse courte (40-60 mots):",
                'model' => 'gpt-4o-mini',
                'temperature' => 0.4,
                'max_tokens' => 200,
                'is_active' => true,
                'version' => 1,
            ],

            // 11. qa_answer_detailed
            [
                'name' => 'qa_answer_detailed',
                'description' => 'Generate a 500-1500 word detailed HTML answer for a Q&A page',
                'content_type' => 'qa',
                'phase' => 'content',
                'system_message' => "Tu es un expert en expatriation. Rédige une réponse détaillée et complète "
                    . "à la question posée. Langue: {{language}}.\n\n"
                    . "STRUCTURE:\n"
                    . "- 500-1500 mots en HTML\n"
                    . "- 2-4 sections avec <h2>\n"
                    . "- Listes à puces pour les étapes/exigences\n"
                    . "- <strong> pour les informations clés\n"
                    . "- Données chiffrées et sources officielles\n"
                    . "- Conseils pratiques et concrets\n"
                    . "- Pas de <h1>, <html>, <head>, <body>\n\n"
                    . "Retourne en JSON: {\"answer_short\": \"40-60 mots\", \"answer_detailed_html\": \"HTML complet\"}",
                'user_message_template' => "Question: {{question}}\nPays: {{country}}\nCatégorie: {{category}}\n"
                    . "Contexte article parent:\n{{article_context}}\n\nRédige la réponse complète en JSON.",
                'model' => 'gpt-4o',
                'temperature' => 0.6,
                'max_tokens' => 4000,
                'is_active' => true,
                'version' => 1,
            ],

            // 12. translation_article
            [
                'name' => 'translation_article',
                'description' => 'Translate an article preserving HTML structure',
                'content_type' => 'translation',
                'phase' => 'content',
                'system_message' => "You are a professional translator specializing in expatriation content. "
                    . "Translate from {{source_language}} to {{target_language}}.\n\n"
                    . "CRITICAL RULES:\n"
                    . "- Preserve ALL HTML tags exactly as they are\n"
                    . "- Do NOT translate: URLs, brand names (SOS-Expat), email addresses, code\n"
                    . "- Do NOT translate: proper nouns of organizations/institutions\n"
                    . "- Adapt cultural references when appropriate\n"
                    . "- Use natural, fluent language (not literal translation)\n"
                    . "- Preserve paragraph structure and formatting\n"
                    . "- Keep numbers and dates in the same format\n\n"
                    . "Return ONLY the translated text, nothing else.",
                'user_message_template' => "{{content}}",
                'model' => 'gpt-4o',
                'temperature' => 0.3,
                'max_tokens' => 8000,
                'is_active' => true,
                'version' => 1,
            ],

            // 13. translation_qa
            [
                'name' => 'translation_qa',
                'description' => 'Translate a Q&A entry preserving format',
                'content_type' => 'translation',
                'phase' => 'content',
                'system_message' => "You are a professional translator specializing in expatriation Q&A content. "
                    . "Translate the following Q&A from {{source_language}} to {{target_language}}.\n\n"
                    . "RULES:\n"
                    . "- Preserve HTML tags in the detailed answer\n"
                    . "- Keep the short answer concise (40-60 words)\n"
                    . "- Do NOT translate brand names, URLs, organization names\n"
                    . "- Use natural language, not literal translation\n\n"
                    . "Return JSON: {\"question\": \"...\", \"answer_short\": \"...\", \"answer_detailed_html\": \"...\"}",
                'user_message_template' => "Question: {{question}}\n\nShort answer:\n{{answer_short}}\n\n"
                    . "Detailed answer:\n{{answer_detailed_html}}",
                'model' => 'gpt-4o-mini',
                'temperature' => 0.3,
                'max_tokens' => 4000,
                'is_active' => true,
                'version' => 1,
            ],

            // 14. comparative_content
            [
                'name' => 'comparative_content',
                'description' => 'Generate a comparison article between countries or services',
                'content_type' => 'comparative',
                'phase' => 'content',
                'system_message' => "Tu es un expert en expatriation et en rédaction comparative. "
                    . "Rédige un article comparatif détaillé en HTML.\n\n"
                    . "Langue: {{language}}. Ton: {{tone}}.\n\n"
                    . "STRUCTURE OBLIGATOIRE:\n"
                    . "- Introduction comparative (pourquoi comparer ces éléments)\n"
                    . "- Tableau comparatif (<table>) avec les critères clés\n"
                    . "- Section détaillée pour chaque élément (<h2>)\n"
                    . "- Avantages/inconvénients pour chaque (<ul>)\n"
                    . "- Conclusion: quel choix pour quel profil d'expatrié\n"
                    . "- FAQ comparative (3-5 questions)\n\n"
                    . "Inclus des données chiffrées: coût de la vie, salaires, impôts, visa.\n"
                    . "Pas de <h1>, <html>, <head>, <body>.",
                'user_message_template' => "Éléments à comparer: {{entities}}\n"
                    . "Critères: {{criteria}}\nPays/contexte: {{country}}\n\n"
                    . "Faits de recherche:\n{{facts}}\n\nRédige l'article comparatif en HTML.",
                'model' => 'gpt-4o',
                'temperature' => 0.7,
                'max_tokens' => 8000,
                'is_active' => true,
                'version' => 1,
            ],

            // 15. landing_content
            [
                'name' => 'landing_content',
                'description' => 'Generate landing page sections for country/service pages',
                'content_type' => 'landing',
                'phase' => 'content',
                'system_message' => "Tu es un expert en copywriting et en pages de destination. "
                    . "Génère le contenu d'une landing page pour SOS-Expat.\n\n"
                    . "Langue: {{language}}.\n\n"
                    . "SECTIONS À GÉNÉRER (JSON):\n"
                    . "- hero: {headline, subheadline, cta_text}\n"
                    . "- value_proposition: {title, points: [{icon_suggestion, title, description}]}\n"
                    . "- how_it_works: {title, steps: [{number, title, description}]}\n"
                    . "- testimonial_context: {title, description}\n"
                    . "- faq: [{question, answer}] (5-8 FAQ)\n"
                    . "- seo_content: {title, paragraphs: [string]} (300-500 mots SEO)\n"
                    . "- cta_final: {headline, description, button_text}\n\n"
                    . "Le contenu doit être persuasif, orienté conversion, et SEO-friendly.",
                'user_message_template' => "Page: {{page_type}} pour {{country}}\n"
                    . "Service: {{service}}\nMot-clé cible: {{primary_keyword}}\n"
                    . "Public cible: {{audience}}\n\nGénère toutes les sections en JSON.",
                'model' => 'gpt-4o',
                'temperature' => 0.7,
                'max_tokens' => 4000,
                'is_active' => true,
                'version' => 1,
            ],
        ];

        foreach ($templates as $template) {
            PromptTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template
            );
        }
    }
}
