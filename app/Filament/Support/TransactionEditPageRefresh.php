<?php

namespace App\Filament\Support;

use App\Filament\Resources\TransactionResource\Pages\EditTransaction;
use Livewire\Component;

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

    /**
     * Fields refreshed after invoice/bill pivot changes (lighter than a full form sync).
     *
     * @var array<int, string>
     */
    public const DOCUMENTATION_FIELDS = [
        'reference',
        'documentation_status',
        'documentation_category',
        'trx_in_pdf_path',
        'trx_out_pdf_path',
    ];

    /**
     * Defer parent refresh to avoid nested Livewire update errors from relation manager actions.
     */
    public static function refresh(mixed $livewire): void
    {
        if (! $livewire instanceof Component) {
            return;
        }

        if ($livewire instanceof EditTransaction) {
            $livewire->dispatch('refresh-transaction-edit-record');

            return;
        }

        $livewire->dispatch('refresh-transaction-edit-record')->to(EditTransaction::class);
    }
}
