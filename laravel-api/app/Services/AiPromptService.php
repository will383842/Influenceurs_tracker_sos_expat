<?php

namespace App\Services;

use App\Enums\ContactType;

/**
 * Generates AI research prompts per contact type + country.
 * Ported from Mission Control's PROMPTS object.
 */
class AiPromptService
{
    /**
     * Build the research prompt for a given contact type, country, and exclusion list.
     */
    public function buildPrompt(string $contactType, string $country, string $language = 'fr', array $excludeUrls = []): string
    {
        $exclusionBlock = '';
        if (!empty($excludeUrls)) {
            $exclusionBlock = "\n\nEXCLURE ces contacts (déjà dans notre base) :\n"
                . implode("\n", $excludeUrls)
                . "\n\nNe propose QUE des contacts qui ne sont PAS dans cette liste.";
        }

        $prompt = match ($contactType) {
            'school' => $this->schoolPrompt($country, $language),
            'erasmus' => $this->erasmusPrompt($country, $language),
            'chatter', 'job_board' => $this->jobBoardPrompt($country, $language),
            'influenceur' => $this->influenceurPrompt($country, $language),
            'tiktoker' => $this->tiktokerPrompt($country, $language),
            'youtuber' => $this->youtuberPrompt($country, $language),
            'instagramer' => $this->instagramerPrompt($country, $language),
            'blogger' => $this->bloggerPrompt($country, $language),
            'association' => $this->associationPrompt($country, $language),
            'press' => $this->pressPrompt($country, $language),
            'backlink' => $this->backlinkPrompt($country, $language),
            'real_estate' => $this->realEstatePrompt($country, $language),
            'translator' => $this->translatorPrompt($country, $language),
            'travel_agency' => $this->travelAgencyPrompt($country, $language),
            'insurer', 'enterprise' => $this->enterprisePrompt($country, $language, $contactType),
            'partner' => $this->partnerPrompt($country, $language),
            'lawyer' => $this->lawyerPrompt($country, $language),
            'group_admin' => $this->groupAdminPrompt($country, $language),
            default => $this->genericPrompt($country, $language, $contactType),
        };

        return $prompt . $exclusionBlock;
    }

    private function schoolPrompt(string $country, string $lang): string
    {
        return "Trouve toutes les écoles internationales françaises (AEFE, homologuées, partenaires) en {$country} avec pour chaque école : nom complet, adresse, téléphone, email, site web (URL), nom du directeur si possible.\n\nFormat pour chaque école :\nNOM: ...\nADRESSE: ...\nTEL: ...\nEMAIL: ...\nURL: ...\nDIRECTEUR: ...";
    }

    private function erasmusPrompt(string $country, string $lang): string
    {
        return "Trouve toutes les universités et écoles supérieures en {$country} qui participent au programme Erasmus+ ou qui accueillent des étudiants internationaux francophones. Pour chaque établissement :\nNOM: nom complet de l'université/école\nEMAIL: email du bureau des relations internationales ou du coordinateur Erasmus\nTEL: téléphone du bureau international\nURL: page web du bureau international ou de la mobilité Erasmus\nCOORDINATEUR: nom du coordinateur Erasmus si disponible\nPROGRAMMES: types de programmes (échange, double diplôme, stage)\nLANGUES: langues d'enseignement";
    }

    private function jobBoardPrompt(string $country, string $lang): string
    {
        return "Trouve les sites d'offres d'emploi GRATUITS en {$country} où je peux publier une offre pour recruter des chatters/freelances francophones. Pour chaque site :\nNOM: nom du site\nURL: ...\nGRATUIT: oui/non\nCOMMENT: comment publier\nAUDIENCE: type de candidats";
    }

    private function influenceurPrompt(string $country, string $lang): string
    {
        return "Trouve 15 influenceurs francophones (Instagram, TikTok, YouTube) qui créent du contenu sur l'expatriation ou le voyage en {$country}. Pour chaque :\nNOM: pseudo\nPLATEFORME: ...\nABONNES: ...\nEMAIL: (si visible)\nURL: lien profil\nPAYS: ...";
    }

    private function tiktokerPrompt(string $country, string $lang): string
    {
        return "Trouve 10 créateurs TikTok francophones qui font du contenu sur l'expatriation, le voyage ou la vie à l'étranger en {$country}. Pour chaque :\nNOM: pseudo TikTok\nABONNES: ...\nEMAIL: (si visible dans bio)\nURL: lien profil TikTok\nPAYS: ...";
    }

