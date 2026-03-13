<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\File;
use Filament\Resources\Pages\CreateRecord;

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

        return $data;
    }
}
