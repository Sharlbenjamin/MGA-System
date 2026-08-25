<?php

namespace Tests\Unit;

use App\Services\Communications\Transports\WhatsAppDeepLinkTransport;
use App\Support\Communications\PhoneNumberNormalizer;
use Tests\TestCase;

class WhatsAppDeepLinkTransportTest extends TestCase
{
    public function test_builds_deep_link_without_plus_prefix(): void
    {
        $transport = new WhatsAppDeepLinkTransport(new PhoneNumberNormalizer('353'));
        $url = $transport->buildOpenUrl('353871234567', 'Case MGA-42147');

        $this->assertSame(
            'https://wa.me/353871234567?text='.rawurlencode('Case MGA-42147'),
            $url,
        );
    }
}
