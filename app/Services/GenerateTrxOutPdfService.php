<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PdfDecompressor\Normalizer;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;

class GenerateTrxOutPdfService
{
    public function __construct(
        protected TransactionDocumentationService $documentationService
    ) {}

    public function generate(Transaction $transaction): string
    {
        $transaction->load(['bills']);

        $billPaths = $this->resolveMergeableBillPdfPaths($transaction);

        $directory = "transactions/out/{$transaction->id}";
        Storage::disk('public')->makeDirectory($directory);

        $filename = 'trx_out_'.$transaction->date->format('Y-m-d').'.pdf';
        $path = "{$directory}/{$filename}";
        $fullOutputPath = Storage::disk('public')->path($path);

        if ($billPaths === []) {
            Log::warning('Trx Out PDF: no mergeable bill PDFs found', [
                'transaction_id' => $transaction->id,
            ]);

            $transaction->trx_out_pdf_path = null;
            $transaction->saveQuietly();
            $this->documentationService->syncAndRecalculate($transaction);

            throw new \RuntimeException('No mergeable bill PDF files found for this transaction.');
        }

        $pdf = $this->makeFpdi();
        $mergeErrors = [];

        foreach ($billPaths as $billPath) {
            try {
                $this->importBillPages($pdf, $billPath);
            } catch (\Throwable $e) {
                $mergeErrors[] = basename($billPath).': '.$e->getMessage();

                Log::warning('Trx Out PDF: skipped bill file during merge', [
                    'transaction_id' => $transaction->id,
                    'path' => $billPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($pdf->PageNo() === 0) {
            $detail = $mergeErrors === []
                ? 'Unknown merge failure.'
                : implode(' | ', array_slice($mergeErrors, 0, 3));

            throw new \RuntimeException(
                'Could not merge any bill PDF pages for this transaction. '.$detail
            );
        }

        $pdf->Output($fullOutputPath, 'F');

        $transaction->trx_out_pdf_path = $path;
        $transaction->saveQuietly();

        $this->documentationService->syncAndRecalculate($transaction);

        return $path;
    }

    protected function importBillPages(Fpdi $pdf, string $billPath): void
    {
        try {
            $this->appendPagesFromFile($pdf, $billPath);

            return;
        } catch (\Throwable $original) {
            $normalizedPath = $this->normalizePdfForFpdi($billPath, $original);

            if ($normalizedPath === null) {
                throw $original;
            }

            try {
                $this->appendPagesFromFile($pdf, $normalizedPath);
            } finally {
                @unlink($normalizedPath);
            }
        }
    }

    protected function appendPagesFromFile(Fpdi $pdf, string $filePath): void
    {
        $pageCount = $pdf->setSourceFile($filePath);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
        }
    }

    protected function normalizePdfForFpdi(string $sourcePath, \Throwable $original): ?string
    {
        $normalized = $this->normalizeWithPhpDecompressor($sourcePath);

        if ($normalized !== null) {
            Log::info('Trx Out PDF: normalized bill PDF with pure-PHP decompressor', [
                'path' => $sourcePath,
                'original_error' => $original->getMessage(),
            ]);

            return $normalized;
        }

        $normalized = $this->normalizeWithGhostscript($sourcePath);

        if ($normalized !== null) {
            Log::info('Trx Out PDF: normalized bill PDF with Ghostscript', [
                'path' => $sourcePath,
                'original_error' => $original->getMessage(),
            ]);

            return $normalized;
        }

        return null;
    }

    protected function normalizeWithPhpDecompressor(string $sourcePath): ?string
    {
        if (! class_exists(Normalizer::class, true)) {
            return null;
        }

        try {
            $bytes = @file_get_contents($sourcePath);

            if ($bytes === false || $bytes === '') {
                return null;
            }

            if (! Normalizer::isCompressed($bytes)) {
                return null;
            }

            $outputPath = $this->makeTempPdfPath();
            (new Normalizer)->normalizeFile($sourcePath, $outputPath);

            if (! is_file($outputPath) || filesize($outputPath) === 0) {
                @unlink($outputPath);

                return null;
            }

            return $outputPath;
        } catch (\Throwable $e) {
            Log::warning('Trx Out PDF: pure-PHP PDF normalize failed', [
                'path' => $sourcePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function normalizeWithGhostscript(string $sourcePath): ?string
    {
        $binary = $this->findGhostscriptBinary();

        if ($binary === null) {
            return null;
        }

        $outputPath = $this->makeTempPdfPath();

        $process = new Process([
            $binary,
            '-dBATCH',
            '-dNOPAUSE',
            '-dQUIET',
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-sOutputFile='.$outputPath,
            $sourcePath,
        ]);
        $process->setTimeout(120);

        try {
            $process->run();
        } catch (\Throwable $e) {
            @unlink($outputPath);

            Log::warning('Trx Out PDF: Ghostscript normalize threw', [
                'path' => $sourcePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $process->isSuccessful() || ! is_file($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);

            Log::warning('Trx Out PDF: Ghostscript normalize failed', [
                'path' => $sourcePath,
                'exit_code' => $process->getExitCode(),
                'error' => $process->getErrorOutput() ?: $process->getOutput(),
            ]);

            return null;
        }

        return $outputPath;
    }

    protected function findGhostscriptBinary(): ?string
    {
        foreach (['gs', 'ghostscript'] as $binary) {
            $process = Process::fromShellCommandline('command -v '.escapeshellarg($binary));
            $process->run();

            $path = trim($process->getOutput());

            if ($process->isSuccessful() && $path !== '') {
                return $path;
            }
        }

        return null;
    }

    protected function makeTempPdfPath(): string
    {
        return sys_get_temp_dir().'/trx_out_norm_'.uniqid('', true).'.pdf';
    }

    protected function makeFpdi(): Fpdi
    {
        if (! class_exists(Fpdi::class, true)) {
            $this->registerFpdiAutoload();
        }

        if (! class_exists(Fpdi::class, true)) {
            throw new \RuntimeException(
                'PDF merge library is not installed on this server. SSH into public_html and run: composer install --no-dev --optimize-autoloader'
            );
        }

        return new Fpdi;
    }

    protected function registerFpdiAutoload(): void
    {
        $fpdf = base_path('vendor/setasign/fpdf/fpdf.php');
        $fpdi = base_path('vendor/setasign/fpdi/src/autoload.php');

        if (is_file($fpdf)) {
            require_once $fpdf;
        }

        if (is_file($fpdi)) {
            require_once $fpdi;
        }
    }

    /**
     * @return list<string> Absolute paths to local bill PDF files, in link order.
     */
    protected function resolveMergeableBillPdfPaths(Transaction $transaction): array
    {
        $paths = [];

        foreach ($transaction->bills as $bill) {
            if (! $bill->bill_document_path) {
                continue;
            }

            if (! Storage::disk('public')->exists($bill->bill_document_path)) {
                continue;
            }

            $absolutePath = Storage::disk('public')->path($bill->bill_document_path);

            if (! str_ends_with(strtolower($bill->bill_document_path), '.pdf')) {
                continue;
            }

            $paths[] = $absolutePath;
        }

        return $paths;
    }
}
