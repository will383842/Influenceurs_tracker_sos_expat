<?php

namespace App\Models;

use App\Enums\ContactType;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'contact_type', 'language', 'name', 'subject', 'body',
        'variables', 'is_active', 'step', 'delay_days',
    ];

    protected $casts = [
        'contact_type' => ContactType::class,
        'variables'    => 'array',
        'is_active'    => 'boolean',
        'step'         => 'integer',
        'delay_days'   => 'integer',
    ];

    /**
     * Get the best matching template for a contact type + language.
     * Fallback: language-specific → FR → first available.
     */
    public static function getBest(string $contactType, string $language = 'fr', int $step = 1): ?self
    {
        return self::where('contact_type', $contactType)
                ->where('is_active', true)
                ->where('step', $step)
                ->where('language', $language)
                ->first()
            ?? self::where('contact_type', $contactType)
                ->where('is_active', true)
                ->where('step', $step)
                ->where('language', 'fr')
                ->first();
    }

    /**
     * Render the template with variable substitution.
     */
    public function render(array $data = []): array
    {
        $defaults = [
            'yourName'    => 'Williams',
            'yourCompany' => 'SOS-Expat',
            'yourWebsite' => 'https://sos-expat.com',
            'yourTitle'   => 'Fondateur',
        ];

        $vars = array_merge($defaults, $data);

        $subject = $this->subject;
        $body = $this->body;

        foreach ($vars as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return [
            'subject' => $subject,
            'body'    => $body,
        ];
    }

    /**
     * Get all templates for a sequence (all steps ordered).
     */
    public static function getSequence(string $contactType, string $language = 'fr'): \Illuminate\Support\Collection
    {
        return self::where('contact_type', $contactType)
            ->where('is_active', true)
            ->where('language', $language)
            ->orderBy('step')
            ->get();
    }
}
