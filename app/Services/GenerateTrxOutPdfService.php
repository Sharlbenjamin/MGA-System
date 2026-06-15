<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

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

        $pdf = new Fpdi;

        foreach ($billPaths as $billPath) {
            try {
                $pageCount = $pdf->setSourceFile($billPath);

                for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                    $templateId = $pdf->importPage($pageNumber);
                    $size = $pdf->getTemplateSize($templateId);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            } catch (\Throwable $e) {
                Log::warning('Trx Out PDF: skipped bill file during merge', [
                    'transaction_id' => $transaction->id,
                    'path' => $billPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($pdf->PageNo() === 0) {
            throw new \RuntimeException('Could not merge any bill PDF pages for this transaction.');
        }

        $pdf->Output($fullOutputPath, 'F');

        $transaction->trx_out_pdf_path = $path;
        $transaction->saveQuietly();

        $this->documentationService->syncAndRecalculate($transaction);

        return $path;
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
