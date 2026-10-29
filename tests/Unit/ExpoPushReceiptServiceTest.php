<?php

namespace Tests\Unit;

use App\Services\ExpoPushReceiptService;
use PHPUnit\Framework\TestCase;

class ExpoPushReceiptServiceTest extends TestCase
{
    public function test_backoff_schedule_has_increasing_delays(): void
    {
        $service = new ExpoPushReceiptService;
        $ref = new \ReflectionClass($service);
        $prop = $ref->getProperty('backoffSeconds');
        $prop->setAccessible(true);
        $backoff = $prop->getValue($service);

        $this->assertNotEmpty($backoff);
        for ($i = 1, $iMax = count($backoff); $i < $iMax; $i++) {
            $this->assertGreaterThan($backoff[$i - 1], $backoff[$i]);
        }
    }
}