    private function youtuberPrompt(string $country, string $lang): string
    {
        return "Trouve 10 YouTubeurs francophones qui font du contenu sur l'expatriation ou la vie à l'étranger en {$country}. Pour chaque :\nNOM: nom de la chaîne\nABONNES: ...\nEMAIL: (email professionnel)\nURL: lien chaîne YouTube\nPAYS: ...";
    }

    private function instagramerPrompt(string $country, string $lang): string
    {
        return "Trouve 10 créateurs Instagram francophones qui font du contenu sur l'expatriation ou le voyage en {$country}. Pour chaque :\nNOM: pseudo Instagram\nABONNES: ...\nEMAIL: (si visible dans bio)\nURL: lien profil Instagram\nPAYS: ...";
    }

    private function bloggerPrompt(string $country, string $lang): string
    {
        return "Trouve 10 blogs francophones sur l'expatriation ou le voyage en {$country}. Pour chaque :\nNOM: nom du blog\nAUTEUR: ...\nEMAIL: ...\nURL: ...\nDA: Domain Authority estimé";
    }

    private function associationPrompt(string $country, string $lang): string
    {
        return "Trouve toutes les associations d'expatriés français en {$country}. Pour chaque :\nNOM: ...\nEMAIL: ...\nURL: ...\nTEL: ...\nRESPONSABLE: ...";
    }

    private function pressPrompt(string $country, string $lang): string
    {
        return "Trouve les médias francophones qui couvrent l'expatriation en {$country} ou à l'international. Pour chaque :\nNOM: ...\nEMAIL: rédaction\nURL: ...\nJOURNALISTE: nom si possible";
    }

    private function backlinkPrompt(string $country, string $lang): string
    {
        return "Trouve 15 sites francophones où SOS-Expat peut obtenir un backlink : annuaires expatriés, annuaires juridiques, annuaires startups, sites de communiqués de presse gratuits, forums avec profil. Pour chaque :\nNOM: nom du site\nURL: ...\nTYPE: annuaire/guest post/forum/communiqué\nDA: Domain Authority estimé\nGRATUIT: oui/non";
    }

    private function realEstatePrompt(string $country, string $lang): string
    {
        return "Trouve les principales agences immobilières internationales ou spécialisées expatriés en {$country}. Pour chaque :\nNOM: ...\nEMAIL: ...\nURL: ...\nTEL: ...\nSPECIALITE: location/vente/relocation";
    }

    private function translatorPrompt(string $country, string $lang): string
    {
        return "Trouve les agences de traduction et traducteurs assermentés francophones en {$country}. Pour chaque :\nNOM: ...\nEMAIL: ...\nURL: ...\nTEL: ...\nLANGUES: ...\nASSERMENTE: oui/non";
    }

    private function travelAgencyPrompt(string $country, string $lang): string
    {
        return "Trouve les agences de voyage et de relocation spécialisées expatriés en {$country}. Pour chaque :\nNOM: ...\nEMAIL: ...\nURL: ...\nTEL: ...\nSERVICES: ...";
    }

    private function enterprisePrompt(string $country, string $lang, string $type): string
    {
        $label = $type === 'insurer' ? 'compagnies d\'assurance expatriés' : 'entreprises multinationales avec employés expatriés';
        return "Trouve les {$label} en {$country}. Pour chaque :\nNOM: ...\nEMAIL: ...\nURL: ...\nTEL: ...\nCONTACT: nom du responsable RH/mobilité si possible";
    }

    private function partnerPrompt(string $country, string $lang): string
    {
        return "Trouve les ambassades, chambres de commerce françaises, banques internationales et institutions qui accompagnent les expatriés français en {$country}. Pour chaque :\nNOM: ...\nEMAIL: ...\nURL: ...\nTEL: ...\nTYPE: ambassade/chambre/banque/institution";
    }

    private function lawyerPrompt(string $country, string $lang): string
    {
        return "Trouve les avocats francophones spécialisés en droit international, droit des étrangers ou droit de la famille en {$country}. Pour chaque :\nNOM: ...\nEMAIL: ...\nURL: ...\nTEL: ...\nSPECIALITE: ...";
    }

    private function groupAdminPrompt(string $country, string $lang): string
    {
        return "Trouve les groupes Facebook et WhatsApp francophones d'expatriés en {$country} (groupes actifs avec plus de 500 membres). Pour chaque :\nNOM: nom du groupe\nPLATEFORME: Facebook/WhatsApp\nMEMBRES: ...\nURL: lien du groupe\nADMIN: nom de l'admin si visible";
    }

    private function genericPrompt(string $country, string $lang, string $type): string
    {
        return "Trouve des contacts professionnels de type '{$type}' en lien avec l'expatriation en {$country}. Pour chaque :\nNOM: ...\nEMAIL: ...\nURL: ...\nTEL: ...\nNOTES: ...";
    }
}
