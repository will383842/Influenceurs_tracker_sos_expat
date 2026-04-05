<?php

namespace App\Services\Content;

/**
 * Injects SOS-Expat Knowledge Base into AI generation prompts.
 *
 * Call getSystemPrompt($contentType) to get the full system prompt
 * with Knowledge Base context for any content type.
 */
class KnowledgeBaseService
{
    private array $kb;

    public function __construct()
    {
        $this->kb = config('knowledge-base', []);
    }

    /**
     * Get the complete system prompt with Knowledge Base for a content type.
     */
    public function getSystemPrompt(string $contentType, ?string $country = null, ?string $language = null): string
    {
        $identity = $this->getIdentityBlock();
        $services = $this->getServicesBlock();
        $coverage = $this->getCoverageBlock();
        $brandVoice = $this->getBrandVoiceBlock();
        $contentRule = $this->getContentRule($contentType);
        $seoRules = $this->getSeoRulesBlock();
        $countryContext = $country ? "\nCONTEXTE PAYS : Cet article concerne specifiquement {$country}. Toutes les donnees doivent etre specifiques a ce pays.\n" : '';
        $langContext = $language ? "\nLANGUE DE GENERATION : {$language}\n" : '';

        return <<<PROMPT
=== SOS-EXPAT KNOWLEDGE BASE (SOURCE DE VERITE) ===

{$identity}

{$services}

{$coverage}

{$brandVoice}

=== REGLES POUR CE TYPE DE CONTENU : {$contentType} ===
{$contentRule}

{$seoRules}
{$countryContext}{$langContext}
=== FIN KNOWLEDGE BASE ===

IMPORTANT : Ne JAMAIS inventer de donnees non presentes dans ce Knowledge Base.
Si tu n'es pas sur d'une information sur SOS-Expat, ne l'inclus pas.
PROMPT;
    }

    /**
     * Get just the Knowledge Base context (without content type rules).
     * Used for translations to preserve SOS-Expat accuracy.
     */
    public function getTranslationContext(): string
    {
        return <<<PROMPT
=== SOS-EXPAT REFERENCE ===
- Nom exact : SOS-Expat (avec tiret)
- Service : mise en relation telephonique avec avocats et experts locaux
- Avocat : 49EUR/20min | Expert local : 19EUR/30min
- 197 pays, 9 langues, disponible 24/7
- Mise en relation en moins de 5 minutes
- Ce n'est PAS un cabinet d'avocats, PAS une assurance, PAS gratuit
- 5 programmes : Chatter, Influenceur, Blogueur, Admin Groupe, Affiliation
=== FIN REFERENCE ===
PROMPT;
    }

    private function getIdentityBlock(): string
    {
        $identity = $this->kb['identity'] ?? [];
        $whatIs = implode("\n- ", $identity['what_it_is'] ?? []);
        $whatIsNot = implode("\n- ", $identity['what_it_is_NOT'] ?? []);

        return <<<BLOCK
QUI EST SOS-EXPAT :
- {$whatIs}

CE QUE SOS-EXPAT N'EST PAS :
- {$whatIsNot}
BLOCK;
    }

    private function getServicesBlock(): string
    {
        $lawyer = $this->kb['services']['lawyer'] ?? [];
        $expat = $this->kb['services']['expat'] ?? [];

        return <<<BLOCK
SERVICES ET TARIFS :
- AVOCAT : {$lawyer['price_eur']}EUR / {$lawyer['price_usd']}USD — {$lawyer['duration_minutes']} minutes
  {$lawyer['description']}
- EXPERT LOCAL : {$expat['price_eur']}EUR / {$expat['price_usd']}USD — {$expat['duration_minutes']} minutes
  {$expat['description']}
BLOCK;
    }

    private function getCoverageBlock(): string
    {
        $coverage = $this->kb['coverage'] ?? [];
        $langs = implode(', ', array_map(
            fn ($code, $name) => "{$name} ({$code})",
            array_keys($coverage['language_names'] ?? []),
            array_values($coverage['language_names'] ?? [])
        ));

        return <<<BLOCK
COUVERTURE :
- {$coverage['countries']} pays
- {$coverage['availability']}
- Mise en relation en {$coverage['response_time']}
- Langues : {$langs}
BLOCK;
    }

    private function getBrandVoiceBlock(): string
    {
        $voice = $this->kb['brand_voice'] ?? [];
        $neverSay = implode("\n- ", $voice['never_say'] ?? []);
        $alwaysSay = implode("\n- ", $voice['always_say'] ?? []);

        return <<<BLOCK
VOIX DE MARQUE :
Ton : {$voice['tone']}

NE JAMAIS :
- {$neverSay}

TOUJOURS :
- {$alwaysSay}
BLOCK;
    }

    private function getContentRule(string $contentType): string
    {
        $rules = $this->kb['content_rules'] ?? [];

        // Map content type aliases
        $typeMap = [
            'qa' => 'qr',
            'guide' => 'fiches_pays',
            'article' => 'art_mots_cles',
            'outreach' => 'chatters',
            'comparative' => 'comparatifs',
            'news' => 'news',
            'partner_legal' => 'avocats',
            'partner_expat' => 'expats_aidants',
            'affiliation' => 'affiliation',
        ];

        $key = $typeMap[$contentType] ?? $contentType;

        return $rules[$key] ?? "Article informatif pour expatries et voyageurs. Ton professionnel, donnees chiffrees, CTA naturel vers SOS-Expat.";
    }

    private function getSeoRulesBlock(): string
    {
        $seo = $this->kb['seo_rules'] ?? [];

        return <<<BLOCK
REGLES SEO :
- CTA : {$seo['cta_max']}
- Format CTA : "{$seo['cta_format']}"
- Maillage : {$seo['internal_links']}
- Featured snippet : {$seo['featured_snippet']}
- Mots-cles : {$seo['no_keyword_stuffing']}
- Annee : {$seo['year_mention']}
BLOCK;
    }
}
