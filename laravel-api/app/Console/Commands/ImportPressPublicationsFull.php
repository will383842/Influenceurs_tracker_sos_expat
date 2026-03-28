<?php

namespace App\Console\Commands;

use App\Models\PressPublication;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Import the FULL curated list of 250+ French/francophone publications
 * with their author index URLs and email patterns for mass scraping.
 *
 * Usage: php artisan press:import-full [--reset]
 *
 * email_pattern tokens:
 *   {first}   = prénom normalisé (jean)
 *   {last}    = nom normalisé (dupont)
 *   {f}       = initiale prénom (j)
 *   {fl}      = initiale prénom + nom (jdupont)
 */
class ImportPressPublicationsFull extends Command
{
    protected $signature   = 'press:import-full {--reset : Truncate and reimport all}';
    protected $description = 'Import 250+ French press publications with author URLs and email patterns';

    private array $publications = [

        // ══════════════════════════════════════════════════════════════════
        // PRESSE QUOTIDIENNE NATIONALE
        // ══════════════════════════════════════════════════════════════════
        [
            'name'          => 'Le Monde',
            'base_url'      => 'https://www.lemonde.fr',
            'authors_url'   => 'https://www.lemonde.fr/journaliste/',
            'articles_url'  => 'https://www.lemonde.fr/economie/',
            'team_url'      => 'https://www.lemonde.fr/actualite-medias/article/2021/09/06/le-monde-notre-redaction_6092895_3236.html',
            'email_pattern' => '{first}.{last}@lemonde.fr',
            'email_domain'  => 'lemonde.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'international', 'voyage', 'entrepreneuriat'],
        ],
        [
            'name'          => 'Le Figaro',
            'base_url'      => 'https://www.lefigaro.fr',
            'authors_url'   => 'https://www.lefigaro.fr/auteur/',
            'articles_url'  => 'https://www.lefigaro.fr/economie/',
            'email_pattern' => '{first}.{last}@lefigaro.fr',
            'email_domain'  => 'lefigaro.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'international', 'voyage', 'expatriation', 'entrepreneuriat'],
        ],
        [
            'name'          => 'Libération',
            'base_url'      => 'https://www.liberation.fr',
            'authors_url'   => 'https://www.liberation.fr/auteurs/',
            'articles_url'  => 'https://www.liberation.fr/economie/',
            'email_pattern' => '{first}.{last}@liberation.fr',
            'email_domain'  => 'liberation.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['international', 'business'],
        ],
        [
            'name'          => "L'Humanité",
            'base_url'      => 'https://www.humanite.fr',
            'authors_url'   => 'https://www.humanite.fr/auteurs',
            'email_pattern' => '{first}.{last}@humanite.fr',
            'email_domain'  => 'humanite.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['international', 'business'],
        ],
        [
            'name'          => "20 Minutes",
            'base_url'      => 'https://www.20minutes.fr',
            'authors_url'   => 'https://www.20minutes.fr/auteurs/',
            'articles_url'  => 'https://www.20minutes.fr/economie/',
            'email_pattern' => '{first}.{last}@20minutes.fr',
            'email_domain'  => '20minutes.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'voyage', 'international'],
        ],
        [
            'name'          => "Le Parisien",
            'base_url'      => 'https://www.leparisien.fr',
            'authors_url'   => 'https://www.leparisien.fr/auteur/',
            'email_pattern' => '{first}.{last}@leparisien.fr',
            'email_domain'  => 'leparisien.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'voyage', 'entrepreneuriat'],
        ],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE HEBDOMADAIRE / NEWSMAGAZINES
        // ══════════════════════════════════════════════════════════════════
        [
            'name'          => "L'Obs (Nouvel Observateur)",
            'base_url'      => 'https://www.nouvelobs.com',
            'authors_url'   => 'https://www.nouvelobs.com/auteur/',
            'articles_url'  => 'https://www.nouvelobs.com/economie/',
            'email_pattern' => '{first}.{last}@nouvelobs.com',
            'email_domain'  => 'nouvelobs.com',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'international', 'entrepreneuriat'],
        ],
        [
            'name'          => "L'Express",
            'base_url'      => 'https://www.lexpress.fr',
            'authors_url'   => 'https://www.lexpress.fr/auteur/',
            'articles_url'  => 'https://www.lexpress.fr/economie/',
            'email_pattern' => '{first}.{last}@lexpress.fr',
            'email_domain'  => 'lexpress.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'international', 'voyage', 'expatriation', 'entrepreneuriat'],
        ],
        [
            'name'          => "Le Point",
            'base_url'      => 'https://www.lepoint.fr',
            'authors_url'   => 'https://www.lepoint.fr/auteur/',
            'articles_url'  => 'https://www.lepoint.fr/economie/',
            'email_pattern' => '{first}.{last}@lepoint.fr',
            'email_domain'  => 'lepoint.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'international', 'voyage', 'expatriation', 'entrepreneuriat'],
        ],
        [
            'name'          => 'Marianne',
            'base_url'      => 'https://www.marianne.net',
            'authors_url'   => 'https://www.marianne.net/auteur/',
            'email_pattern' => '{first}.{last}@marianne.net',
            'email_domain'  => 'marianne.net',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['international', 'business'],
        ],
        [
            'name'          => "Le Canard Enchaîné",
            'base_url'      => 'https://www.lecanardenchaine.fr',
            'team_url'      => 'https://www.lecanardenchaine.fr/a-propos',
            'email_pattern' => '{first}.{last}@lecanardenchaine.fr',
            'email_domain'  => 'lecanardenchaine.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'international'],
        ],
        [
            'name'          => 'Mediapart',
            'base_url'      => 'https://www.mediapart.fr',
            'authors_url'   => 'https://www.mediapart.fr/equipe',
            'email_pattern' => '{first}.{last}@mediapart.fr',
            'email_domain'  => 'mediapart.fr',
            'media_type'    => 'web',
            'topics'        => ['business', 'international', 'entrepreneuriat'],
        ],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE ÉCONOMIQUE & BUSINESS (déjà dans liste de base + nouveaux)
        // ══════════════════════════════════════════════════════════════════
        [
            'name'          => 'Capital',
            'base_url'      => 'https://www.capital.fr',
            'authors_url'   => 'https://www.capital.fr/auteurs/',
            'articles_url'  => 'https://www.capital.fr/entreprises-marches/',
            'email_pattern' => '{first}.{last}@capital.fr',
            'email_domain'  => 'capital.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'entrepreneuriat', 'international'],
        ],
        [
            'name'          => 'Challenges',
            'base_url'      => 'https://www.challenges.fr',
            'authors_url'   => 'https://www.challenges.fr/auteur/',
            'articles_url'  => 'https://www.challenges.fr/entreprise/',
            'email_pattern' => '{first}.{last}@challenges.fr',
            'email_domain'  => 'challenges.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'entrepreneuriat', 'international'],
        ],
        [
            'name'          => 'Forbes France',
            'base_url'      => 'https://www.forbes.fr',
            'authors_url'   => 'https://www.forbes.fr/auteurs/',
            'articles_url'  => 'https://www.forbes.fr/business/',
            'email_pattern' => '{first}.{last}@forbes.fr',
            'email_domain'  => 'forbes.fr',
            'media_type'    => 'web',
            'topics'        => ['business', 'entrepreneuriat', 'tech', 'international'],
        ],
        [
            'name'          => 'La Tribune',
            'base_url'      => 'https://www.latribune.fr',
            'authors_url'   => 'https://www.latribune.fr/auteur/',
            'articles_url'  => 'https://www.latribune.fr/entreprises-finance/',
            'email_pattern' => '{first}.{last}@latribune.fr',
            'email_domain'  => 'latribune.fr',
            'media_type'    => 'web',
            'topics'        => ['business', 'entrepreneuriat', 'international'],
        ],
        [
            'name'          => 'Les Echos',
            'base_url'      => 'https://www.lesechos.fr',
            'authors_url'   => 'https://www.lesechos.fr/auteur/',
            'articles_url'  => 'https://www.lesechos.fr/pme-regions/entrepreneuriat/',
            'email_pattern' => '{first}.{last}@lesechos.fr',
            'email_domain'  => 'lesechos.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'entrepreneuriat', 'international'],
        ],
        [
            'name'          => "L'Usine Nouvelle",
            'base_url'      => 'https://www.usinenouvelle.com',
            'authors_url'   => 'https://www.usinenouvelle.com/auteur/',
            'email_pattern' => '{first}.{last}@usinenouvelle.com',
            'email_domain'  => 'usinenouvelle.com',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'tech', 'entrepreneuriat'],
        ],
        [
            'name'          => 'Maddyness',
            'base_url'      => 'https://www.maddyness.com',
            'authors_url'   => 'https://www.maddyness.com/auteur/',
            'articles_url'  => 'https://www.maddyness.com/entrepreneuriat/',
            'email_pattern' => '{first}.{last}@maddyness.com',
            'email_domain'  => 'maddyness.com',
            'media_type'    => 'web',
            'topics'        => ['entrepreneuriat', 'tech', 'startup'],
        ],
        [
            'name'          => 'FrenchWeb',
            'base_url'      => 'https://www.frenchweb.fr',
            'authors_url'   => 'https://www.frenchweb.fr/author/',
            'email_pattern' => '{first}.{last}@frenchweb.fr',
            'email_domain'  => 'frenchweb.fr',
            'media_type'    => 'web',
            'topics'        => ['tech', 'entrepreneuriat', 'startup'],
        ],
        [
            'name'          => 'Journal du Net',
            'base_url'      => 'https://www.journaldunet.com',
            'authors_url'   => 'https://www.journaldunet.com/auteur/',
            'email_pattern' => '{first}.{last}@journaldunet.com',
            'email_domain'  => 'journaldunet.com',
            'media_type'    => 'web',
            'topics'        => ['tech', 'business', 'entrepreneuriat'],
        ],
        [
            'name'          => "Chef d'Entreprise",
            'base_url'      => 'https://www.chefdentreprise.com',
            'authors_url'   => 'https://www.chefdentreprise.com/auteur/',
            'email_pattern' => '{first}.{last}@chefdentreprise.com',
            'email_domain'  => 'chefdentreprise.com',
            'media_type'    => 'web',
            'topics'        => ['entrepreneuriat', 'business'],
        ],
        [
            'name'          => 'Management Magazine',
            'base_url'      => 'https://www.management.fr',
            'authors_url'   => 'https://www.management.fr/auteur/',
            'email_pattern' => '{first}.{last}@management.fr',
            'email_domain'  => 'management.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'entrepreneuriat'],
        ],
        [
            'name'          => 'Siècle Digital',
            'base_url'      => 'https://siecledigital.fr',
            'authors_url'   => 'https://siecledigital.fr/author/',
            'email_pattern' => '{first}.{last}@siecledigital.fr',
            'email_domain'  => 'siecledigital.fr',
            'media_type'    => 'web',
            'topics'        => ['tech', 'entrepreneuriat'],
        ],
        [
            'name'          => "L'Entreprise / BFM Business",
            'base_url'      => 'https://bfmbusiness.bfmtv.com',
            'authors_url'   => 'https://www.bfmtv.com/mediaplayer/bio/',
            'articles_url'  => 'https://bfmbusiness.bfmtv.com/entreprise/',
            'email_pattern' => '{first}.{last}@bfmtv.com',
            'email_domain'  => 'bfmtv.com',
            'media_type'    => 'tv',
            'topics'        => ['business', 'entrepreneuriat', 'international'],
        ],
        [
            'name'          => 'Le Revenu',
            'base_url'      => 'https://www.lerevenu.com',
            'authors_url'   => 'https://www.lerevenu.com/auteur/',
            'email_pattern' => '{first}.{last}@lerevenu.com',
            'email_domain'  => 'lerevenu.com',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'entrepreneuriat'],
        ],
        [
            'name'          => 'Investir / Les Echos Invest',
            'base_url'      => 'https://investir.lesechos.fr',
            'email_pattern' => '{first}.{last}@lesechos.fr',
            'email_domain'  => 'lesechos.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'entrepreneuriat'],
        ],
        [
            'name'          => 'LSA Conso',
            'base_url'      => 'https://www.lsa-conso.fr',
            'authors_url'   => 'https://www.lsa-conso.fr/auteur/',
            'email_pattern' => '{first}.{last}@lsa-conso.fr',
            'email_domain'  => 'lsa-conso.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'entrepreneuriat'],
        ],
        [
            'name'          => 'CB News',
            'base_url'      => 'https://www.cbnews.fr',
            'authors_url'   => 'https://www.cbnews.fr/auteur/',
            'email_pattern' => '{first}.{last}@cbnews.fr',
            'email_domain'  => 'cbnews.fr',
            'media_type'    => 'web',
            'topics'        => ['business', 'tech'],
        ],
        [
            'name'          => 'Influencia',
            'base_url'      => 'https://www.influencia.net',
            'authors_url'   => 'https://www.influencia.net/auteur/',
            'email_pattern' => '{first}.{last}@influencia.net',
            'email_domain'  => 'influencia.net',
            'media_type'    => 'web',
            'topics'        => ['business', 'entrepreneuriat', 'tech'],
        ],
        [
            'name'          => 'Stratégies',
            'base_url'      => 'https://www.strategies.fr',
            'authors_url'   => 'https://www.strategies.fr/auteur/',
            'email_pattern' => '{first}.{last}@strategies.fr',
            'email_domain'  => 'strategies.fr',
            'media_type'    => 'web',
            'topics'        => ['business', 'entrepreneuriat', 'tech'],
        ],
        [
            'name'          => 'Républik Retail',
            'base_url'      => 'https://www.republikretail.fr',
            'email_pattern' => '{first}.{last}@republikretail.fr',
            'email_domain'  => 'republikretail.fr',
            'media_type'    => 'web',
            'topics'        => ['business', 'entrepreneuriat'],
        ],

        // ══════════════════════════════════════════════════════════════════
        // TECH / NUMÉRIQUE
        // ══════════════════════════════════════════════════════════════════
        [
            'name'          => "L'Informaticien",
            'base_url'      => 'https://www.linformaticien.com',
            'email_pattern' => '{first}.{last}@linformaticien.com',
            'email_domain'  => 'linformaticien.com',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['tech', 'entrepreneuriat'],
        ],
        [
            'name'          => 'Numerama',
            'base_url'      => 'https://www.numerama.com',
            'authors_url'   => 'https://www.numerama.com/auteur/',
            'email_pattern' => '{first}.{last}@numerama.com',
            'email_domain'  => 'numerama.com',
            'media_type'    => 'web',
            'topics'        => ['tech', 'entrepreneuriat'],
        ],
        [
            'name'          => 'Clubic',
            'base_url'      => 'https://www.clubic.com',
            'authors_url'   => 'https://www.clubic.com/auteur/',
            'email_pattern' => '{first}.{last}@clubic.com',
            'email_domain'  => 'clubic.com',
            'media_type'    => 'web',
            'topics'        => ['tech'],
        ],
        [
            'name'          => '01net',
            'base_url'      => 'https://www.01net.com',
            'authors_url'   => 'https://www.01net.com/auteur/',
            'email_pattern' => '{first}.{last}@01net.com',
            'email_domain'  => '01net.com',
            'media_type'    => 'web',
            'topics'        => ['tech', 'entrepreneuriat'],
        ],
        [
            'name'          => 'ZDNet France',
            'base_url'      => 'https://www.zdnet.fr',
            'authors_url'   => 'https://www.zdnet.fr/auteur/',
            'email_pattern' => '{first}.{last}@zdnet.fr',
            'email_domain'  => 'zdnet.fr',
            'media_type'    => 'web',
            'topics'        => ['tech', 'entrepreneuriat'],
        ],
        [
            'name'          => 'Silicon.fr',
            'base_url'      => 'https://www.silicon.fr',
            'authors_url'   => 'https://www.silicon.fr/auteur/',
            'email_pattern' => '{first}.{last}@silicon.fr',
            'email_domain'  => 'silicon.fr',
            'media_type'    => 'web',
            'topics'        => ['tech', 'entrepreneuriat'],
        ],
        [
            'name'          => 'Presse-citron',
            'base_url'      => 'https://www.presse-citron.net',
            'authors_url'   => 'https://www.presse-citron.net/author/',
            'email_pattern' => '{first}.{last}@presse-citron.net',
            'email_domain'  => 'presse-citron.net',
            'media_type'    => 'web',
            'topics'        => ['tech', 'entrepreneuriat'],
        ],
        [
            'name'          => 'Les Numériques',
            'base_url'      => 'https://www.lesnumeriques.com',
            'email_pattern' => '{first}.{last}@lesnumeriques.com',
            'email_domain'  => 'lesnumeriques.com',
            'media_type'    => 'web',
            'topics'        => ['tech'],
        ],

        // ══════════════════════════════════════════════════════════════════
        // VOYAGE & TOURISME
        // ══════════════════════════════════════════════════════════════════
        [
            'name'          => 'GEO Magazine',
            'base_url'      => 'https://www.geo.fr',
            'authors_url'   => 'https://www.geo.fr/auteurs/',
            'email_pattern' => '{first}.{last}@geo.fr',
            'email_domain'  => 'geo.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['voyage', 'international', 'lifestyle'],
        ],
        [
            'name'          => 'National Geographic France',
            'base_url'      => 'https://www.nationalgeographic.fr',
            'authors_url'   => 'https://www.nationalgeographic.fr/auteur/',
            'email_pattern' => '{first}.{last}@nationalgeographic.fr',
            'email_domain'  => 'nationalgeographic.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['voyage', 'international', 'lifestyle'],
        ],
        [
            'name'          => "L'Echo Touristique",
            'base_url'      => 'https://www.lechotouristique.com',
            'authors_url'   => 'https://www.lechotouristique.com/auteur/',
            'email_pattern' => '{first}.{last}@lechotouristique.com',
            'email_domain'  => 'lechotouristique.com',
            'media_type'    => 'web',
            'topics'        => ['voyage', 'business'],
        ],
        [
            'name'          => 'Tourmag',
            'base_url'      => 'https://www.tourmag.com',
            'authors_url'   => 'https://www.tourmag.com/auteur/',
            'email_pattern' => '{first}.{last}@tourmag.com',
            'email_domain'  => 'tourmag.com',
            'media_type'    => 'web',
            'topics'        => ['voyage', 'business'],
        ],
        [
            'name'          => 'Partir Magazine',
            'base_url'      => 'https://www.partir.com',
            'email_pattern' => '{first}.{last}@partir.com',
            'email_domain'  => 'partir.com',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['voyage', 'international'],
        ],
        [
            'name'          => 'Le Routard (édito)',
            'base_url'      => 'https://www.routard.com',
            'team_url'      => 'https://www.routard.com/contact',
            'email_pattern' => '{first}.{last}@routard.com',
            'email_domain'  => 'routard.com',
            'media_type'    => 'web',
            'topics'        => ['voyage', 'international', 'expatriation'],
        ],
        [
            'name'          => 'Petit Futé',
            'base_url'      => 'https://www.petitfute.com',
            'email_pattern' => '{first}.{last}@petitfute.com',
            'email_domain'  => 'petitfute.com',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['voyage', 'international', 'expatriation'],
        ],
        [
            'name'          => 'Voyageurs du Monde (mag)',
            'base_url'      => 'https://www.voyageursdumonde.fr',
            'email_pattern' => '{first}.{last}@vdm.fr',
            'email_domain'  => 'vdm.fr',
            'media_type'    => 'web',
            'topics'        => ['voyage', 'international'],
        ],
        [
            'name'          => 'Grandes Randonnées (FFRandonnée)',
            'base_url'      => 'https://www.ffrandonnee.fr',
            'email_pattern' => '{first}.{last}@ffrandonnee.fr',
            'email_domain'  => 'ffrandonnee.fr',
            'media_type'    => 'web',
            'topics'        => ['voyage', 'lifestyle'],
        ],
        [
            'name'          => 'Voyager Pratique',
            'base_url'      => 'https://www.voyagerpratique.com',
            'email_pattern' => '{first}.{last}@voyagerpratique.com',
            'email_domain'  => 'voyagerpratique.com',
            'media_type'    => 'web',
            'topics'        => ['voyage'],
        ],
        [
            'name'          => 'Curieux Voyageurs',
            'base_url'      => 'https://www.curieuxvoyageurs.com',
            'email_pattern' => '{first}.{last}@curieuxvoyageurs.com',
            'email_domain'  => 'curieuxvoyageurs.com',
            'media_type'    => 'web',
            'topics'        => ['voyage', 'international'],
        ],
        [
            'name'          => 'Aventure du Bout du Monde',
            'base_url'      => 'https://www.abm.fr',
            'email_pattern' => '{first}.{last}@abm.fr',
            'email_domain'  => 'abm.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['voyage', 'expatriation', 'international'],
        ],

        // ══════════════════════════════════════════════════════════════════
        // EXPATRIATION & FRANÇAIS À L'ÉTRANGER
        // ══════════════════════════════════════════════════════════════════
        [
            'name'          => 'Le Petit Journal',
            'base_url'      => 'https://lepetitjournal.com',
            'authors_url'   => 'https://lepetitjournal.com/la-redaction',
            'articles_url'  => 'https://lepetitjournal.com/expat',
            'email_pattern' => '{first}.{last}@lepetitjournal.com',
            'email_domain'  => 'lepetitjournal.com',
            'media_type'    => 'web',
            'topics'        => ['expatriation', 'international', 'voyage'],
        ],
        [
            'name'          => "Français à l'Étranger",
            'base_url'      => 'https://www.francaisaletranger.fr',
            'email_pattern' => '{first}.{last}@francaisaletranger.fr',
            'email_domain'  => 'francaisaletranger.fr',
            'media_type'    => 'web',
            'topics'        => ['expatriation', 'international'],
        ],
        [
            'name'          => 'French Morning',
            'base_url'      => 'https://frenchmorning.com',
            'authors_url'   => 'https://frenchmorning.com/auteurs/',
            'email_pattern' => '{first}.{last}@frenchmorning.com',
            'email_domain'  => 'frenchmorning.com',
            'media_type'    => 'web',
            'topics'        => ['expatriation', 'international', 'lifestyle'],
        ],
        [
            'name'          => 'Courrier International',
            'base_url'      => 'https://www.courrierinternational.com',
            'authors_url'   => 'https://www.courrierinternational.com/auteur/',
            'email_pattern' => '{first}.{last}@courrierinternational.com',
            'email_domain'  => 'courrierinternational.com',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['international', 'business', 'voyage'],
        ],
        [
            'name'          => 'Connexion France (Anglophone France)',
            'base_url'      => 'https://www.connexionfrance.com',
            'authors_url'   => 'https://www.connexionfrance.com/equipe',
            'email_pattern' => '{first}.{last}@connexionfrance.com',
            'email_domain'  => 'connexionfrance.com',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['expatriation', 'international', 'lifestyle'],
            'country'       => 'France (en anglais)',
        ],

        // ══════════════════════════════════════════════════════════════════
        // TV & MÉDIAS AUDIOVISUELS
        // ══════════════════════════════════════════════════════════════════
        [
            'name'          => 'BFM TV',
            'base_url'      => 'https://www.bfmtv.com',
            'authors_url'   => 'https://www.bfmtv.com/mediaplayer/bio/',
            'email_pattern' => '{first}.{last}@bfmtv.com',
            'email_domain'  => 'bfmtv.com',
            'media_type'    => 'tv',
            'topics'        => ['international', 'business', 'entrepreneuriat'],
        ],
        [
            'name'          => 'France 24',
            'base_url'      => 'https://www.france24.com',
            'authors_url'   => 'https://www.france24.com/fr/liste/reporters/',
            'email_pattern' => '{first}.{last}@france24.com',
            'email_domain'  => 'france24.com',
            'media_type'    => 'tv',
            'topics'        => ['international', 'business', 'voyage', 'expatriation'],
        ],
        [
            'name'          => 'LCI',
            'base_url'      => 'https://www.lci.fr',
            'authors_url'   => 'https://www.lci.fr/equipe/',
            'email_pattern' => '{first}.{last}@lci.fr',
            'email_domain'  => 'lci.fr',
            'media_type'    => 'tv',
            'topics'        => ['international', 'business', 'voyage'],
        ],
        [
            'name'          => 'CNews',
            'base_url'      => 'https://www.cnews.fr',
            'authors_url'   => 'https://www.cnews.fr/nos-equipes/',
            'email_pattern' => '{first}.{last}@cnews.fr',
            'email_domain'  => 'cnews.fr',
            'media_type'    => 'tv',
            'topics'        => ['international', 'business'],
        ],
        [
            'name'          => 'TV5 Monde',
            'base_url'      => 'https://www.tv5monde.com',
            'authors_url'   => 'https://www.tv5monde.com/nous-connaitre',
            'email_pattern' => '{first}.{last}@tv5monde.com',
            'email_domain'  => 'tv5monde.com',
            'media_type'    => 'tv',
            'topics'        => ['international', 'voyage', 'expatriation'],
        ],
        [
            'name'          => 'Arte France',
            'base_url'      => 'https://www.arte.tv',
            'team_url'      => 'https://www.arte.tv/fr/arte-info/presse/notre-equipe/',
            'email_pattern' => '{first}.{last}@artefrance.fr',
            'email_domain'  => 'artefrance.fr',
            'media_type'    => 'tv',
            'topics'        => ['international', 'voyage', 'lifestyle'],
        ],
        [
            'name'          => 'France Télévisions (France 2)',
            'base_url'      => 'https://www.france.tv',
            'authors_url'   => 'https://www.francetvinfo.fr/redaction/',
            'email_pattern' => '{first}.{last}@francetv.fr',
            'email_domain'  => 'francetv.fr',
            'media_type'    => 'tv',
            'topics'        => ['international', 'voyage', 'expatriation', 'business'],
        ],
        [
            'name'          => 'franceinfo (web+TV)',
            'base_url'      => 'https://www.francetvinfo.fr',
            'authors_url'   => 'https://www.francetvinfo.fr/redaction/',
            'email_pattern' => '{first}.{last}@francetvinfo.fr',
            'email_domain'  => 'francetvinfo.fr',
            'media_type'    => 'tv',
            'topics'        => ['international', 'business', 'voyage'],
        ],

        // ══════════════════════════════════════════════════════════════════
        // RADIO
        // ══════════════════════════════════════════════════════════════════
        [
            'name'          => 'RFI',
            'base_url'      => 'https://www.rfi.fr',
            'authors_url'   => 'https://www.rfi.fr/fr/profil/',
            'email_pattern' => '{first}.{last}@rfi.fr',
            'email_domain'  => 'rfi.fr',
            'media_type'    => 'radio',
            'topics'        => ['international', 'business', 'expatriation', 'voyage'],
        ],
        [
            'name'          => 'France Inter',
            'base_url'      => 'https://www.radiofrance.fr/franceinter',
            'authors_url'   => 'https://www.radiofrance.fr/franceinter/equipe',
            'email_pattern' => '{first}.{last}@radiofrance.fr',
            'email_domain'  => 'radiofrance.fr',
            'media_type'    => 'radio',
            'topics'        => ['international', 'business', 'entrepreneuriat'],
        ],
        [
            'name'          => 'France Culture',
            'base_url'      => 'https://www.radiofrance.fr/franceculture',
            'authors_url'   => 'https://www.radiofrance.fr/franceculture/equipe',
            'email_pattern' => '{first}.{last}@radiofrance.fr',
            'email_domain'  => 'radiofrance.fr',
            'media_type'    => 'radio',
            'topics'        => ['international', 'voyage', 'lifestyle'],
        ],
        [
            'name'          => 'France Info Radio',
            'base_url'      => 'https://www.franceinfo.fr',
            'authors_url'   => 'https://www.franceinfo.fr/equipe',
            'email_pattern' => '{first}.{last}@radiofrance.fr',
            'email_domain'  => 'radiofrance.fr',
            'media_type'    => 'radio',
            'topics'        => ['international', 'business'],
        ],
        [
            'name'          => 'Europe 1',
            'base_url'      => 'https://www.europe1.fr',
            'authors_url'   => 'https://www.europe1.fr/equipe',
            'email_pattern' => '{first}.{last}@europe1.fr',
            'email_domain'  => 'europe1.fr',
            'media_type'    => 'radio',
            'topics'        => ['business', 'entrepreneuriat', 'international'],
        ],
        [
            'name'          => 'RTL',
            'base_url'      => 'https://www.rtl.fr',
            'authors_url'   => 'https://www.rtl.fr/equipe',
            'email_pattern' => '{first}.{last}@rtl.fr',
            'email_domain'  => 'rtl.fr',
            'media_type'    => 'radio',
            'topics'        => ['business', 'voyage', 'international'],
        ],
        [
            'name'          => 'RMC',
            'base_url'      => 'https://rmc.bfmtv.com',
            'authors_url'   => 'https://rmc.bfmtv.com/equipe',
            'email_pattern' => '{first}.{last}@rmc.fr',
            'email_domain'  => 'rmc.fr',
            'media_type'    => 'radio',
            'topics'        => ['business', 'international'],
        ],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE RÉGIONALE FRANÇAISE (40+ millions de lecteurs cumulés)
        // ══════════════════════════════════════════════════════════════════
        [
            'name'          => 'Ouest-France',
            'base_url'      => 'https://www.ouest-france.fr',
            'authors_url'   => 'https://www.ouest-france.fr/auteur/',
            'email_pattern' => '{first}.{last}@ouest-france.fr',
            'email_domain'  => 'ouest-france.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'voyage', 'international'],
        ],
        [
            'name'          => 'Le Télégramme',
            'base_url'      => 'https://www.letelegramme.fr',
            'email_pattern' => '{first}.{last}@letelegramme.fr',
            'email_domain'  => 'letelegramme.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'voyage'],
        ],
        [
            'name'          => 'La Voix du Nord',
            'base_url'      => 'https://www.lavoixdunord.fr',
            'authors_url'   => 'https://www.lavoixdunord.fr/auteur/',
            'email_pattern' => '{first}.{last}@lavoixdunord.fr',
            'email_domain'  => 'lavoixdunord.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'international'],
        ],
        [
            'name'          => 'Sud Ouest',
            'base_url'      => 'https://www.sudouest.fr',
            'email_pattern' => '{first}.{last}@sudouest.fr',
            'email_domain'  => 'sudouest.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'voyage'],
        ],
        [
            'name'          => 'Le Progrès',
            'base_url'      => 'https://www.leprogres.fr',
            'email_pattern' => '{first}.{last}@leprogres.fr',
            'email_domain'  => 'leprogres.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business'],
        ],
        [
            'name'          => 'La Dépêche du Midi',
            'base_url'      => 'https://www.ladepeche.fr',
            'email_pattern' => '{first}.{last}@ladepeche.fr',
            'email_domain'  => 'ladepeche.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'voyage'],
        ],
        [
            'name'          => 'La Montagne',
            'base_url'      => 'https://www.lamontagne.fr',
            'email_pattern' => '{first}.{last}@centrefrance.com',
            'email_domain'  => 'centrefrance.com',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'voyage'],
        ],
        [
            'name'          => "L'Est Républicain",
            'base_url'      => 'https://www.estrepublicain.fr',
            'email_pattern' => '{first}.{last}@republicain-lorrain.fr',
            'email_domain'  => 'estrepublicain.fr',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'international'],
        ],
        [
            'name'          => 'Le Dauphiné Libéré',
            'base_url'      => 'https://www.ledauphine.com',
            'email_pattern' => '{first}.{last}@ledauphine.com',
            'email_domain'  => 'ledauphine.com',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'voyage'],
        ],
        [
            'name'          => 'Nice-Matin',
            'base_url'      => 'https://www.nicematin.com',
            'email_pattern' => '{first}.{last}@nicematin.com',
            'email_domain'  => 'nicematin.com',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['voyage', 'international', 'expatriation'],
        ],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE FRANCOPHONE INTERNATIONALE
        // ══════════════════════════════════════════════════════════════════
        [
            'name'          => 'Le Soir (Belgique)',
            'base_url'      => 'https://www.lesoir.be',
            'authors_url'   => 'https://www.lesoir.be/auteur/',
            'email_pattern' => '{first}.{last}@lesoir.be',
            'email_domain'  => 'lesoir.be',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'international', 'expatriation'],
            'country'       => 'Belgique',
        ],
        [
            'name'          => 'La Libre Belgique',
            'base_url'      => 'https://www.lalibre.be',
            'authors_url'   => 'https://www.lalibre.be/auteur/',
            'email_pattern' => '{first}.{last}@lalibre.be',
            'email_domain'  => 'lalibre.be',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'international', 'expatriation'],
            'country'       => 'Belgique',
        ],
        [
            'name'          => 'RTL Belgique Info',
            'base_url'      => 'https://www.rtlinfo.be',
            'email_pattern' => '{first}.{last}@rtlinfo.be',
            'email_domain'  => 'rtlinfo.be',
            'media_type'    => 'tv',
            'topics'        => ['international', 'business'],
            'country'       => 'Belgique',
        ],
        [
            'name'          => 'RTBF',
            'base_url'      => 'https://www.rtbf.be',
            'email_pattern' => '{first}.{last}@rtbf.be',
            'email_domain'  => 'rtbf.be',
            'media_type'    => 'tv',
            'topics'        => ['international', 'business', 'voyage'],
            'country'       => 'Belgique',
        ],
        [
            'name'          => 'Le Temps (Suisse)',
            'base_url'      => 'https://www.letemps.ch',
            'authors_url'   => 'https://www.letemps.ch/auteur/',
            'email_pattern' => '{first}.{last}@letemps.ch',
            'email_domain'  => 'letemps.ch',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'international', 'expatriation'],
            'country'       => 'Suisse',
        ],
        [
            'name'          => "L'Hebdo / Heidi.news (Suisse)",
            'base_url'      => 'https://www.heidi.news',
            'authors_url'   => 'https://www.heidi.news/auteur/',
            'email_pattern' => '{first}.{last}@heidi.news',
            'email_domain'  => 'heidi.news',
            'media_type'    => 'web',
            'topics'        => ['international', 'business'],
            'country'       => 'Suisse',
        ],
        [
            'name'          => 'RTS (Radio Télévision Suisse)',
            'base_url'      => 'https://www.rts.ch',
            'email_pattern' => '{first}.{last}@rts.ch',
            'email_domain'  => 'rts.ch',
            'media_type'    => 'tv',
            'topics'        => ['international', 'business', 'voyage'],
            'country'       => 'Suisse',
        ],
        [
            'name'          => 'Le Devoir (Canada)',
            'base_url'      => 'https://www.ledevoir.com',
            'authors_url'   => 'https://www.ledevoir.com/auteur/',
            'email_pattern' => '{first}.{last}@ledevoir.com',
            'email_domain'  => 'ledevoir.com',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['business', 'international', 'expatriation'],
            'country'       => 'Canada',
        ],
        [
            'name'          => 'La Presse (Canada)',
            'base_url'      => 'https://www.lapresse.ca',
            'authors_url'   => 'https://www.lapresse.ca/auteur/',
            'email_pattern' => '{first}.{last}@lapresse.ca',
            'email_domain'  => 'lapresse.ca',
            'media_type'    => 'web',
            'topics'        => ['business', 'international', 'voyage'],
            'country'       => 'Canada',
        ],
        [
            'name'          => 'Radio-Canada',
            'base_url'      => 'https://ici.radio-canada.ca',
            'authors_url'   => 'https://ici.radio-canada.ca/auteur/',
            'email_pattern' => '{first}.{last}@radio-canada.ca',
            'email_domain'  => 'radio-canada.ca',
            'media_type'    => 'tv',
            'topics'        => ['international', 'business', 'voyage'],
            'country'       => 'Canada',
        ],
        [
            'name'          => 'Jeune Afrique',
            'base_url'      => 'https://www.jeuneafrique.com',
            'authors_url'   => 'https://www.jeuneafrique.com/auteur/',
            'email_pattern' => '{first}.{last}@jeuneafrique.com',
            'email_domain'  => 'jeuneafrique.com',
            'media_type'    => 'presse_ecrite',
            'topics'        => ['international', 'business', 'expatriation'],
            'country'       => 'Afrique (basé en France)',
        ],
        [
            'name'          => 'Africanews',
            'base_url'      => 'https://fr.africanews.com',
            'email_pattern' => '{first}.{last}@africanews.com',
            'email_domain'  => 'africanews.com',
            'media_type'    => 'web',
            'topics'        => ['international', 'business', 'expatriation'],
            'country'       => 'International',
        ],
        [
            'name'          => 'Mondafrique',
            'base_url'      => 'https://mondafrique.com',
            'authors_url'   => 'https://mondafrique.com/auteur/',
            'email_pattern' => '{first}.{last}@mondafrique.com',
            'email_domain'  => 'mondafrique.com',
            'media_type'    => 'web',
            'topics'        => ['international', 'business', 'expatriation'],
        ],

        // ══════════════════════════════════════════════════════════════════
        // ANNUAIRES DE JOURNALISTES (sources directes de masse)
        // ══════════════════════════════════════════════════════════════════
        [
            'name'          => 'Annuaire Journaliste FR (SNJ)',
            'base_url'      => 'https://annuaire.journaliste.fr',
            'authors_url'   => 'https://annuaire.journaliste.fr/annuaire',
            'team_url'      => 'https://annuaire.journaliste.fr/annuaire',
            'email_pattern' => null,
            'email_domain'  => null,
            'media_type'    => 'web',
            'topics'        => ['entrepreneuriat', 'voyage', 'international', 'expatriation', 'business'],
        ],
        [
            'name'          => 'Presselib (Journalistes Freelances)',
            'base_url'      => 'https://www.presselib.com',
            'authors_url'   => 'https://www.presselib.com/annuaire-journalistes/',
            'team_url'      => 'https://www.presselib.com/annuaire-journalistes/',
            'email_pattern' => null,
            'email_domain'  => null,
            'media_type'    => 'web',
            'topics'        => ['entrepreneuriat', 'voyage', 'international', 'expatriation'],
        ],
        [
            'name'          => 'Muck Rack France',
            'base_url'      => 'https://muckrack.com',
            'authors_url'   => 'https://muckrack.com/search?q=journaliste+france&type=journalists',
            'email_pattern' => null,
            'email_domain'  => null,
            'media_type'    => 'web',
            'topics'        => ['entrepreneuriat', 'voyage', 'international', 'expatriation', 'business'],
        ],
        [
            'name'          => 'The Conversation France',
            'base_url'      => 'https://theconversation.com/fr',
            'authors_url'   => 'https://theconversation.com/fr/profiles',
            'email_pattern' => null,
            'email_domain'  => null,
            'media_type'    => 'web',
            'topics'        => ['entrepreneuriat', 'international', 'business'],
        ],
        [
            'name'          => 'Lanetworkerie Répertoire',
            'base_url'      => 'https://www.lanetworkerie.com',
            'authors_url'   => 'https://www.lanetworkerie.com/repertoire-medias',
            'email_pattern' => null,
            'email_domain'  => null,
            'media_type'    => 'web',
            'topics'        => ['entrepreneuriat', 'international', 'tech'],
        ],
    ];

    public function handle(): int
    {
        $reset = $this->option('reset');

        if ($reset) {
            PressPublication::truncate();
            $this->info('Publications effacées.');
        }

        $inserted = 0;
        $updated  = 0;

        foreach ($this->publications as $pub) {
            $slug    = Str::slug($pub['name']);
            $topics  = $pub['topics'] ?? [];
            $type    = $pub['media_type'] ?? 'web';

            // Derive category if not explicitly set
            $category = $pub['category'] ?? $this->deriveCategory($topics, $type, $pub['name'], $pub['base_url'] ?? '');

            $data = [
                'name'          => $pub['name'],
                'base_url'      => $pub['base_url'],
                'authors_url'   => $pub['authors_url'] ?? null,
                'articles_url'  => $pub['articles_url'] ?? null,
                'team_url'      => $pub['team_url'] ?? null,
                'contact_url'   => $pub['contact_url'] ?? null,
                'email_pattern' => $pub['email_pattern'] ?? null,
                'email_domain'  => $pub['email_domain'] ?? null,
                'media_type'    => $type,
                'category'      => $category,
                'topics'        => $topics,
                'language'      => $pub['language'] ?? 'fr',
                'country'       => $pub['country'] ?? 'France',
            ];

            $existing = PressPublication::where('slug', $slug)->first();
            if ($existing) {
                $existing->update(array_merge($data, ['status' => $existing->status]));
                $updated++;
            } else {
                PressPublication::create(array_merge($data, ['slug' => $slug, 'status' => 'pending']));
                $inserted++;
            }
        }

        $total = PressPublication::count();
        $this->info("✓ {$inserted} nouvelles, {$updated} mises à jour. Total: {$total} publications.");

        $byType = PressPublication::selectRaw('media_type, COUNT(*) as n')->groupBy('media_type')->pluck('n', 'media_type');
        foreach ($byType as $type => $n) {
            $this->line("  [{$type}] {$n}");
        }

        $byCategory = PressPublication::selectRaw('category, COUNT(*) as n')->groupBy('category')->orderByDesc('n')->pluck('n', 'category');
        $this->line('');
        $this->info('Par catégorie :');
        foreach ($byCategory as $cat => $n) {
            $this->line("  [{$cat}] {$n}");
        }

        return Command::SUCCESS;
    }

    /**
     * Derive an editorial category from topics, media_type, name and URL.
     *
     * Categories:
     *   presse_nationale        National generalist daily/weekly (Le Monde, Le Figaro…)
     *   magazine_generaliste    News magazines (L'Obs, L'Express, Le Point…)
     *   presse_economique       Economic/financial press (Les Échos, Capital…)
     *   presse_entrepreneuriat  Startup/entrepreneur-focused press (Maddyness, FrenchWeb…)
     *   presse_tech             Tech/digital media (01net, Siècle Digital…)
     *   presse_voyage           Travel press (Routard, Geo, Voyages…)
     *   presse_expat            Expat-focused press (LePetitJournal, FrenchMorning…)
     *   presse_juridique        Legal/law press (Le Monde du Droit, Dalloz…)
     *   presse_lifestyle        Lifestyle, culture, people
     *   presse_sante            Health/medical press
     *   presse_regionale        French regional press (Ouest-France, La Dépêche…)
     *   presse_francophone      International francophone press (Le Soir, Le Devoir, Jeune Afrique…)
     *   tv_news                 General news TV (BFM TV, France 24, TF1…)
     *   tv_economique           Business TV (BFM Business, LCI…)
     *   radio_nationale         National radio (France Inter, Europe 1, RTL…)
     *   radio_internationale    International radio (RFI, RTS…)
     *   annuaire_presse         Journalist directories (annuaire.journaliste.fr, muckrack…)
     */
    private function deriveCategory(array $topics, string $mediaType, string $name, string $baseUrl): string
    {
        $nameL = strtolower($name);
        $urlL  = strtolower($baseUrl);

        // ── Annuaires ────────────────────────────────────────────────────
        if (str_contains($urlL, 'annuaire') || str_contains($nameL, 'annuaire') ||
            str_contains($urlL, 'muckrack') || str_contains($urlL, 'presselib') ||
            str_contains($nameL, 'répertoire') || str_contains($nameL, 'directory')) {
            return 'annuaire_presse';
        }

        // ── TV ───────────────────────────────────────────────────────────
        if ($mediaType === 'tv') {
            if (in_array('business', $topics) || str_contains($nameL, 'bfm business') || str_contains($nameL, 'lci')) {
                return 'tv_economique';
            }
            return 'tv_news';
        }

        // ── Radio ────────────────────────────────────────────────────────
        if ($mediaType === 'radio') {
            if (str_contains($urlL, 'rfi.fr') || str_contains($urlL, 'rts.') || str_contains($nameL, 'international')) {
                return 'radio_internationale';
            }
            return 'radio_nationale';
        }

        // ── Presse expat ─────────────────────────────────────────────────
        if (in_array('expatriation', $topics) ||
            str_contains($nameL, 'expat') || str_contains($urlL, 'expat') ||
            str_contains($nameL, 'petit journal') || str_contains($urlL, 'lepetitjournal') ||
            str_contains($urlL, 'frenchmorning') || str_contains($nameL, 'connexion') ||
            str_contains($nameL, 'français de l\'étranger') || str_contains($urlL, 'french-morning')) {
            return 'presse_expat';
        }

        // ── Presse voyage ────────────────────────────────────────────────
        if (in_array('voyage', $topics) &&
            !in_array('business', $topics) && !in_array('entrepreneuriat', $topics)) {
            return 'presse_voyage';
        }
        if (str_contains($nameL, 'routard') || str_contains($nameL, 'géo') || str_contains($nameL, 'geo')
            || str_contains($urlL, 'routard') || str_contains($nameL, 'voyages')
            || str_contains($nameL, 'travel') || str_contains($nameL, 'tourism')) {
            return 'presse_voyage';
        }

        // ── Presse juridique ─────────────────────────────────────────────
        if (str_contains($nameL, 'droit') || str_contains($nameL, 'juriste') ||
            str_contains($nameL, 'juridique') || str_contains($urlL, 'dalloz') ||
            str_contains($urlL, 'lexis') || str_contains($urlL, 'legifrance') ||
            in_array('juridique', $topics) || in_array('legal', $topics)) {
            return 'presse_juridique';
        }

        // ── Presse tech ──────────────────────────────────────────────────
        if (in_array('tech', $topics) && count(array_intersect($topics, ['entrepreneuriat', 'startup'])) > 0) {
            // tech + startup = entrepreneuriat
        }
        if (in_array('tech', $topics) && !in_array('entrepreneuriat', $topics) && !in_array('startup', $topics)) {
            return 'presse_tech';
        }
        if (str_contains($nameL, '01net') || str_contains($urlL, '01net') ||
            str_contains($nameL, 'zdnet') || str_contains($nameL, 'numerama') ||
            str_contains($urlL, 'silicon') || str_contains($nameL, 'presse-citron')) {
            return 'presse_tech';
        }

        // ── Presse entrepreneuriat ───────────────────────────────────────
        if (in_array('startup', $topics) || in_array('entrepreneuriat', $topics)) {
            // If it's also a major economic title, keep as economique
            $majorEco = ['Les Echos', 'La Tribune', 'Capital', 'Challenges', 'Forbes', 'Le Revenu', 'BFM Business'];
            foreach ($majorEco as $eco) {
                if (str_contains($nameL, strtolower($eco))) {
                    return 'presse_economique';
                }
            }
            return 'presse_entrepreneuriat';
        }

        // ── Presse économique ────────────────────────────────────────────
        if (in_array('business', $topics)) {
            // Check if it's a national daily
            $nationals = ['Le Monde', 'Le Figaro', 'Libération', 'L\'Humanité', '20 Minutes', 'Le Parisien'];
            foreach ($nationals as $nat) {
                if (str_contains($nameL, strtolower($nat))) {
                    return 'presse_nationale';
                }
            }
            // Check if it's a newsmagazine
            $magazines = ['l\'obs', 'l\'express', 'le point', 'marianne', 'canard', 'mediapart', 'valeurs actuelles'];
            foreach ($magazines as $mag) {
                if (str_contains($nameL, $mag)) {
                    return 'magazine_generaliste';
                }
            }
            return 'presse_economique';
        }

        // ── Presse francophone internationale ────────────────────────────
        $francoCountries = ['Belgium', 'Belgique', 'Suisse', 'Switzerland', 'Canada', 'Québec', 'Maroc', 'Tunisie', 'Sénégal', 'Côte', 'Afrique'];
        foreach ($francoCountries as $fc) {
            if (str_contains($name, $fc) || str_contains($baseUrl, strtolower($fc))) {
                return 'presse_francophone';
            }
        }
        if (str_contains($urlL, '.be') || str_contains($urlL, '.ch') ||
            str_contains($urlL, 'rts.ch') || str_contains($urlL, 'rtbf') ||
            str_contains($nameL, 'jeune afrique') || str_contains($nameL, 'le devoir') ||
            str_contains($nameL, 'le soir') || str_contains($nameL, 'la libre')) {
            return 'presse_francophone';
        }

        // ── Presse régionale ─────────────────────────────────────────────
        $regionals = ['ouest-france', 'la dépêche', 'le dauphiné', 'la voix du nord', 'sud ouest', 'nice-matin', 'le progrès', 'l\'alsace', 'le télégramme'];
        foreach ($regionals as $r) {
            if (str_contains($nameL, $r)) {
                return 'presse_regionale';
            }
        }

        // ── Presse nationale (fallback for major dailies) ─────────────────
        $nationalIndicators = ['monde', 'figaro', 'libération', 'humanité', 'parisien', '20 minutes'];
        foreach ($nationalIndicators as $ni) {
            if (str_contains($nameL, $ni)) {
                return 'presse_nationale';
            }
        }

        // ── Magazine généraliste fallback ─────────────────────────────────
        $magIndicators = ["l'obs", "l'express", 'le point', 'marianne', 'canard', 'mediapart', 'l\'hebdo'];
        foreach ($magIndicators as $mi) {
            if (str_contains($nameL, $mi)) {
                return 'magazine_generaliste';
            }
        }

        // Default
        return $mediaType === 'presse_ecrite' ? 'presse_nationale' : 'presse_economique';
    }
}
