<?php

namespace App\Observers;

use App\Models\ContentContact;
use App\Services\BacklinkEngineWebhookService;
use Illuminate\Support\Facades\Log;

/**
 * Envoie les contacts web scrapés (communautés, partenaires) au Backlink Engine.
 *
 * Le type exact dépend du secteur de la source (media/assurance/education/etc.),
 * on le mappe dynamiquement via le champ `sector`.
 */
class ContentContactObserver
{
    public function saved(ContentContact $contact): void
    {
        if (!$contact->email) {
            return;
        }

        $significantFields = ['email', 'company_url', 'company', 'country'];
        $isNew = $contact->wasRecentlyCreated;
        $hasSignificantChange = $contact->wasChanged($significantFields);

        if (!$isNew && !$hasSignificantChange) {
            return;
        }

        $type = $this->resolveType($contact->sector);

        if (!BacklinkEngineWebhookService::isSyncable($type)) {
            Log::warning('ContentContactObserver: type non-syncable, skip', [
                'sector' => $contact->sector,
                'type'   => $type,
                'id'     => $contact->id,
            ]);
            return;
        }

        $synced = BacklinkEngineWebhookService::sendContactCreated([
            'email'        => $contact->email,
            'name'         => $contact->name,
            'type'         => $type,
            'publication'  => $contact->company,
            'country'      => $contact->country,
            'language'     => $contact->language,
            'source_url'   => $contact->company_url ?? $contact->page_url,
            'source_table' => 'content_contacts',
            'source_id'    => $contact->id,
        ]);

        if ($synced) {
            $contact->updateQuietly(['backlink_synced_at' => now()]);
        }
    }

    /**
     * Mappe le secteur (champ scraper) vers un type reconnu par le Backlink Engine.
     * Les types inconnus tombent sur 'partenaire' (syncable, catch-all B2B).
     */
    /**
     * `public static` pour permettre à ResyncBacklinkEngine de réutiliser le mapping
     * sans recourir à Reflection. Single source of truth pour secteur → type.
     */
    public static function resolveType(?string $sector): string
    {
        $map = [
            'media'       => 'presse',
            'assurance'   => 'assurance',
            'sante'       => 'partenaire',
            'emploi'      => 'emploi',
            'education'   => 'ecole',
            'fiscalite'   => 'partenaire',
            'social'      => 'communaute_expat',
            'immobilier'  => 'immobilier',
            'traduction'  => 'traducteur',
            'voyage'      => 'agence_voyage',
            'banque'      => 'banque_fintech',
        ];

        $key = strtolower(trim($sector ?? ''));

        if ($sector && !isset($map[$key])) {
            Log::info('ContentContact: sector inconnu mappé à partenaire', ['sector' => $sector]);
        }

        return $map[$key] ?? 'partenaire';
    }
}
