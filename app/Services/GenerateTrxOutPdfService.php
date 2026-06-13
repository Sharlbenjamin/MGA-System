<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerateTrxOutPdfService
{
    public function __construct(
        protected TransactionDocumentationService $documentationService
    ) {}

    public function generate(Transaction $transaction): string
    {
        $transaction->load([
            'bills.file.patient',
            'bills.file.serviceType',
            'bills.branch.city',
            'bills.provider.country',
            'bills.provider.bankAccounts.country',
            'related',
        ]);

        $provider = $this->resolveProvider($transaction);
        $branch = $transaction->bills->first()?->branch
            ?? ($transaction->related_type === 'Branch' ? $transaction->related : null);

        $bankAccount = $provider?->bankAccounts()->first();

        $pdf = Pdf::loadView('pdf.trx_out', [
            'transaction' => $transaction,
            'provider' => $provider,
            'branch' => $branch,
            'bankAccount' => $bankAccount,
            'bills' => $transaction->bills,
        ]);

        $directory = "transactions/out/{$transaction->id}";
        Storage::disk('public')->makeDirectory($directory);

        $filename = 'trx_out_' . $transaction->date->format('Y-m-d') . '.pdf';
        $path = "{$directory}/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());

        $transaction->trx_out_pdf_path = $path;
        $transaction->saveQuietly();

        $this->documentationService->syncAndRecalculate($transaction);

        return $path;
    }

    protected function resolveProvider(Transaction $transaction): ?Provider
    {
        if ($transaction->related_type === 'Provider') {
            return $transaction->related;
        }

        if ($transaction->related_type === 'Branch') {
            return ProviderBranch::find($transaction->related_id)?->provider;
        }

        return $transaction->bills->first()?->provider;
    }
}
