<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the 17 email templates from Mission Control + multi-step sequences.
 */
class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ========== STEP 1: Premier contact ==========
            [
                'contact_type' => 'school',
                'language' => 'fr',
                'name' => 'Partenariat écoles',
                'subject' => 'Partenariat SOS-Expat - Protection juridique pour vos familles',
                'body' => "Madame, Monsieur le Directeur,\n\nSOS-Expat propose un accès immédiat à un avocat francophone par téléphone, en moins de 5 minutes, dans 197 pays.\n\nNous souhaiterions proposer ce service aux familles de votre établissement sous forme d'abonnement annuel à tarif préférentiel.\n\nPuis-je vous présenter notre offre en 10 minutes ?\n\nWilliams, Fondateur SOS-Expat\nhttps://sos-expat.com",
                'step' => 1,
                'delay_days' => 0,
            ],
            [
                'contact_type' => 'influenceur',
                'language' => 'fr',
                'name' => 'Programme influenceur',
                'subject' => 'Collaboration SOS-Expat - 10€/appel pour ta communauté',
                'body' => "Salut {{contactName}} !\n\nJe suis Williams, fondateur de SOS-Expat. On connecte les expatriés avec un avocat francophone en 5 min.\n\nProgramme influenceur : 10€ par appel généré + 5€ de remise pour ta communauté.\n\nIntéressé(e) ?\n\nWilliams\nhttps://sos-expat.com",
                'step' => 1,
                'delay_days' => 0,
                'variables' => ['contactName'],
            ],
            [
                'contact_type' => 'tiktoker',
                'language' => 'fr',
                'name' => 'Programme TikTokeur',
                'subject' => 'Collab SOS-Expat x TikTok - 10€/appel',
                'body' => "Hey {{contactName}} !\n\nJe suis Williams de SOS-Expat — on connecte les expatriés avec un avocat francophone en 5 min, partout dans le monde.\n\nTon contenu sur l'expatriation est top. On propose un programme créateur : 10€ par appel généré via ton lien + une remise exclusive pour tes abonnés.\n\nÇa te dit d'en discuter ?\n\nWilliams\nhttps://sos-expat.com",
                'step' => 1,
                'delay_days' => 0,
                'variables' => ['contactName'],
            ],
            [
                'contact_type' => 'youtuber',
                'language' => 'fr',
                'name' => 'Programme YouTubeur',
                'subject' => 'Sponsoring SOS-Expat pour ta chaîne',
                'body' => "Salut {{contactName}} !\n\nJe suis Williams, fondateur de SOS-Expat (avocat francophone en 5 min pour expatriés).\n\nTa chaîne est parfaite pour notre audience. Programme : 10€/appel + remise abonnés.\n\nOn en parle ?\n\nWilliams\nhttps://sos-expat.com",
                'step' => 1,
                'delay_days' => 0,
                'variables' => ['contactName'],
            ],
            [
                'contact_type' => 'instagramer',
                'language' => 'fr',
                'name' => 'Programme Instagrameur',
                'subject' => 'Collab SOS-Expat x Instagram',
                'body' => "Salut {{contactName}} !\n\nTon contenu expat est super. SOS-Expat connecte les expatriés avec un avocat francophone en 5 min.\n\nProgramme créateur : 10€/appel + remise pour ta communauté.\n\nIntéressé(e) ?\n\nWilliams, Fondateur SOS-Expat",
                'step' => 1,
                'delay_days' => 0,
                'variables' => ['contactName'],
            ],
            [
                'contact_type' => 'blogger',
                'language' => 'fr',
                'name' => 'Article invité blog',
                'subject' => 'Article invité - Données exclusives expatriés',
                'body' => "Bonjour {{contactName}},\n\nJe suis le fondateur de SOS-Expat. J'aimerais écrire un article invité sur les problèmes juridiques les plus fréquents des expatriés par pays.\n\nNous avons des données exclusives qui intéresseraient votre audience.\n\nCela vous dit ?\n\nWilliams\nhttps://sos-expat.com",
                'step' => 1,
                'delay_days' => 0,
                'variables' => ['contactName'],
            ],
            [
                'contact_type' => 'association',
                'language' => 'fr',
                'name' => 'Partenariat association',
                'subject' => 'Partenariat SOS-Expat pour vos membres',
                'body' => "Bonjour,\n\nSOS-Expat offre un accès immédiat à un avocat francophone en moins de 5 min, dans 197 pays.\n\nNous proposons un tarif préférentiel exclusif pour les membres de votre association.\n\nIntéressé(e) pour en discuter ?\n\nWilliams, Fondateur SOS-Expat\nhttps://sos-expat.com",
                'step' => 1,
                'delay_days' => 0,
            ],
            [
                'contact_type' => 'press',
                'language' => 'fr',
                'name' => 'Dossier de presse',
                'subject' => 'SOS-Expat - Service inédit pour les expatriés',
                'body' => "Bonjour,\n\nSOS-Expat vient de lancer un service inédit : la mise en relation téléphonique avec un avocat francophone en 5 minutes, dans 197 pays.\n\nDonnées exclusives sur les problèmes juridiques des expatriés disponibles. Interview possible.\n\nWilliams, Fondateur\nhttps://sos-expat.com",
                'step' => 1,
                'delay_days' => 0,
            ],
            [
                'contact_type' => 'backlink',
                'language' => 'fr',
                'name' => 'Demande référencement',
                'subject' => 'Référencement SOS-Expat sur votre plateforme',
                'body' => "Bonjour,\n\nSOS-Expat est un service de mise en relation téléphonique avec un avocat francophone en 5 min, dans 197 pays.\n\nNous souhaiterions être référencés sur votre site/annuaire. Quelle est la procédure ?\n\nWilliams\nhttps://sos-expat.com",
                'step' => 1,
                'delay_days' => 0,
            ],
            [
                'contact_type' => 'real_estate',
                'language' => 'fr',
                'name' => 'Partenariat immobilier',
                'subject' => 'Partenariat SOS-Expat - Service juridique pour vos clients expatriés',
                'body' => "Bonjour,\n\nVos clients expatriés ont souvent des questions juridiques (bail, litiges, fiscalité). SOS-Expat les connecte avec un avocat francophone en 5 min.\n\nPartenariat possible : recommandation croisée ou commission.\n\nIntéressé ?\n\nWilliams, Fondateur SOS-Expat",
                'step' => 1,
                'delay_days' => 0,
            ],
            [
                'contact_type' => 'translator',
                'language' => 'fr',
                'name' => 'Partenariat traduction',
                'subject' => 'Partenariat SOS-Expat x Traduction',
                'body' => "Bonjour,\n\nSOS-Expat connecte les expatriés avec un avocat francophone en 5 min. Nos utilisateurs ont souvent besoin de traductions (documents juridiques, contrats).\n\nPartenariat recommandation croisée possible ?\n\nWilliams\nhttps://sos-expat.com",
                'step' => 1,
                'delay_days' => 0,
            ],
            [
                'contact_type' => 'travel_agency',
                'language' => 'fr',
                'name' => 'Partenariat agence voyage',
                'subject' => 'SOS-Expat - Protection juridique pour vos clients',
                'body' => "Bonjour,\n\nVos clients qui s'installent à l'étranger ont souvent des questions juridiques urgentes. SOS-Expat les connecte avec un avocat francophone en 5 minutes.\n\nPartenariat possible : recommandation croisée ou commission sur chaque appel.\n\nWilliams, Fondateur SOS-Expat",
                'step' => 1,
                'delay_days' => 0,
            ],
            [
                'contact_type' => 'insurer',
                'language' => 'fr',
                'name' => 'Partenariat assurance',
                'subject' => 'SOS-Expat - Complément juridique pour vos assurés expatriés',
                'body' => "Bonjour,\n\nSOS-Expat propose un accès immédiat à un avocat francophone (5 min) dans 197 pays.\n\nCe service est complémentaire à votre offre d'assurance expatriés. Partenariat ou intégration possible.\n\nWilliams, Fondateur SOS-Expat",
                'step' => 1,
                'delay_days' => 0,
            ],
            [
                'contact_type' => 'enterprise',
                'language' => 'fr',
                'name' => 'Offre B2B entreprise',
                'subject' => 'SOS-Expat - Assistance juridique pour vos salariés expatriés',
                'body' => "Bonjour,\n\nVos collaborateurs expatriés font face à des problèmes juridiques locaux. SOS-Expat les connecte avec un avocat francophone en 5 minutes, dans 197 pays.\n\nOffre entreprise avec facturation centralisée disponible.\n\nWilliams, Fondateur SOS-Expat",
                'step' => 1,
                'delay_days' => 0,
            ],
            [
                'contact_type' => 'partner',
                'language' => 'fr',
                'name' => 'Partenariat institutionnel',
                'subject' => 'SOS-Expat - Partenariat au service des Français de l\'étranger',
                'body' => "Bonjour,\n\nSOS-Expat connecte les expatriés français avec un avocat francophone en 5 min, partout dans le monde.\n\nNous souhaitons établir un partenariat pour mieux accompagner les Français de {{contactCountry}}.\n\nWilliams, Fondateur SOS-Expat",
                'step' => 1,
                'delay_days' => 0,
                'variables' => ['contactCountry'],
            ],
            [
                'contact_type' => 'lawyer',
                'language' => 'fr',
                'name' => 'Recrutement avocat',
                'subject' => 'SOS-Expat recrute des avocats francophones',
                'body' => "Bonjour Maître,\n\nSOS-Expat est une plateforme qui connecte les expatriés avec des avocats francophones par téléphone en 5 minutes.\n\nNous cherchons des avocats partenaires en {{contactCountry}}. Vous fixez vos tarifs, nous amenons les clients.\n\nIntéressé(e) ?\n\nWilliams, Fondateur SOS-Expat",
                'step' => 1,
                'delay_days' => 0,
                'variables' => ['contactCountry'],
            ],
            [
                'contact_type' => 'group_admin',
                'language' => 'fr',
                'name' => 'Partenariat admin groupe',
                'subject' => 'Partenariat SOS-Expat pour votre communauté',
                'body' => "Bonjour {{contactName}},\n\nSOS-Expat connecte les expatriés avec un avocat francophone en 5 min.\n\nEn tant qu'admin d'une communauté d'expatriés, vous pouvez gagner une commission sur chaque appel généré via votre lien.\n\nÇa vous intéresse ?\n\nWilliams\nhttps://sos-expat.com",
                'step' => 1,
                'delay_days' => 0,
                'variables' => ['contactName'],
            ],

            [
                'contact_type' => 'erasmus',
                'language' => 'fr',
                'name' => 'Partenariat Erasmus',
                'subject' => 'SOS-Expat - Assistance juridique pour vos étudiants internationaux',
                'body' => "Bonjour,\n\nJe suis Williams, fondateur de SOS-Expat. Nous proposons un accès immédiat à un avocat francophone par téléphone en moins de 5 minutes, dans 197 pays.\n\nCe service est particulièrement utile pour vos étudiants en mobilité Erasmus+ qui font face à des questions juridiques dans leur pays d'accueil (logement, visa, contrats, litiges).\n\nNous souhaiterions établir un partenariat pour offrir un tarif préférentiel à vos étudiants sortants et entrants.\n\nPuis-je vous présenter notre offre ?\n\nWilliams, Fondateur SOS-Expat\nhttps://sos-expat.com",
                'step' => 1,
                'delay_days' => 0,
            ],

            // ========== STEP 2: Relance J+3 ==========
            [
                'contact_type' => 'influenceur',
                'language' => 'fr',
                'name' => 'Relance influenceur J+3',
                'subject' => 'Re: Collaboration SOS-Expat',
                'body' => "Salut {{contactName}} !\n\nJe me permets de te relancer concernant le programme créateur SOS-Expat (10€/appel).\n\nSi tu as des questions, n'hésite pas !\n\nWilliams",
                'step' => 2,
                'delay_days' => 3,
                'variables' => ['contactName'],
            ],
            [
                'contact_type' => 'school',
                'language' => 'fr',
                'name' => 'Relance école J+3',
                'subject' => 'Re: Partenariat SOS-Expat',
                'body' => "Madame, Monsieur,\n\nJe me permets de revenir vers vous concernant notre proposition de partenariat SOS-Expat pour les familles de votre établissement.\n\nSeriez-vous disponible pour un appel de 10 minutes cette semaine ?\n\nWilliams",
                'step' => 2,
                'delay_days' => 3,
            ],

            // ========== STEP 3: Relance J+7 ==========
            [
                'contact_type' => 'influenceur',
                'language' => 'fr',
                'name' => 'Relance influenceur J+7',
                'subject' => 'Dernier message - Programme SOS-Expat',
                'body' => "{{contactName}}, dernier message !\n\nNotre programme : 10€/appel, dashboard temps réel, paiement mensuel.\n\nSi ce n'est pas le bon moment, pas de souci — je ne relancerai plus.\n\nWilliams",
                'step' => 3,
                'delay_days' => 7,
                'variables' => ['contactName'],
            ],
        ];

        foreach ($templates as $tpl) {
            EmailTemplate::updateOrCreate(
                [
                    'contact_type' => $tpl['contact_type'],
                    'language'     => $tpl['language'],
                    'step'         => $tpl['step'],
                ],
                $tpl
            );
        }
    }
}
