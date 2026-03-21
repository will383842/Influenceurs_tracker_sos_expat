<?php

namespace App\Enums;

enum Channel: string
{
    case Email = 'email';
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';
    case WhatsApp = 'whatsapp';
    case Phone = 'phone';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case Facebook = 'facebook';
    case X = 'x';
    case Telegram = 'telegram';
    case ContactForm = 'contact_form';
    case Other = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
