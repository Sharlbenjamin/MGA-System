<?php

namespace Tests\Unit;

use App\Filament\Resources\ProviderLeadResource\Widgets\ProviderLeadsOverviewWidget;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class ProviderLeadsOverviewWidgetTest extends TestCase
{
    public function test_overview_exposes_exactly_five_stats(): void
    {
        $widget = (new ReflectionClass(ProviderLeadsOverviewWidget::class))->newInstanceWithoutConstructor();

        $columns = new ReflectionMethod(ProviderLeadsOverviewWidget::class, 'getColumns');
        $columns->setAccessible(true);

        $this->assertSame(5, $columns->invoke($widget));
    }

    public function test_action_statuses_match_needs_action_pipeline_steps(): void
    {
        $widget = (new ReflectionClass(ProviderLeadsOverviewWidget::class))->newInstanceWithoutConstructor();
        $property = (new ReflectionClass(ProviderLeadsOverviewWidget::class))->getProperty('actionStatuses');
        $property->setAccessible(true);

        $this->assertSame([
            'Pending information',
            'Step one',
            'Reminder',
            'Discount',
            'Step two',
            'Presentation',
            'Contract',
            'Fake Case',
            'Cancel Case',
        ], $property->getValue($widget));
    }
}
