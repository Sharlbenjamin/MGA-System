<?php

namespace App\Filament\Support;

use App\Filament\Resources\TransactionResource\Pages\EditTransaction;

class TransactionEditPageRefresh
{
    /**
     * @var array<int, string>
     */
    public const FORM_FIELDS = [
        'name',
        'amount',
        'date',
        'notes',
        'type',
        'status',
        'related_type',
        'related_id',
        'reference',
        'documentation_status',
        'documentation_category',
        'bank_charges',
        'charges_covered_by_client',
        'attachment_path',
        'trx_in_pdf_path',
        'trx_out_pdf_path',
    ];

    public static function refresh(mixed $livewire): void
    {
        if ($livewire instanceof EditTransaction) {
            $livewire->refreshRecordOnPage();
        }
    }
}
