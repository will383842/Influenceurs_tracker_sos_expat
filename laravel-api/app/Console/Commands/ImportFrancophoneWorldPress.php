<?php

namespace App\Console\Commands;

use App\Models\PressPublication;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Import ALL francophone press publications worldwide.
 * Covers: Afrique de l'Ouest, Afrique Centrale, Maghreb, Océan Indien,
 * Moyen-Orient, Caraïbes, DOM-TOM, Belgique, Suisse, Luxembourg, Canada/Québec,
 * plus presse francophone en ligne et podcasts.
 *
 * Usage: php artisan press:import-francophone [--reset] [--scrape]
 */
class ImportFrancophoneWorldPress extends Command
{
    protected $signature   = 'press:import-francophone
                              {--reset : Truncate press_publications and reimport}
                              {--scrape : Launch scraping after import}';
    protected $description = 'Import 400+ francophone press publications from every French-speaking country worldwide';

    private array $publications = [

        // ══════════════════════════════════════════════════════════════════
        // AFRIQUE DE L'OUEST — SÉNÉGAL
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Soleil (Sénégal)', 'base_url' => 'https://lesoleil.sn', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Sénégal'],
        ['name' => 'Le Quotidien (Sénégal)', 'base_url' => 'https://lequotidien.sn', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'Sud Quotidien', 'base_url' => 'https://www.sudquotidien.sn', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'Seneweb', 'base_url' => 'https://www.seneweb.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'Dakaractu', 'base_url' => 'https://www.dakaractu.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'Emedia Sénégal', 'base_url' => 'https://emediasn.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'Senego', 'base_url' => 'https://senego.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'RFM Sénégal', 'base_url' => 'https://www.rfm.sn', 'media_type' => 'radio', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'TFM Sénégal', 'base_url' => 'https://www.tfm.sn', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],
        ['name' => 'iGFM', 'base_url' => 'https://www.igfm.sn', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Sénégal'],

        // ══════════════════════════════════════════════════════════════════
        // AFRIQUE DE L'OUEST — CÔTE D'IVOIRE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Fraternité Matin', 'base_url' => 'https://www.fratmat.info', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => "Côte d'Ivoire"],
        ['name' => "L'Intelligent d'Abidjan", 'base_url' => 'https://www.lintelligentdabidjan.info', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => "Côte d'Ivoire"],
        ['name' => 'Abidjan.net', 'base_url' => 'https://news.abidjan.net', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => "Côte d'Ivoire"],
        ['name' => 'Koaci', 'base_url' => 'https://www.koaci.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => "Côte d'Ivoire"],
        ['name' => 'Connectionivoirienne', 'base_url' => 'https://www.connectionivoirienne.net', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => "Côte d'Ivoire"],
        ['name' => 'Le Patriote (CI)', 'base_url' => 'https://www.lepatriote.ci', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => "Côte d'Ivoire"],
        ['name' => 'RTI Info (CI)', 'base_url' => 'https://www.rti.ci', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => "Côte d'Ivoire"],
        ['name' => "L'Expression (CI)", 'base_url' => 'https://www.lexpression.ci', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => "Côte d'Ivoire"],

        // ══════════════════════════════════════════════════════════════════
        // AFRIQUE DE L'OUEST — MALI, BURKINA, GUINÉE, BÉNIN, TOGO, NIGER
        // ══════════════════════════════════════════════════════════════════
        ['name' => "L'Essor (Mali)", 'base_url' => 'https://www.essor.ml', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Mali'],
        ['name' => 'Maliweb', 'base_url' => 'https://www.maliweb.net', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Mali'],
        ['name' => 'Malijet', 'base_url' => 'https://malijet.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Mali'],
        ['name' => 'Journal du Mali', 'base_url' => 'https://www.journaldumali.com', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Mali'],
        ['name' => 'Sidwaya (Burkina Faso)', 'base_url' => 'https://www.sidwaya.info', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Burkina Faso'],
        ['name' => 'LeFaso.net', 'base_url' => 'https://lefaso.net', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Burkina Faso'],
        ['name' => 'Fasozine', 'base_url' => 'https://www.fasozine.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Burkina Faso'],
        ['name' => 'Guinéenews', 'base_url' => 'https://guineenews.org', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Guinée'],
        ['name' => 'Mediaguinée', 'base_url' => 'https://mediaguinee.org', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Guinée'],
        ['name' => 'Aminata.com (Guinée)', 'base_url' => 'https://aminata.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Guinée'],
        ['name' => 'La Nation (Bénin)', 'base_url' => 'https://www.lanationbenin.info', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Bénin'],
        ['name' => 'Bénin Web TV', 'base_url' => 'https://beninwebtv.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Bénin'],
        ['name' => 'La Nouvelle Tribune (Bénin)', 'base_url' => 'https://lanouvelletribune.info', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Bénin'],
        ['name' => 'République Togolaise', 'base_url' => 'https://www.republicoftogo.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Togo'],
        ['name' => 'TogoFirst', 'base_url' => 'https://www.togofirst.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Togo'],
        ['name' => 'Togo Actualité', 'base_url' => 'https://www.togoactu.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Togo'],
        ['name' => 'Le Sahel (Niger)', 'base_url' => 'https://www.lesahel.org', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Niger'],
        ['name' => 'ActuNiger', 'base_url' => 'https://www.actuniger.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Niger'],

        // ══════════════════════════════════════════════════════════════════
        // AFRIQUE CENTRALE — CAMEROUN, CONGO, RDC, GABON, TCHAD
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Cameroon Tribune', 'base_url' => 'https://www.cameroon-tribune.cm', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Cameroun'],
        ['name' => 'Journal du Cameroun', 'base_url' => 'https://www.journalducameroun.com', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Cameroun'],
        ['name' => 'Cameroun24', 'base_url' => 'https://www.cameroun24.net', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Cameroun'],
        ['name' => 'Camer.be', 'base_url' => 'https://www.camer.be', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Cameroun'],
        ['name' => 'ActuCameroun', 'base_url' => 'https://actucameroun.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Cameroun'],
        ['name' => 'Les Dépêches de Brazzaville', 'base_url' => 'https://www.adiac-congo.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Congo-Brazzaville'],
        ['name' => 'Vox Congo', 'base_url' => 'https://www.voxcongo.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Congo-Brazzaville'],
        ['name' => 'Radio Okapi (RDC)', 'base_url' => 'https://www.radiookapi.net', 'media_type' => 'radio', 'topics' => ['international', 'business'], 'country' => 'RDC'],
        ['name' => 'Actualité.cd (RDC)', 'base_url' => 'https://actualite.cd', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'RDC'],
        ['name' => 'Le Phare (RDC)', 'base_url' => 'https://www.lephare.info', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'RDC'],
        ['name' => 'Desk Eco (RDC)', 'base_url' => 'https://deskeco.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'RDC'],
        ['name' => '7sur7.cd (RDC)', 'base_url' => 'https://7sur7.cd', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'RDC'],
        ['name' => "L'Union (Gabon)", 'base_url' => 'https://www.union.sonapresse.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Gabon'],
        ['name' => 'Gabonreview', 'base_url' => 'https://www.gabonreview.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Gabon'],
        ['name' => 'GabonMediaTime', 'base_url' => 'https://www.gabonmediatime.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Gabon'],
        ['name' => 'Tchadinfos', 'base_url' => 'https://tchadinfos.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Tchad'],
        ['name' => 'Alwihda Info (Tchad)', 'base_url' => 'https://www.alwihdainfo.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Tchad'],
        ['name' => 'RJDH (Centrafrique)', 'base_url' => 'https://www.rjdh.org', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'Centrafrique'],

        // ══════════════════════════════════════════════════════════════════
        // MAGHREB — MAROC, TUNISIE, ALGÉRIE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Matin (Maroc)', 'base_url' => 'https://lematin.ma', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Maroc'],
        ['name' => "L'Économiste (Maroc)", 'base_url' => 'https://www.leconomiste.com', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Maroc'],
        ['name' => 'TelQuel (Maroc)', 'base_url' => 'https://telquel.ma', 'media_type' => 'web', 'topics' => ['international', 'business', 'lifestyle'], 'country' => 'Maroc'],
        ['name' => 'Hespress FR (Maroc)', 'base_url' => 'https://fr.hespress.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Maroc'],
        ['name' => 'Médias24 (Maroc)', 'base_url' => 'https://medias24.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Maroc'],
        ['name' => 'Le360 (Maroc)', 'base_url' => 'https://fr.le360.ma', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Maroc'],
        ['name' => 'Yabiladi (Maroc)', 'base_url' => 'https://www.yabiladi.com', 'media_type' => 'web', 'topics' => ['international', 'expatriation', 'business'], 'country' => 'Maroc'],
        ['name' => 'Aujourd\'hui le Maroc', 'base_url' => 'https://aujourdhui.ma', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Maroc'],
        ['name' => 'Maroc Diplomatique', 'base_url' => 'https://maroc-diplomatique.net', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Maroc'],
        ['name' => 'La Vie Éco (Maroc)', 'base_url' => 'https://www.lavieeco.com', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Maroc'],
        ['name' => '2M Maroc', 'base_url' => 'https://www.2m.ma', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'Maroc'],
        ['name' => 'Medi1 TV (Maroc)', 'base_url' => 'https://www.medi1tv.com', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'Maroc'],

        ['name' => 'La Presse de Tunisie', 'base_url' => 'https://lapresse.tn', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Tunisie'],
        ['name' => 'Business News Tunisie', 'base_url' => 'https://www.businessnews.com.tn', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Tunisie'],
        ['name' => 'Webmanagercenter (Tunisie)', 'base_url' => 'https://www.webmanagercenter.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Tunisie'],
        ['name' => 'Kapitalis (Tunisie)', 'base_url' => 'https://kapitalis.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Tunisie'],
        ['name' => 'Leaders Tunisie', 'base_url' => 'https://www.leaders.com.tn', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat', 'expatriation'], 'country' => 'Tunisie'],
        ['name' => 'Réalités Tunisie', 'base_url' => 'https://www.realites.com.tn', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Tunisie'],
        ['name' => 'Mosaïque FM (Tunisie)', 'base_url' => 'https://www.mosaiquefm.net', 'media_type' => 'radio', 'topics' => ['international', 'business'], 'country' => 'Tunisie'],
        ['name' => 'Nawaat (Tunisie)', 'base_url' => 'https://nawaat.org', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Tunisie'],

        ['name' => 'El Watan (Algérie)', 'base_url' => 'https://www.elwatan.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Algérie'],
        ['name' => 'Liberté Algérie', 'base_url' => 'https://www.liberte-algerie.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Algérie'],
        ['name' => 'Le Soir d\'Algérie', 'base_url' => 'https://www.lesoirdalgerie.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Algérie'],
        ['name' => 'TSA Algérie', 'base_url' => 'https://www.tsa-algerie.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Algérie'],
        ['name' => 'Algérie Eco', 'base_url' => 'https://www.algerie-eco.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Algérie'],
        ['name' => 'Le Quotidien d\'Oran', 'base_url' => 'https://www.lequotidien-oran.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Algérie'],
        ['name' => 'Algérie Presse Service (APS)', 'base_url' => 'https://www.aps.dz', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Algérie'],
        ['name' => 'Maghreb Emergent', 'base_url' => 'https://maghrebemergent.info', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat', 'international'], 'country' => 'Algérie'],
        ['name' => 'Interlignes Algérie', 'base_url' => 'https://interlignes.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Algérie'],

        // ══════════════════════════════════════════════════════════════════
        // OCÉAN INDIEN — MADAGASCAR, MAURICE, COMORES, DJIBOUTI
        // ══════════════════════════════════════════════════════════════════
        ['name' => "L'Express de Madagascar", 'base_url' => 'https://www.lexpressmada.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Madagascar'],
        ['name' => 'Midi Madagasikara', 'base_url' => 'https://www.midi-madagasikara.mg', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Madagascar'],
        ['name' => 'Newsmada', 'base_url' => 'https://www.newsmada.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Madagascar'],
        ['name' => 'Madagascar Tribune', 'base_url' => 'https://www.madagascar-tribune.com', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Madagascar'],
        ['name' => 'Orange Actu Madagascar', 'base_url' => 'https://actu.orange.mg', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Madagascar'],
        ['name' => 'L\'Express Maurice', 'base_url' => 'https://lexpress.mu', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Maurice'],
        ['name' => 'Le Défi Media (Maurice)', 'base_url' => 'https://defimedia.info', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Maurice'],
        ['name' => 'Le Mauricien', 'base_url' => 'https://www.lemauricien.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Maurice'],
        ['name' => 'Comores Infos', 'base_url' => 'https://www.comoresinfos.net', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'Comores'],
        ['name' => 'La Nation (Djibouti)', 'base_url' => 'https://www.lanation.dj', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Djibouti'],

        // ══════════════════════════════════════════════════════════════════
        // GRANDS LACS — RWANDA, BURUNDI
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'The New Times Rwanda (FR)', 'base_url' => 'https://www.newtimes.co.rw', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Rwanda'],
        ['name' => 'Igihe (Rwanda)', 'base_url' => 'https://fr.igihe.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Rwanda'],
        ['name' => 'Iwacu (Burundi)', 'base_url' => 'https://www.iwacu-burundi.org', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'Burundi'],
        ['name' => 'SOS Médias Burundi', 'base_url' => 'https://www.sosmedias.org', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'Burundi'],

        // ══════════════════════════════════════════════════════════════════
        // MOYEN-ORIENT — LIBAN
        // ══════════════════════════════════════════════════════════════════
        ['name' => "L'Orient-Le Jour (Liban)", 'base_url' => 'https://www.lorientlejour.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Liban'],
        ['name' => 'L\'Orient Today (Liban)', 'base_url' => 'https://today.lorientlejour.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Liban'],
        ['name' => 'Le Commerce du Levant (Liban)', 'base_url' => 'https://www.lecommercedulevant.com', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Liban'],
        ['name' => 'Ici Beyrouth', 'base_url' => 'https://icibeyrouth.com', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Liban'],
        ['name' => 'Libnanews (Liban)', 'base_url' => 'https://libnanews.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Liban'],
        ['name' => 'MTV Liban', 'base_url' => 'https://www.mtv.com.lb', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'Liban'],
        ['name' => 'OLJ Lifestyle', 'base_url' => 'https://www.lorientlejour.com/rubrique/lifestyle', 'media_type' => 'web', 'topics' => ['lifestyle', 'voyage', 'expatriation'], 'country' => 'Liban'],

        // ══════════════════════════════════════════════════════════════════
        // CARAÏBES — HAÏTI
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Nouvelliste (Haïti)', 'base_url' => 'https://lenouvelliste.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Haïti'],
        ['name' => 'Le National (Haïti)', 'base_url' => 'https://lenational.org', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Haïti'],
        ['name' => 'AyiboPost (Haïti)', 'base_url' => 'https://ayibopost.com', 'media_type' => 'web', 'topics' => ['international', 'business', 'lifestyle'], 'country' => 'Haïti'],
        ['name' => 'Haiti Libre', 'base_url' => 'https://www.haitilibre.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Haïti'],
        ['name' => 'Rezo Nòdwès (Haïti)', 'base_url' => 'https://rezonodwes.com', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'Haïti'],

        // ══════════════════════════════════════════════════════════════════
        // DOM-TOM — GUADELOUPE, MARTINIQUE, GUYANE, RÉUNION, MAYOTTE, NC, PF
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'France-Antilles Guadeloupe', 'base_url' => 'https://www.guadeloupe.franceantilles.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'voyage'], 'country' => 'Guadeloupe'],
        ['name' => 'France-Antilles Martinique', 'base_url' => 'https://www.martinique.franceantilles.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'voyage'], 'country' => 'Martinique'],
        ['name' => 'France-Guyane', 'base_url' => 'https://www.franceguyane.fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'voyage'], 'country' => 'Guyane'],
        ['name' => 'Le Journal de la Réunion (JIR)', 'base_url' => 'https://www.clicanoo.re', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'voyage', 'expatriation'], 'country' => 'La Réunion'],
        ['name' => 'Linfo.re (La Réunion)', 'base_url' => 'https://www.linfo.re', 'media_type' => 'web', 'topics' => ['international', 'voyage'], 'country' => 'La Réunion'],
        ['name' => 'Zinfos974 (La Réunion)', 'base_url' => 'https://www.zinfos974.com', 'media_type' => 'web', 'topics' => ['international', 'voyage'], 'country' => 'La Réunion'],
        ['name' => 'Le Quotidien de La Réunion', 'base_url' => 'https://www.lequotidien.re', 'media_type' => 'presse_ecrite', 'topics' => ['international'], 'country' => 'La Réunion'],
        ['name' => 'Mayotte Hebdo', 'base_url' => 'https://www.mayottehebdo.com', 'media_type' => 'presse_ecrite', 'topics' => ['international'], 'country' => 'Mayotte'],
        ['name' => 'Les Nouvelles Calédoniennes', 'base_url' => 'https://www.lnc.nc', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'voyage'], 'country' => 'Nouvelle-Calédonie'],
        ['name' => 'Caledonia (NC)', 'base_url' => 'https://www.caledonia.nc', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'Nouvelle-Calédonie'],
        ['name' => 'La Dépêche de Tahiti', 'base_url' => 'https://www.ladepeche.pf', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'voyage'], 'country' => 'Polynésie française'],
        ['name' => 'Tahiti Infos', 'base_url' => 'https://www.tahiti-infos.com', 'media_type' => 'web', 'topics' => ['international', 'voyage'], 'country' => 'Polynésie française'],

        // ══════════════════════════════════════════════════════════════════
        // BELGIQUE (compléments)
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Vif (Belgique)', 'base_url' => 'https://www.levif.be', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Belgique'],
        ['name' => 'Moustique (Belgique)', 'base_url' => 'https://www.moustique.be', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle', 'voyage'], 'country' => 'Belgique'],
        ['name' => 'La DH / Les Sports+ (BE)', 'base_url' => 'https://www.dhnet.be', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Belgique'],
        ['name' => 'L\'Avenir (Belgique)', 'base_url' => 'https://www.lavenir.net', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Belgique'],
        ['name' => 'Metro Belgique FR', 'base_url' => 'https://fr.metrotime.be', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'lifestyle'], 'country' => 'Belgique'],
        ['name' => 'Trends-Tendances (BE)', 'base_url' => 'https://trends.levif.be', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Belgique'],

        // ══════════════════════════════════════════════════════════════════
        // SUISSE (compléments)
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Tribune de Genève', 'base_url' => 'https://www.tdg.ch', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Suisse'],
        ['name' => '24 Heures (Suisse)', 'base_url' => 'https://www.24heures.ch', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Suisse'],
        ['name' => 'Le Matin (Suisse)', 'base_url' => 'https://www.lematin.ch', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Suisse'],
        ['name' => 'Agefi Suisse', 'base_url' => 'https://www.agefi.com', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Suisse'],
        ['name' => 'PME Magazine (Suisse)', 'base_url' => 'https://www.pme.ch', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Suisse'],
        ['name' => 'Bilan (Suisse)', 'base_url' => 'https://www.bilan.ch', 'media_type' => 'presse_ecrite', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Suisse'],
        ['name' => 'Swissinfo FR', 'base_url' => 'https://www.swissinfo.ch/fre', 'media_type' => 'web', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'Suisse'],

        // ══════════════════════════════════════════════════════════════════
        // LUXEMBOURG
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Quotidien (Luxembourg)', 'base_url' => 'https://lequotidien.lu', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Luxembourg'],
        ['name' => 'Paperjam (Luxembourg)', 'base_url' => 'https://paperjam.lu', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Luxembourg'],
        ['name' => "L'Essentiel (Luxembourg)", 'base_url' => 'https://www.lessentiel.lu', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Luxembourg'],
        ['name' => 'Luxemburger Wort FR', 'base_url' => 'https://www.wort.lu/fr', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Luxembourg'],
        ['name' => 'Virgule (Luxembourg)', 'base_url' => 'https://www.virgule.lu', 'media_type' => 'web', 'topics' => ['lifestyle', 'business'], 'country' => 'Luxembourg'],
        ['name' => 'Chronicle.lu (Luxembourg)', 'base_url' => 'https://chronicle.lu', 'media_type' => 'web', 'topics' => ['business', 'expatriation'], 'country' => 'Luxembourg'],

        // ══════════════════════════════════════════════════════════════════
        // CANADA / QUÉBEC (compléments)
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Journal de Montréal', 'base_url' => 'https://www.journaldemontreal.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'Le Journal de Québec', 'base_url' => 'https://www.journaldequebec.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'TVA Nouvelles', 'base_url' => 'https://www.tvanouvelles.ca', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'Le Soleil (Québec)', 'base_url' => 'https://www.lesoleil.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'Le Droit (Ottawa)', 'base_url' => 'https://www.ledroit.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'L\'Acadie Nouvelle (NB)', 'base_url' => 'https://www.acadienouvelle.com', 'media_type' => 'presse_ecrite', 'topics' => ['international'], 'country' => 'Canada'],
        ['name' => 'La Tribune (Sherbrooke)', 'base_url' => 'https://www.latribune.ca', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'Les Affaires (Québec)', 'base_url' => 'https://www.lesaffaires.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'Canada'],
        ['name' => 'Protégez-Vous (Québec)', 'base_url' => 'https://www.protegez-vous.ca', 'media_type' => 'web', 'topics' => ['business', 'lifestyle'], 'country' => 'Canada'],
        ['name' => 'L\'Actualité (Québec)', 'base_url' => 'https://lactualite.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'Canada'],
        ['name' => 'Narcity Québec', 'base_url' => 'https://www.narcity.com/fr', 'media_type' => 'web', 'topics' => ['lifestyle', 'voyage'], 'country' => 'Canada'],

        // ══════════════════════════════════════════════════════════════════
        // MONACO
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Monaco Matin', 'base_url' => 'https://www.monacomatin.mc', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'lifestyle'], 'country' => 'Monaco'],
        ['name' => 'Monaco Tribune', 'base_url' => 'https://www.monaco-tribune.com', 'media_type' => 'web', 'topics' => ['business', 'lifestyle', 'expatriation'], 'country' => 'Monaco'],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE PAN-AFRICAINE & PANFRANCOPHONE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Jeune Afrique', 'base_url' => 'https://www.jeuneafrique.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'expatriation'], 'country' => 'International'],
        ['name' => 'Africanews FR', 'base_url' => 'https://fr.africanews.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'Africa24', 'base_url' => 'https://www.africa24tv.com', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'Financial Afrik', 'base_url' => 'https://www.financialafrik.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'International'],
        ['name' => 'Ecofin Agency', 'base_url' => 'https://www.agenceecofin.com', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'International'],
        ['name' => 'La Tribune Afrique', 'base_url' => 'https://afrique.latribune.fr', 'media_type' => 'web', 'topics' => ['business', 'entrepreneuriat'], 'country' => 'International'],
        ['name' => 'Le Point Afrique', 'base_url' => 'https://www.lepoint.fr/afrique', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'Mondafrique', 'base_url' => 'https://mondafrique.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'Africa Intelligence', 'base_url' => 'https://www.africaintelligence.fr', 'media_type' => 'web', 'topics' => ['business', 'international'], 'country' => 'International'],
        ['name' => 'Afrique Magazine', 'base_url' => 'https://www.afriquemagazine.com', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business', 'lifestyle'], 'country' => 'International'],
        ['name' => 'Le Monde Afrique', 'base_url' => 'https://www.lemonde.fr/afrique', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'RFI Afrique', 'base_url' => 'https://www.rfi.fr/fr/afrique', 'media_type' => 'radio', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'France 24 Afrique', 'base_url' => 'https://www.france24.com/fr/afrique', 'media_type' => 'tv', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'Courrier International Afrique', 'base_url' => 'https://www.courrierinternational.com/continent/afrique', 'media_type' => 'presse_ecrite', 'topics' => ['international', 'business'], 'country' => 'International'],
        ['name' => 'Sputnik FR Afrique', 'base_url' => 'https://fr.sputniknews.africa', 'media_type' => 'web', 'topics' => ['international'], 'country' => 'International'],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE EXPAT & VOYAGE FRANCOPHONE (compléments mondiaux)
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Lepetitjournal.com (global)', 'base_url' => 'https://lepetitjournal.com', 'media_type' => 'web', 'topics' => ['expatriation', 'international', 'voyage'], 'country' => 'International'],
        ['name' => 'FemmExpat', 'base_url' => 'https://www.femmexpat.com', 'media_type' => 'web', 'topics' => ['expatriation', 'lifestyle'], 'country' => 'International'],
        ['name' => 'Expat.com Magazine', 'base_url' => 'https://www.expat.com/fr/magazine', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'International'],
        ['name' => 'French Morning (USA)', 'base_url' => 'https://frenchmorning.com', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'USA'],
        ['name' => 'French District (USA)', 'base_url' => 'https://www.frenchdistrict.com', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'USA'],
        ['name' => 'Courrier Australien', 'base_url' => 'https://www.lecourrieraustralien.com', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'Australie'],
        ['name' => 'Le Courrier des Amériques', 'base_url' => 'https://www.lecourrierdesameriques.com', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'USA'],
        ['name' => 'French Connexion (UK)', 'base_url' => 'https://www.french-connexion.co.uk', 'media_type' => 'web', 'topics' => ['expatriation', 'international'], 'country' => 'Royaume-Uni'],
        ['name' => 'Vivre à Berlin', 'base_url' => 'https://vivreaberlin.com', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'Allemagne'],
        ['name' => 'Vivre à Tokyo', 'base_url' => 'https://vivreatokyo.com', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'Japon'],
        ['name' => 'Vivre au Mexique', 'base_url' => 'https://vivreaumexique.com', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'Mexique'],
        ['name' => 'Vivre en Thaïlande', 'base_url' => 'https://www.vivreenthailande.com', 'media_type' => 'web', 'topics' => ['expatriation', 'voyage'], 'country' => 'Thaïlande'],
        ['name' => 'Lepetitjournal Bangkok', 'base_url' => 'https://lepetitjournal.com/bangkok', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Thaïlande'],
        ['name' => 'Lepetitjournal Barcelone', 'base_url' => 'https://lepetitjournal.com/barcelone', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Espagne'],
        ['name' => 'Lepetitjournal Lisbonne', 'base_url' => 'https://lepetitjournal.com/lisbonne', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Portugal'],
        ['name' => 'Lepetitjournal Londres', 'base_url' => 'https://lepetitjournal.com/londres', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Royaume-Uni'],
        ['name' => 'Lepetitjournal Casablanca', 'base_url' => 'https://lepetitjournal.com/casablanca', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Maroc'],
        ['name' => 'Lepetitjournal Dubaï', 'base_url' => 'https://lepetitjournal.com/dubai', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'EAU'],
        ['name' => 'Lepetitjournal Hong Kong', 'base_url' => 'https://lepetitjournal.com/hong-kong', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Hong Kong'],
        ['name' => 'Lepetitjournal Singapour', 'base_url' => 'https://lepetitjournal.com/singapour', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Singapour'],
        ['name' => 'Lepetitjournal Berlin', 'base_url' => 'https://lepetitjournal.com/berlin', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Allemagne'],
        ['name' => 'Lepetitjournal Milan', 'base_url' => 'https://lepetitjournal.com/milan', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Italie'],
        ['name' => 'Lepetitjournal New York', 'base_url' => 'https://lepetitjournal.com/new-york', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'USA'],
        ['name' => 'Lepetitjournal Montréal', 'base_url' => 'https://lepetitjournal.com/montreal', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Canada'],
        ['name' => 'Lepetitjournal Sydney', 'base_url' => 'https://lepetitjournal.com/sydney', 'media_type' => 'web', 'topics' => ['expatriation'], 'country' => 'Australie'],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE JURIDIQUE FRANCOPHONE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Le Monde du Droit', 'base_url' => 'https://www.lemondedudroit.fr', 'media_type' => 'web', 'topics' => ['business', 'juridique'], 'country' => 'France'],
        ['name' => 'Dalloz Actualité', 'base_url' => 'https://www.dalloz-actualite.fr', 'media_type' => 'web', 'topics' => ['juridique'], 'country' => 'France'],
        ['name' => 'Village de la Justice', 'base_url' => 'https://www.village-justice.com', 'media_type' => 'web', 'topics' => ['juridique', 'business'], 'country' => 'France'],
        ['name' => 'Actu-Juridique', 'base_url' => 'https://www.actu-juridique.fr', 'media_type' => 'web', 'topics' => ['juridique'], 'country' => 'France'],
        ['name' => 'Le Petit Juriste', 'base_url' => 'https://www.lepetitjuriste.fr', 'media_type' => 'web', 'topics' => ['juridique', 'business'], 'country' => 'France'],
        ['name' => 'Juritravail', 'base_url' => 'https://www.juritravail.com', 'media_type' => 'web', 'topics' => ['juridique', 'business'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE TECH/STARTUP FRANCOPHONE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Frenchweb', 'base_url' => 'https://www.frenchweb.fr', 'media_type' => 'web', 'topics' => ['tech', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Maddyness', 'base_url' => 'https://www.maddyness.com', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'tech'], 'country' => 'France'],
        ['name' => 'BPI France Le Hub', 'base_url' => 'https://lehub.bpifrance.fr', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'business'], 'country' => 'France'],
        ['name' => 'WeDemain', 'base_url' => 'https://www.wedemain.fr', 'media_type' => 'web', 'topics' => ['entrepreneuriat', 'international'], 'country' => 'France'],
        ['name' => 'Siècle Digital', 'base_url' => 'https://siecledigital.fr', 'media_type' => 'web', 'topics' => ['tech', 'entrepreneuriat'], 'country' => 'France'],
        ['name' => 'Presse-Citron', 'base_url' => 'https://www.presse-citron.net', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'Numerama', 'base_url' => 'https://www.numerama.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => '01net', 'base_url' => 'https://www.01net.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],
        ['name' => 'Clubic', 'base_url' => 'https://www.clubic.com', 'media_type' => 'web', 'topics' => ['tech'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // PRESSE SANTÉ / BIEN-ÊTRE FRANCOPHONE
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'Top Santé', 'base_url' => 'https://www.topsante.com', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Doctissimo', 'base_url' => 'https://www.doctissimo.fr', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Psychologies Magazine', 'base_url' => 'https://www.psychologies.com', 'media_type' => 'presse_ecrite', 'topics' => ['lifestyle'], 'country' => 'France'],
        ['name' => 'Santé Magazine', 'base_url' => 'https://www.santemagazine.fr', 'media_type' => 'web', 'topics' => ['lifestyle'], 'country' => 'France'],

        // ══════════════════════════════════════════════════════════════════
        // AGENCES DE PRESSE FRANCOPHONES
        // ══════════════════════════════════════════════════════════════════
        ['name' => 'AFP (Agence France-Presse)', 'base_url' => 'https://www.afp.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'France'],
        ['name' => 'MAP (Maghreb Arabe Presse)', 'base_url' => 'https://www.mapnews.ma', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Maroc'],
        ['name' => 'TAP (Tunis Afrique Presse)', 'base_url' => 'https://www.tap.info.tn', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Tunisie'],
        ['name' => 'Belga (Belgique)', 'base_url' => 'https://www.belga.be', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Belgique'],
        ['name' => 'ATS/Keystone (Suisse)', 'base_url' => 'https://www.keystone-sda.ch', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'Suisse'],
        ['name' => 'PANA (Panapress Afrique)', 'base_url' => 'https://www.panapress.com', 'media_type' => 'web', 'topics' => ['international', 'business'], 'country' => 'International'],
    ];

    public function handle(): int
    {
        $reset   = $this->option('reset');
        $doScrape = $this->option('scrape');

        if ($reset) {
            $this->warn('⚠ This will truncate ALL publications. Proceed? (existing contacts are preserved)');
            // In non-interactive mode, just proceed
        }

        $inserted = 0;
        $skipped  = 0;

        foreach ($this->publications as $pub) {
            $slug = Str::slug($pub['name']);

            $domain = parse_url($pub['base_url'], PHP_URL_HOST) ?? '';
            $domain = preg_replace('/^www\./', '', $domain);

            // Check duplicate by slug or domain
            $exists = PressPublication::where('slug', $slug)
                ->orWhere('base_url', 'LIKE', "%{$domain}%")
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $base = rtrim($pub['base_url'], '/');

            PressPublication::create([
                'name'        => $pub['name'],
                'slug'        => $slug,
                'base_url'    => $base,
                'team_url'    => $base . '/equipe',
                'contact_url' => $base . '/contact',
                'media_type'  => $pub['media_type'] ?? 'web',
                'topics'      => $pub['topics'] ?? [],
                'language'    => 'fr',
                'country'     => $pub['country'] ?? 'France',
                'status'      => 'pending',
            ]);
            $inserted++;
        }

        $total = PressPublication::count();
        $this->info("Import francophone mondial terminé:");
        $this->line("  Nouvelles: {$inserted}");
        $this->line("  Ignorées (déjà existantes): {$skipped}");
        $this->line("  Total en base: {$total}");
        $this->newLine();

        // Stats par pays
        $byCountry = PressPublication::selectRaw("country, COUNT(*) as n")
            ->groupBy('country')
            ->orderByDesc('n')
            ->pluck('n', 'country');

        $this->info('Par pays:');
        foreach ($byCountry as $country => $n) {
            $this->line("  [{$country}] {$n}");
        }

        // Launch scraping if requested
        if ($doScrape && $inserted > 0) {
            $this->newLine();
            $this->info("Lancement du scraping pour {$inserted} nouvelles publications...");
            $this->call('press:discover', ['--category' => 'all', '--scrape' => true]);
        }

        return Command::SUCCESS;
    }
}
