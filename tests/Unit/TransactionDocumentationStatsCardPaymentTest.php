<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Services\TransactionDocumentationStatsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransactionDocumentationStatsCardPaymentTest extends TestCase
{
    #[Test]
    public function card_expense_is_available_for_expense_not_outflow(): void
    {
        $outflowOptions = TransactionDocumentationStatsService::categoryOptionsFor('Outflow', 'Provider');
        $expenseOptions = TransactionDocumentationStatsService::categoryOptionsFor('Expense', 'Rent');

        $this->assertArrayNotHasKey('card_expense', $outflowOptions);
        $this->assertArrayHasKey('card_expense', $expenseOptions);
        $this->assertArrayHasKey('card_provider', $outflowOptions);
    }

    #[Test]
    public function infer_category_maps_outflow_card_text_to_card_provider(): void
    {
        $transaction = new Transaction([
            'type' => 'Outflow',
            'notes' => 'Compra Tarjeta 4176570171221270',
        ]);
        $transaction->setRelation('bills', collect());
        $transaction->setRelation('invoices', collect());

        $this->assertSame('card_provider', TransactionDocumentationStatsService::inferCategoryKey($transaction));
    }

    #[Test]
    public function infer_category_maps_expense_to_expense_payment(): void
    {
        $transaction = new Transaction([
            'type' => 'Expense',
            'notes' => 'Compra Tarjeta 4176570171221270',
        ]);
        $transaction->setRelation('bills', collect());
        $transaction->setRelation('invoices', collect());

        $this->assertSame('expense_payment', TransactionDocumentationStatsService::inferCategoryKey($transaction));
    }
}
