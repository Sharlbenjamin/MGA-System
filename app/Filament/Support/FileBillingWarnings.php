<?php

namespace App\Filament\Support;

use App\Models\File;
use App\Services\FileBillingIntegrityService;
use Filament\Notifications\Notification;

class FileBillingWarnings
{
    public static function notifyIfBillChangedOnFile(?File $file, string $action = 'update'): void
    {
        if (! $file || ! FileBillingIntegrityService::shouldWarnForBillChange($file)) {
            return;
        }

        $message = FileBillingIntegrityService::warningForBillChange($file, $action);

        if (blank($message)) {
            return;
        }

        Notification::make()
            ->warning()
            ->title($action === 'create' ? 'Bill added after invoice' : 'Bill changed after invoice')
            ->body($message)
            ->persistent()
            ->send();
    }

    public static function modalDescriptionForFile(?File $file, string $action = 'create'): ?string
    {
        if (! $file || ! FileBillingIntegrityService::shouldWarnForBillChange($file)) {
            return null;
        }

        return FileBillingIntegrityService::warningForBillChange($file, $action);
    }
}
