<?php

namespace App\Enums;

enum ContactType: string
{
    // Original Tracker types
    case Influenceur = 'influenceur';
    case GroupAdmin = 'group_admin';
    case Blogger = 'blogger';
    case Partner = 'partner';
    case School = 'school';
    case Association = 'association';

    // New from Mission Control
    case Chatter = 'chatter';
    case Tiktoker = 'tiktoker';
    case Youtuber = 'youtuber';
    case Instagramer = 'instagramer';
    case Backlink = 'backlink';
    case TravelAgency = 'travel_agency';
    case RealEstate = 'real_estate';
    case Translator = 'translator';
    case Insurer = 'insurer';
    case Enterprise = 'enterprise';
    case Press = 'press';
    case Lawyer = 'lawyer';
    case JobBoard = 'job_board';

    public function label(): string
    {
        return match ($this) {
            self::Influenceur => 'Influenceurs',
            self::GroupAdmin => 'Group Admins',
            self::Blogger => 'Blogueurs',
            self::Partner => 'Partenariats',
            self::School => 'Ecoles',
            self::Association => 'Associations',
            self::Chatter => 'Chatters',
            self::Tiktoker => 'TikTokeurs',
            self::Youtuber => 'YouTubeurs',
            self::Instagramer => 'Instagrameurs',
            self::Backlink => 'Backlinks',
            self::TravelAgency => 'Agences voyage',
            self::RealEstate => 'Agents immobiliers',
            self::Translator => 'Traducteurs',
            self::Insurer => 'Assureurs/B2B',
            self::Enterprise => 'Entreprises',
            self::Press => 'Presse',
            self::Lawyer => 'Avocats',
            self::JobBoard => 'Sites emploi',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::School => '🏫',
            self::Chatter => '💬',
            self::Tiktoker => '🎵',
            self::Youtuber => '🎬',
            self::Instagramer => '📸',
            self::Influenceur => '✨',
            self::Blogger => '📰',
            self::Backlink => '🔗',
            self::Association => '🤝',
            self::TravelAgency => '✈️',
            self::RealEstate => '🏠',
            self::Translator => '🌐',
            self::Insurer => '🛡️',
            self::Enterprise => '🏢',
            self::Press => '📺',
            self::Partner => '🏛️',
            self::Lawyer => '⚖️',
            self::JobBoard => '💼',
            self::GroupAdmin => '👥',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::School => '#10B981',
            self::Chatter => '#FF6B6B',
            self::Tiktoker => '#FF0050',
            self::Youtuber => '#FF0000',
            self::Instagramer => '#E1306C',
            self::Influenceur => '#FFD60A',
            self::Blogger => '#A855F7',
            self::Backlink => '#F59E0B',
            self::Association => '#EC4899',
            self::TravelAgency => '#06B6D4',
            self::RealEstate => '#84CC16',
            self::Translator => '#0EA5E9',
            self::Insurer => '#3B82F6',
            self::Enterprise => '#14B8A6',
            self::Press => '#E11D48',
            self::Partner => '#D97706',
            self::Lawyer => '#8B5CF6',
            self::JobBoard => '#78716C',
            self::GroupAdmin => '#F472B6',
        };
    }

    /**
     * All valid values as array for validation rules.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
