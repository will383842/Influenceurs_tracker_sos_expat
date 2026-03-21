<?php

namespace App\Enums;

enum Platform: string
{
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case LinkedIn = 'linkedin';
    case X = 'x';
    case Facebook = 'facebook';
    case Pinterest = 'pinterest';
    case Podcast = 'podcast';
    case Blog = 'blog';
    case Newsletter = 'newsletter';
    case Website = 'website';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
