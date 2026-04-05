<?php

/**
 * SOS-Expat Knowledge Base — Source of truth for ALL AI content generation.
 *
 * This document is injected into EVERY content generation prompt to ensure
 * accuracy and consistency across all articles, Q/R, fiches, comparatives, etc.
 *
 * RULES FOR AI:
 * - NEVER invent data not in this document
 * - NEVER change prices, durations, or commission rates
 * - ALWAYS use the exact service name "SOS-Expat" (with hyphen)
 * - ALWAYS mention "197 pays" and "9 langues" when relevant
 * - NEVER say SOS-Expat is free (it's a paid service)
 * - NEVER say SOS-Expat provides legal advice (it CONNECTS with lawyers)
 * - NEVER confuse lawyer ($49/20min) with expat expert ($19/30min)
 */

return [

    // ═══════════════════════════════════════════════════════
    // 1. IDENTITY
    // ═══════════════════════════════════════════════════════

    'identity' => [
        'name' => 'SOS-Expat',
        'tagline_fr' => 'Parlez a un avocat ou expert local dans votre langue en moins de 5 minutes',
        'tagline_en' => 'Talk to a lawyer or local expert in your language in under 5 minutes',
        'website' => 'https://sos-expat.com',
        'founded' => 2026,
        'type' => 'Plateforme de mise en relation telephonique internationale',

        'what_it_is' => [
            'Une plateforme qui CONNECTE les personnes a l\'etranger avec des avocats et experts locaux par telephone',
            'Service disponible 24h/24, 7j/7 dans 197 pays et 9 langues',
            'Mise en relation en moins de 5 minutes',
        ],

        'what_it_is_NOT' => [
            'PAS un cabinet d\'avocats (SOS-Expat ne fournit PAS de conseil juridique)',
            'PAS une assurance voyage (SOS-Expat ne rembourse rien)',
            'PAS un consulat ou une ambassade',
            'PAS un service gratuit (c\'est payant)',
            'PAS un chatbot ou une IA — ce sont de VRAIS humains au telephone',
        ],
    ],

    // ═══════════════════════════════════════════════════════
    // 2. SERVICES & PRICING
    // ═══════════════════════════════════════════════════════

    'services' => [
        'lawyer' => [
            'name_fr' => 'Appel Avocat',
            'name_en' => 'Lawyer Call',
            'price_eur' => 49,
            'price_usd' => 49,
            'duration_minutes' => 20,
            'description' => 'Mise en relation avec un avocat verifie dans le pays concerne. Specialites : droit de l\'immigration, droit du travail, droit commercial, fiscalite internationale, droit immobilier.',
        ],
        'expat' => [
            'name_fr' => 'Appel Expert Local',
            'name_en' => 'Local Expert Call',
            'price_eur' => 19,
            'price_usd' => 19,
            'duration_minutes' => 30,
            'description' => 'Mise en relation avec un expatrie experimente vivant dans le pays concerne. Aide pratique : logement, banque, transport, vie quotidienne, communaute locale, demarches administratives.',
        ],
    ],

    // ═══════════════════════════════════════════════════════
    // 3. COVERAGE
    // ═══════════════════════════════════════════════════════

    'coverage' => [
        'countries' => 197,
        'languages' => ['fr', 'en', 'es', 'de', 'ru', 'pt', 'zh', 'hi', 'ar'],
        'language_names' => [
            'fr' => 'Francais',
            'en' => 'English',
            'es' => 'Espanol',
            'de' => 'Deutsch',
            'ru' => 'Russkij',
            'pt' => 'Portugues',
            'zh' => 'Zhongwen (Mandarin)',
            'hi' => 'Hindi',
            'ar' => 'Arabiyya',
        ],
        'availability' => '24/7 (24 heures sur 24, 7 jours sur 7)',
        'response_time' => 'Moins de 5 minutes',
    ],

    // ═══════════════════════════════════════════════════════
    // 4. HOW IT WORKS
    // ═══════════════════════════════════════════════════════

    'how_it_works' => [
        'step_1' => 'L\'utilisateur choisit son besoin (avocat ou expert local) et son pays',
        'step_2' => 'SOS-Expat identifie un prestataire disponible dans le pays et la langue',
        'step_3' => 'Mise en relation telephonique en moins de 5 minutes',
        'step_4' => 'Conversation directe 1-a-1 avec le prestataire (20 min avocat, 30 min expert)',
    ],

    // ═══════════════════════════════════════════════════════
    // 5. TARGET AUDIENCE
    // ═══════════════════════════════════════════════════════

    'audience' => [
        'primary' => [
            'Expatries (installes a l\'etranger)',
            'Voyageurs et vacanciers',
            'Digital nomads',
            'Etudiants a l\'etranger',
        ],
        'secondary' => [
            'Retraites a l\'etranger',
            'Investisseurs internationaux',
            'Travailleurs detaches et frontaliers',
            'Voyageurs d\'affaires',
            'Soignants et missionnaires humanitaires',
            'Refugies et demandeurs d\'asile',
        ],
        'key_message' => 'SOS-Expat s\'adresse a TOUTE personne de TOUTE nationalite qui se trouve a l\'etranger et a besoin d\'aide locale.',
    ],

    // ═══════════════════════════════════════════════════════
    // 6. AFFILIATE PROGRAMS
    // ═══════════════════════════════════════════════════════

    'programs' => [
        'chatter' => [
            'name' => 'Programme Chatter',
            'description' => 'Partager SOS-Expat sur les reseaux sociaux et gagner des commissions sur chaque appel',
            'commission_client_lawyer' => '$5 par appel avocat',
            'commission_client_expat' => '$3 par appel expert',
            'commission_n1' => '$1 par appel d\'un filleul',
            'commission_n2' => '$0.50 par appel d\'un filleul de filleul',
            'activation_bonus' => '$5 a l\'activation',
            'telegram_bonus' => '$50 (debloque a $150 de commissions)',
        ],
        'influencer' => [
            'name' => 'Programme Influenceur',
            'description' => 'Createurs de contenu : monetisez votre audience avec SOS-Expat',
        ],
        'blogger' => [
            'name' => 'Programme Blogueur',
            'description' => 'Integrez le widget SOS-Expat sur votre blog et gagnez $10 par appel client',
            'commission_per_call' => '$10 par appel client via votre lien',
            'commission_recruitment' => '$5 par appel des prestataires recrutes (sans limite de temps)',
        ],
        'group_admin' => [
            'name' => 'Programme Admin Groupe',
            'description' => 'Monetisez vos groupes WhatsApp/Telegram/Facebook avec SOS-Expat',
        ],
        'affiliate' => [
            'name' => 'Programme Affiliation',
            'description' => 'Liens d\'affiliation pour services et outils expatries',
            'commission_rate' => '5%',
        ],
    ],

    // ═══════════════════════════════════════════════════════
    // 7. BRAND VOICE
    // ═══════════════════════════════════════════════════════

    'brand_voice' => [
        'tone' => 'Professionnel mais accessible. Comme un ami expert qui donne des conseils pratiques.',
        'never_say' => [
            'Ne jamais dire "SOS Expat" sans le tiret (c\'est "SOS-Expat")',
            'Ne jamais dire que SOS-Expat est gratuit',
            'Ne jamais dire que SOS-Expat donne des conseils juridiques (il CONNECTE avec des avocats)',
            'Ne jamais utiliser un ton alarmiste ou anxiogene',
            'Ne jamais denigrer les ambassades, assurances ou concurrents',
            'Ne jamais promettre des resultats specifiques (chaque situation est unique)',
        ],
        'always_say' => [
            'Toujours mentionner "197 pays" et "9 langues" quand c\'est pertinent',
            'Toujours preciser "en moins de 5 minutes" pour le temps de mise en relation',
            'Toujours differencier avocat (49€/20min) et expert local (19€/30min)',
            'Toujours rappeler que le service est disponible 24h/24, 7j/7',
            'Toujours inclure un CTA vers sos-expat.com en fin d\'article',
        ],
    ],

    // ═══════════════════════════════════════════════════════
    // 8. CONTENT RULES PER TYPE
    // ═══════════════════════════════════════════════════════

    'content_rules' => [
        'fiches_pays' => 'Guide complet d\'un pays. Vue d\'ensemble large (10 sections). NE PAS approfondir un seul sujet — c\'est le role des articles mots-cles.',
        'fiches_expat' => 'Guide expatriation specifique au pays. Visa, travail, logement, fiscalite, sante. NE PAS repeter la fiche pays generale.',
        'fiches_vacances' => 'Guide vacances specifique au pays. Visa touriste, budget, securite, sante voyageur. NE PAS repeter la fiche expat.',
        'art_mots_cles' => 'Article APPROFONDI sur 1 sujet precis. 1500-2500 mots. NE PAS etre un guide general du pays. Aller en PROFONDEUR avec donnees chiffrees, etapes, exemples concrets.',
        'chatters' => 'Article de recrutement chatter. Avantages du programme, missions, revenus. CTA inscription. NE PAS etre un guide d\'expatriation.',
        'influenceurs' => 'Article de recrutement influenceur/blogueur. Monetisation audience, widget, commissions. CTA inscription.',
        'admin_groupes' => 'Article de recrutement admin groupe. Monetisation communaute, recrutement. CTA inscription.',
        'avocats' => 'Article pour attirer des avocats prestataires. Clientele internationale, appels remuneres, flexibilite. CTA inscription prestataire.',
        'expats_aidants' => 'Article pour attirer des expatries aidants. Partager son experience, revenu complementaire. CTA inscription.',
        'comparatifs' => 'Comparaison OBJECTIVE de 2+ services/pays. Tableau, pros/cons, verdict. NE PAS etre promotionnel pour SOS-Expat sauf CTA naturel en fin.',
        'affiliation' => 'Comparatif de services avec liens affilies. Banques, assurances, transferts, VPN. Objectif = conversion affiliation.',
        'qr' => 'Reponse courte et directe a une question precise. 300-800 mots. Featured snippet en premier paragraphe. NE PAS etre un guide long.',
        'news' => 'Actualite expatries/voyageurs. Ton journalistique. Evenement recent. NE PAS repeter du contenu evergreen.',
    ],

    // ═══════════════════════════════════════════════════════
    // 9. SEO / CTA RULES
    // ═══════════════════════════════════════════════════════

    'seo_rules' => [
        'cta_max' => 'Maximum 1 CTA vers SOS-Expat par article (naturel, en fin d\'article)',
        'cta_format' => 'Besoin d\'aide sur place ? Un avocat ou expert local disponible en moins de 5 min via SOS-Expat.',
        'internal_links' => 'Toujours lier vers la fiche pays concernee et les articles thematiques lies',
        'featured_snippet' => 'Premier paragraphe = reponse directe en 40-60 mots (position 0 Google)',
        'no_keyword_stuffing' => 'Densite mot-cle 1-2% maximum, ecrire naturellement',
        'year_mention' => 'Mentionner l\'annee en cours dans le titre et le contenu quand pertinent',
    ],

    // ═══════════════════════════════════════════════════════
    // 10. PAYMENT METHODS
    // ═══════════════════════════════════════════════════════

    'payment' => [
        'providers' => [
            'Stripe' => '44 pays (paiement carte bancaire)',
            'PayPal' => '150+ pays',
            'Mobile Money' => 'Orange Money, Wave, M-Pesa, MTN MoMo, Airtel Money (Afrique)',
        ],
        'withdrawal_minimum' => '$30',
        'withdrawal_fee' => '$3 fixe par transaction',
    ],
];
