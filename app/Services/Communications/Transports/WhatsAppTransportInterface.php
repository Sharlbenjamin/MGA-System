<?php

namespace App\Services\Communications\Transports;

interface WhatsAppTransportInterface
{
    public function buildOpenUrl(string $normalizedPhone, string $message): string;
}
