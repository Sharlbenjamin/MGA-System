<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Collection;

class InvoicePresentationService
{
    public function __construct(
        private readonly InvoiceFileFeeService $fileFeeService,
    ) {}

    /**
     * Lines to render on PDF / email preview (may merge for combined template).
     *
     * @return Collection<int, array{description: string, amount: float}>
     */
    public function linesForDisplay(Invoice $invoice): Collection
    {
        $invoice->loadMissing(['items', 'file.patient.client']);

        $client = $invoice->file?->patient?->client;

        if (! $client?->usesCombinedInvoiceTemplate()) {
            return $invoice->items->map(fn (InvoiceItem $item) => [
                'description' => $item->description,
                'amount' => (float) $item->amount,
            ]);
        }

        $billItems = $this->fileFeeService->billRelatedItems($invoice);
        $fileFeeItems = $invoice->items->filter(fn (InvoiceItem $item) => $item->isFileFeeItem());

        $billTotal = round((float) $billItems->sum(fn (InvoiceItem $item) => (float) $item->amount), 2);
        $fileFeeTotal = round((float) $fileFeeItems->sum(fn (InvoiceItem $item) => (float) $item->amount), 2);
        $combinedTotal = round($billTotal + $fileFeeTotal, 2);

        if ($combinedTotal <= 0) {
            return collect();
        }

        $serviceDate = ($invoice->file?->service_date ?? now())->format('d/m/Y');
        $baseDescription = trim((string) config('invoice.combined_line_description', 'Medical assistance services'));
        $description = $baseDescription;

        if (! preg_match('/\b\d{2}\/\d{2}\/\d{4}\b/', $description)) {
            $description .= " on {$serviceDate}";
        }

        return collect([
            [
                'description' => $description,
                'amount' => $combinedTotal,
            ],
        ]);
    }

    public function resolveClient(Invoice $invoice): ?Client
    {
        $invoice->loadMissing('file.patient.client');

        return $invoice->file?->patient?->client;
    }
}
