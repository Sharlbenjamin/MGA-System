<?php

namespace Tests\Unit;

use App\Services\MobileDashboardService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MobileDashboardServiceTest extends TestCase
{
    public function test_comparison_reports_new_data_when_previous_is_zero(): void
    {
        $service = new MobileDashboardService();
        $method = new ReflectionMethod(MobileDashboardService::class, 'comparison');
        $method->setAccessible(true);

        $result = $method->invoke($service, 100.0, 0.0);

        $this->assertSame('up', $result['trend']);
        $this->assertEquals(100, $result['percentage']);
    }

    public function test_normalize_filters_defaults_to_month(): void
    {
        $service = new MobileDashboardService();
        $method = new ReflectionMethod(MobileDashboardService::class, 'normalizeFilters');
        $method->setAccessible(true);

        $filters = $method->invoke($service, []);

        $this->assertSame('Month', $filters['duration']);
        $this->assertNotEmpty($filters['month']);
    }
}
