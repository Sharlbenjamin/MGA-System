<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\File;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $file = isset($data['file_id']) ? File::find($data['file_id']) : null;
        $serviceDate = $file?->service_date?->format('Y-m-d');

        // Enforce invoice date during creation:
        // service date if available, otherwise today's date.
        $data['invoice_date'] = $serviceDate ?? now()->format('Y-m-d');

        if (($data['status'] ?? null) === 'Paid') {
            throw ValidationException::withMessages([
                'status' => 'This invoice must be linked to a transaction before it can be marked as Paid.',
            ]);
        }

        return $data;
    }
}
