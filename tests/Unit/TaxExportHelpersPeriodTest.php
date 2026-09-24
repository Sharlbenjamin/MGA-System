<?php

namespace Tests\Unit;

use App\Services\TaxExportHelpers;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TaxExportHelpersPeriodTest extends TestCase
{
    #[Test]
    public function month_dates_cover_the_full_selected_month(): void
    {
        [$start, $end] = TaxExportHelpers::resolveMonthDates(2026, 2);

        $this->assertSame('2026-02-01', $start->toDateString());
        $this->assertSame('2026-02-28', $end->toDateString());
    }

    #[Test]
    public function month_dates_include_the_last_day_of_the_month(): void
    {
        [$start, $end] = TaxExportHelpers::resolveMonthDates(2026, 9);

        $this->assertSame('2026-09-01', $start->toDateString());
        $this->assertSame('2026-09-30', $end->toDateString());
    }
}
