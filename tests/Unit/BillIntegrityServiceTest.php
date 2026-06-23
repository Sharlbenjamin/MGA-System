<?php

namespace Tests\Unit;

use App\Models\Bill;
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
}
