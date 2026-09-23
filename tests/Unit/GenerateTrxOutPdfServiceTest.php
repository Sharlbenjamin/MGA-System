<?php

namespace Tests\Unit;

use App\Services\GenerateTrxOutPdfService;
use App\Services\PdfFpdiCompatibilityService;
use App\Services\TransactionDocumentationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Fpdi;

class GenerateTrxOutPdfServiceTest extends TestCase
{
    #[Test]
    public function it_merges_pages_from_a_simple_fpdf_document(): void
    {
        $source = $this->makeSimplePdf();

        $service = $this->makeService();
        $pdf = new Fpdi;

        $method = new \ReflectionMethod($service, 'appendPagesFromFile');
        $method->setAccessible(true);
        $method->invoke($service, $pdf, $source);

        $this->assertSame(1, $pdf->PageNo());

        @unlink($source);
    }

    #[Test]
    public function it_rejects_unreadable_bill_pdfs_with_a_clear_error(): void
    {
        $brokenPath = sys_get_temp_dir().'/trx_out_broken_'.uniqid('', true).'.pdf';
        file_put_contents($brokenPath, '%PDF-1.4 broken');

        $service = $this->makeService();
        $pdf = new Fpdi;

        $method = new \ReflectionMethod($service, 'importBillPages');
        $method->setAccessible(true);

        try {
            $method->invoke($service, $pdf, $brokenPath);
            $this->fail('Expected import to fail for a broken PDF.');
        } catch (\Throwable $e) {
            $this->assertNotSame('', $e->getMessage());
            $this->assertSame(0, $pdf->PageNo());
        } finally {
            @unlink($brokenPath);
        }
    }

    #[Test]
    public function compatibility_service_writes_temp_files_under_storage(): void
    {
        $service = new PdfFpdiCompatibilityService;
        $path = $service->makeTempPdfPath();

        $this->assertStringContainsString('/storage/app/tmp/pdf-fpdi/', $path);
        $this->assertDirectoryExists(dirname($path));
    }

    #[Test]
    public function it_normalizes_compressed_pdfs_for_fpdi(): void
    {
        $source = dirname(__DIR__, 2).'/third-party/php-pdf-decompressor/fixtures/compressed.pdf';
        $this->assertFileExists($source);

        $result = (new PdfFpdiCompatibilityService)->normalizeForFpdi($source);

        $this->assertNull($result['error'], $result['error'] ?? '');
        $this->assertNotNull($result['path']);
        $this->assertFileExists($result['path']);

        $pdf = new Fpdi;
        $pages = $pdf->setSourceFile($result['path']);
        $this->assertGreaterThanOrEqual(1, $pages);

        @unlink($result['path']);
    }

    private function makeService(): GenerateTrxOutPdfService
    {
        return new GenerateTrxOutPdfService(
            $this->createMock(TransactionDocumentationService::class),
            new PdfFpdiCompatibilityService,
        );
    }

    private function makeSimplePdf(): string
    {
        $path = sys_get_temp_dir().'/trx_out_simple_'.uniqid('', true).'.pdf';

        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(40, 10, 'Trx Out Test');
        $pdf->Output('F', $path);

        return $path;
    }
}
