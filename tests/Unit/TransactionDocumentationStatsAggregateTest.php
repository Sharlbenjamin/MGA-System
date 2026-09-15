<?php

namespace Tests\Unit;

use App\Services\TransactionDocumentationStatsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransactionDocumentationStatsAggregateTest extends TestCase
{
    #[Test]
    public function build_breakdown_from_aggregates_computes_totals_and_missing_steps(): void
    {
        $matrix = [
            'client_payment' => [
                'complete' => 5,
                'unlinked' => 2,
                'missing_generated_pdf' => 1,
            ],
            'provider_single' => [
                'complete' => 3,
                'incomplete' => 1,
            ],
        ];

        foreach (TransactionDocumentationStatsService::ALL_CATEGORIES as $category) {
            $matrix[$category] ??= [];
        }

        $breakdown = TransactionDocumentationStatsService::buildBreakdownFromAggregates($matrix, [
            'client_payment' => [
                [
                    'key' => 'transaction_invoice_total_mismatch',
                    'label' => 'Transaction / invoice total mismatch',
                    'count' => 1,
                ],
            ],
        ]);

        $this->assertSame(8, $breakdown['client_payment']['total']);
        $this->assertSame(5, $breakdown['client_payment']['completed']);
        $this->assertSame(3, $breakdown['client_payment']['uncompleted']);
        $this->assertCount(2, $breakdown['client_payment']['missing_steps']);
        $this->assertSame(1, $breakdown['client_payment']['data_issues'][0]['count']);

        $this->assertSame(4, $breakdown['provider_single']['total']);
        $this->assertSame(3, $breakdown['provider_single']['completed']);
        $this->assertSame(1, $breakdown['provider_single']['uncompleted']);
    }

    #[Test]
    public function map_simple_summary_row_includes_net_and_direction_sums(): void
    {
        $summary = TransactionDocumentationStatsService::mapSimpleSummaryRow((object) [
            'all_total' => 5,
            'all_done' => 3,
            'all_unlinked' => 1,
            'all_incomplete' => 1,
            'income_total' => 2,
            'income_done' => 1,
            'income_unlinked' => 1,
            'income_incomplete' => 0,
            'outflow_total' => 3,
            'outflow_done' => 2,
            'outflow_unlinked' => 0,
            'outflow_incomplete' => 1,
            'income_sum' => '1500.50',
            'outflow_sum' => '400.25',
        ]);

        $this->assertSame(5, $summary['all']['total']);
        $this->assertSame(1100.25, $summary['all']['sum']);
        $this->assertSame(1500.50, $summary['income']['sum']);
        $this->assertSame(400.25, $summary['outflow']['sum']);
        $this->assertSame(2, $summary['income']['total']);
        $this->assertSame(3, $summary['outflow']['total']);
    }

    #[Test]
    public function map_simple_summary_row_defaults_empty_row_to_zeros(): void
    {
        $summary = TransactionDocumentationStatsService::mapSimpleSummaryRow(null);

        $this->assertSame(0, $summary['all']['total']);
        $this->assertSame(0.0, $summary['all']['sum']);
        $this->assertSame(0.0, $summary['income']['sum']);
        $this->assertSame(0.0, $summary['outflow']['sum']);
    }
}
