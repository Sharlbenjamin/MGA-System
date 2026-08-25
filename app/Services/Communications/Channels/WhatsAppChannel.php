<?php

namespace App\Services\Communications\Channels;

use App\Enums\CommunicationStatus;
use App\Models\CommunicationLog;
use App\Services\Communications\CommunicationLogService;
use App\Services\Communications\Transports\WhatsAppTransportInterface;

class WhatsAppChannel implements CommunicationChannelInterface
{
    public function __construct(
        protected WhatsAppTransportInterface $transport,
        protected CommunicationLogService $logService,
    ) {}

    public function openConversation(CommunicationLog $log, string $normalizedPhone, string $message): string
    {
        $url = $this->transport->buildOpenUrl($normalizedPhone, $message);

        $this->logService->markOpened($log, [
            'whatsapp_url' => $url,
            'normalized_phone' => $normalizedPhone,
        ]);

        return $url;
    }

    public function markUserSent(CommunicationLog $log): CommunicationLog
    {
        return $this->logService->updateStatus($log, CommunicationStatus::Sent, [
            'user_declared_sent' => true,
            'user_declared_at' => now()->toIso8601String(),
        ]);
    }
}
