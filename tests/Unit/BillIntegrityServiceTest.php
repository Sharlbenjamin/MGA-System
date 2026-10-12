<?php

namespace Tests\Unit;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Comment;
use App\Models\File;
use App\Services\BillIntegrityService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BillIntegrityServiceTest extends TestCase
{
    #[Test]
    public function missing_document_without_transaction_with_comment_scope_requires_all_three_conditions(): void
    {
        $billWithCommentNoTrx = new Bill(['bill_google_link' => null, 'transaction_id' => null]);
        $billWithCommentNoTrx->setRelation('file', tap(new File, function (File $file): void {
            $file->setRelation('comments', collect([
                new Comment(['content' => 'Paid via comment on case']),
            ]));
        }));
        $billWithCommentNoTrx->setRelation('transactions', collect());

        $billWithTrx = new Bill(['bill_google_link' => null, 'transaction_id' => 1]);
        $billWithTrx->setRelation('file', $billWithCommentNoTrx->file);
        $billWithTrx->setRelation('transactions', collect());

        $billWithDoc = new Bill(['bill_google_link' => 'https://drive.google.com/x', 'transaction_id' => null]);
        $billWithDoc->setRelation('file', $billWithCommentNoTrx->file);
        $billWithDoc->setRelation('transactions', collect());

        $this->assertTrue(BillIntegrityService::hasTransactionLink($billWithTrx));
        $this->assertFalse(BillIntegrityService::hasTransactionLink($billWithCommentNoTrx));
        $this->assertFalse(BillIntegrityService::hasTransactionLink($billWithDoc));
    }

    #[Test]
    public function a_complete_bill_is_not_listed_without_documents(): void
    {
        $bill = $this->billWith(link: 'https://drive.google.com/x', providerId: 5, items: 1);

        $this->assertFalse(BillIntegrityService::belongsOnWithoutDocumentsList($bill));
        $this->assertSame([], BillIntegrityService::withoutDocumentsGaps($bill));
    }

    #[Test]
    public function a_bill_with_no_items_is_listed_even_when_it_has_a_document_and_provider(): void
    {
        $bill = $this->billWith(link: 'https://drive.google.com/x', providerId: 5, items: 0);

        $this->assertTrue(BillIntegrityService::missingItems($bill));
        $this->assertTrue(BillIntegrityService::belongsOnWithoutDocumentsList($bill));
        $this->assertSame(
            [BillIntegrityService::GAP_NO_ITEMS],
            BillIntegrityService::withoutDocumentsGaps($bill),
        );
        $this->assertSame('No bill items', BillIntegrityService::withoutDocumentsGapLabel(BillIntegrityService::GAP_NO_ITEMS));
    }

    #[Test]
    public function a_bill_with_no_provider_is_listed_even_when_it_has_a_document_and_items(): void
    {
        $bill = $this->billWith(link: 'https://drive.google.com/x', providerId: null, items: 1);

        $this->assertTrue(BillIntegrityService::missingProvider($bill));
        $this->assertTrue(BillIntegrityService::belongsOnWithoutDocumentsList($bill));
        $this->assertSame(
            [BillIntegrityService::GAP_NO_PROVIDER],
            BillIntegrityService::withoutDocumentsGaps($bill),
        );
        $this->assertSame('No provider', BillIntegrityService::withoutDocumentsGapLabel(BillIntegrityService::GAP_NO_PROVIDER));
    }

    #[Test]
    public function a_bill_missing_its_document_stays_on_the_list(): void
    {
        $bill = $this->billWith(link: null, providerId: 5, items: 2);

        $this->assertTrue(BillIntegrityService::missingDocument($bill));
        $this->assertSame(
            [BillIntegrityService::GAP_MISSING_DOCUMENT],
            BillIntegrityService::withoutDocumentsGaps($bill),
        );
    }

    #[Test]
    public function items_count_attribute_is_used_when_items_are_not_loaded(): void
    {
        $bill = new Bill([
            'bill_google_link' => 'https://drive.google.com/x',
            'provider_id' => 5,
        ]);
        $bill->setRawAttributes(array_merge($bill->getAttributes(), [
            'items_count' => 0,
        ]), true);

        $this->assertTrue(BillIntegrityService::missingItems($bill));
    }

    private function billWith(?string $link, ?int $providerId, int $items): Bill
    {
        $bill = new Bill([
            'bill_google_link' => $link,
            'provider_id' => $providerId,
        ]);
        $bill->setRelation('items', collect(array_fill(0, $items, new BillItem(['description' => 'Consult', 'amount' => 10]))));

        return $bill;
    }
}
