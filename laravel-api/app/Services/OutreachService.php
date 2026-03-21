<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Models\Influenceur;

/**
 * Handles email outreach: template rendering, message generation, tracking.
 */
class OutreachService
{
    /**
     * Generate outreach message for a given influenceur.
     */
    public function generateMessage(Influenceur $influenceur, int $step = 1): ?array
    {
        $contactType = $influenceur->contact_type?->value ?? $influenceur->contact_type;
        $language = $influenceur->language ?? 'fr';

        $template = EmailTemplate::getBest($contactType, $language, $step);
        if (!$template) return null;

        $variables = [
            'contactName'    => $influenceur->name,
            'contactCompany' => $influenceur->company ?? $influenceur->name,
            'contactCountry' => $influenceur->country ?? '',
            'contactEmail'   => $influenceur->email ?? '',
            'contactUrl'     => $influenceur->profile_url ?? $influenceur->website_url ?? '',
        ];

        return array_merge(
            $template->render($variables),
            [
                'template_id'   => $template->id,
                'template_name' => $template->name,
                'step'          => $step,
            ]
        );
    }

    /**
     * Generate messages for a batch of influenceurs.
     */
    public function generateBatch(array $influenceurIds, int $step = 1): array
    {
        $results = [];

        $influenceurs = Influenceur::whereIn('id', $influenceurIds)->get();

        foreach ($influenceurs as $inf) {
            $message = $this->generateMessage($inf, $step);
            if ($message) {
                $results[] = array_merge($message, [
                    'influenceur_id' => $inf->id,
                    'influenceur_name' => $inf->name,
                    'email' => $inf->email,
                ]);
            }
        }

        return $results;
    }

    /**
     * Log an outreach interaction.
     */
    public function logOutreach(
        Influenceur $influenceur,
        int $userId,
        string $channel,
        string $subject,
        string $message,
        ?string $templateUsed = null
    ): Contact {
        $contact = Contact::create([
            'influenceur_id' => $influenceur->id,
            'user_id'        => $userId,
            'date'           => now()->toDateString(),
            'channel'        => $channel,
            'direction'      => 'outbound',
            'result'         => 'sent',
            'subject'        => $subject,
            'message'        => $message,
            'template_used'  => $templateUsed,
        ]);

        // Update influenceur last contact
        $influenceur->update(['last_contact_at' => now()]);

        return $contact;
    }

    /**
     * Get available templates for a contact type, grouped by language.
     */
    public function getTemplatesFor(string $contactType): array
    {
        return EmailTemplate::where('contact_type', $contactType)
            ->where('is_active', true)
            ->orderBy('language')
            ->orderBy('step')
            ->get()
            ->groupBy('language')
            ->toArray();
    }
}
