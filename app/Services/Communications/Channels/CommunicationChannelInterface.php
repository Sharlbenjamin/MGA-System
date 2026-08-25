<?php

namespace App\Services\Communications\Channels;

use App\Models\CommunicationLog;

interface CommunicationChannelInterface
{
    public function openConversation(CommunicationLog $log, string $normalizedPhone, string $message): string;

    public function markUserSent(CommunicationLog $log): CommunicationLog;
}
