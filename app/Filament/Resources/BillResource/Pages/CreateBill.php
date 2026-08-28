<?php

namespace App\Filament\Resources\BillResource\Pages;

use App\Filament\Resources\BillResource;
use App\Filament\Support\FileBillingWarnings;
use App\Models\File;
use Filament\Resources\Pages\CreateRecord;

class CreateBill extends CreateRecord
{
    protected static string $resource = BillResource::class;

    public function mount(): void
    {
        parent::mount();

        $fileId = request()->get('file_id');

        if ($fileId) {
            FileBillingWarnings::notifyIfBillChangedOnFile(File::find($fileId), 'create');
        }
    }

    protected function afterCreate(): void
    {
        FileBillingWarnings::notifyIfBillChangedOnFile($this->record->file, 'create');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
