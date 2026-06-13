<?php

namespace App\Services;

use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerateTrxInPdfService
{
    public function __construct(
        protected TransactionDocumentationService $documentationService
    ) {}

    public function generate(Transaction $transaction): string
    {
        $transaction->load([
            'invoices.file.patient.client',
            'invoices.bankAccount.country',
            'related',
        ]);

        $client = $transaction->related_type === 'Client'
            ? $transaction->related
            : $transaction->invoices->first()?->patient?->client;

        $pdf = Pdf::loadView('pdf.trx_in', [
            'transaction' => $transaction,
            'client' => $client,
            'invoices' => $transaction->invoices,
        ]);

        $directory = "transactions/in/{$transaction->id}";
        Storage::disk('public')->makeDirectory($directory);

        $filename = 'trx_in_' . $transaction->date->format('Y-m-d') . '.pdf';
        $path = "{$directory}/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());

        $transaction->trx_in_pdf_path = $path;
        $transaction->saveQuietly();

        $this->documentationService->syncAndRecalculate($transaction);

        return $path;
    }
}
