<?php

namespace App\Services;

use App\Models\Client;
use App\Models\File;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceBuilderService
{
    public function __construct(
        private readonly InvoiceFileFeeService $fileFeeService,
    ) {}

    /**
     * Build a complete invoice with bill items and auto file fee from a file.
     *
     * @throws ValidationException
     */
    public function buildFromFile(File $file, ?Client $client = null): Invoice
    {
        $file->loadMissing(['patient.client', 'bills.items']);

        $client ??= $file->patient?->client;

        $existingDraft = $file->invoices()
            ->where('status', 'Draft')
            ->withCount('items')
            ->orderByDesc('created_at')
            ->first();

        if ($existingDraft && $existingDraft->items_count > 0) {
            throw ValidationException::withMessages([
                'invoice' => 'This file already has a draft invoice with items. Edit it or delete it before generating a new one.',
            ]);
        }

        return DB::transaction(function () use ($file, $client, $existingDraft) {
            $invoice = $existingDraft ?? $this->createInvoiceShell($file);

            if ($existingDraft) {
                $invoice->items()->delete();
            }

            $serviceDate = ($file->service_date ?? now())->format('d/m/Y');

            foreach ($file->bills as $bill) {
                foreach ($bill->items as $billItem) {
                    $description = trim((string) $billItem->description);
                    if ($description === '') {
                        continue;
                    }

                    if (! preg_match('/\b\d{2}\/\d{2}\/\d{4}\b/', $description)) {
                        $description = trim($description) . " on {$serviceDate}";
                    }

                    InvoiceItem::withoutEvents(function () use ($invoice, $description, $billItem) {
                        $invoice->items()->create([
                            'description' => $description,
                            'amount' => (float) $billItem->amount,
                            'discount' => 0,
                            'tax' => 0,
                            'item_type' => InvoiceItem::TYPE_BILL,
                        ]);
                    });
                }
            }

            $invoice->refresh();
            $this->fileFeeService->syncForInvoice($invoice);
            $invoice->refresh();
            $invoice->calculateTotal();

            return $invoice->fresh(['items', 'file.patient.client']);
        });
    }

    protected function createInvoiceShell(File $file): Invoice
    {
        $serviceDate = $file->service_date?->format('Y-m-d');

        $invoice = new Invoice([
            'file_id' => $file->id,
            'patient_id' => $file->patient_id,
            'status' => 'Draft',
            'invoice_date' => $serviceDate ?? now()->format('Y-m-d'),
        ]);

        $invoice->save();

        return $invoice;
    }
}
