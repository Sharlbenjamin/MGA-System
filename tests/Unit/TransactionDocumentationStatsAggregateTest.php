<?php

namespace Tests\Unit;

use App\Services\TransactionDocumentationStatsService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
}
