<?php

namespace App\Enums;

enum InteractionResult: string
{
    case Sent = 'sent';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Replied = 'replied';
    case Refused = 'refused';
    case Registered = 'registered';
    case NoAnswer = 'no_answer';
    case Bounced = 'bounced';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
