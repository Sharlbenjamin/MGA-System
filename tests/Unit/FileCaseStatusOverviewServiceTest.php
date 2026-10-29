<?php

namespace Tests\Unit;

use App\Services\FileCaseStatusOverviewService;
use PHPUnit\Framework\TestCase;

class FileCaseStatusOverviewServiceTest extends TestCase
{
    public function test_desktop_file_widgets_are_exposed_in_order(): void
    {
        $this->assertSame(
            ['New', 'Hold', 'Handling', 'Available'],
            array_column(FileCaseStatusOverviewService::definitions(), 'key'),
        );
        $this->assertSame(
            ['New Cases', 'Hold Cases', 'Handling', 'Available Cases'],
            array_column(FileCaseStatusOverviewService::definitions(), 'label'),
        );
        $this->assertSame(
            ['New cases without invoices', 'Cases on hold', 'Needs Action', 'Available cases'],
            array_column(FileCaseStatusOverviewService::definitions(), 'description'),
        );
    }

    public function test_opened_cases_match_the_desktop_default_filter(): void
    {
        $this->assertSame(
            ['New', 'Handling', 'Available', 'Confirmed', 'Hold'],
            FileCaseStatusOverviewService::OPENED_STATUSES,
        );
    }

}
