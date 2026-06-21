<?php

namespace App\Filament\Support;

use App\Filament\Resources\TransactionResource;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\TransactionDocumentationService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class TransactionInvoiceLinkForm
{
    /**
     * @param  array<int, int>  $excludeIds
     * @return array<int, string>
     */
    public static function invoiceOptionsForTransaction(Transaction $transaction, array $excludeIds = []): array
    {
        if ($transaction->related_type !== 'Client' || ! $transaction->related_id) {
            return [];
        }

        $options = TransactionResource::availableInvoiceOptions(
            (int) $transaction->related_id,
            $transaction->id,
        );

        foreach ($excludeIds as $excludeId) {
            unset($options[$excludeId]);
        }

        return $options;
    }

    public static function defaultPaidAmountForInvoice(Invoice $invoice, Transaction $transaction): float
    {
        $remaining = $invoice->remainingBalance();

        return round(min($remaining, (float) $invoice->total_amount), 2);
    }

    public static function clampPaidAmountForInvoiceOnTransaction(
        Invoice $invoice,
        Transaction $transaction,
        float $amount,
        ?float $currentPivotOnTransaction = null,
    ): float {
        $paid = round(max(0, $amount), 2);
        $currentPivot = $currentPivotOnTransaction ?? (float) ($transaction->invoices()
            ->where('invoices.id', $invoice->id)
            ->first()?->pivot?->amount_paid ?? 0);
        $totalPaid = (float) DB::table('invoice_transaction')
            ->where('invoice_id', $invoice->id)
            ->sum('amount_paid');
        $maxAllowed = round((float) $invoice->total_amount - ($totalPaid - $currentPivot), 2);

        return min($paid, max(0, $maxAllowed));
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function attachFormSchema(Transaction $transaction, array $excludeIds = []): array
    {
        return [
            Forms\Components\Select::make('invoice_id')
                ->label('Invoice')
                ->options(fn (): array => self::invoiceOptionsForTransaction($transaction, $excludeIds))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function (?string $state, callable $set) use ($transaction): void {
                    if (! $state) {
                        return;
                    }

                    $invoice = Invoice::find((int) $state);

                    if ($invoice) {
                        $set('amount_paid', self::defaultPaidAmountForInvoice($invoice, $transaction));
                    }
                }),
            Forms\Components\TextInput::make('amount_paid')
                ->label('Paid amount')
                ->numeric()
                ->inputMode('decimal')
                ->step('0.01')
                ->prefix('€')
                ->required()
                ->default(0),
            Forms\Components\Placeholder::make('invoice_total_display')
                ->label('Invoice total')
                ->content(function (Get $get): string {
                    $invoice = Invoice::find((int) $get('invoice_id'));

                    if (! $invoice) {
                        return '—';
                    }

                    return '€'.number_format((float) $invoice->total_amount, 2);
                }),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function editPaidAmountSchema(Invoice $invoice): array
    {
        return [
            Forms\Components\Placeholder::make('invoice_name')
                ->label('Invoice')
                ->content($invoice->name),
            Forms\Components\Placeholder::make('invoice_total')
                ->label('Invoice total')
                ->content('€'.number_format((float) $invoice->total_amount, 2)),
            Forms\Components\TextInput::make('amount_paid')
                ->label('Paid amount on this transaction')
                ->numeric()
                ->inputMode('decimal')
                ->step('0.01')
                ->prefix('€')
                ->required(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function createRepeaterSchema(): array
    {
        return [
            Forms\Components\Select::make('invoice_id')
                ->label('Invoice')
                ->options(function (Get $get, $livewire): array {
                    $clientId = (int) ($get('../../related_id') ?? 0);

                    if (! $clientId) {
                        return [];
                    }

                    $selectedElsewhere = collect($get('../../invoice_links') ?? [])
                        ->pluck('invoice_id')
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    $currentId = (int) ($get('invoice_id') ?? 0);
                    $exclude = array_values(array_filter(
                        $selectedElsewhere,
                        fn (int $id): bool => $id !== $currentId,
                    ));

                    $options = TransactionResource::availableInvoiceOptions($clientId, null);

                    foreach ($exclude as $excludeId) {
                        unset($options[$excludeId]);
                    }

                    return $options;
                })
                ->searchable()
                ->required()
                ->live()
                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                ->afterStateUpdated(function (?string $state, callable $set, Get $get): void {
                    if (! $state) {
                        return;
                    }

                    $invoice = Invoice::find((int) $state);

                    if ($invoice) {
                        $set('amount_paid', min($invoice->remainingBalance(), (float) $invoice->total_amount));
                    }
                }),
            Forms\Components\TextInput::make('amount_paid')
                ->label('Paid amount')
                ->numeric()
                ->inputMode('decimal')
                ->step('0.01')
                ->prefix('€')
                ->required()
                ->default(0),
            Forms\Components\Placeholder::make('invoice_total_display')
                ->label('Invoice total')
                ->content(function (Get $get): string {
                    $invoice = Invoice::find((int) $get('invoice_id'));

                    if (! $invoice) {
                        return '—';
                    }

                    return '€'.number_format((float) $invoice->total_amount, 2);
                }),
        ];
    }

    public static function attachInvoice(Transaction $transaction, int $invoiceId, float $amountPaid, bool $notify = true): void
    {
        $invoice = Invoice::find($invoiceId);

        if (! $invoice) {
            if ($notify) {
                Notification::make()->danger()->title('Invoice not found')->send();
            }

            return;
        }

        $existingPivot = $transaction->invoices()->where('invoices.id', $invoiceId)->exists()
            ? (float) ($transaction->invoices()->where('invoices.id', $invoiceId)->first()?->pivot?->amount_paid ?? 0)
            : 0;

        $amountPaid = self::clampPaidAmountForInvoiceOnTransaction(
            $invoice,
            $transaction,
            $amountPaid,
            $existingPivot,
        );

        if ($transaction->invoices()->where('invoices.id', $invoiceId)->exists()) {
            $transaction->updateInvoicePaidAmount($invoice, $amountPaid);
        } else {
            $transaction->invoices()->attach($invoiceId, ['amount_paid' => $amountPaid]);
            $invoice->recalculatePaidAmountFromTransactions();
        }

        app(TransactionDocumentationService::class)->syncAndRecalculate($transaction->fresh());

        if ($notify) {
            Notification::make()
                ->success()
                ->title('Invoice added')
                ->body("{$invoice->name} linked with €".number_format($amountPaid, 2).' paid.')
                ->send();
        }
    }

    public static function updatePaidAmount(Transaction $transaction, Invoice $invoice, float $amountPaid): void
    {
        $currentPivot = (float) ($transaction->invoices()
            ->where('invoices.id', $invoice->id)
            ->first()?->pivot?->amount_paid ?? 0);

        $amountPaid = self::clampPaidAmountForInvoiceOnTransaction(
            $invoice,
            $transaction,
            $amountPaid,
            $currentPivot,
        );

        $transaction->updateInvoicePaidAmount($invoice, $amountPaid);
        app(TransactionDocumentationService::class)->syncAndRecalculate($transaction->fresh());

        Notification::make()
            ->success()
            ->title('Paid amount updated')
            ->send();
    }

    public static function detachInvoice(Transaction $transaction, Invoice $invoice): void
    {
        $transaction->invoices()->detach($invoice->id);
        $invoice->recalculatePaidAmountFromTransactions();
        app(TransactionDocumentationService::class)->syncAndRecalculate($transaction->fresh());

        Notification::make()
            ->success()
            ->title('Invoice removed')
            ->body("{$invoice->name} was unlinked from this transaction.")
            ->send();
    }

    /**
     * @param  array<int, array{invoice_id?: int|string, amount_paid?: float|string}>  $links
     */
    public static function attachLinksFromCreate(Transaction $transaction, array $links): void
    {
        $count = 0;

        foreach ($links as $link) {
            $invoiceId = (int) ($link['invoice_id'] ?? 0);

            if (! $invoiceId) {
                continue;
            }

            self::attachInvoice(
                $transaction,
                $invoiceId,
                (float) ($link['amount_paid'] ?? 0),
                notify: false,
            );
            $count++;
        }

        if ($count > 0) {
            Notification::make()
                ->title('Invoices linked')
                ->body("Linked {$count} invoice(s). Review paid amounts in the Invoices tab.")
                ->info()
                ->send();
        }
    }
}
