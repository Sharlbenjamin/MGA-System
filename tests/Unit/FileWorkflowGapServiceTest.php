<?php

namespace Tests\Unit;

use App\Models\File;
use App\Models\Gop;
use App\Models\Invoice;
use App\Services\FileWorkflowGapService;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class FileWorkflowGapServiceTest extends TestCase
{
    public function test_missing_gop_when_no_gop_records(): void
    {
        $file = $this->makeFileWithRelations(gops: collect());

        $this->assertTrue(FileWorkflowGapService::missingGop($file));
        $this->assertTrue(FileWorkflowGapService::hasAnyGap($file));
    }

    public function test_missing_gop_doc_when_in_gop_has_no_drive_link(): void
    {
        $gop = new Gop(['type' => 'In', 'gop_google_drive_link' => null]);
        $file = $this->makeFileWithRelations(gops: collect([$gop]));

        $this->assertFalse(FileWorkflowGapService::missingGop($file));
        $this->assertTrue(FileWorkflowGapService::missingGopDoc($file));
    }

    public function test_missing_invoice_document_when_invoice_has_no_google_link(): void
    {
        $invoice = new Invoice(['status' => 'Draft', 'invoice_google_link' => null]);
        $file = $this->makeFileWithRelations(invoices: collect([$invoice]));

        $this->assertFalse(FileWorkflowGapService::missingInvoice($file));
        $this->assertTrue(FileWorkflowGapService::missingInvoiceDocument($file));
        $this->assertTrue(FileWorkflowGapService::hasAnyInvoiceGap($file));
    }

    public function test_missing_client_offer_when_only_draft_gop_in_exists(): void
    {
        $gop = new Gop(['type' => 'In', 'status' => Gop::IN_STATUS_DRAFT]);
        $file = $this->makeFileWithRelations(gops: collect([$gop]));

        $this->assertTrue(FileWorkflowGapService::missingClientOffer($file));
    }

    public function test_client_offer_is_present_when_gop_in_is_offered(): void
    {
        $gop = new Gop(['type' => 'In', 'status' => Gop::IN_STATUS_OFFERED]);
        $file = $this->makeFileWithRelations(gops: collect([$gop]));

        $this->assertFalse(FileWorkflowGapService::missingClientOffer($file));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $gops
     * @param  \Illuminate\Support\Collection<int, mixed>  $invoices
     */
    protected function makeFileWithRelations(
        \Illuminate\Support\Collection $gops = new \Illuminate\Support\Collection,
        \Illuminate\Support\Collection $invoices = new \Illuminate\Support\Collection,
        \Illuminate\Support\Collection $bills = new \Illuminate\Support\Collection,
        \Illuminate\Support\Collection $medicalReports = new \Illuminate\Support\Collection,
    ): File {
        $file = new File;
        $file->setRelation('gops', $gops);
        $file->setRelation('invoices', $invoices);
        $file->setRelation('bills', $bills);
        $file->setRelation('medicalReports', $medicalReports);

        return $file;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
    }
}
