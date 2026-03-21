<?php

namespace App\Enums;

enum PipelineStatus: string
{
    // Core pipeline (merged from both tools)
    case New = 'new';
    case Prospect = 'prospect';
    case Contacted1 = 'contacted1';
    case Contacted2 = 'contacted2';
    case Contacted3 = 'contacted3';
    case Contacted = 'contacted';       // Legacy Tracker
    case Negotiating = 'negotiating';
    case Replied = 'replied';
    case Meeting = 'meeting';
    case Active = 'active';
    case Signed = 'signed';
    case Refused = 'refused';
    case Inactive = 'inactive';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nouveau',
            self::Prospect => 'Prospect',
            self::Contacted1 => '1er contact',
            self::Contacted2 => 'Relance 1',
            self::Contacted3 => 'Relance 2',
            self::Contacted => 'Contacté',
            self::Negotiating => 'Négociation',
            self::Replied => 'Répondu',
            self::Meeting => 'Meeting',
            self::Active => 'Actif',
            self::Signed => 'Signé',
            self::Refused => 'Refusé',
            self::Inactive => 'Inactif',
            self::Lost => 'Perdu',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New, self::Prospect => '#4A5568',
            self::Contacted1, self::Contacted => '#F59E0B',
            self::Contacted2 => '#F97316',
            self::Contacted3 => '#EF4444',
            self::Negotiating => '#D97706',
            self::Replied => '#00D4FF',
            self::Meeting => '#A855F7',
            self::Active, self::Signed => '#10B981',
            self::Refused, self::Lost => '#374151',
            self::Inactive => '#6B7280',
        };
    }

    /**
     * Auto-reminder days when transitioning to this status.
     */
    public function reminderDays(): ?int
    {
        return match ($this) {
            self::Contacted1 => 3,
            self::Contacted2 => 5,
            self::Contacted3 => 7,
            self::Contacted => 5,
            self::Negotiating => 7,
            default => null,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Signed, self::Active, self::Refused, self::Lost, self::Inactive]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
