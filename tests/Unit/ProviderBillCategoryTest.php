<?php

namespace Tests\Unit;

use App\Services\TransactionDocumentationStatsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProviderBillCategoryTest extends TestCase
{
    #[Test]
    public function two_or_more_bills_resolve_to_provider_bulk(): void
    {
        $this->assertSame('provider_bulk', TransactionDocumentationStatsService::providerBillCategoryForCount(2));
        $this->assertSame('provider_bulk', TransactionDocumentationStatsService::providerBillCategoryForCount(5));
    }

    #[Test]
    public function one_bill_resolves_to_provider_single(): void
    {
        $this->assertSame('provider_single', TransactionDocumentationStatsService::providerBillCategoryForCount(1));
    }

    #[Test]
    public function zero_bills_do_not_resolve_to_a_provider_category(): void
    {
        $this->assertNull(TransactionDocumentationStatsService::providerBillCategoryForCount(0));
    }

    #[Test]
    public function auto_adjust_only_applies_to_provider_bill_categories(): void
    {
        $this->assertTrue(TransactionDocumentationStatsService::shouldAutoAdjustProviderBillCategory(null));
        $this->assertTrue(TransactionDocumentationStatsService::shouldAutoAdjustProviderBillCategory('provider_single'));
        $this->assertTrue(TransactionDocumentationStatsService::shouldAutoAdjustProviderBillCategory('provider_bulk'));
        $this->assertFalse(TransactionDocumentationStatsService::shouldAutoAdjustProviderBillCategory('card_provider'));
    }
}
