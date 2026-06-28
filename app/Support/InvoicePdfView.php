<?php

namespace App\Support;

use App\Models\Invoice;
use App\Services\InvoicePresentationService;

class InvoicePdfView
{
    /**
     * @return array{invoice: Invoice, displayLines: \Illuminate\Support\Collection}
     */
    public static function data(Invoice $invoice): array
    {
        $invoice->loadMissing(['file.patient.client', 'items']);

        return [
            'invoice' => $invoice,
            'displayLines' => app(InvoicePresentationService::class)->linesForDisplay($invoice),
        ];
    }
}
