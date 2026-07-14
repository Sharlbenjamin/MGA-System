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

    public function test_assisted_file_without_accepted_gop_in_is_flagged(): void
    {
        $gop = new Gop(['type' => 'In', 'status' => Gop::IN_STATUS_OFFERED, 'gop_google_drive_link' => 'https://drive.example/gop']);
        $invoice = new Invoice(['status' => 'Sent', 'invoice_google_link' => 'https://drive.example/invoice']);
        $file = $this->makeFileWithRelations(
            gops: collect([$gop]),
            invoices: collect([$invoice]),
            bills: collect([(object) ['id' => 1]]),
            medicalReports: collect([(object) ['id' => 1]]),
        );

        $this->assertTrue(FileWorkflowGapService::missingAcceptedGopIn($file));
        $this->assertTrue(FileWorkflowGapService::hasAnyGap($file));
        $this->assertFalse(FileWorkflowGapService::hasAnyInvoiceGap($file));
        $this->assertContains(
            FileWorkflowGapService::GAP_NO_GOP_ACCEPTED,
            FileWorkflowGapService::describeAssistedGaps($file),
        );
        $this->assertSame(
            'No GOP accepted',
            FileWorkflowGapService::assistedGapLabel(FileWorkflowGapService::GAP_NO_GOP_ACCEPTED),
        );
    }

    public function test_assisted_file_with_accepted_gop_in_is_not_flagged_for_gop(): void
    {
        $gop = new Gop([
            'type' => 'In',
            'status' => Gop::IN_STATUS_ACCEPTED,
            'gop_google_drive_link' => 'https://drive.example/gop',
        ]);
        $file = $this->makeFileWithRelations(
            gops: collect([$gop]),
            bills: collect([(object) ['id' => 1]]),
            medicalReports: collect([(object) ['id' => 1]]),
        );

        $this->assertFalse(FileWorkflowGapService::missingAcceptedGopIn($file));
        $this->assertFalse(FileWorkflowGapService::hasAnyGap($file));
        $this->assertNotContains(
            FileWorkflowGapService::GAP_NO_GOP_ACCEPTED,
            FileWorkflowGapService::describeAssistedGaps($file),
        );
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
