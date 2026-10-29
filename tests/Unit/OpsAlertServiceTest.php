<?php

namespace Tests\Unit;

use App\Services\OpsAlertService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OpsAlertServiceTest extends TestCase
{
    public function test_case_alerts_cover_created_assisted_and_sent(): void
    {
        $methods = array_map(
            fn (\ReflectionMethod $method) => $method->getName(),
            (new ReflectionClass(OpsAlertService::class))->getMethods(),
        );

        $this->assertContains('fileCreated', $methods);
        $this->assertContains('fileAssisted', $methods);
        $this->assertContains('fileSent', $methods);
        $this->assertContains('invoiceSent', $methods);
    }
}
