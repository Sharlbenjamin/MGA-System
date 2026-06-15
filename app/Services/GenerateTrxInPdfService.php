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
            'invoices.file.patient',
            'invoices.patient',
            'invoices.bankAccount.country',
        ]);

        $client = $transaction->related_type === 'Client'
            ? $transaction->resolveRelated()
            : $transaction->invoices->first()?->patient?->client;

        if (! $client) {
            throw new \RuntimeException('Cannot generate Trx In PDF without a linked client.');
        }

        $client->loadMissing(['financialContact']);

        $pdf = Pdf::loadView('pdf.client_balance', [
            'client' => $client,
            'invoices' => $transaction->invoices,
        ]);

        $directory = "transactions/in/{$transaction->id}";
        Storage::disk('public')->makeDirectory($directory);

        $filename = 'trx_in_'.$transaction->date->format('Y-m-d').'.pdf';
        $path = "{$directory}/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());

        $transaction->trx_in_pdf_path = $path;
        $transaction->saveQuietly();

        $this->documentationService->syncAndRecalculate($transaction);

        return $path;
    }
}
