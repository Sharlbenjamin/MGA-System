<?php

namespace Tests\Unit;

use App\Filament\Resources\LeadResource\Widgets\ClientLeadsOverviewWidget;
use App\Models\Lead;
use App\Services\LeadOverviewService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class LeadOverviewServiceTest extends TestCase
{
    public function test_desktop_widgets_are_exposed_in_order(): void
    {
        $this->assertSame(
            ['leads', 'needs_action', 'searching', 'interested', 'follow_up'],
            array_column(LeadOverviewService::definitions(), 'key'),
        );
        $this->assertSame(
            ['Leads', 'Needs Action', 'Searching', 'Interested', 'Follow up'],
            array_column(LeadOverviewService::definitions(), 'label'),
        );
    }

    public function test_needs_action_matches_unsent_pipeline_steps(): void
    {
        $this->assertSame(
            ['Introduction', 'Reminder', 'Presentation', 'Price List', 'Contract'],
            LeadOverviewService::ACTION_STATUSES,
        );
    }

    public function test_client_leads_overview_widget_uses_five_columns(): void
    {
        $widget = (new ReflectionClass(ClientLeadsOverviewWidget::class))->newInstanceWithoutConstructor();
        $columns = new ReflectionMethod(ClientLeadsOverviewWidget::class, 'getColumns');
        $columns->setAccessible(true);

        $this->assertSame(5, $columns->invoke($widget));
    }

    public function test_follow_up_countdown_matches_desktop_labels(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28'));

        $this->assertSame('No contact date', Lead::followUpCountdownLabel(null));
        $this->assertSame('Due today', Lead::followUpCountdownLabel('2026-10-21'));
        $this->assertSame('1 day remaining', Lead::followUpCountdownLabel('2026-10-22'));
        $this->assertSame('1 day past', Lead::followUpCountdownLabel('2026-10-20'));

        Carbon::setTestNow();
    }
}
