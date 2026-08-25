<?php

namespace App\Services\Communications\Transports;

use App\Support\Communications\PhoneNumberNormalizer;

class WhatsAppDeepLinkTransport implements WhatsAppTransportInterface
{
    public function __construct(
        protected PhoneNumberNormalizer $normalizer,
    ) {}

    public function buildOpenUrl(string $normalizedPhone, string $message): string
    {
        return $this->normalizer->buildWhatsAppUrl($normalizedPhone, $message);
    }
}
