<?php

namespace App\Enums;

enum CommunicationChannel: string
{
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case Slack = 'slack';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Email => 'Email',
            self::Slack => 'Slack',
        };
    }
}
