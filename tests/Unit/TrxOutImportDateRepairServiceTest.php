<?php

namespace Tests\Unit;

use App\Services\TrxOutImportDateRepairService;
use App\Services\TrxOutStatementNormalizer;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TrxOutImportDateRepairServiceTest extends TestCase
{
    #[Test]
    public function it_flags_date_mismatches_and_passes_when_amounts_align(): void
    {
        $service = new TrxOutImportDateRepairService;

        $transactions = collect([
            (object) ['id' => 10, 'amount' => 65.0, 'date' => Carbon::parse('2025-12-05'), 'name' => 'Refund'],
            (object) ['id' => 11, 'amount' => 250.0, 'date' => Carbon::parse('2025-05-21'), 'name' => 'Clinic'],
        ]);

        $revisionRows = collect([
            ['_index' => 2, 'transaction_date' => '2025-05-12', 'amount' => 65.0],
            ['_index' => 3, 'transaction_date' => '2025-05-21', 'amount' => 250.0],
        ]);

        $report = $service->buildPairingReport($transactions, $revisionRows);

        $this->assertTrue($report['can_apply']);
        $this->assertSame(1, $report['already_correct']);
        $this->assertCount(1, $report['to_fix']);
        $this->assertSame(10, $report['to_fix'][0]['transaction_id']);
        $this->assertSame('2025-12-05', $report['to_fix'][0]['old_date']);
        $this->assertSame('2025-05-12', $report['to_fix'][0]['new_date']);
    }

    #[Test]
    public function it_aborts_when_row_counts_or_amounts_do_not_match(): void
    {
        $service = new TrxOutImportDateRepairService;

        $transactions = collect([
            (object) ['id' => 10, 'amount' => 65.0, 'date' => Carbon::parse('2025-12-05'), 'name' => 'Refund'],
        ]);

        $revisionRows = collect([
            ['_index' => 2, 'transaction_date' => '2025-05-12', 'amount' => 70.0],
            ['_index' => 3, 'transaction_date' => '2025-05-21', 'amount' => 250.0],
        ]);

        $report = $service->buildPairingReport($transactions, $revisionRows);

        $this->assertFalse($report['can_apply']);
        $this->assertNotEmpty($report['anomalies']);
    }
}

class TrxOutRevisionFormatTest extends TestCase
{
    #[Test]
    public function it_parses_revision_file_date_column(): void
    {
        $path = dirname(__DIR__, 2).'/TRX Out Revision.xlsx';

        if (! is_file($path)) {
            $this->markTestSkipped('TRX Out Revision.xlsx not present in project root.');
        }

        $normalizer = new TrxOutStatementNormalizer;
        $rows = $normalizer->parseFile($path);

        $this->assertSame(432, $rows->count());
        $this->assertSame('2025-05-12', $rows->first()['transaction_date'] ?? null);
        $this->assertSame('2025-06-11', $rows->get(6)['transaction_date'] ?? null);
    }

    #[Test]
    public function it_prefers_dd_mm_slash_format_over_excel_serial(): void
    {
        $normalizer = new TrxOutStatementNormalizer;
        $method = new \ReflectionMethod($normalizer, 'resolveDate');
        $method->setAccessible(true);

        $this->assertSame('2025-05-12', $method->invoke($normalizer, '12/05/2025'));
    }

    #[Test]
    public function it_maps_date_header_when_value_date_column_is_absent(): void
    {
        $normalizer = new TrxOutStatementNormalizer;
        $method = new \ReflectionMethod($normalizer, 'mapHeaders');
        $method->setAccessible(true);

        $revisionMap = $method->invoke($normalizer, ['Date', 'Item:', 'Amount']);
        $originalMap = $method->invoke($normalizer, ['Value date', 'Date', 'Item:', 'Amount']);

        $this->assertContains('transaction_date', $revisionMap);
        $this->assertContains('_skip_date_formula', $originalMap);
    }
}
